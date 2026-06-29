<?php

namespace App\Models;

use App\Core\BaseModel;

final class Dashboard extends BaseModel
{
    public function summary(array $filters = []): array
    {
        return [
            'metrics' => $this->metrics($filters),
            'charts' => [
                'population' => $this->populationChart($filters),
                'households' => $this->householdChart($filters),
                'ages' => $this->ageChart($filters),
                'residency' => $this->residencyChart($filters),
                'hamlets' => $this->hamletChart($filters),
                'monthlyChanges' => $this->monthlyChangeChart($filters),
                'poverty' => $this->povertyChart($filters),
                'partyMembers' => $this->flagChart($filters, 'party_member', 'Đảng viên'),
                'youthUnion' => $this->flagChart($filters, 'youth_union_member', 'Đoàn viên'),
                'labor' => $this->laborChart($filters),
                'occupations' => $this->groupChart($filters, 'occupation', 'Nghề nghiệp'),
                'ethnicities' => $this->groupChart($filters, 'ethnicity', 'Dân tộc'),
                'religions' => $this->groupChart($filters, 'religion', 'Tôn giáo'),
            ],
            'filters' => $this->normalizeFilters($filters),
            'generatedAt' => date('c'),
        ];
    }

    public function metrics(array $filters = []): array
    {
        [$householdWhere, $householdParams] = $this->householdWhere($filters);
        [$citizenWhere, $citizenParams] = $this->citizenWhere($filters);
        $households = $this->fetchOne("SELECT COUNT(*) AS total_households, COALESCE(SUM(CASE WHEN h.poor_household=1 THEN 1 ELSE 0 END),0) AS poor_households, COALESCE(SUM(CASE WHEN h.near_poor_household=1 THEN 1 ELSE 0 END),0) AS near_poor_households, COALESCE(SUM(CASE WHEN h.meritorious_family=1 THEN 1 ELSE 0 END),0) AS meritorious_households, COALESCE(SUM(CASE WHEN h.note LIKE '%Hộ chính sách%' OR h.note LIKE '%chính sách%' THEN 1 ELSE 0 END),0) AS policy_households, COALESCE(SUM(CASE WHEN h.poor_household=0 AND h.near_poor_household=0 AND h.meritorious_family=0 AND h.disabled_household=0 THEN 1 ELSE 0 END),0) AS normal_households FROM households h $householdWhere", $householdParams) ?: [];
        $citizens = $this->fetchOne("SELECT COUNT(*) AS total_citizens, COALESCE(SUM(CASE WHEN c.gender='Nam' THEN 1 ELSE 0 END),0) AS male_count, COALESCE(SUM(CASE WHEN c.gender='Nữ' THEN 1 ELSE 0 END),0) AS female_count, COALESCE(SUM(CASE WHEN c.relationship='Chủ hộ' THEN 1 ELSE 0 END),0) AS household_head_count, COALESCE(SUM(CASE WHEN c.life_status='ALIVE' THEN 1 ELSE 0 END),0) AS active_citizens, COALESCE(SUM(CASE WHEN c.residency_status='TEMPORARY' THEN 1 ELSE 0 END),0) AS temporary_count, COALESCE(SUM(CASE WHEN c.presence_status='AWAY' THEN 1 ELSE 0 END),0) AS away_count, COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 16 THEN 1 ELSE 0 END),0) AS children_count, COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 60 THEN 1 ELSE 0 END),0) AS elderly_count, COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 16 AND 59 THEN 1 ELSE 0 END),0) AS working_age_count" . $this->flagSelects('c') . " FROM citizens c INNER JOIN households h ON h.id = c.household_id $citizenWhere", $citizenParams) ?: [];
        $totalCitizens = max(1, (int) ($citizens['total_citizens'] ?? 0));
        $totalHouseholds = max(1, (int) ($households['total_households'] ?? 0));
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
            'temporary_count' => (int) ($citizens['temporary_count'] ?? 0),
            'away_count' => (int) ($citizens['away_count'] ?? 0),
            'poor_households' => (int) ($households['poor_households'] ?? 0),
            'near_poor_households' => (int) ($households['near_poor_households'] ?? 0),
            'policy_households' => (int) ($households['policy_households'] ?? 0),
            'meritorious_households' => (int) ($households['meritorious_households'] ?? 0),
            'normal_households' => (int) ($households['normal_households'] ?? 0),
        ];
        foreach (['party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'] as $key) {
            $metrics[$key . '_count'] = (int) ($citizens[$key] ?? 0);
            $metrics[$key . '_percent'] = round($metrics[$key . '_count'] * 100 / $totalCitizens, 2);
        }
        $metrics['poor_households_percent'] = round($metrics['poor_households'] * 100 / $totalHouseholds, 2);
        $metrics['near_poor_households_percent'] = round($metrics['near_poor_households'] * 100 / $totalHouseholds, 2);
        $metrics['children_percent'] = round($metrics['children_count'] * 100 / $totalCitizens, 2);
        $metrics['elderly_percent'] = round($metrics['elderly_count'] * 100 / $totalCitizens, 2);
        $metrics['working_age_percent'] = round($metrics['working_age_count'] * 100 / $totalCitizens, 2);
        return $metrics;
    }

    public function populationChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT COALESCE(NULLIF(c.gender,''),'Khác') AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY label", $params);
    }

    public function householdChart(array $filters = []): array
    {
        return $this->povertyChart($filters);
    }

    public function ageChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 5 THEN '0-5 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 6 AND 14 THEN '6-14 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 15 AND 17 THEN '15-17 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 18 AND 59 THEN '18-59 tuổi' ELSE 'Trên 60 tuổi' END AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY MIN(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()))", $params);
    }

    public function residencyChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT CASE c.residency_status WHEN 'TEMPORARY' THEN 'Tạm trú' ELSE 'Thường trú' END AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY c.residency_status ORDER BY c.residency_status", $params);
    }

    public function hamletChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),'Thôn 09') AS label, COUNT(c.id) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY label", $params);
    }

    public function monthlyChangeChart(array $filters = []): array
    {
        $rows = $this->fetchAll("SELECT DATE_FORMAT(effective_date, '%Y-%m') AS label, SUM(CASE WHEN type IN ('BIRTH','MOVE_IN','TEMPORARY_RESIDENCE') THEN 1 WHEN type IN ('DEATH','MOVE_OUT','TEMPORARY_ABSENCE') THEN -1 ELSE 0 END) AS value FROM movements WHERE status <> 'DELETED' AND effective_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY label ORDER BY label");
        return array_map(fn($row) => ['label' => $row['label'], 'value' => (int) $row['value']], $rows);
    }

    public function povertyChart(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $row = $this->fetchOne("SELECT COALESCE(SUM(CASE WHEN h.poor_household=1 THEN 1 ELSE 0 END),0) AS poor, COALESCE(SUM(CASE WHEN h.near_poor_household=1 THEN 1 ELSE 0 END),0) AS near_poor, COALESCE(SUM(CASE WHEN h.note LIKE '%Hộ chính sách%' OR h.note LIKE '%chính sách%' THEN 1 ELSE 0 END),0) AS policy, COALESCE(SUM(CASE WHEN h.meritorious_family=1 THEN 1 ELSE 0 END),0) AS meritorious, COALESCE(SUM(CASE WHEN h.poor_household=0 AND h.near_poor_household=0 AND h.meritorious_family=0 AND h.disabled_household=0 THEN 1 ELSE 0 END),0) AS normal, COALESCE(SUM(CASE WHEN h.disabled_household=1 THEN 1 ELSE 0 END),0) AS other FROM households h $where", $params) ?: [];
        return [
            ['label' => 'Hộ nghèo', 'value' => (int) ($row['poor'] ?? 0)],
            ['label' => 'Hộ cận nghèo', 'value' => (int) ($row['near_poor'] ?? 0)],
            ['label' => 'Hộ chính sách', 'value' => (int) ($row['policy'] ?? 0)],
            ['label' => 'Hộ có công', 'value' => (int) ($row['meritorious'] ?? 0)],
            ['label' => 'Hộ bình thường', 'value' => (int) ($row['normal'] ?? 0)],
            ['label' => 'Khác', 'value' => (int) ($row['other'] ?? 0)],
        ];
    }

    public function flagChart(array $filters, string $column, string $label): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        if (!$this->columnExists('citizens', $column)) return [['label' => $label, 'value' => 0], ['label' => 'Còn lại', 'value' => 0]];
        $row = $this->fetchOne("SELECT SUM(c.$column=1) AS yes_count, SUM(c.$column=0 OR c.$column IS NULL) AS no_count FROM citizens c INNER JOIN households h ON h.id = c.household_id $where", $params) ?: [];
        return [['label' => $label, 'value' => (int) ($row['yes_count'] ?? 0)], ['label' => 'Còn lại', 'value' => (int) ($row['no_count'] ?? 0)]];
    }

    public function laborChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $columns = ['employed' => 'Có việc làm', 'unemployed' => 'Thất nghiệp', 'freelance_labor' => 'Lao động tự do', 'out_province_labor' => 'Lao động ngoài tỉnh', 'foreign_labor' => 'Lao động nước ngoài', 'pupil' => 'Học sinh', 'student' => 'Sinh viên', 'retired' => 'Nghỉ hưu'];
        $selects = [];
        foreach ($columns as $column => $label) $selects[] = ($this->columnExists('citizens', $column) ? "SUM(c.$column=1)" : '0') . " AS $column";
        $row = $this->fetchOne('SELECT ' . implode(',', $selects) . " FROM citizens c INNER JOIN households h ON h.id = c.household_id $where", $params) ?: [];
        $items = [];
        foreach ($columns as $column => $label) $items[] = ['label' => $label, 'value' => (int) ($row[$column] ?? 0)];
        return $items;
    }

    public function groupChart(array $filters, string $column, string $fallbackLabel): array
    {
        if (!in_array($column, ['occupation','ethnicity','religion'], true)) return [];
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT COALESCE(NULLIF(c.$column,''),'Khác') AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY value DESC, label LIMIT 10", $params);
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

    private function householdWhere(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $where = ['h.status <> "DELETED"'];
        $params = [];
        if ($filters['householdStatus']) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        if ($filters['dateFrom']) { $where[] = 'DATE(h.created_at) >= :household_date_from'; $params['household_date_from'] = $filters['dateFrom']; }
        if ($filters['dateTo']) { $where[] = 'DATE(h.created_at) <= :household_date_to'; $params['household_date_to'] = $filters['dateTo']; }
        $category = $this->categoryKey($filters['householdType']);
        if ($category) $this->addCategoryWhere($where, $params, $category);
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function citizenWhere(array $filters): array
    {
        $rawFilters = $filters;
        $filters = $this->normalizeFilters($filters);
        $where = ['c.status <> "DELETED"', 'h.status <> "DELETED"'];
        $params = [];
        if ($filters['householdStatus']) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        if ($filters['residencyStatus']) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if ($filters['presenceStatus']) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if ($filters['dateFrom']) { $where[] = 'DATE(c.created_at) >= :citizen_date_from'; $params['citizen_date_from'] = $filters['dateFrom']; }
        if ($filters['dateTo']) { $where[] = 'DATE(c.created_at) <= :citizen_date_to'; $params['citizen_date_to'] = $filters['dateTo']; }
        $category = $this->categoryKey($filters['householdType']);
        if ($category) $this->addCategoryWhere($where, $params, $category);
        foreach (['party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'] as $column) {
            $value = $rawFilters[$column] ?? $rawFilters[$this->camel($column)] ?? null;
            if ($value !== null && $value !== '' && $this->columnExists('citizens', $column)) { $where[] = 'c.' . $column . ' = :' . $column; $params[$column] = (int) $value; }
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function addCategoryWhere(array &$where, array &$params, string $category): void
    {
        match ($category) {
            'poor' => $where[] = 'h.poor_household = 1',
            'near_poor' => $where[] = 'h.near_poor_household = 1',
            'meritorious' => $where[] = 'h.meritorious_family = 1',
            'normal' => $where[] = 'h.poor_household = 0 AND h.near_poor_household = 0 AND h.meritorious_family = 0 AND h.disabled_household = 0',
            'other' => $where[] = 'h.disabled_household = 1',
            'escaped_poverty', 'policy' => $this->addTextCategoryWhere($where, $params, $category),
            default => null,
        };
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category): void
    {
        $label = ['escaped_poverty' => 'Hộ mới thoát nghèo', 'policy' => 'Hộ chính sách'][$category] ?? $category;
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
    private function camel(string $column): string { return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $column); }

    private function flagSelects(string $alias): string
    {
        $columns = ['party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'];
        $parts = [];
        foreach ($columns as $column) $parts[] = ', COALESCE(' . ($this->columnExists('citizens', $column) ? "SUM(CASE WHEN $alias.$column=1 THEN 1 ELSE 0 END)" : '0') . ",0) AS $column";
        return implode('', $parts);
    }

}
