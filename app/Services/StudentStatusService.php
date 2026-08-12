<?php

namespace App\Services;

use App\Policies\AgePolicy;

final class StudentStatusService
{
    public const ACADEMIC_YEAR_START_MONTH = AgePolicy::ACADEMIC_YEAR_START_MONTH;
    public const STUDENT_MAX_ACADEMIC_AGE = AgePolicy::STUDENT_MAX_ACADEMIC_AGE;
    public const STUDENT_LABEL = 'Há»c sinh';

    public static function academicYear(?\DateTimeInterface $date = null): int
    {
        return AgePolicy::academicYear($date);
    }

    public static function statusForDateOfBirth(?string $dateOfBirth, ?\DateTimeInterface $date = null): array
    {
        $academicYear = self::academicYear($date);
        $academicAge = AgePolicy::academicAgeForDateOfBirth($dateOfBirth, $date);

        return [
            'isStudent' => AgePolicy::isStudentAcademicAge($academicAge),
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
        return AgePolicy::studentConditionSql($alias);
    }

    public static function academicAgeSql(string $alias = 'c'): string
    {
        return AgePolicy::academicAgeSql($alias);
    }

    public static function academicYearSql(): string
    {
        return AgePolicy::academicYearSql();
    }

}
