<?php

namespace App\Config;

final class CitizenPolicyDefaults
{
    public const ELDERLY_OCCUPATION_DEFAULT_AGE = 70;
    public const BHYT_DEFAULT_AGE = 70;
    public const SOCIAL_ALLOWANCE_DEFAULT_AGE = 75;

    public static function defaultsForAge(?int $age): array
    {
        if ($age === null || $age < 0) return [];

        return [
            'social_assistance' => $age >= self::SOCIAL_ALLOWANCE_DEFAULT_AGE ? 1 : 0,
        ];
    }
}
