<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;
use Throwable;

final class HouseholdPoverty extends BaseModel
{
    public const PERIOD_STATUSES = [
        'ACTIVE' => 'Đang áp dụng',
        'ENDED' => 'Đã kết thúc',
    ];

    public const POVERTY_TYPES = [
        'NONE' => 'Không thuộc diện',
        'NEAR_POOR' => 'Hộ cận nghèo',
        'POOR' => 'Hộ nghèo',
    ];

    public const RECORD_STATUSES = [
        'ACTIVE' => 'Hiệu lực',
        'ENDED' => 'Đã kết thúc',
        'DELETED' => 'Đã xóa',
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS poverty_periods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  note TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_poverty_periods_village_name (village_id, name),
  KEY idx_poverty_periods_village_status (village_id, status),
  KEY idx_poverty_periods_dates (village_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS household_poverty_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  household_id BIGINT UNSIGNED NOT NULL,
  period_id BIGINT UNSIGNED NOT NULL,
  poverty_type VARCHAR(20) NOT NULL DEFAULT 'NONE',
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  decision_number VARCHAR(120) NULL,
  note TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_household_poverty_village_period_type (village_id, period_id, poverty_type, status),
  KEY idx_household_poverty_household_period (village_id, household_id, period_id, status),
  KEY idx_household_poverty_effective (village_id, effective_from, effective_to),
  CONSTRAINT fk_household_poverty_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT,
  CONSTRAINT fk_household_poverty_period FOREIGN KEY (period_id) REFERENCES poverty_periods(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS poverty_change_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  record_id BIGINT UNSIGNED NULL,
  household_id BIGINT UNSIGNED NULL,
  period_id BIGINT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_poverty_change_logs_record (village_id, record_id),
  KEY idx_poverty_change_logs_household (village_id, household_id),
  KEY idx_poverty_change_logs_created (village_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'period_statuses' => $this->options(self::PERIOD_STATUSES),
            'poverty_types' => $this->options(self::POVERTY_TYPES),
            'record_statuses' => $this->options(self::RECORD_STATUSES, ['DELETED']),
            'periods' => array_map(fn(array $row) => [
                'value' => (string) $row['id'],
                'label' => $row['name'] . ' (' . $this->date($row['start_date']) . ' - ' . $this->date($row['end_date']) . ')',
                'status' => $row['status'],
            ], $this->periodList(['pageSize' => 100])['items'] ?? []),
            'areas' => $this->areaOptions(),
        ];
    }

    public function periodList(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->periodWhere($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM poverty_periods pp $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll("SELECT pp.* FROM poverty_periods pp $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn(array $row) => $this->normalizePeriod($row), $rows), $page, $pageSize, $total);
    }

    public function findPeriod(int $id, bool $includeDeleted = false): ?array
    {
        $this->ensureSchema();
        $where = ['pp.id=:id', $this->tenantWhere('pp', 'poverty_periods')];
        if (!$includeDeleted) $where[] = 'pp.status <> "DELETED"';
        $row = $this->fetchOne('SELECT pp.* FROM poverty_periods pp WHERE ' . implode(' AND ', $where), $this->withTenant(['id' => $id]));
        return $row ? $this->normalizePeriod($row) : null;
    }

    public function savePeriod(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->findPeriod($id)) throw new RuntimeException('Không tìm thấy giai đoạn');
        $params = $this->periodParams($data, $userId);
        $this->validatePeriodDates($params['start_date'], $params['end_date']);
        $duplicate = $this->fetchOne(
            'SELECT id, start_date, end_date, note, status FROM poverty_periods WHERE name=:name AND status <> "DELETED" AND ' . $this->tenantWhere('poverty_periods') . ' AND (:id=0 OR id<>:id) LIMIT 1',
            $this->withTenant(['name' => $params['name'], 'id' => (int) ($id ?? 0)])
        );
        if ($duplicate) {
            $samePeriod = !$id
                && (string) $duplicate['start_date'] === (string) $params['start_date']
                && (string) $duplicate['end_date'] === (string) $params['end_date']
                && (string) $duplicate['status'] === (string) $params['status']
                && trim((string) ($duplicate['note'] ?? '')) === trim((string) ($params['note'] ?? ''));
            if ($samePeriod) return $this->findPeriod((int) $duplicate['id']) ?: [];
            throw new RuntimeException('Tên giai đoạn đã tồn tại');
        }

        if ($id) {
            $params['id'] = $id;
            $this->execute(
                'UPDATE poverty_periods SET name=:name, start_date=:start_date, end_date=:end_date, note=:note, status=:status, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('poverty_periods'),
                $this->withTenant($params)
            );
            $this->deactivateOtherActivePeriods($id, $params['status'], $userId);
            return $this->findPeriod($id) ?: [];
        }

        $deleted = $this->fetchOne(
            'SELECT id FROM poverty_periods WHERE name=:name AND status = "DELETED" AND ' . $this->tenantWhere('poverty_periods') . ' ORDER BY id DESC LIMIT 1',
            $this->withTenant(['name' => $params['name']])
        );
        if ($deleted) {
            $params['id'] = (int) $deleted['id'];
            $this->execute(
                'UPDATE poverty_periods SET start_date=:start_date, end_date=:end_date, note=:note, status=:status, deleted_at=NULL, deleted_by=NULL, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('poverty_periods'),
                $this->withTenant($params)
            );
            $this->deactivateOtherActivePeriods((int) $deleted['id'], $params['status'], $userId);
            return $this->findPeriod((int) $deleted['id']) ?: [];
        }

        $columns = ['name','start_date','end_date','note','status','created_by','updated_by'];
        $insert = $params + ['created_by' => $userId, 'updated_by' => $userId];
        $this->addTenantInsert('poverty_periods', $columns, $insert);
        $newId = $this->insert('INSERT INTO poverty_periods (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $insert);
        $this->deactivateOtherActivePeriods($newId, $params['status'], $userId);
        return $this->findPeriod($newId) ?: [];
    }

    private function deactivateOtherActivePeriods(int $activeId, string $status, int $userId): void
    {
        if ($status !== 'ACTIVE') return;
        $this->execute(
            'UPDATE poverty_periods SET status="ENDED", updated_by=:user WHERE id<>:id AND status="ACTIVE" AND ' . $this->tenantWhere('poverty_periods'),
            $this->withTenant(['id' => $activeId, 'user' => $userId])
        );
    }

    public function deletePeriod(int $id, int $userId): void
    {
        $this->ensureSchema();
        $period = $this->findPeriod($id);
        if (!$period) throw new RuntimeException('Không tìm thấy giai đoạn');
        $activeRecords = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM household_poverty_records WHERE period_id=:id AND status <> "DELETED" AND ' . $this->tenantWhere('household_poverty_records'), $this->withTenant(['id' => $id])) ?: [])['total'] ?? 0);
        if ($activeRecords > 0) throw new RuntimeException('Không thể xóa giai đoạn đã có lịch sử hộ');
        $this->execute('UPDATE poverty_periods SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('poverty_periods'), $this->withTenant(['id' => $id, 'user' => $userId]));
    }

    public function recordList(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->recordWhere($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM household_poverty_records hpr INNER JOIN poverty_periods pp ON pp.id=hpr.period_id INNER JOIN households h ON h.id=hpr.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->recordSelect() . " $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn(array $row) => $this->normalizeRecord($row), $rows), $page, $pageSize, $total);
    }

    public function findRecord(int $id, bool $includeDeleted = false): ?array
    {
        if (!$this->db->inTransaction()) {
            $this->ensureSchema();
        }
        $where = ['hpr.id=:id', $this->tenantWhere('hpr', 'household_poverty_records'), $this->tenantWhere('pp', 'poverty_periods'), $this->tenantWhere('h', 'households')];
        if (!$includeDeleted) $where[] = 'hpr.status <> "DELETED"';
        $row = $this->fetchOne($this->recordSelect() . ' WHERE ' . implode(' AND ', $where), $this->withTenant(['id' => $id]));
        return $row ? $this->normalizeRecord($row) : null;
    }

    public function createRecord(array $data, int $userId, array $requestMeta = []): array
    {
        $this->ensureSchema();
        $params = $this->recordParams($data, $userId);
        $this->validateHousehold((int) $params['household_id']);
        $period = $this->findPeriod((int) $params['period_id']);
        if (!$period) throw new RuntimeException('Không tìm thấy giai đoạn');
        $this->validateRecordDates($params['effective_from'], $params['effective_to'], $period);

        try {
            $this->db->beginTransaction();
            $closed = $this->closeCurrentRecord((int) $params['household_id'], (int) $params['period_id'], $params['effective_from'], $userId, $requestMeta);
            $columns = ['household_id','period_id','poverty_type','effective_from','effective_to','decision_number','note','status','created_by','updated_by'];
            $insert = $params + ['status' => 'ACTIVE', 'created_by' => $userId, 'updated_by' => $userId];
            $this->addTenantInsert('household_poverty_records', $columns, $insert);
            $newId = $this->insert('INSERT INTO household_poverty_records (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $insert);
            $row = $this->findRecord($newId);
            $this->writeChangeLog('create', $row, null, $row, $userId, $requestMeta);
            foreach ($closed as $item) $this->writeChangeLog('end_active', $item['after'], $item['before'], $item['after'], $userId, $requestMeta);
            $this->db->commit();
            return $row ?: [];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function updateRecord(int $id, array $data, int $userId, array $requestMeta = []): array
    {
        $this->ensureSchema();
        $before = $this->findRecord($id);
        if (!$before) throw new RuntimeException('Không tìm thấy bản ghi hộ nghèo/cận nghèo');
        $params = $this->recordParams($data + $before, $userId, $before);
        $period = $this->findPeriod((int) $params['period_id']);
        if (!$period) throw new RuntimeException('Không tìm thấy giai đoạn');
        $this->validateRecordDates($params['effective_from'], $params['effective_to'], $period);
        if ((int) $params['household_id'] !== (int) $before['household_id'] || (int) $params['period_id'] !== (int) $before['period_id'] || $params['poverty_type'] !== $before['poverty_type']) {
            return $this->createRecord($params, $userId, $requestMeta);
        }
        $params['id'] = $id;
        $this->execute(
            'UPDATE household_poverty_records SET effective_from=:effective_from, effective_to=:effective_to, decision_number=:decision_number, note=:note, status=:status, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('household_poverty_records'),
            $this->withTenant($params)
        );
        $after = $this->findRecord($id) ?: [];
        $this->writeChangeLog('update', $after, $before, $after, $userId, $requestMeta);
        return $after;
    }

    public function deleteRecord(int $id, int $userId, array $requestMeta = []): void
    {
        $this->ensureSchema();
        $before = $this->findRecord($id);
        if (!$before) throw new RuntimeException('Không tìm thấy bản ghi hộ nghèo/cận nghèo');
        $this->execute('UPDATE household_poverty_records SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('household_poverty_records'), $this->withTenant(['id' => $id, 'user' => $userId]));
        $this->writeChangeLog('delete', ['id' => $id] + $before, $before, null, $userId, $requestMeta);
    }

    public function householdHistory(int $householdId): array
    {
        $this->ensureSchema();
        $this->validateHousehold($householdId);
        $rows = $this->fetchAll(
            $this->recordSelect() . ' WHERE hpr.household_id=:household_id AND hpr.status <> "DELETED" AND ' . $this->tenantWhere('hpr', 'household_poverty_records') . ' AND ' . $this->tenantWhere('pp', 'poverty_periods') . ' AND ' . $this->tenantWhere('h', 'households') . ' ORDER BY pp.start_date DESC, hpr.effective_from DESC, hpr.id DESC',
            $this->withTenant(['household_id' => $householdId])
        );
        return array_map(fn(array $row) => $this->normalizeRecord($row), $rows);
    }

    public function searchHouseholds(string $query, int $limit = 12): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $q = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.area_code
             FROM households h
             WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND ' . $this->tenantWhere('h', 'households') . '
               AND (LOWER(h.household_code) LIKE :q OR LOWER(COALESCE(h.head_citizen_name,"")) LIKE :q OR LOWER(COALESCE(h.address,"")) LIKE :q)
             ORDER BY h.household_code ASC LIMIT ' . max(1, min(30, $limit)),
            $this->withTenant(['q' => $q])
        );
        return array_map(fn(array $row) => [
            'id' => (int) $row['id'],
            'household_code' => (string) ($row['household_code'] ?? ''),
            'head_citizen_name' => (string) ($row['head_citizen_name'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
        ], $rows);
    }

    public function dashboard(array $filters): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->recordWhere($filters, false);
        $metrics = $this->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN hpr.status='ACTIVE' AND hpr.poverty_type='POOR' THEN 1 ELSE 0 END),0) AS poor,
                COALESCE(SUM(CASE WHEN hpr.status='ACTIVE' AND hpr.poverty_type='NEAR_POOR' THEN 1 ELSE 0 END),0) AS near_poor,
                COALESCE(SUM(CASE WHEN hpr.poverty_type IN ('POOR','NEAR_POOR') AND YEAR(hpr.effective_from)=:year_filter THEN 1 ELSE 0 END),0) AS new_entries,
                COALESCE(SUM(CASE WHEN hpr.poverty_type='POOR' AND hpr.status='ENDED' AND hpr.effective_to IS NOT NULL AND YEAR(hpr.effective_to)=:year_filter THEN 1 ELSE 0 END),0) AS escaped_poor,
                COALESCE(SUM(CASE WHEN hpr.poverty_type='NEAR_POOR' AND hpr.status='ENDED' AND hpr.effective_to IS NOT NULL AND YEAR(hpr.effective_to)=:year_filter THEN 1 ELSE 0 END),0) AS escaped_near_poor
             FROM household_poverty_records hpr
             INNER JOIN poverty_periods pp ON pp.id=hpr.period_id
             INNER JOIN households h ON h.id=hpr.household_id $where",
            $params + ['year_filter' => $this->yearFilter($filters)]
        ) ?: [];
        $households = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") AND ' . $this->tenantWhere('h', 'households'), $this->withTenant()) ?: [])['total'] ?? 0);
        $trend = $this->fetchAll(
            "SELECT YEAR(hpr.effective_from) AS year,
                COALESCE(SUM(CASE WHEN hpr.poverty_type='POOR' THEN 1 ELSE 0 END),0) AS poor,
                COALESCE(SUM(CASE WHEN hpr.poverty_type='NEAR_POOR' THEN 1 ELSE 0 END),0) AS near_poor,
                COALESCE(SUM(CASE WHEN hpr.poverty_type='NONE' THEN 1 ELSE 0 END),0) AS none_count
             FROM household_poverty_records hpr
             INNER JOIN poverty_periods pp ON pp.id=hpr.period_id
             INNER JOIN households h ON h.id=hpr.household_id $where
             GROUP BY YEAR(hpr.effective_from)
             ORDER BY year ASC",
            $params
        );
        return [
            'metrics' => [
                'poor' => (int) ($metrics['poor'] ?? 0),
                'near_poor' => (int) ($metrics['near_poor'] ?? 0),
                'new_entries' => (int) ($metrics['new_entries'] ?? 0),
                'escaped_poor' => (int) ($metrics['escaped_poor'] ?? 0),
                'escaped_near_poor' => (int) ($metrics['escaped_near_poor'] ?? 0),
                'total_households' => $households,
                'poor_rate' => $households > 0 ? round(((int) ($metrics['poor'] ?? 0)) * 100 / $households, 2) : 0,
                'near_poor_rate' => $households > 0 ? round(((int) ($metrics['near_poor'] ?? 0)) * 100 / $households, 2) : 0,
            ],
            'trend' => array_map(fn(array $row) => [
                'year' => (int) $row['year'],
                'poor' => (int) $row['poor'],
                'near_poor' => (int) $row['near_poor'],
                'none' => (int) $row['none_count'],
            ], $trend),
        ];
    }

    public function report(array $filters): array
    {
        $this->ensureSchema();
        [$where, $params, $order] = $this->recordWhere($filters);
        $items = array_map(fn(array $row) => $this->normalizeRecord($row), $this->fetchAll($this->recordSelect() . " $where $order", $params));
        $dashboard = $this->dashboard($filters);
        $year = $this->yearFilter($filters);
        $previous = $this->dashboard($filters + ['year' => $year - 1]);
        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['household_code'],
                $item['head_citizen_name'],
                $item['area_code'],
                $item['period_name'],
                $item['poverty_type_label'],
                $this->date($item['effective_from']),
                $this->date($item['effective_to']),
                $item['decision_number'],
                $item['note'],
            ];
        }
        return [
            'title' => 'Báo cáo hộ nghèo / hộ cận nghèo',
            'headers' => ['STT','Mã hộ','Chủ hộ','Khu','Giai đoạn','Loại hộ','Từ ngày','Đến ngày','Quyết định','Ghi chú'],
            'rows' => $rows,
            'items' => $items,
            'totalRows' => count($rows),
            'summary' => [
                'Tổng hộ nghèo' => $dashboard['metrics']['poor'],
                'Tổng hộ cận nghèo' => $dashboard['metrics']['near_poor'],
                'Tỷ lệ hộ nghèo' => $dashboard['metrics']['poor_rate'] . '%',
                'Tỷ lệ hộ cận nghèo' => $dashboard['metrics']['near_poor_rate'] . '%',
                'So với năm trước - hộ nghèo' => $dashboard['metrics']['poor'] - $previous['metrics']['poor'],
                'So với năm trước - hộ cận nghèo' => $dashboard['metrics']['near_poor'] - $previous['metrics']['near_poor'],
            ],
            'metrics' => $dashboard['metrics'],
            'trend' => $dashboard['trend'],
            'filters' => $filters,
            'generatedAt' => date('c'),
        ];
    }

    private function periodWhere(array $filters): array
    {
        $where = ['pp.status <> "DELETED"', $this->tenantWhere('pp', 'poverty_periods')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(pp.name) LIKE :q OR LOWER(COALESCE(pp.note,"")) LIKE :q)';
        }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if (isset(self::PERIOD_STATUSES[$status])) {
            $where[] = 'pp.status=:status';
            $params['status'] = $status;
        }
        return ['WHERE ' . implode(' AND ', $where), $params, $this->listOrder($filters, ['name' => 'pp.name', 'start_date' => 'pp.start_date', 'end_date' => 'pp.end_date', 'status' => 'pp.status'], 'start_date', 'DESC', ['pp.id DESC'])];
    }

    private function recordWhere(array $filters, bool $withOrder = true): array
    {
        $where = ['hpr.status <> "DELETED"', 'pp.status <> "DELETED"', 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', $this->tenantWhere('hpr', 'household_poverty_records'), $this->tenantWhere('pp', 'poverty_periods'), $this->tenantWhere('h', 'households')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(h.household_code) LIKE :q OR LOWER(COALESCE(h.head_citizen_name,"")) LIKE :q OR LOWER(COALESCE(h.address,"")) LIKE :q OR LOWER(COALESCE(hpr.decision_number,"")) LIKE :q)';
        }
        $periodId = (int) ($filters['period_id'] ?? $filters['periodId'] ?? 0);
        if ($periodId > 0) {
            $where[] = 'hpr.period_id=:period_id';
            $params['period_id'] = $periodId;
        }
        $type = strtoupper(trim((string) ($filters['poverty_type'] ?? $filters['povertyType'] ?? $filters['type'] ?? '')));
        if (isset(self::POVERTY_TYPES[$type])) {
            $where[] = 'hpr.poverty_type=:poverty_type';
            $params['poverty_type'] = $type;
        }
        $status = strtoupper(trim((string) ($filters['record_status'] ?? $filters['status'] ?? '')));
        if (isset(self::RECORD_STATUSES[$status]) && $status !== 'DELETED') {
            $where[] = 'hpr.status=:record_status';
            $params['record_status'] = $status;
        }
        $area = trim((string) ($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') {
            $where[] = 'h.area_code=:area_code';
            $params['area_code'] = $area;
        }
        $year = trim((string) ($filters['year'] ?? ''));
        if ($year !== '' && preg_match('/^\d{4}$/', $year)) {
            $where[] = 'YEAR(hpr.effective_from) <= :year AND (hpr.effective_to IS NULL OR YEAR(hpr.effective_to) >= :year)';
            $params['year'] = (int) $year;
        }
        $list = trim((string) ($filters['list'] ?? ''));
        if ($list === 'poor') $where[] = 'hpr.poverty_type="POOR" AND hpr.status="ACTIVE"';
        if ($list === 'near_poor') $where[] = 'hpr.poverty_type="NEAR_POOR" AND hpr.status="ACTIVE"';
        if ($list === 'new_entries') $where[] = 'hpr.poverty_type IN ("POOR","NEAR_POOR")';
        if ($list === 'escaped_poor') $where[] = 'hpr.poverty_type="POOR" AND hpr.status="ENDED"';
        if ($list === 'escaped_near_poor') $where[] = 'hpr.poverty_type="NEAR_POOR" AND hpr.status="ENDED"';
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = $this->listOrder($filters, ['household_code' => 'h.household_code', 'head' => 'h.head_citizen_name', 'period' => 'pp.start_date', 'poverty_type' => 'hpr.poverty_type', 'effective_from' => 'hpr.effective_from', 'status' => 'hpr.status'], 'effective_from', 'DESC', ['hpr.id DESC']);
        return $result;
    }

    private function recordSelect(): string
    {
        return 'SELECT hpr.*, pp.name AS period_name, pp.start_date AS period_start_date, pp.end_date AS period_end_date, h.household_code, h.head_citizen_name, h.address, h.area_code
            FROM household_poverty_records hpr
            INNER JOIN poverty_periods pp ON pp.id=hpr.period_id
            INNER JOIN households h ON h.id=hpr.household_id';
    }

    private function periodParams(array $data, int $userId): array
    {
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::PERIOD_STATUSES[$status])) $status = 'ACTIVE';
        return [
            'name' => $this->required($data['name'] ?? null, 'Tên giai đoạn'),
            'start_date' => $this->dateOrFail($data['start_date'] ?? $data['startDate'] ?? null, 'Ngày bắt đầu'),
            'end_date' => $this->dateOrFail($data['end_date'] ?? $data['endDate'] ?? null, 'Ngày kết thúc'),
            'note' => $this->nullable($data['note'] ?? null),
            'status' => $status,
            'user' => $userId,
        ];
    }

    private function recordParams(array $data, int $userId, ?array $existing = null): array
    {
        $type = strtoupper(trim((string) ($data['poverty_type'] ?? $data['povertyType'] ?? $existing['poverty_type'] ?? 'NONE')));
        if (!isset(self::POVERTY_TYPES[$type])) throw new RuntimeException('Loại hộ không hợp lệ');
        $status = strtoupper(trim((string) ($data['status'] ?? $existing['status'] ?? 'ACTIVE')));
        if (!isset(self::RECORD_STATUSES[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        return [
            'household_id' => (int) ($data['household_id'] ?? $data['householdId'] ?? $existing['household_id'] ?? 0),
            'period_id' => (int) ($data['period_id'] ?? $data['periodId'] ?? $existing['period_id'] ?? 0),
            'poverty_type' => $type,
            'effective_from' => $this->dateOrFail($data['effective_from'] ?? $data['effectiveFrom'] ?? $existing['effective_from'] ?? null, 'Ngày bắt đầu'),
            'effective_to' => $this->dateOrNull($data['effective_to'] ?? $data['effectiveTo'] ?? $existing['effective_to'] ?? null),
            'decision_number' => $this->nullable($data['decision_number'] ?? $data['decisionNumber'] ?? $existing['decision_number'] ?? null),
            'note' => $this->nullable($data['note'] ?? $existing['note'] ?? null),
            'status' => $status,
            'user' => $userId,
        ];
    }

    private function closeCurrentRecord(int $householdId, int $periodId, string $newEffectiveFrom, int $userId, array $requestMeta): array
    {
        $rows = $this->fetchAll(
            $this->recordSelect() . ' WHERE hpr.household_id=:household_id AND hpr.period_id=:period_id AND hpr.status="ACTIVE" AND ' . $this->tenantWhere('hpr', 'household_poverty_records') . ' AND ' . $this->tenantWhere('pp', 'poverty_periods') . ' AND ' . $this->tenantWhere('h', 'households') . ' ORDER BY hpr.effective_from DESC, hpr.id DESC',
            $this->withTenant(['household_id' => $householdId, 'period_id' => $periodId])
        );
        $closed = [];
        foreach ($rows as $row) {
            $before = $this->normalizeRecord($row);
            $effectiveTo = date('Y-m-d', strtotime($newEffectiveFrom . ' -1 day'));
            if ($effectiveTo < (string) $row['effective_from']) {
                if ($newEffectiveFrom !== (string) $row['effective_from']) {
                    throw new RuntimeException('Ngày bắt đầu mới phải sau ngày bắt đầu của bản ghi hiệu lực hiện tại');
                }
                $effectiveTo = (string) $row['effective_from'];
            }
            $this->execute('UPDATE household_poverty_records SET status="ENDED", effective_to=:effective_to, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('household_poverty_records'), $this->withTenant(['id' => (int) $row['id'], 'effective_to' => $effectiveTo, 'user' => $userId]));
            $after = $this->findRecord((int) $row['id'], true) ?: [];
            $closed[] = ['before' => $before, 'after' => $after, 'meta' => $requestMeta];
        }
        return $closed;
    }

    private function writeChangeLog(string $action, ?array $record, ?array $before, ?array $after, int $userId, array $requestMeta): void
    {
        $columns = ['record_id','household_id','period_id','action','before_json','after_json','actor_user_id','ip_address','user_agent'];
        $params = [
            'record_id' => $record['id'] ?? $before['id'] ?? $after['id'] ?? null,
            'household_id' => $record['household_id'] ?? $before['household_id'] ?? $after['household_id'] ?? null,
            'period_id' => $record['period_id'] ?? $before['period_id'] ?? $after['period_id'] ?? null,
            'action' => $action,
            'before_json' => $before ? json_encode($this->safeLogPayload($before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $after ? json_encode($this->safeLogPayload($after), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'actor_user_id' => $userId,
            'ip_address' => $this->nullable($requestMeta['ip'] ?? null),
            'user_agent' => $this->nullable(mb_substr((string) ($requestMeta['user_agent'] ?? ''), 0, 255)),
        ];
        $this->addTenantInsert('poverty_change_logs', $columns, $params);
        $this->insert('INSERT INTO poverty_change_logs (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
    }

    private function normalizePeriod(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['status_label'] = self::PERIOD_STATUSES[$row['status'] ?? 'ACTIVE'] ?? (string) ($row['status'] ?? '');
        return $row;
    }

    private function normalizeRecord(array $row): array
    {
        foreach (['id','household_id','period_id'] as $key) $row[$key] = (int) $row[$key];
        $row['poverty_type_label'] = self::POVERTY_TYPES[$row['poverty_type'] ?? 'NONE'] ?? (string) ($row['poverty_type'] ?? '');
        $row['status_label'] = self::RECORD_STATUSES[$row['status'] ?? 'ACTIVE'] ?? (string) ($row['status'] ?? '');
        return $row;
    }

    private function validateHousehold(int $householdId): void
    {
        if ($householdId <= 0) throw new RuntimeException('Hộ gia đình là bắt buộc');
        $row = $this->fetchOne('SELECT id FROM households h WHERE h.id=:id AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") AND ' . $this->tenantWhere('h', 'households'), $this->withTenant(['id' => $householdId]));
        if (!$row) throw new RuntimeException('Không tìm thấy hộ gia đình');
    }

    private function validatePeriodDates(string $start, string $end): void
    {
        if ($end < $start) throw new RuntimeException('Ngày kết thúc giai đoạn phải sau ngày bắt đầu');
    }

    private function validateRecordDates(string $from, ?string $to, array $period): void
    {
        if ($to !== null && $to < $from) throw new RuntimeException('Ngày kết thúc phải sau ngày bắt đầu');
        if ($from < (string) $period['start_date'] || $from > (string) $period['end_date']) throw new RuntimeException('Ngày bắt đầu phải nằm trong giai đoạn');
        if ($to !== null && ($to < (string) $period['start_date'] || $to > (string) $period['end_date'])) throw new RuntimeException('Ngày kết thúc phải nằm trong giai đoạn');
    }

    private function areaOptions(): array
    {
        $rows = $this->fetchAll('SELECT DISTINCT area_code AS value FROM households h WHERE area_code IS NOT NULL AND TRIM(area_code)<>"" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") AND ' . $this->tenantWhere('h', 'households') . ' ORDER BY area_code ASC LIMIT 100', $this->withTenant());
        return array_map(fn(array $row) => ['value' => (string) $row['value'], 'label' => (string) $row['value']], $rows);
    }

    private function yearFilter(array $filters): int
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        return $year >= 1900 && $year <= 2200 ? $year : (int) date('Y');
    }

    private function safeLogPayload(array $payload): array
    {
        foreach (['password','token','secret','connection_string','db_password','database_password'] as $key) {
            unset($payload[$key]);
        }
        return $payload;
    }

    private function required(mixed $value, string $label): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') throw new RuntimeException($label . ' là bắt buộc');
        return $text;
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function dateOrFail(mixed $value, string $label): string
    {
        $date = $this->normalizeInputDate($value);
        if ($date === null) throw new RuntimeException($label . ' không hợp lệ');
        return $date;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $this->normalizeInputDate($text);
    }

    private function normalizeInputDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $text : null;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            $year = (int) $m[3];
            $month = $second > 12 ? $first : ($first > 12 ? $second : $first);
            $day = $second > 12 ? $second : ($first > 12 ? $first : $second);
            if (!checkdate($month, $day, $year)) return null;
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
        return null;
    }

    private function date(?string $value): string
    {
        if (!$value || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) return '';
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    private function options(array $labels, array $exclude = []): array
    {
        return array_values(array_map(fn(string $key) => ['value' => $key, 'label' => $labels[$key]], array_filter(array_keys($labels), fn(string $key) => !in_array($key, $exclude, true))));
    }
}
