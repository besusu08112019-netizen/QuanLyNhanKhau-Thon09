<?php

final class PolicyFixtures
{
    public static function createHousehold(array $overrides = []): array
    {
        return array_merge([
            'household_code' => 'HH_POLICY_001',
            'head_name' => 'Policy Household',
            'address' => 'Policy Test Address',
            'status' => 'active',
        ], $overrides);
    }

    public static function createCitizen(array $overrides = []): array
    {
        return array_merge([
            'citizen_code' => 'CT_POLICY_001',
            'full_name' => 'Policy Citizen',
            'gender' => 'Nam',
            'date_of_birth' => '2000-01-01',
            'relationship' => 'Chu ho',
            'residence_status' => 'permanent',
            'life_status' => 'alive',
            'occupation' => 'Lao dong',
            'has_health_insurance' => 0,
            'social_assistance' => 0,
        ], $overrides);
    }

    public static function createStudent(array $overrides = []): array
    {
        return self::createCitizen(array_merge([
            'citizen_code' => 'CT_POLICY_STUDENT',
            'full_name' => 'Policy Student',
            'date_of_birth' => '2009-01-01',
            'occupation' => 'Hoc sinh',
        ], $overrides));
    }

    public static function createSenior(array $overrides = []): array
    {
        return self::createCitizen(array_merge([
            'citizen_code' => 'CT_POLICY_SENIOR',
            'full_name' => 'Policy Senior',
            'date_of_birth' => '1956-07-29',
            'occupation' => 'Nguoi cao tuoi (70+)',
            'has_health_insurance' => 1,
        ], $overrides));
    }

    public static function createDisabled(array $overrides = []): array
    {
        return self::createCitizen(array_merge([
            'citizen_code' => 'CT_POLICY_DISABLED',
            'full_name' => 'Policy Disabled Citizen',
            'disability_status' => 'disabled',
            'social_assistance' => 1,
        ], $overrides));
    }
}
