<?php

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use App\Config\CitizenPolicyDefaults;
use App\Models\PolicyAlert;
use App\Policies\AgePolicy;
use App\Services\HealthInsuranceDefaultService;
use App\Services\StudentStatusService;

function assert_age_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . ' Expected: ' . var_export($expected, true) . ', actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$today = new DateTimeImmutable('2026-07-29');

assert_age_same(70, AgePolicy::ageFromDate('1956-07-29', $today), 'Age must be exact on birthday.');
assert_age_same(69, AgePolicy::ageFromDate('1956-07-30', $today), 'Age must not round up before birthday.');
assert_age_same(null, AgePolicy::ageFromDate('invalid', $today), 'Invalid date must return null.');

assert_age_same(true, AgePolicy::isChildAge(15), 'Age 15 must be child.');
assert_age_same(false, AgePolicy::isChildAge(16), 'Age 16 must not be child.');
assert_age_same(true, AgePolicy::isStatisticalElderlyAge(60), 'Age 60 must be statistical elderly.');
assert_age_same(false, AgePolicy::isStatisticalElderlyAge(59), 'Age 59 must not be statistical elderly.');
assert_age_same(true, AgePolicy::isWorkingAge(16), 'Age 16 must be working age.');
assert_age_same(true, AgePolicy::isWorkingAge(59), 'Age 59 must be working age.');
assert_age_same(false, AgePolicy::isWorkingAge(60), 'Age 60 must not be working age.');

assert_age_same(70, CitizenPolicyDefaults::BHYT_DEFAULT_AGE, 'BHYT age threshold must stay 70.');
assert_age_same(75, CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE, 'Social allowance threshold must stay 75.');
assert_age_same(['social_assistance' => 0], CitizenPolicyDefaults::defaultsForAge(74), 'Age 74 must not auto-enable social assistance.');
assert_age_same(['social_assistance' => 1], CitizenPolicyDefaults::defaultsForAge(75), 'Age 75 must auto-enable social assistance.');

assert_age_same(2025, StudentStatusService::academicYear(new DateTimeImmutable('2026-07-29')), 'Academic year before August must use previous calendar year.');
assert_age_same(2026, StudentStatusService::academicYear(new DateTimeImmutable('2026-08-01')), 'Academic year from August must use current calendar year.');
assert_age_same(true, StudentStatusService::statusForDateOfBirth('2008-01-01', $today)['isStudent'], 'Academic age 17 must be student.');
assert_age_same(false, StudentStatusService::statusForDateOfBirth('2007-01-01', $today)['isStudent'], 'Academic age 18 must not be student.');

assert_age_same('TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE())', AgePolicy::ageSql('c'), 'Age SQL must preserve current expression.');
assert_age_same('TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 16', AgePolicy::childConditionSql('c'), 'Child SQL must preserve threshold.');
assert_age_same('TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 60', AgePolicy::statisticalElderlyConditionSql('c'), 'Elderly SQL must preserve threshold.');
assert_age_same('TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 16 AND 59', AgePolicy::workingAgeConditionSql('c'), 'Working age SQL must preserve threshold.');
assert_age_same('(CASE WHEN MONTH(CURDATE()) >= 8 THEN YEAR(CURDATE()) ELSE YEAR(CURDATE()) - 1 END)', AgePolicy::academicYearSql(), 'Academic year SQL must preserve current expression.');

$age70Condition = PolicyAlert::filterCondition('age_70', 'c');
$upcoming70Condition = PolicyAlert::filterCondition('upcoming_70', 'c');
if (!is_string($age70Condition) || !str_contains($age70Condition, '>= 70')) {
    fwrite(STDERR, 'Age 70 policy alert must keep reached age condition.' . PHP_EOL);
    exit(1);
}
if (!is_string($upcoming70Condition) || !str_contains($upcoming70Condition, 'BETWEEN 0 AND 90')) {
    fwrite(STDERR, 'Upcoming age policy alert must keep 90-day lookahead.' . PHP_EOL);
    exit(1);
}

assert_age_same(HealthInsuranceDefaultService::ELDERLY_OCCUPATION, HealthInsuranceDefaultService::defaultOccupationForDateOfBirth('1956-07-29', $today), 'Age 70 must keep elderly occupation default.');

echo "AgePolicy tests passed.\n";
