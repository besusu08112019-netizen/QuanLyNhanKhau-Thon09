<?php

namespace App\Models;

use App\Core\BaseModel;

final class HouseholdContribution extends BaseModel
{
    public const CATEGORIES = ['Quỹ vệ sinh','Quỹ an ninh','Quỹ khuyến học','Đóng góp làm đường','Điện chiếu sáng','Nghĩa trang','Nhà văn hóa','Đóng góp khác'];
    public const CAMPAIGN_STATUS = ['ACTIVE' => 'Đang thu', 'CLOSED' => 'Đã kết thúc', 'INACTIVE' => 'Tạm dừng', 'DELETED' => 'Đã xóa'];
    public const PAYMENT_STATUS = ['UNPAID' => 'Chưa nộp', 'PAID' => 'Đã nộp', 'PARTIAL' => 'Nộp một phần', 'EXEMPT' => 'Miễn giảm'];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS contribution_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contribution_name VARCHAR(180) NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  period_name VARCHAR(80) NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  unit VARCHAR(40) NOT NULL DEFAULT 'VNĐ/hộ',
  due_date DATE NULL,
  note TEXT NULL,
  status ENUM('ACTIVE','CLOSED','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_contribution_campaign_year (year),
  KEY idx_contribution_campaign_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS household_contributions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  household_id BIGINT UNSIGNED NOT NULL,
  payment_status ENUM('UNPAID','PAID','PARTIAL','EXEMPT') NOT NULL DEFAULT 'UNPAID',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_at DATE NULL,
  collector_name VARCHAR(180) NULL,
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
    }

    public function catalogs(): array
    {
        return [
            'categories' => array_map(fn($v) => ['value' => $v, 'label' => $v], self::CATEGORIES),
            'campaign_statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::CAMPAIGN_STATUS), array_values(self::CAMPAIGN_STATUS)),
            'payment_statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::PAYMENT_STATUS), array_values(self::PAYMENT_STATUS)),
        ];
    }

    public function campaigns(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->campaignWhere($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM contribution_campaigns c $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT c.*,
                COALESCE(SUM(CASE WHEN hc.payment_status='PAID' AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS paid_households,
                COALESCE(SUM(CASE WHEN hc.payment_status='EXEMPT' AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS exempt_households,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.amount ELSE 0 END),0) AS collected_amount
             FROM contribution_campaigns c
             LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE'
             $where
             GROUP BY c.id
             $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalizeCampaign($row), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function findCampaign(int $id): ?array
    {
        $this->ensureSchema();
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
            $this->execute('UPDATE contribution_campaigns SET contribution_name=:contribution_name, year=:year, period_name=:period_name, amount=:amount, unit=:unit, due_date=:due_date, note=:note, status=:status, updated_by=:user WHERE id=:id', $params);
            return $this->findCampaign($id);
        }
        $insertParams = $params + ['created_by' => $userId, 'updated_by' => $userId];
        unset($insertParams['user']);
        $newId = $this->insert('INSERT INTO contribution_campaigns (contribution_name, year, period_name, amount, unit, due_date, note, status, created_by, updated_by) VALUES (:contribution_name,:year,:period_name,:amount,:unit,:due_date,:note,:status,:created_by,:updated_by)', $insertParams);
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
        if (!$this->findCampaign($campaignId)) throw new \RuntimeException('Không tìm thấy đợt thu');
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->trackingWhere($campaignId, $filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM households h LEFT JOIN household_contributions hc ON hc.household_id=h.id AND hc.campaign_id=:campaign_id AND hc.status='ACTIVE' $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT h.id AS household_id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code,
                hc.id, hc.campaign_id, COALESCE(hc.payment_status,'UNPAID') AS payment_status,
                COALESCE(hc.amount,0) AS amount, hc.paid_at, hc.collector_name, hc.receipt_number, hc.note, hc.created_at, hc.updated_at
             FROM households h
             LEFT JOIN household_contributions hc ON hc.household_id=h.id AND hc.campaign_id=:campaign_id AND hc.status='ACTIVE'
             $where $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalizeTracking($row), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function upsertTracking(int $campaignId, int $householdId, array $data, int $userId): array
    {
        $this->ensureSchema();
        if (!$this->findCampaign($campaignId)) throw new \RuntimeException('Không tìm thấy đợt thu');
        if (!$this->fetchOne('SELECT id FROM households WHERE id=:id AND status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', ['id' => $householdId])) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $status = strtoupper(trim((string) ($data['payment_status'] ?? $data['paymentStatus'] ?? 'UNPAID')));
        if (!isset(self::PAYMENT_STATUS[$status])) $status = 'UNPAID';
        $params = [
            'campaign_id' => $campaignId,
            'household_id' => $householdId,
            'payment_status' => $status,
            'amount' => max(0, (float) ($data['amount'] ?? 0)),
            'paid_at' => trim((string) ($data['paid_at'] ?? $data['paidAt'] ?? '')) ?: null,
            'collector_name' => trim((string) ($data['collector_name'] ?? $data['collectorName'] ?? '')) ?: null,
            'receipt_number' => trim((string) ($data['receipt_number'] ?? $data['receiptNumber'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'user' => $userId,
        ];
        $this->execute(
            'INSERT INTO household_contributions (campaign_id, household_id, payment_status, amount, paid_at, collector_name, receipt_number, note, created_by, updated_by)
             VALUES (:campaign_id,:household_id,:payment_status,:amount,:paid_at,:collector_name,:receipt_number,:note,:user,:user)
             ON DUPLICATE KEY UPDATE payment_status=VALUES(payment_status), amount=VALUES(amount), paid_at=VALUES(paid_at), collector_name=VALUES(collector_name), receipt_number=VALUES(receipt_number), note=VALUES(note), status="ACTIVE", updated_by=VALUES(updated_by), deleted_at=NULL, deleted_by=NULL',
            $params
        );
        return $this->tracking($campaignId, ['household_id' => $householdId, 'pageSize' => 1])['items'][0] ?? [];
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->campaignWhere($filters, false);
        $row = $this->fetchOne("SELECT COUNT(*) AS campaigns, COALESCE(SUM(amount),0) AS expected_per_household FROM contribution_campaigns c $where", $params) ?: [];
        $pay = $this->fetchOne(
            "SELECT COUNT(*) AS records,
                COALESCE(SUM(CASE WHEN hc.payment_status='PAID' THEN 1 ELSE 0 END),0) AS paid,
                COALESCE(SUM(CASE WHEN hc.payment_status='UNPAID' THEN 1 ELSE 0 END),0) AS unpaid,
                COALESCE(SUM(CASE WHEN hc.payment_status='PARTIAL' THEN 1 ELSE 0 END),0) AS partial,
                COALESCE(SUM(CASE WHEN hc.payment_status='EXEMPT' THEN 1 ELSE 0 END),0) AS exempt,
                COALESCE(SUM(hc.amount),0) AS collected
             FROM household_contributions hc INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id
             WHERE hc.status='ACTIVE' AND c.status <> 'DELETED'",
            []
        ) ?: [];
        return [
            'campaigns' => (int) ($row['campaigns'] ?? 0),
            'paid' => (int) ($pay['paid'] ?? 0),
            'unpaid' => (int) ($pay['unpaid'] ?? 0),
            'partial' => (int) ($pay['partial'] ?? 0),
            'exempt' => (int) ($pay['exempt'] ?? 0),
            'collected' => (float) ($pay['collected'] ?? 0),
        ];
    }

    public function charts(array $filters = []): array
    {
        $this->ensureSchema();
        return [
            'by_status' => $this->fetchAll("SELECT hc.payment_status AS label, COUNT(*) AS value FROM household_contributions hc INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id WHERE hc.status='ACTIVE' AND c.status <> 'DELETED' GROUP BY hc.payment_status ORDER BY value DESC"),
            'by_year' => $this->fetchAll("SELECT c.year AS label, COALESCE(SUM(hc.amount),0) AS value FROM contribution_campaigns c LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE' WHERE c.status <> 'DELETED' GROUP BY c.year ORDER BY c.year DESC LIMIT 10"),
            'by_campaign' => $this->fetchAll("SELECT c.contribution_name AS label, COALESCE(SUM(hc.amount),0) AS value FROM contribution_campaigns c LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE' WHERE c.status <> 'DELETED' GROUP BY c.id, c.contribution_name ORDER BY value DESC LIMIT 10"),
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
            return $this->table('Báo cáo đóng góp hộ', ['Mã hộ','Chủ hộ','Trạng thái','Số tiền','Ngày thu','Người thu','Biên lai','Ghi chú'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['payment_status_label'], $r['amount'], $r['paid_at'], $r['collector_name'], $r['receipt_number'], $r['note']], $rows), $filters);
        }
        $rows = $this->campaigns($filters + ['pageSize' => 100])['items'];
        return $this->table('Danh sách đợt thu', ['Khoản thu','Năm','Đợt','Mức thu','Đơn vị','Hạn đóng','Đã nộp','Miễn giảm','Đã thu','Trạng thái'], array_map(fn($r) => [$r['contribution_name'], $r['year'], $r['period_name'], $r['amount'], $r['unit'], $r['due_date'], $r['paid_households'], $r['exempt_households'], $r['collected_amount'], $r['status_label']], $rows), $filters);
    }

    private function campaignWhere(array $filters, bool $withOrder = true): array
    {
        $where = ['c.status <> "DELETED"'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(LOWER(c.contribution_name) LIKE :search OR LOWER(c.period_name) LIKE :search OR LOWER(c.note) LIKE :search)'; $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%'; }
        $year = (int) ($filters['year'] ?? 0);
        if ($year > 0) { $where[] = 'c.year = :year'; $params['year'] = $year; }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && isset(self::CAMPAIGN_STATUS[$status])) { $where[] = 'c.status = :status'; $params['status'] = $status; }
        $order = 'ORDER BY c.year DESC, c.id DESC';
        return ['WHERE ' . implode(' AND ', $where), $params, $order];
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
        if ($name === '') throw new \RuntimeException('Tên khoản thu là bắt buộc');
        $year = (int) ($data['year'] ?? date('Y'));
        if ($year < 2000 || $year > (int) date('Y') + 2) throw new \RuntimeException('Năm thu không hợp lệ');
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::CAMPAIGN_STATUS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        return [
            'contribution_name' => $name,
            'year' => $year,
            'period_name' => trim((string) ($data['period_name'] ?? $data['periodName'] ?? '')) ?: null,
            'amount' => max(0, (float) ($data['amount'] ?? 0)),
            'unit' => trim((string) ($data['unit'] ?? 'VNĐ/hộ')) ?: 'VNĐ/hộ',
            'due_date' => trim((string) ($data['due_date'] ?? $data['dueDate'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'status' => $status,
            'user' => $userId,
        ];
    }

    private function normalizeCampaign(array $row): array
    {
        $status = (string) ($row['status'] ?? 'ACTIVE');
        return ['id' => (int) $row['id'], 'contribution_name' => (string) $row['contribution_name'], 'year' => (int) $row['year'], 'period_name' => (string) ($row['period_name'] ?? ''), 'amount' => (float) ($row['amount'] ?? 0), 'unit' => (string) ($row['unit'] ?? 'VNĐ/hộ'), 'due_date' => $row['due_date'] ?? null, 'note' => (string) ($row['note'] ?? ''), 'status' => $status, 'status_label' => self::CAMPAIGN_STATUS[$status] ?? $status, 'paid_households' => (int) ($row['paid_households'] ?? 0), 'exempt_households' => (int) ($row['exempt_households'] ?? 0), 'collected_amount' => (float) ($row['collected_amount'] ?? 0), 'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null];
    }

    private function normalizeTracking(array $row): array
    {
        $payment = (string) ($row['payment_status'] ?? 'UNPAID');
        return ['id' => isset($row['id']) ? (int) $row['id'] : null, 'campaign_id' => isset($row['campaign_id']) ? (int) $row['campaign_id'] : null, 'household_id' => (int) $row['household_id'], 'household_code' => (string) $row['household_code'], 'head_citizen_name' => (string) $row['head_citizen_name'], 'address' => (string) ($row['address'] ?? ''), 'phone' => (string) ($row['phone'] ?? ''), 'area_code' => (string) ($row['area_code'] ?? ''), 'payment_status' => $payment, 'payment_status_label' => self::PAYMENT_STATUS[$payment] ?? $payment, 'amount' => (float) ($row['amount'] ?? 0), 'paid_at' => $row['paid_at'] ?? null, 'collector_name' => (string) ($row['collector_name'] ?? ''), 'receipt_number' => (string) ($row['receipt_number'] ?? ''), 'note' => (string) ($row['note'] ?? ''), 'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null];
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')];
    }
}
