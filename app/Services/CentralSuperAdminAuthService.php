<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class CentralSuperAdminAuthService
{
    private ?PDO $db = null;

    public function authenticate(string $login, string $password): ?array
    {
        $login = strtolower(trim($login));
        if ($login === '' || strlen($password) > 1024) {
            return null;
        }

        try {
            $user = $this->findCentralSuperAdmin($login);
            if (!$user) {
                return null;
            }
            if ((string) ($user['status'] ?? '') !== 'ACTIVE' || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
                throw new RuntimeException('Invalid account or password');
            }
            return $user;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('[CENTRAL_SUPER_ADMIN_AUTH_ERROR] ' . $e->getMessage());
            return null;
        }
    }

    private function findCentralSuperAdmin(string $login): ?array
    {
        $hasUsername = $this->hasColumn('username');
        $usernameSelect = $hasUsername ? 'username' : 'NULL AS username';
        $usernameCondition = $hasUsername ? ' OR LOWER(username) = :login_username' : '';
        $sql = 'SELECT id, ' . $usernameSelect . ', village_id, email, display_name, password_hash, role, status, last_login_at
                FROM users
                WHERE role = "SUPER_ADMIN"
                  AND status <> "DELETED"
                  AND (LOWER(email) = :login_email' . $usernameCondition . ')
                ORDER BY id ASC
                LIMIT 1';
        $params = ['login_email' => $login];
        if ($hasUsername) {
            $params['login_username'] = $login;
        }
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function hasColumn(string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        $stmt = $this->db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "users" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        $row = $stmt->fetch();
        return $cache[$column] = ((int) ($row['total'] ?? 0) > 0);
    }

    private function db(): PDO
    {
        if ($this->db instanceof PDO) return $this->db;
        $config = $this->config();
        if (!$config['configured'] || !empty($config['use_shared_connection'])) {
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