<?php

namespace App\Services;

use App\PolicyEngine\BusinessRuleCenter;

final class ExecutiveDashboardService
{
    public const VERSION = '1.0.0';

    private CitizenInsightEngine $citizenInsightEngine;
    private RiskWarningEngine $riskWarningEngine;
    private BusinessRuleCenter $businessRuleCenter;

    public function __construct(
        ?CitizenInsightEngine $citizenInsightEngine = null,
        ?RiskWarningEngine $riskWarningEngine = null,
        ?BusinessRuleCenter $businessRuleCenter = null,
    ) {
        $this->citizenInsightEngine = $citizenInsightEngine ?? new CitizenInsightEngine();
        $this->riskWarningEngine = $riskWarningEngine ?? new RiskWarningEngine();
        $this->businessRuleCenter = $businessRuleCenter ?? new BusinessRuleCenter();
    }

    public function summary(array $filters = []): array
    {
        $insight = $this->citizenInsightEngine->summary($filters);
        $warnings = $this->riskWarningEngine->warnings($filters + ['limitPerRule' => 8]);

        return [
            'engine' => [
                'name' => 'ExecutiveDashboardService',
                'version' => self::VERSION,
                'generatedAt' => date('c'),
                'sources' => [
                    'citizenInsightEngine' => $insight['engine'] ?? [],
                    'riskWarningEngine' => $warnings['engine'] ?? [],
                    'businessRuleCenter' => $this->businessRuleCenter->health(),
                ],
            ],
            'overview' => $this->overview($insight),
            'policy' => $this->policy($insight),
            'warnings' => $this->warnings($warnings),
            'insight' => $this->insight($insight),
            'trends' => $this->trends($insight),
            'kpi' => $this->kpi($insight, $warnings),
        ];
    }

    private function overview(array $insight): array
    {
        $population = $insight['population'] ?? [];
        $policy = $insight['policy'] ?? [];
        $labor = $insight['labor'] ?? [];
        $movements = $insight['movements']['events'] ?? [];

        return [
            'totalHouseholds' => $this->int($population, 'totalHouseholds'),
            'totalCitizens' => $this->int($population, 'totalCitizens'),
            'male' => $this->int($population, 'male'),
            'female' => $this->int($population, 'female'),
            'newHouseholds' => 0,
            'monthlyMovement' => array_sum(array_map('intval', $movements)),
            'elderly' => $this->int($policy, 'elderly'),
            'students' => $this->int($labor, 'pupil') + $this->int($labor, 'student'),
            'workingAge' => $this->int($labor, 'workingAge'),
        ];
    }

    private function policy(array $insight): array
    {
        $policy = $insight['policy'] ?? [];

        return [
            'eligibleHealthInsurance' => $this->int($policy, 'eligibleHealthInsuranceByPolicy'),
            'eligibleSocialSupport' => $this->int($policy, 'eligibleSocialSupport'),
            'missingHealthInsurance' => $this->int($policy, 'missingHealthInsurance'),
            'elderly' => $this->int($policy, 'elderly'),
            'disabled' => $this->int($policy, 'disabled'),
            'meritorious' => $this->int($policy, 'meritorious'),
            'partyMembers' => $this->int($policy, 'partyMembers'),
        ];
    }

    private function warnings(array $warnings): array
    {
        $items = $warnings['warnings'] ?? [];

        return [
            'summary' => $warnings['summary'] ?? ['total' => 0, 'byGroup' => [], 'bySeverity' => []],
            'items' => array_slice($items, 0, 12),
            'byGroup' => $this->groupWarnings($items, 'group'),
            'bySeverity' => $this->groupWarnings($items, 'severity'),
        ];
    }

    private function insight(array $insight): array
    {
        return [
            'populationStructure' => $insight['ageStructure']['bands'] ?? [],
            'averageAge' => $insight['ageStructure']['averageAge'] ?? null,
            'households' => $insight['households'] ?? [],
            'labor' => $insight['labor'] ?? [],
            'population' => $insight['population'] ?? [],
        ];
    }

    private function trends(array $insight): array
    {
        return [
            'period' => 'current',
            'movements' => $insight['movements']['events'] ?? [],
            'currentTemporaryStatus' => $insight['movements']['current'] ?? [],
        ];
    }

    private function kpi(array $insight, array $warnings): array
    {
        $population = $insight['population'] ?? [];
        $policy = $insight['policy'] ?? [];
        $labor = $insight['labor'] ?? [];
        $households = $insight['households'] ?? [];
        $totalCitizens = max(0, $this->int($population, 'totalCitizens'));
        $totalHouseholds = max(0, $this->int($population, 'totalHouseholds'));
        $students = $this->int($labor, 'pupil') + $this->int($labor, 'student');
        $missingInfoHouseholds = $this->int($households, 'missingInfoHouseholds');

        return [
            'healthInsuranceRate' => (float) ($policy['healthInsuranceCoveragePercent'] ?? 0),
            'studentRate' => $this->percent($students, $totalCitizens),
            'workingAgeRate' => $this->percent($this->int($labor, 'workingAge'), $totalCitizens),
            'elderlyRate' => $this->percent($this->int($policy, 'elderly'), $totalCitizens),
            'dataUpdateRate' => $totalHouseholds > 0 ? max(0, round(100 - (($missingInfoHouseholds / $totalHouseholds) * 100), 2)) : 100.0,
            'warningCount' => (int) ($warnings['summary']['total'] ?? 0),
        ];
    }

    private function groupWarnings(array $items, string $key): array
    {
        $result = [];
        foreach ($items as $item) {
            $group = (string) ($item[$key] ?? 'unknown');
            $result[$group] = ($result[$group] ?? 0) + 1;
        }
        ksort($result);
        return $result;
    }

    private function percent(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
    }

    private function int(array $row, string $key): int
    {
        return (int) ($row[$key] ?? 0);
    }
}
