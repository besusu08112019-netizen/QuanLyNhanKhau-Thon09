<?php

namespace App\Models;

use App\Core\BaseModel;

final class HouseholdContribution extends BaseModel
{
    public const CATEGORIES = ['Quỹ vệ sinh','Quỹ an ninh','Quỹ khuyến học','Đóng góp làm đường','Điện chiếu sáng','Nghĩa trang','Nhà văn hóa','Đóng góp khác'];
    public const REQUIRED_TYPES = ['REQUIRED' => 'Bắt buộc', 'VOLUNTARY' => 'Tự nguyện'];
    public const CAMPAIGN_STATUS = ['ACTIVE' => 'Đang thu', 'CLOSED' => 'Đã kết thúc', 'INACTIVE' => 'Tạm dừng', 'DELETED' => 'Đã xóa'];
    public const PAYMENT_STATUS = ['UNPAID' => 'Chưa nộp', 'PAID' => 'Đã nộp', 'PARTIAL' => 'Nộp một phần', 'EXEMPT' => 'Miễn', 'REDUCED' => 'Giảm'];
    public const PAYMENT_METHODS = ['CASH' => 'Tiền mặt', 'TRANSFER' => 'Chuyển khoản', 'OTHER' => 'Khác'];
    public const UNIT_TYPES = ['HOUSEHOLD' => 'Theo ho', 'PERSON' => 'Theo nhan khau', 'AREA' => 'Theo dien tich', 'VEHICLE' => 'Theo phuong tien', 'TIME' => 'Theo lan', 'OTHER' => 'Khac'];
    public const TARGET_OPTIONS = ['ALL_HOUSEHOLDS' => 'Thu toan bo ho', 'ALL_PERSONS' => 'Thu toan bo nhan khau', 'LABOR_AGE' => 'Nguoi trong do tuoi lao dong', 'NON_LABOR_AGE' => 'Nguoi ngoai do tuoi lao dong', 'AGE_RANGE' => 'Nguoi tu ... den ... tuoi', 'AGE_FROM' => 'Nguoi tu ... tuoi tro len', 'CHILDREN' => 'Tre em', 'ELDERLY' => 'Nguoi cao tuoi', 'OTHER' => 'Khac'];
    public const EXEMPTION_OPTIONS = ['NON_LABOR_AGE' => 'Nguoi ngoai do tuoi lao dong', 'CHILDREN' => 'Tre em', 'ELDERLY' => 'Nguoi cao tuoi', 'DISABLED' => 'Nguoi khuyet tat', 'MERITORIOUS' => 'Nguoi co cong', 'POOR_HOUSEHOLD' => 'Ho ngheo', 'NEAR_POOR_HOUSEHOLD' => 'Ho can ngheo', 'SOCIAL_ASSISTANCE' => 'Bao tro xa hoi', 'POLICY' => 'Doi tuong chinh sach', 'UBND_DECISION' => 'Mien theo quyet dinh UBND xa', 'HAMLET_DECISION' => 'Mien theo quyet dinh Truong thon', 'OTHER' => 'Khac'];
    private const LABOR_AGE = ['min' => 15, 'male_start_year' => 2021, 'male_start_months' => 723, 'male_step_months' => 3, 'male_max_months' => 744, 'female_start_year' => 2021, 'female_start_months' => 664, 'female_step_months' => 4, 'female_max_months' => 720];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS contribution_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_code VARCHAR(40) NULL,
  contribution_name VARCHAR(180) NOT NULL,
  contribution_type VARCHAR(120) NULL,
  required_type ENUM('REQUIRED','VOLUNTARY') NOT NULL DEFAULT 'REQUIRED',
  year SMALLINT UNSIGNED NOT NULL,
  period_name VARCHAR(80) NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  unit VARCHAR(40) NOT NULL DEFAULT 'VNĐ/hộ',
  start_date DATE NULL,
  due_date DATE NULL,
  note TEXT NULL,
  status ENUM('ACTIVE','CLOSED','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_contribution_campaign_code (campaign_code),
  KEY idx_contribution_campaign_year (year),
  KEY idx_contribution_campaign_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS household_contributions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  household_id BIGINT UNSIGNED NOT NULL,
  payment_status ENUM('UNPAID','PAID','PARTIAL','EXEMPT','REDUCED') NOT NULL DEFAULT 'UNPAID',
  expected_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  debt_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_at DATE NULL,
  collector_name VARCHAR(180) NULL,
  payment_method ENUM('CASH','TRANSFER','OTHER') NOT NULL DEFAULT 'CASH',
  receipt_number VARCHAR(80) NULL,
  note TEXT NULL,
  status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uniq_household_contribution (campaign_id, household_id),
  KEY idx_household_contributions_household (household_id),
  KEY idx_household_contributions_status (payment_status),
  CONSTRAINT fk_contribution_campaign FOREIGN KEY (campaign_id) REFERENCES contribution_campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_contribution_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS contribution_payment_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contribution_id BIGINT UNSIGNED NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  household_id BIGINT UNSIGNED NOT NULL,
  paid_at DATE NULL,
  actor_id BIGINT UNSIGNED NULL,
  collector_name VARCHAR(180) NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_status VARCHAR(40) NULL,
  payment_method VARCHAR(40) NULL,
  receipt_number VARCHAR(80) NULL,
  content TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_contribution_history_campaign (campaign_id),
  KEY idx_contribution_history_household (household_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureColumns();
    }

    public function catalogs(): array
    {
        return [
            'categories' => array_map(fn($v) => ['value' => $v, 'label' => $v], self::CATEGORIES),
            'required_types' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::REQUIRED_TYPES), array_values(self::REQUIRED_TYPES)),
            'campaign_statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::CAMPAIGN_STATUS), array_values(self::CAMPAIGN_STATUS)),
            'payment_statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::PAYMENT_STATUS), array_values(self::PAYMENT_STATUS)),
            'payment_methods' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::PAYMENT_METHODS), array_values(self::PAYMENT_METHODS)),
            'unit_types' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::UNIT_TYPES), array_values(self::UNIT_TYPES)),
            'target_options' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::TARGET_OPTIONS), array_values(self::TARGET_OPTIONS)),
            'exemption_options' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::EXEMPTION_OPTIONS), array_values(self::EXEMPTION_OPTIONS)),
        ];
    }

    public function campaigns(array $filters): array
    {
        $this->ensureSchema();
        $this->syncActiveCampaigns();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->campaignWhere($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM contribution_campaigns c $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT c.*,
                COUNT(hc.id) AS tracking_count,
                COALESCE(SUM(CASE WHEN hc.payment_status='PAID' AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS paid_households,
                COALESCE(SUM(CASE WHEN hc.payment_status='PARTIAL' AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS partial_households,
                COALESCE(SUM(CASE WHEN hc.payment_status IN ('EXEMPT','REDUCED') AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS exempt_households,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.eligible_count ELSE 0 END),0) AS eligible_count,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.exempt_count ELSE 0 END),0) AS exempt_count,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.chargeable_count ELSE 0 END),0) AS chargeable_count,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.expected_amount ELSE 0 END),0) AS expected_total,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.paid_amount ELSE 0 END),0) AS collected_amount,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.debt_amount ELSE 0 END),0) AS debt_amount
             FROM contribution_campaigns c
             LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE'
             $where GROUP BY c.id $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalizeCampaign($row), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function findCampaign(int $id): ?array
    {
        $this->ensureSchema();
        if ($id > 0) $this->syncCampaign($id, false);
        $row = $this->fetchOne('SELECT c.* FROM contribution_campaigns c WHERE c.id=:id AND c.status <> "DELETED"', ['id' => $id]);
        return $row ? $this->normalizeCampaign($row) : null;
    }

    public function upsertCampaign(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->campaignParams($data, $userId);
        if ($id && !$this->findCampaign($id)) throw new \RuntimeException('Không tìm thấy đợt thu');
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE contribution_campaigns SET contribution_name=:contribution_name, contribution_type=:contribution_type, required_type=:required_type, year=:year, period_name=:period_name, amount=:amount, unit=:unit, unit_type=:unit_type, target_config_json=:target_config_json, exemption_config_json=:exemption_config_json, start_date=:start_date, due_date=:due_date, note=:note, status=:status, updated_by=:user WHERE id=:id', $params);
            $this->syncCampaign($id);
            return $this->findCampaign($id);
        }
        $insertParams = $params + ['campaign_code' => $this->nextCampaignCode(), 'created_by' => $userId, 'updated_by' => $userId];
        unset($insertParams['user']);
        $newId = $this->insert('INSERT INTO contribution_campaigns (campaign_code, contribution_name, contribution_type, required_type, year, period_name, amount, unit, unit_type, target_config_json, exemption_config_json, start_date, due_date, note, status, created_by, updated_by) VALUES (:campaign_code,:contribution_name,:contribution_type,:required_type,:year,:period_name,:amount,:unit,:unit_type,:target_config_json,:exemption_config_json,:start_date,:due_date,:note,:status,:created_by,:updated_by)', $insertParams);
        $this->syncCampaign($newId);
        return $this->findCampaign($newId);
    }

    public function deleteCampaign(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->findCampaign($id)) throw new \RuntimeException('Không tìm thấy đợt thu');
        $this->execute('UPDATE contribution_campaigns SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id', ['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]);
    }

    public function tracking(int $campaignId, array $filters): array
    {
        $this->ensureSchema();
        $campaign = $this->findCampaign($campaignId);
        if (!$campaign) throw new \RuntimeException('Không tìm thấy đợt thu');
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->trackingWhere($campaignId, $filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM households h LEFT JOIN household_contributions hc ON hc.household_id=h.id AND hc.campaign_id=:campaign_id AND hc.status='ACTIVE' $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT h.id AS household_id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code,
                hc.id, hc.campaign_id, COALESCE(hc.payment_status,'UNPAID') AS payment_status,
                COALESCE(hc.expected_amount, 0) AS expected_amount,
                COALESCE(hc.paid_amount,0) AS paid_amount, COALESCE(hc.discount_amount,0) AS discount_amount,
                GREATEST(COALESCE(hc.debt_amount, 0),0) AS debt_amount,
                COALESCE(hc.amount,0) AS amount, hc.paid_at, hc.collector_name, COALESCE(hc.payment_method,'CASH') AS payment_method,
                hc.receipt_number, hc.note, COALESCE(hc.eligible_count,0) AS eligible_count, COALESCE(hc.exempt_count,0) AS exempt_count, COALESCE(hc.chargeable_count,0) AS chargeable_count, hc.calculation_note, hc.created_at, hc.updated_at
             FROM households h
             LEFT JOIN household_contributions hc ON hc.household_id=h.id AND hc.campaign_id=:campaign_id AND hc.status='ACTIVE'
             $where $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalizeTracking($row, $campaign), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function upsertTracking(int $campaignId, int $householdId, array $data, int $userId): array
    {
        $this->ensureSchema();
        $campaign = $this->findCampaign($campaignId);
        if (!$campaign) throw new \RuntimeException('Không tìm thấy đợt thu');
        if (!$this->fetchOne('SELECT id FROM households WHERE id=:id AND status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', ['id' => $householdId])) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $status = strtoupper(trim((string) ($data['payment_status'] ?? $data['paymentStatus'] ?? 'UNPAID')));
        if (!isset(self::PAYMENT_STATUS[$status])) $status = 'UNPAID';
        $method = strtoupper(trim((string) ($data['payment_method'] ?? $data['paymentMethod'] ?? 'CASH')));
        if (!isset(self::PAYMENT_METHODS[$method])) $method = 'CASH';
        $expected = max(0, (float) ($data['expected_amount'] ?? $data['expectedAmount'] ?? $campaign['amount']));
        $paid = max(0, (float) ($data['paid_amount'] ?? $data['paidAmount'] ?? $data['amount'] ?? 0));
        $paymentAmount = max(0, (float) ($data['payment_amount'] ?? $data['paymentAmount'] ?? 0));
        if ($paymentAmount > 0) {
            $current = $this->fetchOne('SELECT paid_amount FROM household_contributions WHERE campaign_id=:campaign_id AND household_id=:household_id AND status="ACTIVE"', ['campaign_id' => $campaignId, 'household_id' => $householdId]) ?: [];
            $paid = (float) ($current['paid_amount'] ?? 0) + $paymentAmount;
        }
        $discount = max(0, (float) ($data['discount_amount'] ?? $data['discountAmount'] ?? 0));
        if ($status === 'EXEMPT') { $discount = $expected; $paid = 0; }
        if ($status === 'PAID' && $paid <= 0) $paid = max(0, $expected - $discount);
        $debt = max(0, $expected - $paid - $discount);
        if ($status === 'PAID') $debt = 0;
        $params = [
            'campaign_id' => $campaignId,
            'household_id' => $householdId,
            'payment_status' => $status,
            'expected_amount' => $expected,
            'paid_amount' => $paid,
            'discount_amount' => $discount,
            'debt_amount' => $debt,
            'amount' => $paymentAmount > 0 ? $paymentAmount : $paid,
            'paid_at' => trim((string) ($data['paid_at'] ?? $data['paidAt'] ?? '')) ?: null,
            'collector_name' => trim((string) ($data['collector_name'] ?? $data['collectorName'] ?? '')) ?: null,
            'payment_method' => $method,
            'receipt_number' => trim((string) ($data['receipt_number'] ?? $data['receiptNumber'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'user' => $userId,
        ];
        $this->execute(
            'INSERT INTO household_contributions (campaign_id, household_id, payment_status, expected_amount, paid_amount, discount_amount, debt_amount, amount, paid_at, collector_name, payment_method, receipt_number, note, created_by, updated_by)
             VALUES (:campaign_id,:household_id,:payment_status,:expected_amount,:paid_amount,:discount_amount,:debt_amount,:amount,:paid_at,:collector_name,:payment_method,:receipt_number,:note,:user,:user)
             ON DUPLICATE KEY UPDATE payment_status=VALUES(payment_status), expected_amount=VALUES(expected_amount), paid_amount=VALUES(paid_amount), discount_amount=VALUES(discount_amount), debt_amount=VALUES(debt_amount), amount=VALUES(amount), paid_at=VALUES(paid_at), collector_name=VALUES(collector_name), payment_method=VALUES(payment_method), receipt_number=VALUES(receipt_number), note=VALUES(note), status="ACTIVE", updated_by=VALUES(updated_by), deleted_at=NULL, deleted_by=NULL',
            $params
        );
        $row = $this->tracking($campaignId, ['household_id' => $householdId, 'pageSize' => 1])['items'][0] ?? [];
        $this->writeHistory($row['id'] ?? null, $campaignId, $householdId, $params, $userId);
        return $row;
    }

    public function history(int $campaignId, int $householdId): array
    {
        $this->ensureSchema();
        return $this->fetchAll('SELECT * FROM contribution_payment_history WHERE campaign_id=:campaign_id AND household_id=:household_id ORDER BY created_at DESC, id DESC LIMIT 100', ['campaign_id' => $campaignId, 'household_id' => $householdId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        $this->syncActiveCampaigns();
        $households = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households WHERE status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")') ?: [])['total'] ?? 0);
        $citizens = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.status="ACTIVE" AND COALESCE(c.life_status,"ALIVE") <> "DECEASED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")') ?: [])['total'] ?? 0);
        $pay = $this->fetchOne(
            "SELECT COUNT(*) AS records,
                COALESCE(SUM(CASE WHEN hc.payment_status='PAID' THEN 1 ELSE 0 END),0) AS paid,
                COALESCE(SUM(CASE WHEN hc.payment_status='UNPAID' THEN 1 ELSE 0 END),0) AS unpaid,
                COALESCE(SUM(CASE WHEN hc.payment_status='PARTIAL' THEN 1 ELSE 0 END),0) AS partial,
                COALESCE(SUM(hc.eligible_count),0) AS eligible_count,
                COALESCE(SUM(hc.exempt_count),0) AS exempt_count,
                COALESCE(SUM(hc.chargeable_count),0) AS chargeable_count,
                COALESCE(SUM(hc.expected_amount),0) AS expected_total,
                COALESCE(SUM(hc.paid_amount),0) AS collected,
                COALESCE(SUM(hc.debt_amount),0) AS debt
             FROM household_contributions hc INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id
             WHERE hc.status='ACTIVE' AND c.status <> 'DELETED'",
            []
        ) ?: [];
        $expected = (float) ($pay['expected_total'] ?? 0);
        $collected = (float) ($pay['collected'] ?? 0);
        return ['households' => $households, 'citizens' => $citizens, 'eligible_count' => (int) ($pay['eligible_count'] ?? 0), 'exempt_count' => (int) ($pay['exempt_count'] ?? 0), 'chargeable_count' => (int) ($pay['chargeable_count'] ?? 0), 'paid' => (int) ($pay['paid'] ?? 0), 'unpaid' => (int) ($pay['unpaid'] ?? 0), 'partial' => (int) ($pay['partial'] ?? 0), 'expected_total' => $expected, 'collected' => $collected, 'debt' => (float) ($pay['debt'] ?? 0), 'completion_rate' => $expected > 0 ? round($collected * 100 / $expected, 2) : 0];
    }

    public function charts(array $filters = []): array
    {
        $this->ensureSchema();
        return [
            'by_status' => $this->fetchAll("SELECT hc.payment_status AS label, COUNT(*) AS value FROM household_contributions hc INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id WHERE hc.status='ACTIVE' AND c.status <> 'DELETED' GROUP BY hc.payment_status ORDER BY value DESC"),
            'by_year' => $this->fetchAll("SELECT c.year AS label, COALESCE(SUM(hc.paid_amount),0) AS value FROM contribution_campaigns c LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE' WHERE c.status <> 'DELETED' GROUP BY c.year ORDER BY c.year DESC LIMIT 10"),
            'by_campaign' => $this->fetchAll("SELECT c.contribution_name AS label, COALESCE(SUM(hc.paid_amount),0) AS value FROM contribution_campaigns c LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE' WHERE c.status <> 'DELETED' GROUP BY c.id, c.contribution_name ORDER BY value DESC LIMIT 10"),
            'by_period' => $this->fetchAll("SELECT COALESCE(NULLIF(c.period_name,''),'Chưa phân đợt') AS label, COALESCE(SUM(hc.paid_amount),0) AS value FROM contribution_campaigns c LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE' WHERE c.status <> 'DELETED' GROUP BY label ORDER BY value DESC LIMIT 10"),
        ];
    }

    public function searchHouseholds(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll('SELECT id, household_code, head_citizen_name, address, phone FROM households WHERE status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") AND (LOWER(household_code) LIKE :code OR LOWER(head_citizen_name) LIKE :head OR LOWER(address) LIKE :address) ORDER BY household_code LIMIT ' . max(1, min(20, $limit)), ['code' => $keyword, 'head' => $keyword, 'address' => $keyword]);
        return array_map(fn($r) => ['id' => (int) $r['id'], 'household_code' => (string) $r['household_code'], 'head_citizen_name' => (string) $r['head_citizen_name'], 'address' => (string) ($r['address'] ?? ''), 'phone' => (string) ($r['phone'] ?? '')], $rows);
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'paid') $filters['payment_status'] = 'PAID';
        if ($mode === 'unpaid' || $mode === 'debt') $filters['payment_status'] = 'UNPAID';
        $campaignId = (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0);
        if ($campaignId > 0) {
            $rows = $this->tracking($campaignId, $filters + ['pageSize' => 100])['items'];
            return $this->table('Báo cáo đóng góp hộ', ['Mã hộ','Chủ hộ','Khoản thu','Phải nộp','Đã nộp','Còn nợ','Trạng thái','Ngày thu','Người thu','Hình thức','Biên lai','Ghi chú'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['contribution_name'], $r['expected_amount'], $r['paid_amount'], $r['debt_amount'], $r['payment_status_label'], $r['paid_at'], $r['collector_name'], $r['payment_method_label'], $r['receipt_number'], $r['note']], $rows), $filters);
        }
        $rows = $this->campaigns($filters + ['pageSize' => 100])['items'];
        return $this->table('Danh sách đợt thu', ['Mã khoản','Khoản thu','Loại','Tính chất','Năm','Đợt','Mức thu','Đơn vị','Bắt đầu','Hạn thu','Phải thu','Đã thu','Còn nợ','Trạng thái'], array_map(fn($r) => [$r['campaign_code'], $r['contribution_name'], $r['contribution_type'], $r['required_type_label'], $r['year'], $r['period_name'], $r['amount'], $r['unit'], $r['start_date'], $r['due_date'], $r['expected_total'], $r['collected_amount'], $r['debt_amount'], $r['status_label']], $rows), $filters);
    }

    private function campaignWhere(array $filters, bool $withOrder = true): array
    {
        $where = ['c.status <> "DELETED"'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(LOWER(c.campaign_code) LIKE :search OR LOWER(c.contribution_name) LIKE :search OR LOWER(c.contribution_type) LIKE :search OR LOWER(c.period_name) LIKE :search OR LOWER(c.note) LIKE :search)'; $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%'; }
        $year = (int) ($filters['year'] ?? 0);
        if ($year > 0) { $where[] = 'c.year = :year'; $params['year'] = $year; }
        foreach (['status' => self::CAMPAIGN_STATUS, 'required_type' => self::REQUIRED_TYPES] as $key => $allowed) {
            $value = strtoupper(trim((string) ($filters[$key] ?? $filters[lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))))] ?? '')));
            if ($value !== '' && isset($allowed[$value])) { $where[] = "c.$key = :$key"; $params[$key] = $value; }
        }
        return ['WHERE ' . implode(' AND ', $where), $params, 'ORDER BY c.year DESC, c.id DESC'];
    }

    private function trackingWhere(int $campaignId, array $filters): array
    {
        $where = ['h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = ['campaign_id' => $campaignId];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(LOWER(h.household_code) LIKE :search OR LOWER(h.head_citizen_name) LIKE :search OR LOWER(h.address) LIKE :search OR LOWER(hc.receipt_number) LIKE :search)'; $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%'; }
        $householdId = (int) ($filters['household_id'] ?? $filters['householdId'] ?? 0);
        if ($householdId > 0) { $where[] = 'h.id = :household_id'; $params['household_id'] = $householdId; }
        $payment = strtoupper(trim((string) ($filters['payment_status'] ?? $filters['paymentStatus'] ?? '')));
        if ($payment !== '' && isset(self::PAYMENT_STATUS[$payment])) {
            $where[] = $payment === 'UNPAID' ? 'COALESCE(hc.payment_status,"UNPAID") = "UNPAID"' : 'hc.payment_status = :payment_status';
            if ($payment !== 'UNPAID') $params['payment_status'] = $payment;
        }
        $area = trim((string) ($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') { $where[] = 'h.area_code = :area_code'; $params['area_code'] = $area; }
        return ['WHERE ' . implode(' AND ', $where), $params, 'ORDER BY h.household_code ASC'];
    }

    private function campaignParams(array $data, int $userId): array
    {
        $name = trim((string) ($data['contribution_name'] ?? $data['contributionName'] ?? ''));
        if ($name === '') throw new \RuntimeException('Ten khoan thu la bat buoc');
        $year = (int) ($data['year'] ?? date('Y'));
        if ($year < 2000 || $year > (int) date('Y') + 2) throw new \RuntimeException('Nam thu khong hop le');
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::CAMPAIGN_STATUS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        $required = strtoupper(trim((string) ($data['required_type'] ?? $data['requiredType'] ?? 'REQUIRED')));
        if (!isset(self::REQUIRED_TYPES[$required])) $required = 'REQUIRED';
        $unitType = strtoupper(trim((string) ($data['unit_type'] ?? $data['unitType'] ?? 'HOUSEHOLD')));
        if (!isset(self::UNIT_TYPES[$unitType])) $unitType = 'HOUSEHOLD';
        $targetConfig = $this->parseConfig($data, 'target', ['mode' => 'ALL_HOUSEHOLDS', 'age_from' => null, 'age_to' => null, 'note' => '']);
        $exemptionConfig = $this->parseConfig($data, 'exemption', ['conditions' => [], 'note' => '']);
        return ['contribution_name' => $name, 'contribution_type' => trim((string) ($data['contribution_type'] ?? $data['contributionType'] ?? '')) ?: null, 'required_type' => $required, 'year' => $year, 'period_name' => trim((string) ($data['period_name'] ?? $data['periodName'] ?? '')) ?: null, 'amount' => max(0, (float) ($data['amount'] ?? 0)), 'unit' => trim((string) ($data['unit'] ?? 'VND/ho')) ?: 'VND/ho', 'unit_type' => $unitType, 'target_config_json' => json_encode($targetConfig, JSON_UNESCAPED_UNICODE), 'exemption_config_json' => json_encode($exemptionConfig, JSON_UNESCAPED_UNICODE), 'start_date' => trim((string) ($data['start_date'] ?? $data['startDate'] ?? '')) ?: null, 'due_date' => trim((string) ($data['due_date'] ?? $data['dueDate'] ?? '')) ?: null, 'note' => trim((string) ($data['note'] ?? '')) ?: null, 'status' => $status, 'user' => $userId];
    }

    private function normalizeCampaign(array $row): array
    {
        $status = (string) ($row['status'] ?? 'ACTIVE');
        $required = (string) ($row['required_type'] ?? 'REQUIRED');
        $unitType = (string) ($row['unit_type'] ?? 'HOUSEHOLD');
        $targetConfig = $this->decodeConfig($row['target_config_json'] ?? null, ['mode' => 'ALL_HOUSEHOLDS', 'age_from' => null, 'age_to' => null, 'note' => '']);
        $exemptionConfig = $this->decodeConfig($row['exemption_config_json'] ?? null, ['conditions' => [], 'note' => '']);
        return ['id' => (int) $row['id'], 'campaign_code' => (string) ($row['campaign_code'] ?? ''), 'contribution_name' => (string) $row['contribution_name'], 'contribution_type' => (string) ($row['contribution_type'] ?? ''), 'required_type' => $required, 'required_type_label' => self::REQUIRED_TYPES[$required] ?? $required, 'year' => (int) $row['year'], 'period_name' => (string) ($row['period_name'] ?? ''), 'amount' => (float) ($row['amount'] ?? 0), 'unit' => (string) ($row['unit'] ?? 'VND/ho'), 'unit_type' => $unitType, 'unit_type_label' => self::UNIT_TYPES[$unitType] ?? $unitType, 'target_config' => $targetConfig, 'exemption_config' => $exemptionConfig, 'target_config_json' => json_encode($targetConfig, JSON_UNESCAPED_UNICODE), 'exemption_config_json' => json_encode($exemptionConfig, JSON_UNESCAPED_UNICODE), 'start_date' => $row['start_date'] ?? null, 'due_date' => $row['due_date'] ?? null, 'note' => (string) ($row['note'] ?? ''), 'status' => $status, 'status_label' => self::CAMPAIGN_STATUS[$status] ?? $status, 'tracking_count' => (int) ($row['tracking_count'] ?? 0), 'paid_households' => (int) ($row['paid_households'] ?? 0), 'partial_households' => (int) ($row['partial_households'] ?? 0), 'exempt_households' => (int) ($row['exempt_households'] ?? 0), 'eligible_count' => (int) ($row['eligible_count'] ?? 0), 'exempt_count' => (int) ($row['exempt_count'] ?? 0), 'chargeable_count' => (int) ($row['chargeable_count'] ?? 0), 'expected_total' => (float) ($row['expected_total'] ?? 0), 'collected_amount' => (float) ($row['collected_amount'] ?? 0), 'debt_amount' => (float) ($row['debt_amount'] ?? 0), 'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null];
    }

    private function normalizeTracking(array $row, array $campaign): array
    {
        $payment = (string) ($row['payment_status'] ?? 'UNPAID');
        $method = (string) ($row['payment_method'] ?? 'CASH');
        return ['id' => isset($row['id']) ? (int) $row['id'] : null, 'campaign_id' => (int) ($row['campaign_id'] ?? $campaign['id']), 'campaign_code' => (string) ($campaign['campaign_code'] ?? ''), 'contribution_name' => (string) ($campaign['contribution_name'] ?? ''), 'household_id' => (int) $row['household_id'], 'household_code' => (string) $row['household_code'], 'head_citizen_name' => (string) $row['head_citizen_name'], 'address' => (string) ($row['address'] ?? ''), 'phone' => (string) ($row['phone'] ?? ''), 'area_code' => (string) ($row['area_code'] ?? ''), 'eligible_count' => (int) ($row['eligible_count'] ?? 0), 'exempt_count' => (int) ($row['exempt_count'] ?? 0), 'chargeable_count' => (int) ($row['chargeable_count'] ?? 0), 'calculation_note' => (string) ($row['calculation_note'] ?? ''), 'expected_amount' => (float) ($row['expected_amount'] ?? 0), 'paid_amount' => (float) ($row['paid_amount'] ?? 0), 'discount_amount' => (float) ($row['discount_amount'] ?? 0), 'debt_amount' => (float) ($row['debt_amount'] ?? 0), 'amount' => (float) ($row['amount'] ?? 0), 'payment_status' => $payment, 'payment_status_label' => self::PAYMENT_STATUS[$payment] ?? $payment, 'paid_at' => $row['paid_at'] ?? null, 'collector_name' => (string) ($row['collector_name'] ?? ''), 'payment_method' => $method, 'payment_method_label' => self::PAYMENT_METHODS[$method] ?? $method, 'receipt_number' => (string) ($row['receipt_number'] ?? ''), 'note' => (string) ($row['note'] ?? ''), 'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null];
    }

    private function writeHistory(?int $contributionId, int $campaignId, int $householdId, array $params, int $userId): void
    {
        $this->execute('INSERT INTO contribution_payment_history (contribution_id, campaign_id, household_id, paid_at, actor_id, collector_name, amount, payment_status, payment_method, receipt_number, content) VALUES (:contribution_id,:campaign_id,:household_id,:paid_at,:actor_id,:collector_name,:amount,:payment_status,:payment_method,:receipt_number,:content)', ['contribution_id' => $contributionId, 'campaign_id' => $campaignId, 'household_id' => $householdId, 'paid_at' => $params['paid_at'], 'actor_id' => $userId, 'collector_name' => $params['collector_name'], 'amount' => $params['paid_amount'], 'payment_status' => $params['payment_status'], 'payment_method' => $params['payment_method'], 'receipt_number' => $params['receipt_number'], 'content' => $params['note']]);
    }

    private function syncActiveCampaigns(): void
    {
        $ids = $this->fetchAll('SELECT id FROM contribution_campaigns WHERE status="ACTIVE" ORDER BY id DESC LIMIT 20');
        foreach ($ids as $row) $this->syncCampaign((int) $row['id'], false);
    }

    private function syncCampaign(int $campaignId, bool $ensure = true): void
    {
        if ($ensure) $this->ensureSchema();
        $campaign = $this->fetchOne('SELECT * FROM contribution_campaigns WHERE id=:id AND status <> "DELETED"', ['id' => $campaignId]);
        if (!$campaign) return;
        $households = $this->fetchAll('SELECT h.* FROM households h WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") ORDER BY h.household_code');
        foreach ($households as $household) {
            $calc = $this->calculateHousehold((array) $campaign, $household);
            $existing = $this->fetchOne('SELECT id, paid_amount, discount_amount, payment_status FROM household_contributions WHERE campaign_id=:campaign_id AND household_id=:household_id AND status="ACTIVE"', ['campaign_id' => $campaignId, 'household_id' => (int) $household['id']]) ?: [];
            $paid = (float) ($existing['paid_amount'] ?? 0);
            $discount = (float) ($existing['discount_amount'] ?? 0);
            $status = (string) ($existing['payment_status'] ?? 'UNPAID');
            if ($calc['expected_amount'] <= 0) { $status = 'EXEMPT'; $discount = 0; $paid = 0; }
            elseif ($paid > 0 && $paid < $calc['expected_amount']) $status = 'PARTIAL';
            elseif ($paid >= $calc['expected_amount']) $status = 'PAID';
            $debt = max(0, $calc['expected_amount'] - $paid - $discount);
            if ($status === 'PAID') $debt = 0;
            $params = ['campaign_id' => $campaignId, 'household_id' => (int) $household['id'], 'payment_status' => $status, 'expected_amount' => $calc['expected_amount'], 'paid_amount' => $paid, 'discount_amount' => min($discount, $calc['expected_amount']), 'debt_amount' => $debt, 'eligible_count' => $calc['eligible_count'], 'exempt_count' => $calc['exempt_count'], 'chargeable_count' => $calc['chargeable_count'], 'calculation_note' => $calc['calculation_note']];
            $this->execute('INSERT INTO household_contributions (campaign_id, household_id, payment_status, expected_amount, paid_amount, discount_amount, debt_amount, amount, eligible_count, exempt_count, chargeable_count, calculation_note)
                VALUES (:campaign_id,:household_id,:payment_status,:expected_amount,:paid_amount,:discount_amount,:debt_amount,0,:eligible_count,:exempt_count,:chargeable_count,:calculation_note)
                ON DUPLICATE KEY UPDATE expected_amount=VALUES(expected_amount), discount_amount=LEAST(discount_amount, VALUES(expected_amount)), debt_amount=GREATEST(VALUES(expected_amount)-paid_amount-discount_amount,0), eligible_count=VALUES(eligible_count), exempt_count=VALUES(exempt_count), chargeable_count=VALUES(chargeable_count), calculation_note=VALUES(calculation_note), payment_status=IF(VALUES(expected_amount)=0,"EXEMPT",IF(discount_amount>=VALUES(expected_amount),"EXEMPT",IF(paid_amount+discount_amount>=VALUES(expected_amount),IF(discount_amount>0,"REDUCED","PAID"),IF(paid_amount>0,"PARTIAL","UNPAID")))), updated_at=NOW()', $params);
        }
    }

    private function calculateHousehold(array $campaign, array $household): array
    {
        $target = $this->decodeConfig($campaign['target_config_json'] ?? null, ['mode' => 'ALL_HOUSEHOLDS']);
        $exemption = $this->decodeConfig($campaign['exemption_config_json'] ?? null, ['conditions' => []]);
        $members = $this->householdMembers((int) $household['id']);
        $eligible = 0; $exempt = 0; $chargeable = 0;
        foreach ($members as $member) {
            $isEligible = $this->matchesTarget($member, $target);
            $isExempt = $isEligible && $this->matchesExemption($member, $household, $exemption);
            if ($isEligible) $eligible++;
            if ($isExempt) $exempt++;
            if ($isEligible && !$isExempt) $chargeable++;
        }
        $unitType = (string) ($campaign['unit_type'] ?? 'HOUSEHOLD');
        $baseAmount = (float) ($campaign['amount'] ?? 0);
        $expected = match ($unitType) {
            'PERSON' => $baseAmount * $chargeable,
            default => $chargeable > 0 || (($target['mode'] ?? '') === 'ALL_HOUSEHOLDS' && !$this->householdExempt($household, $exemption)) ? $baseAmount : 0,
        };
        if (($target['mode'] ?? '') === 'ALL_HOUSEHOLDS') {
            $eligible = count($members);
            $exempt = $this->householdExempt($household, $exemption) ? $eligible : $exempt;
            $chargeable = $this->householdExempt($household, $exemption) ? 0 : max(1, $eligible);
            $expected = $this->householdExempt($household, $exemption) ? 0 : $baseAmount;
        }
        return ['eligible_count' => $eligible, 'exempt_count' => $exempt, 'chargeable_count' => $chargeable, 'expected_amount' => round($expected, 2), 'calculation_note' => json_encode(['target' => $target, 'exemption' => $exemption], JSON_UNESCAPED_UNICODE)];
    }

    private function householdMembers(int $householdId): array
    {
        $columns = ['c.id','c.full_name','c.gender','c.date_of_birth','c.life_status','c.status'];
        foreach ($this->existingColumns('citizens', ['disabled_person','meritorious_person','social_assistance','martyr_relative','wounded_soldier','sick_soldier']) as $column) $columns[] = 'c.' . $column;
        return $this->fetchAll('SELECT ' . implode(',', $columns) . ' FROM citizens c WHERE c.household_id=:household_id AND c.status="ACTIVE" AND COALESCE(c.life_status,"ALIVE") <> "DECEASED"', ['household_id' => $householdId]);
    }

    private function matchesTarget(array $member, array $target): bool
    {
        $mode = strtoupper((string) ($target['mode'] ?? 'ALL_HOUSEHOLDS'));
        $age = $this->ageYears($member['date_of_birth'] ?? null);
        return match ($mode) {
            'ALL_PERSONS' => true,
            'LABOR_AGE' => $this->isLaborAge($member),
            'NON_LABOR_AGE' => !$this->isLaborAge($member),
            'AGE_RANGE' => $age >= (int) ($target['age_from'] ?? 0) && $age <= (int) ($target['age_to'] ?? 200),
            'AGE_FROM' => $age >= (int) ($target['age_from'] ?? 0),
            'CHILDREN' => $age < 16,
            'ELDERLY' => $age >= 60,
            'OTHER' => true,
            'ALL_HOUSEHOLDS' => true,
            default => true,
        };
    }

    private function matchesExemption(array $member, array $household, array $exemption): bool
    {
        $conditions = array_map('strtoupper', (array) ($exemption['conditions'] ?? []));
        if (!$conditions) return false;
        $age = $this->ageYears($member['date_of_birth'] ?? null);
        foreach ($conditions as $condition) {
            if ($condition === 'NON_LABOR_AGE' && !$this->isLaborAge($member)) return true;
            if ($condition === 'CHILDREN' && $age < 16) return true;
            if ($condition === 'ELDERLY' && $age >= 60) return true;
            if ($condition === 'DISABLED' && (int) ($member['disabled_person'] ?? 0) === 1) return true;
            if ($condition === 'MERITORIOUS' && ((int) ($member['meritorious_person'] ?? 0) === 1 || (int) ($member['martyr_relative'] ?? 0) === 1 || (int) ($member['wounded_soldier'] ?? 0) === 1 || (int) ($member['sick_soldier'] ?? 0) === 1)) return true;
            if ($condition === 'SOCIAL_ASSISTANCE' && (int) ($member['social_assistance'] ?? 0) === 1) return true;
            if ($condition === 'POOR_HOUSEHOLD' && (int) ($household['poor_household'] ?? 0) === 1) return true;
            if ($condition === 'NEAR_POOR_HOUSEHOLD' && (int) ($household['near_poor_household'] ?? 0) === 1) return true;
            if ($condition === 'POLICY' && ((int) ($household['meritorious_family'] ?? 0) === 1 || str_contains(mb_strtolower((string) ($household['note'] ?? ''), 'UTF-8'), 'chÃ­nh sÃ¡ch'))) return true;
        }
        return false;
    }

    private function householdExempt(array $household, array $exemption): bool
    {
        $conditions = array_map('strtoupper', (array) ($exemption['conditions'] ?? []));
        if (!$conditions) return false;
        $note = mb_strtolower((string) ($household['note'] ?? ''), 'UTF-8');
        foreach ($conditions as $condition) {
            if ($condition === 'POOR_HOUSEHOLD' && (int) ($household['poor_household'] ?? 0) === 1) return true;
            if ($condition === 'NEAR_POOR_HOUSEHOLD' && (int) ($household['near_poor_household'] ?? 0) === 1) return true;
            if ($condition === 'POLICY' && ((int) ($household['meritorious_family'] ?? 0) === 1 || str_contains($note, 'chinh sach') || str_contains($note, 'ch????nh s????ch'))) return true;
            if ($condition === 'UBND_DECISION' && (str_contains($note, 'ubnd') || str_contains($note, 'uy ban') || str_contains($note, '?y ban'))) return true;
            if ($condition === 'HAMLET_DECISION' && (str_contains($note, 'truong thon') || str_contains($note, 'tr??ng th?n'))) return true;
            if ($condition === 'OTHER' && str_contains($note, 'mien')) return true;
        }
        return false;
    }

    private function isLaborAge(array $member): bool
    {
        $ageMonths = $this->ageMonths($member['date_of_birth'] ?? null);
        if ($ageMonths < self::LABOR_AGE['min'] * 12) return false;
        $retirement = $this->retirementAgeMonths((string) ($member['gender'] ?? ''));
        return $ageMonths < $retirement;
    }

    private function retirementAgeMonths(string $gender): int
    {
        $text = mb_strtolower($gender, 'UTF-8');
        $female = !str_contains($text, 'nam');
        $year = (int) date('Y');
        $prefix = $female ? 'female' : 'male';
        $months = self::LABOR_AGE[$prefix . '_start_months'] + max(0, $year - self::LABOR_AGE[$prefix . '_start_year']) * self::LABOR_AGE[$prefix . '_step_months'];
        return min($months, self::LABOR_AGE[$prefix . '_max_months']);
    }

    private function ageYears(?string $dob): int { return intdiv($this->ageMonths($dob), 12); }
    private function ageMonths(?string $dob): int
    {
        if (!$dob) return 0;
        try { $birth = new \DateTimeImmutable($dob); $now = new \DateTimeImmutable('today'); return max(0, ((int) $birth->diff($now)->y) * 12 + (int) $birth->diff($now)->m); } catch (\Throwable) { return 0; }
    }

    private function parseConfig(array $data, string $prefix, array $fallback): array
    {
        $json = $data[$prefix . '_config_json'] ?? $data[$prefix . 'ConfigJson'] ?? null;
        if (is_string($json) && trim($json) !== '') return $this->decodeConfig($json, $fallback);
        if ($prefix === 'target') return ['mode' => strtoupper((string) ($data['target_mode'] ?? $data['targetMode'] ?? $fallback['mode'])), 'age_from' => $data['target_age_from'] ?? $data['targetAgeFrom'] ?? null, 'age_to' => $data['target_age_to'] ?? $data['targetAgeTo'] ?? null, 'note' => trim((string) ($data['target_note'] ?? $data['targetNote'] ?? ''))];
        $conditions = $data['exemption_conditions'] ?? $data['exemptionConditions'] ?? [];
        if (is_string($conditions)) $conditions = array_filter(array_map('trim', explode(',', $conditions)));
        return ['conditions' => array_values(array_unique(array_map('strtoupper', (array) $conditions))), 'note' => trim((string) ($data['exemption_note'] ?? $data['exemptionNote'] ?? ''))];
    }

    private function decodeConfig(mixed $json, array $fallback): array
    {
        if (is_array($json)) return $json + $fallback;
        $decoded = is_string($json) && trim($json) !== '' ? json_decode($json, true) : null;
        return is_array($decoded) ? ($decoded + $fallback) : $fallback;
    }

    private function nextCampaignCode(): string
    {
        $row = $this->fetchOne('SELECT MAX(id) + 1 AS next_id FROM contribution_campaigns') ?: [];
        return 'KT-' . str_pad((string) max(1, (int) ($row['next_id'] ?? 1)), 6, '0', STR_PAD_LEFT);
    }

    private function ensureColumns(): void
    {
        $campaignColumns = ['campaign_code' => 'VARCHAR(40) NULL', 'contribution_type' => 'VARCHAR(120) NULL', 'required_type' => "ENUM('REQUIRED','VOLUNTARY') NOT NULL DEFAULT 'REQUIRED'", 'unit_type' => "VARCHAR(30) NOT NULL DEFAULT 'HOUSEHOLD'", 'target_config_json' => 'JSON NULL', 'exemption_config_json' => 'JSON NULL', 'start_date' => 'DATE NULL'];
        foreach ($campaignColumns as $column => $definition) if (!$this->columnExists('contribution_campaigns', $column)) $this->execute("ALTER TABLE contribution_campaigns ADD COLUMN $column $definition");
        $trackingColumns = ['expected_amount' => 'DECIMAL(14,2) NOT NULL DEFAULT 0', 'paid_amount' => 'DECIMAL(14,2) NOT NULL DEFAULT 0', 'discount_amount' => 'DECIMAL(14,2) NOT NULL DEFAULT 0', 'debt_amount' => 'DECIMAL(14,2) NOT NULL DEFAULT 0', 'eligible_count' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'exempt_count' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'chargeable_count' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'calculation_note' => 'TEXT NULL', 'payment_method' => "ENUM('CASH','TRANSFER','OTHER') NOT NULL DEFAULT 'CASH'"];
        foreach ($trackingColumns as $column => $definition) if (!$this->columnExists('household_contributions', $column)) $this->execute("ALTER TABLE household_contributions ADD COLUMN $column $definition");
        $this->execute("ALTER TABLE household_contributions MODIFY payment_status ENUM('UNPAID','PAID','PARTIAL','EXEMPT','REDUCED') NOT NULL DEFAULT 'UNPAID'");
        $this->execute("UPDATE contribution_campaigns SET campaign_code=CONCAT('KT-', LPAD(id, 6, '0')) WHERE campaign_code IS NULL OR campaign_code=''");
        $this->execute('UPDATE household_contributions hc INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id SET hc.expected_amount=IF(hc.expected_amount=0,c.amount,hc.expected_amount), hc.paid_amount=IF(hc.paid_amount=0,hc.amount,hc.paid_amount), hc.debt_amount=GREATEST(IF(hc.expected_amount=0,c.amount,hc.expected_amount)-IF(hc.paid_amount=0,hc.amount,hc.paid_amount)-hc.discount_amount,0) WHERE hc.status="ACTIVE"');
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')];
    }
}
