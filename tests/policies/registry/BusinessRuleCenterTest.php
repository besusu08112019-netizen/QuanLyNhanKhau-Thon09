<?php

use App\PolicyEngine\BusinessRuleCenter;
use App\PolicyEngine\PolicyRegistry;

policy_test('BusinessRuleCenter discovers policy classes without hard-coded registration list', function (): void {
    $registry = new PolicyRegistry();
    $policies = $registry->all();

    policy_assert_array_has_keys(['age', 'household_relation', 'insurance'], $policies, 'Registry must discover existing policy classes.');
    policy_assert_same('App\\Policies\\AgePolicy', $policies['age']->className, 'AgePolicy class must be registered.');
    policy_assert_same('App\\Policies\\InsurancePolicy', $policies['insurance']->className, 'InsurancePolicy class must be registered.');
    policy_assert_same('App\\Policies\\HouseholdRelationPolicy', $policies['household_relation']->className, 'HouseholdRelationPolicy class must be registered.');
});

policy_test('BusinessRuleCenter exposes policy metadata', function (): void {
    $metadata = (new PolicyRegistry())->find('age');

    policy_assert_true($metadata !== null, 'Age policy metadata must exist.');
    policy_assert_same('Age', $metadata->name, 'Policy name must be derived from policy id.');
    policy_assert_same('1.0.0', $metadata->version, 'Policy default version must be available.');
    policy_assert_same('Policy Engine', $metadata->owner, 'Policy default owner must be available.');
    policy_assert_same(PolicyRegistry::STATUS_READY, $metadata->status, 'Policy health status must be READY.');
    policy_assert_same(PolicyRegistry::TEST_PASS, $metadata->testStatus, 'Policy test status must be PASS when test file exists.');
});

policy_test('BusinessRuleCenter detects dependencies from policy source', function (): void {
    $registry = new PolicyRegistry();

    policy_assert_same([], $registry->find('age')->dependencies ?? null, 'AgePolicy must not depend on another policy.');
    policy_assert_same(['age'], $registry->find('insurance')->dependencies ?? null, 'InsurancePolicy must depend on AgePolicy.');
});

policy_test('BusinessRuleCenter health summarizes policy readiness', function (): void {
    $health = (new BusinessRuleCenter())->health();

    policy_assert_same(PolicyRegistry::STATUS_READY, $health['status'], 'Policy center health must be READY.');
    policy_assert_same(3, $health['total'], 'Policy center must include all current policies.');
    policy_assert_same(3, $health['ready'], 'All current policies must be READY.');
    policy_assert_same(0, $health['disabled'], 'No current policy should be disabled.');
    policy_assert_same(0, $health['deprecated'], 'No current policy should be deprecated.');
    policy_assert_same(0, $health['error'], 'No current policy should be in ERROR.');
    policy_assert_same(0, $health['missingTests'], 'Every current policy must have Policy Test Suite coverage.');
});

policy_test('BusinessRuleCenter generates documentation from registry metadata', function (): void {
    $documentation = (new BusinessRuleCenter())->documentation();
    $ids = array_column($documentation, 'id');

    policy_assert_true(in_array('age', $ids, true), 'Generated documentation must include AgePolicy.');
    policy_assert_true(in_array('insurance', $ids, true), 'Generated documentation must include InsurancePolicy.');
    policy_assert_true(in_array('household_relation', $ids, true), 'Generated documentation must include HouseholdRelationPolicy.');
});
