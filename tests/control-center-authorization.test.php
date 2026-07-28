<?php

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/app/Core/Autoloader.php';
require_once BASE_PATH . '/config/env.php';

\App\Core\Autoloader::register();
env_load(BASE_PATH);

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Request;
use App\Services\ControlCenterSuperAdminAuthorization;

function assertAuthFailure(callable $callback, int $status): void
{
    try {
        $callback();
    } catch (ControlCenterAuthorizationException $e) {
        if ($e->status() !== $status) {
            fwrite(STDERR, 'Expected status ' . $status . ', got ' . $e->status() . PHP_EOL);
            exit(1);
        }
        return;
    }

    fwrite(STDERR, 'Expected authorization failure ' . $status . PHP_EOL);
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/control-center/units';
$_SERVER['HTTP_HOST'] = 'hongphongnb.com';
unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_AUTH_TOKEN'], $_SERVER['HTTP_X_CSRF_TOKEN']);

$request = Request::capture();
$guard = new ControlCenterSuperAdminAuthorization($request);
assertAuthFailure(fn() => $guard->authorize('control_center.units.create'), 401);

$token = str_repeat('a', 64);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
unset($_SERVER['HTTP_X_CSRF_TOKEN']);
$request = Request::capture();
$guard = new ControlCenterSuperAdminAuthorization($request);
assertAuthFailure(fn() => $guard->authorize('control_center.units.create'), 419);

echo 'Control Center authorization tests passed.' . PHP_EOL;
