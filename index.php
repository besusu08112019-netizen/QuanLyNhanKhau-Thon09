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
    }
    $status = $exception instanceof RuntimeException ? 400 : 500;
    Response::error($exception->getMessage(), $status);
});

$request = Request::capture();
$router = new Router($request);

$router->get('/api/health', fn() => Response::ok(['status' => 'ok', 'app' => 'Quan Ly Nhan Khau Thon 09']));

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
$router->get('/api/public/login-config', [SettingController::class, 'publicLoginConfig']);
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

require BASE_PATH . '/views/app.php';
