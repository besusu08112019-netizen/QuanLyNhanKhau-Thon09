<?php

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Core\BaseModel;
use App\Services\CitizenInsightEngine;

$reflection = new ReflectionClass(CitizenInsightEngine::class);
$source = file_get_contents(BASE_PATH . '/app/Services/CitizenInsightEngine.php') ?: '';

assert_true($reflection->isFinal(), 'CitizenInsightEngine must be final.');
assert_true($reflection->isSubclassOf(BaseModel::class), 'CitizenInsightEngine must use BaseModel for tenant-aware reads.');
assert_true($reflection->hasMethod('summary'), 'CitizenInsightEngine must expose summary().');
assert_true(str_contains($source, 'PopulationStatistics'), 'CitizenInsightEngine must reuse PopulationStatistics.');
assert_true(str_contains($source, 'BusinessRuleCenter'), 'CitizenInsightEngine must expose BusinessRuleCenter health.');
assert_true(str_contains($source, 'AgePolicy::'), 'CitizenInsightEngine must use AgePolicy for age rules.');
assert_true(str_contains($source, 'InsurancePolicy::'), 'CitizenInsightEngine must use InsurancePolicy for insurance rules.');
assert_true(str_contains($source, 'HouseholdRelationPolicy::'), 'CitizenInsightEngine must use HouseholdRelationPolicy for household relation rules.');
assert_false((bool) preg_match('/thon0[0-9]|hongphongnb\\.com|nhhon5mp_thon/i', $source), 'CitizenInsightEngine must not hard-code tenant names, domains, or databases.');

$method = $reflection->getMethod('summary');
assert_same(1, $method->getNumberOfParameters(), 'summary() must accept optional filters only.');
assert_same('1.0.0', CitizenInsightEngine::VERSION, 'CitizenInsightEngine version must be explicit.');

echo "CitizenInsightEngine tests: PASS\n";

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
