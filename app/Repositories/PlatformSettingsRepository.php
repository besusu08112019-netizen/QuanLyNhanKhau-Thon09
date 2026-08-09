<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
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
        if ($this->schemaReady) return;
        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS platform_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                setting_key VARCHAR(120) NOT NULL,
                setting_value TEXT NULL,
                setting_type VARCHAR(30) NOT NULL DEFAULT 'string',
                setting_group VARCHAR(60) NOT NULL,
                is_secret TINYINT(1) NOT NULL DEFAULT 0,
                updated_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_platform_settings_key (setting_key),
                KEY idx_platform_settings_group (setting_group)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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
