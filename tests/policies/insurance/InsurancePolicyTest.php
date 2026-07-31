<?php

use App\Policies\InsurancePolicy;
use App\Services\HealthInsuranceDefaultService;

policy_test('InsurancePolicy default occupations remain stable', function (): void {
    $today = new DateTimeImmutable(PolicyTestMatrix::BASE_DATE);

    policy_assert_same(InsurancePolicy::STUDENT_OCCUPATION, InsurancePolicy::defaultOccupationForDateOfBirth('2009-01-01', $today), 'Student academic age must keep BHYT occupation default.');
    policy_assert_same(InsurancePolicy::ELDERLY_OCCUPATION, InsurancePolicy::defaultOccupationForDateOfBirth('1956-07-29', $today), 'Age 70 must keep elderly BHYT occupation default.');
    policy_assert_same(null, InsurancePolicy::defaultOccupationForDateOfBirth('1967-07-29', $today), 'Working-age citizen must not receive BHYT occupation default.');
});

policy_test('InsurancePolicy occupation eligibility remains stable', function (): void {
    policy_assert_same(1, InsurancePolicy::defaultForLaborOccupation(InsurancePolicy::STUDENT_OCCUPATION), 'Student occupation must default to BHYT.');
    policy_assert_same(1, InsurancePolicy::defaultForLaborOccupation(InsurancePolicy::ELDERLY_OCCUPATION), 'Elderly occupation must default to BHYT.');
    policy_assert_same(1, InsurancePolicy::defaultForLaborOccupation('nguoi cao tuoi 70+'), 'Normalized elderly occupation must default to BHYT.');
    policy_assert_same(null, InsurancePolicy::defaultForLaborOccupation('Lao dong'), 'Normal labor occupation must not default to BHYT.');
    policy_assert_same('', InsurancePolicy::normalize(null), 'Empty occupation normalization must stay empty.');
});

policy_test('InsurancePolicy default BHYT eligibility includes students and age 70+', function (): void {
    $today = new DateTimeImmutable(PolicyTestMatrix::BASE_DATE);

    policy_assert_true(InsurancePolicy::hasDefaultHealthInsuranceForDateOfBirth('2009-01-01', $today), 'Student academic age must be default BHYT eligible.');
    policy_assert_true(InsurancePolicy::hasDefaultHealthInsuranceForDateOfBirth('1956-07-29', $today), 'Age 70+ must be default BHYT eligible.');
    policy_assert_false(InsurancePolicy::hasDefaultHealthInsuranceForDateOfBirth('1967-07-29', $today), 'Normal working-age citizen must not be default BHYT eligible.');
    policy_assert_same('(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 70 OR ((CASE WHEN MONTH(CURDATE()) >= 8 THEN YEAR(CURDATE()) ELSE YEAR(CURDATE()) - 1 END) - YEAR(c.date_of_birth) <= 17))', InsurancePolicy::defaultEligibilitySql('c'), 'Default eligibility SQL must include students and age 70+.');
});

policy_test('InsurancePolicy SQL predicates remain backward compatible', function (): void {
    policy_assert_same('COALESCE(c.has_health_insurance,0)=1', InsurancePolicy::enrolledConditionSql('c'), 'Enrolled SQL must remain stable.');
    policy_assert_same('COALESCE(c.has_health_insurance,0)=0', InsurancePolicy::missingConditionSql('c'), 'Missing SQL must remain stable.');
    policy_assert_same('(COALESCE(c.has_health_insurance,0)=1 AND (c.health_insurance_end_date IS NULL OR c.health_insurance_end_date >= CURDATE()))', InsurancePolicy::effectiveConditionSql('c'), 'Effective SQL must remain stable.');
    policy_assert_same('COALESCE(c.has_health_insurance,0)=1 AND c.health_insurance_end_date IS NOT NULL AND c.health_insurance_end_date < CURDATE()', InsurancePolicy::expiredConditionSql('c'), 'Expired SQL must remain stable.');
    policy_assert_same('COALESCE(c.has_health_insurance,0)=1 AND c.health_insurance_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)', InsurancePolicy::expiringConditionSql('c'), 'Expiring SQL must remain stable.');
});

policy_test('InsurancePolicy compatibility facade remains stable', function (): void {
    $today = new DateTimeImmutable(PolicyTestMatrix::BASE_DATE);

    policy_assert_same(InsurancePolicy::ELDERLY_OCCUPATION, HealthInsuranceDefaultService::ELDERLY_OCCUPATION, 'Facade elderly constant must delegate to InsurancePolicy.');
    policy_assert_same(InsurancePolicy::eligibleOccupations(), HealthInsuranceDefaultService::eligibleOccupations(), 'Facade occupation list must match InsurancePolicy.');
    policy_assert_same(InsurancePolicy::defaultOccupationForDateOfBirth('1956-07-29', $today), HealthInsuranceDefaultService::defaultOccupationForDateOfBirth('1956-07-29', $today), 'Facade DOB default must match InsurancePolicy.');
    policy_assert_same(InsurancePolicy::defaultForLaborOccupation('hoc sinh'), HealthInsuranceDefaultService::defaultForLaborOccupation('hoc sinh'), 'Facade occupation default must match InsurancePolicy.');
});
