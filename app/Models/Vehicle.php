<?php

namespace App\Models;

use App\Core\BaseModel;

final class Vehicle extends BaseModel
{
    public const TYPES = ['Xe đạp','Xe máy','Xe điện','Ô tô','Máy kéo','Máy cày','Xe công nông','Xe tải','Xe khách','Thuyền','Các loại khác'];
    public const USAGE_LABELS = [
        'USING' => 'Đang sử dụng',
        'REPAIRING' => 'Đang sửa chữa',
        'INACTIVE' => 'Ngừng sử dụng',
        'SOLD' => 'Đã chuyển nhượng',
    ];
    public const STATUS_LABELS = [
        'ACTIVE' => 'Hoạt động',
        'INACTIVE' => 'Tạm dừng',
        'DELETED' => 'Đã xóa',
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS vehicles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  owner_name VARCHAR(180) NULL,
  vehicle_type VARCHAR(80) NOT NULL,
  brand VARCHAR(120) NULL,
  model VARCHAR(120) NULL,
  license_plate VARCHAR(40) NULL,
  frame_number VARCHAR(80) NULL,
  engine_number VARCHAR(80) NULL,
  manufacture_year SMALLINT UNSIGNED NULL,
  color VARCHAR(80) NULL,
  usage_status ENUM('USING','REPAIRING','INACTIVE','SOLD') NOT NULL DEFAULT 'USING',
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_vehicles_household (household_id),
  KEY idx_vehicles_type (vehicle_type),
  KEY idx_vehicles_plate (license_plate),
  KEY idx_vehicles_status (status),
  CONSTRAINT fk_vehicles_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function catalogs(): array
    {
        return [
            'vehicle_types' => array_map(fn($v) => ['value' => $v, 'label' => $v], self::TYPES),
            'usage_statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::USAGE_LABELS), array_values(self::USAGE_LABELS)),
            'statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::STATUS_LABELS), array_values(self::STATUS_LABELS)),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM vehicles v INNER JOIN households h ON h.id=v.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT v.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude
             FROM vehicles v INNER JOIN households h ON h.id=v.household_id
             $where $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalize($row), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT v.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude
             FROM vehicles v INNER JOIN households h ON h.id=v.household_id
             WHERE v.id=:id AND v.status <> "DELETED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")',
            ['id' => $id]
        );
        return $row ? $this->normalize($row) : null;
    }

    public function findByHousehold(int $householdId): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll(
            'SELECT v.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude
             FROM vehicles v INNER JOIN households h ON h.id=v.household_id
             WHERE v.household_id=:household_id AND v.status <> "DELETED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
             ORDER BY v.vehicle_type ASC, v.id DESC',
            ['household_id' => $householdId]
        );
        return array_map(fn($row) => $this->normalize($row), $rows);
    }

    public function searchHouseholds(string $query, int $limit = 10): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, COALESCE(vc.vehicle_count,0) AS vehicle_count
             FROM households h
             LEFT JOIN (SELECT household_id, COUNT(*) AS vehicle_count FROM vehicles WHERE status <> "DELETED" GROUP BY household_id) vc ON vc.household_id=h.id
             WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND (LOWER(h.household_code) LIKE :code OR LOWER(h.head_citizen_name) LIKE :head OR LOWER(h.address) LIKE :address)
             ORDER BY h.household_code ASC LIMIT ' . max(1, min(20, $limit)),
            ['code' => $keyword, 'head' => $keyword, 'address' => $keyword]
        );
        return array_map(fn($r) => ['id' => (int) $r['id'], 'household_code' => (string) $r['household_code'], 'head_citizen_name' => (string) $r['head_citizen_name'], 'address' => (string) ($r['address'] ?? ''), 'phone' => (string) ($r['phone'] ?? ''), 'vehicle_count' => (int) ($r['vehicle_count'] ?? 0)], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->params($data, $userId, $id);
        if ($id && !$this->find($id)) throw new \RuntimeException('Không tìm thấy phương tiện');
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE vehicles SET household_id=:household_id, owner_name=:owner_name, vehicle_type=:vehicle_type, brand=:brand, model=:model, license_plate=:license_plate, frame_number=:frame_number, engine_number=:engine_number, manufacture_year=:manufacture_year, color=:color, usage_status=:usage_status, status=:status, note=:note, updated_by=:user WHERE id=:id', $params);
            return $this->find($id);
        }
        $insertParams = $params + ['created_by' => $userId, 'updated_by' => $userId];
        unset($insertParams['user']);
        $newId = $this->insert('INSERT INTO vehicles (household_id, owner_name, vehicle_type, brand, model, license_plate, frame_number, engine_number, manufacture_year, color, usage_status, status, note, created_by, updated_by) VALUES (:household_id,:owner_name,:vehicle_type,:brand,:model,:license_plate,:frame_number,:engine_number,:manufacture_year,:color,:usage_status,:status,:note,:created_by,:updated_by)', $insertParams);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy phương tiện');
        $this->execute('UPDATE vehicles SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id', ['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total, COUNT(DISTINCT v.household_id) AS households,
                COALESCE(SUM(CASE WHEN v.vehicle_type='Ô tô' THEN 1 ELSE 0 END),0) AS cars,
                COALESCE(SUM(CASE WHEN v.vehicle_type='Xe máy' THEN 1 ELSE 0 END),0) AS motorbikes,
                COALESCE(SUM(CASE WHEN v.vehicle_type='Xe điện' THEN 1 ELSE 0 END),0) AS electric,
                COALESCE(SUM(CASE WHEN v.vehicle_type IN ('Xe tải','Xe khách') THEN 1 ELSE 0 END),0) AS transport,
                COALESCE(SUM(CASE WHEN v.vehicle_type IN ('Máy kéo','Máy cày','Xe công nông') THEN 1 ELSE 0 END),0) AS farm,
                COALESCE(SUM(CASE WHEN v.license_plate IS NOT NULL AND v.license_plate <> '' THEN 1 ELSE 0 END),0) AS with_plate
             FROM vehicles v INNER JOIN households h ON h.id=v.household_id $where",
            $params
        ) ?: [];
        return array_map('intval', ['total' => $row['total'] ?? 0, 'households' => $row['households'] ?? 0, 'cars' => $row['cars'] ?? 0, 'motorbikes' => $row['motorbikes'] ?? 0, 'electric' => $row['electric'] ?? 0, 'transport' => $row['transport'] ?? 0, 'farm' => $row['farm'] ?? 0, 'with_plate' => $row['with_plate'] ?? 0]);
    }

    public function charts(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        return [
            'types' => $this->fetchAll("SELECT v.vehicle_type AS label, COUNT(*) AS value FROM vehicles v INNER JOIN households h ON h.id=v.household_id $where GROUP BY v.vehicle_type ORDER BY value DESC, v.vehicle_type", $params),
            'households' => $this->topHouseholds($filters),
            'areas' => $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),'Chưa phân khu') AS label, COUNT(*) AS value FROM vehicles v INNER JOIN households h ON h.id=v.household_id $where GROUP BY label ORDER BY value DESC, label LIMIT 10", $params),
            'usage' => $this->fetchAll("SELECT v.usage_status AS value, COUNT(*) AS total FROM vehicles v INNER JOIN households h ON h.id=v.household_id $where GROUP BY v.usage_status ORDER BY total DESC", $params),
        ];
    }

    public function topHouseholds(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $rows = $this->fetchAll("SELECT h.id AS household_id, h.household_code, h.head_citizen_name, COUNT(v.id) AS value FROM vehicles v INNER JOIN households h ON h.id=v.household_id $where GROUP BY h.id, h.household_code, h.head_citizen_name ORDER BY value DESC, h.household_code ASC LIMIT 10", $params);
        return array_map(fn($r) => ['household_id' => (int) $r['household_id'], 'household_code' => (string) $r['household_code'], 'head_citizen_name' => (string) $r['head_citizen_name'], 'label' => (string) $r['household_code'], 'value' => (int) $r['value']], $rows);
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'by_type' && !empty($filters['vehicle_type'])) $filters['vehicle_type'] = $filters['vehicle_type'];
        if ($mode === 'missing_plate') $filters['missing_plate'] = '1';
        $filters['page'] = 1;
        $filters['pageSize'] = 100;
        $rows = $this->paginate($filters)['items'];
        $title = match ($mode) {
            'by_type' => 'Báo cáo phương tiện theo loại',
            'missing_plate' => 'Danh sách phương tiện chưa có biển kiểm soát',
            default => 'Danh sách phương tiện',
        };
        return $this->table($title, ['Mã hộ','Chủ hộ','Chủ sở hữu','Loại','Nhãn hiệu','Model','Biển kiểm soát','Số khung','Số máy','Năm SX','Màu','Tình trạng','Ghi chú'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['owner_name'], $r['vehicle_type'], $r['brand'], $r['model'], $r['license_plate'], $r['frame_number'], $r['engine_number'], $r['manufacture_year'], $r['color'], $r['usage_status_label'], $r['note']], $rows), $filters);
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['v.status <> "DELETED"', 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $keyword = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $parts = [];
            foreach (['h.household_code' => 'q_code','h.head_citizen_name' => 'q_head','h.address' => 'q_address','v.owner_name' => 'q_owner','v.vehicle_type' => 'q_type','v.brand' => 'q_brand','v.model' => 'q_model','v.license_plate' => 'q_plate','v.frame_number' => 'q_frame','v.engine_number' => 'q_engine','v.color' => 'q_color'] as $column => $param) {
                $parts[] = "LOWER($column) LIKE :$param";
                $params[$param] = $keyword;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        $type = trim((string) ($filters['vehicle_type'] ?? $filters['vehicleType'] ?? ''));
        if ($type !== '') { $where[] = 'v.vehicle_type = :vehicle_type'; $params['vehicle_type'] = $type; }
        $householdId = (int) ($filters['household_id'] ?? $filters['householdId'] ?? 0);
        if ($householdId > 0) { $where[] = 'v.household_id = :household_id'; $params['household_id'] = $householdId; }
        $owner = trim((string) ($filters['owner_name'] ?? $filters['ownerName'] ?? ''));
        if ($owner !== '') { $where[] = 'LOWER(v.owner_name) LIKE :owner_name'; $params['owner_name'] = '%' . mb_strtolower($owner, 'UTF-8') . '%'; }
        $usage = strtoupper(trim((string) ($filters['usage_status'] ?? $filters['usageStatus'] ?? '')));
        if ($usage !== '' && isset(self::USAGE_LABELS[$usage])) { $where[] = 'v.usage_status = :usage_status'; $params['usage_status'] = $usage; }
        $area = trim((string) ($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') { $where[] = 'h.area_code = :area_code'; $params['area_code'] = $area; }
        if ((string) ($filters['missing_plate'] ?? '') === '1') $where[] = '(v.license_plate IS NULL OR v.license_plate = "")';
        $from = trim((string) ($filters['date_from'] ?? $filters['dateFrom'] ?? ''));
        if ($from !== '') { $where[] = 'DATE(COALESCE(v.updated_at, v.created_at)) >= :date_from'; $params['date_from'] = $from; }
        $to = trim((string) ($filters['date_to'] ?? $filters['dateTo'] ?? ''));
        if ($to !== '') { $where[] = 'DATE(COALESCE(v.updated_at, v.created_at)) <= :date_to'; $params['date_to'] = $to; }
        $sort = preg_replace('/[^a-z_]/', '', (string) ($filters['sort'] ?? 'household_code'));
        $direction = strtoupper((string) ($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sortMap = ['household_code' => 'h.household_code','owner_name' => 'v.owner_name','vehicle_type' => 'v.vehicle_type','brand' => 'v.brand','license_plate' => 'v.license_plate','usage_status' => 'v.usage_status','updated_at' => 'COALESCE(v.updated_at,v.created_at)'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = 'ORDER BY ' . ($sortMap[$sort] ?? 'h.household_code') . ' ' . $direction . ', v.id DESC';
        return $result;
    }

    private function params(array $data, int $userId, ?int $id): array
    {
        $householdId = (int) ($data['household_id'] ?? $data['householdId'] ?? 0);
        if ($householdId <= 0) throw new \RuntimeException('Hộ gia đình là bắt buộc');
        if (!$this->fetchOne('SELECT id FROM households WHERE id=:id AND status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', ['id' => $householdId])) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $type = trim((string) ($data['vehicle_type'] ?? $data['vehicleType'] ?? ''));
        if ($type === '') throw new \RuntimeException('Loại phương tiện là bắt buộc');
        $usage = strtoupper(trim((string) ($data['usage_status'] ?? $data['usageStatus'] ?? 'USING')));
        if (!isset(self::USAGE_LABELS[$usage])) $usage = 'USING';
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::STATUS_LABELS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        $year = trim((string) ($data['manufacture_year'] ?? $data['manufactureYear'] ?? ''));
        $yearValue = $year === '' ? null : max(1900, min((int) date('Y') + 1, (int) $year));
        return [
            'household_id' => $householdId,
            'owner_name' => trim((string) ($data['owner_name'] ?? $data['ownerName'] ?? '')) ?: null,
            'vehicle_type' => $type,
            'brand' => trim((string) ($data['brand'] ?? '')) ?: null,
            'model' => trim((string) ($data['model'] ?? '')) ?: null,
            'license_plate' => strtoupper(trim((string) ($data['license_plate'] ?? $data['licensePlate'] ?? ''))) ?: null,
            'frame_number' => trim((string) ($data['frame_number'] ?? $data['frameNumber'] ?? '')) ?: null,
            'engine_number' => trim((string) ($data['engine_number'] ?? $data['engineNumber'] ?? '')) ?: null,
            'manufacture_year' => $yearValue,
            'color' => trim((string) ($data['color'] ?? '')) ?: null,
            'usage_status' => $usage,
            'status' => $status,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'user' => $userId,
        ];
    }

    private function normalize(array $row): array
    {
        $usage = (string) ($row['usage_status'] ?? 'USING');
        $status = (string) ($row['status'] ?? 'ACTIVE');
        return [
            'id' => (int) $row['id'],
            'household_id' => (int) $row['household_id'],
            'household_code' => (string) ($row['household_code'] ?? ''),
            'head_citizen_name' => (string) ($row['head_citizen_name'] ?? ''),
            'owner_name' => (string) ($row['owner_name'] ?? ''),
            'vehicle_type' => (string) ($row['vehicle_type'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'model' => (string) ($row['model'] ?? ''),
            'license_plate' => (string) ($row['license_plate'] ?? ''),
            'frame_number' => (string) ($row['frame_number'] ?? ''),
            'engine_number' => (string) ($row['engine_number'] ?? ''),
            'manufacture_year' => $row['manufacture_year'] !== null ? (int) $row['manufacture_year'] : null,
            'color' => (string) ($row['color'] ?? ''),
            'usage_status' => $usage,
            'usage_status_label' => self::USAGE_LABELS[$usage] ?? $usage,
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? $status,
            'note' => (string) ($row['note'] ?? ''),
            'address' => (string) ($row['household_address'] ?? $row['address'] ?? ''),
            'phone' => (string) ($row['household_phone'] ?? $row['phone'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
            'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float) $row['longitude'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')];
    }
}
