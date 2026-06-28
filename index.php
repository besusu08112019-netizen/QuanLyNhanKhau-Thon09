<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\DashboardController;
use App\Controllers\HouseholdController;
use App\Controllers\LogController;
use App\Controllers\PersonController;
use App\Controllers\ReportController;
use App\Controllers\UserController;

Autoloader::register();

set_exception_handler(function (Throwable $exception): void {
    Response::error($exception->getMessage(), 500);
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

$router->get('/api/reports/summary', [ReportController::class, 'summary']);
$router->get('/api/reports/population', [ReportController::class, 'population']);
$router->get('/api/reports/household', [ReportController::class, 'household']);
$router->get('/api/reports/export-excel', [ReportController::class, 'exportExcel']);
$router->get('/api/reports/export-pdf', [ReportController::class, 'exportPdf']);
$router->get('/api/reports/print', [ReportController::class, 'print']);

$router->get('/api/users', [UserController::class, 'index']);
$router->post('/api/users', [UserController::class, 'store']);
$router->get('/api/users/{id}', [UserController::class, 'show']);
$router->put('/api/users/{id}', [UserController::class, 'update']);
$router->delete('/api/users/{id}', [UserController::class, 'destroy']);
$router->post('/api/users/{id}/lock', [UserController::class, 'lock']);
$router->post('/api/users/{id}/unlock', [UserController::class, 'unlock']);
$router->get('/api/roles', [UserController::class, 'roles']);
$router->get('/api/logs', [LogController::class, 'index']);
$router->get('/api/backups', [BackupController::class, 'index']);
$router->post('/api/backups', [BackupController::class, 'create']);
$router->post('/api/backups/restore', [BackupController::class, 'restore']);

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
