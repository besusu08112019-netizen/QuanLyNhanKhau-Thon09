<?php

namespace App\Models;

use App\Core\BaseModel;

final class Dashboard extends BaseModel
{
    public function summary(array $filters = []): array
    {
        $errors = [];
        $metrics = $this->safeWidget('metrics', fn() => $this->metrics($filters), $this->defaultMetrics(), $errors);
        $businessDashboard = $this->safeWidget('household_business.dashboard', fn() => (new \App\Models\HouseholdBusiness())->dashboard(), ['production_households' => 0, 'business_households' => 0, 'production_business_households' => 0, 'business_worker_total' => 0], $errors);
        $metrics = array_merge($metrics, $businessDashboard);
        $charts = [
            'population' => $this->safeWidget('charts.population', fn() => $this->populationChart($filters), [], $errors),
            'households' => $this->safeWidget('charts.households', fn() => $this->householdChart($filters), [], $errors),
            'ages' => $this->safeWidget('charts.ages', fn() => $this->ageChart($filters), [], $errors),
            'residency' => $this->safeWidget('charts.residency', fn() => $this->residencyChart($filters), [], $errors),
            'hamlets' => $this->safeWidget('charts.hamlets', fn() => $this->hamletChart($filters), [], $errors),
            'monthlyChanges' => $this->safeWidget('charts.monthlyChanges', fn() => $this->monthlyChangeChart($filters), [], $errors),
            'poverty' => $this->safeWidget('charts.poverty', fn() => $this->povertyChart($filters), [], $errors),
            'partyMembers' => $this->safeWidget('charts.partyMembers', fn() => $this->flagChart($filters, 'party_member', 'Đảng viên'), [], $errors),
            'youthUnion' => $this->safeWidget('charts.youthUnion', fn() => $this->flagChart($filters, 'youth_union_member', 'Đoàn viên'), [], $errors),
            'labor' => $this->safeWidget('charts.labor', fn() => $this->laborChart($filters), [], $errors),
            'occupations' => $this->safeWidget('charts.occupations', fn() => $this->groupChart($filters, 'occupation', 'Nghề nghiệp'), [], $errors),
            'educationLevels' => $this->safeWidget('charts.educationLevels', fn() => $this->groupChart($filters, 'education_level', 'Trình độ học vấn'), [], $errors),
            'ethnicities' => $this->safeWidget('charts.ethnicities', fn() => $this->groupChart($filters, 'ethnicity', 'Dân tộc'), [], $errors),
            'religions' => $this->safeWidget('charts.religions', fn() => $this->groupChart($filters, 'religion', 'Tôn giáo'), [], $errors),
            'gpsProgress' => $this->safeWidget('charts.gpsProgress', fn() => $this->gpsProgressChart($filters), [], $errors),
            'profileProgress' => $this->safeWidget('charts.profileProgress', fn() => $this->profileProgressChart($filters), [], $errors),
            'healthInsurance' => $this->safeWidget('charts.healthInsurance', fn() => $this->healthInsuranceChart($filters), [], $errors),
            'businessTypes' => $this->safeWidget('charts.businessTypes', fn() => (new \App\Models\HouseholdBusiness())->charts()['types'] ?? [], [], $errors),
            'businessSectors' => $this->safeWidget('charts.businessSectors', fn() => (new \App\Models\HouseholdBusiness())->charts()['sectors'] ?? [], [], $errors),
            'businessStatuses' => $this->safeWidget('charts.businessStatuses', fn() => (new \App\Models\HouseholdBusiness())->charts()['statuses'] ?? [], [], $errors),
        ];

        $payload = [
            'metrics' => $metrics,
            'charts' => $charts,
            'alerts' => $this->safeWidget('alerts', fn() => $this->alerts($filters), null, $errors),
            'movementWindows' => $this->safeWidget('movementWindows', fn() => $this->movementWindows($filters), null, $errors),
            'gis' => $this->safeWidget('gis', fn() => $this->gisSummary($filters), null, $errors),
            'profiles' => $this->safeWidget('profiles', fn() => $this->profileSummary($filters), null, $errors),
            'tasks' => $this->safeWidget('tasks', fn() => $this->tasks($filters), null, $errors),
            'filters' => $this->normalizeFilters($filters),
            'generatedAt' => date('c'),
        ];
        if ($errors) $payload['widgetErrors'] = $errors;
        return $payload;
    }

    private function safeWidget(string $name, callable $callback, mixed $fallback, array &$errors): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $exception) {
            $lastQuery = self::lastQuery();
            $errors[$name] = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ];
            error_log('[DASHBOARD_WIDGET_ERROR] ' . json_encode([
                'widget' => $name,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'sql' => $lastQuery['sql'] ?? null,
                'params' => $lastQuery['params'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $fallback;
        }
    }

    private function defaultMetrics(): array
    {
        $metrics = [
            'total_households' => 0,
            'total_citizens' => 0,
            'male_count' => 0,
            'female_count' => 0,
            'household_head_count' => 0,
            'active_citizens' => 0,
            'children_count' => 0,
            'elderly_count' => 0,
            'working_age_count' => 0,
            'temporary_count' => 0,
            'away_count' => 0,
            'poor_households' => 0,
            'near_poor_households' => 0,
            'policy_households' => 0,
            'meritorious_households' => 0,
            'normal_households' => 0,
            'health_insurance_total' => 0,
            'health_insurance_count' => 0,
            'health_insurance_covered_count' => 0,
            'health_insurance_missing_count' => 0,
            'health_insurance_uninsured_count' => 0,
            'health_insurance_coverage_percent' => 0,
            'health_insurance_percent' => 0,
            'production_households' => 0,
            'business_households' => 0,
            'production_business_households' => 0,
            'business_worker_total' => 0,
        ];
        foreach (['has_health_insurance','party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'] as $key) {
            $metrics[$key . '_count'] = 0;
            $metrics[$key . '_percent'] = 0;
        }
        $metrics['poor_households_percent'] = 0;
        $metrics['near_poor_households_percent'] = 0;
        $metrics['children_percent'] = 0;
        $metrics['elderly_percent'] = 0;
        $metrics['working_age_percent'] = 0;
        return $metrics;
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
        foreach (['has_health_insurance','party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'] as $key) {
            $metrics[$key . '_count'] = (int) ($citizens[$key] ?? 0);
            $metrics[$key . '_percent'] = round($metrics[$key . '_count'] * 100 / $totalCitizens, 2);
        }
        $metrics['poor_households_percent'] = round($metrics['poor_households'] * 100 / $totalHouseholds, 2);
        $metrics['near_poor_households_percent'] = round($metrics['near_poor_households'] * 100 / $totalHouseholds, 2);
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
        (new Citizen())->ensureHealthInsuranceSchema();
        [$where, $params] = $this->citizenWhere($filters);
        $hasColumn = $this->columnExists('citizens', 'has_health_insurance');
        $endColumn = $this->columnExists('citizens', 'health_insurance_end_date');
        $hasExpr = $hasColumn ? 'c.has_health_insurance=1' : '0=1';
        $effectiveExpr = $endColumn ? "($hasExpr AND (c.health_insurance_end_date IS NULL OR c.health_insurance_end_date >= CURDATE()))" : $hasExpr;
        $row = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN $hasExpr THEN 1 ELSE 0 END),0) AS enrolled, COALESCE(SUM(CASE WHEN $effectiveExpr THEN 1 ELSE 0 END),0) AS effective FROM citizens c INNER JOIN households h ON h.id = c.household_id $where", $params) ?: [];
        $total = (int) ($row['total'] ?? 0);
        $enrolled = (int) ($row['enrolled'] ?? 0);
        $effective = (int) ($row['effective'] ?? 0);
        $uninsured = max(0, $total - $enrolled);
        return [
            'total' => $total,
            'insured' => $effective,
            'enrolled' => $enrolled,
            'effective' => $effective,
            'uninsured' => $uninsured,
            'coverage_percent' => $total > 0 ? round($effective * 100 / $total, 2) : 0,
        ];
    }

    public function healthInsuranceChart(array $filters = []): array
    {
        $stats = $this->healthInsuranceStats($filters);
        return [
            ['label' => 'Có BHYT', 'value' => $stats['insured']],
            ['label' => 'Chưa có BHYT', 'value' => $stats['uninsured']],
        ];
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
        return $this->fetchAll("SELECT CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 5 THEN '0-5 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 6 AND 14 THEN '6-14 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 15 AND 17 THEN '15-17 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 18 AND 59 THEN '18-59 tuổi' ELSE 'Từ 60 tuổi trở lên' END AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY MIN(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()))", $params);
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
        $columns = ['employed','unemployed','pupil','student','retired'];
        $selects = ['c.occupation'];
        foreach ($columns as $column) {
            $selects[] = ($this->columnExists('citizens', $column) ? "c.$column" : "0") . " AS $column";
        }

        $rows = $this->fetchAll('SELECT ' . implode(',', $selects) . " FROM citizens c INNER JOIN households h ON h.id = c.household_id $where", $params);
        $groups = [
            'Có việc làm' => 0,
            'Chưa có việc làm' => 0,
            'Học sinh' => 0,
            'Sinh viên' => 0,
            'Nghỉ hưu' => 0,
            'Khác' => 0,
        ];

        foreach ($rows as $row) {
            $groups[$this->laborGroup($row)]++;
        }

        $items = [];
        foreach ($groups as $label => $value) {
            $items[] = ['label' => $label, 'value' => (int) $value];
        }
        return $items;
    }

    private function laborGroup(array $row): string
    {
        $occupation = $this->normalize((string) ($row['occupation'] ?? ''));
        if ((int) ($row['pupil'] ?? 0) === 1 || str_contains($occupation, 'hoc sinh')) return 'Học sinh';
        if ((int) ($row['student'] ?? 0) === 1 || str_contains($occupation, 'sinh vien')) return 'Sinh viên';
        if ((int) ($row['retired'] ?? 0) === 1 || str_contains($occupation, 'nghi huu') || str_contains($occupation, 'huu tri')) return 'Nghỉ hưu';
        if ((int) ($row['unemployed'] ?? 0) === 1 || str_contains($occupation, 'that nghiep') || str_contains($occupation, 'chua co viec') || str_contains($occupation, 'khong co viec')) return 'Chưa có việc làm';
        if ((int) ($row['employed'] ?? 0) === 1) return 'Có việc làm';
        if ($occupation === '' || str_contains($occupation, 'khac') || str_contains($occupation, 'noi tro')) return 'Khác';
        return 'Có việc làm';
    }

    public function groupChart(array $filters, string $column, string $fallbackLabel): array
    {
        if (!in_array($column, ['occupation','education_level','ethnicity','religion'], true)) return [];
        if (!$this->columnExists('citizens', $column)) return [];
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT COALESCE(NULLIF(c.$column,''),'Khác') AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY value DESC, label LIMIT 10", $params);
    }

    public function quickSearch(array $filters = []): array
    {
        $query = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));
        if ($query === '') return ['items' => [], 'total' => 0];
        $limit = min(12, max(3, (int) ($filters['limit'] ?? 8)));
        $like = '%' . $query . '%';
        $items = [];

        $households = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone
             FROM households h
             WHERE ' . $this->activeHouseholdCondition('h') . ' AND (h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR h.address LIKE :q OR h.phone LIKE :q)
             ORDER BY h.household_code ASC LIMIT ' . $limit,
            ['q' => $like]
        );
        foreach ($households as $row) {
            $items[] = [
                'type' => 'household',
                'id' => (int) $row['id'],
                'title' => $row['head_citizen_name'] ?: ($row['household_code'] ?? 'Hộ gia đình'),
                'subtitle' => trim(($row['household_code'] ?? '') . ' - ' . ($row['address'] ?? ''), ' -'),
                'phone' => $row['phone'] ?? '',
                'screen' => 'households',
            ];
        }

        $citizens = $this->fetchAll(
            'SELECT c.id, c.citizen_code, c.full_name, c.identity_number, c.phone, c.current_address, h.household_code, h.head_citizen_name
             FROM citizens c INNER JOIN households h ON h.id = c.household_id
             WHERE ' . $this->activeCitizenCondition('c') . ' AND ' . $this->activeHouseholdCondition('h') . ' AND (c.full_name LIKE :q OR c.identity_number LIKE :q OR c.citizen_code LIKE :q OR c.phone LIKE :q OR c.current_address LIKE :q OR h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR h.address LIKE :q)
             ORDER BY c.full_name ASC LIMIT ' . $limit,
            ['q' => $like]
        );
        foreach ($citizens as $row) {
            $items[] = [
                'type' => 'citizen',
                'id' => (int) $row['id'],
                'title' => $row['full_name'] ?: ($row['citizen_code'] ?? 'Nhân khẩu'),
                'subtitle' => trim(($row['identity_number'] ?? '') . ' - ' . ($row['household_code'] ?? '') . ' - ' . ($row['current_address'] ?? ''), ' -'),
                'phone' => $row['phone'] ?? '',
                'screen' => 'persons',
            ];
        }

        return ['items' => array_slice($items, 0, $limit), 'total' => count($items)];
    }

    private function alerts(array $filters): array
    {
        $items = [
            ['key' => 'missing_citizen_photo', 'label' => 'Hồ sơ chưa có ảnh', 'count' => $this->missingCitizenPhotoCount($filters), 'priority' => 'high', 'screen' => 'persons'],
            ['key' => 'missing_gps', 'label' => 'Hộ chưa định vị GPS', 'count' => $this->missingGpsCount($filters), 'priority' => 'high', 'screen' => 'gis'],
            ['key' => 'missing_identity', 'label' => 'Nhân khẩu thiếu CCCD', 'count' => $this->missingCitizenFieldCount($filters, 'identity_number'), 'priority' => 'medium', 'screen' => 'persons'],
            ['key' => 'missing_birthdate', 'label' => 'Nhân khẩu thiếu ngày sinh', 'count' => $this->missingCitizenFieldCount($filters, 'date_of_birth'), 'priority' => 'medium', 'screen' => 'persons'],
            ['key' => 'recent_movements', 'label' => 'Có biến động mới', 'count' => $this->movementCount($filters, 7), 'priority' => 'low', 'screen' => 'movements'],
            ['key' => 'incomplete_profiles', 'label' => 'Hồ sơ số chưa hoàn thiện', 'count' => $this->incompleteProfileCount($filters), 'priority' => 'medium', 'screen' => 'households'],
        ];
        if ($this->columnExists('citizens', 'identity_expiry_date')) {
            $items[] = ['key' => 'identity_expiring', 'label' => 'CCCD sắp hết hạn', 'count' => $this->identityExpiringCount($filters), 'priority' => 'medium', 'screen' => 'persons'];
        }
        return $items;
    }

    private function movementWindows(array $filters): array
    {
        return [
            'today' => ['label' => 'Hôm nay', 'items' => $this->movementTypeCounts($filters, 0)],
            'sevenDays' => ['label' => '7 ngày gần nhất', 'items' => $this->movementTypeCounts($filters, 7)],
            'thirtyDays' => ['label' => '30 ngày gần nhất', 'items' => $this->movementTypeCounts($filters, 30)],
        ];
    }

    private function gisSummary(array $filters): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $hasLat = $this->columnExists('households', 'latitude');
        $hasLng = $this->columnExists('households', 'longitude');
        $locatedExpr = ($hasLat && $hasLng) ? "h.latitude IS NOT NULL AND h.latitude <> '' AND h.longitude IS NOT NULL AND h.longitude <> ''" : '0=1';
        $row = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN $locatedExpr THEN 1 ELSE 0 END),0) AS located FROM households h $where", $params) ?: [];
        $total = (int) ($row['total'] ?? 0);
        $located = (int) ($row['located'] ?? 0);
        $areas = 0;
        if ($this->tableExists('gis_areas')) {
            $areaSql = $this->columnExists('gis_areas', 'status') ? 'SELECT COUNT(*) AS total FROM gis_areas WHERE status <> "DELETED"' : 'SELECT COUNT(*) AS total FROM gis_areas';
            $areas = (int) (($this->fetchOne($areaSql) ?: [])['total'] ?? 0);
        }
        return [
            'totalHouseholds' => $total,
            'locatedHouseholds' => $located,
            'unlocatedHouseholds' => max(0, $total - $located),
            'gpsPercent' => $total > 0 ? round($located * 100 / $total, 1) : 0,
            'totalAreas' => $areas,
            'activeMarkers' => $located,
            'heatmapReady' => $located > 0,
        ];
    }

    private function profileSummary(array $filters): array
    {
        [$householdWhere, $householdParams] = $this->householdWhere($filters);
        [$citizenWhere, $citizenParams] = $this->citizenWhere($filters);
        $citizenTotal = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id $citizenWhere", $citizenParams) ?: [])['total'] ?? 0);
        $householdTotal = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM households h $householdWhere", $householdParams) ?: [])['total'] ?? 0);
        $citizenWithPhoto = $this->entityFileCount('citizen', 'c.id', true, 'citizens c INNER JOIN households h ON h.id = c.household_id', $citizenWhere, $citizenParams);
        $citizenWithFiles = $this->entityFileCount('citizen', 'c.id', false, 'citizens c INNER JOIN households h ON h.id = c.household_id', $citizenWhere, $citizenParams);
        $householdWithFiles = $this->entityFileCount('household', 'h.id', false, 'households h', $householdWhere, $householdParams);
        $householdWithPhoto = $this->entityFileCount('household', 'h.id', true, 'households h', $householdWhere, $householdParams);
        return [
            'citizenComplete' => $this->progress($citizenWithPhoto, $citizenTotal),
            'citizenMissingPhoto' => max(0, $citizenTotal - $citizenWithPhoto),
            'citizenMissingDocuments' => max(0, $citizenTotal - $citizenWithFiles),
            'householdComplete' => $this->progress($householdWithFiles, $householdTotal),
            'householdMissingPhoto' => max(0, $householdTotal - $householdWithPhoto),
            'householdMissingDocuments' => max(0, $householdTotal - $householdWithFiles),
        ];
    }

    private function tasks(array $filters): array
    {
        return [
            ['label' => 'Hộ chưa định vị', 'count' => $this->missingGpsCount($filters), 'screen' => 'gis', 'action' => 'Mở GIS'],
            ['label' => 'Hồ sơ thiếu ảnh', 'count' => $this->missingCitizenPhotoCount($filters), 'screen' => 'persons', 'action' => 'Mở nhân khẩu'],
            ['label' => 'Hồ sơ thiếu GPS', 'count' => $this->missingGpsCount($filters), 'screen' => 'households', 'action' => 'Mở hộ'],
            ['label' => 'Biến động chưa xác nhận', 'count' => $this->pendingMovementCount(), 'screen' => 'movements', 'action' => 'Mở biến động'],
        ];
    }

    private function gpsProgressChart(array $filters): array
    {
        $gis = $this->gisSummary($filters);
        return [
            ['label' => 'Đã định vị', 'value' => $gis['locatedHouseholds']],
            ['label' => 'Chưa định vị', 'value' => $gis['unlocatedHouseholds']],
        ];
    }

    private function profileProgressChart(array $filters): array
    {
        $profiles = $this->profileSummary($filters);
        return [
            ['label' => 'Hồ sơ công dân hoàn chỉnh', 'value' => (int) round($profiles['citizenComplete']['percent'] ?? 0)],
            ['label' => 'Hồ sơ hộ hoàn chỉnh', 'value' => (int) round($profiles['householdComplete']['percent'] ?? 0)],
        ];
    }

    private function missingGpsCount(array $filters): int
    {
        [$where, $params] = $this->householdWhere($filters);
        if (!$this->columnExists('households', 'latitude') || !$this->columnExists('households', 'longitude')) {
            return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM households h $where", $params) ?: [])['total'] ?? 0);
        }
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM households h $where AND (h.latitude IS NULL OR h.latitude = '' OR h.longitude IS NULL OR h.longitude = '')", $params) ?: [])['total'] ?? 0);
    }

    private function missingCitizenFieldCount(array $filters, string $column): int
    {
        if (!$this->columnExists('citizens', $column)) return 0;
        [$where, $params] = $this->citizenWhere($filters);
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id $where AND (c.$column IS NULL OR c.$column = '' OR c.$column = '0')", $params) ?: [])['total'] ?? 0);
    }

    private function missingCitizenPhotoCount(array $filters): int
    {
        [$where, $params] = $this->citizenWhere($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id $where", $params) ?: [])['total'] ?? 0);
        $withPhoto = $this->entityFileCount('citizen', 'c.id', true, 'citizens c INNER JOIN households h ON h.id = c.household_id', $where, $params);
        return max(0, $total - $withPhoto);
    }

    private function incompleteProfileCount(array $filters): int
    {
        $profiles = $this->profileSummary($filters);
        return (int) ($profiles['citizenMissingPhoto'] + $profiles['householdMissingDocuments']);
    }

    private function movementCount(array $filters, int $days): int
    {
        [$condition, $params] = $this->movementWindowCondition($days);
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM movements m WHERE m.status <> 'DELETED' AND $condition", $params) ?: [])['total'] ?? 0);
    }

    private function movementTypeCounts(array $filters, int $days): array
    {
        [$condition, $params] = $this->movementWindowCondition($days);
        $rows = $this->fetchAll("SELECT m.type, COUNT(*) AS value FROM movements m WHERE m.status <> 'DELETED' AND $condition GROUP BY m.type", $params);
        $map = ['BIRTH' => 0, 'MOVE_IN' => 0, 'MOVE_OUT' => 0, 'DEATH' => 0, 'TEMPORARY_RESIDENCE' => 0, 'TEMPORARY_ABSENCE' => 0];
        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            if (array_key_exists($type, $map)) $map[$type] = (int) $row['value'];
        }
        return [
            ['key' => 'BIRTH', 'label' => 'Sinh mới', 'value' => $map['BIRTH']],
            ['key' => 'MOVE_IN', 'label' => 'Chuyển đến', 'value' => $map['MOVE_IN']],
            ['key' => 'MOVE_OUT', 'label' => 'Chuyển đi', 'value' => $map['MOVE_OUT']],
            ['key' => 'DEATH', 'label' => 'Qua đời', 'value' => $map['DEATH']],
            ['key' => 'TEMPORARY_RESIDENCE', 'label' => 'Tạm trú', 'value' => $map['TEMPORARY_RESIDENCE']],
            ['key' => 'TEMPORARY_ABSENCE', 'label' => 'Tạm vắng', 'value' => $map['TEMPORARY_ABSENCE']],
        ];
    }

    private function movementWindowCondition(int $days): array
    {
        if ($days <= 0) return ['DATE(m.effective_date) = CURDATE()', []];
        return ['DATE(m.effective_date) >= DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY)', []];
    }

    private function pendingMovementCount(): int
    {
        if (!$this->tableExists('movements')) return 0;
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM movements WHERE status IN ('PENDING','DRAFT')") ?: [])['total'] ?? 0);
    }

    private function identityExpiringCount(array $filters): int
    {
        [$where, $params] = $this->citizenWhere($filters);
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id $where AND c.identity_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)", $params) ?: [])['total'] ?? 0);
    }

    private function entityFileCount(string $module, string $idExpr, bool $imageOnly, string $fromSql, string $entityWhere, array $entityParams): int
    {
        if (!$this->tableExists('file_attachments')) return 0;
        $columns = $this->existingColumns('file_attachments', ['id', 'module', 'entity_type', 'entity_id', 'status', 'file_type', 'mime_type']);
        if (!in_array('id', $columns, true) || !in_array('entity_id', $columns, true)) return 0;
        $where = ["f.entity_id = $idExpr"];
        $usesFileModuleParam = false;
        if (in_array('entity_type', $columns, true) && in_array('module', $columns, true)) {
            $where[] = 'COALESCE(f.entity_type, f.module) = :file_module';
            $usesFileModuleParam = true;
        } elseif (in_array('entity_type', $columns, true)) {
            $where[] = 'f.entity_type = :file_module';
            $usesFileModuleParam = true;
        } elseif (in_array('module', $columns, true)) {
            $where[] = 'f.module = :file_module';
            $usesFileModuleParam = true;
        }
        if (in_array('status', $columns, true)) $where[] = 'f.status = "ACTIVE"';
        if ($imageOnly) {
            $image = [];
            if (in_array('file_type', $columns, true)) $image[] = 'f.file_type IN ("PHOTO","IMAGE")';
            if (in_array('mime_type', $columns, true)) $image[] = 'f.mime_type LIKE "image/%"';
            if ($image) $where[] = '(' . implode(' OR ', $image) . ')';
        }
        $params = $entityParams;
        if ($usesFileModuleParam) $params['file_module'] = $module;
        return (int) (($this->fetchOne("SELECT COUNT(DISTINCT $idExpr) AS total FROM $fromSql $entityWhere AND EXISTS (SELECT 1 FROM file_attachments f WHERE " . implode(' AND ', $where) . ')', $params) ?: [])['total'] ?? 0);
    }

    private function progress(int $done, int $total): array
    {
        return ['done' => $done, 'total' => $total, 'percent' => $total > 0 ? round($done * 100 / $total, 1) : 0];
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
        $where = [$this->activeHouseholdCondition('h')];
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
        $where = [$this->activeCitizenCondition('c'), $this->activeHouseholdCondition('h')];
        $params = [];
        if ($filters['householdStatus']) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        if ($filters['residencyStatus']) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if ($filters['presenceStatus']) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if ($filters['dateFrom']) { $where[] = 'DATE(c.created_at) >= :citizen_date_from'; $params['citizen_date_from'] = $filters['dateFrom']; }
        if ($filters['dateTo']) { $where[] = 'DATE(c.created_at) <= :citizen_date_to'; $params['citizen_date_to'] = $filters['dateTo']; }
        $category = $this->categoryKey($filters['householdType']);
        if ($category) $this->addCategoryWhere($where, $params, $category);
        foreach (['has_health_insurance','party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'] as $column) {
            $value = $rawFilters[$column] ?? $rawFilters[$this->camel($column)] ?? null;
            if ($value !== null && $value !== '' && $this->columnExists('citizens', $column)) { $where[] = 'c.' . $column . ' = :' . $column; $params[$column] = (int) $value; }
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function activeHouseholdCondition(string $alias): string
    {
        return $alias . ".status NOT IN ('DELETED','ENDED','MERGED','TRANSFERRED_OUT','MOVED_OUT','INACTIVE')";
    }

    private function activeCitizenCondition(string $alias): string
    {
        return $alias . ".status <> 'DELETED' AND COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED' AND COALESCE(" . $alias . ".residency_status,'PERMANENT') <> 'TRANSFERRED_OUT'";
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
        $columns = ['has_health_insurance','party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'];
        $parts = [];
        foreach ($columns as $column) $parts[] = ', COALESCE(' . ($this->columnExists('citizens', $column) ? "SUM(CASE WHEN $alias.$column=1 THEN 1 ELSE 0 END)" : '0') . ",0) AS $column";
        return implode('', $parts);
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
}
