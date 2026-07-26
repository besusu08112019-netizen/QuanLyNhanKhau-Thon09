<?php

namespace App\Models;

use App\Core\BaseModel;

final class SystemInsight extends BaseModel
{
    public function requiredModulesForQuestion(string $question): array
    {
        return match ($this->intent($question)) {
            'analytics_alerts' => ['dashboard', 'household', 'citizen'],
            'unpaid_contributions' => ['household', 'contributions'],
            'open_complaints' => ['complaints'],
            'citizens_over_80' => ['citizen', 'household'],
            'maintenance_due' => ['public_assets'],
            'households_with_livestock' => ['livestock', 'household'],
            'monthly_movements' => ['movement'],
            default => ['dashboard'],
        };
    }

    public function ask(string $question): array
    {
        $question = trim(mb_substr($question, 0, 500));
        if ($question === '') throw new \RuntimeException('Cau hoi la bat buoc');
        $intent = $this->intent($question);
        $result = match ($intent) {
            'analytics_alerts' => $this->answerAnalytics(),
            'unpaid_contributions' => $this->answerUnpaidContributions(),
            'open_complaints' => $this->answerOpenComplaints(),
            'citizens_over_80' => $this->answerCitizensOver80(),
            'maintenance_due' => $this->answerMaintenanceDue(),
            'households_with_livestock' => $this->answerHouseholdsWithLivestock(),
            'monthly_movements' => $this->answerMonthlyMovements(),
            default => $this->answerOverview(),
        };
        return ['question' => $question, 'intent' => $intent, 'mode' => 'READ_ONLY', 'answer' => $result['answer'], 'metrics' => $result['metrics'] ?? [], 'items' => $result['items'] ?? [], 'generatedAt' => date('c')];
    }

    public function globalSearch(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') return ['households' => [], 'citizens' => []];
        $limit = min(max($limit, 5), 50);
        $q = '%' . $query . '%';

        $households = $this->fetchAll(
            "SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code, h.poor_household, h.near_poor_household, h.meritorious_family, h.status,
                    COALESCE(v.total_members,0) AS member_count_real,
                    'household' AS result_type
             FROM households h
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             WHERE h.status <> 'DELETED' AND " . $this->tenantWhere('h', 'households') . "
               AND (h.household_code LIKE :q_code OR h.head_citizen_name LIKE :q_head OR h.address LIKE :q_address OR h.phone LIKE :q_phone OR h.area_code LIKE :q_area OR h.note LIKE :q_note)
             ORDER BY h.household_code
             LIMIT $limit",
            $this->withTenant(['q_code' => $q, 'q_head' => $q, 'q_address' => $q, 'q_phone' => $q, 'q_area' => $q, 'q_note' => $q])
        );

        $citizens = $this->fetchAll(
            "SELECT c.id, c.citizen_code, c.full_name, c.identity_number, c.phone, c.gender, c.date_of_birth, c.relationship, c.residency_status, c.presence_status, c.life_status,
                    h.id AS household_id, h.household_code, h.address AS household_address,
                    'citizen' AS result_type
             FROM citizens c
             INNER JOIN households h ON h.id = c.household_id
             WHERE c.status <> 'DELETED' AND " . $this->tenantWhere('c', 'citizens') . " AND " . $this->tenantWhere('h', 'households') . "
               AND (c.citizen_code LIKE :q_code OR c.full_name LIKE :q_name OR c.identity_number LIKE :q_identity OR c.phone LIKE :q_phone OR h.household_code LIKE :q_household OR h.address LIKE :q_address)
             ORDER BY c.full_name
             LIMIT $limit",
            $this->withTenant(['q_code' => $q, 'q_name' => $q, 'q_identity' => $q, 'q_phone' => $q, 'q_household' => $q, 'q_address' => $q])
        );

        foreach ($citizens as &$row) {
            if (!empty($row['identity_number'])) $row['identity_masked'] = $this->maskIdentity((string) $row['identity_number']);
            $row['computed_age'] = $this->age($row['date_of_birth'] ?? null);
        }

        return ['households' => $households, 'citizens' => $citizens];
    }

    public function smartAlerts(): array
    {
        return [
            'missing_identity' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND " . $this->tenantWhere('citizens') . " AND (identity_number IS NULL OR identity_number = '')", false, $this->withTenant()),
            'missing_phone' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND " . $this->tenantWhere('citizens') . " AND (phone IS NULL OR phone = '')", false, $this->withTenant()),
            'invalid_identity' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND " . $this->tenantWhere('citizens') . " AND identity_number IS NOT NULL AND identity_number <> '' AND identity_number NOT REGEXP '^[0-9]{9,12}$'", false, $this->withTenant()),
            'duplicate_identity' => $this->countOne("SELECT COUNT(*) AS total FROM (SELECT identity_number FROM citizens WHERE status <> 'DELETED' AND " . $this->tenantWhere('citizens') . " AND identity_number IS NOT NULL AND identity_number <> '' GROUP BY identity_number HAVING COUNT(*) > 1) d", false, $this->withTenant()),
            'households_without_members' => $this->countOne("SELECT COUNT(*) AS total FROM households h LEFT JOIN citizens c ON c.household_id = h.id AND c.status <> 'DELETED' AND " . $this->tenantWhere('c', 'citizens') . " WHERE h.status <> 'DELETED' AND " . $this->tenantWhere('h', 'households') . " GROUP BY h.id HAVING COUNT(c.id) = 0", true, $this->withTenant()),
            'missing_area_code' => $this->countOne("SELECT COUNT(*) AS total FROM households WHERE status <> 'DELETED' AND " . $this->tenantWhere('households') . " AND (area_code IS NULL OR area_code = '')", false, $this->withTenant()),
        ];
    }

    public function analytics(): array
    {
        $alerts = $this->smartAlerts();
        $items = [];
        $suggestions = [];
        foreach ($this->analyticsRules() as $rule) {
            $count = (int) ($alerts[$rule['key']] ?? 0);
            if ($count <= 0) continue;
            $items[] = [
                'key' => $rule['key'],
                'label' => $rule['label'],
                'severity' => $rule['severity'],
                'count' => $count,
                'module' => $rule['module'],
                'screen' => $rule['screen'],
                'suggestion' => $rule['suggestion'],
            ];
            $suggestions[] = $rule['suggestion'];
        }

        $severityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        usort($items, fn(array $a, array $b) => (($severityOrder[$b['severity']] ?? 0) <=> ($severityOrder[$a['severity']] ?? 0)) ?: ((int) $b['count'] <=> (int) $a['count']));

        return [
            'mode' => 'READ_ONLY',
            'summary' => $this->analyticsSummary($items),
            'metrics' => [
                'total_alerts' => array_sum(array_map(fn($item) => (int) $item['count'], $items)),
                'high' => count(array_filter($items, fn($item) => $item['severity'] === 'high')),
                'medium' => count(array_filter($items, fn($item) => $item['severity'] === 'medium')),
                'low' => count(array_filter($items, fn($item) => $item['severity'] === 'low')),
            ],
            'items' => $items,
            'suggestions' => array_values(array_unique($suggestions)),
            'generatedAt' => date('c'),
        ];
    }

    private function intent(string $question): string
    {
        $q = mb_strtolower($this->stripVietnamese($question), 'UTF-8');
        if (str_contains($q, 'bat thuong') || str_contains($q, 'canh bao') || str_contains($q, 'goi y xu ly') || str_contains($q, 'du lieu thieu') || str_contains($q, 'ho so thieu')) return 'analytics_alerts';
        if (str_contains($q, 'chua dong') || str_contains($q, 'no quy') || str_contains($q, 'dong quy')) return 'unpaid_contributions';
        if (str_contains($q, 'phan anh') && (str_contains($q, 'chua xu ly') || str_contains($q, 'dang xu ly') || str_contains($q, 'bao nhieu'))) return 'open_complaints';
        if ((str_contains($q, '80') || str_contains($q, 'cao tuoi')) && str_contains($q, 'nhan khau')) return 'citizens_over_80';
        if (str_contains($q, 'bao tri') || str_contains($q, 'bao duong')) return 'maintenance_due';
        if (str_contains($q, 'vat nuoi')) return 'households_with_livestock';
        if (str_contains($q, 'bien dong') && (str_contains($q, 'thang nay') || str_contains($q, 'bao nhieu'))) return 'monthly_movements';
        return 'overview';
    }

    private function answerUnpaidContributions(): array
    {
        if (!$this->tableExists('household_contributions')) return $this->emptyAnswer('Chua co du lieu dong gop ho.');
        $tenant = $this->withTenant();
        $tenantWhere = $this->tenantWhere('hc', 'household_contributions') . ' AND ' . $this->tenantWhere('cc', 'contribution_campaigns') . ' AND ' . $this->tenantWhere('h', 'households');
        $total = $this->countOne("SELECT COUNT(*) AS total FROM household_contributions hc INNER JOIN contribution_campaigns cc ON cc.id=hc.campaign_id INNER JOIN households h ON h.id=hc.household_id WHERE hc.status <> 'DELETED' AND cc.status='ACTIVE' AND hc.payment_status NOT IN ('PAID','EXEMPT') AND $tenantWhere", false, $tenant);
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, cc.contribution_name, hc.payment_status, hc.debt_amount FROM household_contributions hc INNER JOIN contribution_campaigns cc ON cc.id=hc.campaign_id INNER JOIN households h ON h.id=hc.household_id WHERE hc.status <> 'DELETED' AND cc.status='ACTIVE' AND hc.payment_status NOT IN ('PAID','EXEMPT') AND $tenantWhere ORDER BY hc.debt_amount DESC, h.household_code ASC LIMIT 20", $tenant);
        return ['answer' => "Co $total ho/khoan thu chua hoan thanh dong quy trong cac dot dang thu.", 'metrics' => ['total' => $total], 'items' => $rows];
    }

    private function answerOpenComplaints(): array
    {
        if (!$this->tableExists('complaints')) return $this->emptyAnswer('Chua co du lieu phan anh.');
        $where = 'soft_status <> "DELETED" AND closed_at IS NULL AND ' . $this->tenantWhere('complaints');
        $total = $this->countOne('SELECT COUNT(*) AS total FROM complaints WHERE ' . $where, false, $this->withTenant());
        $overdue = $this->countOne('SELECT COUNT(*) AS total FROM complaints WHERE ' . $where . ' AND due_at IS NOT NULL AND due_at < NOW()', false, $this->withTenant());
        $rows = $this->fetchAll('SELECT complaint_code, title, reporter_name, assigned_name, due_at, created_at FROM complaints WHERE ' . $where . ' ORDER BY due_at IS NULL ASC, due_at ASC, id DESC LIMIT 20', $this->withTenant());
        return ['answer' => "Co $total phan anh chua hoan tat, trong do $overdue phan anh da qua han.", 'metrics' => ['open' => $total, 'overdue' => $overdue], 'items' => $rows];
    }

    private function answerCitizensOver80(): array
    {
        if (!$this->tableExists('citizens')) return $this->emptyAnswer('Chua co du lieu nhan khau.');
        $where = "c.status <> 'DELETED' AND " . $this->tenantWhere('c', 'citizens') . " AND COALESCE(c.life_status,'ALIVE') <> 'DECEASED' AND c.date_of_birth IS NOT NULL AND TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 80";
        $total = $this->countOne("SELECT COUNT(*) AS total FROM citizens c WHERE $where", false, $this->withTenant());
        $rows = $this->fetchAll("SELECT c.full_name, c.date_of_birth, TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) AS age, h.household_code, h.address FROM citizens c LEFT JOIN households h ON h.id=c.household_id WHERE $where AND (h.id IS NULL OR " . $this->tenantWhere('h', 'households') . ") ORDER BY age DESC, c.full_name ASC LIMIT 20", $this->withTenant());
        return ['answer' => "Co $total nhan khau tu 80 tuoi tro len.", 'metrics' => ['total' => $total], 'items' => $rows];
    }

    private function answerMaintenanceDue(): array
    {
        if (!$this->tableExists('public_asset_maintenance_schedules')) return $this->emptyAnswer('Chua co lich bao tri cong trinh/tai san.');
        $where = "pams.deleted_at IS NULL AND pams.status='SCHEDULED' AND pams.scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND " . $this->tenantWhere('pams', 'public_asset_maintenance_schedules');
        $total = $this->countOne("SELECT COUNT(*) AS total FROM public_asset_maintenance_schedules pams WHERE $where", false, $this->withTenant());
        $rows = $this->fetchAll("SELECT pams.maintenance_code, pams.title, pams.scheduled_date, pams.manager_name, pa.asset_code, pa.asset_name FROM public_asset_maintenance_schedules pams INNER JOIN public_assets pa ON pa.id=pams.public_asset_id WHERE $where AND " . $this->tenantWhere('pa', 'public_assets') . " ORDER BY pams.scheduled_date ASC LIMIT 20", $this->withTenant());
        return ['answer' => "Co $total lich bao tri can theo doi trong 30 ngay toi.", 'metrics' => ['total' => $total], 'items' => $rows];
    }

    private function answerHouseholdsWithLivestock(): array
    {
        if (!$this->tableExists('livestock')) return $this->emptyAnswer('Chua co du lieu vat nuoi.');
        $total = $this->countOne('SELECT COUNT(*) AS total FROM (SELECT household_id FROM livestock WHERE status <> "DELETED" AND ' . $this->tenantWhere('livestock') . ' GROUP BY household_id) x', false, $this->withTenant());
        $rows = $this->fetchAll('SELECT h.household_code, h.head_citizen_name, h.address, COUNT(l.id) AS livestock_records, COALESCE(SUM(l.quantity),0) AS quantity FROM livestock l INNER JOIN households h ON h.id=l.household_id WHERE l.status <> "DELETED" AND ' . $this->tenantWhere('l', 'livestock') . ' AND ' . $this->tenantWhere('h', 'households') . ' GROUP BY h.id, h.household_code, h.head_citizen_name, h.address ORDER BY quantity DESC LIMIT 20', $this->withTenant());
        return ['answer' => "Co $total ho co ghi nhan vat nuoi.", 'metrics' => ['households' => $total], 'items' => $rows];
    }

    private function answerMonthlyMovements(): array
    {
        if (!$this->tableExists('movements')) return $this->emptyAnswer('Chua co du lieu bien dong.');
        $where = "status <> 'DELETED' AND " . $this->tenantWhere('movements') . " AND effective_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $total = $this->countOne("SELECT COUNT(*) AS total FROM movements WHERE $where", false, $this->withTenant());
        $rows = $this->fetchAll("SELECT type, COUNT(*) AS total FROM movements WHERE $where GROUP BY type ORDER BY total DESC", $this->withTenant());
        return ['answer' => "Thang nay co $total bien dong nhan khau.", 'metrics' => ['total' => $total], 'items' => $rows];
    }

    private function answerAnalytics(): array
    {
        $analytics = $this->analytics();
        return [
            'answer' => $analytics['summary'],
            'metrics' => $analytics['metrics'],
            'items' => $analytics['items'],
        ];
    }

    private function answerOverview(): array
    {
        return ['answer' => 'Tro ly du lieu hien ho tro cau hoi ve ho chua dong quy, phan anh chua xu ly, nhan khau tren 80 tuoi, cong trinh sap bao tri, ho co vat nuoi va bien dong thang nay.', 'metrics' => [], 'items' => []];
    }

    private function emptyAnswer(string $message): array
    {
        return ['answer' => $message, 'metrics' => [], 'items' => []];
    }

    private function analyticsSummary(array $items): string
    {
        if (!$items) return 'Chua phat hien bat thuong du lieu noi bat trong cac chi so dang theo doi.';
        $high = count(array_filter($items, fn($item) => $item['severity'] === 'high'));
        $total = array_sum(array_map(fn($item) => (int) $item['count'], $items));
        return "Phat hien $total van de du lieu can ra soat trong " . count($items) . " nhom, trong do co $high nhom uu tien cao.";
    }

    private function analyticsRules(): array
    {
        return [
            ['key' => 'invalid_identity', 'label' => 'CCCD/SĐD không hợp lệ', 'severity' => 'high', 'module' => 'citizen', 'screen' => 'persons', 'suggestion' => 'Rà soát định dạng CCCD/SĐD và cập nhật lại hồ sơ nhân khẩu.'],
            ['key' => 'duplicate_identity', 'label' => 'Trùng CCCD/SĐD', 'severity' => 'high', 'module' => 'citizen', 'screen' => 'persons', 'suggestion' => 'Đối chiếu các hồ sơ trùng số định danh trước khi nhập mới hoặc đồng bộ.'],
            ['key' => 'households_without_members', 'label' => 'Hộ chưa có thành viên', 'severity' => 'high', 'module' => 'household', 'screen' => 'households', 'suggestion' => 'Kiểm tra hộ chưa có thành viên và bổ sung chủ hộ/thành viên nếu hồ sơ còn hiệu lực.'],
            ['key' => 'missing_identity', 'label' => 'Nhân khẩu thiếu CCCD/SĐD', 'severity' => 'medium', 'module' => 'citizen', 'screen' => 'persons', 'suggestion' => 'Ưu tiên bổ sung CCCD/SĐD cho nhân khẩu đang cư trú.'],
            ['key' => 'missing_phone', 'label' => 'Nhân khẩu thiếu số điện thoại', 'severity' => 'low', 'module' => 'citizen', 'screen' => 'persons', 'suggestion' => 'Bổ sung số điện thoại liên hệ khi rà soát hồ sơ.'],
            ['key' => 'missing_area_code', 'label' => 'Hộ thiếu khu vực/xóm', 'severity' => 'medium', 'module' => 'household', 'screen' => 'households', 'suggestion' => 'Cập nhật khu vực/xóm để báo cáo và điều hành theo địa bàn chính xác hơn.'],
        ];
    }

    private function stripVietnamese(string $value): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return is_string($converted) ? $converted : $value;
    }

    private function countOne(string $sql, bool $countRows = false, array $params = []): int
    {
        if ($countRows) return count($this->fetchAll($sql, $params));
        return (int) ($this->fetchOne($sql, $params)['total'] ?? 0);
    }

    private function age(mixed $date): ?int
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $date)) return null;
        try { return (int) (new \DateTimeImmutable((string) $date))->diff(new \DateTimeImmutable('today'))->y; } catch (\Throwable) { return null; }
    }

    private function maskIdentity(string $identity): string
    {
        $identity = trim($identity);
        if (mb_strlen($identity) <= 8) return $identity;
        return mb_substr($identity, 0, 4) . '••••' . mb_substr($identity, -4);
    }
}
