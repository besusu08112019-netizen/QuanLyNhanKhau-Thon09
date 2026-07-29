<?php

namespace App\Services;

use App\Policies\InsurancePolicy;

final class HealthInsuranceDefaultService
{
    public const STUDENT_OCCUPATION = InsurancePolicy::STUDENT_OCCUPATION;
    public const ELDERLY_OCCUPATION = InsurancePolicy::ELDERLY_OCCUPATION;

    public static function defaultForLaborOccupation(?string $occupation): ?int
    {
        return InsurancePolicy::defaultForLaborOccupation($occupation);
    }

    public static function eligibleOccupations(): array
    {
        return InsurancePolicy::eligibleOccupations();
    }

    public static function defaultOccupationForDateOfBirth(?string $dateOfBirth, ?\DateTimeInterface $date = null): ?string
    {
        return InsurancePolicy::defaultOccupationForDateOfBirth($dateOfBirth, $date);
    }

    public static function eligibleOccupationKeys(): array
    {
        return InsurancePolicy::eligibleOccupationKeys();
    }

    public static function normalize(?string $value): string
    {
        return InsurancePolicy::normalize($value);
    }
}
