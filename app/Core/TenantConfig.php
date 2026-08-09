<?php

namespace App\Core;

use App\Services\GlobalCopyrightService;
use App\Services\PlatformBrandingService;
use Throwable;

final class TenantConfig
{
    private static ?array $databaseSettings = null;
    private static ?array $publicSettings = null;

    public static function defaults(): array
    {
        self::loadEnv();
        $village = TenantContext::current();
        $hamlet = self::env('TENANT_HAMLET_NAME', (string) ($village['name'] ?? ''));
        $commune = self::env('TENANT_COMMUNE_NAME', (string) ($village['commune_name'] ?? ''));
        $unit = self::env('TENANT_UNIT_NAME', (string) ($village['unit_name'] ?? self::joinUnit($hamlet, $commune)));
        $systemName = self::env('APP_NAME', self::env('TENANT_SYSTEM_NAME', 'Hệ thống Quản lý Hành chính'));
        $copyright = self::globalCopyright();
        $platformBranding = self::platformBranding();
        $tenantLogoUrl = self::env('TENANT_LOGO_URL', (string) ($village['logo_url'] ?? ''));
        if ($tenantLogoUrl === '') {
            $tenantLogoUrl = (string) ($platformBranding['default_tenant_logo']['url'] ?? '');
        }
        $tenantBackgroundUrl = self::env('TENANT_BACKGROUND_URL', '');
        if ($tenantBackgroundUrl === '') {
            $tenantBackgroundUrl = (string) ($platformBranding['default_login_background']['url'] ?? '');
        }

        return [
            'systemName' => $systemName,
            'logoUrl' => $tenantLogoUrl,
            'backgroundUrl' => $tenantBackgroundUrl,
            'backgroundImages' => self::env('TENANT_BACKGROUND_IMAGES', ''),
            'backgroundInterval' => self::env('TENANT_BACKGROUND_INTERVAL', '6000'),
            'introImageUrl' => self::env('TENANT_INTRO_IMAGE_URL', ''),
            'unitName' => $unit,
            'hamletName' => $hamlet,
            'communeName' => $commune,
            'slogan' => self::env('TENANT_SLOGAN', 'Vì Nhân dân phục vụ'),
            'softwareVersion' => self::env('APP_VERSION', 'v2.0'),
            'introTitle' => self::env('TENANT_INTRO_TITLE', ''),
            'historyTitle' => self::env('TENANT_HISTORY_TITLE', ''),
            'hamletHistory' => self::env('TENANT_HISTORY', ''),
            'introduction' => self::env('TENANT_INTRODUCTION', ''),
            'phone' => self::env('TENANT_PHONE', (string) ($village['phone'] ?? '')),
            'email' => self::env('TENANT_EMAIL', (string) ($village['email'] ?? '')),
            'address' => self::env('TENANT_ADDRESS', (string) ($village['address'] ?? '')),
            'website' => self::env('TENANT_WEBSITE', self::env('APP_URL', '')),
            'copyright' => $copyright,
            'reportSigner' => self::env('TENANT_REPORT_SIGNER', ''),
            'supportEmail' => self::env('SUPPORT_EMAIL', ''),
            'maintenanceMessage' => self::env('MAINTENANCE_MESSAGE', ''),
            'themeColor' => self::env('TENANT_THEME_COLOR', (string) ($village['theme_color'] ?? '#0b6b3a')),
            'backgroundColor' => self::env('TENANT_BACKGROUND_COLOR', '#eef3f8'),
            'manifestId' => self::env('TENANT_MANIFEST_ID', '/pwa/app'),
        ];
    }

    public static function publicSettings(?array $settings = null): array
    {
        if ($settings === null && self::$publicSettings !== null) return self::$publicSettings;

        $merged = self::defaults();
        foreach (($settings ?? self::databaseSettings()) as $key => $value) {
            if ($key === 'copyright') continue;
            if ($value !== null && $value !== '') $merged[$key] = $value;
        }

        $merged = self::normalizeDisplaySettings($merged);
        $merged['copyright'] = self::globalCopyright();
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

        return trim((string) ($settings['systemName'] ?? '')) ?: 'Đơn vị hành chính';
    }


    private static function normalizeDisplaySettings(array $settings): array
    {
        $legacyValues = [
            'reportTitlePrefix' => [
                'Quan ly nhan khau' => 'Quản lý nhân khẩu',
            ],
            'systemName' => [
                'He thong Quan ly Hanh chinh' => 'Hệ thống Quản lý Hành chính',
                'Há»‡ thá»‘ng Quáº£n lÃ½ HÃ nh chÃ­nh' => 'Hệ thống Quản lý Hành chính',
            ],
            'slogan' => [
                'Vi Nhan dan phuc vu' => 'Vì Nhân dân phục vụ',
            ],
        ];

        foreach ($legacyValues as $key => $map) {
            $value = (string) ($settings[$key] ?? '');
            if ($value !== '' && isset($map[$value])) {
                $settings[$key] = $map[$value];
            }
        }

        return $settings;
    }

    private static function globalCopyright(): string
    {
        return (new GlobalCopyrightService())->value();
    }

    private static function platformBranding(): array
    {
        try {
            return (new PlatformBrandingService())->publicBranding();
        } catch (Throwable) {
            return [];
        }
    }
    private static function databaseSettings(): array
    {
        if (self::$databaseSettings !== null) return self::$databaseSettings;

        try {
            $pdo = Database::pdo();
            if (self::columnExists('settings', 'village_id')) {
                $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings WHERE village_id = :village_id');
                $stmt->execute(['village_id' => TenantContext::id()]);
            } else {
                $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
            }

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
        self::loadEnv();
        return (string) \env($key, $default);
    }

    private static function loadEnv(): void
    {
        $envFile = BASE_PATH . '/config/env.php';
        if (is_file($envFile)) require_once $envFile;
        \env_load(BASE_PATH);
    }

    private static function columnExists(string $table, string $column): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int) ($stmt->fetch()['total'] ?? 0) > 0;
    }

    private static function joinUnit(string $hamlet, string $commune): string
    {
        $parts = array_values(array_filter([trim($hamlet), trim($commune)], static fn(string $value): bool => $value !== ''));
        return implode(' - ', $parts);
    }
}
