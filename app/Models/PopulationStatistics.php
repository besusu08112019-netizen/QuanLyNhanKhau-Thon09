<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Policies\AgePolicy;
use App\Policies\InsurancePolicy;
use App\Services\HouseholdCategoryService;
use App\Services\StudentStatusService;

final class PopulationStatistics extends BaseModel
{
    private const CITIZEN_FLAG_COLUMNS = [
        'has_health_insurance',
        'party_member',
        'youth_union_member',
        'women_union_member',
        'farmers_union_member',
        'veterans_union_member',
        'elderly_union_member',
        'meritorious_person',
        'martyr_relative',
        'wounded_soldier',
        'sick_soldier',
        'chemical_warfare_victim',
        'imprisoned_resistance_activist',
        'youth_volunteer',
        'resistance_hero',
        'revolutionary_activist',
        'disabled_person',
        'social_assistance',
        'employed',
        'unemployed',
        'freelance_labor',
        'out_province_labor',
        'foreign_labor',
        'not_attending_school',
        'pupil',
        'student',
        'retired',
    ];

    private const MERITORIOUS_POLICY_COLUMNS = [
        'martyr_relative',
        'wounded_soldier',
        'sick_soldier',
        'chemical_warfare_victim',
        'imprisoned_resistance_activist',
        'youth_volunteer',
        'resistance_hero',
        'revolutionary_activist',
    ];

    public function currentHouseholdCondition(string $alias = 'h'): string
    {
        $conditions = [$this->notDeletedCondition('households', $alias)];
        if ($this->columnExists('households', 'status')) {
            $conditions[] = $alias . ".status NOT IN ('ENDED','MERGED','TRANSFERRED_OUT','MOVED_OUT','INACTIVE')";
        }
        return implode(' AND ', $conditions);
    }

    public function householdCondition(string $alias = 'h'): string
    {
        return $this->currentHouseholdCondition($alias);
    }

    public function historicalHouseholdCondition(string $alias = 'h'): string
    {
        return $this->notDeletedCondition('households', $alias);
    }

    public function currentCitizenCondition(string $alias = 'c'): string
    {
        $conditions = [$this->notDeletedCondition('citizens', $alias)];
        if ($this->columnExists('citizens', 'life_status')) {
            $conditions[] = "COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED'";
        }
        if ($this->columnExists('citizens', 'residency_status')) {
            $conditions[] = "COALESCE(" . $alias . ".residency_status,'PERMANENT') <> 'TRANSFERRED_OUT'";
        }
        if ($this->columnExists('citizens', 'presence_status')) {
            $conditions[] = "COALESCE(" . $alias . ".presence_status,'AT_HOME') <> 'MOVED_OUT'";
        }
        return implode(' AND ', $conditions);
    }

    public function citizenCondition(string $alias = 'c'): string
    {
        return $this->currentCitizenCondition($alias);
    }

    public function historicalCitizenCondition(string $alias = 'c'): string
    {
        return $this->notDeletedCondition('citizens', $alias);
    }
    public function temporaryAbsenceCitizenCondition(string $alias = 'c'): string
    {
        $conditions = [$this->notDeletedCondition('citizens', $alias)];
        if ($this->columnExists('citizens', 'life_status')) {
            $conditions[] = "COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED'";
        }
        if ($this->columnExists('citizens', 'residency_status')) {
            $conditions[] = "COALESCE(" . $alias . ".residency_status,'PERMANENT') <> 'TRANSFERRED_OUT'";
        }
        if ($this->columnExists('citizens', 'presence_status')) {
            $conditions[] = "COALESCE(" . $alias . ".presence_status,'AT_HOME') <> 'MOVED_OUT'";
        }
        $conditions[] = $alias . ".presence_status = 'AWAY'";
        return implode(' AND ', $conditions);
    }

    public function temporaryResidenceCitizenCondition(string $alias = 'c'): string
    {
        $conditions = [$this->notDeletedCondition('citizens', $alias)];
        if ($this->columnExists('citizens', 'life_status')) {
            $conditions[] = "COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED'";
        }
        if ($this->columnExists('citizens', 'residency_status')) {
            $conditions[] = $alias . ".residency_status = 'TEMPORARY'";
        } else {
            $conditions[] = '0=1';
        }
        return implode(' AND ', $conditions);
    }

    public function temporaryAbsenceHouseholdCondition(string $alias = 'h'): string
    {
        return $this->notDeletedCondition('households', $alias);
    }

    public function currentTemporaryStatusCounts(): array
    {
        $temporaryResidence = $this->currentTemporaryResidenceCount();
        $temporaryAbsence = $this->currentTemporaryAbsenceCount();
        return [
            'temporary_residence_count' => $temporaryResidence,
            'temporary_absence_count' => $temporaryAbsence,
            'temporary_count' => $temporaryResidence,
            'away_count' => $temporaryAbsence,
        ];
    }

    public function counts(): array
    {
        $householdWhere = $this->householdCondition('h');
        $citizenWhere = $this->citizenCondition('c') . ' AND ' . $this->householdCondition('h');

        $households = $this->fetchOne("SELECT COUNT(*) AS total FROM households h WHERE $householdWhere") ?: [];
        $citizens = $this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE $citizenWhere") ?: [];

        return [
            'total_households' => (int) ($households['total'] ?? 0),
            'total_citizens' => (int) ($citizens['total'] ?? 0),
        ];
    }

    public function metrics(array $filters = []): array
    {
        [$householdWhere, $householdParams] = $this->householdWhere($filters);
        [$citizenWhere, $citizenParams] = $this->citizenWhere($filters);

        $meritoriousHouseholdExpr = $this->meritoriousHouseholdExists('h');
        $disabledHouseholdExpr = $this->disabledHouseholdExists('h');
        $households = $this->fetchOne("SELECT COUNT(*) AS total_households, COALESCE(SUM(CASE WHEN h.poor_household=1 THEN 1 ELSE 0 END),0) AS poor_households, COALESCE(SUM(CASE WHEN h.near_poor_household=1 THEN 1 ELSE 0 END),0) AS near_poor_households, COALESCE(SUM(CASE WHEN $meritoriousHouseholdExpr THEN 1 ELSE 0 END),0) AS meritorious_households, COALESCE(SUM(CASE WHEN $disabledHouseholdExpr THEN 1 ELSE 0 END),0) AS disabled_households, COALESCE(SUM(CASE WHEN h.note LIKE '%H? ch?nh s?ch%' OR h.note LIKE '%ch?nh s?ch%' THEN 1 ELSE 0 END),0) AS policy_households, COALESCE(SUM(CASE WHEN h.poor_household=0 AND h.near_poor_household=0 AND NOT $meritoriousHouseholdExpr AND NOT $disabledHouseholdExpr THEN 1 ELSE 0 END),0) AS normal_households FROM households h $householdWhere", $householdParams) ?: [];
        $householdCategories = $this->householdCategoryCounts($filters);

        $citizens = $this->fetchOne("SELECT COUNT(*) AS total_citizens, COALESCE(SUM(CASE WHEN c.gender='Nam' THEN 1 ELSE 0 END),0) AS male_count, COALESCE(SUM(CASE WHEN c.gender='Nữ' THEN 1 ELSE 0 END),0) AS female_count, COALESCE(SUM(CASE WHEN c.gender='Khác' THEN 1 ELSE 0 END),0) AS gender_other_count, COALESCE(SUM(CASE WHEN c.gender IS NULL OR c.gender='' OR c.gender NOT IN ('Nam','Nữ','Khác') THEN 1 ELSE 0 END),0) AS gender_unknown_count, COALESCE(SUM(CASE WHEN c.relationship='Chủ hộ' THEN 1 ELSE 0 END),0) AS household_head_count, COALESCE(SUM(CASE WHEN c.life_status='ALIVE' THEN 1 ELSE 0 END),0) AS active_citizens, COALESCE(SUM(CASE WHEN c.residency_status='TEMPORARY' THEN 1 ELSE 0 END),0) AS temporary_residence_count, COALESCE(SUM(CASE WHEN c.presence_status='AWAY' THEN 1 ELSE 0 END),0) AS temporary_absence_count, COALESCE(SUM(CASE WHEN " . AgePolicy::childConditionSql('c') . " THEN 1 ELSE 0 END),0) AS children_count, COALESCE(SUM(CASE WHEN " . AgePolicy::statisticalElderlyConditionSql('c') . " THEN 1 ELSE 0 END),0) AS elderly_count, COALESCE(SUM(CASE WHEN " . AgePolicy::workingAgeConditionSql('c') . " THEN 1 ELSE 0 END),0) AS working_age_count" . $this->flagSelects('c') . " FROM citizens c INNER JOIN households h ON h.id = c.household_id $citizenWhere", $citizenParams) ?: [];

        $totalCitizens = max(1, (int) ($citizens['total_citizens'] ?? 0));
        $totalHouseholds = max(1, (int) ($households['total_households'] ?? 0));
        $temporaryStatusCounts = $this->currentTemporaryStatusCounts();
        $temporaryResidence = $temporaryStatusCounts['temporary_residence_count'];
        $temporaryAbsence = $temporaryStatusCounts['temporary_absence_count'];
        $householdReview = $this->householdReviewCounts($filters);
        $residenceCounts = $this->householdResidenceCounts($filters);

        $metrics = [
            'total_households' => (int) ($households['total_households'] ?? 0),
            'total_citizens' => (int) ($citizens['total_citizens'] ?? 0),
            'male_count' => (int) ($citizens['male_count'] ?? 0),
            'female_count' => (int) ($citizens['female_count'] ?? 0),
            'gender_other_count' => (int) ($citizens['gender_other_count'] ?? 0),
            'gender_unknown_count' => (int) ($citizens['gender_unknown_count'] ?? 0),
            'household_head_count' => (int) ($citizens['household_head_count'] ?? 0),
            'active_citizens' => (int) ($citizens['active_citizens'] ?? 0),
            'children_count' => (int) ($citizens['children_count'] ?? 0),
            'elderly_count' => (int) ($citizens['elderly_count'] ?? 0),
            'working_age_count' => (int) ($citizens['working_age_count'] ?? 0),
            'temporary_residence_count' => $temporaryResidence,
            'temporary_absence_count' => $temporaryAbsence,
            'temporary_count' => $temporaryResidence,
            'away_count' => $temporaryAbsence,
            'poor_households' => $householdCategories['poor_households'],
            'near_poor_households' => $householdCategories['near_poor_households'],
            'medium_households' => $householdCategories['medium_households'],
            'policy_households' => (int) ($households['policy_households'] ?? 0),
            'meritorious_households' => (int) ($households['meritorious_households'] ?? 0),
            'disabled_households' => (int) ($households['disabled_households'] ?? 0),
            'normal_households' => (int) ($households['normal_households'] ?? 0),
            'resident_households' => $residenceCounts['resident'],
            'away_for_work_households' => $residenceCounts['away_for_work'],
            'settled_elsewhere_households' => $residenceCounts['settled_elsewhere'],
            'outside_households' => $residenceCounts['settled_elsewhere'],
            'partial_households' => $residenceCounts['partial'],
            'inactive_residence_households' => $residenceCounts['inactive'],
            'actual_resident_households' => $residenceCounts['actual_resident'],
            'households_with_present_count' => $householdReview['with_present'],
            'household_review_count' => $householdReview['needs_status_review'],
            'household_needs_review_count' => $householdReview['needs_status_review'],
            'household_head_review_count' => $householdReview['needs_head_review'],
            'household_needs_head_review_count' => $householdReview['needs_head_review'],
        ];

        foreach (self::CITIZEN_FLAG_COLUMNS as $key) {
            $metrics[$key . '_count'] = (int) ($citizens[$key] ?? 0);
            $metrics[$key . '_percent'] = round($metrics[$key . '_count'] * 100 / $totalCitizens, 2);
        }

        $metrics['poor_households_percent'] = round($metrics['poor_households'] * 100 / $totalHouseholds, 2);
        $metrics['near_poor_households_percent'] = round($metrics['near_poor_households'] * 100 / $totalHouseholds, 2);
        $metrics['medium_households_percent'] = round($metrics['medium_households'] * 100 / $totalHouseholds, 2);
        $metrics['children_percent'] = round($metrics['children_count'] * 100 / $totalCitizens, 2);
        $metrics['elderly_percent'] = round($metrics['elderly_count'] * 100 / $totalCitizens, 2);
        $metrics['working_age_percent'] = round($metrics['working_age_count'] * 100 / $totalCitizens, 2);

        $healthInsurance = $this->healthInsuranceStats($filters);
        $metrics['health_insurance_total'] = $healthInsurance['total'];
        $metrics['health_insurance_count'] = $healthInsurance['insured'];
        $metrics['health_insurance_covered_count'] = $healthInsurance['insured'];
        $metrics['health_insurance_missing_count'] = $healthInsurance['uninsured'];
        $metrics['health_insurance_uninsured_count'] = $healthInsurance['uninsured'];
        $metrics['health_insurance_coverage_percent'] = $healthInsurance['coverage_percent'];
        $metrics['health_insurance_percent'] = $healthInsurance['coverage_percent'];

        $age70HealthInsurance = $this->age70HealthInsuranceStats($filters);
        $metrics['age_70_plus_count'] = $age70HealthInsurance['total'];
        $metrics['age_70_plus_health_insurance_count'] = $age70HealthInsurance['insured'];
        $metrics['age_70_plus_without_health_insurance_count'] = $age70HealthInsurance['uninsured'];
        $metrics['elderly_health_insurance_count'] = $age70HealthInsurance['insured'];
        $metrics['elderly_health_insurance_missing_count'] = $age70HealthInsurance['uninsured'];
        $metrics['age_70_plus_health_insurance_percent'] = $age70HealthInsurance['coverage_percent'];

        $age75SocialAssistance = $this->age75SocialAssistanceStats($filters);
        $metrics['age_75_plus_count'] = $age75SocialAssistance['total'];
        $metrics['age_75_plus_social_assistance_count'] = $age75SocialAssistance['assisted'];
        $metrics['age_75_plus_without_social_assistance_record_count'] = $age75SocialAssistance['without_record'];
        $metrics['age_75_plus_legacy_social_assistance_count'] = $age75SocialAssistance['legacy_flagged'];
        $metrics['elderly_social_assistance_count'] = $age75SocialAssistance['assisted'];
        $metrics['elderly_social_assistance_review_count'] = $age75SocialAssistance['without_record'];

        return $metrics;
    }

    public function healthInsuranceStats(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $hasColumn = $this->columnExists('citizens', 'has_health_insurance');
        $endColumn = $this->columnExists('citizens', 'health_insurance_end_date');
        $hasExpr = InsurancePolicy::enrolledConditionSql('c', $hasColumn);
        $effectiveExpr = InsurancePolicy::effectiveConditionSql('c', $hasColumn, $endColumn);
        $row = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN $hasExpr THEN 1 ELSE 0 END),0) AS enrolled, COALESCE(SUM(CASE WHEN $effectiveExpr THEN 1 ELSE 0 END),0) AS effective FROM citizens c INNER JOIN households h ON h.id = c.household_id $where", $params) ?: [];
        $total = (int) ($row['total'] ?? 0);
        $enrolled = (int) ($row['enrolled'] ?? 0);
        $effective = (int) ($row['effective'] ?? 0);
        return [
            'total' => $total,
            'insured' => $effective,
            'enrolled' => $enrolled,
            'effective' => $effective,
            'uninsured' => max(0, $total - $enrolled),
            'coverage_percent' => $total > 0 ? round($effective * 100 / $total, 2) : 0,
        ];
    }
    public function age70HealthInsuranceStats(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $hasColumn = $this->columnExists('citizens', 'has_health_insurance');
        $endColumn = $this->columnExists('citizens', 'health_insurance_end_date');
        $ageExpr = AgePolicy::ageSql('c') . ' >= ' . AgePolicy::BHYT_DEFAULT_AGE;
        $effectiveExpr = InsurancePolicy::effectiveConditionSql('c', $hasColumn, $endColumn);
        $row = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN $effectiveExpr THEN 1 ELSE 0 END),0) AS insured FROM citizens c INNER JOIN households h ON h.id = c.household_id $where AND $ageExpr", $params) ?: [];
        $total = (int) ($row['total'] ?? 0);
        $insured = (int) ($row['insured'] ?? 0);
        return [
            'total' => $total,
            'insured' => $insured,
            'uninsured' => max(0, $total - $insured),
            'coverage_percent' => $total > 0 ? round($insured * 100 / $total, 2) : 0,
        ];
    }

    private function householdCategoryCounts(array $filters): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $select = (new HouseholdCategoryService())->countsSelect('h');
        $row = $this->fetchOne("SELECT $select FROM households h $where", $params) ?: [];
        return [
            'poor_households' => (int) ($row['poor_households'] ?? 0),
            'near_poor_households' => (int) ($row['near_poor_households'] ?? 0),
            'medium_households' => (int) ($row['medium_households'] ?? 0),
        ];
    }

    public function age75SocialAssistanceStats(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $ageExpr = AgePolicy::ageSql('c') . ' >= ' . AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE;
        $activeStatusExpr = $this->columnExists('citizens', 'status') ? ' AND COALESCE(c.status,"ACTIVE") <> "INACTIVE"' : '';
        $policyRecordExpr = $this->socialAssistanceRecordExists('c');
        $reviewReceivingExpr = $this->btxhReviewReceivingExists('c');
        $reviewCompletedExpr = $this->btxhReviewCompletedExists('c');
        $legacyExpr = $this->columnExists('citizens', 'social_assistance') ? 'COALESCE(c.social_assistance,0)=1' : '0=1';
        $receivingExpr = "(($policyRecordExpr) OR ($reviewReceivingExpr))";
        $row = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN $receivingExpr THEN 1 ELSE 0 END),0) AS assisted, COALESCE(SUM(CASE WHEN $legacyExpr THEN 1 ELSE 0 END),0) AS legacy_flagged, COALESCE(SUM(CASE WHEN NOT ($reviewCompletedExpr) THEN 1 ELSE 0 END),0) AS needs_review FROM citizens c INNER JOIN households h ON h.id = c.household_id $where AND $ageExpr$activeStatusExpr", $params) ?: [];
        $total = (int) ($row['total'] ?? 0);
        $assisted = (int) ($row['assisted'] ?? 0);
        return [
            'total' => $total,
            'assisted' => $assisted,
            'without_record' => (int) ($row['needs_review'] ?? 0),
            'legacy_flagged' => (int) ($row['legacy_flagged'] ?? 0),
        ];
    }


    private function btxhReviewReceivingExists(string $alias): string
    {
        if (!$this->tableExists('policy_alert_reviews') || !$this->columnExists('policy_alert_reviews', 'result_status')) return '0=1';
        return 'EXISTS (SELECT 1 FROM policy_alert_reviews par WHERE par.citizen_id = ' . $alias . '.id AND par.alert_key="age_75_social_assistance_legacy_missing_record" AND par.result_status="RECEIVING_SOCIAL_ASSISTANCE" AND ' . $this->tenantLiteral('policy_alert_reviews', 'par') . ')';
    }

    private function btxhReviewCompletedExists(string $alias): string
    {
        if (!$this->tableExists('policy_alert_reviews')) return '0=1';
        if (!$this->columnExists('policy_alert_reviews', 'result_status')) return '0=1';
        return 'EXISTS (SELECT 1 FROM policy_alert_reviews par WHERE par.citizen_id = ' . $alias . '.id AND par.alert_key="age_75_social_assistance_legacy_missing_record" AND par.result_status IN ("RECEIVING_SOCIAL_ASSISTANCE","ELIGIBLE_NOT_RECEIVING","NOT_ELIGIBLE") AND ' . $this->tenantLiteral('policy_alert_reviews', 'par') . ')';
    }

    private function notDeletedCondition(string $table, string $alias): string
    {
        $conditions = [];
        if ($this->tenantColumnExists($table)) {
            $conditions[] = $alias . '.village_id = ' . $this->tenantId();
        }
        if ($this->columnExists($table, 'status')) {
            $conditions[] = '(' . $alias . ".status IS NULL OR " . $alias . ".status <> 'DELETED')";
        }
        if ($this->columnExists($table, 'deleted_at')) {
            $conditions[] = $alias . '.deleted_at IS NULL';
        }
        return $conditions ? implode(' AND ', $conditions) : '1=1';
    }

    private function householdWhere(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $where = [$this->householdCondition('h')];
        $params = [];
        if ($filters['householdStatus']) {
            $status = $this->residenceStatus($filters['householdStatus']);
            if ($status !== null) {
                $where[] = $this->residenceStatusSql('h') . ' = :household_status';
                $params['household_status'] = $status;
            } else {
                $where[] = 'h.status = :household_status';
                $params['household_status'] = $filters['householdStatus'];
            }
        }
        if ($filters['residenceStatus']) {
            $where[] = $this->residenceStatusSql('h') . ' = :residence_status';
            $params['residence_status'] = $filters['residenceStatus'];
        }
        if ($filters['dateFrom']) {
            $where[] = 'DATE(h.created_at) >= :household_date_from';
            $params['household_date_from'] = $filters['dateFrom'];
        }
        if ($filters['dateTo']) {
            $where[] = 'DATE(h.created_at) <= :household_date_to';
            $params['household_date_to'] = $filters['dateTo'];
        }
        $category = $this->categoryKey($filters['householdType']);
        if ($category) $this->addCategoryWhere($where, $params, $category);
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function citizenWhere(array $filters): array
    {
        $rawFilters = $filters;
        $filters = $this->normalizeFilters($filters);
        $isMovedOutFilter = $filters['presenceStatus'] === 'MOVED_OUT' || $filters['residencyStatus'] === 'TRANSFERRED_OUT';
        $where = $isMovedOutFilter
            ? [$this->historicalCitizenCondition('c'), $this->historicalHouseholdCondition('h')]
            : [$this->citizenCondition('c'), $this->householdCondition('h')];
        $params = [];
        if ($filters['householdStatus']) {
            $status = $this->residenceStatus($filters['householdStatus']);
            if ($status !== null) {
                $where[] = $this->residenceStatusSql('h') . ' = :household_status';
                $params['household_status'] = $status;
            } else {
                $where[] = 'h.status = :household_status';
                $params['household_status'] = $filters['householdStatus'];
            }
        }
        if ($filters['residenceStatus']) {
            $where[] = $this->residenceStatusSql('h') . ' = :residence_status';
            $params['residence_status'] = $filters['residenceStatus'];
        }
        if ($filters['residencyStatus']) {
            $where[] = 'c.residency_status = :residency_status';
            $params['residency_status'] = $filters['residencyStatus'];
        }
        if ($filters['presenceStatus']) {
            $where[] = 'c.presence_status = :presence_status';
            $params['presence_status'] = $filters['presenceStatus'];
        }
        if ($filters['dateFrom']) {
            $where[] = 'DATE(c.created_at) >= :citizen_date_from';
            $params['citizen_date_from'] = $filters['dateFrom'];
        }
        if ($filters['dateTo']) {
            $where[] = 'DATE(c.created_at) <= :citizen_date_to';
            $params['citizen_date_to'] = $filters['dateTo'];
        }
        $category = $this->categoryKey($filters['householdType']);
        if ($category) $this->addCategoryWhere($where, $params, $category);
        foreach (self::CITIZEN_FLAG_COLUMNS as $column) {
            $value = $rawFilters[$column] ?? $rawFilters[$this->camel($column)] ?? null;
            if ($column === 'meritorious_person' && $value !== null && $value !== '') {
                $where[] = $this->meritoriousCitizenExpression('c', (int) $value === 1);
            } elseif ($column === 'pupil' && $value !== null && $value !== '') {
                $where[] = ((int) $value === 1 ? '' : 'NOT ') . StudentStatusService::studentSql('c');
            } elseif ($value !== null && $value !== '' && $this->columnExists('citizens', $column)) {
                $where[] = 'c.' . $column . ' = :' . $column;
                $params[$column] = (int) $value;
            }
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }


    public function householdResidenceCounts(array $filters = []): array
    {
        $empty = ['resident' => 0, 'away_for_work' => 0, 'settled_elsewhere' => 0, 'partial' => 0, 'inactive' => 0, 'actual_resident' => 0];
        if (!$this->columnExists('households', 'residence_status')) return $empty;
        [$where, $params] = $this->householdWhere($filters);
        $statusExpr = $this->residenceStatusSql('h');
        $row = $this->fetchOne("SELECT COALESCE(SUM(CASE WHEN $statusExpr = 'resident' THEN 1 ELSE 0 END),0) AS resident_count, COALESCE(SUM(CASE WHEN $statusExpr = 'away_for_work' THEN 1 ELSE 0 END),0) AS away_for_work_count, COALESCE(SUM(CASE WHEN $statusExpr IN ('settled_elsewhere','outside') THEN 1 ELSE 0 END),0) AS settled_elsewhere_count, COALESCE(SUM(CASE WHEN $statusExpr = 'partial' THEN 1 ELSE 0 END),0) AS partial_count, COALESCE(SUM(CASE WHEN $statusExpr = 'inactive' THEN 1 ELSE 0 END),0) AS inactive_count, COALESCE(SUM(CASE WHEN COALESCE(v.at_home_count,0) > 0 THEN 1 ELSE 0 END),0) AS actual_resident_count FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id $where", $params) ?: [];
        return [
            'resident' => (int) ($row['resident_count'] ?? 0),
            'away_for_work' => (int) ($row['away_for_work_count'] ?? 0),
            'settled_elsewhere' => (int) ($row['settled_elsewhere_count'] ?? 0),
            'partial' => (int) ($row['partial_count'] ?? 0),
            'inactive' => (int) ($row['inactive_count'] ?? 0),
            'actual_resident' => (int) ($row['actual_resident_count'] ?? 0),
        ];
    }

    public function householdReviewCounts(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $headExists = 'EXISTS (SELECT 1 FROM citizens hc WHERE hc.household_id = h.id AND hc.relationship = "' . \App\Policies\HouseholdRelationPolicy::HEAD . '" AND ' . $this->currentCitizenCondition('hc') . ')';
        $row = $this->fetchOne("SELECT COALESCE(SUM(CASE WHEN COALESCE(v.at_home_count,0) > 0 THEN 1 ELSE 0 END),0) AS with_present, COALESCE(SUM(CASE WHEN COALESCE(v.total_members,0) > 0 AND COALESCE(v.at_home_count,0) = 0 AND COALESCE(v.away_count,0) = COALESCE(v.total_members,0) THEN 1 ELSE 0 END),0) AS all_away, COALESCE(SUM(CASE WHEN COALESCE(v.total_members,0) = 0 THEN 1 ELSE 0 END),0) AS needs_status_review, COALESCE(SUM(CASE WHEN COALESCE(v.total_members,0) > 0 AND NOT ($headExists) THEN 1 ELSE 0 END),0) AS needs_head_review FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id $where", $params) ?: [];
        return [
            'with_present' => (int) ($row['with_present'] ?? 0),
            'all_away' => (int) ($row['all_away'] ?? 0),
            'needs_status_review' => (int) ($row['needs_status_review'] ?? 0),
            'needs_head_review' => (int) ($row['needs_head_review'] ?? 0),
        ];
    }

    private function currentTemporaryResidenceCount(): int
    {
        $where = [$this->temporaryResidenceCitizenCondition('c'), $this->temporaryAbsenceHouseholdCondition('h')];
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE ' . implode(' AND ', $where)) ?: [];
        return (int) ($row['total'] ?? 0);
    }

    private function currentTemporaryAbsenceCount(): int
    {
        $where = [$this->temporaryAbsenceCitizenCondition('c'), $this->temporaryAbsenceHouseholdCondition('h')];
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE ' . implode(' AND ', $where)) ?: [];
        return (int) ($row['total'] ?? 0);
    }


    private function residenceStatusSql(string $householdAlias = 'h', string $countsAlias = 'v'): string
    {
        $citizenCondition = $this->currentCitizenCondition('rc');
        $total = "(SELECT COUNT(*) FROM citizens rc WHERE rc.household_id = $householdAlias.id AND $citizenCondition)";
        $atHome = "(SELECT COUNT(*) FROM citizens rc WHERE rc.household_id = $householdAlias.id AND $citizenCondition AND COALESCE(rc.presence_status,'AT_HOME') = 'AT_HOME')";
        $away = "(SELECT COUNT(*) FROM citizens rc WHERE rc.household_id = $householdAlias.id AND $citizenCondition AND rc.presence_status = 'AWAY')";
        return "CASE WHEN COALESCE($householdAlias.residence_status_mode,'AUTO') = 'AUTO' AND $total > 0 AND $atHome = 0 AND $away = $total THEN 'away_for_work' ELSE COALESCE($householdAlias.residence_status,'resident') END";
    }

    private function residenceStatus(mixed $value): ?string
    {
        $status = strtolower(trim((string) $value));
        if ($status === 'outside') return 'settled_elsewhere';
        return in_array($status, ['resident', 'away_for_work', 'settled_elsewhere', 'partial', 'inactive'], true) ? $status : null;
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? $filters['date_from'] ?? '')) ?: null,
            'dateTo' => trim((string) ($filters['dateTo'] ?? $filters['date_to'] ?? '')) ?: null,
            'householdStatus' => trim((string) ($filters['householdStatus'] ?? $filters['household_status'] ?? '')) ?: null,
            'residenceStatus' => $this->residenceStatus($filters['residenceStatus'] ?? $filters['residence_status'] ?? $filters['householdResidenceStatus'] ?? '') ?: null,
            'householdType' => trim((string) ($filters['householdCategory'] ?? $filters['household_category'] ?? $filters['householdType'] ?? $filters['household_type'] ?? $filters['category'] ?? '')) ?: null,
            'residencyStatus' => trim((string) ($filters['residencyStatus'] ?? $filters['residency_status'] ?? '')) ?: null,
            'presenceStatus' => trim((string) ($filters['presenceStatus'] ?? $filters['presence_status'] ?? '')) ?: null,
        ];
    }

    private function addCategoryWhere(array &$where, array &$params, string $category): void
    {
        match ($category) {
            'poor' => $where[] = 'h.poor_household = 1',
            'near_poor' => $where[] = 'h.near_poor_household = 1',
            'meritorious' => $where[] = $this->meritoriousHouseholdExists('h'),
            'normal' => $where[] = 'h.poor_household = 0 AND h.near_poor_household = 0 AND NOT ' . $this->meritoriousHouseholdExists('h') . ' AND NOT ' . $this->disabledHouseholdExists('h'),
            'other' => $where[] = $this->disabledHouseholdExists('h'),
            'escaped_poverty', 'policy' => $this->addTextCategoryWhere($where, $params, $category),
            default => null,
        };
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category): void
    {
        $label = ['escaped_poverty' => 'H? m?i tho?t ngh?o', 'policy' => 'H? ch?nh s?ch'][$category] ?? $category;
        $where[] = '(h.note LIKE :category_label OR h.note LIKE :category_key)';
        $params['category_label'] = '%' . $label . '%';
        $params['category_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function categoryKey(mixed $value): string
    {
        $text = $this->normalize((string) $value);
        if ($text === '') return '';
        return match (true) {
            str_contains($text, 'can ngheo') || str_contains($text, 'near poor') => 'near_poor',
            str_contains($text, 'moi thoat ngheo') || str_contains($text, 'thoat ngheo') || str_contains($text, 'escaped poverty') => 'escaped_poverty',
            str_contains($text, 'chinh sach') || str_contains($text, 'policy') => 'policy',
            str_contains($text, 'co cong') || str_contains($text, 'gia dinh co cong') || str_contains($text, 'meritorious') => 'meritorious',
            str_contains($text, 'binh thuong') || str_contains($text, 'normal') || $text === 'khong' => 'normal',
            str_contains($text, 'khac') || str_contains($text, 'tan tat') || str_contains($text, 'khuyet tat') || str_contains($text, 'other') => 'other',
            str_contains($text, 'ngheo') || str_contains($text, 'poor') => 'poor',
            default => '',
        };
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function flagSelects(string $alias): string
    {
        $parts = [];
        foreach (self::CITIZEN_FLAG_COLUMNS as $column) {
            if ($column === 'meritorious_person') {
                $parts[] = ', COALESCE(SUM(CASE WHEN ' . $this->meritoriousCitizenExpression($alias) . " THEN 1 ELSE 0 END),0) AS $column";
            } elseif ($column === 'pupil') {
                $parts[] = ', COALESCE(SUM(CASE WHEN ' . StudentStatusService::studentSql($alias) . " THEN 1 ELSE 0 END),0) AS $column";
            } else {
                $parts[] = ', COALESCE(' . ($this->columnExists('citizens', $column) ? "SUM(CASE WHEN $alias.$column=1 THEN 1 ELSE 0 END)" : '0') . ",0) AS $column";
            }
        }
        return implode('', $parts);
    }
    private function socialAssistanceRecordExists(string $alias): string
    {
        if (!$this->tableExists('citizen_policy_records') || !$this->tableExists('policy_subject_types')) return '0=1';
        return 'EXISTS (SELECT 1 FROM citizen_policy_records cpr INNER JOIN policy_subject_types pst ON pst.id = cpr.policy_type_id WHERE cpr.citizen_id = ' . $alias . '.id AND cpr.status IN ("ACTIVE","PAUSED") AND cpr.deleted_at IS NULL AND pst.deleted_at IS NULL AND COALESCE(pst.is_active,1)=1 AND pst.code="SOCIAL_ASSISTANCE" AND ' . $this->tenantLiteral('citizen_policy_records', 'cpr') . ' AND ' . $this->tenantLiteral('policy_subject_types', 'pst') . ')';
    }

    private function socialAssistanceAnyRecordExists(string $alias): string
    {
        if (!$this->tableExists('citizen_policy_records') || !$this->tableExists('policy_subject_types')) return '0=1';
        return 'EXISTS (SELECT 1 FROM citizen_policy_records cpr INNER JOIN policy_subject_types pst ON pst.id = cpr.policy_type_id WHERE cpr.citizen_id = ' . $alias . '.id AND cpr.deleted_at IS NULL AND pst.deleted_at IS NULL AND COALESCE(pst.is_active,1)=1 AND pst.code="SOCIAL_ASSISTANCE" AND ' . $this->tenantLiteral('citizen_policy_records', 'cpr') . ' AND ' . $this->tenantLiteral('policy_subject_types', 'pst') . ')';
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
    private function tenantLiteral(string $table, string $alias = ''): string
    {
        if (!$this->tenantColumnExists($table)) return '1=1';
        return ($alias !== '' ? $alias . '.' : '') . 'village_id = ' . $this->tenantId();
    }

    private function meritoriousCitizenExpression(string $alias, bool $positive = true): string
    {
        $parts = [];
        foreach (self::MERITORIOUS_POLICY_COLUMNS as $column) {
            if ($this->columnExists('citizens', $column)) $parts[] = $alias . '.' . $column . '=1';
        }
        if (!$parts) return $positive ? '0=1' : '1=1';
        $expression = '(' . implode(' OR ', $parts) . ')';
        return $positive ? $expression : 'NOT ' . $expression;
    }

    private function meritoriousHouseholdExists(string $alias): string
    {
        $citizenPolicy = $this->meritoriousCitizenExpression('mc');
        if ($citizenPolicy === '0=1') return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens mc WHERE mc.household_id=' . $alias . '.id AND ' . $this->citizenCondition('mc') . ' AND ' . $citizenPolicy . ')';
    }

    private function disabledHouseholdExists(string $alias): string
    {
        if (!$this->columnExists('citizens', 'disabled_person')) return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens dc WHERE dc.household_id=' . $alias . '.id AND ' . $this->citizenCondition('dc') . ' AND dc.disabled_person=1)';
    }

    private function camel(string $column): string
    {
        return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $column);
    }
}
