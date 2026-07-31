<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Policies\AgePolicy;
use App\Core\SimplePdf;
use App\Core\TenantConfig;

final class PolicyAlert extends BaseModel
{
    private ?array $config = null;
    private ?PopulationStatistics $statistics = null;

    public static function filterCondition(string $key, string $alias = 'c'): ?string
    {
        $config = self::configData();
        $alert = $config['alerts'][$key] ?? null;
        if (!$alert) return null;
        $ageExpr = AgePolicy::ageSql($alias);
        if (($alert['type'] ?? '') === 'upcoming') {
            $targetDate = AgePolicy::targetDateSql($alias, (int) $alert['age']);
            return "$ageExpr < " . (int) $alert['age'] . " AND DATEDIFF($targetDate,CURDATE()) BETWEEN 0 AND " . (int) ($config['lookahead_days'] ?? AgePolicy::UPCOMING_POLICY_LOOKAHEAD_DAYS);
        }
        $condition = "$ageExpr >= " . (int) $alert['age'];
        if (!empty($alert['exclude_if_flag'])) $condition .= " AND COALESCE($alias." . preg_replace('/[^a-z_]/', '', $alert['exclude_if_flag']) . ',0)=0';
        return $condition;
    }

    public function summary(): array
    {
        $this->ensureSchema();
        $items = [];
        foreach ($this->alerts() as $key => $alert) {
            $count = $this->countFor($key, true);
            $items[] = [
                'key' => $key,
                'label' => (string) $alert['label'],
                'age' => (int) $alert['age'],
                'type' => (string) $alert['type'],
                'purpose' => (string) ($alert['purpose'] ?? ''),
                'count' => $count,
                'message' => sprintf((string) $alert['message'], $count),
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
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN policy_alert_reviews r ON r.citizen_id=c.id AND r.alert_key=:review_key AND " . $this->tenantWhere('r', 'policy_alert_reviews') . " $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN policy_alert_reviews r ON r.citizen_id=c.id AND r.alert_key=:review_key AND " . $this->tenantWhere('r', 'policy_alert_reviews') . " $where ORDER BY c.date_of_birth ASC, c.full_name ASC LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total, ['summary' => $this->summary()]);
    }

    public function mark(int $citizenId, string $alertKey, string $status, int $userId, string $note = ''): array
    {
        $this->ensureSchema();
        if (!isset($this->alerts()[$alertKey])) throw new \RuntimeException('Loại cảnh báo không hợp lệ');
        if (!in_array($status, ['reviewed', 'processed'], true)) throw new \RuntimeException('Trạng thái xử lý không hợp lệ');
        $existing = $this->fetchOne('SELECT id FROM policy_alert_reviews WHERE citizen_id=:citizen_id AND alert_key=:alert_key AND ' . $this->tenantWhere('policy_alert_reviews'), $this->withTenant(['citizen_id' => $citizenId, 'alert_key' => $alertKey]));
        $params = $this->withTenant([
            'citizen_id' => $citizenId,
            'alert_key' => $alertKey,
            'user_id' => $userId,
            'note' => trim($note),
        ]);
        if ($existing) {
            $sets = $status === 'reviewed'
                ? 'reviewed_at=COALESCE(reviewed_at,NOW()), reviewed_by=COALESCE(reviewed_by,:user_id), note=:note'
                : 'reviewed_at=COALESCE(reviewed_at,NOW()), reviewed_by=COALESCE(reviewed_by,:user_id), processed_at=NOW(), processed_by=:user_id, note=:note';
            $params['id'] = (int) $existing['id'];
            $this->execute("UPDATE policy_alert_reviews SET $sets WHERE id=:id AND " . $this->tenantWhere('policy_alert_reviews'), $params);
        } else {
            $columns = ['citizen_id', 'alert_key', 'reviewed_at', 'reviewed_by', 'processed_at', 'processed_by', 'note'];
            $this->addTenantInsert('policy_alert_reviews', $columns, $params);
            $values = $status === 'reviewed'
                ? [':citizen_id', ':alert_key', 'NOW()', ':user_id', 'NULL', 'NULL', ':note']
                : [':citizen_id', ':alert_key', 'NOW()', ':user_id', 'NOW()', ':user_id', ':note'];
            if (in_array('village_id', $columns, true)) {
                $values[] = ':village_id';
            }
            $this->insert('INSERT INTO policy_alert_reviews (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')', $params);
        }
        return $this->findReview($citizenId, $alertKey) ?? ['citizen_id' => $citizenId, 'alert_key' => $alertKey];
    }

    public function report(array $filters): array
    {
        $this->ensureSchema();
        $type = (string) ($filters['type'] ?? $filters['alert'] ?? 'age_70');
        $filters['type'] = $type;
        $alert = $this->alerts()[$type] ?? null;
        [$where, $params] = $this->where($filters);
        $items = array_map(fn($row) => $this->normalize($row), $this->fetchAll($this->selectSql() . " FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN policy_alert_reviews r ON r.citizen_id=c.id AND r.alert_key=:review_key AND " . $this->tenantWhere('r', 'policy_alert_reviews') . " $where ORDER BY c.date_of_birth ASC, c.full_name ASC", $params));
        return [
            'title' => $alert ? 'Danh sách ' . $this->lowerLabel((string) $alert['label']) : 'Danh sách cảnh báo chính sách',
            'headers' => ['STT', 'Họ tên', 'Ngày sinh', 'Tuổi', 'Chủ hộ', 'Địa chỉ', 'BHYT', 'Trợ cấp xã hội', 'Trạng thái xử lý', 'Ghi chú'],
            'rows' => array_map(function ($row, $index) {
                return [
                    $index + 1,
                    $row['full_name'],
                    $row['date_of_birth'],
                    $row['age'],
                    $row['head_citizen_name'],
                    $row['address'],
                    $row['has_health_insurance'] ? 'Có' : 'Chưa có',
                    $row['social_assistance'] ? 'Đang hưởng' : 'Chưa hưởng',
                    $row['processed_at'] ? 'Đã xử lý' : ($row['reviewed_at'] ? 'Đã rà soát' : 'Chưa xử lý'),
                    $row['review_note'] ?? '',
                ];
            }, $items, array_keys($items)),
            'totalRows' => count($items),
            'filters' => $filters,
            'summary' => ['Tổng số' => count($items)],
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

    private function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS policy_alert_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  citizen_id BIGINT UNSIGNED NOT NULL,
  alert_key VARCHAR(80) NOT NULL,
  reviewed_at DATETIME NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  processed_at DATETIME NULL,
  processed_by BIGINT UNSIGNED NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_policy_alert_reviews_citizen_key (citizen_id, alert_key),
  KEY idx_policy_alert_reviews_key (alert_key),
  KEY idx_policy_alert_reviews_processed (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureTenantColumn('policy_alert_reviews');
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
        if ($status === 'reviewed') $where[] = 'r.reviewed_at IS NOT NULL AND r.processed_at IS NULL';
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
        return 'SELECT c.id, c.citizen_code, c.full_name, c.date_of_birth, ' . AgePolicy::ageSql('c') . ' AS age, c.phone, c.has_health_insurance, c.social_assistance, h.household_code, h.head_citizen_name, COALESCE(NULLIF(c.current_address,""),h.address) AS address, r.reviewed_at, r.processed_at, r.note AS review_note';
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['age'] = (int) $row['age'];
        $row['has_health_insurance'] = !empty($row['has_health_insurance']);
        $row['social_assistance'] = !empty($row['social_assistance']);
        return $row;
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
