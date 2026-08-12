<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Policies\AgePolicy;
use App\Policies\InsurancePolicy;
use App\Services\HouseholdCategoryService;
use App\Services\StudentStatusService;

final class PopulationStatistics extends BaseModel
{
    private ?HouseholdCategoryService $categoryService = null;

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

    public function householdCondition(string $alias = 'h'): string
    {
        $conditions = [$this->notDeletedCondition('households', $alias)];
        if ($this->columnExists('households', 'status')) {
            $conditions[] = $alias . ".status NOT IN ('ENDED','MERGED','TRANSFERRED_OUT','MOVED_OUT','INACTIVE')";
        }
        return implode(' AND ', $conditions);
    }

    public function citizenCondition(string $alias = 'c'): string
    {
        $conditions = [$this->notDeletedCondition('citizens', $alias)];
        if ($this->columnExists('citizens', 'life_status')) {
            $conditions[] = "COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED'";
        }
        if ($this->columnExists('citizens', 'residency_status')) {
            $conditions[] = "COALESCE(" . $alias . ".residency_status,'PERMANENT') <> 'TRANSFERRED_OUT'";
        }
        return implode(' AND ', $conditions);
    }

    public function temporaryAbsenceCitizenCondition(string $alias = 'c'): string
    {
        $conditions = [$this->notDeletedCondition('citizens', $alias)];
        if ($this->columnExists('citizens', 'life_status')) {
            $conditions[] = "COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED'";
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

        $categoryCounts = $this->categoryService()->countsSelect('h');
        $meritoriousExpr = $this->meritoriousHouseholdExists('h');
        $disabledExpr = $this->disabledHouseholdExists('h');
        $households = $this->fetchOne("SELECT COUNT(*) AS total_households, $categoryCounts, COALESCE(SUM(CASE WHEN $meritoriousExpr THEN 1 ELSE 0 END),0) AS meritorious_households, COALESCE(SUM(CASE WHEN $disabledExpr THEN 1 ELSE 0 END),0) AS disabled_households FROM households h $householdWhere", $householdParams) ?: [];
        $residenceCounts = $this->householdResidenceCounts($filters);

        $ageSql = AgePolicy::ageSql('c');
        $healthInsuranceEffectiveExpr = InsurancePolicy::effectiveConditionSql(
            'c',
            $this->columnExists('citizens', 'has_health_insurance'),
            $this->columnExists('citizens', 'health_insurance_end_date')
        );
        $socialAssistanceExpr = $this->columnExists('citizens', 'social_assistance')
            ? 'COALESCE(c.social_assistance,0)=1'
            : '0=1';

        $citizens = $this->fetchOne("SELECT COUNT(*) AS total_citizens, COALESCE(SUM(CASE WHEN c.gender='Nam' THEN 1 ELSE 0 END),0) AS male_count, COALESCE(SUM(CASE WHEN c.gender='Ná»¯' THEN 1 ELSE 0 END),0) AS female_count, COALESCE(SUM(CASE WHEN c.relationship='Chá»§ há»™' THEN 1 ELSE 0 END),0) AS household_head_count, COALESCE(SUM(CASE WHEN c.life_status='ALIVE' THEN 1 ELSE 0 END),0) AS active_citizens, COALESCE(SUM(CASE WHEN c.residency_status='TEMPORARY' THEN 1 ELSE 0 END),0) AS temporary_residence_count, COALESCE(SUM(CASE WHEN c.presence_status='AWAY' THEN 1 ELSE 0 END),0) AS temporary_absence_count, COALESCE(SUM(CASE WHEN " . AgePolicy::childConditionSql('c') . " THEN 1 ELSE 0 END),0) AS children_count, COALESCE(SUM(CASE WHEN " . AgePolicy::statisticalElderlyConditionSql('c') . " THEN 1 ELSE 0 END),0) AS elderly_count, COALESCE(SUM(CASE WHEN " . AgePolicy::workingAgeConditionSql('c') . " THEN 1 ELSE 0 END),0) AS working_age_count, COALESCE(SUM(CASE WHEN $ageSql >= " . AgePolicy::BHYT_DEFAULT_AGE . " AND $healthInsuranceEffectiveExpr THEN 1 ELSE 0 END),0) AS elderly_health_insurance_count, COALESCE(SUM(CASE WHEN $ageSql >= " . AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE . " AND $socialAssistanceExpr THEN 1 ELSE 0 END),0) AS elderly_social_assistance_count" . $this->flagSelects('c') . " FROM citizens c INNER JOIN households h ON h.id = c.household_id $citizenWhere", $citizenParams) ?: [];

        $totalCitizens = max(1, (int) ($citizens['total_citizens'] ?? 0));
        $totalHouseholds = max(1, (int) ($households['total_households'] ?? 0));
        $temporaryStatusCounts = $this->currentTemporaryStatusCounts();
        $temporaryResidence = $temporaryStatusCounts['temporary_residence_count'];
        $temporaryAbsence = $temporaryStatusCounts['temporary_absence_count'];

        $metrics = [
            'total_households' => (int) ($households['total_households'] ?? 0),
            'total_citizens' => (int) ($citizens['total_citizens'] ?? 0),
            'male_count' => (int) ($citizens['male_count'] ?? 0),
            'female_count' => (int) ($citizens['female_count'] ?? 0),
            'household_head_count' => (int) ($citizens['household_head_count'] ?? 0),
            'active_citizens' => (int) ($citizens['active_citizens'] ?? 0),
            'children_count' => (int) ($citizens['children_count'] ?? 0),
            'elderly_count' => (int) ($citizens['elderly_count'] ?? 0),
            'working_age_count' => (int) ($citizens['working_age_count'] ?? 0),
            'elderly_health_insurance_count' => (int) ($citizens['elderly_health_insurance_count'] ?? 0),
            'elderly_social_assistance_count' => (int) ($citizens['elderly_social_assistance_count'] ?? 0),
            'temporary_residence_count' => $temporaryResidence,
            'temporary_absence_count' => $temporaryAbsence,
            'temporary_count' => $temporaryResidence,
            'away_count' => $temporaryAbsence,
            'poor_households' => (int) ($households['poor_households'] ?? 0),
            'near_poor_households' => (int) ($households['near_poor_households'] ?? 0),
            'medium_households' => (int) ($households['medium_households'] ?? 0),
            'ho_ngheo' => (int) ($households['poor_households'] ?? 0),
            'ho_can_ngheo' => (int) ($households['near_poor_households'] ?? 0),
            'ho_trung_binh' => (int) ($households['medium_households'] ?? 0),
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
        ];

        foreach (self::CITIZEN_FLAG_COLUMNS as $key) {
            $metrics[$key . '_count'] = (int) ($citizens[$key] ?? 0);
            $metrics[$key . '_percent'] = round($metrics[$key . '_count'] * 100 / $totalCitizens, 2);
        }
        $activePartyMembers = $this->activePartyMemberCount($filters);
        if ($activePartyMembers !== null) {
            $metrics['party_member_count'] = $activePartyMembers;
            $metrics['party_member_percent'] = round($activePartyMembers * 100 / $totalCitizens, 2);
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


    private function householdResidenceCounts(array $filters = []): array
    {
        $empty = ['resident' => 0, 'away_for_work' => 0, 'settled_elsewhere' => 0, 'partial' => 0, 'inactive' => 0, 'actual_resident' => 0];
        if (!$this->columnExists('households', 'residence_status')) return $empty;
        [$where, $params] = $this->householdWhere($filters);
        $statusExpr = $this->residenceStatusSql('h');
        $row = $this->fetchOne("SELECT
            COALESCE(SUM(CASE WHEN $statusExpr = 'resident' THEN 1 ELSE 0 END),0) AS resident,
            COALESCE(SUM(CASE WHEN $statusExpr = 'away_for_work' THEN 1 ELSE 0 END),0) AS away_for_work_count,
            COALESCE(SUM(CASE WHEN $statusExpr IN ('settled_elsewhere','outside') THEN 1 ELSE 0 END),0) AS settled_elsewhere_count,
            COALESCE(SUM(CASE WHEN $statusExpr = 'partial' THEN 1 ELSE 0 END),0) AS partial_count,
            COALESCE(SUM(CASE WHEN $statusExpr = 'inactive' THEN 1 ELSE 0 END),0) AS inactive_count
            FROM households h $where", $params) ?: [];
        $resident = (int) ($row['resident'] ?? 0);
        $partial = (int) ($row['partial_count'] ?? 0);
        return [
            'resident' => $resident,
            'away_for_work' => (int) ($row['away_for_work_count'] ?? 0),
            'settled_elsewhere' => (int) ($row['settled_elsewhere_count'] ?? 0),
            'partial' => $partial,
            'inactive' => (int) ($row['inactive_count'] ?? 0),
            'actual_resident' => $resident,
        ];
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
            if (in_array($filters['householdStatus'], ['resident', 'away_for_work', 'settled_elsewhere', 'partial', 'inactive', 'outside'], true)) {
                $where[] = $this->residenceStatusSql('h') . ' = :household_status';
                $params['household_status'] = $filters['householdStatus'];
            } else {
                $where[] = 'h.status = :household_status';
                $params['household_status'] = $filters['householdStatus'];
            }
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
        $where = [$this->citizenCondition('c'), $this->householdCondition('h')];
        $params = [];
        if ($filters['householdStatus']) {
            if (in_array($filters['householdStatus'], ['resident', 'away_for_work', 'settled_elsewhere', 'partial', 'inactive', 'outside'], true)) {
                $where[] = $this->residenceStatusSql('h') . ' = :household_status';
                $params['household_status'] = $filters['householdStatus'];
            } else {
                $where[] = 'h.status = :household_status';
                $params['household_status'] = $filters['householdStatus'];
            }
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


    private function residenceStatusSql(string $householdAlias = 'h'): string
    {
        $active = "c.status <> 'DELETED' AND COALESCE(c.life_status,'ALIVE') <> 'DECEASED' AND COALESCE(c.residency_status,'PERMANENT') <> 'TRANSFERRED_OUT'";
        $total = "(SELECT COUNT(*) FROM citizens c WHERE c.household_id = $householdAlias.id AND $active)";
        $atHome = "(SELECT COUNT(*) FROM citizens c WHERE c.household_id = $householdAlias.id AND $active AND c.presence_status = 'AT_HOME')";
        $away = "(SELECT COUNT(*) FROM citizens c WHERE c.household_id = $householdAlias.id AND $active AND c.presence_status = 'AWAY')";
        return "CASE WHEN COALESCE($householdAlias.residence_status_mode,'AUTO') = 'AUTO' AND $total > 0 AND $atHome = 0 AND $away = $total THEN 'away_for_work' ELSE COALESCE($householdAlias.residence_status,'resident') END";
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

    private function normalizeFilters(array $filters): array
    {
        return [
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? $filters['date_from'] ?? '')) ?: null,
            'dateTo' => trim((string) ($filters['dateTo'] ?? $filters['date_to'] ?? '')) ?: null,
            'householdStatus' => trim((string) ($filters['householdStatus'] ?? $filters['household_status'] ?? '')) ?: null,
            'householdType' => trim((string) ($filters['householdType'] ?? $filters['household_type'] ?? $filters['category'] ?? '')) ?: null,
            'residencyStatus' => trim((string) ($filters['residencyStatus'] ?? $filters['residency_status'] ?? '')) ?: null,
            'presenceStatus' => trim((string) ($filters['presenceStatus'] ?? $filters['presence_status'] ?? '')) ?: null,
        ];
    }

    private function addCategoryWhere(array &$where, array &$params, string $category): void
    {
        $condition = $this->categoryService()->condition($category, 'h');
        if ($condition !== '') $where[] = $condition;
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category): void
    {
        $label = ['escaped_poverty' => 'Há»™ má»›i thoÃ¡t nghÃ¨o', 'policy' => 'Há»™ chÃ­nh sÃ¡ch'][$category] ?? $category;
        $where[] = '(h.note LIKE :category_label OR h.note LIKE :category_key)';
        $params['category_label'] = '%' . $label . '%';
        $params['category_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function categoryKey(mixed $value): string
    {
        return HouseholdCategoryService::normalizeKey($value);
    }

    private function categoryService(): HouseholdCategoryService
    {
        return $this->categoryService ??= new HouseholdCategoryService();
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

    private function activePartyMemberCount(array $filters): ?int
    {
        if (!$this->tableExists('party_members') || !$this->columnExists('party_members', 'party_status')) return null;
        [$where, $params] = $this->citizenWhere($filters);
        $sql = "SELECT COUNT(*) AS total
            FROM party_members pm
            INNER JOIN citizens c ON c.id = pm.citizen_id
            INNER JOIN households h ON h.id = c.household_id
            $where
              AND pm.status <> 'DELETED'
              AND pm.party_status IN ('ACTIVE','TEMPORARY')
              AND " . $this->tenantWhere('pm', 'party_members');
        return (int) (($this->fetchOne($sql, $params) ?: [])['total'] ?? 0);
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
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
