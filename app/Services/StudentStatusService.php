<?php

namespace App\Services;

final class StudentStatusService
{
    public const ACADEMIC_YEAR_START_MONTH = 8;
    public const STUDENT_MAX_ACADEMIC_AGE = 17;
    public const STUDENT_LABEL = 'Học sinh';

    public static function academicYear(?\DateTimeInterface $date = null): int
    {
        $date ??= new \DateTimeImmutable('today');
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        return $month >= self::ACADEMIC_YEAR_START_MONTH ? $year : $year - 1;
    }

    public static function statusForDateOfBirth(?string $dateOfBirth, ?\DateTimeInterface $date = null): array
    {
        $birthYear = self::birthYear($dateOfBirth);
        $academicYear = self::academicYear($date);
        $academicAge = $birthYear === null ? null : $academicYear - $birthYear;

        return [
            'isStudent' => $academicAge !== null && $academicAge <= self::STUDENT_MAX_ACADEMIC_AGE,
            'academicYear' => $academicYear,
            'academicAge' => $academicAge,
        ];
    }

    public static function defaultFieldsForDateOfBirth(?string $dateOfBirth, ?\DateTimeInterface $date = null): array
    {
        $status = self::statusForDateOfBirth($dateOfBirth, $date);
        if (!$status['isStudent']) return [];

        return [
            'education' => self::STUDENT_LABEL,
            'occupation' => self::STUDENT_LABEL,
            'pupil' => 1,
            'student' => 0,
            'not_attending_school' => 0,
            'employed' => 0,
        ];
    }

    public static function studentSql(string $alias = 'c'): string
    {
        $academicYear = self::academicYearSql();
        return "($academicYear - YEAR($alias.date_of_birth) <= " . self::STUDENT_MAX_ACADEMIC_AGE . ")";
    }

    public static function academicAgeSql(string $alias = 'c'): string
    {
        return '(' . self::academicYearSql() . ' - YEAR(' . $alias . '.date_of_birth))';
    }

    public static function academicYearSql(): string
    {
        return '(CASE WHEN MONTH(CURDATE()) >= ' . self::ACADEMIC_YEAR_START_MONTH . ' THEN YEAR(CURDATE()) ELSE YEAR(CURDATE()) - 1 END)';
    }

    private static function birthYear(?string $dateOfBirth): ?int
    {
        $raw = trim((string) $dateOfBirth);
        if (preg_match('/^(\d{4})-\d{2}-\d{2}$/', $raw, $m)) return (int) $m[1];
        if (preg_match('/^\d{4}$/', $raw)) return (int) $raw;
        return null;
    }
}
