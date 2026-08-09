<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use Throwable;

final class TenantRegistryStatusService
{
    private ?PDO $db = null;
    private ?array $villageColumns = null;

    public function statusForHost(string $host): array
    {
        $host = $this->normalizeHost($host);
        if ($host === '') {
            return $this->locked('invalid_host', 'Domain tenant khong hop le');
        }

        $config = $this->config();
        if (!$config['configured']) {
            return $this->locked('registry_not_configured', 'Chua cau hinh Community Control Center Tenant Registry');
        }

        try {
            $row = $this->findTenant($host);
        } catch (Throwable $e) {
            error_log('[TENANT_REGISTRY_CHECK_FAILED] ' . json_encode([
                'host' => $host,
                'sqlstate' => $e instanceof PDOException ? ($e->errorInfo[0] ?? $e->getCode()) : $e->getCode(),
                'code' => (string) $e->getCode(),
            ], JSON_UNESCAPED_SLASHES));
            return $this->locked('registry_unavailable', 'Khong kiem tra duoc trang thai don vi');
        }

        if (!$row) {
            return $this->locked('tenant_not_registered', 'Don vi chua duoc dang ky trong Community Control Center');
        }

        $status = strtoupper((string) ($row['status'] ?? ''));
        if (!empty($row['deleted_at']) || $status === 'DELETED') {
            return $this->locked('tenant_deleted', 'Don vi khong con hoat dong tren Community Control Center', $row);
        }
        $websiteStatus = strtoupper((string) ($row['website_status'] ?? ''));
        $databaseStatus = strtoupper((string) ($row['database_status'] ?? ''));
        if (!empty($row['locked_at']) || !in_array($status, ['READY', 'ACTIVE'], true) || $websiteStatus === 'LOCKED' || $databaseStatus === 'LOCKED') {
            return $this->locked('tenant_locked', 'Don vi dang bi khoa tren Community Control Center', $row);
        }

        return [
            'configured' => true,
            'active' => true,
            'reason' => 'active',
            'tenant' => $this->publicTenant($row),
        ];
    }

    private function findTenant(string $host): ?array
    {
        $subdomain = explode('.', $host)[0] ?? $host;
        $select = implode(', ', [
            'id',
            'code',
            'name',
            'domain',
            'subdomain',
            'status',
            $this->columnSql('connection_status'),
            $this->columnSql('website_status'),
            $this->columnSql('database_status'),
            $this->columnSql('locked_at'),
            $this->columnSql('deleted_at'),
        ]);
        $stmt = $this->db()->prepare(
            'SELECT ' . $select . '
             FROM villages
             WHERE domain = :host OR subdomain = :subdomain
             ORDER BY domain = :host_order DESC, id ASC
             LIMIT 1'
        );
        $stmt->execute([
            'host' => $host,
            'host_order' => $host,
            'subdomain' => $subdomain,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function columnSql(string $column): string
    {
        return in_array($column, $this->villageColumns(), true) ? $column : 'NULL AS ' . $column;
    }

    private function villageColumns(): array
    {
        if ($this->villageColumns !== null) {
            return $this->villageColumns;
        }

        $stmt = $this->db()->query('SHOW COLUMNS FROM villages');
        $columns = [];
        foreach ($stmt->fetchAll() as $row) {
            $field = strtolower((string) ($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[] = $field;
            }
        }
        return $this->villageColumns = $columns;
    }

    private function db(): PDO
    {
        if ($this->db instanceof PDO) {
            return $this->db;
        }

        $config = $this->config();
        if (!empty($config['use_shared_connection'])) {
            return $this->db = Database::pdo();
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
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
            $paths[] = rtrim((string) BASE_PATH, '/\\') . '/.env';
            $paths[] = dirname(rtrim((string) BASE_PATH, '/\\')) . '/.env';
        }

        foreach (array_values(array_unique($paths)) as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }
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
            if ($value !== false && $value !== null && (string) $value !== '') {
                return (string) $value;
            }
        }
        return $default;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        return preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
    }

    private function locked(string $reason, string $message, ?array $tenant = null): array
    {
        return [
            'configured' => true,
            'active' => false,
            'reason' => $reason,
            'message' => $message,
            'tenant' => $tenant ? $this->publicTenant($tenant) : null,
        ];
    }

    private function publicTenant(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'domain' => (string) ($row['domain'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'connectionStatus' => (string) ($row['connection_status'] ?? ''),
            'websiteStatus' => (string) ($row['website_status'] ?? ''),
            'databaseStatus' => (string) ($row['database_status'] ?? ''),
            'lockedAt' => $row['locked_at'] ?? null,
            'deletedAt' => $row['deleted_at'] ?? null,
        ];
    }
}
