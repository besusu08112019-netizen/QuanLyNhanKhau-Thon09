<?php

namespace App\Policies;

use App\Services\StudentStatusService;

final class InsurancePolicy
{
    public const STUDENT_OCCUPATION = StudentStatusService::STUDENT_LABEL;
    public const ELDERLY_OCCUPATION = 'Người cao tuổi (' . AgePolicy::ELDERLY_OCCUPATION_DEFAULT_AGE . '+)';
    public const DEFAULT_AGE = AgePolicy::BHYT_DEFAULT_AGE;
    public const EXPIRING_DAYS = AgePolicy::HEALTH_INSURANCE_EXPIRING_DAYS;

    public static function defaultForLaborOccupation(?string $occupation): ?int
    {
        $key = self::normalize($occupation);
        if ($key === '') return null;

        return in_array($key, self::eligibleOccupationKeys(), true) ? 1 : null;
    }

    public static function eligibleOccupations(): array
    {
        return [self::STUDENT_OCCUPATION, self::ELDERLY_OCCUPATION];
    }

    public static function defaultOccupationForDateOfBirth(?string $dateOfBirth, ?\DateTimeInterface $date = null): ?string
    {
        $student = StudentStatusService::defaultFieldsForDateOfBirth($dateOfBirth, $date);
        if (!empty($student['occupation'])) return $student['occupation'];

        $age = AgePolicy::ageFromDate($dateOfBirth, $date);
        return self::hasDefaultHealthInsuranceForAge($age) ? self::ELDERLY_OCCUPATION : null;
    }

    public static function hasDefaultHealthInsuranceForAge(?int $age): bool
    {
        return AgePolicy::hasDefaultHealthInsurance($age);
    }

    public static function hasDefaultHealthInsuranceForDateOfBirth(?string $dateOfBirth, ?\DateTimeInterface $date = null): bool
    {
        return AgePolicy::isStudent($dateOfBirth, $date)
            || self::hasDefaultHealthInsuranceForAge(AgePolicy::ageFromDate($dateOfBirth, $date));
    }

    public static function defaultEligibilitySql(string $alias = 'c'): string
    {
        return '(' . AgePolicy::ageSql($alias) . ' >= ' . self::DEFAULT_AGE . ' OR ' . StudentStatusService::studentSql($alias) . ')';
    }

    public static function eligibleOccupationKeys(): array
    {
        return [
            'hoc sinh',
            'nguoi cao tuoi ' . AgePolicy::ELDERLY_OCCUPATION_DEFAULT_AGE,
            'nguoi cao tuoi ' . AgePolicy::ELDERLY_OCCUPATION_DEFAULT_AGE . '+',
            'elderly ' . AgePolicy::ELDERLY_OCCUPATION_DEFAULT_AGE,
        ];
    }

    public static function enrolledConditionSql(string $alias = 'c', bool $hasColumn = true): string
    {
        return $hasColumn ? 'COALESCE(' . $alias . '.has_health_insurance,0)=1' : '0=1';
    }

    public static function missingConditionSql(string $alias = 'c', bool $hasColumn = true): string
    {
        return $hasColumn ? 'COALESCE(' . $alias . '.has_health_insurance,0)=0' : '1=1';
    }

    public static function effectiveConditionSql(string $alias = 'c', bool $hasColumn = true, bool $endDateColumn = true): string
    {
        $enrolled = self::enrolledConditionSql($alias, $hasColumn);
        return $endDateColumn
            ? '(' . $enrolled . ' AND (' . $alias . '.health_insurance_end_date IS NULL OR ' . $alias . '.health_insurance_end_date >= CURDATE()))'
            : $enrolled;
    }

    public static function expiredConditionSql(string $alias = 'c', bool $hasColumn = true, bool $endDateColumn = true): string
    {
        if (!$hasColumn || !$endDateColumn) return '0=1';
        return self::enrolledConditionSql($alias, true) . ' AND ' . $alias . '.health_insurance_end_date IS NOT NULL AND ' . $alias . '.health_insurance_end_date < CURDATE()';
    }

    public static function expiringConditionSql(string $alias = 'c', bool $hasColumn = true, bool $endDateColumn = true): string
    {
        if (!$hasColumn || !$endDateColumn) return '0=1';
        return self::enrolledConditionSql($alias, true) . ' AND ' . $alias . '.health_insurance_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . self::EXPIRING_DAYS . ' DAY)';
    }

    public static function normalize(?string $value): string
    {
        $text = mb_strtolower(trim((string) $value), 'UTF-8');
        if ($text === '') return '';
        $text = self::removeVietnameseMarks($text);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) $text = $converted;
        return trim(preg_replace('/[^a-z0-9+]+/', ' ', $text) ?? $text);
    }

    private static function removeVietnameseMarks(string $value): string
    {
        $groups = [
            'a' => '&#224;&#225;&#7843;&#227;&#7841;&#259;&#7857;&#7855;&#7859;&#7861;&#7863;&#226;&#7847;&#7845;&#7849;&#7851;&#7853;',
            'd' => '&#273;',
            'e' => '&#232;&#233;&#7867;&#7869;&#7865;&#234;&#7873;&#7871;&#7875;&#7877;&#7879;',
            'i' => '&#236;&#237;&#7881;&#297;&#7883;',
            'o' => '&#242;&#243;&#7887;&#245;&#7885;&#244;&#7891;&#7889;&#7893;&#7895;&#7897;&#417;&#7901;&#7899;&#7903;&#7905;&#7907;',
            'u' => '&#249;&#250;&#7911;&#361;&#7909;&#432;&#7915;&#7913;&#7917;&#7919;&#7921;',
            'y' => '&#7923;&#253;&#7927;&#7929;&#7925;',
        ];

        foreach ($groups as $ascii => $entities) {
            $chars = preg_split('//u', html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($chars) $value = str_replace($chars, $ascii, $value);
        }

        return $value;
    }
}
