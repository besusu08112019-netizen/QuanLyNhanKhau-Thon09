<?php

namespace App\Policies;

final class AgePolicy
{
    public const CHILD_MAX_EXCLUSIVE_AGE = 16;
    public const CHILD_MAX_INCLUSIVE_AGE = self::CHILD_MAX_EXCLUSIVE_AGE - 1;
    public const STATISTICAL_ELDERLY_MIN_AGE = 60;
    public const WORKING_MIN_AGE = 16;
    public const WORKING_MAX_AGE = 59;
    public const AGE_BAND_0_5_MAX = 5;
    public const AGE_BAND_6_14_MIN = 6;
    public const AGE_BAND_6_14_MAX = 14;
    public const AGE_BAND_15_17_MIN = 15;
    public const AGE_BAND_15_17_MAX = 17;
    public const AGE_BAND_18_59_MIN = 18;
    public const AGE_BAND_18_59_MAX = 59;
    public const PARTY_MEMBER_AGE_UNDER_30_MAX_EXCLUSIVE = 30;
    public const PARTY_MEMBER_AGE_30_39_MAX_EXCLUSIVE = 40;
    public const PARTY_MEMBER_AGE_40_49_MAX_EXCLUSIVE = 50;
    public const PARTY_MEMBER_AGE_50_59_MAX_EXCLUSIVE = 60;
    public const PARTY_BADGE_MIN_YEARS = 30;
    public const PARTY_BADGE_INTERVAL_YEARS = 5;
    public const ACADEMIC_YEAR_START_MONTH = 8;
    public const STUDENT_MAX_ACADEMIC_AGE = 17;
    public const ELDERLY_OCCUPATION_DEFAULT_AGE = 70;
    public const BHYT_DEFAULT_AGE = 70;
    public const SOCIAL_ALLOWANCE_DEFAULT_AGE = 75;
    public const UPCOMING_POLICY_LOOKAHEAD_DAYS = 90;
    public const HEALTH_INSURANCE_EXPIRING_DAYS = 30;

    public static function ageFromDate(?string $dateOfBirth, ?\DateTimeInterface $date = null): ?int
    {
        $birth = \DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $dateOfBirth));
        if (!$birth) return null;
        $date ??= new \DateTimeImmutable('today');
        return $birth->diff($date)->y;
    }

    public static function getAge(?string $dateOfBirth, ?\DateTimeInterface $date = null): ?int
    {
        return self::ageFromDate($dateOfBirth, $date);
    }

    public static function getAgeGroup(?int $age): string
    {
        if ($age === null) return 'unknown';
        if ($age <= self::AGE_BAND_0_5_MAX) return '0_5';
        if ($age >= self::AGE_BAND_6_14_MIN && $age <= self::AGE_BAND_6_14_MAX) return '6_14';
        if ($age >= self::AGE_BAND_15_17_MIN && $age <= self::AGE_BAND_15_17_MAX) return '15_17';
        if ($age >= self::AGE_BAND_18_59_MIN && $age <= self::AGE_BAND_18_59_MAX) return '18_59';
        return '60_plus';
    }

    public static function ageSql(string $alias = 'c'): string
    {
        return "TIMESTAMPDIFF(YEAR,$alias.date_of_birth,CURDATE())";
    }

    public static function targetDateSql(string $alias, int $age): string
    {
        return "DATE_ADD($alias.date_of_birth, INTERVAL $age YEAR)";
    }

    public static function yearsSinceSql(string $dateExpression): string
    {
        return "TIMESTAMPDIFF(YEAR,$dateExpression,CURDATE())";
    }

    public static function childConditionSql(string $alias = 'c'): string
    {
        return self::ageSql($alias) . ' < ' . self::CHILD_MAX_EXCLUSIVE_AGE;
    }

    public static function statisticalElderlyConditionSql(string $alias = 'c'): string
    {
        return self::ageSql($alias) . ' >= ' . self::STATISTICAL_ELDERLY_MIN_AGE;
    }

    public static function workingAgeConditionSql(string $alias = 'c'): string
    {
        return self::ageSql($alias) . ' BETWEEN ' . self::WORKING_MIN_AGE . ' AND ' . self::WORKING_MAX_AGE;
    }

    public static function isChildAge(?int $age): bool
    {
        return $age !== null && $age < self::CHILD_MAX_EXCLUSIVE_AGE;
    }

    public static function isStatisticalElderlyAge(?int $age): bool
    {
        return $age !== null && $age >= self::STATISTICAL_ELDERLY_MIN_AGE;
    }

    public static function isWorkingAge(?int $age): bool
    {
        return $age !== null && $age >= self::WORKING_MIN_AGE && $age <= self::WORKING_MAX_AGE;
    }

    public static function hasDefaultHealthInsurance(?int $age): bool
    {
        return $age !== null && $age >= self::BHYT_DEFAULT_AGE;
    }

    public static function eligibleForSocialSupport(?int $age): bool
    {
        return $age !== null && $age >= self::SOCIAL_ALLOWANCE_DEFAULT_AGE;
    }

    public static function academicYear(?\DateTimeInterface $date = null): int
    {
        $date ??= new \DateTimeImmutable('today');
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        return $month >= self::ACADEMIC_YEAR_START_MONTH ? $year : $year - 1;
    }

    public static function academicYearSql(): string
    {
        return '(CASE WHEN MONTH(CURDATE()) >= ' . self::ACADEMIC_YEAR_START_MONTH . ' THEN YEAR(CURDATE()) ELSE YEAR(CURDATE()) - 1 END)';
    }

    public static function academicAgeSql(string $alias = 'c'): string
    {
        return '(' . self::academicYearSql() . ' - YEAR(' . $alias . '.date_of_birth))';
    }

    public static function studentConditionSql(string $alias = 'c'): string
    {
        return '(' . self::academicYearSql() . ' - YEAR(' . $alias . '.date_of_birth) <= ' . self::STUDENT_MAX_ACADEMIC_AGE . ')';
    }

    public static function academicAgeForDateOfBirth(?string $dateOfBirth, ?\DateTimeInterface $date = null): ?int
    {
        $birthYear = self::birthYear($dateOfBirth);
        return $birthYear === null ? null : self::academicYear($date) - $birthYear;
    }

    public static function isStudentAcademicAge(?int $academicAge): bool
    {
        return $academicAge !== null && $academicAge <= self::STUDENT_MAX_ACADEMIC_AGE;
    }

    public static function isStudent(?string $dateOfBirth, ?\DateTimeInterface $date = null): bool
    {
        return self::isStudentAcademicAge(self::academicAgeForDateOfBirth($dateOfBirth, $date));
    }

    public static function getPolicyAlerts(): array
    {
        return [
            'health_insurance_age' => self::BHYT_DEFAULT_AGE,
            'social_support_age' => self::SOCIAL_ALLOWANCE_DEFAULT_AGE,
            'lookahead_days' => self::UPCOMING_POLICY_LOOKAHEAD_DAYS,
        ];
    }

    private static function birthYear(?string $dateOfBirth): ?int
    {
        $raw = trim((string) $dateOfBirth);
        if (preg_match('/^(\d{4})-\d{2}-\d{2}$/', $raw, $m)) return (int) $m[1];
        if (preg_match('/^\d{4}$/', $raw)) return (int) $raw;
        return null;
    }
}
