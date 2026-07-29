<?php

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Services\CitizenInsightEngine;
use App\Services\ExecutiveDashboardService;
use App\Services\RiskWarningEngine;

$reflection = new ReflectionClass(ExecutiveDashboardService::class);
$source = file_get_contents(BASE_PATH . '/app/Services/ExecutiveDashboardService.php') ?: '';
$controller = file_get_contents(BASE_PATH . '/app/Controllers/DashboardController.php') ?: '';
$routes = file_get_contents(BASE_PATH . '/index.php') ?: '';
$dashboardJs = file_get_contents(BASE_PATH . '/assets/js/module-dashboards.js') ?: '';

assert_true($reflection->isFinal(), 'ExecutiveDashboardService must be final.');
assert_true($reflection->hasMethod('summary'), 'ExecutiveDashboardService must expose summary().');
assert_true(str_contains($source, 'CitizenInsightEngine'), 'ExecutiveDashboardService must use CitizenInsightEngine.');
assert_true(str_contains($source, 'RiskWarningEngine'), 'ExecutiveDashboardService must use RiskWarningEngine.');
assert_true(str_contains($source, 'BusinessRuleCenter'), 'ExecutiveDashboardService must expose BusinessRuleCenter health.');
assert_false(str_contains($source, 'extends BaseModel'), 'ExecutiveDashboardService must not query database directly.');
assert_false(str_contains($source, 'SELECT '), 'ExecutiveDashboardService must not calculate Dashboard data with SQL.');
assert_false((bool) preg_match('/thon0[0-9]|hongphongnb\\.com|nhhon5mp_thon/i', $source), 'ExecutiveDashboardService must not hard-code tenant names, domains, or databases.');
assert_same('1.0.0', ExecutiveDashboardService::VERSION, 'ExecutiveDashboardService version must be explicit.');

$constructor = $reflection->getConstructor();
assert_true($constructor !== null, 'ExecutiveDashboardService must allow dependency injection.');
$params = $constructor->getParameters();
assert_same(CitizenInsightEngine::class, $params[0]->getType()?->getName(), 'Constructor must accept CitizenInsightEngine.');
assert_same(RiskWarningEngine::class, $params[1]->getType()?->getName(), 'Constructor must accept RiskWarningEngine.');

assert_true(str_contains($controller, 'ExecutiveDashboardService'), 'DashboardController must call ExecutiveDashboardService.');
assert_true(str_contains($routes, '/api/dashboard/executive'), 'Executive Dashboard route must be registered.');
assert_true(str_contains($dashboardJs, '/api/dashboard/executive'), 'Dashboard UI must call Executive Dashboard API.');
assert_true(str_contains($dashboardJs, 'renderExecutiveDashboard'), 'Dashboard UI must render Executive Dashboard sections.');

echo "ExecutiveDashboardService tests: PASS\n";

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_false(bool $condition, string $message): void
{
    if ($condition) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', actual ' . var_export($actual, true));
    }
}
