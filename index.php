<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\DatabaseException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\DashboardController;
use App\Controllers\FileController;
use App\Controllers\GisController;
use App\Controllers\HouseholdController;
use App\Controllers\ImportController;
use App\Controllers\LogController;
use App\Controllers\MovementController;
use App\Controllers\PermissionController;
use App\Controllers\PersonController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\UserController;

Autoloader::register();

set_exception_handler(function (Throwable $exception): void {
    if ($exception instanceof DatabaseException) {
        error_log('[DATABASE_EXCEPTION] ' . $exception->getMessage());
        Response::error($exception->getMessage(), 500);
        return;
    }
    $status = $exception instanceof RuntimeException ? 400 : 500;
    Response::error($exception->getMessage(), $status);
});

$request = Request::capture();
$router = new Router($request);

$router->get('/api/health', fn() => Response::ok(['status' => 'ok', 'app' => 'Quan Ly Nhan Khau Thon 09']));
$router->get('/api/public/login-config', [SettingController::class, 'publicLoginConfig']);

$router->post('/api/auth/setup', [AuthController::class, 'setup']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me', [AuthController::class, 'me']);

$router->get('/api/dashboard/summary', [DashboardController::class, 'summary']);
$router->get('/api/dashboard/population-chart', [DashboardController::class, 'populationChart']);
$router->get('/api/dashboard/household-chart', [DashboardController::class, 'householdChart']);
$router->get('/api/dashboard/age-chart', [DashboardController::class, 'ageChart']);

$router->get('/api/gis/areas', [GisController::class, 'areas']);
$router->post('/api/gis/areas', [GisController::class, 'storeArea']);
$router->put('/api/gis/areas/{id}', [GisController::class, 'updateArea']);
$router->delete('/api/gis/areas/{id}', [GisController::class, 'deleteArea']);
$router->get('/api/gis/export-pdf', [GisController::class, 'exportPdf']);

$router->get('/api/reports/summary', [ReportController::class, 'summary']);
$router->get('/api/reports/population', [ReportController::class, 'population']);
$router->get('/api/reports/household', [ReportController::class, 'household']);
$router->get('/api/reports/households', [ReportController::class, 'household']);
$router->get('/api/reports/temporary-residence', [ReportController::class, 'temporaryResidence']);
$router->get('/api/reports/temporary-absence', [ReportController::class, 'temporaryAbsence']);
$router->get('/api/reports/births', [ReportController::class, 'births']);
$router->get('/api/reports/deaths', [ReportController::class, 'deaths']);
$router->get('/api/reports/migration', [ReportController::class, 'migration']);
$router->get('/api/reports/export-excel', [ReportController::class, 'exportExcel']);
$router->get('/api/reports/export-pdf', [ReportController::class, 'exportPdf']);
$router->get('/api/reports/print', [ReportController::class, 'print']);

$router->get('/api/import/template', [ImportController::class, 'template']);
$router->post('/api/import/preview', [ImportController::class, 'preview']);
$router->post('/api/import/process', [ImportController::class, 'process']);
$router->post('/api/files/upload', [FileController::class, 'upload']);
$router->get('/api/files/{module}/{entityId}', [FileController::class, 'index']);

$router->get('/api/movements', [MovementController::class, 'index']);
$router->post('/api/movements', [MovementController::class, 'store']);
$router->get('/api/movements/types', [MovementController::class, 'types']);
$router->get('/api/movements/{id}', [MovementController::class, 'show']);
$router->put('/api/movements/{id}', [MovementController::class, 'update']);
$router->delete('/api/movements/{id}', [MovementController::class, 'destroy']);

$router->get('/api/users', [UserController::class, 'index']);
$router->post('/api/users', [UserController::class, 'store']);
$router->get('/api/users/{id}', [UserController::class, 'show']);
$router->put('/api/users/{id}', [UserController::class, 'update']);
$router->delete('/api/users/{id}', [UserController::class, 'destroy']);
$router->post('/api/users/{id}/lock', [UserController::class, 'lock']);
$router->post('/api/users/{id}/unlock', [UserController::class, 'unlock']);
$router->get('/api/roles', [UserController::class, 'roles']);
$router->get('/api/permissions', [PermissionController::class, 'index']);
$router->post('/api/permissions', [PermissionController::class, 'update']);
$router->get('/api/media/{folder}/{kind}/{year}/{month}/{file}', [SettingController::class, 'media']);
$router->get('/api/settings', [SettingController::class, 'index']);
$router->post('/api/settings', [SettingController::class, 'update']);
$router->post('/api/settings/media', [SettingController::class, 'uploadMedia']);
$router->post('/api/settings/media/delete', [SettingController::class, 'deleteMedia']);
$router->get('/api/logs', [LogController::class, 'index']);
$router->get('/api/backups', [BackupController::class, 'index']);
$router->post('/api/backups', [BackupController::class, 'create']);
$router->post('/api/backups/restore', [BackupController::class, 'restore']);

$router->get('/api/temporary-residence', [PersonController::class, 'temporaryResidence']);
$router->get('/api/temporary-absence', [PersonController::class, 'temporaryAbsence']);
$router->get('/api/households', [HouseholdController::class, 'index']);
$router->post('/api/households', [HouseholdController::class, 'store']);
$router->post('/api/households/bulk-delete', [HouseholdController::class, 'bulkDelete']);
$router->get('/api/households/{id}', [HouseholdController::class, 'show']);
$router->put('/api/households/{id}', [HouseholdController::class, 'update']);
$router->delete('/api/households/{id}', [HouseholdController::class, 'destroy']);

$router->get('/api/persons', [PersonController::class, 'index']);
$router->post('/api/persons', [PersonController::class, 'store']);
$router->post('/api/persons/bulk-delete', [PersonController::class, 'bulkDelete']);
$router->get('/api/persons/{id}', [PersonController::class, 'show']);
$router->put('/api/persons/{id}', [PersonController::class, 'update']);
$router->delete('/api/persons/{id}', [PersonController::class, 'destroy']);
$router->post('/api/persons/{id}/restore', [PersonController::class, 'restore']);

if (str_starts_with($request->path(), '/api/')) {
    $router->dispatch();
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

ob_start();
require BASE_PATH . '/views/app.php';
$html = (string) ob_get_clean();
$html = str_replace('assets/js/import.js?v=20260630-import-final-1', 'assets/js/import.js?v=20260701-import-nullguard-1', $html);
$html = str_replace('assets/css/app.css?v=20260701-gis-2', 'assets/css/app.css?v=20260701-sidebar-brand-1', $html);
$html = str_replace('</head>', '<link rel="stylesheet" href="assets/css/mobile-household.css?v=20260701-mobile-household-3">' . "\n" . '<link rel="stylesheet" href="assets/css/mobile-household-dienho.css?v=20260701-dienho-badge-1">' . "\n</head>", $html);
$html = str_replace(
    '<span class="state-mark small-mark">09</span>',
    '<div class="sidebar-official-logo" aria-label="Logo Thôn 09"><span class="sidebar-logo-flag"><i class="fa-solid fa-star"></i></span><span class="sidebar-logo-landmark"><i class="fa-solid fa-landmark"></i></span><span class="sidebar-logo-home"><i class="fa-solid fa-house-chimney"></i></span><span class="sidebar-logo-text">Thôn</span><strong>09</strong></div>',
    $html
);
$sidebarCss = <<<'HTML'
<style id="thon09-sidebar-redesign">
  .gov-sidebar{padding:14px 12px!important;overflow-x:hidden!important}.gov-brand{display:grid!important;grid-template-columns:1fr!important;justify-items:center!important;align-items:center!important;gap:8px!important;text-align:center!important;padding:8px 8px 14px!important;margin:0 0 8px!important;border-bottom:1px solid rgba(15,23,42,.1)!important}.gov-brand>div:not(.sidebar-official-logo){display:grid!important;justify-items:center!important;gap:2px!important;text-align:center!important;width:100%!important}.gov-brand strong,.gov-brand b,.gov-brand small{max-width:204px!important;margin:0 auto!important;letter-spacing:0!important;text-align:center!important;text-transform:uppercase!important}.gov-brand strong{color:#064e2e!important;font-size:12px!important;line-height:1.22!important;font-weight:800!important}.gov-brand b{color:#dc2626!important;font-size:17px!important;line-height:1.15!important;font-weight:850!important}.gov-brand small{color:#667085!important;font-size:12px!important;line-height:1.2!important;font-weight:650!important}.sidebar-official-logo{width:88px;height:88px;position:relative;display:grid;place-items:center;border-radius:50%;background:transparent;isolation:isolate;flex:0 0 auto}.sidebar-official-logo:before{content:'';position:absolute;inset:3px;border-radius:50%;background:radial-gradient(circle at 50% 50%,#fff 0 58%,transparent 59%),conic-gradient(from -18deg,#f2b705,#0f8f4d,#f2b705,#0f8f4d,#f2b705);box-shadow:0 10px 24px rgba(10,143,77,.12);z-index:-2}.sidebar-official-logo:after{content:'';position:absolute;inset:13px;border-radius:50%;border:2px solid #0a8f4d;background:linear-gradient(180deg,#fffef6 0%,#eef8ec 100%);z-index:-1}.sidebar-logo-flag,.sidebar-logo-landmark,.sidebar-logo-home,.sidebar-logo-text,.sidebar-official-logo strong{position:absolute;z-index:1}.sidebar-logo-flag{top:17px;left:50%;width:28px;height:17px;transform:translateX(-50%);border-radius:4px 10px 10px 4px;background:#dc2626;color:#facc15;display:grid;place-items:center;font-size:8px}.sidebar-logo-landmark{top:38px;left:24px;color:#08733e;font-size:19px}.sidebar-logo-home{top:41px;right:24px;color:#2f9a4b;font-size:15px}.sidebar-logo-text{top:56px;left:0;right:0;color:#0a8f4d;font-size:10px;line-height:1;font-weight:850;text-align:center;text-transform:uppercase}.sidebar-official-logo strong{top:66px;left:0;right:0;color:#dc2626;font-size:24px;line-height:.9;font-weight:900;text-align:center}.nav-section{margin:5px 0 8px!important}.nav-section-title{padding:0 10px 5px!important;font-size:10px!important;letter-spacing:.08em!important;color:#8a968e!important}.gov-nav{gap:0!important}.gov-nav .nav-link,.gov-logout{min-height:45px!important;border-radius:13px!important;padding:0 11px!important;gap:10px!important;font-size:13.5px!important;font-weight:700!important}.gov-nav .nav-link.active{background:#0A8F4D!important;color:#fff!important;box-shadow:0 12px 24px rgba(10,143,77,.22)!important}.gov-nav .nav-link.active i{color:#fff!important}.gov-nav .nav-link:hover:not(.active){background:#e9f7ef!important;color:#0A8F4D!important}body.sidebar-collapsed .gov-brand>div:not(.sidebar-official-logo),body.sidebar-collapsed .nav-section-title,body.sidebar-collapsed .gov-nav .nav-link span,body.sidebar-collapsed .gov-logout span{display:none!important}body.sidebar-collapsed .sidebar-official-logo{width:52px;height:52px}body.sidebar-collapsed .sidebar-logo-flag{top:10px;width:18px;height:11px;font-size:6px}body.sidebar-collapsed .sidebar-logo-landmark{top:23px;left:14px;font-size:11px}body.sidebar-collapsed .sidebar-logo-home{top:24px;right:14px;font-size:10px}body.sidebar-collapsed .sidebar-logo-text{top:34px;font-size:7px}body.sidebar-collapsed .sidebar-official-logo strong{top:39px;font-size:14px}@media(max-width:991px){.gov-sidebar{width:min(240px,86vw)!important;max-width:86vw!important}}@media(max-width:360px){.gov-sidebar{width:min(232px,90vw)!important;max-width:90vw!important}.gov-nav .nav-link,.gov-logout{font-size:13px!important}}
</style>
HTML;
$html = preg_replace('/<\/head>/', $sidebarCss . "\n</head>", $html, 1);
echo $html;