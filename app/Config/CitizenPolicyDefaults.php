<?php

namespace App\Config;

use App\Policies\AgePolicy;

final class CitizenPolicyDefaults
{
    public const ELDERLY_OCCUPATION_DEFAULT_AGE = AgePolicy::ELDERLY_OCCUPATION_DEFAULT_AGE;
    public const BHYT_DEFAULT_AGE = AgePolicy::BHYT_DEFAULT_AGE;
    public const SOCIAL_ALLOWANCE_DEFAULT_AGE = AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE;

    public static function defaultsForAge(?int $age): array
    {
        if ($age === null || $age < 0) return [];

        return [
            'social_assistance' => AgePolicy::eligibleForSocialSupport($age) ? 1 : 0,
        ];
    }
}
