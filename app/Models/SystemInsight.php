<?php

namespace App\Models;

use App\Core\BaseModel;

final class SystemInsight extends BaseModel
{
    public function requiredModulesForQuestion(string $question): array
    {
        return match ($this->intent($question)) {
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
             WHERE h.status <> 'DELETED'
               AND (h.household_code LIKE :q_code OR h.head_citizen_name LIKE :q_head OR h.address LIKE :q_address OR h.phone LIKE :q_phone OR h.area_code LIKE :q_area OR h.note LIKE :q_note)
             ORDER BY h.household_code
             LIMIT $limit",
            ['q_code' => $q, 'q_head' => $q, 'q_address' => $q, 'q_phone' => $q, 'q_area' => $q, 'q_note' => $q]
        );

        $citizens = $this->fetchAll(
            "SELECT c.id, c.citizen_code, c.full_name, c.identity_number, c.phone, c.gender, c.date_of_birth, c.relationship, c.residency_status, c.presence_status, c.life_status,
                    h.id AS household_id, h.household_code, h.address AS household_address,
                    'citizen' AS result_type
             FROM citizens c
             INNER JOIN households h ON h.id = c.household_id
             WHERE c.status <> 'DELETED'
               AND (c.citizen_code LIKE :q_code OR c.full_name LIKE :q_name OR c.identity_number LIKE :q_identity OR c.phone LIKE :q_phone OR h.household_code LIKE :q_household OR h.address LIKE :q_address)
             ORDER BY c.full_name
             LIMIT $limit",
            ['q_code' => $q, 'q_name' => $q, 'q_identity' => $q, 'q_phone' => $q, 'q_household' => $q, 'q_address' => $q]
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
            'missing_identity' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND (identity_number IS NULL OR identity_number = '')"),
            'missing_phone' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND (phone IS NULL OR phone = '')"),
            'invalid_identity' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND identity_number IS NOT NULL AND identity_number <> '' AND identity_number NOT REGEXP '^[0-9]{9,12}$'"),
            'duplicate_identity' => $this->countOne("SELECT COUNT(*) AS total FROM (SELECT identity_number FROM citizens WHERE status <> 'DELETED' AND identity_number IS NOT NULL AND identity_number <> '' GROUP BY identity_number HAVING COUNT(*) > 1) d"),
            'households_without_members' => $this->countOne("SELECT COUNT(*) AS total FROM households h LEFT JOIN citizens c ON c.household_id = h.id AND c.status <> 'DELETED' WHERE h.status <> 'DELETED' GROUP BY h.id HAVING COUNT(c.id) = 0", true),
            'missing_area_code' => $this->countOne("SELECT COUNT(*) AS total FROM households WHERE status <> 'DELETED' AND (area_code IS NULL OR area_code = '')"),
        ];
    }

    private function intent(string $question): string
    {
        $q = mb_strtolower($this->stripVietnamese($question), 'UTF-8');
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
        $total = $this->countOne("SELECT COUNT(*) AS total FROM household_contributions hc INNER JOIN contribution_campaigns cc ON cc.id=hc.campaign_id WHERE hc.status <> 'DELETED' AND cc.status='ACTIVE' AND hc.payment_status NOT IN ('PAID','EXEMPT')");
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, cc.contribution_name, hc.payment_status, hc.debt_amount FROM household_contributions hc INNER JOIN contribution_campaigns cc ON cc.id=hc.campaign_id INNER JOIN households h ON h.id=hc.household_id WHERE hc.status <> 'DELETED' AND cc.status='ACTIVE' AND hc.payment_status NOT IN ('PAID','EXEMPT') ORDER BY hc.debt_amount DESC, h.household_code ASC LIMIT 20");
        return ['answer' => "Co $total ho/khoan thu chua hoan thanh dong quy trong cac dot dang thu.", 'metrics' => ['total' => $total], 'items' => $rows];
    }

    private function answerOpenComplaints(): array
    {
        if (!$this->tableExists('complaints')) return $this->emptyAnswer('Chua co du lieu phan anh.');
        $total = $this->countOne('SELECT COUNT(*) AS total FROM complaints WHERE soft_status <> "DELETED" AND closed_at IS NULL');
        $overdue = $this->countOne('SELECT COUNT(*) AS total FROM complaints WHERE soft_status <> "DELETED" AND closed_at IS NULL AND due_at IS NOT NULL AND due_at < NOW()');
        $rows = $this->fetchAll('SELECT complaint_code, title, reporter_name, assigned_name, due_at, created_at FROM complaints WHERE soft_status <> "DELETED" AND closed_at IS NULL ORDER BY due_at IS NULL ASC, due_at ASC, id DESC LIMIT 20');
        return ['answer' => "Co $total phan anh chua hoan tat, trong do $overdue phan anh da qua han.", 'metrics' => ['open' => $total, 'overdue' => $overdue], 'items' => $rows];
    }

    private function answerCitizensOver80(): array
    {
        if (!$this->tableExists('citizens')) return $this->emptyAnswer('Chua co du lieu nhan khau.');
        $where = "c.status <> 'DELETED' AND COALESCE(c.life_status,'ALIVE') <> 'DECEASED' AND c.date_of_birth IS NOT NULL AND TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 80";
        $total = $this->countOne("SELECT COUNT(*) AS total FROM citizens c WHERE $where");
        $rows = $this->fetchAll("SELECT c.full_name, c.date_of_birth, TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) AS age, h.household_code, h.address FROM citizens c LEFT JOIN households h ON h.id=c.household_id WHERE $where ORDER BY age DESC, c.full_name ASC LIMIT 20");
        return ['answer' => "Co $total nhan khau tu 80 tuoi tro len.", 'metrics' => ['total' => $total], 'items' => $rows];
    }

    private function answerMaintenanceDue(): array
    {
        if (!$this->tableExists('public_asset_maintenance_schedules')) return $this->emptyAnswer('Chua co lich bao tri cong trinh/tai san.');
        $where = "pams.deleted_at IS NULL AND pams.status='SCHEDULED' AND pams.scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        $total = $this->countOne("SELECT COUNT(*) AS total FROM public_asset_maintenance_schedules pams WHERE $where");
        $rows = $this->fetchAll("SELECT pams.maintenance_code, pams.title, pams.scheduled_date, pams.manager_name, pa.asset_code, pa.asset_name FROM public_asset_maintenance_schedules pams INNER JOIN public_assets pa ON pa.id=pams.public_asset_id WHERE $where ORDER BY pams.scheduled_date ASC LIMIT 20");
        return ['answer' => "Co $total lich bao tri can theo doi trong 30 ngay toi.", 'metrics' => ['total' => $total], 'items' => $rows];
    }

    private function answerHouseholdsWithLivestock(): array
    {
        if (!$this->tableExists('livestock')) return $this->emptyAnswer('Chua co du lieu vat nuoi.');
        $total = $this->countOne('SELECT COUNT(*) AS total FROM (SELECT household_id FROM livestock WHERE status <> "DELETED" GROUP BY household_id) x');
        $rows = $this->fetchAll('SELECT h.household_code, h.head_citizen_name, h.address, COUNT(l.id) AS livestock_records, COALESCE(SUM(l.quantity),0) AS quantity FROM livestock l INNER JOIN households h ON h.id=l.household_id WHERE l.status <> "DELETED" GROUP BY h.id, h.household_code, h.head_citizen_name, h.address ORDER BY quantity DESC LIMIT 20');
        return ['answer' => "Co $total ho co ghi nhan vat nuoi.", 'metrics' => ['households' => $total], 'items' => $rows];
    }

    private function answerMonthlyMovements(): array
    {
        if (!$this->tableExists('movements')) return $this->emptyAnswer('Chua co du lieu bien dong.');
        $total = $this->countOne("SELECT COUNT(*) AS total FROM movements WHERE status <> 'DELETED' AND effective_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $rows = $this->fetchAll("SELECT type, COUNT(*) AS total FROM movements WHERE status <> 'DELETED' AND effective_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY type ORDER BY total DESC");
        return ['answer' => "Thang nay co $total bien dong nhan khau.", 'metrics' => ['total' => $total], 'items' => $rows];
    }

    private function answerOverview(): array
    {
        return ['answer' => 'Tro ly du lieu hien ho tro cau hoi ve ho chua dong quy, phan anh chua xu ly, nhan khau tren 80 tuoi, cong trinh sap bao tri, ho co vat nuoi va bien dong thang nay.', 'metrics' => [], 'items' => []];
    }

    private function emptyAnswer(string $message): array
    {
        return ['answer' => $message, 'metrics' => [], 'items' => []];
    }

    private function stripVietnamese(string $value): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return is_string($converted) ? $converted : $value;
    }

    private function countOne(string $sql, bool $countRows = false): int
    {
        if ($countRows) return count($this->fetchAll($sql));
        return (int) ($this->fetchOne($sql)['total'] ?? 0);
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
