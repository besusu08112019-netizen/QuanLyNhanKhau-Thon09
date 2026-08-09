<?php

namespace App\Services;

use PDO;
use PDOException;

final class TenantRegistryStatusService
{
    private ?PDO $db = null;

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
        } catch (PDOException $e) {
            error_log('[TENANT_REGISTRY_CHECK_FAILED] ' . json_encode([
                'host' => $host,
                'sqlstate' => $e->errorInfo[0] ?? $e->getCode(),
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
        if (!empty($row['locked_at']) || !in_array($status, ['READY', 'ACTIVE'], true)) {
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
        $stmt = $this->db()->prepare(
            'SELECT id, code, name, domain, subdomain, status, connection_status, locked_at, deleted_at
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

    private function db(): PDO
    {
        if ($this->db instanceof PDO) {
            return $this->db;
        }

        $config = $this->config();
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
        $database = $this->env(['CONTROL_CENTER_DB_DATABASE', 'CONTROL_CENTER_DB_NAME']);
        $username = $this->env(['CONTROL_CENTER_DB_USERNAME', 'CONTROL_CENTER_DB_USER']);
        return [
            'configured' => $database !== '' && $username !== '',
            'host' => $this->env(['CONTROL_CENTER_DB_HOST'], 'localhost'),
            'port' => (int) $this->env(['CONTROL_CENTER_DB_PORT'], '3306'),
            'database' => $database,
            'username' => $username,
            'password' => $this->env(['CONTROL_CENTER_DB_PASSWORD', 'CONTROL_CENTER_DB_PASS']),
            'charset' => $this->env(['CONTROL_CENTER_DB_CHARSET'], 'utf8mb4'),
        ];
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
            'lockedAt' => $row['locked_at'] ?? null,
            'deletedAt' => $row['deleted_at'] ?? null,
        ];
    }
}
