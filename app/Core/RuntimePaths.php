<?php

namespace App\Core;

final class RuntimePaths
{
    public static function host(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? getenv('APP_HOST') ?: 'localhost')));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
        return $host !== '' ? $host : 'localhost';
    }

    public static function tenantKey(?string $host = null): string
    {
        $key = strtolower(trim($host ?? self::host()));
        $key = preg_replace('/:\d+$/', '', $key) ?? $key;
        $key = preg_replace('/[^a-z0-9._-]/', '', $key) ?? '';
        return $key !== '' ? $key : 'localhost';
    }

    public static function storageRoot(?string $configured = null): string
    {
        return self::ensure($configured ?: BASE_PATH . '/storage/' . self::tenantKey());
    }

    public static function uploadRoot(?string $configured = null): string
    {
        return self::ensure($configured ?: BASE_PATH . '/uploads/' . self::tenantKey());
    }

    public static function cacheRoot(?string $configured = null): string
    {
        return self::ensure($configured ?: self::storageRoot() . '/cache');
    }

    public static function logsRoot(?string $configured = null): string
    {
        return self::ensure($configured ?: self::storageRoot() . '/logs');
    }

    public static function backupRoot(?string $configured = null): string
    {
        return self::ensure($configured ?: BASE_PATH . '/backups/' . self::tenantKey());
    }

    public static function tempRoot(?string $configured = null): string
    {
        return self::ensure($configured ?: self::storageRoot() . '/temp');
    }

    public static function sessionRoot(?string $configured = null): string
    {
        return self::ensure($configured ?: self::storageRoot() . '/sessions');
    }

    public static function ensure(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path !== '' && !is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        return $path;
    }
}
