<?php

$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/' || $path === '/index.php') {
    $html = (string) file_get_contents($root . '/views/app.php');
    $settings = [
        'tenantNamespace' => 'playwright_tenant',
        'systemName' => 'Playwright Test',
        'hamletName' => 'Thon test',
        'idleTimeoutSeconds' => 21600,
        'idleWarningSeconds' => 60,
    ];
    $replacements = [
        '{{APP_SETTINGS_JSON}}' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        '{{APP_NAME}}' => 'Playwright Test',
        '{{THEME_COLOR}}' => '#0f8a3b',
        '{{BACKGROUND_COLOR}}' => '#ffffff',
        '{{LOGIN_BACKGROUND_STYLE}}' => '',
        '{{UNIT_NAME}}' => 'Thon test',
        '{{TENANT_LOGO_CLASS}}' => 'tenant-logo-placeholder',
        '{{TENANT_LOGO_HTML}}' => 'PT',
        '{{HAMLET_NAME}}' => 'Thon test',
        '{{COMMUNE_NAME}}' => 'Xa test',
        '{{COPYRIGHT}}' => 'Playwright',
    ];

    header('Content-Type: text/html; charset=UTF-8');
    echo strtr($html, $replacements);
    return true;
}

$file = realpath($root . $path);
if ($file !== false && str_starts_with($file, $root) && is_file($file)) {
    return false;
}

require $root . '/index.php';
