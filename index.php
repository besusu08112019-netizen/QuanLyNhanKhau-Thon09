<?php

define('BASE_PATH', __DIR__);
define('APP_ROOT', __DIR__);
define('APP_ASSET_VERSION', '20260729-mt-acceptance-1');

require_once BASE_PATH . '/app/Core/Autoloader.php';
require_once BASE_PATH . '/config/env.php';

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(self), camera=(self)');
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
use App\Core\RuntimePaths;
use App\Core\PortalContext;
use App\Core\Response;
use App\Core\TenantConfig;
use App\Core\TenantContext;
use App\Core\TenantGuard;
use App\Controllers\AgriculturalLandZoneController;
use App\Controllers\AgricultureProductionController;
use App\Controllers\AdministrativeUnitController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\ComplaintController;
use App\Controllers\ControlCenterAuthController;
use App\Controllers\ControlCenterPermissionController;
use App\Controllers\ControlCenterUserController;
use App\Controllers\ContributionController;
use App\Controllers\ControlCenterController;
use App\Controllers\DataQualityController;
use App\Controllers\DashboardController;
use App\Controllers\FileController;
use App\Controllers\FinanceController;
use App\Controllers\GisController;
use App\Controllers\HouseholdBusinessController;
use App\Controllers\HouseController;
use App\Controllers\HouseholdController;
use App\Controllers\HouseholdPovertyController;
use App\Controllers\ImportController;
use App\Controllers\LivestockController;
use App\Controllers\LogController;
use App\Controllers\MovementController;
use App\Controllers\NotificationController;
use App\Controllers\OperationCenterController;
use App\Controllers\PartyMemberController;
use App\Controllers\PermissionController;
use App\Controllers\PlatformSettingsController;
use App\Controllers\PolicyAlertController;
use App\Controllers\PersonController;
use App\Controllers\PhotoGalleryController;
use App\Controllers\ProfileController;
use App\Controllers\PublicAssetController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\SystemAdminController;
use App\Controllers\TenantInstallerController;
use App\Controllers\TenantManagementController;
use App\Controllers\UserController;
use App\Controllers\VehicleController;
use App\Controllers\VillageDocumentController;
use App\Controllers\WorkCalendarController;
use App\Controllers\WorkTaskController;
use App\Config\CitizenPolicyDefaults;
use App\Policies\AgePolicy;
use App\Policies\InsurancePolicy;
use App\Services\StudentStatusService;
use App\Services\PlatformBrandingService;

Autoloader::register();
env_load(BASE_PATH);
configure_tenant_php_session();
PortalContext::boot();
if (PortalContext::isTenant()) {
    TenantContext::boot();
}

function configure_tenant_php_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    $config = is_file(BASE_PATH . '/config/app.php') ? require BASE_PATH . '/config/app.php' : [];
    $sessionPath = (string) ($config['session_path'] ?? RuntimePaths::sessionRoot());
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_name('qh_session_' . substr(hash('sha256', RuntimePaths::host()), 0, 16));
}

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
    $config = is_file(BASE_PATH . '/config/app.php') ? require BASE_PATH . '/config/app.php' : [];
    $dir = (string) ($config['logs_path'] ?? RuntimePaths::logsRoot());
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
TenantGuard::enforce($request);

if (PortalContext::isPublic() && str_starts_with($request->path(), '/api')) {
    Response::json([
        'ok' => false,
        'success' => false,
        'message' => 'Community Control Center đang bị tắt.',
        'errors' => [],
        'error' => [
            'message' => 'Community Control Center đang bị tắt.',
            'reason' => 'control_center_disabled',
        ],
        'status' => 404,
    ], 404);
}

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
if ($request->method() === 'GET' && preg_match('#^/api/platform/assets/([a-z_]+)/([a-z0-9.-]+)$#', $request->path(), $matches)) {
    (new PlatformSettingsController($request))->asset($matches[1], $matches[2]);
}
if (PortalContext::isControlCenter() && $request->method() === 'POST' && in_array($request->path(), ['/api/platform/settings/assets', '/api/platform/settings/assets/reset'], true)) {
    $controller = new PlatformSettingsController($request);
    if ($request->path() === '/api/platform/settings/assets') {
        $controller->uploadAsset();
    } else {
        $controller->resetAsset();
    }
    exit;
}
if ($request->path() === '/favicon.ico') {
    try {
        $branding = (new PlatformBrandingService())->publicBranding();
        $stored = (string) ($branding['favicon']['stored'] ?? '');
        if ($stored !== '') {
            (new PlatformSettingsController($request))->asset('favicon', basename($stored));
        }
    } catch (Throwable) {
    }
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
if (PortalContext::isControlCenter() && str_starts_with($request->path(), '/api')) {
    if (str_starts_with($request->path(), '/api/control-center/')) {
        $controller = new ControlCenterController($request);
        $authController = new ControlCenterAuthController($request);
        $unitsController = new AdministrativeUnitController($request);
        $tenantInstallerController = new TenantInstallerController($request);
        $tenantsController = new TenantManagementController($request);
        $usersController = new ControlCenterUserController($request);
        $permissionsController = new ControlCenterPermissionController($request);
        $platformSettingsController = new PlatformSettingsController($request);
        $path = $request->path();
        $method = $request->method();
        if ($method === 'POST' && $path === '/api/control-center/login') {
            $authController->login();
        }
        if ($method === 'GET' && $path === '/api/control-center/me') {
            $authController->me();
        }
        if ($method === 'POST' && $path === '/api/control-center/logout') {
            $authController->logout();
        }
        if ($method === 'GET' && $path === '/api/control-center/units') {
            $unitsController->index();
        }
        if ($method === 'POST' && $path === '/api/control-center/units') {
            $unitsController->store();
        }
        if ($method === 'GET' && $path === '/api/control-center/tenants') {
            $tenantsController->index();
        }
        if ($method === 'POST' && $path === '/api/control-center/tenants') {
            $tenantsController->store();
        }
        if ($method === 'POST' && $path === '/api/control-center/tenant-installer') {
            $tenantInstallerController->start();
        }
        if ($method === 'POST' && $path === '/api/control-center/tenant-installer/preflight') {
            $tenantInstallerController->preflight();
        }
        if ($method === 'POST' && $path === '/api/control-center/tenant-installer/database-check') {
            $tenantInstallerController->databaseCheck();
        }
        if ($method === 'POST' && $path === '/api/control-center/tenant-installer/dry-run') {
            $tenantInstallerController->dryRun();
        }
        if ($method === 'GET' && preg_match('#^/api/control-center/tenant-installer/(\d+)$#', $path, $matches)) {
            $tenantInstallerController->show($matches[1]);
        }
        if ($method === 'POST' && preg_match('#^/api/control-center/tenant-installer/(\d+)/retry$#', $path, $matches)) {
            $tenantInstallerController->retry($matches[1]);
        }
        if ($method === 'POST' && preg_match('#^/api/control-center/tenant-installer/(\d+)/rollback$#', $path, $matches)) {
            $tenantInstallerController->rollback($matches[1]);
        }
        if ($method === 'GET' && preg_match('#^/api/control-center/units/(\d+)$#', $path, $matches)) {
            $unitsController->show($matches[1]);
        }
        if ($method === 'PUT' && preg_match('#^/api/control-center/units/(\d+)$#', $path, $matches)) {
            $unitsController->update($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/units/(\d+)/lock$#', $path, $matches)) {
            $unitsController->lock($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/units/(\d+)/activate$#', $path, $matches)) {
            $unitsController->activate($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/units/(\d+)/check-connection$#', $path, $matches)) {
            $unitsController->checkConnection($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/units/(\d+)/check-website$#', $path, $matches)) {
            $unitsController->checkWebsite($matches[1]);
        }
        if ($method === 'POST' && preg_match('#^/api/control-center/units/(\d+)/open-portal$#', $path, $matches)) {
            $unitsController->openPortal($matches[1]);
        }
        if ($method === 'GET' && preg_match('#^/api/control-center/tenants/(\d+)/activity$#', $path, $matches)) {
            $tenantsController->activity($matches[1]);
        }
        if ($method === 'GET' && preg_match('#^/api/control-center/tenants/(\d+)$#', $path, $matches)) {
            $tenantsController->show($matches[1]);
        }
        if ($method === 'PUT' && preg_match('#^/api/control-center/tenants/(\d+)$#', $path, $matches)) {
            $tenantsController->update($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/tenants/(\d+)/lock$#', $path, $matches)) {
            $tenantsController->lock($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/tenants/(\d+)/unlock$#', $path, $matches)) {
            $tenantsController->unlock($matches[1]);
        }
        if ($method === 'DELETE' && preg_match('#^/api/control-center/tenants/(\d+)$#', $path, $matches)) {
            $tenantsController->destroy($matches[1]);
        }
        if ($method === 'GET' && $path === '/api/control-center/users') {
            $usersController->index();
        }
        if ($method === 'POST' && $path === '/api/control-center/users') {
            $usersController->store();
        }
        if ($method === 'GET' && preg_match('#^/api/control-center/users/(\d+)$#', $path, $matches)) {
            $usersController->show($matches[1]);
        }
        if ($method === 'PUT' && preg_match('#^/api/control-center/users/(\d+)$#', $path, $matches)) {
            $usersController->update($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/users/(\d+)/deactivate$#', $path, $matches)) {
            $usersController->deactivate($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/users/(\d+)/activate$#', $path, $matches)) {
            $usersController->activate($matches[1]);
        }
        if ($method === 'PATCH' && preg_match('#^/api/control-center/users/(\d+)/reset-password$#', $path, $matches)) {
            $usersController->resetPassword($matches[1]);
        }
        if ($method === 'GET' && $path === '/api/control-center/permissions') {
            $permissionsController->index();
        }
        if ($method === 'PUT' && $path === '/api/control-center/permissions') {
            $permissionsController->update();
        }
        if ($method === 'PATCH' && $path === '/api/control-center/permissions/reset') {
            $permissionsController->reset();
        }
        if ($method === 'POST' && ($path === '/api/control-center/configuration/assets' || $path === '/api/platform/settings/assets')) {
            $platformSettingsController->uploadAsset();
        }
        if ($method === 'POST' && ($path === '/api/control-center/configuration/assets/reset' || $path === '/api/platform/settings/assets/reset')) {
            $platformSettingsController->resetAsset();
        }
        if ($method === 'GET' && $path === '/api/control-center/configuration') {
            $platformSettingsController->show();
        }
        if ($method === 'PUT' && $path === '/api/control-center/configuration') {
            $platformSettingsController->update();
        }
        if ($method === 'PUT' && $path === '/api/control-center/configuration/secret') {
            $platformSettingsController->updateSecret();
        }
        if ($method === 'POST' && $path === '/api/control-center/configuration/check-registry') {
            $platformSettingsController->checkRegistry();
        }
        if ($method === 'POST' && $path === '/api/control-center/configuration/check-backup') {
            $platformSettingsController->checkBackup();
        }
        if ($method === 'POST' && $path === '/api/control-center/configuration/test-email') {
            $platformSettingsController->testEmail();
        }
        if ($method === 'PATCH' && $path === '/api/control-center/configuration/maintenance') {
            $platformSettingsController->maintenance();
        }
        match ($request->path()) {
            '/api/control-center/status' => $controller->status(),
            '/api/control-center/dashboard' => $controller->dashboard(),
            '/api/control-center/accounts' => $controller->accounts(),
            '/api/control-center/monitoring' => $controller->monitoring(),
            '/api/control-center/audit' => $controller->auditLogs(),
            default => Response::error('API Community Control Center không tồn tại', 404),
        };
    }
    Response::error('API nghiệp vụ đơn vị không khả dụng trên Community Control Center', 404);
}

$router = new Router($request);

$router->get('/api/public/login-config', [SettingController::class, 'publicLoginConfig']);

$router->post('/api/setup', [AuthController::class, 'setup']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/logout', [AuthController::class, 'logout']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->post('/api/auth/keepalive', [AuthController::class, 'keepAlive']);
$router->get('/api/me', [AuthController::class, 'me']);

$router->get('/api/dashboard', [DashboardController::class, 'summary']);
$router->get('/api/dashboard/summary', [DashboardController::class, 'summary']);
$router->get('/api/dashboard/executive', [DashboardController::class, 'executive']);
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

$router->get('/api/data-quality/summary', [DataQualityController::class, 'summary']);
$router->get('/api/data-quality/issues', [DataQualityController::class, 'issues']);
$router->get('/api/data-quality/issue', [DataQualityController::class, 'issueDetail']);

$router->get('/api/policy-alerts/summary', [PolicyAlertController::class, 'summary']);
$router->get('/api/policy-alerts', [PolicyAlertController::class, 'index']);
$router->get('/api/policy-alerts/report', [PolicyAlertController::class, 'report']);
$router->get('/api/policy-alerts/print', [PolicyAlertController::class, 'print']);
$router->get('/api/policy-alerts/export-excel', [PolicyAlertController::class, 'exportExcel']);
$router->get('/api/policy-alerts/export-pdf', [PolicyAlertController::class, 'exportPdf']);
$router->post('/api/policy-alerts/{citizenId}/mark', [PolicyAlertController::class, 'mark']);

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
$router->get('/api/agricultural-land', [AgriculturalLandZoneController::class, 'index']);
$router->post('/api/agricultural-land', [AgriculturalLandZoneController::class, 'store']);
$router->get('/api/agricultural-land/dashboard', [AgriculturalLandZoneController::class, 'dashboard']);
$router->get('/api/agricultural-land/catalogs', [AgriculturalLandZoneController::class, 'catalogs']);
$router->get('/api/agricultural-land/settings', [AgriculturalLandZoneController::class, 'settings']);
$router->put('/api/agricultural-land/settings', [AgriculturalLandZoneController::class, 'updateSettings']);
$router->get('/api/agricultural-land/usage-types', [AgriculturalLandZoneController::class, 'usageTypes']);
$router->post('/api/agricultural-land/usage-types', [AgriculturalLandZoneController::class, 'storeUsageType']);
$router->put('/api/agricultural-land/usage-types/{id}', [AgriculturalLandZoneController::class, 'updateUsageType']);
$router->delete('/api/agricultural-land/usage-types/{id}', [AgriculturalLandZoneController::class, 'deleteUsageType']);
$router->get('/api/agricultural-land/report', [AgriculturalLandZoneController::class, 'report']);
$router->get('/api/agricultural-land/{id}', [AgriculturalLandZoneController::class, 'show']);
$router->put('/api/agricultural-land/{id}', [AgriculturalLandZoneController::class, 'update']);
$router->delete('/api/agricultural-land/{id}', [AgriculturalLandZoneController::class, 'destroy']);
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
$router->get('/api/documents/{id}/download', [VillageDocumentController::class, 'downloadPrimary']);
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

$router->get('/api/party-members', [PartyMemberController::class, 'index']);
$router->post('/api/party-members', [PartyMemberController::class, 'store']);
$router->get('/api/party-members/dashboard', [PartyMemberController::class, 'dashboard']);
$router->get('/api/party-members/catalogs', [PartyMemberController::class, 'catalogs']);
$router->get('/api/party-members/citizen-search', [PartyMemberController::class, 'citizenSearch']);
$router->post('/api/party-members/{id}/restore', [PartyMemberController::class, 'restore']);
$router->get('/api/party-members/{id}', [PartyMemberController::class, 'show']);
$router->put('/api/party-members/{id}', [PartyMemberController::class, 'update']);
$router->delete('/api/party-members/{id}', [PartyMemberController::class, 'destroy']);

$router->get('/api/poverty/catalogs', [HouseholdPovertyController::class, 'catalogs']);
$router->get('/api/poverty/dashboard', [HouseholdPovertyController::class, 'dashboard']);
$router->get('/api/poverty/report', [HouseholdPovertyController::class, 'report']);
$router->get('/api/poverty/export-excel', [HouseholdPovertyController::class, 'exportExcel']);
$router->get('/api/poverty/export-pdf', [HouseholdPovertyController::class, 'exportPdf']);
$router->get('/api/poverty/households/search', [HouseholdPovertyController::class, 'householdSearch']);
$router->get('/api/poverty/households/{householdId}/history', [HouseholdPovertyController::class, 'householdHistory']);
$router->get('/api/poverty/periods', [HouseholdPovertyController::class, 'periods']);
$router->post('/api/poverty/periods', [HouseholdPovertyController::class, 'storePeriod']);
$router->get('/api/poverty/periods/{id}', [HouseholdPovertyController::class, 'showPeriod']);
$router->put('/api/poverty/periods/{id}', [HouseholdPovertyController::class, 'updatePeriod']);
$router->delete('/api/poverty/periods/{id}', [HouseholdPovertyController::class, 'deletePeriod']);
$router->get('/api/poverty/records', [HouseholdPovertyController::class, 'index']);
$router->post('/api/poverty/records', [HouseholdPovertyController::class, 'store']);
$router->get('/api/poverty/records/{id}', [HouseholdPovertyController::class, 'show']);
$router->put('/api/poverty/records/{id}', [HouseholdPovertyController::class, 'update']);
$router->delete('/api/poverty/records/{id}', [HouseholdPovertyController::class, 'destroy']);

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

    if (PortalContext::isPublic()) {
        echo '<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Hong Phong Community Platform</title><style>body{margin:0;font-family:Arial,sans-serif;background:#f3f6f9;color:#111827;min-height:100vh;display:flex;align-items:center;justify-content:center}.panel{max-width:560px;background:#fff;border:1px solid #d7dee8;border-radius:12px;padding:32px;box-shadow:0 24px 80px rgba(15,23,42,.12)}.mark{width:48px;height:48px;border-radius:12px;background:#0f766e;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;margin-bottom:18px}h1{font-size:24px;margin:0 0 10px}p{line-height:1.6;margin:0;color:#4b5563}.status{margin-top:20px;padding:12px 14px;border-radius:8px;background:#eef6f5;color:#0f766e;font-weight:700}</style></head><body><main class="panel"><div class="mark">HP</div><h1>Hong Phong Community Platform</h1><p>Community Control Center đang ở chế độ bảo trì cấu hình. Cổng đơn vị vẫn hoạt động trên các tên miền phụ riêng.</p><div class="status">Chế độ bảo trì</div></main></body></html>';
        exit;
    }

    if (PortalContext::isControlCenter()) {
        $html = file_get_contents(BASE_PATH . '/views/control-center.php');
        if ($html === false) {
            http_response_code(500);
            echo 'Không tải được giao diện Community Control Center.';
            exit;
        }

        $branding = (new PlatformBrandingService())->publicBranding();
        $settings = [
            'portal' => PortalContext::type(),
            'host' => PortalContext::host(),
            'appName' => env_value('APP_NAME') ?: 'Community Control Center',
            'sessionTtlSeconds' => (int) (env_value('SESSION_TTL_SECONDS') ?: 21600),
            'idleTimeoutSeconds' => (int) (env_value('IDLE_TIMEOUT_SECONDS') ?: 900),
            'idleWarningSeconds' => (int) (env_value('IDLE_WARNING_SECONDS') ?: 60),
            'branding' => $branding,
        ];
        $escapeHtml = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $controlCenterLogoUrl = (string) ($branding['control_center_logo']['url'] ?? '');
        $controlCenterLogoHtml = $controlCenterLogoUrl !== '' ? '<img src="' . $escapeHtml($controlCenterLogoUrl) . '" alt="Community Control Center logo">' : 'CC';
        $platformFaviconUrl = (string) ($branding['favicon']['url'] ?? '/favicon.ico');
        $html = strtr($html, [
            '{{APP_NAME}}' => $escapeHtml((string) ($settings['appName'] ?? 'Community Control Center')),
            '{{APP_SETTINGS_JSON}}' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}',
            '{{CONTROL_CENTER_LOGO_HTML}}' => $controlCenterLogoHtml,
            '{{PLATFORM_FAVICON_URL}}' => $escapeHtml($platformFaviconUrl),
            'assets/vendor/bootstrap/bootstrap.min.css' => versioned_asset('assets/vendor/bootstrap/bootstrap.min.css'),
            'assets/vendor/fontawesome-local.css' => versioned_asset('assets/vendor/fontawesome-local.css'),
            'assets/css/app.min.css' => versioned_asset('assets/css/app.min.css'),
        ]);
        echo $html;
        exit;
    }

    $html = file_get_contents(BASE_PATH . '/views/app.php');
    if ($html === false) {
        http_response_code(500);
        echo 'Không tải được giao diện ứng dụng.';
        exit;
    }
    $tenantSettings = TenantConfig::publicSettings();
    $tenantMark = trim((string) ($tenantSettings['hamletName'] ?? ''));
    if ($tenantMark === '') {
        $tenantMark = trim((string) ($tenantSettings['unitName'] ?? ''));
    }
    $tenantMark = mb_strtoupper(mb_substr($tenantMark !== '' ? $tenantMark : 'DV', 0, 3, 'UTF-8'), 'UTF-8');
    $tenantNamespaceSource = strtolower((string) (TenantContext::host() ?: ($_SERVER['HTTP_HOST'] ?? 'tenant')));
    $tenantNamespace = preg_replace('/[^a-z0-9]+/', '_', $tenantNamespaceSource) ?: 'tenant';
    $tenantSettings['tenantNamespace'] = trim($tenantNamespace, '_') ?: 'tenant';
    $tenantSettings['tenantHost'] = TenantContext::host();
    $tenantSettings['villageId'] = TenantContext::villageId();
    $appConfig = require BASE_PATH . '/config/app.php';
    $tenantSettings['sessionTtlSeconds'] = (int) $appConfig['session_ttl_seconds'];
    $tenantSettings['idleTimeoutSeconds'] = (int) $appConfig['idle_timeout_seconds'];
    $tenantSettings['idleWarningSeconds'] = (int) $appConfig['idle_warning_seconds'];
    $tenantSettings['citizenPolicyDefaults'] = [
        'bhytDefaultAge' => InsurancePolicy::DEFAULT_AGE,
        'socialAllowanceDefaultAge' => CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE,
        'elderlyOccupationDefaultAge' => CitizenPolicyDefaults::ELDERLY_OCCUPATION_DEFAULT_AGE,
        'academicYearStartMonth' => StudentStatusService::ACADEMIC_YEAR_START_MONTH,
        'studentMaxAcademicAge' => StudentStatusService::STUDENT_MAX_ACADEMIC_AGE,
        'ageBand05Max' => AgePolicy::AGE_BAND_0_5_MAX,
        'ageBand614Min' => AgePolicy::AGE_BAND_6_14_MIN,
        'ageBand614Max' => AgePolicy::AGE_BAND_6_14_MAX,
        'ageBand1517Min' => AgePolicy::AGE_BAND_15_17_MIN,
        'ageBand1517Max' => AgePolicy::AGE_BAND_15_17_MAX,
        'ageBand1859Min' => AgePolicy::AGE_BAND_18_59_MIN,
        'ageBand1859Max' => AgePolicy::AGE_BAND_18_59_MAX,
        'statisticalElderlyMinAge' => AgePolicy::STATISTICAL_ELDERLY_MIN_AGE,
        'studentLabel' => StudentStatusService::STUDENT_LABEL,
        'elderlyOccupationLabel' => InsurancePolicy::ELDERLY_OCCUPATION,
        'healthInsuranceDefaultOccupations' => InsurancePolicy::eligibleOccupations(),
    ];
    $escapeHtml = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $tenantLogoUrl = trim((string) ($tenantSettings['logoUrl'] ?? ''));
    $tenantLogoClass = $tenantLogoUrl !== ''
        ? 'login-logo login-logo-emblem login-logo-image'
        : 'login-logo login-logo-emblem';
    $tenantLogoHtml = $tenantLogoUrl !== ''
        ? '<img src="' . $escapeHtml($tenantLogoUrl) . '" alt="' . $escapeHtml(TenantConfig::unitName($tenantSettings)) . '">'
        : '<span></span><span></span><span></span><strong>{{TENANT_MARK}}</strong>';
    $loginBackgroundUrl = trim((string) ($tenantSettings['backgroundUrl'] ?? ''));
    $loginBackgroundStyle = $loginBackgroundUrl !== ''
        ? '--login-bg:linear-gradient(135deg,rgba(4,23,18,.74),rgba(12,87,61,.48)),url(&quot;' . $escapeHtml($loginBackgroundUrl) . '&quot;);'
        : '';
    $html = strtr($html, [
        '{{APP_NAME}}' => $escapeHtml((string) ($tenantSettings['systemName'] ?? 'Hệ thống Quản lý Hành chính')),
        '{{UNIT_NAME}}' => $escapeHtml(TenantConfig::unitName($tenantSettings)),
        '{{HAMLET_NAME}}' => $escapeHtml((string) ($tenantSettings['hamletName'] ?? '')),
        '{{COMMUNE_NAME}}' => $escapeHtml((string) ($tenantSettings['communeName'] ?? '')),
        '{{COPYRIGHT}}' => $escapeHtml((string) ($tenantSettings['copyright'] ?? '')),
        '{{TENANT_MARK}}' => $escapeHtml($tenantMark),
        '{{THEME_COLOR}}' => $escapeHtml((string) ($tenantSettings['themeColor'] ?? '#0b6b3a')),
        '{{BACKGROUND_COLOR}}' => $escapeHtml((string) ($tenantSettings['backgroundColor'] ?? '#eef3f8')),
        '{{TENANT_LOGO_CLASS}}' => $tenantLogoClass,
        '{{TENANT_LOGO_HTML}}' => str_replace('{{TENANT_MARK}}', $escapeHtml($tenantMark), $tenantLogoHtml),
        '{{LOGIN_BACKGROUND_STYLE}}' => $loginBackgroundStyle,
        '{{APP_SETTINGS_JSON}}' => json_encode($tenantSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}',
    ]);
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
        'assets/js/party-members.min.js',
        'assets/js/poverty-management.min.js',
        'assets/js/vehicles.min.js',
        'assets/js/contributions.min.js',
        'assets/js/agriculture.min.js',
        'assets/js/agricultural-land.min.js',
        'assets/js/houses.min.js',
        'assets/js/public-assets.min.js',
        'assets/js/complaints.min.js',
        'assets/js/work-tasks.min.js',
        'assets/js/work-calendar.min.js',
        'assets/js/documents.min.js',
        'assets/js/finance.min.js',
        'assets/js/photo-gallery.min.js',
        'assets/js/policy-alerts.min.js',
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
