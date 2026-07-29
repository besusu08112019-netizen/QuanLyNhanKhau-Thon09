<?php

namespace App\Services;

use App\Core\BaseModel;
use App\Models\PopulationStatistics;
use App\Policies\AgePolicy;
use App\Policies\HouseholdRelationPolicy;
use App\Policies\InsurancePolicy;
use App\PolicyEngine\BusinessRuleCenter;

final class CitizenInsightEngine extends BaseModel
{
    public const VERSION = '1.0.0';

    private PopulationStatistics $statistics;
    private BusinessRuleCenter $businessRuleCenter;

    public function __construct(?PopulationStatistics $statistics = null, ?BusinessRuleCenter $businessRuleCenter = null)
    {
        parent::__construct();
        $this->statistics = $statistics ?? new PopulationStatistics();
        $this->businessRuleCenter = $businessRuleCenter ?? new BusinessRuleCenter();
    }

    public function summary(array $filters = []): array
    {
        $metrics = $this->statistics->metrics($filters);

        return [
            'engine' => [
                'name' => 'CitizenInsightEngine',
                'version' => self::VERSION,
                'generatedAt' => date('c'),
                'policyCenter' => $this->businessRuleCenter->health(),
            ],
            'population' => $this->populationInsights($metrics),
            'ageStructure' => $this->ageStructureInsights($filters),
            'labor' => $this->laborInsights($metrics),
            'policy' => $this->policyInsights($metrics, $filters),
            'households' => $this->householdInsights($metrics, $filters),
            'movements' => $this->movementInsights(),
        ];
    }

    private function populationInsights(array $metrics): array
    {
        $totalCitizens = $this->int($metrics, 'total_citizens');
        $totalHouseholds = $this->int($metrics, 'total_households');
        $male = $this->int($metrics, 'male_count');
        $female = $this->int($metrics, 'female_count');

        return [
            'totalCitizens' => $totalCitizens,
            'totalHouseholds' => $totalHouseholds,
            'male' => $male,
            'female' => $female,
            'genderRatio' => $female > 0 ? round($male / $female, 2) : null,
            'averageHouseholdSize' => $totalHouseholds > 0 ? round($totalCitizens / $totalHouseholds, 2) : 0,
        ];
    }

    private function ageStructureInsights(array $filters): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $age = AgePolicy::ageSql('c');

        $row = $this->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN c.date_of_birth IS NOT NULL AND $age BETWEEN 0 AND " . AgePolicy::AGE_BAND_0_5_MAX . " THEN 1 ELSE 0 END),0) AS age_0_5,
                COALESCE(SUM(CASE WHEN c.date_of_birth IS NOT NULL AND $age BETWEEN " . AgePolicy::AGE_BAND_6_14_MIN . " AND " . AgePolicy::AGE_BAND_15_17_MAX . " THEN 1 ELSE 0 END),0) AS age_6_17,
                COALESCE(SUM(CASE WHEN c.date_of_birth IS NOT NULL AND $age BETWEEN " . AgePolicy::AGE_BAND_18_59_MIN . " AND " . AgePolicy::AGE_BAND_18_59_MAX . " THEN 1 ELSE 0 END),0) AS age_18_59,
                COALESCE(SUM(CASE WHEN c.date_of_birth IS NOT NULL AND $age BETWEEN " . AgePolicy::STATISTICAL_ELDERLY_MIN_AGE . " AND " . (AgePolicy::BHYT_DEFAULT_AGE - 1) . " THEN 1 ELSE 0 END),0) AS age_60_69,
                COALESCE(SUM(CASE WHEN c.date_of_birth IS NOT NULL AND $age BETWEEN " . AgePolicy::BHYT_DEFAULT_AGE . " AND " . (AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE - 1) . " THEN 1 ELSE 0 END),0) AS age_70_74,
                COALESCE(SUM(CASE WHEN c.date_of_birth IS NOT NULL AND $age >= " . AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE . " THEN 1 ELSE 0 END),0) AS age_75_plus,
                AVG(CASE WHEN c.date_of_birth IS NOT NULL THEN $age ELSE NULL END) AS average_age
             FROM citizens c
             INNER JOIN households h ON h.id = c.household_id
             $where",
            $params
        ) ?: [];

        return [
            'bands' => [
                '0_5' => $this->int($row, 'age_0_5'),
                '6_17' => $this->int($row, 'age_6_17'),
                '18_59' => $this->int($row, 'age_18_59'),
                '60_69' => $this->int($row, 'age_60_69'),
                '70_74' => $this->int($row, 'age_70_74'),
                '75_plus' => $this->int($row, 'age_75_plus'),
            ],
            'averageAge' => isset($row['average_age']) ? round((float) $row['average_age'], 2) : null,
        ];
    }

    private function laborInsights(array $metrics): array
    {
        $totalCitizens = $this->int($metrics, 'total_citizens');
        $workingAge = $this->int($metrics, 'working_age_count');

        return [
            'workingAge' => $workingAge,
            'outsideWorkingAge' => max(0, $totalCitizens - $workingAge),
            'employed' => $this->int($metrics, 'employed_count'),
            'unemployed' => $this->int($metrics, 'unemployed_count'),
            'pupil' => $this->int($metrics, 'pupil_count'),
            'student' => $this->int($metrics, 'student_count'),
            'retired' => $this->int($metrics, 'retired_count'),
        ];
    }

    private function policyInsights(array $metrics, array $filters): array
    {
        $hasHealthInsurance = $this->int($metrics, 'health_insurance_covered_count');
        $missingHealthInsurance = $this->int($metrics, 'health_insurance_missing_count');

        return [
            'hasHealthInsurance' => $hasHealthInsurance,
            'missingHealthInsurance' => $missingHealthInsurance,
            'healthInsuranceCoveragePercent' => (float) ($metrics['health_insurance_coverage_percent'] ?? 0),
            'eligibleHealthInsuranceByPolicy' => $this->eligibleHealthInsuranceByPolicy($filters),
            'eligibleSocialSupport' => $this->eligibleSocialSupport($filters),
            'elderly' => $this->int($metrics, 'elderly_count'),
            'disabled' => $this->int($metrics, 'disabled_person_count'),
            'meritorious' => $this->int($metrics, 'meritorious_person_count'),
            'partyMembers' => $this->int($metrics, 'party_member_count'),
            'poorHouseholds' => $this->int($metrics, 'poor_households'),
            'nearPoorHouseholds' => $this->int($metrics, 'near_poor_households'),
        ];
    }

    private function householdInsights(array $metrics, array $filters): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $age = AgePolicy::ageSql('c');
        $childCondition = AgePolicy::childConditionSql('c');
        $elderlyCondition = AgePolicy::statisticalElderlyConditionSql('c');
        $head = HouseholdRelationPolicy::HEAD;
        $citizenCondition = $this->statistics->citizenCondition('c');

        $row = $this->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN x.member_count = 1 THEN 1 ELSE 0 END),0) AS size_1,
                COALESCE(SUM(CASE WHEN x.member_count = 2 THEN 1 ELSE 0 END),0) AS size_2,
                COALESCE(SUM(CASE WHEN x.member_count = 3 THEN 1 ELSE 0 END),0) AS size_3,
                COALESCE(SUM(CASE WHEN x.member_count = 4 THEN 1 ELSE 0 END),0) AS size_4,
                COALESCE(SUM(CASE WHEN x.member_count >= 5 THEN 1 ELSE 0 END),0) AS size_5_plus,
                COALESCE(SUM(CASE WHEN x.member_count > 0 AND x.elderly_count = x.member_count THEN 1 ELSE 0 END),0) AS elderly_only_households,
                COALESCE(SUM(CASE WHEN x.child_count > 0 THEN 1 ELSE 0 END),0) AS households_with_children,
                COALESCE(SUM(CASE WHEN x.child_count > 0 AND x.elderly_count > 0 THEN 1 ELSE 0 END),0) AS multi_generation_households,
                COALESCE(SUM(CASE WHEN x.missing_member_info > 0 OR x.head_count = 0 THEN 1 ELSE 0 END),0) AS missing_info_households,
                COALESCE(SUM(CASE WHEN x.member_count = 0 OR x.head_count <> 1 OR x.missing_member_info > 0 THEN 1 ELSE 0 END),0) AS data_risk_households
             FROM (
                SELECT
                    h.id,
                    COUNT(c.id) AS member_count,
                    COALESCE(SUM(CASE WHEN c.id IS NOT NULL AND $childCondition THEN 1 ELSE 0 END),0) AS child_count,
                    COALESCE(SUM(CASE WHEN c.id IS NOT NULL AND $elderlyCondition THEN 1 ELSE 0 END),0) AS elderly_count,
                    COALESCE(SUM(CASE WHEN c.id IS NOT NULL AND c.relationship = :head_relation THEN 1 ELSE 0 END),0) AS head_count,
                    COALESCE(SUM(CASE WHEN c.id IS NOT NULL AND (c.date_of_birth IS NULL OR c.gender IS NULL OR c.gender = '' OR c.relationship IS NULL OR c.relationship = '') THEN 1 ELSE 0 END),0) AS missing_member_info,
                    MIN(CASE WHEN c.date_of_birth IS NOT NULL THEN $age ELSE NULL END) AS min_age,
                    MAX(CASE WHEN c.date_of_birth IS NOT NULL THEN $age ELSE NULL END) AS max_age
                 FROM households h
                 LEFT JOIN citizens c ON c.household_id = h.id AND $citizenCondition
                 $where
                 GROUP BY h.id
             ) x",
            $params + ['head_relation' => $head]
        ) ?: [];

        return [
            'averageHouseholdSize' => $this->int($metrics, 'total_households') > 0
                ? round($this->int($metrics, 'total_citizens') / $this->int($metrics, 'total_households'), 2)
                : 0,
            'sizeDistribution' => [
                '1' => $this->int($row, 'size_1'),
                '2' => $this->int($row, 'size_2'),
                '3' => $this->int($row, 'size_3'),
                '4' => $this->int($row, 'size_4'),
                '5_plus' => $this->int($row, 'size_5_plus'),
            ],
            'elderlyOnlyHouseholds' => $this->int($row, 'elderly_only_households'),
            'householdsWithChildren' => $this->int($row, 'households_with_children'),
            'multiGenerationHouseholds' => $this->int($row, 'multi_generation_households'),
            'missingInfoHouseholds' => $this->int($row, 'missing_info_households'),
            'dataRiskHouseholds' => $this->int($row, 'data_risk_households'),
        ];
    }

    private function movementInsights(): array
    {
        $current = $this->statistics->currentTemporaryStatusCounts();
        $events = [
            'birth' => 0,
            'death' => 0,
            'moveIn' => 0,
            'moveOut' => 0,
            'temporaryResidence' => $this->int($current, 'temporary_residence_count'),
            'temporaryAbsence' => $this->int($current, 'temporary_absence_count'),
        ];

        if (!$this->tableExists('movements')) {
            return ['events' => $events, 'current' => $current];
        }

        $where = ['m.status <> "DELETED"', $this->tenantWhere('m', 'movements')];
        $rows = $this->fetchAll(
            'SELECT m.type, COUNT(*) AS total FROM movements m WHERE ' . implode(' AND ', $where) . ' GROUP BY m.type',
            $this->withTenant()
        );

        $map = [
            'BIRTH' => 'birth',
            'DEATH' => 'death',
            'MOVE_IN' => 'moveIn',
            'MOVE_OUT' => 'moveOut',
            'TEMPORARY_RESIDENCE' => 'temporaryResidence',
            'TEMPORARY_ABSENCE' => 'temporaryAbsence',
        ];
        foreach ($rows as $row) {
            $type = strtoupper((string) ($row['type'] ?? ''));
            if (!isset($map[$type])) continue;
            $events[$map[$type]] = (int) ($row['total'] ?? 0);
        }

        return ['events' => $events, 'current' => $current];
    }

    private function eligibleHealthInsuranceByPolicy(array $filters): int
    {
        [$where, $params] = $this->citizenWhere($filters);
        $age = AgePolicy::ageSql('c');
        $student = StudentStatusService::studentSql('c');
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total
             FROM citizens c
             INNER JOIN households h ON h.id = c.household_id
             $where
             AND (($age >= " . InsurancePolicy::DEFAULT_AGE . ") OR ($student))",
            $params
        ) ?: [];
        return $this->int($row, 'total');
    }

    private function eligibleSocialSupport(array $filters): int
    {
        [$where, $params] = $this->citizenWhere($filters);
        $age = AgePolicy::ageSql('c');
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total
             FROM citizens c
             INNER JOIN households h ON h.id = c.household_id
             $where
             AND $age >= " . AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE,
            $params
        ) ?: [];
        return $this->int($row, 'total');
    }

    private function citizenWhere(array $filters): array
    {
        $where = [$this->statistics->citizenCondition('c'), $this->statistics->householdCondition('h')];
        $params = [];
        if (!empty($filters['dateFrom'])) {
            $where[] = 'DATE(c.created_at) >= :citizen_date_from';
            $params['citizen_date_from'] = (string) $filters['dateFrom'];
        }
        if (!empty($filters['dateTo'])) {
            $where[] = 'DATE(c.created_at) <= :citizen_date_to';
            $params['citizen_date_to'] = (string) $filters['dateTo'];
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function householdWhere(array $filters): array
    {
        $where = [$this->statistics->householdCondition('h')];
        $params = [];
        if (!empty($filters['dateFrom'])) {
            $where[] = 'DATE(h.created_at) >= :household_date_from';
            $params['household_date_from'] = (string) $filters['dateFrom'];
        }
        if (!empty($filters['dateTo'])) {
            $where[] = 'DATE(h.created_at) <= :household_date_to';
            $params['household_date_to'] = (string) $filters['dateTo'];
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }

    private function int(array $row, string $key): int
    {
        return (int) ($row[$key] ?? 0);
    }
}
