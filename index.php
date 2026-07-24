<?php

define('BASE_PATH', __DIR__);
define('APP_ROOT', __DIR__);
define('APP_ASSET_VERSION', 'gis-esri-tilelayer-reset-20260719-1');

require_once BASE_PATH . '/app/Core/Autoloader.php';

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(self), camera=(self), microphone=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; img-src 'self' data: blob: https://images.unsplash.com https://*.tile.openstreetmap.org https://*.openstreetmap.fr https://*.basemaps.cartocdn.com https://*.arcgisonline.com; connect-src 'self'; frame-src 'self' https://www.openstreetmap.org; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

send_security_headers();

use App\Core\Autoloader;
use App\Core\BaseModel;
use App\Core\Request;
use App\Core\Router;
use App\Core\Response;
use App\Controllers\AgricultureProductionController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\ComplaintController;
use App\Controllers\ContributionController;
use App\Controllers\DashboardController;
use App\Controllers\FileController;
use App\Controllers\FinanceController;
use App\Controllers\GisController;
use App\Controllers\HouseholdBusinessController;
use App\Controllers\HouseController;
use App\Controllers\HouseholdController;
use App\Controllers\ImportController;
use App\Controllers\InsightController;
use App\Controllers\LivestockController;
use App\Controllers\LogController;
use App\Controllers\MovementController;
use App\Controllers\NotificationController;
use App\Controllers\OperationCenterController;
use App\Controllers\PermissionController;
use App\Controllers\PersonController;
use App\Controllers\PhotoGalleryController;
use App\Controllers\ProfileController;
use App\Controllers\PublicAssetController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\SystemAdminController;
use App\Controllers\UserController;
use App\Controllers\VehicleController;
use App\Controllers\VillageDocumentController;
use App\Controllers\WorkCalendarController;
use App\Controllers\WorkTaskController;

Autoloader::register();

function reject_oversized_api_request(): void
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (!str_starts_with($path, '/api/')) return;
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return;
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $maxBytes = 25 * 1024 * 1024;
    if ($length <= $maxBytes) return;
    Response::json([
        'ok' => false,
        'success' => false,
        'message' => 'Request entity too large',
        'errors' => [],
        'error' => ['message' => 'Request entity too large'],
        'status' => 413,
    ], 413);
}

function redact_security_value(mixed $value): mixed
{
    if (is_array($value)) {
        $redacted = [];
        foreach ($value as $key => $item) {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $key));
            if (preg_match('/(password|passwd|pwd|token|csrf|cookie|session|authorization|identity|cccd|phone|email|login)/', $normalized)) {
                $redacted[$key] = '[REDACTED]';
            } else {
                $redacted[$key] = redact_security_value($item);
            }
        }
        return $redacted;
    }
    if (is_string($value) && preg_match('/Bearer\s+[a-f0-9]{32,}/i', $value)) {
        return '[REDACTED]';
    }
    return $value;
}

function redact_security_uri(?string $uri): ?string
{
    if ($uri === null || $uri === '') return $uri;
    $parts = parse_url($uri);
    if ($parts === false) return '[REDACTED_URI]';
    $path = (string) ($parts['path'] ?? '');
    if (empty($parts['query'])) return $path;

    parse_str((string) $parts['query'], $query);
    return $path . '?' . http_build_query(redact_security_value($query));
}

function production_log_message(Throwable $e): string
{
    if ($e instanceof PDOException) return 'Database operation failed';
    if (app_debug_enabled()) return $e->getMessage();
    return 'Application operation failed';
}

function api_log_exception(Throwable $e, array $payload): void
{
    $exception = [
        'message' => production_log_message($e),
        'type' => get_class($e),
        'code' => (string) $e->getCode(),
    ];
    if ($e instanceof PDOException) {
        $exception['sqlstate'] = $e->errorInfo[0] ?? $e->getCode();
    }
    $entry = [
        'time' => date('c'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => redact_security_uri($_SERVER['REQUEST_URI'] ?? null),
        'status' => $payload['status'] ?? null,
        'exception' => $exception,
    ];
    $line = '[API_EXCEPTION] ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    error_log($line);
    $dir = BASE_PATH . '/storage';
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($dir . '/api-errors.log', $line, FILE_APPEND | LOCK_EX);
    }
}
function app_debug_enabled(): bool
{
    static $debug = null;
    if ($debug !== null) return $debug;
    $config = require BASE_PATH . '/config/app.php';
    $debug = !empty($config['debug']);
    return $debug;
}

function api_exception_payload(Throwable $e, int $status = 500): array
{
    $error = [
        'message' => $status >= 500 ? 'Internal Server Error' : ($e->getMessage() ?: 'Request failed'),
        'type' => $status >= 500 ? 'ServerError' : get_class($e),
    ];

    if (app_debug_enabled()) {
        $lastQuery = BaseModel::lastQuery();
        $error += [
            'debug_message' => $e->getMessage(),
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
    }

    return ['ok' => false, 'success' => false, 'message' => $error['message'], 'errors' => [], 'error' => $error, 'status' => $status];
}

function api_exception_status(Throwable $e): int
{
    if ($e instanceof PDOException) {
        return 500;
    }
    if ($e instanceof RuntimeException || $e instanceof InvalidArgumentException) {
        return 422;
    }
    return 500;
}

reject_oversized_api_request();
$request = Request::capture();
set_exception_handler(function (Throwable $e) use ($request): void {
    if (str_starts_with($request->path(), '/api')) {
        $status = api_exception_status($e);
        $payload = api_exception_payload($e, $status);
        api_log_exception($e, $payload);
        Response::json($payload, $status);
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
    $payload = [
        'ok' => false,
        'success' => false,
        'message' => 'Internal Server Error',
        'errors' => [],
        'error' => [
            'message' => 'Internal Server Error',
            'type' => 'FatalError',
        ],
        'status' => 500,
    ];
    if (app_debug_enabled()) {
        $payload['error'] += [
            'debug_message' => $error['message'] ?? 'Fatal error',
            'file' => $error['file'] ?? null,
            'line' => $error['line'] ?? null,
            'sql' => BaseModel::lastQuery()['sql'] ?? null,
            'sql_params' => BaseModel::lastQuery()['params'] ?? null,
        ];
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});
if ($request->path() === '/favicon.ico') {
    $faviconPath = __DIR__ . '/favicon.ico';
    header('Content-Type: image/x-icon');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (is_file($faviconPath)) {
        readfile($faviconPath);
    }
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
$router->get('/api/agriculture', [AgricultureProductionController::class, 'index']);
$router->post('/api/agriculture', [AgricultureProductionController::class, 'store']);
$router->get('/api/agriculture/dashboard', [AgricultureProductionController::class, 'dashboard']);
$router->get('/api/agriculture/catalogs', [AgricultureProductionController::class, 'catalogs']);
$router->get('/api/agriculture/gis', [AgricultureProductionController::class, 'gis']);
$router->get('/api/agriculture/{id}', [AgricultureProductionController::class, 'show']);
$router->put('/api/agriculture/{id}', [AgricultureProductionController::class, 'update']);
$router->delete('/api/agriculture/{id}', [AgricultureProductionController::class, 'destroy']);
$router->post('/api/agriculture/{parcelId}/plots', [AgricultureProductionController::class, 'addPlot']);
$router->post('/api/agriculture/plots/{plotId}/seasons', [AgricultureProductionController::class, 'addSeason']);
$router->post('/api/agriculture/seasons/{seasonId}/logs', [AgricultureProductionController::class, 'addLog']);
$router->post('/api/agriculture/{parcelId}/damages', [AgricultureProductionController::class, 'addDamage']);
$router->get('/api/houses', [HouseController::class, 'index']);
$router->post('/api/houses', [HouseController::class, 'store']);
$router->get('/api/houses/dashboard', [HouseController::class, 'dashboard']);
$router->get('/api/houses/catalogs', [HouseController::class, 'catalogs']);
$router->get('/api/houses/household-search', [HouseController::class, 'householdSearch']);
$router->get('/api/houses/household/{householdId}', [HouseController::class, 'byHousehold']);
$router->get('/api/houses/gis', [HouseController::class, 'gis']);
$router->post('/api/houses/{id}/photos', [HouseController::class, 'uploadPhoto']);
$router->delete('/api/houses/{id}/photos/{photoId}', [HouseController::class, 'deletePhoto']);
$router->get('/api/houses/{id}', [HouseController::class, 'show']);
$router->put('/api/houses/{id}', [HouseController::class, 'update']);
$router->delete('/api/houses/{id}', [HouseController::class, 'destroy']);
$router->get('/api/public-assets', [PublicAssetController::class, 'index']);
$router->post('/api/public-assets', [PublicAssetController::class, 'store']);
$router->get('/api/public-assets/dashboard', [PublicAssetController::class, 'dashboard']);
$router->get('/api/public-assets/catalogs', [PublicAssetController::class, 'catalogs']);
$router->get('/api/public-assets/gis', [PublicAssetController::class, 'gis']);
$router->get('/api/public-assets/inventory/catalogs', [PublicAssetController::class, 'inventoryCatalogs']);
$router->get('/api/public-assets/inventory/dashboard', [PublicAssetController::class, 'inventoryDashboard']);
$router->get('/api/public-assets/{id}/photo', [PublicAssetController::class, 'photo']);
$router->post('/api/public-assets/{id}/photo', [PublicAssetController::class, 'uploadPhoto']);
$router->delete('/api/public-assets/{id}/photo', [PublicAssetController::class, 'deletePhoto']);
$router->get('/api/public-assets/{id}/inventory', [PublicAssetController::class, 'inventoryIndex']);
$router->post('/api/public-assets/{id}/inventory', [PublicAssetController::class, 'inventoryStore']);
$router->get('/api/public-assets/{id}/inventory/{itemId}/photo', [PublicAssetController::class, 'inventoryPhoto']);
$router->post('/api/public-assets/{id}/inventory/{itemId}/photo', [PublicAssetController::class, 'inventoryUploadPhoto']);
$router->delete('/api/public-assets/{id}/inventory/{itemId}/photo', [PublicAssetController::class, 'inventoryDeletePhoto']);
$router->put('/api/public-assets/{id}/inventory/{itemId}', [PublicAssetController::class, 'inventoryUpdate']);
$router->delete('/api/public-assets/{id}/inventory/{itemId}', [PublicAssetController::class, 'inventoryDestroy']);
$router->get('/api/public-assets/{id}/maintenance', [PublicAssetController::class, 'maintenanceIndex']);
$router->post('/api/public-assets/{id}/maintenance', [PublicAssetController::class, 'maintenanceStore']);
$router->put('/api/public-assets/{id}/maintenance/{maintenanceId}', [PublicAssetController::class, 'maintenanceUpdate']);
$router->delete('/api/public-assets/{id}/maintenance/{maintenanceId}', [PublicAssetController::class, 'maintenanceDestroy']);
$router->get('/api/public-assets/{id}', [PublicAssetController::class, 'show']);
$router->put('/api/public-assets/{id}', [PublicAssetController::class, 'update']);
$router->delete('/api/public-assets/{id}', [PublicAssetController::class, 'destroy']);
$router->get('/api/complaints', [ComplaintController::class, 'index']);
$router->post('/api/complaints', [ComplaintController::class, 'store']);
$router->get('/api/complaints/dashboard', [ComplaintController::class, 'dashboard']);
$router->get('/api/complaints/catalogs', [ComplaintController::class, 'catalogs']);
$router->get('/api/complaints/gis', [ComplaintController::class, 'gis']);
$router->get('/api/complaints/report', [ComplaintController::class, 'report']);
$router->get('/api/complaints/export-excel', [ComplaintController::class, 'exportExcel']);
$router->get('/api/complaints/export-pdf', [ComplaintController::class, 'exportPdf']);
$router->get('/api/complaints/household-search', [ComplaintController::class, 'householdSearch']);
$router->get('/api/complaints/citizen-search', [ComplaintController::class, 'citizenSearch']);
$router->get('/api/complaints/related-search', [ComplaintController::class, 'relatedSearch']);
$router->post('/api/complaints/{id}/histories', [ComplaintController::class, 'addHistory']);
$router->post('/api/complaints/{id}/assignments', [ComplaintController::class, 'assign']);
$router->post('/api/complaints/{id}/evaluation', [ComplaintController::class, 'evaluate']);
$router->post('/api/complaints/{id}/attachments', [ComplaintController::class, 'uploadAttachment']);
$router->get('/api/complaints/{id}/attachments/{fileId}/preview', [ComplaintController::class, 'previewAttachment']);
$router->get('/api/complaints/{id}/attachments/{fileId}/download', [ComplaintController::class, 'downloadAttachment']);
$router->delete('/api/complaints/{id}/attachments/{fileId}', [ComplaintController::class, 'deleteAttachment']);
$router->get('/api/complaints/{id}', [ComplaintController::class, 'show']);
$router->put('/api/complaints/{id}', [ComplaintController::class, 'update']);
$router->delete('/api/complaints/{id}', [ComplaintController::class, 'destroy']);
$router->get('/api/work-tasks', [WorkTaskController::class, 'index']);
$router->post('/api/work-tasks', [WorkTaskController::class, 'store']);
$router->get('/api/work-tasks/dashboard', [WorkTaskController::class, 'dashboard']);
$router->get('/api/work-tasks/catalogs', [WorkTaskController::class, 'catalogs']);
$router->get('/api/work-tasks/report', [WorkTaskController::class, 'report']);
$router->get('/api/work-tasks/export-excel', [WorkTaskController::class, 'exportExcel']);
$router->get('/api/work-tasks/export-pdf', [WorkTaskController::class, 'exportPdf']);
$router->post('/api/work-tasks/{id}/logs', [WorkTaskController::class, 'addLog']);
$router->post('/api/work-tasks/{id}/attachments', [WorkTaskController::class, 'uploadAttachment']);
$router->get('/api/work-tasks/{id}/attachments/{fileId}/preview', [WorkTaskController::class, 'previewAttachment']);
$router->get('/api/work-tasks/{id}/attachments/{fileId}/download', [WorkTaskController::class, 'downloadAttachment']);
$router->delete('/api/work-tasks/{id}/attachments/{fileId}', [WorkTaskController::class, 'deleteAttachment']);
$router->get('/api/work-tasks/{id}', [WorkTaskController::class, 'show']);
$router->put('/api/work-tasks/{id}', [WorkTaskController::class, 'update']);
$router->delete('/api/work-tasks/{id}', [WorkTaskController::class, 'destroy']);
$router->get('/api/work-calendar', [WorkCalendarController::class, 'index']);
$router->post('/api/work-calendar', [WorkCalendarController::class, 'store']);
$router->get('/api/work-calendar/dashboard', [WorkCalendarController::class, 'dashboard']);
$router->get('/api/work-calendar/catalogs', [WorkCalendarController::class, 'catalogs']);
$router->get('/api/work-calendar/report', [WorkCalendarController::class, 'report']);
$router->get('/api/work-calendar/export-excel', [WorkCalendarController::class, 'exportExcel']);
$router->get('/api/work-calendar/export-pdf', [WorkCalendarController::class, 'exportPdf']);
$router->post('/api/work-calendar/{id}/attachments', [WorkCalendarController::class, 'uploadAttachment']);
$router->get('/api/work-calendar/{id}/attachments/{fileId}/preview', [WorkCalendarController::class, 'previewAttachment']);
$router->get('/api/work-calendar/{id}/attachments/{fileId}/download', [WorkCalendarController::class, 'downloadAttachment']);
$router->delete('/api/work-calendar/{id}/attachments/{fileId}', [WorkCalendarController::class, 'deleteAttachment']);
$router->get('/api/work-calendar/{id}', [WorkCalendarController::class, 'show']);
$router->put('/api/work-calendar/{id}', [WorkCalendarController::class, 'update']);
$router->delete('/api/work-calendar/{id}', [WorkCalendarController::class, 'destroy']);
$router->get('/api/documents', [VillageDocumentController::class, 'index']);
$router->post('/api/documents', [VillageDocumentController::class, 'store']);
$router->get('/api/documents/dashboard', [VillageDocumentController::class, 'dashboard']);
$router->get('/api/documents/catalogs', [VillageDocumentController::class, 'catalogs']);
$router->get('/api/documents/report', [VillageDocumentController::class, 'report']);
$router->get('/api/documents/export-excel', [VillageDocumentController::class, 'exportExcel']);
$router->get('/api/documents/export-pdf', [VillageDocumentController::class, 'exportPdf']);
$router->post('/api/documents/{id}/attachments', [VillageDocumentController::class, 'uploadAttachment']);
$router->get('/api/documents/{id}/attachments/{fileId}/preview', [VillageDocumentController::class, 'previewAttachment']);
$router->get('/api/documents/{id}/attachments/{fileId}/download', [VillageDocumentController::class, 'downloadAttachment']);
$router->delete('/api/documents/{id}/attachments/{fileId}', [VillageDocumentController::class, 'deleteAttachment']);
$router->get('/api/documents/{id}', [VillageDocumentController::class, 'show']);
$router->put('/api/documents/{id}', [VillageDocumentController::class, 'update']);
$router->delete('/api/documents/{id}', [VillageDocumentController::class, 'destroy']);
$router->get('/api/finance', [FinanceController::class, 'index']);
$router->post('/api/finance', [FinanceController::class, 'store']);
$router->get('/api/finance/dashboard', [FinanceController::class, 'dashboard']);
$router->get('/api/finance/catalogs', [FinanceController::class, 'catalogs']);
$router->get('/api/finance/report', [FinanceController::class, 'report']);
$router->get('/api/finance/export-excel', [FinanceController::class, 'exportExcel']);
$router->get('/api/finance/export-pdf', [FinanceController::class, 'exportPdf']);
$router->post('/api/finance/{id}/attachments', [FinanceController::class, 'uploadAttachment']);
$router->get('/api/finance/{id}/attachments/{fileId}/preview', [FinanceController::class, 'previewAttachment']);
$router->get('/api/finance/{id}/attachments/{fileId}/download', [FinanceController::class, 'downloadAttachment']);
$router->delete('/api/finance/{id}/attachments/{fileId}', [FinanceController::class, 'deleteAttachment']);
$router->get('/api/finance/{id}', [FinanceController::class, 'show']);
$router->put('/api/finance/{id}', [FinanceController::class, 'update']);
$router->delete('/api/finance/{id}', [FinanceController::class, 'destroy']);
$router->get('/api/photo-gallery', [PhotoGalleryController::class, 'index']);
$router->post('/api/photo-gallery/upload', [PhotoGalleryController::class, 'upload']);
$router->get('/api/photo-gallery/dashboard', [PhotoGalleryController::class, 'dashboard']);
$router->get('/api/photo-gallery/catalogs', [PhotoGalleryController::class, 'catalogs']);
$router->get('/api/photo-gallery/albums', [PhotoGalleryController::class, 'albums']);
$router->post('/api/photo-gallery/albums', [PhotoGalleryController::class, 'createAlbum']);
$router->get('/api/photo-gallery/{id}/preview', [PhotoGalleryController::class, 'preview']);
$router->get('/api/photo-gallery/{id}/download', [PhotoGalleryController::class, 'download']);
$router->get('/api/photo-gallery/{id}', [PhotoGalleryController::class, 'show']);
$router->put('/api/photo-gallery/{id}', [PhotoGalleryController::class, 'update']);
$router->delete('/api/photo-gallery/{id}', [PhotoGalleryController::class, 'destroy']);
$router->get('/api/vehicles', [VehicleController::class, 'index']);
$router->post('/api/vehicles', [VehicleController::class, 'store']);
$router->get('/api/vehicles/dashboard', [VehicleController::class, 'dashboard']);
$router->get('/api/vehicles/catalogs', [VehicleController::class, 'catalogs']);
$router->get('/api/vehicles/household-search', [VehicleController::class, 'householdSearch']);
$router->get('/api/vehicles/household/{householdId}/citizens', [VehicleController::class, 'citizenSearch']);
$router->get('/api/vehicles/household/{householdId}', [VehicleController::class, 'byHousehold']);
$router->get('/api/vehicles/{id}', [VehicleController::class, 'show']);
$router->put('/api/vehicles/{id}', [VehicleController::class, 'update']);
$router->delete('/api/vehicles/{id}', [VehicleController::class, 'destroy']);
$router->get('/api/contributions', [ContributionController::class, 'index']);
$router->post('/api/contributions', [ContributionController::class, 'store']);
$router->get('/api/contributions/dashboard', [ContributionController::class, 'dashboard']);
$router->get('/api/contributions/catalogs', [ContributionController::class, 'catalogs']);
$router->get('/api/contributions/categories', [ContributionController::class, 'categories']);
$router->post('/api/contributions/categories', [ContributionController::class, 'storeCategory']);
$router->get('/api/contributions/categories/{id}', [ContributionController::class, 'showCategory']);
$router->put('/api/contributions/categories/{id}', [ContributionController::class, 'updateCategory']);
$router->delete('/api/contributions/categories/{id}', [ContributionController::class, 'destroyCategory']);
$router->get('/api/contributions/household-search', [ContributionController::class, 'householdSearch']);
$router->get('/api/contributions/{id}', [ContributionController::class, 'show']);
$router->put('/api/contributions/{id}', [ContributionController::class, 'update']);
$router->delete('/api/contributions/{id}', [ContributionController::class, 'destroy']);
$router->get('/api/contributions/{campaignId}/households', [ContributionController::class, 'tracking']);
$router->get('/api/contributions/{campaignId}/households/{householdId}/history', [ContributionController::class, 'history']);
$router->post('/api/contributions/{campaignId}/households/{householdId}', [ContributionController::class, 'updateTracking']);
$router->put('/api/contributions/{campaignId}/households/{householdId}', [ContributionController::class, 'updateTracking']);
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
$router->post('/api/users/{id}/reset-password', [UserController::class, 'resetPassword']);
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
$router->get('/api/system/interface/media', [SettingController::class, 'mediaList']);
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
$router->get('/api/operation-center/command-center', [OperationCenterController::class, 'commandCenter']);
$router->get('/api/operation-center/system-logs', [OperationCenterController::class, 'systemLogs']);
$router->get('/api/operation-center/export-report', [OperationCenterController::class, 'exportReport']);
$router->get('/api/operation-center/export-logs', [OperationCenterController::class, 'exportLogs']);
$router->get('/api/notifications', [NotificationController::class, 'index']);
$router->post('/api/notifications/read-all', [NotificationController::class, 'readAll']);
$router->post('/api/notifications/{key}/read', [NotificationController::class, 'read']);
$router->post('/api/notifications/{key}/dismiss', [NotificationController::class, 'dismiss']);
$router->get('/api/insights/search', [InsightController::class, 'search']);
$router->get('/api/insights/alerts', [InsightController::class, 'alerts']);
$router->post('/api/insights/ask', [InsightController::class, 'ask']);
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
$router->get('/api/gis/households/{id}/detail', [GisController::class, 'householdDetail']);
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
        $hash = hash_file('xxh3', $file);
        if ($hash === false) {
            $hash = hash_file('sha1', $file);
        }
        $version .= '-' . substr((string) $hash, 0, 12);
    }
    $separator = str_contains($normalized, '?') ? '&' : '?';
    return $normalized . $separator . 'v=' . rawurlencode($version);
}

if (!str_starts_with($request->path(), '/api')) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    $html = file_get_contents(BASE_PATH . '/views/app.php');
    if ($html === false) {
        http_response_code(500);
        echo 'Không tải được giao diện ứng dụng.';
        exit;
    }
    $versionedAssets = [
        'manifest.json',
        'favicon.ico',
        'assets/icons/apple-touch-icon.png',
        'assets/icons/splash-512.png',
        'assets/vendor/bootstrap/bootstrap.min.css',
        'assets/vendor/bootstrap/bootstrap.bundle.min.js',
        'assets/vendor/fontawesome-local.css',
        'assets/css/app.min.css',
        'assets/css/mobile-design-system-v2.min.css',
        'assets/css/print.min.css',
        'assets/js/i18n.min.js',
        'assets/js/print-framework.min.js',
        'assets/js/app-platform.min.js',
        'assets/js/mobile-component-library.min.js',
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
        'assets/js/operation-center.min.js',
        'assets/js/system-admin.min.js',
        'assets/js/report.min.js',
        'assets/js/gis-household-location.min.js',
        'assets/js/gis-platform.min.js',
        'assets/js/household-photo-capture.min.js',
        'assets/js/household-photo-camera-fix.min.js',
        'assets/js/household-photo-gps.min.js',
        'assets/js/digital-profile.min.js',
        'assets/js/household-business.min.js',
        'assets/js/livestock.min.js',
        'assets/js/vehicles.min.js',
        'assets/js/contributions.min.js',
        'assets/js/agriculture.min.js',
        'assets/js/houses.min.js',
        'assets/js/public-assets.min.js',
        'assets/js/complaints.min.js',
        'assets/js/work-tasks.min.js',
        'assets/js/work-calendar.min.js',
        'assets/js/documents.min.js',
        'assets/js/finance.min.js',
        'assets/js/photo-gallery.min.js',
        'assets/js/view-inline-patches.min.js',
        'assets/js/notifications.min.js',
        'assets/js/module-dashboards.min.js',
        'assets/js/pwa.min.js',
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
        $status = api_exception_status($e);
        $payload = api_exception_payload($e, $status);
        api_log_exception($e, $payload);
        Response::json($payload, $status);
    }
    throw $e;
}
Response::error('Không tìm thấy đường dẫn', 404);
