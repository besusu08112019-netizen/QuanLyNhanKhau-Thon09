<?php

use App\Config\CitizenPolicyDefaults;
use App\Models\PolicyAlert;
use App\Policies\AgePolicy;
use App\Services\HealthInsuranceDefaultService;
use App\Services\StudentStatusService;

policy_test('AgePolicy exact age calculation', function (): void {
    $today = new DateTimeImmutable(PolicyTestMatrix::BASE_DATE);

    policy_assert_same(70, AgePolicy::ageFromDate('1956-07-29', $today), 'Age must be exact on birthday.');
    policy_assert_same(69, AgePolicy::ageFromDate('1956-07-30', $today), 'Age must not round up before birthday.');
    policy_assert_same(null, AgePolicy::ageFromDate('invalid', $today), 'Invalid date must return null.');

    foreach (PolicyTestMatrix::ages() as $row) {
        policy_assert_same($row['age'], AgePolicy::getAge($row['date_of_birth'], $today), 'Matrix age mismatch for ' . $row['date_of_birth']);
    }
});

policy_test('AgePolicy shared thresholds', function (): void {
    policy_assert_true(AgePolicy::isChildAge(15), 'Age 15 must be child.');
    policy_assert_false(AgePolicy::isChildAge(16), 'Age 16 must not be child.');
    policy_assert_true(AgePolicy::isStatisticalElderlyAge(60), 'Age 60 must be statistical elderly.');
    policy_assert_false(AgePolicy::isStatisticalElderlyAge(59), 'Age 59 must not be statistical elderly.');
    policy_assert_true(AgePolicy::isWorkingAge(16), 'Age 16 must be working age.');
    policy_assert_true(AgePolicy::isWorkingAge(59), 'Age 59 must be working age.');
    policy_assert_false(AgePolicy::isWorkingAge(60), 'Age 60 must not be working age.');
});

policy_test('AgePolicy citizen defaults remain stable', function (): void {
    policy_assert_same(70, CitizenPolicyDefaults::BHYT_DEFAULT_AGE, 'BHYT age threshold must stay 70.');
    policy_assert_same(75, CitizenPolicyDefaults::SOCIAL_ALLOWANCE_DEFAULT_AGE, 'Social allowance threshold must stay 75.');
    policy_assert_same(['social_assistance' => 0], CitizenPolicyDefaults::defaultsForAge(74), 'Age 74 must not auto-enable social assistance.');
    policy_assert_same(['social_assistance' => 1], CitizenPolicyDefaults::defaultsForAge(75), 'Age 75 must auto-enable social assistance.');
});

policy_test('AgePolicy student academic year rules remain stable', function (): void {
    $today = new DateTimeImmutable(PolicyTestMatrix::BASE_DATE);

    policy_assert_same(2025, StudentStatusService::academicYear(new DateTimeImmutable('2026-07-29')), 'Academic year before August must use previous calendar year.');
    policy_assert_same(2026, StudentStatusService::academicYear(new DateTimeImmutable('2026-08-01')), 'Academic year from August must use current calendar year.');
    policy_assert_true(StudentStatusService::statusForDateOfBirth('2008-01-01', $today)['isStudent'], 'Academic age 17 must be student.');
    policy_assert_false(StudentStatusService::statusForDateOfBirth('2007-01-01', $today)['isStudent'], 'Academic age 18 must not be student.');
});

policy_test('AgePolicy SQL expressions remain backward compatible', function (): void {
    policy_assert_same('TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE())', AgePolicy::ageSql('c'), 'Age SQL must preserve current expression.');
    policy_assert_same('TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 16', AgePolicy::childConditionSql('c'), 'Child SQL must preserve threshold.');
    policy_assert_same('TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 60', AgePolicy::statisticalElderlyConditionSql('c'), 'Elderly SQL must preserve threshold.');
    policy_assert_same('TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 16 AND 59', AgePolicy::workingAgeConditionSql('c'), 'Working age SQL must preserve threshold.');
    policy_assert_same('(CASE WHEN MONTH(CURDATE()) >= 8 THEN YEAR(CURDATE()) ELSE YEAR(CURDATE()) - 1 END)', AgePolicy::academicYearSql(), 'Academic year SQL must preserve current expression.');
});

policy_test('AgePolicy warning thresholds remain stable', function (): void {
    $age70Condition = PolicyAlert::filterCondition('age_70', 'c');
    $upcoming70Condition = PolicyAlert::filterCondition('upcoming_70', 'c');

    policy_assert_true(is_string($age70Condition) && str_contains($age70Condition, '>= 70'), 'Age 70 policy alert must keep reached age condition.');
    policy_assert_true(is_string($age70Condition) && str_contains($age70Condition, 'COALESCE(c.has_health_insurance,0)=0'), 'Age 70 policy alert must only include missing BHYT records.');
    policy_assert_true(is_string($upcoming70Condition) && str_contains($upcoming70Condition, 'BETWEEN 0 AND 90'), 'Upcoming age policy alert must keep 90-day lookahead.');
});

policy_test('AgePolicy golden dataset remains stable', function (): void {
    $today = new DateTimeImmutable(PolicyTestMatrix::BASE_DATE);

    foreach (PolicyGoldenDataset::citizens() as $row) {
        $citizen = $row['citizen'];
        $expected = $row['expected'];
        $age = AgePolicy::getAge($citizen['date_of_birth'], $today);

        if (array_key_exists('age', $expected)) policy_assert_same($expected['age'], $age, 'Golden age mismatch for ' . $citizen['citizen_code']);
        if (array_key_exists('age_group', $expected)) policy_assert_same($expected['age_group'], AgePolicy::getAgeGroup($age), 'Golden age group mismatch for ' . $citizen['citizen_code']);
        if (array_key_exists('is_child', $expected)) policy_assert_same($expected['is_child'], AgePolicy::isChildAge($age), 'Golden child flag mismatch for ' . $citizen['citizen_code']);
        if (array_key_exists('is_working_age', $expected)) policy_assert_same($expected['is_working_age'], AgePolicy::isWorkingAge($age), 'Golden working flag mismatch for ' . $citizen['citizen_code']);
        if (array_key_exists('is_statistical_elderly', $expected)) policy_assert_same($expected['is_statistical_elderly'], AgePolicy::isStatisticalElderlyAge($age), 'Golden elderly flag mismatch for ' . $citizen['citizen_code']);
        if (array_key_exists('has_default_health_insurance', $expected)) policy_assert_same($expected['has_default_health_insurance'], AgePolicy::hasDefaultHealthInsurance($age), 'Golden BHYT flag mismatch for ' . $citizen['citizen_code']);
        if (array_key_exists('eligible_for_social_support', $expected)) policy_assert_same($expected['eligible_for_social_support'], AgePolicy::eligibleForSocialSupport($age), 'Golden social support flag mismatch for ' . $citizen['citizen_code']);
        if (array_key_exists('is_student', $expected)) policy_assert_same($expected['is_student'], AgePolicy::isStudent($citizen['date_of_birth'], $today), 'Golden student flag mismatch for ' . $citizen['citizen_code']);
    }
});

policy_test('AgePolicy integrations keep occupation default', function (): void {
    $today = new DateTimeImmutable(PolicyTestMatrix::BASE_DATE);

    policy_assert_same(
        HealthInsuranceDefaultService::ELDERLY_OCCUPATION,
        HealthInsuranceDefaultService::defaultOccupationForDateOfBirth('1956-07-29', $today),
        'Age 70 must keep elderly occupation default.'
    );
});
