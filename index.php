<?php

define('BASE_PATH', __DIR__);
define('APP_ROOT', __DIR__);
define('APP_ASSET_VERSION', 'deploy-354-gis-mobile-popup-touch');

require_once BASE_PATH . '/app/Core/Autoloader.php';

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://maps.googleapis.com https://maps.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://maps.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; img-src 'self' data: blob: https://images.unsplash.com https://*.tile.openstreetmap.org https://maps.gstatic.com https://maps.googleapis.com https://*.googleapis.com https://*.gstatic.com; connect-src 'self' https://maps.googleapis.com; frame-src 'self' https://www.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

send_security_headers();

use App\Core\Autoloader;
use App\Core\BaseModel;
use App\Core\Request;
use App\Core\Router;
use App\Core\Response;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\DashboardController;
use App\Controllers\FileController;
use App\Controllers\GisController;
use App\Controllers\HouseholdBusinessController;
use App\Controllers\HouseholdController;
use App\Controllers\ImportController;
use App\Controllers\InsightController;
use App\Controllers\LivestockController;
use App\Controllers\LogController;
use App\Controllers\MovementController;
use App\Controllers\OperationCenterController;
use App\Controllers\PermissionController;
use App\Controllers\PersonController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\SystemAdminController;
use App\Controllers\UserController;

Autoloader::register();

function api_log_exception(Throwable $e, array $payload): void
{
    $entry = [
        'time' => date('c'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'payload' => $payload,
    ];
    $line = '[API_EXCEPTION] ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    error_log($line);
    $dir = BASE_PATH . '/storage';
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($dir . '/api-errors.log', $line, FILE_APPEND | LOCK_EX);
    }
}
function api_exception_payload(Throwable $e, int $status = 500): array
{
    $lastQuery = BaseModel::lastQuery();
    $error = [
        'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Internal Server Error',
        'type' => get_class($e),
        'code' => (string) $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stack_trace' => $e->getTraceAsString(),
        'sql' => $lastQuery['sql'] ?? null,
        'sql_params' => $lastQuery['params'] ?? null,
    ];
    if ($e instanceof PDOException) {
        $error['sqlstate'] = $e->errorInfo[0] ?? $e->getCode();
        $error['driver_code'] = $e->errorInfo[1] ?? null;
        $error['driver_message'] = $e->errorInfo[2] ?? null;
    }
    return ['ok' => false, 'success' => false, 'error' => $error, 'status' => $status];
}
$request = Request::capture();
set_exception_handler(function (Throwable $e) use ($request): void {
    if (str_starts_with($request->path(), '/api')) {
        $payload = api_exception_payload($e);
        api_log_exception($e, $payload);
        Response::json($payload, 500);
    }
    throw $e;
});

register_shutdown_function(function () use ($request): void {
    $error = error_get_last();
    if (!$error || !str_starts_with($request->path(), '/api')) return;
    if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    if (headers_sent()) return;
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'success' => false,
        'error' => [
            'message' => $error['message'] ?? 'Fatal error',
            'type' => 'FatalError',
            'file' => $error['file'] ?? null,
            'line' => $error['line'] ?? null,
            'sql' => BaseModel::lastQuery()['sql'] ?? null,
            'sql_params' => BaseModel::lastQuery()['params'] ?? null,
        ],
        'status' => 500,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});
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
$router->get('/api/dashboard/overview', [DashboardController::class, 'overview']);
$router->get('/api/dashboard/households', [DashboardController::class, 'households']);
$router->get('/api/dashboard/population', [DashboardController::class, 'population']);
$router->get('/api/dashboard/business', [DashboardController::class, 'business']);
$router->get('/api/dashboard/vehicles', [DashboardController::class, 'vehicles']);
$router->get('/api/dashboard/livestock', [DashboardController::class, 'livestock']);
$router->get('/api/dashboard/gis', [DashboardController::class, 'gis']);
$router->get('/api/dashboard/reports', [DashboardController::class, 'reports']);
$router->get('/api/dashboard/search', [DashboardController::class, 'search']);
$router->get('/api/dashboard/population-chart', [DashboardController::class, 'populationChart']);
$router->get('/api/dashboard/household-chart', [DashboardController::class, 'householdChart']);
$router->get('/api/dashboard/age-chart', [DashboardController::class, 'ageChart']);

$router->get('/api/households', [HouseholdController::class, 'index']);
$router->post('/api/households', [HouseholdController::class, 'store']);
$router->get('/api/households/{id}', [HouseholdController::class, 'show']);
$router->put('/api/households/{id}', [HouseholdController::class, 'update']);
$router->delete('/api/households/{id}', [HouseholdController::class, 'destroy']);
$router->post('/api/households/bulk-delete', [HouseholdController::class, 'bulkDelete']);

$router->get('/api/household-business', [HouseholdBusinessController::class, 'index']);
$router->post('/api/household-business', [HouseholdBusinessController::class, 'store']);
$router->get('/api/household-business/dashboard', [HouseholdBusinessController::class, 'dashboard']);
$router->get('/api/household-business/catalogs', [HouseholdBusinessController::class, 'catalogs']);
$router->get('/api/household-business/household-search', [HouseholdBusinessController::class, 'householdSearch']);
$router->get('/api/household-business/household/{householdId}', [HouseholdBusinessController::class, 'byHousehold']);
$router->get('/api/household-business/{id}/files', [HouseholdBusinessController::class, 'files']);
$router->post('/api/household-business/{id}/files', [HouseholdBusinessController::class, 'uploadFile']);
$router->get('/api/household-business/{id}/files/{fileId}/preview', [HouseholdBusinessController::class, 'previewFile']);
$router->get('/api/household-business/{id}/files/{fileId}/download', [HouseholdBusinessController::class, 'downloadFile']);
$router->delete('/api/household-business/{id}/files/{fileId}', [HouseholdBusinessController::class, 'deleteFile']);
$router->get('/api/household-business/{id}', [HouseholdBusinessController::class, 'show']);
$router->put('/api/household-business/{id}', [HouseholdBusinessController::class, 'update']);
$router->delete('/api/household-business/{id}', [HouseholdBusinessController::class, 'destroy']);
$router->get('/api/household-businesses', [HouseholdBusinessController::class, 'index']);
$router->post('/api/household-businesses', [HouseholdBusinessController::class, 'store']);
$router->get('/api/household-businesses/dashboard', [HouseholdBusinessController::class, 'dashboard']);
$router->get('/api/household-businesses/catalogs', [HouseholdBusinessController::class, 'catalogs']);
$router->get('/api/household-businesses/household-search', [HouseholdBusinessController::class, 'householdSearch']);
$router->get('/api/household-businesses/household/{householdId}', [HouseholdBusinessController::class, 'byHousehold']);
$router->get('/api/household-businesses/{id}/files', [HouseholdBusinessController::class, 'files']);
$router->post('/api/household-businesses/{id}/files', [HouseholdBusinessController::class, 'uploadFile']);
$router->get('/api/household-businesses/{id}/files/{fileId}/preview', [HouseholdBusinessController::class, 'previewFile']);
$router->get('/api/household-businesses/{id}/files/{fileId}/download', [HouseholdBusinessController::class, 'downloadFile']);
$router->delete('/api/household-businesses/{id}/files/{fileId}', [HouseholdBusinessController::class, 'deleteFile']);
$router->get('/api/household-businesses/{id}', [HouseholdBusinessController::class, 'show']);
$router->put('/api/household-businesses/{id}', [HouseholdBusinessController::class, 'update']);
$router->delete('/api/household-businesses/{id}', [HouseholdBusinessController::class, 'destroy']);
$router->get('/api/livestock', [LivestockController::class, 'index']);
$router->post('/api/livestock', [LivestockController::class, 'store']);
$router->get('/api/livestock/dashboard', [LivestockController::class, 'dashboard']);
$router->get('/api/livestock/catalogs', [LivestockController::class, 'catalogs']);
$router->get('/api/livestock/household-search', [LivestockController::class, 'householdSearch']);
$router->get('/api/livestock/household/{householdId}', [LivestockController::class, 'byHousehold']);
$router->get('/api/livestock/{id}', [LivestockController::class, 'show']);
$router->put('/api/livestock/{id}', [LivestockController::class, 'update']);
$router->delete('/api/livestock/{id}', [LivestockController::class, 'destroy']);

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
$router->get('/api/reports/center', [ReportController::class, 'center']);
$router->get('/api/reports/bi', [ReportController::class, 'bi']);
$router->get('/api/reports/bitype-summary', [ReportController::class, 'bi']);
$router->get('/api/reports/templates', [ReportController::class, 'templates']);
$router->post('/api/reports/templates', [ReportController::class, 'saveTemplate']);
$router->delete('/api/reports/templates/{id}', [ReportController::class, 'deleteTemplate']);
$router->post('/api/reports/templates/{id}/default', [ReportController::class, 'defaultTemplate']);
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
$router->get('/api/reports/export-word', [ReportController::class, 'exportWord']);
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

$router->get('/api/system-admin/overview', [SystemAdminController::class, 'overview']);
$router->get('/api/system-admin/health', [SystemAdminController::class, 'health']);
$router->get('/api/system-admin/sessions', [SystemAdminController::class, 'sessions']);
$router->post('/api/system-admin/sessions/{id}/revoke', [SystemAdminController::class, 'revokeSession']);
$router->post('/api/system-admin/sessions/revoke-all', [SystemAdminController::class, 'revokeAllSessions']);
$router->get('/api/system-admin/memory', [SystemAdminController::class, 'memory']);
$router->post('/api/system-admin/cleanup', [SystemAdminController::class, 'cleanup']);
$router->get('/api/system-admin/performance', [SystemAdminController::class, 'performance']);
$router->get('/api/system-admin/security', [SystemAdminController::class, 'security']);
$router->get('/api/system-admin/configuration', [SystemAdminController::class, 'configuration']);
$router->post('/api/system-admin/backups', [SystemAdminController::class, 'createBackup']);

$router->get('/api/operation-center/notifications', [OperationCenterController::class, 'notifications']);
$router->get('/api/operation-center/tasks', [OperationCenterController::class, 'tasks']);
$router->get('/api/operation-center/search', [OperationCenterController::class, 'search']);
$router->get('/api/operation-center/quick-profile', [OperationCenterController::class, 'quickProfile']);
$router->get('/api/operation-center/timeline', [OperationCenterController::class, 'timeline']);
$router->get('/api/operation-center/area-dashboard', [OperationCenterController::class, 'areaDashboard']);
$router->get('/api/operation-center/progress', [OperationCenterController::class, 'progress']);
$router->get('/api/operation-center/system-logs', [OperationCenterController::class, 'systemLogs']);
$router->get('/api/operation-center/export-report', [OperationCenterController::class, 'exportReport']);
$router->get('/api/operation-center/export-logs', [OperationCenterController::class, 'exportLogs']);
$router->get('/api/insights/search', [InsightController::class, 'search']);
$router->get('/api/insights/alerts', [InsightController::class, 'alerts']);
$router->get('/api/profiles/household/{id}', [ProfileController::class, 'household']);
$router->get('/api/profiles/citizen/{id}', [ProfileController::class, 'citizen']);
$router->get('/api/profiles/timeline/{type}/{id}', [ProfileController::class, 'timeline']);
$router->get('/api/timeline/{type}/{id}', [ProfileController::class, 'timeline']);
$router->post('/api/profiles/{type}/{id}/notes', [ProfileController::class, 'createNote']);
$router->delete('/api/profiles/notes/{id}', [ProfileController::class, 'deleteNote']);
$router->put('/api/profiles/notes/{id}', [ProfileController::class, 'updateNote']);
$router->get('/api/files', [FileController::class, 'index']);
$router->post('/api/files', [FileController::class, 'upload']);
$router->get('/api/files/{id}', [FileController::class, 'show']);
$router->put('/api/files/{id}', [FileController::class, 'update']);
$router->get('/api/files/{id}/preview', [FileController::class, 'preview']);
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

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $line));
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $key = (string) preg_replace('/^\xEF\xBB\xBF/', '', $key);
        if ($key === '') continue;
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (env_value($key) !== '') continue;
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function env_value(string $key): string
{
    $value = getenv($key);
    if ($value !== false && trim((string) $value) !== '') return trim((string) $value);
    if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') return trim((string) $_ENV[$key]);
    if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') return trim((string) $_SERVER[$key]);
    return '';
}

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
    header('Content-Type: text/html; charset=UTF-8');
    $html = file_get_contents(BASE_PATH . '/views/app.php');
    if ($html === false) {
        http_response_code(500);
        echo 'Không tải được giao diện ứng dụng.';
        exit;
    }

    load_env_file(BASE_PATH . '/.env');
    load_env_file(dirname(BASE_PATH) . '/.env');
    $googleMapsApiKey = env_value('GOOGLE_MAPS_API_KEY') ?: env_value('VITE_GOOGLE_MAPS_API_KEY');
    $googleMapsConfig = '<script>window.THON09_GOOGLE_MAPS_API_KEY=' . json_encode($googleMapsApiKey, JSON_UNESCAPED_SLASHES) . ';</script>';
    $headClosePosition = stripos($html, '</head>');
    if ($headClosePosition !== false) {
        $html = substr_replace($html, $googleMapsConfig . "\n</head>", $headClosePosition, strlen('</head>'));
    }

    $versionedAssets = [
        'assets/css/app.min.css',
        'assets/js/i18n.min.js',
        'assets/js/app.utf8.min.js',
        'assets/js/csrf.min.js',
        'assets/js/session.min.js',
        'assets/js/admin.utf8.min.js',
        'assets/js/import.min.js',
        'assets/js/admin-panel.min.js',
        'assets/js/admin-panel-bridge.min.js',
        'assets/js/sprint8.min.js',
        'assets/js/sprint9.min.js',
        'assets/js/sprint10.min.js',
        'assets/js/view-inline-patches.min.js',
        'assets/js/operation-center.min.js',
        'assets/js/system-admin.min.js',
        'assets/js/gis-household-location.min.js',
        'assets/js/household-photo-capture.min.js',
        'assets/js/household-photo-camera-fix.min.js',
        'assets/js/household-photo-gps.min.js',
        'assets/js/gis-search.min.js',
        'assets/js/gis-smart.min.js',
        'assets/js/gis-google.min.js',
        'assets/js/digital-profile.min.js',
        'assets/js/household-business.min.js',
        'assets/js/livestock.min.js',
        'assets/js/module-dashboards.min.js',
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
    echo $html;
    exit;
}

try {
    $router->dispatch();
} catch (Throwable $e) {
    if (str_starts_with($request->path(), '/api')) {
        $payload = api_exception_payload($e);
        api_log_exception($e, $payload);
        Response::json($payload, 500);
    }
    throw $e;
}
Response::error('Không tìm thấy đường dẫn', 404);
