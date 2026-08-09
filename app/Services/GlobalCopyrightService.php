<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

final class GlobalCopyrightService
{
    public const KEY = 'general.copyright';
    public const DEFAULT_VALUE = "Bản quyền thuộc về Thôn 09";

    private ?PDO $db = null;
    private bool $schemaReady = false;

    public function value(): string
    {
        try {
            $this->ensureSchema();
            $stmt = $this->db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = :key LIMIT 1');
            $stmt->execute(['key' => self::KEY]);
            $value = trim((string) ($stmt->fetchColumn() ?: ''));
            return $value !== '' ? $value : self::DEFAULT_VALUE;
        } catch (Throwable $e) {
            error_log('[GLOBAL_COPYRIGHT_FALLBACK] ' . $e->getMessage());
            return self::DEFAULT_VALUE;
        }
    }

    public function upsertDefault(?int $actorId = null): void
    {
        try {
            $this->ensureSchema();
            $stmt = $this->db()->prepare(
                'INSERT INTO platform_settings (setting_key, setting_value, setting_type, setting_group, is_secret, updated_by, created_at, updated_at)
                 VALUES (:setting_key, :setting_value, :setting_type, :setting_group, 0, :updated_by, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE setting_value = CASE WHEN TRIM(COALESCE(setting_value, "")) = "" THEN VALUES(setting_value) ELSE setting_value END, setting_type = VALUES(setting_type), setting_group = VALUES(setting_group), updated_at = updated_at'
            );
            $stmt->execute([
                'setting_key' => self::KEY,
                'setting_value' => self::DEFAULT_VALUE,
                'setting_type' => 'string',
                'setting_group' => 'general',
                'updated_by' => $actorId,
            ]);
        } catch (Throwable $e) {
            error_log('[GLOBAL_COPYRIGHT_DEFAULT_ERROR] ' . $e->getMessage());
        }
    }

    private function ensureSchema(): void
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

    private function db(): PDO
    {
        if ($this->db instanceof PDO) return $this->db;
        $config = $this->config();
        if (!$config['configured']) {
            return $this->db = Database::pdo();
        }
        if (!empty($config['use_shared_connection'])) {
            return $this->db = Database::pdo();
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);
        return $this->db = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
    }

    private function config(): array
    {
        $registryDatabase = $this->env(['CONTROL_CENTER_DB_DATABASE', 'CONTROL_CENTER_DB_NAME', 'TENANT_REGISTRY_DB_DATABASE', 'TENANT_REGISTRY_DB_NAME']);
        $registryUsername = $this->env(['CONTROL_CENTER_DB_USERNAME', 'CONTROL_CENTER_DB_USER', 'TENANT_REGISTRY_DB_USERNAME', 'TENANT_REGISTRY_DB_USER']);
        $baseDatabase = $this->baseEnv(['DB_DATABASE', 'DB_NAME']);
        $baseUsername = $this->baseEnv(['DB_USERNAME', 'DB_USER']);
        $useBaseRegistry = $registryDatabase === '' && $registryUsername === '' && $baseDatabase !== '' && $baseUsername !== '';
        $database = $registryDatabase !== '' ? $registryDatabase : ($baseDatabase !== '' ? $baseDatabase : $this->env(['DB_DATABASE', 'DB_NAME']));
        $username = $registryUsername !== '' ? $registryUsername : ($baseUsername !== '' ? $baseUsername : $this->env(['DB_USERNAME', 'DB_USER']));
        return [
            'configured' => $database !== '' && $username !== '',
            'use_shared_connection' => $registryDatabase === '' && $registryUsername === '' && !$useBaseRegistry,
            'host' => $this->env(['CONTROL_CENTER_DB_HOST', 'TENANT_REGISTRY_DB_HOST'], $useBaseRegistry ? $this->baseEnv(['DB_HOST'], 'localhost') : $this->env(['DB_HOST'], 'localhost')),
            'port' => (int) $this->env(['CONTROL_CENTER_DB_PORT', 'TENANT_REGISTRY_DB_PORT'], $useBaseRegistry ? $this->baseEnv(['DB_PORT'], '3306') : $this->env(['DB_PORT'], '3306')),
            'database' => $database,
            'username' => $username,
            'password' => $this->env(['CONTROL_CENTER_DB_PASSWORD', 'CONTROL_CENTER_DB_PASS', 'TENANT_REGISTRY_DB_PASSWORD', 'TENANT_REGISTRY_DB_PASS'], $useBaseRegistry ? $this->baseEnv(['DB_PASSWORD', 'DB_PASS']) : $this->env(['DB_PASSWORD', 'DB_PASS'])),
            'charset' => $this->env(['CONTROL_CENTER_DB_CHARSET', 'TENANT_REGISTRY_DB_CHARSET'], $useBaseRegistry ? $this->baseEnv(['DB_CHARSET'], 'utf8mb4') : $this->env(['DB_CHARSET'], 'utf8mb4')),
        ];
    }

    private function baseEnv(array $keys, string $default = ''): string
    {
        $paths = [];
        if (defined('BASE_PATH')) {
            $base = rtrim((string) BASE_PATH, '/\\');
            $paths[] = $base . '/.env';
            $paths[] = dirname($base) . '/.env';
        }
        foreach (array_values(array_unique($paths)) as $path) {
            if (!is_file($path) || !is_readable($path)) continue;
            $values = parse_ini_file($path, false, INI_SCANNER_RAW) ?: [];
            foreach ($keys as $key) {
                if (isset($values[$key]) && trim((string) $values[$key]) !== '') {
                    return trim((string) $values[$key], " \t\n\r\0\x0B\"'");
                }
            }
        }
        return $default;
    }

    private function env(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = function_exists('env') ? \env($key) : getenv($key);
            if ($value !== false && $value !== null && (string) $value !== '') return (string) $value;
        }
        return $default;
    }
}
