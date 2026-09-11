<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Policies\AgePolicy;
use App\Policies\InsurancePolicy;
use App\Core\SimplePdf;
use App\Core\TenantConfig;

final class PolicyAlert extends BaseModel
{
    private const ALERT_75_BTXH = 'age_75_social_assistance_legacy_missing_record';
    private const BTXH_RECEIVING = 'RECEIVING_SOCIAL_ASSISTANCE';
    private const BTXH_ELIGIBLE_NOT_RECEIVING = 'ELIGIBLE_NOT_RECEIVING';
    private const BTXH_NOT_ELIGIBLE = 'NOT_ELIGIBLE';
    private const BTXH_UNVERIFIED = 'UNVERIFIED';
    private const BTXH_COMPLETED_RESULTS = [
        self::BTXH_RECEIVING,
        self::BTXH_ELIGIBLE_NOT_RECEIVING,
        self::BTXH_NOT_ELIGIBLE,
    ];

    private ?array $config = null;
    private ?PopulationStatistics $statistics = null;

    public static function filterCondition(string $key, string $alias = 'c'): ?string
    {
        $config = self::configData();
        $alert = $config['alerts'][$key] ?? null;
        if (!$alert) return null;
        $ageExpr = AgePolicy::ageSql($alias);
        $excludeCondition = self::excludeCondition($alert, $alias);
        if ($key === 'age_70_health_insurance_effective') {
            return "$ageExpr >= " . InsurancePolicy::DEFAULT_AGE . ' AND ' . InsurancePolicy::effectiveConditionSql($alias, true, true);
        }
        if ($key === 'age_70_health_insurance_missing') {
            return "$ageExpr >= " . InsurancePolicy::DEFAULT_AGE . ' AND NOT (' . InsurancePolicy::effectiveConditionSql($alias, true, true) . ')';
        }
        if ($key === self::ALERT_75_BTXH) {
            return "$ageExpr >= " . AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE;
        }
        if ($key === 'age_75_social_assistance_no_legacy_no_record') {
            return "$ageExpr >= " . AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE . " AND COALESCE($alias.social_assistance,0)=0 AND NOT (" . self::socialAssistanceRecordExistsSql($alias, false) . ')';
        }
        if ($key === 'age_75_social_assistance_inactive_record') {
            return "$ageExpr >= " . AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE . ' AND NOT (' . self::socialAssistanceRecordExistsSql($alias, true) . ') AND ' . self::socialAssistanceRecordExistsSql($alias, false);
        }
        if (($alert['type'] ?? '') === 'upcoming') {
            $targetDate = AgePolicy::targetDateSql($alias, (int) $alert['age']);
            $condition = "$ageExpr < " . (int) $alert['age'] . " AND DATEDIFF($targetDate,CURDATE()) BETWEEN 0 AND " . (int) ($config['lookahead_days'] ?? AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS);
            if (!empty($alert['exclude_if_flag'])) $condition .= ' AND ' . self::flagExclusionCondition((string) $alert['exclude_if_flag'], $alias);
            return $excludeCondition !== '' ? $condition . ' AND ' . $excludeCondition : $condition;
        }
        $condition = "$ageExpr >= " . (int) $alert['age'];
        if (!empty($alert['exclude_if_flag'])) $condition .= " AND COALESCE($alias." . preg_replace('/[^a-z_]/', '', $alert['exclude_if_flag']) . ',0)=0';
        if ($excludeCondition !== '') $condition .= ' AND ' . $excludeCondition;
        return $condition;
    }


    private static function flagExclusionCondition(string $flag, string $alias): string
    {
        $field = preg_replace('/[^a-z_]/', '', $flag);
        if ($field === '') return '1=1';
        $condition = "COALESCE($alias.$field,0)=0";
        if ($field === 'social_assistance') {
            $condition .= ' AND NOT (' . self::socialAssistanceRecordExistsSql($alias, true) . ')';
        }
        return $condition;
    }

    private static function excludeCondition(array $alert, string $alias): string
    {
        $field = preg_replace('/[^a-z_]/', '', (string) ($alert['exclude_if_field'] ?? ''));
        if ($field === '') return '';

        $conditions = [];
        foreach ((array) ($alert['exclude_if_values'] ?? []) as $value) {
            $conditions[] = "$alias.$field <> " . self::quoteSqlLiteral((string) $value);
        }
        foreach ((array) ($alert['exclude_if_prefixes'] ?? []) as $prefix) {
            $conditions[] = "$alias.$field NOT LIKE " . self::quoteSqlLiteral((string) $prefix . '%');
        }

        if (!$conditions) return '';
        return '(' . "$alias.$field IS NULL OR $alias.$field = '' OR (" . implode(' AND ', $conditions) . '))';
    }

    private static function quoteSqlLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    public function summary(): array
    {
        $this->assertSchemaReady();
        $items = [];
        foreach ($this->alerts() as $key => $alert) {
            if (($alert['summary'] ?? true) === false) continue;
            $count = $this->countFor($key, true);
            $items[] = [
                'key' => $key,
                'label' => (string) $alert['label'],
                'age' => (int) $alert['age'],
                'type' => (string) $alert['type'],
                'purpose' => (string) ($alert['purpose'] ?? ''),
                'count' => $count,
                'message' => sprintf((string) $alert['message'], $count),
                'subtitle' => (string) ($alert['subtitle'] ?? ''),
            ];
        }
        return [
            'lookaheadDays' => $this->lookaheadDays(),
            'items' => $items,
            'total' => array_sum(array_column($items, 'count')),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->assertSchemaReady();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN policy_alert_reviews r ON r.citizen_id=c.id AND r.alert_key=:review_key AND " . $this->tenantWhere('r', 'policy_alert_reviews') . " $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN policy_alert_reviews r ON r.citizen_id=c.id AND r.alert_key=:review_key AND " . $this->tenantWhere('r', 'policy_alert_reviews') . " $where ORDER BY c.date_of_birth ASC, c.full_name ASC LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total, ['summary' => $this->summary()]);
    }

    public function mark(int $citizenId, string $alertKey, string $status, int $userId, string $note = '', string $resultStatus = ''): array
    {
        $this->assertSchemaReady();
        if (!isset($this->alerts()[$alertKey])) throw new \RuntimeException('Loai canh bao khong hop le');
        $resultStatus = trim($resultStatus);
        if ($alertKey === self::ALERT_75_BTXH) {
            $resultStatus = $this->normalizeBtxhResult($resultStatus !== '' ? $resultStatus : $status);
            if ($resultStatus === '') throw new \RuntimeException('Ket qua ra soat BTXH khong hop le');
            $status = $this->isBtxhCompletedResult($resultStatus) ? 'processed' : 'reviewed';
        }
        if (!in_array($status, ['reviewed', 'processed'], true)) throw new \RuntimeException('Trang thai xu ly khong hop le');
        $existing = $this->fetchOne('SELECT id FROM policy_alert_reviews WHERE citizen_id=:citizen_id AND alert_key=:alert_key AND ' . $this->tenantWhere('policy_alert_reviews'), $this->withTenant(['citizen_id' => $citizenId, 'alert_key' => $alertKey]));
        $params = $this->withTenant([
            'citizen_id' => $citizenId,
            'alert_key' => $alertKey,
            'user_id' => $userId,
            'note' => trim($note),
            'result_status' => $resultStatus,
        ]);
        if ($existing) {
            $sets = $status === 'reviewed'
                ? 'reviewed_at=COALESCE(reviewed_at,NOW()), reviewed_by=COALESCE(reviewed_by,:user_id), processed_at=NULL, processed_by=NULL, note=:note, result_status=:result_status'
                : 'reviewed_at=COALESCE(reviewed_at,NOW()), reviewed_by=COALESCE(reviewed_by,:user_id), processed_at=NOW(), processed_by=:user_id, note=:note, result_status=:result_status';
            $params['id'] = (int) $existing['id'];
            $this->execute("UPDATE policy_alert_reviews SET $sets WHERE id=:id AND " . $this->tenantWhere('policy_alert_reviews'), $params);
        } else {
            $columns = ['citizen_id', 'alert_key', 'reviewed_at', 'reviewed_by', 'processed_at', 'processed_by', 'note', 'result_status'];
            $this->addTenantInsert('policy_alert_reviews', $columns, $params);
            $values = $status === 'reviewed'
                ? [':citizen_id', ':alert_key', 'NOW()', ':user_id', 'NULL', 'NULL', ':note', ':result_status']
                : [':citizen_id', ':alert_key', 'NOW()', ':user_id', 'NOW()', ':user_id', ':note', ':result_status'];
            if (in_array('village_id', $columns, true)) {
                $values[] = ':village_id';
            }
            $this->insert('INSERT INTO policy_alert_reviews (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')', $params);
        }
        return $this->findReview($citizenId, $alertKey) ?? ['citizen_id' => $citizenId, 'alert_key' => $alertKey];
    }

    public function report(array $filters): array
    {
        $this->assertSchemaReady();
        $type = (string) ($filters['type'] ?? $filters['alert'] ?? 'age_70');
        $filters['type'] = $type;
        $alert = $this->alerts()[$type] ?? null;
        [$where, $params] = $this->where($filters);
        $items = array_map(fn($row) => $this->normalize($row), $this->fetchAll($this->selectSql() . " FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN policy_alert_reviews r ON r.citizen_id=c.id AND r.alert_key=:review_key AND " . $this->tenantWhere('r', 'policy_alert_reviews') . " $where ORDER BY c.date_of_birth ASC, c.full_name ASC", $params));
        return [
            'title' => $alert ? 'Danh sach ' . $this->lowerLabel((string) $alert['label']) : 'Danh sach canh bao chinh sach',
            'headers' => ['STT', 'Ma nhan khau', 'Ho ten', 'Ngay sinh', 'Tuoi', 'Gioi tinh', 'Ma ho', 'Chu ho hien tai', 'Khu vuc', 'Cu tru', 'Hien dien', 'BHYT', 'Thong tin BTXH hien co', 'Ket qua ra soat BTXH', 'Trang thai ra soat', 'Ghi chu'],
            'rows' => array_map(function ($row, $index) {
                $legacySocialAssistance = !empty($row['legacy_social_assistance'] ?? $row['social_assistance'] ?? false);
                $existingInfo = [];
                if ($legacySocialAssistance) $existingInfo[] = "C\u{00F3} d\u{1EEF} li\u{1EC7}u BTXH c\u{0169}";
                if (!empty($row['has_social_assistance_record'])) $existingInfo[] = "C\u{00F3} h\u{1ED3} s\u{01A1} BTXH hi\u{1EC7}n h\u{00E0}nh";
                elseif (!empty($row['has_any_social_assistance_record'])) $existingInfo[] = "C\u{00F3} h\u{1ED3} s\u{01A1} BTXH kh\u{00F4}ng hi\u{1EC7}n h\u{00E0}nh: " . ($row['social_assistance_record_status'] ?? '');
                if (!$existingInfo) $existingInfo[] = "Ch\u{01B0}a c\u{00F3} th\u{00F4}ng tin BTXH trong h\u{1EC7} th\u{1ED1}ng";
                return [
                    $index + 1,
                    $row['citizen_code'],
                    $row['full_name'],
                    $row['date_of_birth'],
                    $row['age'],
                    $row['gender'] ?: "Kh\u{00E1}c/Ch\u{01B0}a x\u{00E1}c \u{0111}\u{1ECB}nh",
                    $row['household_code'],
                    $row['head_citizen_name'],
                    $row['area_code'] ?? '',
                    $row['residency_status'] ?? '',
                    $row['presence_status'] ?? '',
                    $row['has_health_insurance'] ? "C\u{00F3} BHYT" : "Ch\u{01B0}a ghi nh\u{1EAD}n",
                    implode('; ', $existingInfo),
                    $this->btxhResultLabel((string) ($row['review_result_status'] ?? '')),
                    $row['review_completed'] ? "\u{0110}\u{00E3} ho\u{00E0}n th\u{00E0}nh" : ($row['reviewed_at'] ? "Ch\u{01B0}a x\u{00E1}c minh xong" : "Ch\u{01B0}a r\u{00E0} so\u{00E1}t"),
                    $row['review_note'] ?? '',
                ];
            }, $items, array_keys($items)),
            'totalRows' => count($items),
            'filters' => $filters,
            'summary' => ["T\u{1ED5}ng s\u{1ED1}" => count($items)],
        ];
    }

    public function excel(array $filters): string
    {
        $report = $this->report($filters);
        $html = '<html><head><meta charset="utf-8"></head><body><h1>' . htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') . '</h1><table border="1"><thead><tr>';
        foreach ($report['headers'] as $header) $html .= '<th>' . htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($report['rows'] as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) $html .= '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '</tr>';
        }
        return "\xEF\xBB\xBF" . $html . '</tbody></table></body></html>';
    }

    public function pdf(array $filters): string
    {
        $report = $this->report($filters);
        $pdf = new SimplePdf();
        $pdf->addPrintHeader(TenantConfig::unitName(), $report['title']);
        $pdf->addMeta('Thời gian xuất: ' . date('d/m/Y H:i:s'));
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('Trưởng thôn');
        return $pdf->output();
    }

    private function assertSchemaReady(): void
    {
        $required = ['id','village_id','citizen_id','alert_key','reviewed_at','reviewed_by','processed_at','processed_by','result_status','note','created_at','updated_at'];
        if (!$this->tableExists('policy_alert_reviews')) {
            throw new \RuntimeException('Policy Alert schema is not provisioned: missing table policy_alert_reviews');
        }
        foreach ($required as $column) {
            if (!$this->columnExists('policy_alert_reviews', $column)) {
                throw new \RuntimeException('Policy Alert schema is not provisioned: missing column policy_alert_reviews.' . $column);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }

    private function countFor(string $key, bool $pendingOnly): int
    {
        [$where, $params] = $this->where(['type' => $key, 'status' => $pendingOnly ? 'pending' : '']);
        $row = $this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN policy_alert_reviews r ON r.citizen_id=c.id AND r.alert_key=:review_key AND " . $this->tenantWhere('r', 'policy_alert_reviews') . " $where", $params) ?: [];
        return (int) ($row['total'] ?? 0);
    }

    private function where(array $filters): array
    {
        $type = preg_replace('/[^a-z0-9_]/', '', (string) ($filters['type'] ?? $filters['alert'] ?? 'age_70'));
        if (!isset($this->alerts()[$type])) $type = 'age_70';
        $params = $this->withTenant(['review_key' => $type]);
        $where = [
            $this->statistics()->citizenCondition('c'),
            $this->statistics()->householdCondition('h'),
            $this->tenantWhere('c', 'citizens'),
            $this->tenantWhere('h', 'households'),
            self::filterCondition($type, 'c') ?? '1=1',
        ];
        $status = (string) ($filters['status'] ?? '');
        if ($type === self::ALERT_75_BTXH) {
            $where[] = 'COALESCE(c.status,"ACTIVE") <> "INACTIVE"';
            $this->addBtxhStatusWhere($where, $status);
        }
        elseif ($status === 'reviewed') $where[] = 'r.reviewed_at IS NOT NULL AND r.processed_at IS NULL';
        elseif ($status === 'processed') $where[] = 'r.processed_at IS NOT NULL';
        elseif ($status === 'pending') $where[] = 'r.reviewed_at IS NULL AND r.processed_at IS NULL';
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(c.full_name LIKE :q OR c.citizen_code LIKE :q OR h.household_code LIKE :q OR h.head_citizen_name LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function selectSql(): string
    {
        $currentSocialAssistance = self::socialAssistanceRecordExistsSql('c', true);
        $anySocialAssistance = self::socialAssistanceRecordExistsSql('c', false);
        $recordStatus = self::socialAssistanceRecordStatusSql('c');
        return 'SELECT c.id, c.citizen_code, c.full_name, c.date_of_birth, ' . AgePolicy::ageSql('c') . ' AS age, c.gender, c.phone, c.has_health_insurance, c.social_assistance, c.residency_status, c.presence_status, h.household_code, (SELECT hc.full_name FROM citizens hc WHERE hc.id=h.head_citizen_id AND hc.status <> "DELETED" AND COALESCE(hc.life_status,"ALIVE") <> "DECEASED" AND COALESCE(hc.residency_status,"PERMANENT") <> "TRANSFERRED_OUT" AND COALESCE(hc.presence_status,"AT_HOME") <> "MOVED_OUT" AND hc.village_id = c.village_id LIMIT 1) AS head_citizen_name, h.area_code, COALESCE(NULLIF(c.current_address,""),h.address) AS address, r.reviewed_at, r.processed_at, r.result_status AS review_result_status, r.note AS review_note, (' . $currentSocialAssistance . ') AS has_social_assistance_record, (' . $anySocialAssistance . ') AS has_any_social_assistance_record, (' . $recordStatus . ') AS social_assistance_record_status';
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['age'] = (int) $row['age'];
        $row['has_health_insurance'] = !empty($row['has_health_insurance']);
        $row['social_assistance'] = !empty($row['social_assistance']);
        $row['legacy_social_assistance'] = $row['social_assistance'];
        $row['has_social_assistance_record'] = !empty($row['has_social_assistance_record']);
        $row['has_any_social_assistance_record'] = !empty($row['has_any_social_assistance_record']);
        $row['social_assistance_record_status'] = $row['social_assistance_record_status'] ?? null;
        $row['review_result_status'] = (string) ($row['review_result_status'] ?? '');
        $row['review_completed'] = $this->isBtxhCompletedResult($row['review_result_status']);
        $row['receiving_social_assistance'] = $row['review_result_status'] === self::BTXH_RECEIVING || $row['has_social_assistance_record'];
        return $row;
    }


    private function addBtxhStatusWhere(array &$where, string $status): void
    {
        $completed = $this->btxhCompletedSql('r');
        if ($status === 'pending' || $status === '') $where[] = 'NOT (' . $completed . ')';
        elseif ($status === 'unreviewed') $where[] = 'r.reviewed_at IS NULL AND r.processed_at IS NULL AND (r.result_status IS NULL OR r.result_status="")';
        elseif ($status === 'reviewed' || $status === 'processed') $where[] = $completed;
        elseif ($status === 'receiving') $where[] = '((r.result_status="' . self::BTXH_RECEIVING . '") OR (' . self::socialAssistanceRecordExistsSql('c', true) . '))';
        elseif ($status === 'not_receiving') $where[] = 'r.result_status IN ("' . self::BTXH_ELIGIBLE_NOT_RECEIVING . '","' . self::BTXH_NOT_ELIGIBLE . '")';
        elseif ($status === 'unverified') $where[] = 'r.result_status="' . self::BTXH_UNVERIFIED . '"';
        elseif ($status === 'all') return;
    }

    private function btxhCompletedSql(string $alias): string
    {
        return 'COALESCE(' . $alias . '.result_status,"") IN ("' . implode('","', self::BTXH_COMPLETED_RESULTS) . '")';
    }

    private function normalizeBtxhResult(string $value): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, [self::BTXH_RECEIVING, self::BTXH_ELIGIBLE_NOT_RECEIVING, self::BTXH_NOT_ELIGIBLE, self::BTXH_UNVERIFIED], true) ? $value : '';
    }

    private function isBtxhCompletedResult(string $value): bool
    {
        return in_array($value, self::BTXH_COMPLETED_RESULTS, true);
    }

    private function btxhResultLabel(string $value): string
    {
        return match ($value) {
            self::BTXH_RECEIVING => "\u{0110}ang h\u{01B0}\u{1EDF}ng BTXH",
            self::BTXH_ELIGIBLE_NOT_RECEIVING => "Thu\u{1ED9}c di\u{1EC7}n nh\u{01B0}ng ch\u{01B0}a h\u{01B0}\u{1EDF}ng",
            self::BTXH_NOT_ELIGIBLE => "Kh\u{00F4}ng thu\u{1ED9}c di\u{1EC7}n h\u{01B0}\u{1EDF}ng",
            self::BTXH_UNVERIFIED => "Ch\u{01B0}a x\u{00E1}c minh \u{0111}\u{01B0}\u{1EE3}c",
            default => '',
        };
    }

    private static function socialAssistanceRecordExistsSql(string $alias, bool $currentOnly): string
    {
        $status = $currentOnly ? 'cpr.status IN ("ACTIVE","PAUSED")' : 'cpr.status IS NOT NULL';
        return 'EXISTS (SELECT 1 FROM citizen_policy_records cpr INNER JOIN policy_subject_types pst ON pst.id = cpr.policy_type_id WHERE cpr.citizen_id = ' . $alias . '.id AND ' . $status . ' AND cpr.deleted_at IS NULL AND pst.deleted_at IS NULL AND COALESCE(pst.is_active,1)=1 AND pst.code="SOCIAL_ASSISTANCE" AND cpr.village_id = ' . $alias . '.village_id AND pst.village_id = ' . $alias . '.village_id)';
    }

    private static function socialAssistanceRecordStatusSql(string $alias): string
    {
        return 'SELECT cpr.status FROM citizen_policy_records cpr INNER JOIN policy_subject_types pst ON pst.id = cpr.policy_type_id WHERE cpr.citizen_id = ' . $alias . '.id AND cpr.deleted_at IS NULL AND pst.deleted_at IS NULL AND COALESCE(pst.is_active,1)=1 AND pst.code="SOCIAL_ASSISTANCE" AND cpr.village_id = ' . $alias . '.village_id AND pst.village_id = ' . $alias . '.village_id ORDER BY FIELD(cpr.status,"ACTIVE","PAUSED","ENDED","DELETED"), cpr.id DESC LIMIT 1';
    }
    private function findReview(int $citizenId, string $alertKey): ?array
    {
        return $this->fetchOne('SELECT * FROM policy_alert_reviews WHERE citizen_id=:citizen_id AND alert_key=:alert_key AND ' . $this->tenantWhere('policy_alert_reviews'), $this->withTenant(['citizen_id' => $citizenId, 'alert_key' => $alertKey]));
    }

    private function alerts(): array
    {
        return $this->config()['alerts'] ?? [];
    }

    private function lookaheadDays(): int
    {
        return (int) ($this->config()['lookahead_days'] ?? AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS);
    }

    private function lowerLabel(string $label): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : $label;
    }

    private function config(): array
    {
        return $this->config ??= self::configData();
    }

    private static function configData(): array
    {
        $path = BASE_PATH . '/config/policy_alerts.php';
        return is_file($path) ? require $path : ['lookahead_days' => AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS, 'alerts' => []];
    }

    private function statistics(): PopulationStatistics
    {
        return $this->statistics ??= new PopulationStatistics();
    }
}
