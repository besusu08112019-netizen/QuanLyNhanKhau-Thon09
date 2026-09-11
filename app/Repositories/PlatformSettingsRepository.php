<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class PlatformSettingsRepository
{
    private ?PDO $db = null;
    private bool $schemaReady = false;

    public function all(): array
    {
        $this->ensureSchema();
        $stmt = $this->db()->query('SELECT setting_key, setting_value, setting_type, setting_group, is_secret, updated_by, updated_at FROM platform_settings ORDER BY setting_group, setting_key');
        return $stmt->fetchAll() ?: [];
    }

    public function find(string $key): ?array
    {
        $this->ensureSchema();
        $stmt = $this->db()->prepare('SELECT setting_key, setting_value, setting_type, setting_group, is_secret, updated_by, updated_at FROM platform_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $this->ensureSchema();
        $row = $this->find($key);
        if (!$row) return $default;
        return $this->castValue((string) $row['setting_value'], (string) $row['setting_type']);
    }

    public function upsert(string $key, mixed $value, string $type, string $group, bool $secret, ?int $actorId): void
    {
        $this->ensureSchema();
        $stmt = $this->db()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, setting_type, setting_group, is_secret, updated_by, created_at, updated_at)
             VALUES (:setting_key, :setting_value, :setting_type, :setting_group, :is_secret, :updated_by, NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), setting_group = VALUES(setting_group), is_secret = VALUES(is_secret), updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $this->serializeValue($value, $type),
            'setting_type' => $type,
            'setting_group' => $group,
            'is_secret' => $secret ? 1 : 0,
            'updated_by' => $actorId,
        ]);
    }

    public function health(): array
    {
        try {
            $this->ensureSchema();
            $version = (string) $this->db()->query('SELECT VERSION()')->fetchColumn();
            $database = (string) $this->db()->query('SELECT DATABASE()')->fetchColumn();
            $villages = $this->tableExists('villages') ? $this->countRows('villages') : null;
            return [
                'ok' => true,
                'status' => 'OK',
                'database' => $database,
                'databaseVersion' => $version,
                'settingsTable' => true,
                'villagesTable' => $villages !== null,
                'tenantCount' => $villages,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 'ERROR', 'message' => 'Không kiểm tra được Central Registry'];
        }
    }

    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
    }

    private function assertSchemaReady(): void
    {
        if ($this->schemaReady) return;

        if (!$this->tableExists('platform_settings')) {
            throw new RuntimeException('PLATFORM_SETTINGS_SCHEMA_NOT_READY: platform_settings table is missing; controlled provisioning required');
        }

        $columns = $this->platformSettingsColumns();
        $indexes = $this->platformSettingsIndexes();
        $missing = [];

        foreach ([
            'id' => ['type' => 'bigint unsigned', 'nullable' => 'NO', 'extra' => 'auto_increment'],
            'setting_key' => ['type' => 'varchar(120)', 'nullable' => 'NO'],
            'setting_value' => ['type' => 'text', 'nullable' => 'YES'],
            'setting_type' => ['type' => 'varchar(30)', 'nullable' => 'NO', 'default' => 'string'],
            'setting_group' => ['type' => 'varchar(60)', 'nullable' => 'NO'],
            'is_secret' => ['type' => 'tinyint(1)', 'nullable' => 'NO', 'default' => '0'],
            'updated_by' => ['type' => 'bigint unsigned', 'nullable' => 'YES'],
            'created_at' => ['type' => 'timestamp', 'nullable' => 'YES'],
            'updated_at' => ['type' => 'timestamp', 'nullable' => 'YES'],
        ] as $name => $expected) {
            $column = $columns[$name] ?? null;
            if (!$column) {
                $missing[] = 'column ' . $name;
                continue;
            }
            if (strtolower((string) $column['COLUMN_TYPE']) !== $expected['type']) {
                $missing[] = 'column ' . $name . ' type';
            }
            if ((string) $column['IS_NULLABLE'] !== $expected['nullable']) {
                $missing[] = 'column ' . $name . ' nullability';
            }
            if (isset($expected['default']) && (string) ($column['COLUMN_DEFAULT'] ?? '') !== $expected['default']) {
                $missing[] = 'column ' . $name . ' default';
            }
            if (isset($expected['extra']) && !str_contains(strtolower((string) $column['EXTRA']), $expected['extra'])) {
                $missing[] = 'column ' . $name . ' extra';
            }
        }

        if (!$this->indexMatches($indexes, 'PRIMARY', false, ['id'])) {
            $missing[] = 'primary key id';
        }
        if (!$this->indexMatches($indexes, 'uq_platform_settings_key', false, ['setting_key'])) {
            $missing[] = 'unique key uq_platform_settings_key';
        }
        if (!$this->indexMatches($indexes, 'idx_platform_settings_group', true, ['setting_group'])) {
            $missing[] = 'index idx_platform_settings_group';
        }

        if ($missing) {
            throw new RuntimeException('PLATFORM_SETTINGS_SCHEMA_NOT_READY: ' . implode(', ', $missing) . '; controlled provisioning required');
        }

        $this->schemaReady = true;
    }

    public function castValue(?string $value, string $type): mixed
    {
        if ($type === 'boolean') return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        if ($type === 'integer') return (int) $value;
        if ($type === 'json') {
            $decoded = json_decode((string) $value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return (string) ($value ?? '');
    }

    private function serializeValue(mixed $value, string $type): string
    {
        if ($type === 'boolean') return $value ? '1' : '0';
        if ($type === 'integer') return (string) max(0, (int) $value);
        if ($type === 'json') return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        return (string) $value;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        $row = $stmt->fetch();
        return (int) ($row['total'] ?? 0) > 0;
    }

    private function platformSettingsColumns(): array
    {
        $stmt = $this->db()->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => 'platform_settings']);
        $columns = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $columns[(string) $row['COLUMN_NAME']] = $row;
        }
        return $columns;
    }

    private function platformSettingsIndexes(): array
    {
        $stmt = $this->db()->prepare(
            'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
             ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        $stmt->execute(['table' => 'platform_settings']);
        $indexes = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $name = (string) $row['INDEX_NAME'];
            $indexes[$name]['nonUnique'] = (bool) $row['NON_UNIQUE'];
            $indexes[$name]['columns'][] = (string) $row['COLUMN_NAME'];
        }
        return $indexes;
    }

    private function indexMatches(array $indexes, string $name, bool $nonUnique, array $columns): bool
    {
        $index = $indexes[$name] ?? null;
        if (!$index) return false;
        return $index['nonUnique'] === $nonUnique && ($index['columns'] ?? []) === $columns;
    }

    private function countRows(string $table): int
    {
        $safeTable = str_replace('`', '', $table);
        $row = $this->db()->query('SELECT COUNT(*) AS total FROM `' . $safeTable . '`')->fetch();
        return (int) ($row['total'] ?? 0);
    }

    private function db(): PDO
    {
        return $this->db ??= Database::pdo();
    }
}
