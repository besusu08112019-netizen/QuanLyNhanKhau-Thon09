<?php

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class TenantMigrationService
{
    public const MIGRATION_TABLE = 'schema_migrations';

    public function __construct(private string $migrationPath = BASE_PATH . '/database/migrations')
    {
    }

    public function latestMigrationId(): string
    {
        $migrations = $this->migrations();
        if ($migrations === []) {
            return 'schema.sql';
        }
        $last = end($migrations);
        return (string) $last['id'];
    }

    public function currentAppVersion(): string
    {
        if (defined('APP_ASSET_VERSION')) {
            return (string) APP_ASSET_VERSION;
        }
        if (function_exists('env')) {
            $version = trim((string) \env('APP_VERSION', ''));
            if ($version !== '') {
                return $version;
            }
        }
        return 'dev';
    }

    public function migrations(): array
    {
        if (!is_dir($this->migrationPath)) {
            return [];
        }

        $files = glob(rtrim($this->migrationPath, '/\\') . '/*.sql') ?: [];
        usort($files, static function (string $a, string $b): int {
            return self::migrationSortKey($a) <=> self::migrationSortKey($b) ?: strcmp($a, $b);
        });
        return array_map(static function (string $path): array {
            $id = pathinfo($path, PATHINFO_FILENAME);
            $sql = (string) file_get_contents($path);
            return [
                'id' => $id,
                'path' => $path,
                'checksum' => hash('sha256', $sql),
                'bytes' => strlen($sql),
            ];
        }, $files);
    }

    public function audit(PDO $pdo): array
    {
        $this->ensureMigrationTable($pdo);
        $applied = $this->appliedMigrations($pdo);
        $migrations = $this->migrations();
        $pending = [];
        foreach ($migrations as $migration) {
            $id = (string) $migration['id'];
            if (($applied[$id]['status'] ?? '') !== 'DONE') {
                $pending[] = $migration;
            }
        }

        return [
            'appVersion' => $this->currentAppVersion(),
            'schemaVersion' => $this->latestAppliedVersion($pdo),
            'latestSchemaVersion' => $this->latestMigrationId(),
            'migrationCount' => count($migrations),
            'appliedCount' => count(array_filter($applied, static fn(array $row): bool => ($row['status'] ?? '') === 'DONE')),
            'pendingCount' => count($pending),
            'pending' => array_map(static fn(array $row): string => (string) $row['id'], $pending),
            'tables' => $this->tableSummary($pdo),
        ];
    }

    public function applyPending(PDO $pdo, string $tenantLabel = 'tenant', bool $dryRun = false): array
    {
        $this->ensureMigrationTable($pdo);
        $applied = $this->appliedMigrations($pdo);
        $batch = $this->nextBatch($pdo);
        $results = [];

        foreach ($this->migrations() as $migration) {
            $id = (string) $migration['id'];
            if (($applied[$id]['status'] ?? '') === 'DONE') {
                $results[] = ['id' => $id, 'status' => 'SKIPPED'];
                continue;
            }

            if ($dryRun) {
                $results[] = ['id' => $id, 'status' => 'PENDING'];
                continue;
            }

            $started = microtime(true);
            $this->markStarted($pdo, $migration, $batch);
            try {
                $statements = $this->executeSqlFile($pdo, (string) $migration['path']);
                $this->markDone($pdo, $migration, $statements, (int) round((microtime(true) - $started) * 1000));
                $results[] = ['id' => $id, 'status' => 'DONE', 'statements' => $statements];
            } catch (Throwable $e) {
                $this->markFailed($pdo, $migration, $e);
                throw new RuntimeException($tenantLabel . ': migration ' . $id . ' failed: ' . $e->getMessage(), 0, $e);
            }
        }

        if (!$dryRun) {
            $this->syncLocalVersion($pdo);
        }

        return [
            'tenant' => $tenantLabel,
            'appVersion' => $this->currentAppVersion(),
            'schemaVersion' => $this->latestMigrationId(),
            'results' => $results,
        ];
    }

    public function markFreshInstall(PDO $pdo): array
    {
        $this->ensureMigrationTable($pdo);
        $batch = $this->nextBatch($pdo);
        $count = 0;
        foreach ($this->migrations() as $migration) {
            $stmt = $pdo->prepare(
                'INSERT INTO schema_migrations (migration, checksum, status, batch, app_version, statements_executed, execution_time_ms, started_at, finished_at)
                 VALUES (:migration,:checksum,"DONE",:batch,:app_version,0,0,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE checksum=VALUES(checksum), status="DONE", app_version=VALUES(app_version), finished_at=NOW(), error_message=NULL'
            );
            $stmt->execute([
                'migration' => $migration['id'],
                'checksum' => $migration['checksum'],
                'batch' => $batch,
                'app_version' => $this->currentAppVersion(),
            ]);
            $count++;
        }
        $this->syncLocalVersion($pdo);
        return ['schemaVersion' => $this->latestMigrationId(), 'marked' => $count];
    }

    public function syncLocalVersion(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'villages')) {
            return;
        }
        $columns = $this->columns($pdo, 'villages');
        $sets = [];
        $params = [];
        if (in_array('schema_version', $columns, true)) {
            $sets[] = 'schema_version=:schema_version';
            $params['schema_version'] = $this->latestMigrationId();
        }
        if (in_array('app_version', $columns, true)) {
            $sets[] = 'app_version=:app_version';
            $params['app_version'] = $this->currentAppVersion();
        }
        if (in_array('build_version', $columns, true)) {
            $sets[] = 'build_version=:build_version';
            $params['build_version'] = $this->currentAppVersion();
        }
        if ($sets === []) {
            return;
        }
        $pdo->prepare('UPDATE villages SET ' . implode(', ', $sets))->execute($params);
    }

    public function ensureMigrationTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(190) NOT NULL,
            checksum CHAR(64) NOT NULL,
            status ENUM("RUNNING","DONE","FAILED") NOT NULL DEFAULT "RUNNING",
            batch INT UNSIGNED NOT NULL DEFAULT 1,
            app_version VARCHAR(50) NULL,
            statements_executed INT UNSIGNED NOT NULL DEFAULT 0,
            execution_time_ms INT UNSIGNED NULL,
            error_message TEXT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_schema_migrations_migration (migration),
            KEY idx_schema_migrations_status (status),
            KEY idx_schema_migrations_batch (batch)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function appliedMigrations(PDO $pdo): array
    {
        if (!$this->tableExists($pdo, self::MIGRATION_TABLE)) {
            return [];
        }
        $rows = $pdo->query('SELECT migration, checksum, status, batch, app_version, finished_at FROM schema_migrations')->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['migration']] = $row;
        }
        return $map;
    }

    private function latestAppliedVersion(PDO $pdo): string
    {
        if (!$this->tableExists($pdo, self::MIGRATION_TABLE)) {
            return '';
        }
        return (string) ($pdo->query('SELECT migration FROM schema_migrations WHERE status="DONE" ORDER BY migration DESC LIMIT 1')->fetchColumn() ?: '');
    }

    private function nextBatch(PDO $pdo): int
    {
        if (!$this->tableExists($pdo, self::MIGRATION_TABLE)) {
            return 1;
        }
        return ((int) $pdo->query('SELECT COALESCE(MAX(batch),0) FROM schema_migrations')->fetchColumn()) + 1;
    }

    private function markStarted(PDO $pdo, array $migration, int $batch): void
    {
        $pdo->prepare(
            'INSERT INTO schema_migrations (migration, checksum, status, batch, app_version, started_at, finished_at, error_message)
             VALUES (:migration,:checksum,"RUNNING",:batch,:app_version,NOW(),NULL,NULL)
             ON DUPLICATE KEY UPDATE checksum=VALUES(checksum), status="RUNNING", batch=VALUES(batch), app_version=VALUES(app_version), started_at=NOW(), finished_at=NULL, error_message=NULL'
        )->execute([
            'migration' => $migration['id'],
            'checksum' => $migration['checksum'],
            'batch' => $batch,
            'app_version' => $this->currentAppVersion(),
        ]);
    }

    private function markDone(PDO $pdo, array $migration, int $statements, int $timeMs): void
    {
        $pdo->prepare('UPDATE schema_migrations SET status="DONE", statements_executed=:statements, execution_time_ms=:time_ms, finished_at=NOW(), error_message=NULL WHERE migration=:migration')
            ->execute(['migration' => $migration['id'], 'statements' => $statements, 'time_ms' => $timeMs]);
    }

    private function markFailed(PDO $pdo, array $migration, Throwable $e): void
    {
        $pdo->prepare('UPDATE schema_migrations SET status="FAILED", error_message=:error, finished_at=NOW() WHERE migration=:migration')
            ->execute(['migration' => $migration['id'], 'error' => substr($e->getMessage(), 0, 2000)]);
    }

    private function executeSqlFile(PDO $pdo, string $path): int
    {
        if (!is_file($path)) {
            throw new RuntimeException('Migration file not found: ' . basename($path));
        }
        $count = 0;
        foreach ($this->splitSql((string) file_get_contents($path)) as $statement) {
            $pdo->exec($statement);
            $count++;
        }
        return $count;
    }

    private static function migrationSortKey(string $path): string
    {
        $id = pathinfo($path, PATHINFO_FILENAME);
        if (preg_match('/^(\d{4})[_-]?(\d{2})[_-]?(\d{2})(?:[_-]?(\d{2})(\d{2})(\d{2})?)?/', $id, $matches) === 1) {
            return $matches[1] . $matches[2] . $matches[3]
                . str_pad((string) ($matches[4] ?? ''), 2, '0')
                . str_pad((string) ($matches[5] ?? ''), 2, '0')
                . str_pad((string) ($matches[6] ?? ''), 2, '0');
        }
        return preg_replace('/\D+/', '', $id) ?: $id;
    }

    public function splitSql(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';
            if ($lineComment) {
                if ($char === "\n") $lineComment = false;
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }
            if ($quote === null && $char === '-' && $next === '-' && ($i + 2 >= $length || preg_match('/\s/', $sql[$i + 2]) === 1)) {
                $lineComment = true;
                $i++;
                continue;
            }
            if ($quote === null && $char === '#') {
                $lineComment = true;
                continue;
            }
            if ($quote === null && $char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }
                if ($char === $quote) $quote = null;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') $statements[] = $statement;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $tail = trim($buffer);
        if ($tail !== '') $statements[] = $tail;
        return $statements;
    }

    private function tableSummary(PDO $pdo): array
    {
        $summary = [];
        foreach (['villages','users','households','citizens','settings',self::MIGRATION_TABLE] as $table) {
            $summary[$table] = $this->tableExists($pdo, $table);
        }
        return $summary;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        return array_map('strval', array_column($stmt->fetchAll(), 'COLUMN_NAME'));
    }
}
