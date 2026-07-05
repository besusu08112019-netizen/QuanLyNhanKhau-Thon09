<?php

define('BASE_PATH', __DIR__);
define('APP_ROOT', __DIR__);
define('APP_ASSET_VERSION', '20260705-frontend-polish-1');

require_once BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Request;
use App\Core\Router;
use App\Core\Response;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\DashboardController;
use App\Controllers\FileController;
use App\Controllers\GisController;
use App\Controllers\HouseholdController;
use App\Controllers\ImportController;
use App\Controllers\InsightController;
use App\Controllers\LogController;
use App\Controllers\MovementController;
use App\Controllers\PermissionController;
use App\Controllers\PersonController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\UserController;

Autoloader::register();

$request = Request::capture();
if ($request->path() === '/favicon.ico') {
    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=604800');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0a8f4d"/><text x="32" y="40" text-anchor="middle" font-size="24" font-family="Arial, sans-serif" font-weight="700" fill="#ffffff">09</text></svg>';
    exit;
}
$router = new Router($request);

$router->get('/api/public/login-config', [SettingController::class, 'publicLoginConfig']);

$router->post('/api/setup', [AuthController::class, 'setup']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/logout', [AuthController::class, 'logout']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/me', [AuthController::class, 'me']);

$router->get('/api/dashboard', [DashboardController::class, 'summary']);
$router->get('/api/dashboard/summary', [DashboardController::class, 'summary']);
$router->get('/api/dashboard/population-chart', [DashboardController::class, 'populationChart']);
$router->get('/api/dashboard/household-chart', [DashboardController::class, 'householdChart']);
$router->get('/api/dashboard/age-chart', [DashboardController::class, 'ageChart']);

$router->get('/api/households', [HouseholdController::class, 'index']);
$router->post('/api/households', [HouseholdController::class, 'store']);
$router->get('/api/households/{id}', [HouseholdController::class, 'show']);
$router->put('/api/households/{id}', [HouseholdController::class, 'update']);
$router->delete('/api/households/{id}', [HouseholdController::class, 'destroy']);
$router->post('/api/households/bulk-delete', [HouseholdController::class, 'bulkDelete']);

$router->get('/api/citizens', [PersonController::class, 'index']);
$router->post('/api/citizens', [PersonController::class, 'store']);
$router->get('/api/citizens/{id}', [PersonController::class, 'show']);
$router->put('/api/citizens/{id}', [PersonController::class, 'update']);
$router->delete('/api/citizens/{id}', [PersonController::class, 'destroy']);
$router->post('/api/citizens/bulk-delete', [PersonController::class, 'bulkDelete']);
$router->post('/api/citizens/{id}/restore', [PersonController::class, 'restore']);

$router->get('/api/persons', [PersonController::class, 'index']);
$router->post('/api/persons', [PersonController::class, 'store']);
$router->get('/api/persons/{id}', [PersonController::class, 'show']);
$router->put('/api/persons/{id}', [PersonController::class, 'update']);
$router->delete('/api/persons/{id}', [PersonController::class, 'destroy']);
$router->post('/api/persons/bulk-delete', [PersonController::class, 'bulkDelete']);
$router->post('/api/persons/{id}/restore', [PersonController::class, 'restore']);

$router->get('/api/temporary-residence', [PersonController::class, 'temporaryResidence']);
$router->get('/api/temporary-absence', [PersonController::class, 'temporaryAbsence']);

$router->get('/api/movements', [MovementController::class, 'index']);
$router->post('/api/movements', [MovementController::class, 'store']);
$router->get('/api/movements/types', [MovementController::class, 'types']);
$router->get('/api/movements/{id}', [MovementController::class, 'show']);
$router->put('/api/movements/{id}', [MovementController::class, 'update']);
$router->delete('/api/movements/{id}', [MovementController::class, 'destroy']);

$router->get('/api/import/template', [ImportController::class, 'template']);
$router->post('/api/import/preview', [ImportController::class, 'preview']);
$router->post('/api/import/process', [ImportController::class, 'process']);
$router->post('/api/import/check', [ImportController::class, 'preview']);
$router->post('/api/import/execute', [ImportController::class, 'process']);

$router->get('/api/reports', [ReportController::class, 'summary']);
$router->get('/api/reports/summary', [ReportController::class, 'summary']);
$router->get('/api/reports/population', [ReportController::class, 'population']);
$router->get('/api/reports/household', [ReportController::class, 'household']);
$router->get('/api/reports/temporary-residence', [ReportController::class, 'temporaryResidence']);
$router->get('/api/reports/temporary-absence', [ReportController::class, 'temporaryAbsence']);
$router->get('/api/reports/births', [ReportController::class, 'births']);
$router->get('/api/reports/deaths', [ReportController::class, 'deaths']);
$router->get('/api/reports/migration', [ReportController::class, 'migration']);
$router->get('/api/reports/export-excel', [ReportController::class, 'exportExcel']);
$router->get('/api/reports/print', [ReportController::class, 'print']);
$router->get('/api/reports/export-pdf', [ReportController::class, 'exportPdf']);
$router->get('/api/export/excel', [ReportController::class, 'exportExcel']);

$router->get('/api/accounts', [UserController::class, 'index']);
$router->post('/api/accounts', [UserController::class, 'store']);
$router->get('/api/accounts/{id}', [UserController::class, 'show']);
$router->put('/api/accounts/{id}', [UserController::class, 'update']);
$router->delete('/api/accounts/{id}', [UserController::class, 'destroy']);
$router->post('/api/accounts/{id}/lock', [UserController::class, 'lock']);
$router->post('/api/accounts/{id}/unlock', [UserController::class, 'unlock']);
$router->get('/api/users', [UserController::class, 'index']);
$router->post('/api/users', [UserController::class, 'store']);
$router->get('/api/users/{id}', [UserController::class, 'show']);
$router->put('/api/users/{id}', [UserController::class, 'update']);
$router->delete('/api/users/{id}', [UserController::class, 'destroy']);
$router->post('/api/users/{id}/lock', [UserController::class, 'lock']);
$router->post('/api/users/{id}/unlock', [UserController::class, 'unlock']);
$router->get('/api/roles', [UserController::class, 'roles']);
$router->get('/api/permissions', [PermissionController::class, 'index']);
$router->put('/api/permissions', [PermissionController::class, 'update']);
$router->post('/api/permissions', [PermissionController::class, 'update']);

$router->get('/api/system/logs', [LogController::class, 'index']);
$router->get('/api/logs', [LogController::class, 'index']);
$router->get('/api/system/settings', [SettingController::class, 'index']);
$router->put('/api/system/settings', [SettingController::class, 'update']);
$router->get('/api/system/interface', [SettingController::class, 'index']);
$router->put('/api/system/interface', [SettingController::class, 'update']);
$router->post('/api/system/interface/upload', [SettingController::class, 'uploadMedia']);
$router->delete('/api/system/interface/asset', [SettingController::class, 'deleteMedia']);
$router->get('/api/system/interface/media', [SettingController::class, 'media']);
$router->get('/api/settings', [SettingController::class, 'index']);
$router->post('/api/settings', [SettingController::class, 'update']);
$router->put('/api/settings', [SettingController::class, 'update']);
$router->post('/api/settings/media', [SettingController::class, 'uploadMedia']);
$router->post('/api/settings/media/delete', [SettingController::class, 'deleteMedia']);
$router->get('/api/media/{folder}/{kind}/{year}/{month}/{file}', [SettingController::class, 'media']);
$router->get('/api/system/backups', [BackupController::class, 'index']);
$router->post('/api/system/backup', [BackupController::class, 'create']);
$router->post('/api/system/restore', [BackupController::class, 'restore']);
$router->get('/api/backups', [BackupController::class, 'index']);
$router->post('/api/backups', [BackupController::class, 'create']);
$router->post('/api/backups/restore', [BackupController::class, 'restore']);

$router->get('/api/insights/search', [InsightController::class, 'search']);
$router->get('/api/insights/alerts', [InsightController::class, 'alerts']);
$router->get('/api/profiles/household/{id}', [ProfileController::class, 'household']);
$router->get('/api/profiles/citizen/{id}', [ProfileController::class, 'citizen']);
$router->get('/api/profiles/timeline/{type}/{id}', [ProfileController::class, 'timeline']);
$router->get('/api/files', [FileController::class, 'index']);
$router->post('/api/files', [FileController::class, 'upload']);
$router->get('/api/files/{id}/download', [FileController::class, 'download']);
$router->delete('/api/files/{id}', [FileController::class, 'destroy']);

$router->get('/api/gis/areas', [GisController::class, 'areas']);
$router->get('/api/gis/search', [GisController::class, 'search']);
$router->get('/api/gis/households', [GisController::class, 'households']);
$router->post('/api/gis/areas', [GisController::class, 'storeArea']);
$router->put('/api/gis/areas/{id}', [GisController::class, 'updateArea']);
$router->delete('/api/gis/areas/{id}', [GisController::class, 'deleteArea']);
$router->put('/api/gis/households/{id}/location', [GisController::class, 'saveHouseholdLocation']);
$router->delete('/api/gis/households/{id}/location', [GisController::class, 'clearHouseholdLocation']);
$router->get('/api/gis/export-pdf', [GisController::class, 'exportPdf']);

function versioned_asset(string $path): string
{
    $normalized = ltrim($path, '/');
    $file = BASE_PATH . '/' . $normalized;
    $version = defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1';
    if (is_file($file)) {
        $version .= '-' . filemtime($file);
    }
    $separator = str_contains($normalized, '?') ? '&' : '?';
    return $normalized . $separator . 'v=' . rawurlencode($version);
}

if (!str_starts_with($request->path(), '/api')) {
    $html = file_get_contents(BASE_PATH . '/views/app.php');
    if ($html === false) {
        http_response_code(500);
        echo 'KhÃ´ng táº£i Ä‘Æ°á»£c giao diá»‡n á»©ng dá»¥ng.';
        exit;
    }

    $versionedAssets = [
        'assets/css/app.css',
        'assets/js/app.js',
        'assets/js/csrf.js',
        'assets/js/session.js',
        'assets/js/admin.js',
        'assets/js/import.js',
        'assets/js/admin-panel.js',
        'assets/js/admin-panel-bridge.js',
        'assets/js/sprint8.js',
        'assets/js/sprint9.js',
        'assets/js/sprint10.js',
        'assets/js/gis.js',
        'assets/js/gis-household-location.js',
        'assets/js/household-photo-capture.js',
        'assets/js/household-photo-gps.js',
        'assets/js/gis-search.js',
        'assets/js/reports.js',
        'assets/js/reports-ui-fix.js',
        'assets/js/household-member-popup.js',
        'assets/css/design-system.css',
        'assets/css/dashboard-redesign.css',
        'assets/css/login-redesign.css',
        'assets/css/sidebar-modern.css',
        'assets/css/header-cleanup.css',
    ];

    foreach ($versionedAssets as $asset) {
        $html = str_replace($asset, versioned_asset($asset), $html);
    }

    $runtimeStyles = [
    ];
    $runtimeCss = implode("\n", array_map(
        fn(string $style): string => '<link rel="stylesheet" href="' . versioned_asset($style) . '">',
        $runtimeStyles
    ));
    $headClosePosition = stripos($html, '</head>');
    if ($headClosePosition !== false) {
        $html = substr_replace($html, $runtimeCss . "\n</head>", $headClosePosition, strlen('</head>'));
    }

    $runtimeScripts = [        'assets/js/view-inline-patches.js',
        'assets/js/gis-household-location.js',
        'assets/js/household-photo-capture.js',
        'assets/js/household-photo-gps.js',
        'assets/js/gis-search.js',
    ];
    $runtimeHtml = implode("\n", array_map(
        fn(string $script): string => '<script src="' . versioned_asset($script) . '"></script>',
        $runtimeScripts
    ));
    $bodyClosePosition = strripos($html, '</body>');
    if ($bodyClosePosition !== false) {
        $html = substr_replace($html, $runtimeHtml . "\n</body>", $bodyClosePosition, strlen('</body>'));
    }

    echo $html;
    exit;
}

$router->dispatch();
Response::error('KhÃ´ng tÃ¬m tháº¥y Ä‘Æ°á»ng dáº«n', 404);

