<?php

namespace App\Core;

use Throwable;

final class TenantConfig
{
    private static ?array $databaseSettings = null;
    private static ?array $publicSettings = null;
    private static bool $envLoaded = false;

    public static function defaults(): array
    {
        self::loadEnvFiles();
        $hamlet = self::env('TENANT_HAMLET_NAME', '');
        $commune = self::env('TENANT_COMMUNE_NAME', '');
        $unit = self::env('TENANT_UNIT_NAME', self::joinUnit($hamlet, $commune));
        $systemName = self::env('APP_NAME', self::env('TENANT_SYSTEM_NAME', 'He thong Quan ly Hanh chinh'));
        $copyright = self::env('TENANT_COPYRIGHT', $unit !== '' ? '© ' . $unit : '');

        return [
            'systemName' => $systemName,
            'logoUrl' => self::env('TENANT_LOGO_URL', ''),
            'backgroundUrl' => self::env('TENANT_BACKGROUND_URL', ''),
            'backgroundImages' => self::env('TENANT_BACKGROUND_IMAGES', ''),
            'backgroundInterval' => self::env('TENANT_BACKGROUND_INTERVAL', '6000'),
            'introImageUrl' => self::env('TENANT_INTRO_IMAGE_URL', ''),
            'unitName' => $unit,
            'hamletName' => $hamlet,
            'communeName' => $commune,
            'slogan' => self::env('TENANT_SLOGAN', 'Vi Nhan dan phuc vu'),
            'softwareVersion' => self::env('APP_VERSION', 'v2.0'),
            'introTitle' => self::env('TENANT_INTRO_TITLE', ''),
            'historyTitle' => self::env('TENANT_HISTORY_TITLE', ''),
            'hamletHistory' => self::env('TENANT_HISTORY', ''),
            'introduction' => self::env('TENANT_INTRODUCTION', ''),
            'phone' => self::env('TENANT_PHONE', ''),
            'email' => self::env('TENANT_EMAIL', ''),
            'address' => self::env('TENANT_ADDRESS', ''),
            'website' => self::env('TENANT_WEBSITE', self::env('APP_URL', '')),
            'copyright' => $copyright,
            'reportSigner' => self::env('TENANT_REPORT_SIGNER', ''),
            'supportEmail' => self::env('SUPPORT_EMAIL', ''),
            'maintenanceMessage' => self::env('MAINTENANCE_MESSAGE', ''),
            'themeColor' => self::env('TENANT_THEME_COLOR', '#0b6b3a'),
            'backgroundColor' => self::env('TENANT_BACKGROUND_COLOR', '#eef3f8'),
            'manifestId' => self::env('TENANT_MANIFEST_ID', '/pwa/app'),
        ];
    }

    public static function publicSettings(?array $settings = null): array
    {
        if ($settings === null && self::$publicSettings !== null) return self::$publicSettings;

        $merged = self::defaults();
        foreach (($settings ?? self::databaseSettings()) as $key => $value) {
            if ($value !== null && $value !== '') $merged[$key] = $value;
        }

        $merged['unitName'] = self::unitName($merged);
        $merged['appName'] = $merged['systemName'];
        $merged['contactAddress'] = $merged['address'] ?? '';
        $merged['contactPhone'] = $merged['phone'] ?? '';
        $merged['contactEmail'] = $merged['email'] ?? '';
        $merged['contactWebsite'] = $merged['website'] ?? '';

        if ($settings === null) self::$publicSettings = $merged;
        return $merged;
    }

    public static function setting(string $key, string $default = ''): string
    {
        $value = trim((string) (self::publicSettings()[$key] ?? ''));
        return $value !== '' ? $value : $default;
    }

    public static function unitName(?array $settings = null): string
    {
        $settings ??= self::publicSettings();
        $unit = trim((string) ($settings['unitName'] ?? ''));
        if ($unit !== '') return $unit;

        $combined = self::joinUnit((string) ($settings['hamletName'] ?? ''), (string) ($settings['communeName'] ?? ''));
        if ($combined !== '') return $combined;

        return trim((string) ($settings['systemName'] ?? '')) ?: 'Don vi hanh chinh';
    }

    private static function databaseSettings(): array
    {
        if (self::$databaseSettings !== null) return self::$databaseSettings;

        try {
            $stmt = Database::pdo()->query('SELECT setting_key, setting_value FROM settings');
            $settings = [];
            foreach ($stmt->fetchAll() as $row) {
                $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
            return self::$databaseSettings = $settings;
        } catch (Throwable) {
            return self::$databaseSettings = [];
        }
    }

    private static function env(string $key, string $default = ''): string
    {
        self::loadEnvFiles();
        $value = getenv($key);
        if ($value !== false && $value !== '') return (string) $value;
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return (string) $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return (string) $_SERVER[$key];
        return $default;
    }

    private static function loadEnvFiles(): void
    {
        if (self::$envLoaded) return;
        self::$envLoaded = true;

        foreach ([BASE_PATH . '/.env', dirname(BASE_PATH) . '/.env'] as $path) {
            if (!is_file($path) || !is_readable($path)) continue;
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if ($key === '') continue;
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (getenv($key) === false || getenv($key) === '') {
                    putenv($key . '=' . $value);
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }

    private static function joinUnit(string $hamlet, string $commune): string
    {
        $parts = array_values(array_filter([trim($hamlet), trim($commune)], static fn(string $value): bool => $value !== ''));
        return implode(' - ', $parts);
    }
}
