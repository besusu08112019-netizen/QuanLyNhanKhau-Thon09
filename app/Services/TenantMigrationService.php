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

    public function audit(PDO $pdo, bool $readOnly = false): array
    {
        if (!$readOnly) {
            $this->ensureMigrationTable($pdo);
        }
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
            'migrationHistory' => $this->migrationHistorySummary($pdo),
            'actualSchema' => $this->actualSchemaAudit($pdo),
            'livestock' => $this->livestockAudit($pdo),
            'livestockMigrationCompatibility' => $this->livestockMigrationCompatibility($pdo),
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
        foreach (['villages','users','households','citizens','settings','livestock','livestock_facilities',self::MIGRATION_TABLE] as $table) {
            $summary[$table] = $this->tableExists($pdo, $table);
        }
        return $summary;
    }

    public function actualSchemaAudit(PDO $pdo): array
    {
        $tables = [];
        foreach (['villages','users','households','citizens','settings','platform_settings','rural_clean_water','defense_security_settings','defense_nvqs_records','defense_militia_records','defense_security_force_records','organizations','organization_positions','organization_members','organization_member_history','livestock','livestock_facilities',self::MIGRATION_TABLE] as $table) {
            $exists = $this->tableExists($pdo, $table);
            $tables[$table] = [
                'exists' => $exists,
                'columns' => $exists ? $this->columnDetails($pdo, $table) : [],
                'indexes' => $exists ? $this->indexDetails($pdo, $table) : [],
                'foreignKeys' => $exists ? $this->foreignKeyDetails($pdo, $table) : [],
            ];
        }
        return [
            'tables' => $tables,
            'features' => [
                'platform_settings' => $tables['platform_settings']['exists'],
                'rural_clean_water' => $tables['rural_clean_water']['exists'],
                'defense_security' => $tables['defense_security_settings']['exists'] && $tables['defense_nvqs_records']['exists'] && $tables['defense_militia_records']['exists'] && $tables['defense_security_force_records']['exists'],
                'community_organizations' => $tables['organizations']['exists'] && $tables['organization_positions']['exists'] && $tables['organization_members']['exists'] && $tables['organization_member_history']['exists'],
                'livestock_legacy' => $tables['livestock']['exists'],
                'livestock_facilities' => $tables['livestock_facilities']['exists'],
                'livestock_groups' => $tables['livestock']['exists'] && $this->hasColumns($pdo, 'livestock', ['facility_id', 'animal_group']),
            ],
        ];
    }

    public function livestockAudit(PDO $pdo): array
    {
        $hasLivestock = $this->tableExists($pdo, 'livestock');
        $hasFacilities = $this->tableExists($pdo, 'livestock_facilities');
        $livestockColumns = $hasLivestock ? $this->columns($pdo, 'livestock') : [];
        $facilityColumns = $hasFacilities ? $this->columns($pdo, 'livestock_facilities') : [];
        $indexes = ['livestock' => $hasLivestock ? $this->indexDetails($pdo, 'livestock') : [], 'livestock_facilities' => $hasFacilities ? $this->indexDetails($pdo, 'livestock_facilities') : []];
        $foreignKeys = ['livestock' => $hasLivestock ? $this->foreignKeyDetails($pdo, 'livestock') : [], 'livestock_facilities' => $hasFacilities ? $this->foreignKeyDetails($pdo, 'livestock_facilities') : []];
        $hasQuantity = in_array('quantity', $livestockColumns, true);
        $hasAnimalType = in_array('animal_type', $livestockColumns, true);
        $hasAnimalGroup = in_array('animal_group', $livestockColumns, true);
        $hasHouseholdId = in_array('household_id', $livestockColumns, true);
        $hasStatus = in_array('status', $livestockColumns, true);
        $activeCondition = $hasStatus ? "COALESCE(status,'ACTIVE') <> 'DELETED'" : '1=1';
        $pigCondition = $hasAnimalType ? $this->pigSqlCondition('animal_type') : '0=1';
        $summary = [
            'tables' => ['livestock' => $hasLivestock, 'livestock_facilities' => $hasFacilities],
            'columns' => ['livestock' => $livestockColumns, 'livestock_facilities' => $facilityColumns],
            'indexes' => $indexes,
            'foreignKeys' => $foreignKeys,
            'tenantScope' => [
                'livestock' => ['village_id' => in_array('village_id', $livestockColumns, true), 'tenant_id' => in_array('tenant_id', $livestockColumns, true)],
                'livestock_facilities' => ['village_id' => in_array('village_id', $facilityColumns, true), 'tenant_id' => in_array('tenant_id', $facilityColumns, true)],
            ],
            'facilityRelation' => [
                'column' => in_array('facility_id', $livestockColumns, true),
                'table' => $hasFacilities,
                'index' => $this->hasIndex($indexes['livestock'], 'facility_id'),
                'foreignKey' => $this->hasForeignKey($foreignKeys['livestock'], 'facility_id', 'livestock_facilities'),
            ],
            'hasGroupsNew' => $hasLivestock && $hasFacilities && in_array('facility_id', $livestockColumns, true) && $hasAnimalGroup,
            'records' => 0,
            'households' => 0,
            'with_household_id' => 0,
            'missing_household_id' => 0,
            'total_quantity' => 0.0,
            'pig_records' => 0,
            'pig_total' => 0.0,
            'animal_type_totals' => [],
            'animal_group_totals' => [],
            'pig_group_totals' => [],
        ];
        if (!$hasLivestock) return $summary;
        $summary['records'] = (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE $activeCondition")->fetchColumn();
        if ($hasHouseholdId) {
            $summary['households'] = (int) $pdo->query("SELECT COUNT(DISTINCT household_id) FROM livestock WHERE $activeCondition AND household_id IS NOT NULL AND household_id > 0")->fetchColumn();
            $summary['with_household_id'] = (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE $activeCondition AND household_id IS NOT NULL AND household_id > 0")->fetchColumn();
            $summary['missing_household_id'] = (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE $activeCondition AND (household_id IS NULL OR household_id <= 0)")->fetchColumn();
        }
        if ($hasQuantity) $summary['total_quantity'] = (float) $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM livestock WHERE $activeCondition")->fetchColumn();
        if ($hasAnimalType) $summary['pig_records'] = (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE $activeCondition AND $pigCondition")->fetchColumn();
        if ($hasAnimalType && $hasQuantity) {
            $summary['pig_total'] = (float) $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM livestock WHERE $activeCondition AND $pigCondition")->fetchColumn();
            foreach ($pdo->query("SELECT animal_type, COALESCE(SUM(quantity),0) AS total FROM livestock WHERE $activeCondition GROUP BY animal_type ORDER BY animal_type")->fetchAll() as $row) {
                $summary['animal_type_totals'][] = ['animal_type' => (string) ($row['animal_type'] ?? ''), 'total' => (float) ($row['total'] ?? 0)];
            }
        }
        if ($hasAnimalGroup && $hasQuantity) {
            foreach ($pdo->query("SELECT COALESCE(NULLIF(animal_group,''),'UNCLASSIFIED') AS animal_group, COALESCE(SUM(quantity),0) AS total FROM livestock WHERE $activeCondition GROUP BY COALESCE(NULLIF(animal_group,''),'UNCLASSIFIED') ORDER BY animal_group")->fetchAll() as $row) {
                $summary['animal_group_totals'][] = ['animal_group' => (string) ($row['animal_group'] ?? 'UNCLASSIFIED'), 'total' => (float) ($row['total'] ?? 0)];
            }
            if ($hasAnimalType) {
                foreach ($pdo->query("SELECT COALESCE(NULLIF(animal_group,''),'UNCLASSIFIED') AS animal_group, COALESCE(SUM(quantity),0) AS total FROM livestock WHERE $activeCondition AND $pigCondition GROUP BY COALESCE(NULLIF(animal_group,''),'UNCLASSIFIED') ORDER BY animal_group")->fetchAll() as $row) {
                    $summary['pig_group_totals'][] = ['animal_group' => (string) ($row['animal_group'] ?? 'UNCLASSIFIED'), 'total' => (float) ($row['total'] ?? 0)];
                }
            }
        }
        return $summary;
    }

    public function livestockMigrationCompatibility(PDO $pdo): array
    {
        $audit = $this->livestockAudit($pdo);
        $reasons = [];
        $hasLivestock = (bool) ($audit['tables']['livestock'] ?? false);
        $hasFacilities = (bool) ($audit['tables']['livestock_facilities'] ?? false);
        $records = (int) ($audit['records'] ?? 0);
        $columns = $audit['columns']['livestock'] ?? [];
        if (!$hasLivestock) return ['status' => 'BLOCKED', 'reasons' => ['Missing livestock table; create-livestock baseline must be resolved before facilities/groups migration.']];
        if (($audit['hasGroupsNew'] ?? false) === true) return ['status' => 'ALREADY_APPLIED', 'reasons' => ['livestock_facilities exists and livestock has facility_id + animal_group.']];
        foreach (['id', 'household_id', 'animal_type', 'quantity'] as $required) if (!in_array($required, $columns, true)) $reasons[] = 'Missing required legacy livestock column: ' . $required;
        if (!$this->tableExists($pdo, 'households')) $reasons[] = 'Missing households table required by livestock_facilities household foreign key.';
        elseif (!in_array('id', $this->columns($pdo, 'households'), true)) $reasons[] = 'households table exists but id column is missing.';
        if (in_array('facility_id', $columns, true) && !$hasFacilities) $reasons[] = 'livestock.facility_id exists but livestock_facilities table is missing.';
        if ($hasFacilities && (!in_array('facility_id', $columns, true) || !in_array('animal_group', $columns, true))) $reasons[] = 'livestock_facilities exists but livestock is missing facility_id or animal_group.';
        if ($reasons !== []) return ['status' => 'NEEDS_ADJUSTMENT', 'reasons' => $reasons];
        if ($records === 0) return ['status' => 'NO_LIVESTOCK_DATA', 'reasons' => ['Legacy livestock table exists and is compatible, but contains no active livestock rows.']];
        return ['status' => 'SAFE', 'reasons' => ['Legacy livestock schema has required columns and data can be migrated without inferring pig groups.']];
    }

    private function migrationHistorySummary(PDO $pdo): array
    {
        $exists = $this->tableExists($pdo, self::MIGRATION_TABLE);
        $records = $exists ? (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() : 0;
        return [
            'tableExists' => $exists,
            'records' => $records,
            'done' => $exists ? (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE status="DONE"')->fetchColumn() : 0,
            'failed' => $exists ? (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations WHERE status="FAILED"')->fetchColumn() : 0,
            'emptyHistoryWithSchema' => $records === 0 && ($this->tableExists($pdo, 'villages') || $this->tableExists($pdo, 'households') || $this->tableExists($pdo, 'users')),
        ];
    }

    private function columnDetails(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION');
        $stmt->execute(['table' => $table]);
        return array_map(static fn(array $row): array => ['name' => (string) $row['COLUMN_NAME'], 'type' => (string) $row['COLUMN_TYPE'], 'nullable' => (string) $row['IS_NULLABLE'], 'default' => $row['COLUMN_DEFAULT'], 'key' => (string) $row['COLUMN_KEY'], 'extra' => (string) $row['EXTRA']], $stmt->fetchAll());
    }

    private function indexDetails(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare('SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY INDEX_NAME, SEQ_IN_INDEX');
        $stmt->execute(['table' => $table]);
        $indexes = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = (string) $row['INDEX_NAME'];
            $indexes[$name] ??= ['name' => $name, 'unique' => (int) $row['NON_UNIQUE'] === 0, 'columns' => []];
            $indexes[$name]['columns'][] = (string) $row['COLUMN_NAME'];
        }
        return array_values($indexes);
    }

    private function foreignKeyDetails(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare('SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION');
        $stmt->execute(['table' => $table]);
        return array_map(static fn(array $row): array => ['name' => (string) $row['CONSTRAINT_NAME'], 'column' => (string) $row['COLUMN_NAME'], 'referencesTable' => (string) $row['REFERENCED_TABLE_NAME'], 'referencesColumn' => (string) $row['REFERENCED_COLUMN_NAME']], $stmt->fetchAll());
    }

    private function hasColumns(PDO $pdo, string $table, array $columns): bool
    {
        $existing = $this->columns($pdo, $table);
        foreach ($columns as $column) if (!in_array($column, $existing, true)) return false;
        return true;
    }

    private function hasIndex(array $indexes, string $column): bool
    {
        foreach ($indexes as $index) if (in_array($column, $index['columns'] ?? [], true)) return true;
        return false;
    }

    private function hasForeignKey(array $foreignKeys, string $column, string $referencesTable): bool
    {
        foreach ($foreignKeys as $foreignKey) if (($foreignKey['column'] ?? '') === $column && ($foreignKey['referencesTable'] ?? '') === $referencesTable) return true;
        return false;
    }

    private function pigSqlCondition(string $column): string
    {
        $quoted = '`' . str_replace('`', '``', $column) . '`';
        return '(' . "$quoted = 'Lá»£n' OR $quoted = 'Heo' OR LOWER($quoted) IN ('pig','lon','heo') OR $quoted LIKE '%lá»£n%' OR $quoted LIKE '%heo%' OR $quoted LIKE '%pig%' OR $quoted LIKE '%LÃ¡Â»Â£n%' OR $quoted LIKE '%LÃƒÂ£%'" . ')';
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
