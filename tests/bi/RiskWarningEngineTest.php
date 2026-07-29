<?php

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Core\BaseModel;
use App\Services\RiskWarningEngine;

$reflection = new ReflectionClass(RiskWarningEngine::class);
$source = file_get_contents(BASE_PATH . '/app/Services/RiskWarningEngine.php') ?: '';

assert_true($reflection->isFinal(), 'RiskWarningEngine must be final.');
assert_true($reflection->isSubclassOf(BaseModel::class), 'RiskWarningEngine must use BaseModel for tenant-aware reads.');
assert_true($reflection->hasMethod('warnings'), 'RiskWarningEngine must expose warnings().');
assert_true(str_contains($source, 'CitizenInsightEngine'), 'RiskWarningEngine must use CitizenInsightEngine.');
assert_true(str_contains($source, 'BusinessRuleCenter'), 'RiskWarningEngine must expose BusinessRuleCenter health.');
assert_true(str_contains($source, 'AgePolicy::'), 'RiskWarningEngine must use AgePolicy for age rules.');
assert_true(str_contains($source, 'InsurancePolicy::'), 'RiskWarningEngine must use InsurancePolicy for insurance rules.');
assert_true(str_contains($source, 'HouseholdRelationPolicy::'), 'RiskWarningEngine must use HouseholdRelationPolicy for household rules.');
assert_true(str_contains($source, 'PolicyAlert::filterCondition'), 'RiskWarningEngine must reuse existing policy alert conditions.');
assert_false((bool) preg_match('/thon0[0-9]|hongphongnb\\.com|nhhon5mp_thon/i', $source), 'RiskWarningEngine must not hard-code tenant names, domains, or databases.');
assert_false(str_contains($source, 'CREATE TABLE'), 'RiskWarningEngine must not change database schema.');
assert_false(str_contains($source, 'INSERT INTO'), 'RiskWarningEngine must not write data.');
assert_false(str_contains($source, 'UPDATE '), 'RiskWarningEngine must not update data.');
assert_false(str_contains($source, 'DELETE '), 'RiskWarningEngine must not delete data.');
assert_same('1.0.0', RiskWarningEngine::VERSION, 'RiskWarningEngine version must be explicit.');

echo "RiskWarningEngine tests: PASS\n";

function assert_true(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function assert_false(bool $condition, string $message): void
{
    if ($condition) throw new RuntimeException($message);
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', actual ' . var_export($actual, true));
    }
}
