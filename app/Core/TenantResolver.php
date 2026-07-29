<?php

namespace App\Core;

final class TenantResolver
{
    public static function host(?string $host = null): string
    {
        $host = strtolower(trim((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? getenv('APP_HOST') ?: ''))));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
        return $host !== '' ? $host : 'localhost';
    }

    public static function tenantCodeFromHost(?string $host = null, ?string $rootDomain = null): string
    {
        $host = self::host($host);
        $rootDomain = self::host($rootDomain ?? getenv('PLATFORM_ROOT_DOMAIN') ?: '');

        if ($rootDomain !== 'localhost' && ($host === $rootDomain || $host === 'www.' . $rootDomain)) {
            return 'control-center';
        }

        if ($rootDomain !== 'localhost' && str_ends_with($host, '.' . $rootDomain)) {
            $subdomain = substr($host, 0, -1 * (strlen($rootDomain) + 1));
            $parts = array_values(array_filter(explode('.', $subdomain), static fn(string $value): bool => $value !== ''));
            return self::normalizeCode((string) end($parts));
        }

        $firstLabel = explode('.', $host)[0] ?? $host;
        return self::normalizeCode($firstLabel);
    }

    public static function candidateKeys(?string $host = null): array
    {
        $host = self::host($host);
        $code = self::tenantCodeFromHost($host);
        $keys = [$host];
        if ($code !== '' && $code !== $host) {
            $keys[] = $code;
        }
        return array_values(array_unique(array_filter($keys)));
    }

    private static function normalizeCode(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';
        return $value !== '' ? $value : 'default';
    }
}
