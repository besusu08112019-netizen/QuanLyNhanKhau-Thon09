<?php

define('APP_ROOT', __DIR__);
define('APP_ASSET_VERSION', '20260703-gis-search-gps-1');

require_once __DIR__ . '/app/Core/Env.php';
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Router.php';
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Controllers/BaseController.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/DashboardController.php';
require_once __DIR__ . '/app/Controllers/HouseholdController.php';
require_once __DIR__ . '/app/Controllers/CitizenController.php';
require_once __DIR__ . '/app/Controllers/TemporaryResidenceController.php';
require_once __DIR__ . '/app/Controllers/TemporaryAbsenceController.php';
require_once __DIR__ . '/app/Controllers/MovementController.php';
require_once __DIR__ . '/app/Controllers/ImportController.php';
require_once __DIR__ . '/app/Controllers/ExportController.php';
require_once __DIR__ . '/app/Controllers/ReportController.php';
require_once __DIR__ . '/app/Controllers/AccountController.php';
require_once __DIR__ . '/app/Controllers/SystemController.php';
require_once __DIR__ . '/app/Controllers/GisController.php';
require_once __DIR__ . '/app/Models/BaseModel.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Models/Household.php';
require_once __DIR__ . '/app/Models/Citizen.php';
require_once __DIR__ . '/app/Models/Movement.php';
require_once __DIR__ . '/app/Models/SystemLog.php';
require_once __DIR__ . '/app/Models/InterfaceSetting.php';
require_once __DIR__ . '/app/Models/DashboardStats.php';
require_once __DIR__ . '/app/Models/GisArea.php';
require_once __DIR__ . '/app/Models/GisHouseholdLocation.php';
require_once __DIR__ . '/app/Models/GisSearch.php';
require_once __DIR__ . '/app/Services/Auth.php';
require_once __DIR__ . '/app/Services/Excel.php';
require_once __DIR__ . '/app/Services/Response.php';

use App\Core\Router;
use App\Core\Session;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HouseholdController;
use App\Controllers\CitizenController;
use App\Controllers\TemporaryResidenceController;
use App\Controllers\TemporaryAbsenceController;
use App\Controllers\MovementController;
use App\Controllers\ImportController;
use App\Controllers\ExportController;
use App\Controllers\ReportController;
use App\Controllers\AccountController;
use App\Controllers\SystemController;
use App\Controllers\GisController;

Session::start();

$router = new Router();

$router->get('/api/public/login-config', [SystemController::class, 'publicLoginConfig']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/logout', [AuthController::class, 'logout']);
$router->get('/api/me', [AuthController::class, 'me']);

$router->get('/api/dashboard', [DashboardController::class, 'stats']);

$router->get('/api/households', [HouseholdController::class, 'index']);
$router->post('/api/households', [HouseholdController::class, 'store']);
$router->get('/api/households/{id}', [HouseholdController::class, 'show']);
$router->put('/api/households/{id}', [HouseholdController::class, 'update']);
$router->delete('/api/households/{id}', [HouseholdController::class, 'delete']);
$router->post('/api/households/bulk-delete', [HouseholdController::class, 'bulkDelete']);
$router->post('/api/households/{id}/photo', [HouseholdController::class, 'uploadPhoto']);
$router->delete('/api/households/{id}/photo', [HouseholdController::class, 'deletePhoto']);

$router->get('/api/citizens', [CitizenController::class, 'index']);
$router->post('/api/citizens', [CitizenController::class, 'store']);
$router->get('/api/citizens/{id}', [CitizenController::class, 'show']);
$router->put('/api/citizens/{id}', [CitizenController::class, 'update']);
$router->delete('/api/citizens/{id}', [CitizenController::class, 'delete']);
$router->post('/api/citizens/bulk-delete', [CitizenController::class, 'bulkDelete']);

$router->get('/api/temporary-residence', [TemporaryResidenceController::class, 'index']);
$router->get('/api/temporary-absence', [TemporaryAbsenceController::class, 'index']);
$router->get('/api/movements', [MovementController::class, 'index']);
$router->post('/api/movements', [MovementController::class, 'store']);

$router->post('/api/import/check', [ImportController::class, 'check']);
$router->post('/api/import/execute', [ImportController::class, 'execute']);
$router->get('/api/import/template', [ImportController::class, 'template']);

$router->get('/api/export/excel', [ExportController::class, 'excel']);
$router->get('/api/export/templates', [ExportController::class, 'templates']);

$router->get('/api/reports', [ReportController::class, 'index']);
$router->get('/api/reports/export', [ReportController::class, 'export']);

$router->get('/api/accounts', [AccountController::class, 'index']);
$router->post('/api/accounts', [AccountController::class, 'store']);
$router->put('/api/accounts/{id}', [AccountController::class, 'update']);
$router->delete('/api/accounts/{id}', [AccountController::class, 'delete']);
$router->post('/api/accounts/{id}/reset-password', [AccountController::class, 'resetPassword']);
$router->get('/api/roles', [AccountController::class, 'roles']);
$router->get('/api/system/logs', [SystemController::class, 'logs']);
$router->get('/api/system/settings', [SystemController::class, 'settings']);
$router->put('/api/system/settings', [SystemController::class, 'updateSettings']);
$router->get('/api/system/interface', [SystemController::class, 'interfaceSettings']);
$router->put('/api/system/interface', [SystemController::class, 'updateInterfaceSettings']);
$router->post('/api/system/interface/upload', [SystemController::class, 'uploadInterfaceAsset']);
$router->delete('/api/system/interface/asset', [SystemController::class, 'deleteInterfaceAsset']);
$router->post('/api/system/backup', [SystemController::class, 'backup']);
$router->get('/api/system/backups', [SystemController::class, 'backups']);
$router->post('/api/system/restore', [SystemController::class, 'restore']);
$router->get('/api/system/health', [SystemController::class, 'health']);

$router->get('/api/gis/areas', [GisController::class, 'areas']);
$router->get('/api/gis/search', [GisController::class, 'search']);
$router->get('/api/gis/households', [GisController::class, 'households']);
$router->post('/api/gis/areas', [GisController::class, 'storeArea']);
$router->put('/api/gis/areas/{id}', [GisController::class, 'updateArea']);
$router->delete('/api/gis/areas/{id}', [GisController::class, 'deleteArea']);
$router->put('/api/gis/households/{id}/location', [GisController::class, 'saveHouseholdLocation']);
$router->delete('/api/gis/households/{id}/location', [GisController::class, 'clearHouseholdLocation']);
$router->get('/api/gis/export-pdf', [GisController::class, 'exportPdf']);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path)) {
    exit;
}

function versioned_asset(string $path): string
{
    $normalized = ltrim($path, '/');
    $file = APP_ROOT . '/' . $normalized;
    $version = defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1';
    if (is_file($file)) {
        $version .= '-' . filemtime($file);
    }
    $separator = str_contains($normalized, '?') ? '&' : '?';
    return $normalized . $separator . 'v=' . rawurlencode($version);
}

if ($path === '/' || !str_starts_with($path, '/api')) {
    $html = file_get_contents(__DIR__ . '/views/app.php');
    if ($html === false) {
        http_response_code(500);
        echo 'Không tải được giao diện ứng dụng.';
        exit;
    }

    $html = preg_replace('/<script>\s*window\.__THON09_INLINE_START__[\s\S]*?<\/script>/u', '', $html) ?? $html;
    $html = preg_replace('/<script>\s*window\.showApp\s*=\s*window\.showApp[\s\S]*?<\/script>/u', '', $html) ?? $html;
    $html = preg_replace('/<script>\s*\/\/ Sidebar toggle[\s\S]*?<\/script>/u', '', $html) ?? $html;
    $html = preg_replace('/<script>\s*\/\/ Default credentials[\s\S]*?<\/script>/u', '', $html) ?? $html;

    $versionedAssets = [
        'assets/js/csrf.js' => versioned_asset('assets/js/csrf.js'),
        'assets/js/gis.js' => versioned_asset('assets/js/gis.js'),
        'assets/js/gis-household-location.js' => versioned_asset('assets/js/gis-household-location.js'),
        'assets/js/household-photo-capture.js' => versioned_asset('assets/js/household-photo-capture.js'),
        'assets/js/household-photo-gps.js' => versioned_asset('assets/js/household-photo-gps.js'),
        'assets/js/gis-search.js' => versioned_asset('assets/js/gis-search.js'),
        'assets/js/reports.js' => versioned_asset('assets/js/reports.js'),
        'assets/js/reports-ui-fix.js' => versioned_asset('assets/js/reports-ui-fix.js'),
        'assets/js/mobile-design-system.js' => versioned_asset('assets/js/mobile-design-system.js'),
        'assets/js/household-member-popup.js' => versioned_asset('assets/js/household-member-popup.js'),
        'assets/css/design-system.css' => versioned_asset('assets/css/design-system.css'),
        'assets/css/mobile-design-system.css' => versioned_asset('assets/css/mobile-design-system.css'),
        'assets/css/dashboard-redesign.css' => versioned_asset('assets/css/dashboard-redesign.css'),
        'assets/css/login-redesign.css' => versioned_asset('assets/css/login-redesign.css'),
        'assets/css/mobile-person-card.css' => versioned_asset('assets/css/mobile-person-card.css'),
        'assets/css/mobile-tablet-responsive.css' => versioned_asset('assets/css/mobile-tablet-responsive.css'),
        'assets/css/sidebar-modern.css' => versioned_asset('assets/css/sidebar-modern.css'),
        'assets/css/header-cleanup.css' => versioned_asset('assets/css/header-cleanup.css'),
    ];

    foreach ($versionedAssets as $asset => $versioned) {
        $html = str_replace($asset, $versioned, $html);
    }

    $runtimeScripts = [
        'assets/js/view-inline-patches.js',
        'assets/js/mobile-design-system.js',
        'assets/js/gis-household-location.js',
        'assets/js/household-photo-capture.js',
        'assets/js/household-photo-gps.js',
        'assets/js/gis-search.js',
    ];
    $runtimeHtml = implode("\n", array_map(
        fn(string $script): string => '<script src="' . versioned_asset($script) . '"></script>',
        $runtimeScripts
    ));
    $html = preg_replace('/<\/body>/', $runtimeHtml . "\n</body>", $html, 1) ?? $html;

    echo $html;
    exit;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => ['message' => 'Không tìm thấy đường dẫn']], JSON_UNESCAPED_UNICODE);
