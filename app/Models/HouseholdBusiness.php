<?php

namespace App\Models;

use App\Core\BaseModel;

final class HouseholdBusiness extends BaseModel
{
    public const TYPE_LABELS = [
        'RESIDENT' => 'Hộ dân',
        'PRODUCTION' => 'Hộ sản xuất',
        'BUSINESS' => 'Hộ kinh doanh',
        'BOTH' => 'Hộ sản xuất và kinh doanh',
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS household_business (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  business_type ENUM('RESIDENT','PRODUCTION','BUSINESS','BOTH') NOT NULL DEFAULT 'RESIDENT',
  business_name VARCHAR(255) NULL,
  owner_name VARCHAR(255) NULL,
  production_sector VARCHAR(255) NULL,
  business_sector VARCHAR(255) NULL,
  business_license VARCHAR(100) NULL,
  license_date DATE NULL,
  license_place VARCHAR(255) NULL,
  tax_code VARCHAR(50) NULL,
  start_date DATE NULL,
  worker_count INT UNSIGNED NOT NULL DEFAULT 0,
  annual_revenue DECIMAL(18,2) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  address VARCHAR(500) NULL,
  latitude DECIMAL(10,8) NULL,
  longitude DECIMAL(11,8) NULL,
  status ENUM('ACTIVE','INACTIVE','SUSPENDED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_household_business_household (household_id),
  KEY idx_household_business_type (business_type),
  KEY idx_household_business_status (status),
  KEY idx_household_business_sector (production_sector, business_sector),
  KEY idx_household_business_license (business_license),
  KEY idx_household_business_tax (tax_code),
  KEY idx_household_business_location (latitude, longitude),
  CONSTRAINT fk_household_business_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM households h LEFT JOIN household_business b ON b.household_id = h.id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT b.*, h.id AS household_id_real, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude AS household_latitude, h.longitude AS household_longitude,
                    COALESCE(v.total_members,0) AS member_count
             FROM households h
             LEFT JOIN household_business b ON b.household_id = h.id
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             $where
             $order
             LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalize($row), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT b.*, h.id AS household_id_real, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude AS household_latitude, h.longitude AS household_longitude,
                    COALESCE(v.total_members,0) AS member_count
             FROM household_business b
             INNER JOIN households h ON h.id = b.household_id
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             WHERE b.id = :id AND b.status <> "DELETED" AND h.status <> "DELETED"',
            ['id' => $id]
        );
        return $row ? $this->normalize($row) : null;
    }

    public function findByHousehold(int $householdId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT b.*, h.id AS household_id_real, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude AS household_latitude, h.longitude AS household_longitude,
                    COALESCE(v.total_members,0) AS member_count
             FROM households h
             LEFT JOIN household_business b ON b.household_id = h.id AND b.status <> "DELETED"
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             WHERE h.id = :id AND h.status <> "DELETED"',
            ['id' => $householdId]
        );
        return $row ? $this->normalize($row) : null;
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->params($data, $userId);
        $before = $id ? $this->find($id) : null;
        if ($id && !$before) throw new \RuntimeException('Không tìm thấy thông tin hộ sản xuất/kinh doanh');
        if ($id) $params['id'] = $id;

        $existing = $this->fetchOne('SELECT id FROM household_business WHERE household_id = :household_id AND status <> "DELETED"' . ($id ? ' AND id <> :id' : ''), $id ? ['household_id' => $params['household_id'], 'id' => $id] : ['household_id' => $params['household_id']]);
        if ($existing) throw new \RuntimeException('Hộ này đã có thông tin sản xuất/kinh doanh');

        if ($id) {
            $this->execute(
                'UPDATE household_business SET household_id=:household_id,business_type=:business_type,business_name=:business_name,owner_name=:owner_name,production_sector=:production_sector,business_sector=:business_sector,business_license=:business_license,license_date=:license_date,license_place=:license_place,tax_code=:tax_code,start_date=:start_date,worker_count=:worker_count,annual_revenue=:annual_revenue,phone=:phone,email=:email,address=:address,latitude=:latitude,longitude=:longitude,status=:status,note=:note,updated_by=:user WHERE id=:id',
                $params
            );
            return $this->find($id);
        }

        $newId = $this->insert(
            'INSERT INTO household_business (household_id,business_type,business_name,owner_name,production_sector,business_sector,business_license,license_date,license_place,tax_code,start_date,worker_count,annual_revenue,phone,email,address,latitude,longitude,status,note,created_by,updated_by) VALUES (:household_id,:business_type,:business_name,:owner_name,:production_sector,:business_sector,:business_license,:license_date,:license_place,:tax_code,:start_date,:worker_count,:annual_revenue,:phone,:email,:address,:latitude,:longitude,:status,:note,:user,:user)',
            $params
        );
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy thông tin hộ sản xuất/kinh doanh');
        $this->execute('UPDATE household_business SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    public function members(int $householdId): array
    {
        return $this->fetchAll('SELECT id, citizen_code, full_name, relationship, gender, date_of_birth, identity_number, phone, residency_status, presence_status FROM citizens WHERE household_id = :id AND status <> "DELETED" ORDER BY CASE WHEN relationship = "Chủ hộ" THEN 0 ELSE 1 END, full_name', ['id' => $householdId]);
    }

    public function dashboard(): array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT
                COALESCE(SUM(CASE WHEN business_type = "PRODUCTION" THEN 1 ELSE 0 END),0) AS production,
                COALESCE(SUM(CASE WHEN business_type = "BUSINESS" THEN 1 ELSE 0 END),0) AS business,
                COALESCE(SUM(CASE WHEN business_type = "BOTH" THEN 1 ELSE 0 END),0) AS both,
                COALESCE(SUM(worker_count),0) AS workers
             FROM household_business WHERE status <> "DELETED"'
        ) ?: [];
        return [
            'production_households' => (int) ($row['production'] ?? 0),
            'business_households' => (int) ($row['business'] ?? 0),
            'production_business_households' => (int) ($row['both'] ?? 0),
            'business_worker_total' => (int) ($row['workers'] ?? 0),
        ];
    }

    public function charts(): array
    {
        $this->ensureSchema();
        return [
            'types' => $this->fetchAll('SELECT business_type AS code, business_type AS label, COUNT(*) AS value FROM household_business WHERE status <> "DELETED" GROUP BY business_type ORDER BY business_type'),
            'sectors' => $this->fetchAll('SELECT COALESCE(NULLIF(production_sector,""), NULLIF(business_sector,""), "Chưa cập nhật") AS label, COUNT(*) AS value FROM household_business WHERE status <> "DELETED" GROUP BY label ORDER BY value DESC, label LIMIT 10'),
            'statuses' => $this->fetchAll('SELECT status AS label, COUNT(*) AS value FROM household_business WHERE status <> "DELETED" GROUP BY status ORDER BY status'),
        ];
    }

    public function report(string $mode, array $filters = []): array
    {
        $filters['pageSize'] = 100;
        $filters['page'] = 1;
        if ($mode === 'production') $filters['business_type'] = 'PRODUCTION';
        if ($mode === 'business') $filters['business_type'] = 'BUSINESS';
        if ($mode === 'gis') $filters['located'] = '1';
        $rows = $this->paginate($filters)['items'];
        if ($mode === 'sector') {
            $body = array_map(fn($r) => [$r['sector_label'], $r['household_code'], $r['head_citizen_name'], $r['business_name'], $r['business_type_label'], $r['worker_count'], $r['status_label']], $rows);
            return $this->table('Báo cáo hộ sản xuất/kinh doanh theo ngành nghề', ['Ngành nghề','Mã hộ','Chủ hộ','Tên cơ sở','Loại hình','Lao động','Trạng thái'], $body, $filters);
        }
        if ($mode === 'status') {
            $body = array_map(fn($r) => [$r['status_label'], $r['household_code'], $r['head_citizen_name'], $r['business_name'], $r['business_type_label'], $r['sector_label'], $r['worker_count']], $rows);
            return $this->table('Báo cáo hộ sản xuất/kinh doanh theo trạng thái', ['Trạng thái','Mã hộ','Chủ hộ','Tên cơ sở','Loại hình','Ngành nghề','Lao động'], $body, $filters);
        }
        $title = match ($mode) {
            'production' => 'Danh sách hộ sản xuất',
            'business' => 'Danh sách hộ kinh doanh',
            'gis' => 'Báo cáo hộ sản xuất/kinh doanh theo khu vực GIS',
            default => 'Danh sách hộ sản xuất và kinh doanh',
        };
        $body = array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['business_name'], $r['business_type_label'], $r['sector_label'], $r['worker_count'], $r['business_license'] ?: 'Không', $r['tax_code'] ?: 'Không', $r['status_label'], $r['address']], $rows);
        return $this->table($title, ['Mã hộ','Chủ hộ','Tên cơ sở','Loại hình','Ngành nghề','Lao động','Giấy phép','Mã số thuế','Trạng thái','Địa chỉ'], $body, $filters);
    }

    private function where(array $filters): array
    {
        $where = ['h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR h.address LIKE :q OR b.business_name LIKE :q OR b.owner_name LIKE :q OR b.production_sector LIKE :q OR b.business_sector LIKE :q OR b.phone LIKE :q OR b.tax_code LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $type = $this->businessType($filters['business_type'] ?? $filters['businessType'] ?? '');
        if ($type !== '') {
            $where[] = 'b.business_type = :business_type';
            $params['business_type'] = $type;
        }
        $sector = trim((string) ($filters['sector'] ?? ''));
        if ($sector !== '') {
            $where[] = '(b.production_sector LIKE :sector OR b.business_sector LIKE :sector)';
            $params['sector'] = '%' . $sector . '%';
        }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'b.status = :status';
            $params['status'] = $status;
        } else {
            $where[] = '(b.status IS NULL OR b.status <> "DELETED")';
        }
        foreach (['license' => 'business_license', 'tax' => 'tax_code'] as $filter => $column) {
            $value = trim((string) ($filters[$filter] ?? ''));
            if ($value === '1') $where[] = "b.$column IS NOT NULL AND b.$column <> ''";
            if ($value === '0') $where[] = "(b.$column IS NULL OR b.$column = '')";
        }
        $located = trim((string) ($filters['located'] ?? ''));
        if ($located === '1') $where[] = '((b.latitude IS NOT NULL AND b.longitude IS NOT NULL) OR (h.latitude IS NOT NULL AND h.longitude IS NOT NULL))';
        if ($located === '0') $where[] = '((b.latitude IS NULL OR b.longitude IS NULL) AND (h.latitude IS NULL OR h.longitude IS NULL))';
        $sort = preg_replace('/[^a-z_]/', '', (string) ($filters['sort'] ?? 'household_code'));
        $direction = strtoupper((string) ($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sortMap = [
            'household_code' => 'h.household_code',
            'head_citizen_name' => 'h.head_citizen_name',
            'business_name' => 'b.business_name',
            'business_type' => 'b.business_type',
            'sector' => 'sector_label',
            'worker_count' => 'b.worker_count',
            'status' => 'b.status',
            'address' => 'COALESCE(NULLIF(b.address,""), h.address)',
        ];
        return ['WHERE ' . implode(' AND ', $where), $params, 'ORDER BY ' . ($sortMap[$sort] ?? 'h.household_code') . ' ' . $direction . ', h.household_code ASC'];
    }

    private function params(array $data, int $userId): array
    {
        $householdId = (int) ($data['household_id'] ?? $data['householdId'] ?? 0);
        if ($householdId <= 0) throw new \RuntimeException('Hộ gia đình là bắt buộc');
        if (!$this->fetchOne('SELECT id FROM households WHERE id = :id AND status <> "DELETED"', ['id' => $householdId])) {
            throw new \RuntimeException('Không tìm thấy hộ gia đình liên kết');
        }
        return [
            'household_id' => $householdId,
            'business_type' => $this->businessType($data['business_type'] ?? $data['businessType'] ?? 'RESIDENT') ?: 'RESIDENT',
            'business_name' => $this->nullable($data['business_name'] ?? $data['businessName'] ?? null),
            'owner_name' => $this->nullable($data['owner_name'] ?? $data['ownerName'] ?? null),
            'production_sector' => $this->nullable($data['production_sector'] ?? $data['productionSector'] ?? null),
            'business_sector' => $this->nullable($data['business_sector'] ?? $data['businessSector'] ?? $data['sector'] ?? null),
            'business_license' => $this->nullable($data['business_license'] ?? $data['businessLicense'] ?? null),
            'license_date' => $this->dateOrNull($data['license_date'] ?? $data['licenseDate'] ?? null),
            'license_place' => $this->nullable($data['license_place'] ?? $data['licensePlace'] ?? null),
            'tax_code' => $this->nullable($data['tax_code'] ?? $data['taxCode'] ?? null),
            'start_date' => $this->dateOrNull($data['start_date'] ?? $data['startDate'] ?? null),
            'worker_count' => max(0, (int) ($data['worker_count'] ?? $data['workerCount'] ?? 0)),
            'annual_revenue' => isset($data['annual_revenue']) || isset($data['annualRevenue']) ? (float) ($data['annual_revenue'] ?? $data['annualRevenue'] ?? 0) : null,
            'phone' => $this->nullable($data['phone'] ?? null),
            'email' => $this->nullable($data['email'] ?? null),
            'address' => $this->nullable($data['address'] ?? null),
            'latitude' => $this->coordinateOrNull($data['latitude'] ?? null, -90, 90),
            'longitude' => $this->coordinateOrNull($data['longitude'] ?? null, -180, 180),
            'status' => in_array(strtoupper((string) ($data['status'] ?? 'ACTIVE')), ['ACTIVE','INACTIVE','SUSPENDED'], true) ? strtoupper((string) ($data['status'] ?? 'ACTIVE')) : 'ACTIVE',
            'note' => $this->nullable($data['note'] ?? null),
            'user' => $userId,
        ];
    }

    private function normalize(array $row): array
    {
        $type = $this->businessType($row['business_type'] ?? '') ?: 'RESIDENT';
        $sector = trim((string) ($row['production_sector'] ?? '')) ?: trim((string) ($row['business_sector'] ?? ''));
        $lat = $row['latitude'] ?? $row['household_latitude'] ?? null;
        $lng = $row['longitude'] ?? $row['household_longitude'] ?? null;
        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'household_id' => (int) ($row['household_id'] ?? $row['household_id_real'] ?? 0),
            'household_code' => (string) ($row['household_code'] ?? ''),
            'head_citizen_name' => (string) ($row['head_citizen_name'] ?? ''),
            'business_type' => $type,
            'business_type_label' => self::TYPE_LABELS[$type] ?? $type,
            'business_name' => (string) ($row['business_name'] ?? ''),
            'owner_name' => (string) ($row['owner_name'] ?? $row['head_citizen_name'] ?? ''),
            'production_sector' => (string) ($row['production_sector'] ?? ''),
            'business_sector' => (string) ($row['business_sector'] ?? ''),
            'sector_label' => $sector,
            'business_license' => (string) ($row['business_license'] ?? ''),
            'license_date' => $row['license_date'] ?? null,
            'license_place' => (string) ($row['license_place'] ?? ''),
            'tax_code' => (string) ($row['tax_code'] ?? ''),
            'start_date' => $row['start_date'] ?? null,
            'worker_count' => (int) ($row['worker_count'] ?? 0),
            'annual_revenue' => $row['annual_revenue'] ?? null,
            'phone' => (string) ($row['phone'] ?? $row['household_phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'address' => (string) ($row['address'] ?? $row['household_address'] ?? ''),
            'latitude' => $lat !== null && $lat !== '' ? (float) $lat : null,
            'longitude' => $lng !== null && $lng !== '' ? (float) $lng : null,
            'status' => (string) ($row['status'] ?? 'ACTIVE'),
            'status_label' => $this->statusLabel($row['status'] ?? 'ACTIVE'),
            'note' => (string) ($row['note'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
            'member_count' => (int) ($row['member_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'filters' => $filters, 'totalRows' => count($rows), 'generatedAt' => date('c')];
    }

    private function businessType(mixed $value): string
    {
        $text = strtoupper(trim((string) $value));
        return match ($text) {
            'RESIDENT', 'HO_DAN', 'HODAN' => 'RESIDENT',
            'PRODUCTION', 'SAN_XUAT', 'HOSANXUAT' => 'PRODUCTION',
            'BUSINESS', 'KINH_DOANH', 'HOKINHDOANH' => 'BUSINESS',
            'BOTH', 'PRODUCTION_BUSINESS', 'SAN_XUAT_KINH_DOANH' => 'BOTH',
            default => '',
        };
    }

    private function statusLabel(mixed $value): string
    {
        return ['ACTIVE' => 'Đang hoạt động', 'INACTIVE' => 'Ngừng hoạt động', 'SUSPENDED' => 'Tạm ngừng', 'DELETED' => 'Đã xóa'][strtoupper((string) $value)] ?? (string) $value;
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : null;
    }

    private function coordinateOrNull(mixed $value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) return null;
        $number = (float) $value;
        return $number >= $min && $number <= $max ? round($number, 8) : null;
    }
}
