<?php

namespace App\Core;

final class PortalContext
{
    public const CONTROL_CENTER = 'CONTROL_CENTER';
    public const TENANT = 'TENANT';
    public const PUBLIC = 'PUBLIC';
    public const API = 'API';
    public const MOBILE = 'MOBILE';

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
        $adminEnabled = self::boolEnv('PLATFORM_ADMIN_ENABLED', false);
        $adminDomains = self::rootAwareAdminDomains();
        $tenantPattern = trim((string) self::env('PLATFORM_TENANT_DOMAIN_PATTERN', ''));
        $defaultPortal = self::portalType((string) self::env('PLATFORM_DEFAULT_PORTAL', self::TENANT), self::TENANT);

        $type = $defaultPortal;
        $matchedBy = 'default';
        $adminDomainMatched = in_array($host, $adminDomains, true);

        if ($adminDomainMatched) {
            if ($adminEnabled) {
                $type = self::CONTROL_CENTER;
                $matchedBy = 'admin_domain';
            } else {
                $type = self::PUBLIC;
                $matchedBy = 'admin_domain_disabled';
            }
        } elseif ($tenantPattern !== '' && self::matchesTenantPattern($host, $tenantPattern)) {
            $type = self::TENANT;
            $matchedBy = 'tenant_pattern';
        }

        return self::$current = [
            'type' => $type,
            'host' => $host,
            'matchedBy' => $matchedBy,
            'adminEnabled' => $adminEnabled,
        ];
    }

    public static function type(): string
    {
        return (string) self::current()['type'];
    }

    public static function isControlCenter(): bool
    {
        return self::type() === self::CONTROL_CENTER;
    }

    public static function isTenant(): bool
    {
        return self::type() === self::TENANT;
    }

    public static function isPublic(): bool
    {
        return self::type() === self::PUBLIC;
    }

    public static function host(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? self::env('APP_HOST', ''))));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
        return $host !== '' ? $host : 'localhost';
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    private static function matchesTenantPattern(string $host, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\{code\}', '[a-z0-9-]+', $pattern);
        return (bool) preg_match('#^' . $pattern . '$#', $host);
    }

    private static function portalType(string $value, string $default): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, [self::CONTROL_CENTER, self::TENANT, self::PUBLIC, self::API, self::MOBILE], true)
            ? $value
            : $default;
    }

    private static function csvEnv(string $key): array
    {
        $raw = (string) self::env($key, '');
        $items = array_map(
            static fn(string $value): string => strtolower(trim(preg_replace('/:\d+$/', '', $value) ?? $value)),
            explode(',', $raw)
        );
        return array_values(array_filter(array_unique($items), static fn(string $value): bool => $value !== ''));
    }

    private static function rootAwareAdminDomains(): array
    {
        $domains = self::csvEnv('PLATFORM_ADMIN_DOMAINS');
        $rootDomain = strtolower(trim((string) self::env('PLATFORM_ROOT_DOMAIN', '')));
        $rootDomain = preg_replace('/:\d+$/', '', $rootDomain) ?? $rootDomain;
        if ($rootDomain !== '') {
            $domains[] = $rootDomain;
            $domains[] = 'www.' . $rootDomain;
        }
        return array_values(array_filter(array_unique($domains), static fn(string $value): bool => $value !== ''));
    }

    private static function boolEnv(string $key, bool $default): bool
    {
        return filter_var(self::env($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private static function env(string $key, mixed $default = ''): mixed
    {
        if (function_exists('env')) {
            return \env($key, $default);
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }
}
