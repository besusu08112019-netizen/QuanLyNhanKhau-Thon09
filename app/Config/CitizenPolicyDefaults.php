<?php

namespace App\Config;

final class CitizenPolicyDefaults
{
    public const BHYT_DEFAULT_AGE = 70;
    public const SOCIAL_ALLOWANCE_DEFAULT_AGE = 75;

    public static function defaultsForAge(?int $age): array
    {
        if ($age === null || $age < 0) return [];

        return [
            'has_health_insurance' => $age >= self::BHYT_DEFAULT_AGE ? 1 : 0,
            'social_assistance' => $age >= self::SOCIAL_ALLOWANCE_DEFAULT_AGE ? 1 : 0,
        ];
    }
}
