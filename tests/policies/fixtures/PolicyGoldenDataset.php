<?php

final class PolicyGoldenDataset
{
    /**
     * Golden citizens are shared by every policy sprint. A future policy may add
     * expected fields, but existing expectations should only change deliberately.
     *
     * @return array<int, array{citizen: array<string, mixed>, expected: array<string, mixed>}>
     */
    public static function citizens(): array
    {
        return [
            [
                'citizen' => PolicyFixtures::createCitizen([
                    'citizen_code' => 'GOLDEN_AGE_00',
                    'date_of_birth' => '2026-07-29',
                ]),
                'expected' => [
                    'age' => 0,
                    'age_group' => '0_5',
                    'is_child' => true,
                    'is_working_age' => false,
                    'is_statistical_elderly' => false,
                    'has_default_health_insurance' => false,
                    'eligible_for_social_support' => false,
                ],
            ],
            [
                'citizen' => PolicyFixtures::createStudent([
                    'citizen_code' => 'GOLDEN_STUDENT_17',
                    'date_of_birth' => '2009-01-01',
                ]),
                'expected' => [
                    'age' => 17,
                    'age_group' => '15_17',
                    'is_student' => true,
                    'is_working_age' => true,
                ],
            ],
            [
                'citizen' => PolicyFixtures::createCitizen([
                    'citizen_code' => 'GOLDEN_WORKING_59',
                    'date_of_birth' => '1967-07-29',
                ]),
                'expected' => [
                    'age' => 59,
                    'age_group' => '18_59',
                    'is_working_age' => true,
                    'is_statistical_elderly' => false,
                ],
            ],
            [
                'citizen' => PolicyFixtures::createSenior([
                    'citizen_code' => 'GOLDEN_SENIOR_70',
                    'date_of_birth' => '1956-07-29',
                ]),
                'expected' => [
                    'age' => 70,
                    'age_group' => '60_plus',
                    'is_statistical_elderly' => true,
                    'has_default_health_insurance' => true,
                    'eligible_for_social_support' => false,
                ],
            ],
            [
                'citizen' => PolicyFixtures::createSenior([
                    'citizen_code' => 'GOLDEN_SUPPORT_75',
                    'date_of_birth' => '1951-07-29',
                    'social_assistance' => 1,
                ]),
                'expected' => [
                    'age' => 75,
                    'age_group' => '60_plus',
                    'is_statistical_elderly' => true,
                    'has_default_health_insurance' => true,
                    'eligible_for_social_support' => true,
                ],
            ],
        ];
    }
}
