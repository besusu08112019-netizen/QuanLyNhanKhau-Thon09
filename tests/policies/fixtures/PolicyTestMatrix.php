<?php

final class PolicyTestMatrix
{
    public const BASE_DATE = '2026-07-29';

    /** @return array<int, array{age: int, date_of_birth: string}> */
    public static function ages(): array
    {
        return [
            ['age' => 0, 'date_of_birth' => '2026-07-29'],
            ['age' => 5, 'date_of_birth' => '2021-07-29'],
            ['age' => 6, 'date_of_birth' => '2020-07-29'],
            ['age' => 17, 'date_of_birth' => '2009-07-29'],
            ['age' => 18, 'date_of_birth' => '2008-07-29'],
            ['age' => 59, 'date_of_birth' => '1967-07-29'],
            ['age' => 60, 'date_of_birth' => '1966-07-29'],
            ['age' => 69, 'date_of_birth' => '1957-07-29'],
            ['age' => 70, 'date_of_birth' => '1956-07-29'],
            ['age' => 74, 'date_of_birth' => '1952-07-29'],
            ['age' => 75, 'date_of_birth' => '1951-07-29'],
            ['age' => 90, 'date_of_birth' => '1936-07-29'],
        ];
    }
}
