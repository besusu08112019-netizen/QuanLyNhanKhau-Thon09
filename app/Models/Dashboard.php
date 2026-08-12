<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Policies\AgePolicy;
use App\Core\TenantConfig;
use App\Services\HouseholdCategoryService;
use App\Services\StudentStatusService;

final class Dashboard extends BaseModel
{
    private ?PopulationStatistics $statistics = null;
    private ?HouseholdCategoryService $categoryService = null;

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
            'partyMembers' => $this->safeWidget('charts.partyMembers', fn() => $this->flagChart($filters, 'party_member', 'Ã„ÂÃ¡ÂºÂ£ng viÃƒÂªn'), [], $errors),
            'youthUnion' => $this->safeWidget('charts.youthUnion', fn() => $this->flagChart($filters, 'youth_union_member', 'Ã„ÂoÃƒÂ n viÃƒÂªn'), [], $errors),
            'labor' => $this->safeWidget('charts.labor', fn() => $this->laborChart($filters), [], $errors),
            'occupations' => $this->safeWidget('charts.occupations', fn() => $this->groupChart($filters, 'occupation', 'NghÃ¡Â»Â nghiÃ¡Â»â€¡p'), [], $errors),
            'educationLevels' => $this->safeWidget('charts.educationLevels', fn() => $this->groupChart($filters, 'education_level', 'TrÃƒÂ¬nh Ã„â€˜Ã¡Â»â„¢ hÃ¡Â»Âc vÃ¡ÂºÂ¥n'), [], $errors),
            'ethnicities' => $this->safeWidget('charts.ethnicities', fn() => $this->groupChart($filters, 'ethnicity', 'DÃƒÂ¢n tÃ¡Â»â„¢c'), [], $errors),
            'religions' => $this->safeWidget('charts.religions', fn() => $this->groupChart($filters, 'religion', 'TÃƒÂ´n giÃƒÂ¡o'), [], $errors),
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
            $debug = $this->debugEnabled();
            $errors[$name] = [
                'type' => $debug ? get_class($exception) : 'WidgetError',
                'message' => $debug ? $exception->getMessage() : json_decode('"Kh\u00f4ng t\u1ea3i \u0111\u01b0\u1ee3c d\u1eef li\u1ec7u th\u1ed1ng k\u00ea"', true),
            ];
            error_log('[DASHBOARD_WIDGET_ERROR] ' . json_encode([
                'widget' => $name,
                'type' => get_class($exception),
                'debug' => $debug,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $fallback;
        }
    }

    private function debugEnabled(): bool
    {
        return filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
    }

    private function defaultMetrics(): array
    {
        $metrics = [
            'total_households' => 0,
            'resident_households' => 0,
            'away_for_work_households' => 0,
            'settled_elsewhere_households' => 0,
            'outside_households' => 0,
            'partial_households' => 0,
            'inactive_residence_households' => 0,
            'actual_resident_households' => 0,
            'total_citizens' => 0,
            'male_count' => 0,
            'female_count' => 0,
            'household_head_count' => 0,
            'active_citizens' => 0,
            'children_count' => 0,
            'elderly_count' => 0,
            'working_age_count' => 0,
            'temporary_residence_count' => 0,
            'temporary_absence_count' => 0,
            'temporary_count' => 0,
            'away_count' => 0,
            'poor_households' => 0,
            'near_poor_households' => 0,
            'medium_households' => 0,
            'ho_ngheo' => 0,
            'ho_can_ngheo' => 0,
            'ho_trung_binh' => 0,
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
            'elderly_health_insurance_count' => 0,
            'elderly_social_assistance_count' => 0,
            'production_households' => 0,
            'business_households' => 0,
            'production_business_households' => 0,
            'business_worker_total' => 0,
        ];
        foreach (self::CITIZEN_FLAG_COLUMNS as $key) {
            $metrics[$key . '_count'] = 0;
            $metrics[$key . '_percent'] = 0;
        }
        $metrics['poor_households_percent'] = 0;
        $metrics['near_poor_households_percent'] = 0;
        $metrics['medium_households_percent'] = 0;
        $metrics['children_percent'] = 0;
        $metrics['elderly_percent'] = 0;
        $metrics['working_age_percent'] = 0;
        return $metrics;
    }
    public function metrics(array $filters = []): array
    {
        return $this->statistics()->metrics($filters);
    }

    public function healthInsuranceStats(array $filters = []): array
    {
        return $this->statistics()->healthInsuranceStats($filters);
    }

    public function healthInsuranceChart(array $filters = []): array
    {
        $stats = $this->healthInsuranceStats($filters);
        return [
            ['label' => 'CÃƒÂ³ BHYT', 'value' => $stats['insured']],
            ['label' => 'ChÃ†Â°a cÃƒÂ³ BHYT', 'value' => $stats['uninsured']],
        ];
    }

    public function populationChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT COALESCE(NULLIF(c.gender,''),'KhÃƒÂ¡c') AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY label", $params);
    }

    public function householdChart(array $filters = []): array
    {
        return $this->povertyChart($filters);
    }

    public function ageChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $ageSql = AgePolicy::ageSql('c');
        return $this->fetchAll("SELECT CASE WHEN $ageSql <= " . AgePolicy::AGE_BAND_0_5_MAX . " THEN '0-5 tuÃ¡Â»â€¢i' WHEN $ageSql BETWEEN " . AgePolicy::AGE_BAND_6_14_MIN . ' AND ' . AgePolicy::AGE_BAND_6_14_MAX . " THEN '6-14 tuÃ¡Â»â€¢i' WHEN $ageSql BETWEEN " . AgePolicy::AGE_BAND_15_17_MIN . ' AND ' . AgePolicy::AGE_BAND_15_17_MAX . " THEN '15-17 tuÃ¡Â»â€¢i' WHEN $ageSql BETWEEN " . AgePolicy::AGE_BAND_18_59_MIN . ' AND ' . AgePolicy::AGE_BAND_18_59_MAX . " THEN '18-59 tuÃ¡Â»â€¢i' ELSE 'TÃ¡Â»Â« 60 tuÃ¡Â»â€¢i trÃ¡Â»Å¸ lÃƒÂªn' END AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY MIN($ageSql)", $params);
    }

    public function residencyChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT CASE c.residency_status WHEN 'TEMPORARY' THEN 'TÃ¡ÂºÂ¡m trÃƒÂº' ELSE 'ThÃ†Â°Ã¡Â»Âng trÃƒÂº' END AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY c.residency_status ORDER BY c.residency_status", $params);
    }

    public function hamletChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $params['default_area_label'] = TenantConfig::setting('hamletName', 'Khu vuc chua phan loai');
        return $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),:default_area_label) AS label, COUNT(c.id) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY label", $params);
    }

    public function monthlyChangeChart(array $filters = []): array
    {
        $rows = $this->fetchAll("SELECT DATE_FORMAT(effective_date, '%Y-%m') AS label, SUM(CASE WHEN type IN ('BIRTH','MOVE_IN','TEMPORARY_RESIDENCE') THEN 1 WHEN type IN ('DEATH','MOVE_OUT','TEMPORARY_ABSENCE') THEN -1 ELSE 0 END) AS value FROM movements WHERE status <> 'DELETED' AND " . $this->tenantLiteral('movements') . " AND effective_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY label ORDER BY label");
        return array_map(fn($row) => ['label' => $row['label'], 'value' => (int) $row['value']], $rows);
    }

    public function povertyChart(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $categoryCounts = $this->categoryService()->countsSelect('h');
        $row = $this->fetchOne("SELECT $categoryCounts FROM households h $where", $params) ?: [];
        return [
            ['label' => HouseholdCategoryService::LABELS[HouseholdCategoryService::POOR], 'value' => (int) ($row['poor_households'] ?? 0)],
            ['label' => HouseholdCategoryService::LABELS[HouseholdCategoryService::NEAR_POOR], 'value' => (int) ($row['near_poor_households'] ?? 0)],
            ['label' => HouseholdCategoryService::LABELS[HouseholdCategoryService::MEDIUM], 'value' => (int) ($row['medium_households'] ?? 0)],
            ['label' => HouseholdCategoryService::LABELS[HouseholdCategoryService::POLICY], 'value' => (int) ($row['policy_households'] ?? 0)],
            ['label' => HouseholdCategoryService::LABELS[HouseholdCategoryService::NORMAL], 'value' => (int) ($row['normal_households'] ?? 0)],
        ];
    }

    public function flagChart(array $filters, string $column, string $label): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        if (!$this->columnExists('citizens', $column)) return [['label' => $label, 'value' => 0], ['label' => 'CÃƒÂ²n lÃ¡ÂºÂ¡i', 'value' => 0]];
        $row = $this->fetchOne("SELECT SUM(c.$column=1) AS yes_count, SUM(c.$column=0 OR c.$column IS NULL) AS no_count FROM citizens c INNER JOIN households h ON h.id = c.household_id $where", $params) ?: [];
        return [['label' => $label, 'value' => (int) ($row['yes_count'] ?? 0)], ['label' => 'CÃƒÂ²n lÃ¡ÂºÂ¡i', 'value' => (int) ($row['no_count'] ?? 0)]];
    }

    public function laborChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $columns = ['employed','unemployed','not_attending_school','student','retired'];
        $selects = ['c.occupation'];
        foreach ($columns as $column) {
            $selects[] = ($this->columnExists('citizens', $column) ? "c.$column" : "0") . " AS $column";
        }
        $selects[] = 'CASE WHEN ' . StudentStatusService::studentSql('c') . ' THEN 1 ELSE 0 END AS pupil';

        $rows = $this->fetchAll('SELECT ' . implode(',', $selects) . " FROM citizens c INNER JOIN households h ON h.id = c.household_id $where", $params);
        $groups = [
            'CÃƒÂ³ viÃ¡Â»â€¡c lÃƒÂ m' => 0,
            'ChÃ†Â°a cÃƒÂ³ viÃ¡Â»â€¡c lÃƒÂ m' => 0,
            'HÃ¡Â»Âc sinh' => 0,
            'Sinh viÃƒÂªn' => 0,
            'NghÃ¡Â»â€° hÃ†Â°u' => 0,
            'KhÃƒÂ¡c' => 0,
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
        if ((int) ($row['not_attending_school'] ?? 0) === 1 || str_contains($occupation, 'chua di hoc')) return 'ChÃ†Â°a Ã„â€˜i hÃ¡Â»Âc';
        if ((int) ($row['pupil'] ?? 0) === 1) return 'HÃ¡Â»Âc sinh';
        if ((int) ($row['student'] ?? 0) === 1 || str_contains($occupation, 'sinh vien')) return 'Sinh viÃƒÂªn';
        if ((int) ($row['retired'] ?? 0) === 1 || str_contains($occupation, 'nghi huu') || str_contains($occupation, 'huu tri')) return 'NghÃ¡Â»â€° hÃ†Â°u';
        if ((int) ($row['unemployed'] ?? 0) === 1 || str_contains($occupation, 'that nghiep') || str_contains($occupation, 'chua co viec') || str_contains($occupation, 'khong co viec')) return 'ChÃ†Â°a cÃƒÂ³ viÃ¡Â»â€¡c lÃƒÂ m';
        if ((int) ($row['employed'] ?? 0) === 1) return 'CÃƒÂ³ viÃ¡Â»â€¡c lÃƒÂ m';
        if ($occupation === '' || str_contains($occupation, 'khac') || str_contains($occupation, 'noi tro')) return 'KhÃƒÂ¡c';
        return 'CÃƒÂ³ viÃ¡Â»â€¡c lÃƒÂ m';
    }

    public function groupChart(array $filters, string $column, string $fallbackLabel): array
    {
        if (!in_array($column, ['occupation','education_level','ethnicity','religion'], true)) return [];
        if (!$this->columnExists('citizens', $column)) return [];
        [$where, $params] = $this->citizenWhere($filters);
        $labelSql = "COALESCE(NULLIF(c.$column,''),'KhÃƒÂ¡c')";
        if (in_array($column, ['occupation', 'education_level'], true)) {
            $studentSql = StudentStatusService::studentSql('c');
            $labelSql = "CASE WHEN $studentSql THEN 'HÃ¡Â»Âc sinh' WHEN LOWER(COALESCE(c.$column,'')) LIKE '%hÃ¡Â»Âc sinh%' THEN 'KhÃƒÂ¡c' ELSE COALESCE(NULLIF(c.$column,''),'KhÃƒÂ¡c') END";
        }
        return $this->fetchAll("SELECT $labelSql AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY value DESC, label LIMIT 10", $params);
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
                'title' => $row['head_citizen_name'] ?: ($row['household_code'] ?? 'HÃ¡Â»â„¢ gia Ã„â€˜ÃƒÂ¬nh'),
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
                'title' => $row['full_name'] ?: ($row['citizen_code'] ?? 'NhÃƒÂ¢n khÃ¡ÂºÂ©u'),
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
            ['key' => 'missing_citizen_photo', 'label' => 'HÃ¡Â»â€œ sÃ†Â¡ chÃ†Â°a cÃƒÂ³ Ã¡ÂºÂ£nh', 'count' => $this->missingCitizenPhotoCount($filters), 'priority' => 'high', 'screen' => 'persons'],
            ['key' => 'missing_gps', 'label' => 'HÃ¡Â»â„¢ chÃ†Â°a Ã„â€˜Ã¡Â»â€¹nh vÃ¡Â»â€¹ GPS', 'count' => $this->missingGpsCount($filters), 'priority' => 'high', 'screen' => 'gis'],
            ['key' => 'missing_identity', 'label' => 'NhÃƒÂ¢n khÃ¡ÂºÂ©u thiÃ¡ÂºÂ¿u CCCD', 'count' => $this->missingCitizenFieldCount($filters, 'identity_number'), 'priority' => 'medium', 'screen' => 'persons'],
            ['key' => 'missing_birthdate', 'label' => 'NhÃƒÂ¢n khÃ¡ÂºÂ©u thiÃ¡ÂºÂ¿u ngÃƒÂ y sinh', 'count' => $this->missingCitizenFieldCount($filters, 'date_of_birth'), 'priority' => 'medium', 'screen' => 'persons'],
            ['key' => 'recent_movements', 'label' => 'CÃƒÂ³ biÃ¡ÂºÂ¿n Ã„â€˜Ã¡Â»â„¢ng mÃ¡Â»â€ºi', 'count' => $this->movementCount($filters, 7), 'priority' => 'low', 'screen' => 'movements'],
            ['key' => 'incomplete_profiles', 'label' => 'HÃ¡Â»â€œ sÃ†Â¡ sÃ¡Â»â€˜ chÃ†Â°a hoÃƒÂ n thiÃ¡Â»â€¡n', 'count' => $this->incompleteProfileCount($filters), 'priority' => 'medium', 'screen' => 'households'],
        ];
        if ($this->columnExists('citizens', 'identity_expiry_date')) {
            $items[] = ['key' => 'identity_expiring', 'label' => 'CCCD sÃ¡ÂºÂ¯p hÃ¡ÂºÂ¿t hÃ¡ÂºÂ¡n', 'count' => $this->identityExpiringCount($filters), 'priority' => 'medium', 'screen' => 'persons'];
        }
        return $items;
    }

    private function movementWindows(array $filters): array
    {
        return [
            'today' => ['label' => 'HÃƒÂ´m nay', 'items' => $this->movementTypeCounts($filters, 0)],
            'sevenDays' => ['label' => '7 ngÃƒÂ y gÃ¡ÂºÂ§n nhÃ¡ÂºÂ¥t', 'items' => $this->movementTypeCounts($filters, 7)],
            'thirtyDays' => ['label' => '30 ngÃƒÂ y gÃ¡ÂºÂ§n nhÃ¡ÂºÂ¥t', 'items' => $this->movementTypeCounts($filters, 30)],
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
            $areaSql = $this->columnExists('gis_areas', 'status') ? 'SELECT COUNT(*) AS total FROM gis_areas WHERE status <> "DELETED" AND ' . $this->tenantLiteral('gis_areas') : 'SELECT COUNT(*) AS total FROM gis_areas WHERE ' . $this->tenantLiteral('gis_areas');
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
            ['label' => 'HÃ¡Â»â„¢ chÃ†Â°a Ã„â€˜Ã¡Â»â€¹nh vÃ¡Â»â€¹', 'count' => $this->missingGpsCount($filters), 'screen' => 'gis', 'action' => 'MÃ¡Â»Å¸ GIS'],
            ['label' => 'HÃ¡Â»â€œ sÃ†Â¡ thiÃ¡ÂºÂ¿u Ã¡ÂºÂ£nh', 'count' => $this->missingCitizenPhotoCount($filters), 'screen' => 'persons', 'action' => 'MÃ¡Â»Å¸ nhÃƒÂ¢n khÃ¡ÂºÂ©u'],
            ['label' => 'HÃ¡Â»â€œ sÃ†Â¡ thiÃ¡ÂºÂ¿u GPS', 'count' => $this->missingGpsCount($filters), 'screen' => 'households', 'action' => 'MÃ¡Â»Å¸ hÃ¡Â»â„¢'],
            ['label' => 'BiÃ¡ÂºÂ¿n Ã„â€˜Ã¡Â»â„¢ng chÃ†Â°a xÃƒÂ¡c nhÃ¡ÂºÂ­n', 'count' => $this->pendingMovementCount(), 'screen' => 'movements', 'action' => 'MÃ¡Â»Å¸ biÃ¡ÂºÂ¿n Ã„â€˜Ã¡Â»â„¢ng'],
        ];
    }

    private function gpsProgressChart(array $filters): array
    {
        $gis = $this->gisSummary($filters);
        return [
            ['label' => 'Ã„ÂÃƒÂ£ Ã„â€˜Ã¡Â»â€¹nh vÃ¡Â»â€¹', 'value' => $gis['locatedHouseholds']],
            ['label' => 'ChÃ†Â°a Ã„â€˜Ã¡Â»â€¹nh vÃ¡Â»â€¹', 'value' => $gis['unlocatedHouseholds']],
        ];
    }

    private function profileProgressChart(array $filters): array
    {
        $profiles = $this->profileSummary($filters);
        return [
            ['label' => 'HÃ¡Â»â€œ sÃ†Â¡ cÃƒÂ´ng dÃƒÂ¢n hoÃƒÂ n chÃ¡Â»â€°nh', 'value' => (int) round($profiles['citizenComplete']['percent'] ?? 0)],
            ['label' => 'HÃ¡Â»â€œ sÃ†Â¡ hÃ¡Â»â„¢ hoÃƒÂ n chÃ¡Â»â€°nh', 'value' => (int) round($profiles['householdComplete']['percent'] ?? 0)],
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
        $value = "TRIM(COALESCE(CAST(c.$column AS CHAR), ''))";
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id $where AND ($value = '' OR $value = '0' OR $value = '0000-00-00')", $params) ?: [])['total'] ?? 0);
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
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM movements m WHERE m.status <> 'DELETED' AND " . $this->tenantLiteral('movements', 'm') . " AND $condition", $params) ?: [])['total'] ?? 0);
    }

    private function movementTypeCounts(array $filters, int $days): array
    {
        [$condition, $params] = $this->movementWindowCondition($days);
        $rows = $this->fetchAll("SELECT m.type, COUNT(*) AS value FROM movements m WHERE m.status <> 'DELETED' AND " . $this->tenantLiteral('movements', 'm') . " AND $condition GROUP BY m.type", $params);
        $map = ['BIRTH' => 0, 'MOVE_IN' => 0, 'MOVE_OUT' => 0, 'DEATH' => 0, 'TEMPORARY_RESIDENCE' => 0, 'TEMPORARY_ABSENCE' => 0];
        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            if (array_key_exists($type, $map)) $map[$type] = (int) $row['value'];
        }
        return [
            ['key' => 'BIRTH', 'label' => 'Sinh mÃ¡Â»â€ºi', 'value' => $map['BIRTH']],
            ['key' => 'MOVE_IN', 'label' => 'ChuyÃ¡Â»Æ’n Ã„â€˜Ã¡ÂºÂ¿n', 'value' => $map['MOVE_IN']],
            ['key' => 'MOVE_OUT', 'label' => 'ChuyÃ¡Â»Æ’n Ã„â€˜i', 'value' => $map['MOVE_OUT']],
            ['key' => 'DEATH', 'label' => 'Qua Ã„â€˜Ã¡Â»Âi', 'value' => $map['DEATH']],
            ['key' => 'TEMPORARY_RESIDENCE', 'label' => 'TÃ¡ÂºÂ¡m trÃƒÂº', 'value' => $map['TEMPORARY_RESIDENCE']],
            ['key' => 'TEMPORARY_ABSENCE', 'label' => 'TÃ¡ÂºÂ¡m vÃ¡ÂºÂ¯ng', 'value' => $map['TEMPORARY_ABSENCE']],
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
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM movements WHERE status IN ('PENDING','DRAFT') AND " . $this->tenantLiteral('movements')) ?: [])['total'] ?? 0);
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
        $where[] = $this->tenantLiteral('file_attachments', 'f');
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

    public function overviewDashboard(array $filters = []): array
    {
        $errors = [];
        $metrics = $this->safeWidget('overview.metrics', fn() => $this->metrics($filters), $this->defaultMetrics(), $errors);
        $business = $this->safeWidget('overview.business', fn() => (new \App\Models\HouseholdBusiness())->dashboard($filters), [], $errors);
        $vehicles = $this->safeWidget('overview.vehicles', fn() => (new \App\Models\Vehicle())->dashboard($filters), [], $errors);
        $livestock = $this->safeWidget('overview.livestock', fn() => (new \App\Models\Livestock())->dashboard($filters), [], $errors);
        $gis = $this->safeWidget('overview.gis', fn() => $this->gisSummary($filters), [], $errors);
        $businessCharts = $this->safeWidget('overview.businessCharts', fn() => (new \App\Models\HouseholdBusiness())->charts($filters), [], $errors);
        $vehicleCharts = $this->safeWidget('overview.vehicleCharts', fn() => (new \App\Models\Vehicle())->charts($filters), [], $errors);
        $livestockCharts = $this->safeWidget('overview.livestockCharts', fn() => (new \App\Models\Livestock())->charts($filters), [], $errors);
        $payload = ['module' => 'overview', 'title' => 'Dashboard TÃ¡Â»â€¢ng quan', 'kpis' => [
            $this->kpi('TÃ¡Â»â€¢ng sÃ¡Â»â€˜ hÃ¡Â»â„¢', $metrics['total_households'] ?? 0, 'hÃ¡Â»â„¢', 'fa-house-chimney', 'green'),
            $this->kpi('TÃ¡Â»â€¢ng sÃ¡Â»â€˜ nhÃƒÂ¢n khÃ¡ÂºÂ©u', $metrics['total_citizens'] ?? 0, 'ngÃ†Â°Ã¡Â»Âi', 'fa-users', 'blue'),
            $this->kpi('TÃ¡Â»â€¢ng sÃ¡Â»â€˜ hÃ¡Â»â„¢ kinh doanh', $business['economic_households'] ?? 0, 'hÃ¡Â»â„¢', 'fa-store', 'orange'),
            $this->kpi('TÃ¡Â»â€¢ng sÃ¡Â»â€˜ cÃ†Â¡ sÃ¡Â»Å¸ kinh doanh', $business['establishment_total'] ?? 0, 'cÃ†Â¡ sÃ¡Â»Å¸', 'fa-briefcase', 'cyan'),
            $this->kpi('TÃ¡Â»â€¢ng sÃ¡Â»â€˜ phÃ†Â°Ã†Â¡ng tiÃ¡Â»â€¡n', $vehicles['total'] ?? 0, 'xe', 'fa-car', 'purple'),
            $this->kpi('TÃ¡Â»â€¢ng sÃ¡Â»â€˜ hÃ¡Â»â„¢ chÃ„Æ’n nuÃƒÂ´i', $livestock['livestock_households'] ?? 0, 'hÃ¡Â»â„¢', 'fa-warehouse', 'green'),
            $this->kpi('TÃ¡Â»â€¢ng sÃ¡Â»â€˜ vÃ¡ÂºÂ­t nuÃƒÂ´i', $livestock['livestock_total'] ?? 0, 'con', 'fa-paw', 'orange'),
            $this->kpi('TÃ¡Â»â€¢ng khu vÃ¡Â»Â±c GIS', $gis['totalAreas'] ?? 0, 'khu', 'fa-map-location-dot', 'cyan'),
            $this->kpi('TÃ¡Â»Â· lÃ¡Â»â€¡ BHYT', $metrics['health_insurance_coverage_percent'] ?? 0, '%', 'fa-notes-medical', 'green'),
            $this->kpi('Ã„ÂÃ¡ÂºÂ£ng viÃƒÂªn', $metrics['party_member_count'] ?? 0, 'ngÃ†Â°Ã¡Â»Âi', 'fa-star', 'orange'),
        ], 'charts' => [
            'households' => $this->safeWidget('overview.households', fn() => $this->householdChart($filters), [], $errors),
            'businessSectors' => $businessCharts['sectors'] ?? [],
            'vehicles' => $vehicleCharts['types'] ?? [], 'livestock' => $livestockCharts['types'] ?? [],
        ], 'generatedAt' => date('c')];
        if ($errors) $payload['widgetErrors'] = $errors;
        return $payload;
    }

    public function householdDashboard(array $filters = []): array
    {
        $errors = [];
        $m = $this->safeWidget('households.metrics', fn() => $this->metrics($filters), $this->defaultMetrics(), $errors);
        $charts = [
            'households' => $this->safeWidget('households.chart', fn() => $this->householdChart($filters), [], $errors),
            'gps' => $this->safeWidget('households.gps', fn() => $this->gpsProgressChart($filters), [], $errors),
            'profiles' => $this->safeWidget('households.profiles', fn() => $this->profileProgressChart($filters), [], $errors),
        ];
        $top = $this->safeWidget('households.tasks', fn() => $this->tasks($filters), [], $errors);
        return ['module'=>'households','title'=>'Dashboard HÃ¡Â»â„¢ dÃƒÂ¢n','kpis'=>[
            $this->kpi('TÃ¡Â»â€¢ng sÃ¡Â»â€˜ hÃ¡Â»â„¢',$m['total_households']??0,'hÃ¡Â»â„¢','fa-house-chimney','green'),
            $this->kpi('HÃ¡Â»â„¢ nghÃƒÂ¨o',$m['poor_households']??0,'hÃ¡Â»â„¢','fa-hand-holding-heart','orange'),
            $this->kpi('HÃ¡Â»â„¢ cÃ¡ÂºÂ­n nghÃƒÂ¨o',$m['near_poor_households']??0,'hÃ¡Â»â„¢','fa-hands-holding','pink'),
            $this->kpi('HÃ¡Â»â„¢ trung bÃƒÂ¬nh',$m['medium_households']??0,'hÃ¡Â»â„¢','fa-house-circle-check','cyan'),
            $this->kpi('HÃ¡Â»â„¢ chÃƒÂ­nh sÃƒÂ¡ch',$m['policy_households']??0,'hÃ¡Â»â„¢','fa-award','purple'),
            $this->kpi('HÃ¡Â»â„¢ cÃƒÂ³ cÃƒÂ´ng',$m['meritorious_households']??0,'hÃ¡Â»â„¢','fa-medal','blue'),
        ],'charts'=>$charts,'top'=>$top,'widgetErrors'=>$errors,'generatedAt'=>date('c')];
    }

    public function populationDashboard(array $filters = []): array
    {
        $m = $this->metrics($filters);
        return ['module'=>'population','title'=>'Dashboard NhÃƒÂ¢n khÃ¡ÂºÂ©u','kpis'=>[
            $this->kpi('TÃ¡Â»â€¢ng nhÃƒÂ¢n khÃ¡ÂºÂ©u',$m['total_citizens']??0,'ngÃ†Â°Ã¡Â»Âi','fa-users','blue'),
            $this->kpi('Nam',$m['male_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-mars','cyan'),
            $this->kpi('NÃ¡Â»Â¯',$m['female_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-venus','pink'),
            $this->kpi('TrÃ¡ÂºÂ» em',$m['children_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-child-reaching','green'),
            $this->kpi('NgÃ†Â°Ã¡Â»Âi cao tuÃ¡Â»â€¢i',$m['elderly_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-person-cane','purple'),
            $this->kpi('TÃ¡ÂºÂ¡m trÃƒÂº',$m['temporary_residence_count']??$m['temporary_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-location-dot','orange'),
            $this->kpi('TÃ¡ÂºÂ¡m vÃ¡ÂºÂ¯ng',$m['temporary_absence_count']??$m['away_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-person-walking-arrow-right','pink'),
            $this->kpi('BHYT',$m['health_insurance_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-notes-medical','green'),
            $this->kpi('70+ cÃƒÂ³ BHYT',$m['elderly_health_insurance_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-shield-heart','green'),
            $this->kpi('75+ hÃ†Â°Ã¡Â»Å¸ng BTXH',$m['elderly_social_assistance_count']??0,'ngÃ†Â°Ã¡Â»Âi','fa-hand-holding-heart','orange'),
        ],'charts'=>['gender'=>$this->populationChart($filters),'ages'=>$this->ageChart($filters),'labor'=>$this->laborChart($filters),'healthInsurance'=>$this->healthInsuranceChart($filters)],'generatedAt'=>date('c')];
    }

    public function businessDashboard(array $filters = []): array
    {
        $model = new \App\Models\HouseholdBusiness();
        $stats = $model->dashboard($filters);
        $charts = $model->charts($filters);
        return ['module'=>'business','title'=>'Dashboard Kinh doanh','kpis'=>[
            $this->kpi('TÃ¡Â»â€¢ng hÃ¡Â»â„¢ kinh doanh',$stats['economic_households']??0,'hÃ¡Â»â„¢','fa-house-user','green'),
            $this->kpi('TÃ¡Â»â€¢ng cÃ†Â¡ sÃ¡Â»Å¸ kinh doanh',$stats['establishment_total']??0,'cÃ†Â¡ sÃ¡Â»Å¸','fa-store','blue'),
            $this->kpi('HÃ¡Â»â„¢ cÃƒÂ³ giÃ¡ÂºÂ¥y phÃƒÂ©p',$this->businessDistinctCount($filters,'hb.business_license IS NOT NULL AND hb.business_license <> ""'),'hÃ¡Â»â„¢','fa-file-signature','orange'),
            $this->kpi('HÃ¡Â»â„¢ cÃƒÂ³ mÃƒÂ£ sÃ¡Â»â€˜ thuÃ¡ÂºÂ¿',$this->businessDistinctCount($filters,'hb.tax_code IS NOT NULL AND hb.tax_code <> ""'),'hÃ¡Â»â„¢','fa-receipt','cyan'),
            $this->kpi('HÃ¡Â»â„¢ tham gia OCOP',$stats['ocop_households']??0,'hÃ¡Â»â„¢','fa-award','purple'),
            $this->kpi('HÃ¡Â»â„¢ Ã„â€˜Ã¡ÂºÂ¡t ATTP',$stats['food_safety_households']??0,'hÃ¡Â»â„¢','fa-shield-heart','green'),
            $this->kpi('HÃ¡Â»â„¢ tham gia BHXH',$stats['social_insurance_households']??0,'hÃ¡Â»â„¢','fa-user-shield','blue'),
        ],'charts'=>['types'=>$charts['economicTypes']??[],'sectors'=>$charts['sectors']??[],'sectorShare'=>$charts['sectors']??[],'scales'=>$charts['scales']??[]],'top'=>$this->businessTopHouseholds($filters),'map'=>$this->businessMapMarkers($filters),'generatedAt'=>date('c')];
    }

    public function vehicleDashboard(array $filters = []): array
    {
        $model = new \App\Models\Vehicle();
        $stats = $model->dashboard($filters);
        $charts = $model->charts($filters);
        return ['module'=>'vehicles','title'=>'Dashboard Xe cÃ¡Â»â„¢','kpis'=>[
            $this->kpi('TÃ¡Â»â€¢ng phÃ†Â°Ã†Â¡ng tiÃ¡Â»â€¡n',$stats['total']??0,'xe','fa-car','green'),
            $this->kpi('HÃ¡Â»â„¢ cÃƒÂ³ phÃ†Â°Ã†Â¡ng tiÃ¡Â»â€¡n',$stats['households']??0,'hÃ¡Â»â„¢','fa-house-user','blue'),
            $this->kpi('Ãƒâ€ tÃƒÂ´',$stats['cars']??0,'xe','fa-car-side','orange'),
            $this->kpi('Xe mÃƒÂ¡y',$stats['motorbikes']??0,'xe','fa-motorcycle','cyan'),
            $this->kpi('Xe Ã„â€˜iÃ¡Â»â€¡n',$stats['electric']??0,'xe','fa-bolt','purple'),
            $this->kpi('CÃƒÂ³ biÃ¡Â»Æ’n sÃ¡Â»â€˜',$stats['with_plate']??0,'xe','fa-id-card','blue'),
            $this->kpi('KhÃƒÂ´ng biÃ¡Â»Æ’n sÃ¡Â»â€˜',$stats['without_plate']??0,'xe','fa-circle-question','orange'),
            $this->kpi('HÃ¡ÂºÂ¿t hÃ¡ÂºÂ¡n kiÃ¡Â»Æ’m Ã„â€˜Ã¡Â»â€¹nh',$stats['expired_inspection']??0,'xe','fa-triangle-exclamation','pink'),
            $this->kpi('HÃ¡ÂºÂ¿t hÃ¡ÂºÂ¡n bÃ¡ÂºÂ£o hiÃ¡Â»Æ’m',$stats['expired_insurance']??0,'xe','fa-shield-halved','green'),
        ],'charts'=>['types'=>$charts['types']??[],'households'=>$charts['households']??[],'areas'=>$charts['areas']??[],'details'=>$charts['details']??[]],'top'=>$model->topHouseholds($filters),'generatedAt'=>date('c')];
    }

    public function livestockDashboard(array $filters = []): array
    {
        $model = new \App\Models\Livestock();
        $stats = $model->dashboard($filters);
        $charts = $model->charts($filters);
        return ['module'=>'livestock','title'=>'Dashboard Chan nuoi','kpis'=>[
            $this->kpi('Tong ho co chan nuoi',$stats['livestock_households']??0,'ho','fa-house','green'),
            $this->kpi('Tong co so chan nuoi',$stats['facility_total']??0,'co so','fa-warehouse','blue'),
            $this->kpi('Tong trang trai',$stats['farm_total']??0,'trang trai','fa-industry','orange'),
            $this->kpi('Tong dan vat nuoi',$stats['livestock_total']??0,'con','fa-paw','blue'),
            $this->kpi('Tong dan lon',$stats['pig_total']??0,'con','fa-bacon','purple'),
            $this->kpi('Lon nai',$stats['pig_sow_total']??0,'con','fa-circle-dot','green'),
            $this->kpi('Lon thit',$stats['pig_meat_total']??0,'con','fa-circle-dot','orange'),
            $this->kpi('Lon con',$stats['piglet_total']??0,'con','fa-circle-dot','cyan'),
            $this->kpi('Lon duc giong',$stats['pig_boar_total']??0,'con','fa-circle-dot','pink'),
            $this->kpi('Ho nuoi lon',$stats['pig_households']??0,'ho','fa-house-chimney','green'),
            $this->kpi('Trang trai nuoi lon',$stats['pig_farms']??0,'trang trai','fa-warehouse','purple'),
        ],'charts'=>['types'=>$charts['types']??[],'scale'=>$charts['scale']??[],'areas'=>$charts['areas']??[],'vaccination'=>$charts['vaccination']??[]],'top'=>$model->topHouseholds($filters),'generatedAt'=>date('c')];
    }

    public function gisDashboard(array $filters = []): array
    {
        $gis = $this->gisSummary($filters);
        $business = (new \App\Models\HouseholdBusiness())->dashboard($filters);
        return ['module'=>'gis','title'=>'Dashboard GIS','kpis'=>[
            $this->kpi('HÃ¡Â»â„¢ dÃƒÂ¢n',$gis['totalHouseholds']??0,'hÃ¡Â»â„¢','fa-house-chimney','green'),
            $this->kpi('HÃ¡Â»â„¢ Ã„â€˜ÃƒÂ£ Ã„â€˜Ã¡Â»â€¹nh vÃ¡Â»â€¹',$gis['locatedHouseholds']??0,'hÃ¡Â»â„¢','fa-location-dot','blue'),
            $this->kpi('HÃ¡Â»â„¢ chÃ†Â°a Ã„â€˜Ã¡Â»â€¹nh vÃ¡Â»â€¹',$gis['unlocatedHouseholds']??0,'hÃ¡Â»â„¢','fa-map-pin','orange'),
            $this->kpi('Khu vÃ¡Â»Â±c GIS',$gis['totalAreas']??0,'khu','fa-draw-polygon','purple'),
            $this->kpi('HÃ¡Â»â„¢ kinh doanh',$business['economic_households']??0,'hÃ¡Â»â„¢','fa-store','cyan'),
        ],'charts'=>['gps'=>$this->gpsProgressChart($filters),'business'=>(new \App\Models\HouseholdBusiness())->charts($filters)['economicTypes']??[]],'layers'=>['HÃ¡Â»â„¢ dÃƒÂ¢n','HÃ¡Â»â„¢ kinh doanh','PhÃ†Â°Ã†Â¡ng tiÃ¡Â»â€¡n','Trang trÃ¡ÂºÂ¡i','ChuÃ¡Â»â€œng trÃ¡ÂºÂ¡i','Khu vÃ¡Â»Â±c sÃ¡ÂºÂ£n xuÃ¡ÂºÂ¥t','Khu vÃ¡Â»Â±c chÃ„Æ’n nuÃƒÂ´i'],'map'=>$this->businessMapMarkers($filters),'generatedAt'=>date('c')];
    }

    public function reportsDashboard(array $filters = []): array
    {
        $reports = ['BÃƒÂ¡o cÃƒÂ¡o nhÃƒÂ¢n khÃ¡ÂºÂ©u','BÃƒÂ¡o cÃƒÂ¡o kinh doanh','BÃƒÂ¡o cÃƒÂ¡o xe','BÃƒÂ¡o cÃƒÂ¡o chÃ„Æ’n nuÃƒÂ´i','BÃƒÂ¡o cÃƒÂ¡o GIS'];
        $exports = ['PDF','Excel','In trÃ¡Â»Â±c tiÃ¡ÂºÂ¿p'];
        $populationReports = array_filter($reports, fn($label) => str_contains($label, 'nhÃƒÂ¢n khÃ¡ÂºÂ©u'));
        $domainReports = array_filter($reports, fn($label) => !str_contains($label, 'nhÃƒÂ¢n khÃ¡ÂºÂ©u') && !str_contains($label, 'GIS'));
        return ['module'=>'reports','title'=>'Dashboard BÃƒÂ¡o cÃƒÂ¡o','kpis'=>[
            $this->kpi('NhÃƒÂ³m bÃƒÂ¡o cÃƒÂ¡o khÃ¡ÂºÂ£ dÃ¡Â»Â¥ng', count($reports), 'nhÃƒÂ³m', 'fa-layer-group', 'blue'),
            $this->kpi('Ã„ÂÃ¡Â»â€¹nh dÃ¡ÂºÂ¡ng xuÃ¡ÂºÂ¥t', count($exports), 'loÃ¡ÂºÂ¡i', 'fa-file-export', 'green'),
            $this->kpi('BÃƒÂ¡o cÃƒÂ¡o dÃƒÂ¢n cÃ†Â°', count($populationReports), 'nhÃƒÂ³m', 'fa-users', 'cyan'),
            $this->kpi('BÃƒÂ¡o cÃƒÂ¡o nghiÃ¡Â»â€¡p vÃ¡Â»Â¥', count($domainReports), 'nhÃƒÂ³m', 'fa-chart-pie', 'orange'),
        ],'reports'=>$reports,'exports'=>$exports,'generatedAt'=>date('c')];
    }

    private function kpi(string $label, mixed $value, string $unit, string $icon, string $tone): array
    {
        return ['label'=>$label,'value'=>(float) $value,'unit'=>$unit,'icon'=>$icon,'tone'=>$tone];
    }

    private function emptyDomainDashboard(string $module, string $title, array $cards, array $chartKeys): array
    {
        $tones = ['green','blue','orange','cyan','purple','pink'];
        $kpis = [];
        foreach ($cards as $i => $card) $kpis[] = $this->kpi($card[0], 0, '', $card[1], $tones[$i % count($tones)]);
        $charts = [];
        foreach ($chartKeys as $key) $charts[$key] = [];
        return ['module'=>$module,'title'=>$title,'kpis'=>$kpis,'charts'=>$charts,'top'=>[],'map'=>[],'generatedAt'=>date('c')];
    }

    private function businessDistinctCount(array $filters, string $condition): int
    {
        [$where, $params] = $this->businessWhere($filters);
        return (int) (($this->fetchOne("SELECT COUNT(DISTINCT hb.household_id) AS total FROM household_business hb INNER JOIN households h ON h.id = hb.household_id $where AND ($condition)", $params) ?: [])['total'] ?? 0);
    }

    private function businessTopHouseholds(array $filters): array
    {
        [$where, $params] = $this->businessWhere($filters);
        $rows = $this->fetchAll("SELECT h.id AS household_id, h.household_code, h.head_citizen_name, COUNT(hb.id) AS activity_count, COALESCE(SUM(hb.worker_count),0) AS worker_count FROM household_business hb INNER JOIN households h ON h.id = hb.household_id $where GROUP BY h.id, h.household_code, h.head_citizen_name ORDER BY activity_count DESC, worker_count DESC, h.household_code ASC LIMIT 10", $params);
        return array_map(fn($r) => ['household_id'=>(int)$r['household_id'],'household_code'=>(string)$r['household_code'],'head_citizen_name'=>(string)$r['head_citizen_name'],'activity_count'=>(int)$r['activity_count'],'worker_count'=>(int)$r['worker_count']], $rows);
    }

    private function businessMapMarkers(array $filters): array
    {
        if (!$this->columnExists('households','latitude') || !$this->columnExists('households','longitude')) return [];
        [$where, $params] = $this->businessWhere($filters);
        $rows = $this->fetchAll("SELECT h.id AS household_id, h.household_code, h.head_citizen_name, h.latitude, h.longitude, COUNT(hb.id) AS activity_count, GROUP_CONCAT(COALESCE(NULLIF(hb.business_name,''), NULLIF(hb.economic_type,''), 'HoÃ¡ÂºÂ¡t Ã„â€˜Ã¡Â»â„¢ng kinh tÃ¡ÂºÂ¿') ORDER BY hb.id SEPARATOR '; ') AS activities FROM household_business hb INNER JOIN households h ON h.id = hb.household_id $where AND h.latitude IS NOT NULL AND h.latitude <> '' AND h.longitude IS NOT NULL AND h.longitude <> '' GROUP BY h.id, h.household_code, h.head_citizen_name, h.latitude, h.longitude LIMIT 200", $params);
        return array_map(fn($r) => ['household_id'=>(int)$r['household_id'],'household_code'=>(string)$r['household_code'],'head_citizen_name'=>(string)$r['head_citizen_name'],'latitude'=>(float)$r['latitude'],'longitude'=>(float)$r['longitude'],'activity_count'=>(int)$r['activity_count'],'activities'=>(string)($r['activities']??'')], $rows);
    }

    private function businessWhere(array $filters): array
    {
        $where = ['hb.status <> "DELETED"', $this->tenantLiteral('household_business', 'hb'), $this->activeHouseholdCondition('h')];
        $params = [];
        $area = trim((string) ($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') { $where[] = 'h.area_code = :business_area_code'; $params['business_area_code'] = $area; }
        $from = trim((string) ($filters['date_from'] ?? $filters['dateFrom'] ?? ''));
        if ($from !== '') { $where[] = 'DATE(COALESCE(hb.updated_at, hb.created_at)) >= :business_date_from'; $params['business_date_from'] = $from; }
        $to = trim((string) ($filters['date_to'] ?? $filters['dateTo'] ?? ''));
        if ($to !== '') { $where[] = 'DATE(COALESCE(hb.updated_at, hb.created_at)) <= :business_date_to'; $params['business_date_to'] = $to; }
        return ['WHERE ' . implode(' AND ', $where), $params];
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
        if ($filters['householdStatus']) { if (in_array($filters['householdStatus'], ['resident', 'away_for_work', 'settled_elsewhere', 'partial', 'inactive', 'outside'], true)) { $where[] = $this->residenceStatusSql('h') . ' = :household_status'; $params['household_status'] = $filters['householdStatus']; } else { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; } }
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
        if ($filters['householdStatus']) { if (in_array($filters['householdStatus'], ['resident', 'away_for_work', 'settled_elsewhere', 'partial', 'inactive', 'outside'], true)) { $where[] = $this->residenceStatusSql('h') . ' = :household_status'; $params['household_status'] = $filters['householdStatus']; } else { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; } }
        if ($filters['residencyStatus']) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if ($filters['presenceStatus']) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if ($filters['dateFrom']) { $where[] = 'DATE(c.created_at) >= :citizen_date_from'; $params['citizen_date_from'] = $filters['dateFrom']; }
        if ($filters['dateTo']) { $where[] = 'DATE(c.created_at) <= :citizen_date_to'; $params['citizen_date_to'] = $filters['dateTo']; }
        $category = $this->categoryKey($filters['householdType']);
        if ($category) $this->addCategoryWhere($where, $params, $category);
        foreach (self::CITIZEN_FLAG_COLUMNS as $column) {
            $value = $rawFilters[$column] ?? $rawFilters[$this->camel($column)] ?? null;
            if ($column === 'meritorious_person' && $value !== null && $value !== '') {
                $where[] = $this->meritoriousCitizenExpression('c', (int) $value === 1);
            } elseif ($column === 'pupil' && $value !== null && $value !== '') {
                $where[] = ((int) $value === 1 ? '' : 'NOT ') . StudentStatusService::studentSql('c');
            } elseif ($value !== null && $value !== '' && $this->columnExists('citizens', $column)) {
                $where[] = 'c.' . $column . ' = :' . $column; $params[$column] = (int) $value;
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

    private function activeHouseholdCondition(string $alias): string
    {
        return $this->statistics()->householdCondition($alias);
    }

    private function activeCitizenCondition(string $alias): string
    {
        return $this->statistics()->citizenCondition($alias);
    }

    private function statistics(): PopulationStatistics
    {
        return $this->statistics ??= new PopulationStatistics();
    }

    private function addCategoryWhere(array &$where, array &$params, string $category): void
    {
        $condition = $this->categoryService()->condition($category, 'h');
        if ($condition !== '') $where[] = $condition;
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category): void
    {
        $label = ['escaped_poverty' => 'HÃ¡Â»â„¢ mÃ¡Â»â€ºi thoÃƒÂ¡t nghÃƒÂ¨o', 'policy' => 'HÃ¡Â»â„¢ chÃƒÂ­nh sÃƒÂ¡ch'][$category] ?? $category;
        $where[] = '(h.note LIKE :category_label OR h.note LIKE :category_key)';
        $params['category_label'] = '%' . $label . '%';
        $params['category_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function categoryKey(mixed $value): string
    {
        return HouseholdCategoryService::normalizeKey($value);
    }
    private function categoryService(): HouseholdCategoryService { return $this->categoryService ??= new HouseholdCategoryService(); }

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
        $citizenPolicy = $this->meritoriousCitizenExpression('dhc');
        if ($citizenPolicy === '0=1') return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens dhc WHERE dhc.household_id=' . $alias . '.id AND ' . $this->activeCitizenCondition('dhc') . ' AND ' . $citizenPolicy . ')';
    }

    private function disabledHouseholdExists(string $alias): string
    {
        if (!$this->columnExists('citizens', 'disabled_person')) return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens ddc WHERE ddc.household_id=' . $alias . '.id AND ' . $this->activeCitizenCondition('ddc') . ' AND ddc.disabled_person=1)';
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
}
