<?php

namespace App\Models;

use App\Core\BaseModel;

final class RuralCleanWater extends BaseModel
{
    private const CONNECTION_LABELS = [
        'PIPED' => 'Nước máy tập trung',
        'WELL' => 'Giếng khoan/giếng đào',
        'RAINWATER' => 'Nước mưa',
        'PURCHASED' => 'Nước mua/bình',
        'OTHER' => 'Nguồn khác',
    ];

    private const STATUS_LABELS = [
        'ACTIVE' => 'Đang sử dụng',
        'INACTIVE' => 'Tạm ngừng',
        'NEEDS_REPAIR' => 'Cần sửa chữa',
        'DISCONNECTED' => 'Đã ngắt',
        'DELETED' => 'Đã xóa',
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS rural_clean_water (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  connection_type ENUM('PIPED','WELL','RAINWATER','PURCHASED','OTHER') NOT NULL DEFAULT 'PIPED',
  water_source VARCHAR(255) NULL,
  provider_name VARCHAR(255) NULL,
  meter_number VARCHAR(120) NULL,
  contract_number VARCHAR(120) NULL,
  installed_date DATE NULL,
  monthly_usage_m3 DECIMAL(12,2) NOT NULL DEFAULT 0,
  monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_clean_standard TINYINT(1) NOT NULL DEFAULT 0,
  last_test_date DATE NULL,
  test_result VARCHAR(120) NULL,
  status ENUM('ACTIVE','INACTIVE','NEEDS_REPAIR','DISCONNECTED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_rural_clean_water_household (household_id),
  KEY idx_rural_clean_water_type (connection_type),
  KEY idx_rural_clean_water_standard (is_clean_standard),
  KEY idx_rural_clean_water_status (status),
  CONSTRAINT fk_rural_clean_water_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureTenantColumn('rural_clean_water');
    }

    public function catalogs(): array
    {
        return [
            'connection_types' => $this->pairs(self::CONNECTION_LABELS),
            'statuses' => $this->pairs(self::STATUS_LABELS),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT w.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code
             FROM rural_clean_water w
             INNER JOIN households h ON h.id=w.household_id
             $where $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT w.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code
             FROM rural_clean_water w
             INNER JOIN households h ON h.id=w.household_id
             WHERE w.id=:id AND w.status <> "DELETED" AND ' . $this->tenantWhere('w', 'rural_clean_water') . ' AND ' . $this->tenantWhere('h', 'households') . ' AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")',
            $this->withTenant(['id' => $id])
        );
        return $row ? $this->normalize($row) : null;
    }

    public function byHousehold(int $householdId): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll(
            'SELECT w.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code
             FROM rural_clean_water w
             INNER JOIN households h ON h.id=w.household_id
             WHERE w.household_id=:household_id AND w.status <> "DELETED" AND ' . $this->tenantWhere('w', 'rural_clean_water') . ' AND ' . $this->tenantWhere('h', 'households') . '
             ORDER BY w.id DESC',
            $this->withTenant(['household_id' => $householdId])
        );
        return array_map(fn($row) => $this->normalize($row), $rows);
    }

    public function searchHouseholds(string $query, int $limit = 12): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) return [];
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, COALESCE(wc.water_count,0) AS water_count
             FROM households h
             LEFT JOIN (SELECT household_id, COUNT(*) AS water_count FROM rural_clean_water WHERE status <> "DELETED" AND ' . $this->tenantWhere('rural_clean_water') . ' GROUP BY household_id) wc ON wc.household_id=h.id
             WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND ' . $this->tenantWhere('h', 'households') . '
               AND (LOWER(h.household_code) LIKE :code OR LOWER(h.head_citizen_name) LIKE :head OR LOWER(h.address) LIKE :address)
             ORDER BY h.household_code ASC LIMIT ' . max(1, min(20, $limit)),
            $this->withTenant(['code' => $keyword, 'head' => $keyword, 'address' => $keyword])
        );
        return array_map(fn($row) => [
            'id' => (int) $row['id'],
            'household_code' => (string) $row['household_code'],
            'head_citizen_name' => (string) $row['head_citizen_name'],
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'water_count' => (int) ($row['water_count'] ?? 0),
        ], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->find($id)) throw new \RuntimeException('Không tìm thấy bản ghi nước sạch');
        $params = $this->params($data, $userId);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE rural_clean_water SET household_id=:household_id, connection_type=:connection_type, water_source=:water_source, provider_name=:provider_name, meter_number=:meter_number, contract_number=:contract_number, installed_date=:installed_date, monthly_usage_m3=:monthly_usage_m3, monthly_fee=:monthly_fee, is_clean_standard=:is_clean_standard, last_test_date=:last_test_date, test_result=:test_result, status=:status, note=:note, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('rural_clean_water'), $this->withTenant($params));
            return $this->find($id);
        }
        $columns = ['household_id', 'connection_type', 'water_source', 'provider_name', 'meter_number', 'contract_number', 'installed_date', 'monthly_usage_m3', 'monthly_fee', 'is_clean_standard', 'last_test_date', 'test_result', 'status', 'note', 'created_by', 'updated_by'];
        $this->addTenantInsert('rural_clean_water', $columns, $params);
        $newId = $this->insert('INSERT INTO rural_clean_water (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy bản ghi nước sạch');
        $this->execute('UPDATE rural_clean_water SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('rural_clean_water'), $this->withTenant(['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]));
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total_records,
                    COUNT(DISTINCT w.household_id) AS connected_households,
                    COALESCE(SUM(w.is_clean_standard=1),0) AS clean_standard_records,
                    COALESCE(SUM(w.status='NEEDS_REPAIR'),0) AS needs_repair_records,
                    COALESCE(SUM(w.status='DISCONNECTED'),0) AS disconnected_records,
                    COALESCE(SUM(w.monthly_usage_m3),0) AS monthly_usage_m3,
                    COALESCE(SUM(w.monthly_fee),0) AS monthly_fee
             FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id $where",
            $params
        ) ?: [];
        return [
            'metrics' => [
                'total_records' => (int) ($row['total_records'] ?? 0),
                'connected_households' => (int) ($row['connected_households'] ?? 0),
                'clean_standard_records' => (int) ($row['clean_standard_records'] ?? 0),
                'needs_repair_records' => (int) ($row['needs_repair_records'] ?? 0),
                'disconnected_records' => (int) ($row['disconnected_records'] ?? 0),
                'monthly_usage_m3' => (float) ($row['monthly_usage_m3'] ?? 0),
                'monthly_fee' => (float) ($row['monthly_fee'] ?? 0),
            ],
            'charts' => [
                'connection_types' => $this->fetchAll("SELECT w.connection_type AS code, COUNT(*) AS value FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id $where GROUP BY w.connection_type ORDER BY value DESC", $params),
                'areas' => $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),'Chưa phân khu') AS label, COUNT(DISTINCT w.household_id) AS value FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id $where GROUP BY label ORDER BY value DESC, label LIMIT 12", $params),
                'quality' => $this->fetchAll("SELECT CASE WHEN w.is_clean_standard=1 THEN 'Đạt chuẩn' ELSE 'Chưa xác nhận' END AS label, COUNT(*) AS value FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id $where GROUP BY w.is_clean_standard ORDER BY w.is_clean_standard DESC", $params),
            ],
        ];
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'standard') $filters['is_clean_standard'] = '1';
        if ($mode === 'not_standard') $filters['is_clean_standard'] = '0';
        if ($mode === 'needs_repair') $filters['status'] = 'NEEDS_REPAIR';
        $filters['page'] = 1;
        $filters['pageSize'] = 500;
        $rows = $this->paginate($filters)['items'];
        $title = match ($mode) {
            'standard' => 'Danh sách hộ dùng nước sạch đạt chuẩn',
            'not_standard' => 'Danh sách hộ chưa xác nhận nước sạch đạt chuẩn',
            'needs_repair' => 'Danh sách công trình nước cần sửa chữa',
            default => 'Danh sách quản lý nước sạch nông thôn',
        };
        return $this->table($title, ['Mã hộ','Chủ hộ','Khu vực','Nguồn nước','Đơn vị cấp nước','Số đồng hồ','Hợp đồng','Sử dụng m3/tháng','Phí/tháng','Đạt chuẩn','Ngày kiểm định','Kết quả','Trạng thái','Địa chỉ'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['area_code'], $r['connection_type_label'], $r['provider_name'], $r['meter_number'], $r['contract_number'], $r['monthly_usage_m3'], $r['monthly_fee'], $r['is_clean_standard'] ? 'Có' : 'Chưa', $r['last_test_date'], $r['test_result'], $r['status_label'], $r['address']], $rows), $filters);
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['w.status <> "DELETED"', $this->tenantWhere('w', 'rural_clean_water'), $this->tenantWhere('h', 'households'), 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $keyword = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(h.household_code) LIKE :q OR LOWER(h.head_citizen_name) LIKE :q OR LOWER(h.address) LIKE :q OR LOWER(w.water_source) LIKE :q OR LOWER(w.provider_name) LIKE :q OR LOWER(w.meter_number) LIKE :q OR LOWER(w.contract_number) LIKE :q)';
            $params['q'] = $keyword;
        }
        foreach (['connection_type' => 'w.connection_type', 'status' => 'w.status', 'area_code' => 'h.area_code'] as $key => $column) {
            $value = strtoupper(trim((string) ($filters[$key] ?? $filters[str_replace('_', '', $key)] ?? '')));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        $standard = trim((string) ($filters['is_clean_standard'] ?? $filters['isCleanStandard'] ?? ''));
        if ($standard === '1' || $standard === '0') $where[] = 'w.is_clean_standard = ' . (int) $standard;
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            $value = trim((string) ($filters[$key] ?? $filters[str_replace('_', '', $key)] ?? ''));
            if ($value !== '') { $where[] = "DATE(COALESCE(w.updated_at,w.created_at)) $op :$key"; $params[$key] = $value; }
        }
        $sortMap = ['household_code' => 'h.household_code', 'head_citizen_name' => 'h.head_citizen_name', 'connection_type' => 'w.connection_type', 'status' => 'w.status', 'monthly_usage_m3' => 'w.monthly_usage_m3', 'updated_at' => 'COALESCE(w.updated_at,w.created_at)'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = $this->listOrder($filters, $sortMap, 'household_code', 'ASC', ['w.id ASC']);
        return $result;
    }

    private function params(array $data, int $userId): array
    {
        $householdId = (int) ($data['household_id'] ?? $data['householdId'] ?? 0);
        if ($householdId <= 0) throw new \RuntimeException('Hộ gia đình là bắt buộc');
        if (!$this->fetchOne('SELECT h.id FROM households h WHERE h.id=:id AND ' . $this->tenantWhere('h', 'households') . ' AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', $this->withTenant(['id' => $householdId]))) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $type = strtoupper(trim((string) ($data['connection_type'] ?? $data['connectionType'] ?? 'PIPED')));
        if (!isset(self::CONNECTION_LABELS[$type])) $type = 'OTHER';
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::STATUS_LABELS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        return [
            'household_id' => $householdId,
            'connection_type' => $type,
            'water_source' => $this->nullable($data['water_source'] ?? $data['waterSource'] ?? ''),
            'provider_name' => $this->nullable($data['provider_name'] ?? $data['providerName'] ?? ''),
            'meter_number' => $this->nullable($data['meter_number'] ?? $data['meterNumber'] ?? ''),
            'contract_number' => $this->nullable($data['contract_number'] ?? $data['contractNumber'] ?? ''),
            'installed_date' => $this->dateValue($data['installed_date'] ?? $data['installedDate'] ?? ''),
            'monthly_usage_m3' => $this->number($data['monthly_usage_m3'] ?? $data['monthlyUsageM3'] ?? 0),
            'monthly_fee' => $this->number($data['monthly_fee'] ?? $data['monthlyFee'] ?? 0),
            'is_clean_standard' => !empty($data['is_clean_standard'] ?? $data['isCleanStandard'] ?? 0) && ($data['is_clean_standard'] ?? $data['isCleanStandard'] ?? 0) !== '0' ? 1 : 0,
            'last_test_date' => $this->dateValue($data['last_test_date'] ?? $data['lastTestDate'] ?? ''),
            'test_result' => $this->nullable($data['test_result'] ?? $data['testResult'] ?? ''),
            'status' => $status,
            'note' => $this->nullable($data['note'] ?? ''),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function normalize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'household_id' => (int) $row['household_id'],
            'household_code' => (string) ($row['household_code'] ?? ''),
            'head_citizen_name' => (string) ($row['head_citizen_name'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
            'address' => (string) ($row['household_address'] ?? ''),
            'phone' => (string) ($row['household_phone'] ?? ''),
            'connection_type' => (string) ($row['connection_type'] ?? 'PIPED'),
            'connection_type_label' => self::CONNECTION_LABELS[$row['connection_type'] ?? 'PIPED'] ?? self::CONNECTION_LABELS['OTHER'],
            'water_source' => (string) ($row['water_source'] ?? ''),
            'provider_name' => (string) ($row['provider_name'] ?? ''),
            'meter_number' => (string) ($row['meter_number'] ?? ''),
            'contract_number' => (string) ($row['contract_number'] ?? ''),
            'installed_date' => $row['installed_date'] ?? null,
            'monthly_usage_m3' => (float) ($row['monthly_usage_m3'] ?? 0),
            'monthly_fee' => (float) ($row['monthly_fee'] ?? 0),
            'is_clean_standard' => (int) ($row['is_clean_standard'] ?? 0) === 1,
            'last_test_date' => $row['last_test_date'] ?? null,
            'test_result' => (string) ($row['test_result'] ?? ''),
            'status' => (string) ($row['status'] ?? 'ACTIVE'),
            'status_label' => self::STATUS_LABELS[$row['status'] ?? 'ACTIVE'] ?? self::STATUS_LABELS['ACTIVE'],
            'note' => (string) ($row['note'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function nullable(mixed $value): ?string { $value = trim((string) ($value ?? '')); return $value === '' ? null : $value; }
    private function number(mixed $value): float { return max(0, (float) str_replace(',', '.', (string) $value)); }
    private function dateValue(mixed $value): ?string { $value = trim((string) ($value ?? '')); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null; }
    private function pairs(array $map): array { return array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys($map), array_values($map)); }
    private function table(string $title, array $headers, array $rows, array $filters): array { return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c'), 'orientation' => 'landscape']; }
}
