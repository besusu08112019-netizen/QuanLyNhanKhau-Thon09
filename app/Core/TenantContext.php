<?php

namespace App\Core;

use Throwable;

final class TenantContext
{
    private static ?array $current = null;

    public static function boot(): void
    {
        self::current();
    }

    public static function current(): array
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $host = self::host();
        $fallbackCode = self::env('TENANT_DEFAULT_VILLAGE_CODE', self::env('TENANT_CODE', 'default'));

        try {
            $pdo = Database::pdo();
            if (!self::tableExists('villages')) {
                return self::$current = self::fallback($host, $fallbackCode);
            }

            $stmt = $pdo->prepare(
                'SELECT * FROM villages
                 WHERE status IN ("ACTIVE", "READY")
                   AND (domain = :host OR subdomain = :host OR code = :fallback_code)
                 ORDER BY CASE WHEN domain = :host THEN 0 WHEN subdomain = :host THEN 1 ELSE 2 END
                 LIMIT 1'
            );
            $stmt->execute(['host' => $host, 'fallback_code' => $fallbackCode]);
            $row = $stmt->fetch();

            if ($row) {
                return self::$current = self::normalize($row, $host);
            }
        } catch (Throwable $e) {
            error_log('[TENANT_CONTEXT_FALLBACK] ' . $e->getMessage());
        }

        return self::$current = self::fallback($host, $fallbackCode);
    }

    public static function id(): int
    {
        return (int) (self::current()['id'] ?? 0);
    }

    public static function villageId(): int
    {
        return self::id();
    }

    public static function host(): string
    {
        return TenantResolver::host((string) ($_SERVER['HTTP_HOST'] ?? self::env('APP_HOST', '')));
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    private static function normalize(array $row, string $host): array
    {
        return [
            'id' => (int) ($row['id'] ?? 1),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'unit_name' => (string) ($row['unit_name'] ?? ''),
            'commune_name' => (string) ($row['commune_name'] ?? ''),
            'domain' => (string) ($row['domain'] ?? $host),
            'subdomain' => (string) ($row['subdomain'] ?? ''),
            'logo_url' => (string) ($row['logo_url'] ?? ''),
            'theme_color' => (string) ($row['theme_color'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'status' => (string) ($row['status'] ?? 'ACTIVE'),
        ];
    }

    private static function fallback(string $host, string $code): array
    {
        $hamlet = self::env('TENANT_HAMLET_NAME', self::env('TENANT_NAME', ''));
        return [
            'id' => (int) self::env('TENANT_DEFAULT_VILLAGE_ID', 0),
            'code' => $code !== '' ? $code : 'default',
            'name' => $hamlet,
            'unit_name' => self::env('TENANT_UNIT_NAME', $hamlet),
            'commune_name' => self::env('TENANT_COMMUNE_NAME', ''),
            'domain' => $host,
            'subdomain' => $host,
            'logo_url' => self::env('TENANT_LOGO_URL', ''),
            'theme_color' => self::env('TENANT_THEME_COLOR', ''),
            'address' => self::env('TENANT_ADDRESS', ''),
            'phone' => self::env('TENANT_PHONE', ''),
            'email' => self::env('TENANT_EMAIL', ''),
            'status' => 'ACTIVE',
        ];
    }

    private static function tableExists(string $table): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        return (int) ($stmt->fetch()['total'] ?? 0) > 0;
    }

    private static function env(string $key, mixed $default = ''): mixed
    {
        $envFile = BASE_PATH . '/config/env.php';
        if (is_file($envFile)) {
            require_once $envFile;
        }
        return \env($key, $default);
    }
}
