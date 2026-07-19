<?php

namespace App\Models;

use App\Core\BaseModel;

final class Vehicle extends BaseModel
{
    public const TYPES = ['Xe đạp','Xe máy','Xe điện','Ô tô','Máy kéo','Máy cày','Xe công nông','Xe tải','Xe khách','Thuyền','Các loại khác'];
    public const DETAIL_TYPES = ['Xe máy','Xe máy điện','Xe đạp điện','Ô tô con','Xe tải','Xe khách','Máy kéo','Máy cày','Xe công nông','Thuyền','Khác'];
    public const USAGE_LABELS = [
        'USING' => 'Đang sử dụng',
        'INACTIVE' => 'Không sử dụng',
        'SOLD' => 'Đã bán',
        'LIQUIDATED' => 'Đã thanh lý',
        'DAMAGED' => 'Hư hỏng',
        'LOST' => 'Mất',
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
  vehicle_code VARCHAR(40) NULL,
  household_id BIGINT UNSIGNED NOT NULL,
  owner_citizen_id BIGINT UNSIGNED NULL,
  owner_name VARCHAR(180) NULL,
  vehicle_type VARCHAR(80) NOT NULL,
  detail_type VARCHAR(120) NULL,
  brand VARCHAR(120) NULL,
  model VARCHAR(120) NULL,
  version_name VARCHAR(120) NULL,
  license_plate VARCHAR(40) NULL,
  frame_number VARCHAR(80) NULL,
  engine_number VARCHAR(80) NULL,
  registration_date DATE NULL,
  registration_place VARCHAR(180) NULL,
  manufacture_year SMALLINT UNSIGNED NULL,
  color VARCHAR(80) NULL,
  usage_status ENUM('USING','INACTIVE','SOLD','LIQUIDATED','DAMAGED','LOST') NOT NULL DEFAULT 'USING',
  has_insurance TINYINT(1) NOT NULL DEFAULT 0,
  insurance_expiry_date DATE NULL,
  has_inspection TINYINT(1) NOT NULL DEFAULT 0,
  inspection_expiry_date DATE NULL,
  vehicle_photo_path VARCHAR(255) NULL,
  plate_photo_path VARCHAR(255) NULL,
  registration_photo_path VARCHAR(255) NULL,
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_vehicles_household (household_id),
  KEY idx_vehicles_code (vehicle_code),
  KEY idx_vehicles_owner_citizen (owner_citizen_id),
  KEY idx_vehicles_type (vehicle_type),
  KEY idx_vehicles_plate (license_plate),
  KEY idx_vehicles_status (status),
  CONSTRAINT fk_vehicles_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureColumns();
    }

    public function catalogs(): array
    {
        return [
            'vehicle_types' => array_map(fn($v) => ['value' => $v, 'label' => $v], self::TYPES),
            'detail_types' => array_map(fn($v) => ['value' => $v, 'label' => $v], self::DETAIL_TYPES),
            'usage_statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::USAGE_LABELS), array_values(self::USAGE_LABELS)),
            'statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::STATUS_LABELS), array_values(self::STATUS_LABELS)),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM vehicles v INNER JOIN households h ON h.id=v.household_id LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT v.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude,
                    oc.citizen_code AS owner_citizen_code, oc.full_name AS owner_citizen_name
             FROM vehicles v
             INNER JOIN households h ON h.id=v.household_id
             LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id
             $where $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT v.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude,
                    oc.citizen_code AS owner_citizen_code, oc.full_name AS owner_citizen_name
             FROM vehicles v
             INNER JOIN households h ON h.id=v.household_id
             LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id
             WHERE v.id=:id AND v.status <> "DELETED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")',
            ['id' => $id]
        );
        return $row ? $this->normalize($row) : null;
    }

    public function findByHousehold(int $householdId): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll(
            'SELECT v.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude,
                    oc.citizen_code AS owner_citizen_code, oc.full_name AS owner_citizen_name
             FROM vehicles v
             INNER JOIN households h ON h.id=v.household_id
             LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id
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

    public function searchCitizens(int $householdId, string $query = '', int $limit = 20): array
    {
        $query = trim($query);
        $params = ['household_id' => $householdId];
        $where = ['c.household_id=:household_id', 'c.status NOT IN ("DELETED","INACTIVE")'];
        if ($query !== '') {
            $where[] = '(LOWER(c.full_name) LIKE :q OR LOWER(c.citizen_code) LIKE :q OR LOWER(c.identity_number) LIKE :q)';
            $params['q'] = '%' . mb_strtolower($query, 'UTF-8') . '%';
        }
        $rows = $this->fetchAll('SELECT c.id, c.citizen_code, c.full_name, c.identity_number FROM citizens c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.relationship="Chủ hộ" DESC, c.full_name ASC LIMIT ' . max(1, min(50, $limit)), $params);
        return array_map(fn($r) => ['id' => (int) $r['id'], 'citizen_code' => (string) ($r['citizen_code'] ?? ''), 'full_name' => (string) ($r['full_name'] ?? ''), 'identity_number' => (string) ($r['identity_number'] ?? '')], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->params($data, $userId, $id);
        if ($id && !$this->find($id)) throw new \RuntimeException('Không tìm thấy phương tiện');
        if ($id) {
            $params['id'] = $id;
            $this->execute(
                'UPDATE vehicles SET household_id=:household_id, owner_citizen_id=:owner_citizen_id, owner_name=:owner_name, vehicle_type=:vehicle_type, detail_type=:detail_type, brand=:brand, model=:model, version_name=:version_name, license_plate=:license_plate, frame_number=:frame_number, engine_number=:engine_number, registration_date=:registration_date, registration_place=:registration_place, manufacture_year=:manufacture_year, color=:color, usage_status=:usage_status, has_insurance=:has_insurance, insurance_expiry_date=:insurance_expiry_date, has_inspection=:has_inspection, inspection_expiry_date=:inspection_expiry_date, vehicle_photo_path=:vehicle_photo_path, plate_photo_path=:plate_photo_path, registration_photo_path=:registration_photo_path, status=:status, note=:note, updated_by=:user WHERE id=:id',
                $params
            );
            return $this->find($id);
        }
        $insertParams = $params + ['vehicle_code' => $this->nextCode(), 'created_by' => $userId, 'updated_by' => $userId];
        unset($insertParams['user']);
        $newId = $this->insert(
            'INSERT INTO vehicles (vehicle_code, household_id, owner_citizen_id, owner_name, vehicle_type, detail_type, brand, model, version_name, license_plate, frame_number, engine_number, registration_date, registration_place, manufacture_year, color, usage_status, has_insurance, insurance_expiry_date, has_inspection, inspection_expiry_date, vehicle_photo_path, plate_photo_path, registration_photo_path, status, note, created_by, updated_by) VALUES (:vehicle_code,:household_id,:owner_citizen_id,:owner_name,:vehicle_type,:detail_type,:brand,:model,:version_name,:license_plate,:frame_number,:engine_number,:registration_date,:registration_place,:manufacture_year,:color,:usage_status,:has_insurance,:insurance_expiry_date,:has_inspection,:inspection_expiry_date,:vehicle_photo_path,:plate_photo_path,:registration_photo_path,:status,:note,:created_by,:updated_by)',
            $insertParams
        );
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
                COALESCE(SUM(CASE WHEN v.vehicle_type='Ô tô' OR v.detail_type LIKE '%ô tô%' THEN 1 ELSE 0 END),0) AS cars,
                COALESCE(SUM(CASE WHEN v.vehicle_type='Xe máy' OR v.detail_type LIKE '%xe máy%' THEN 1 ELSE 0 END),0) AS motorbikes,
                COALESCE(SUM(CASE WHEN v.vehicle_type='Xe điện' OR v.detail_type LIKE '%điện%' THEN 1 ELSE 0 END),0) AS electric,
                COALESCE(SUM(CASE WHEN v.license_plate IS NOT NULL AND v.license_plate <> '' THEN 1 ELSE 0 END),0) AS with_plate,
                COALESCE(SUM(CASE WHEN v.license_plate IS NULL OR v.license_plate = '' THEN 1 ELSE 0 END),0) AS without_plate,
                COALESCE(SUM(CASE WHEN v.has_inspection=1 AND v.inspection_expiry_date IS NOT NULL AND v.inspection_expiry_date < CURDATE() THEN 1 ELSE 0 END),0) AS expired_inspection,
                COALESCE(SUM(CASE WHEN v.has_insurance=1 AND v.insurance_expiry_date IS NOT NULL AND v.insurance_expiry_date < CURDATE() THEN 1 ELSE 0 END),0) AS expired_insurance
             FROM vehicles v INNER JOIN households h ON h.id=v.household_id LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id $where",
            $params
        ) ?: [];
        return array_map('intval', ['total' => $row['total'] ?? 0, 'households' => $row['households'] ?? 0, 'cars' => $row['cars'] ?? 0, 'motorbikes' => $row['motorbikes'] ?? 0, 'electric' => $row['electric'] ?? 0, 'with_plate' => $row['with_plate'] ?? 0, 'without_plate' => $row['without_plate'] ?? 0, 'expired_inspection' => $row['expired_inspection'] ?? 0, 'expired_insurance' => $row['expired_insurance'] ?? 0]);
    }

    public function charts(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        return [
            'types' => $this->fetchAll("SELECT v.vehicle_type AS label, COUNT(*) AS value FROM vehicles v INNER JOIN households h ON h.id=v.household_id LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id $where GROUP BY v.vehicle_type ORDER BY value DESC, v.vehicle_type", $params),
            'details' => $this->fetchAll("SELECT COALESCE(NULLIF(v.detail_type,''),'Chưa phân loại') AS label, COUNT(*) AS value FROM vehicles v INNER JOIN households h ON h.id=v.household_id LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id $where GROUP BY label ORDER BY value DESC LIMIT 12", $params),
            'households' => $this->topHouseholds($filters),
            'areas' => $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),'Chưa phân khu') AS label, COUNT(*) AS value FROM vehicles v INNER JOIN households h ON h.id=v.household_id LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id $where GROUP BY label ORDER BY value DESC, label LIMIT 10", $params),
        ];
    }

    public function topHouseholds(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $rows = $this->fetchAll("SELECT h.id AS household_id, h.household_code, h.head_citizen_name, COUNT(v.id) AS value FROM vehicles v INNER JOIN households h ON h.id=v.household_id LEFT JOIN citizens oc ON oc.id=v.owner_citizen_id $where GROUP BY h.id, h.household_code, h.head_citizen_name ORDER BY value DESC, h.household_code ASC LIMIT 10", $params);
        return array_map(fn($r) => ['household_id' => (int) $r['household_id'], 'household_code' => (string) $r['household_code'], 'head_citizen_name' => (string) $r['head_citizen_name'], 'label' => (string) $r['household_code'], 'value' => (int) $r['value']], $rows);
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'missing_plate') $filters['missing_plate'] = '1';
        if ($mode === 'expired_inspection') $filters['expired_inspection'] = '1';
        if ($mode === 'expired_insurance') $filters['expired_insurance'] = '1';
        $filters['page'] = 1;
        $filters['pageSize'] = 100;
        $rows = $this->paginate($filters)['items'];
        $title = match ($mode) {
            'by_type' => 'Báo cáo phương tiện theo loại',
            'missing_plate' => 'Danh sách phương tiện chưa có biển kiểm soát',
            'expired_inspection' => 'Danh sách phương tiện hết hạn kiểm định',
            'expired_insurance' => 'Danh sách phương tiện hết hạn bảo hiểm',
            default => 'Danh sách phương tiện',
        };
        return $this->table($title, ['Mã phương tiện','Mã hộ','Chủ hộ','Chủ sở hữu','Mã nhân khẩu','Loại','Phân loại','Nhãn hiệu','Model','Phiên bản','Biển số','Số khung','Số máy','Ngày đăng ký','Nơi đăng ký','Năm SX','Màu','Tình trạng','Bảo hiểm','Hạn BH','Kiểm định','Hạn KĐ','Ghi chú'], array_map(fn($r) => [$r['vehicle_code'], $r['household_code'], $r['head_citizen_name'], $r['owner_name'], $r['owner_citizen_code'], $r['vehicle_type'], $r['detail_type'], $r['brand'], $r['model'], $r['version_name'], $r['license_plate'], $r['frame_number'], $r['engine_number'], $r['registration_date'], $r['registration_place'], $r['manufacture_year'], $r['color'], $r['usage_status_label'], $r['has_insurance'] ? 'Có' : 'Không', $r['insurance_expiry_date'], $r['has_inspection'] ? 'Có' : 'Không', $r['inspection_expiry_date'], $r['note']], $rows), $filters);
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['v.status <> "DELETED"', 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $keyword = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $parts = [];
            foreach (['v.vehicle_code'=>'q_vehicle','h.household_code'=>'q_code','h.head_citizen_name'=>'q_head','h.address'=>'q_address','v.owner_name'=>'q_owner','oc.full_name'=>'q_owner_citizen','oc.citizen_code'=>'q_owner_code','v.vehicle_type'=>'q_type','v.detail_type'=>'q_detail','v.brand'=>'q_brand','v.model'=>'q_model','v.version_name'=>'q_version','v.license_plate'=>'q_plate','v.frame_number'=>'q_frame','v.engine_number'=>'q_engine','v.registration_place'=>'q_reg_place','v.color'=>'q_color'] as $column => $param) {
                $parts[] = "LOWER($column) LIKE :$param";
                $params[$param] = $keyword;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        foreach (['vehicle_type' => 'v.vehicle_type', 'detail_type' => 'v.detail_type', 'usage_status' => 'v.usage_status'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? $filters[lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))))] ?? ''));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        $householdId = (int) ($filters['household_id'] ?? $filters['householdId'] ?? 0);
        if ($householdId > 0) { $where[] = 'v.household_id = :household_id'; $params['household_id'] = $householdId; }
        $ownerCitizenId = (int) ($filters['owner_citizen_id'] ?? $filters['ownerCitizenId'] ?? 0);
        if ($ownerCitizenId > 0) { $where[] = 'v.owner_citizen_id = :owner_citizen_id'; $params['owner_citizen_id'] = $ownerCitizenId; }
        $owner = trim((string) ($filters['owner_name'] ?? $filters['ownerName'] ?? ''));
        if ($owner !== '') { $where[] = 'LOWER(v.owner_name) LIKE :owner_name'; $params['owner_name'] = '%' . mb_strtolower($owner, 'UTF-8') . '%'; }
        $area = trim((string) ($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') { $where[] = 'h.area_code = :area_code'; $params['area_code'] = $area; }
        if ((string) ($filters['missing_plate'] ?? '') === '1') $where[] = '(v.license_plate IS NULL OR v.license_plate = "")';
        if ((string) ($filters['expired_insurance'] ?? '') === '1') $where[] = '(v.has_insurance=1 AND v.insurance_expiry_date IS NOT NULL AND v.insurance_expiry_date < CURDATE())';
        if ((string) ($filters['expired_inspection'] ?? '') === '1') $where[] = '(v.has_inspection=1 AND v.inspection_expiry_date IS NOT NULL AND v.inspection_expiry_date < CURDATE())';
        $from = trim((string) ($filters['date_from'] ?? $filters['dateFrom'] ?? ''));
        if ($from !== '') { $where[] = 'DATE(COALESCE(v.updated_at, v.created_at)) >= :date_from'; $params['date_from'] = $from; }
        $to = trim((string) ($filters['date_to'] ?? $filters['dateTo'] ?? ''));
        if ($to !== '') { $where[] = 'DATE(COALESCE(v.updated_at, v.created_at)) <= :date_to'; $params['date_to'] = $to; }
        $sortMap = ['vehicle_code'=>'v.vehicle_code','household_code'=>'h.household_code','owner_name'=>'v.owner_name','vehicle_type'=>'v.vehicle_type','detail_type'=>'v.detail_type','brand'=>'v.brand','license_plate'=>'v.license_plate','usage_status'=>'v.usage_status','insurance_expiry_date'=>'v.insurance_expiry_date','inspection_expiry_date'=>'v.inspection_expiry_date','updated_at'=>'COALESCE(v.updated_at,v.created_at)'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = $this->listOrder($filters, $sortMap, 'household_code', 'ASC', ['h.household_code ASC', 'v.vehicle_code ASC', 'v.id ASC']);
        return $result;
    }

    private function params(array $data, int $userId, ?int $id): array
    {
        $householdId = (int) ($data['household_id'] ?? $data['householdId'] ?? 0);
        if ($householdId <= 0) throw new \RuntimeException('Hộ gia đình là bắt buộc');
        if (!$this->fetchOne('SELECT id FROM households WHERE id=:id AND status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', ['id' => $householdId])) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $ownerCitizenId = (int) ($data['owner_citizen_id'] ?? $data['ownerCitizenId'] ?? 0);
        if ($ownerCitizenId > 0 && !$this->fetchOne('SELECT id FROM citizens WHERE id=:id AND household_id=:household_id AND status NOT IN ("DELETED","INACTIVE")', ['id' => $ownerCitizenId, 'household_id' => $householdId])) throw new \RuntimeException('Chủ sở hữu không thuộc hộ đã chọn');
        $type = trim((string) ($data['vehicle_type'] ?? $data['vehicleType'] ?? ''));
        if ($type === '') throw new \RuntimeException('Loại phương tiện là bắt buộc');
        $usage = strtoupper(trim((string) ($data['usage_status'] ?? $data['usageStatus'] ?? 'USING')));
        if (!isset(self::USAGE_LABELS[$usage])) $usage = 'USING';
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::STATUS_LABELS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        $year = trim((string) ($data['manufacture_year'] ?? $data['manufactureYear'] ?? ''));
        return [
            'household_id' => $householdId,
            'owner_citizen_id' => $ownerCitizenId > 0 ? $ownerCitizenId : null,
            'owner_name' => trim((string) ($data['owner_name'] ?? $data['ownerName'] ?? '')) ?: null,
            'vehicle_type' => $type,
            'detail_type' => trim((string) ($data['detail_type'] ?? $data['detailType'] ?? '')) ?: null,
            'brand' => trim((string) ($data['brand'] ?? '')) ?: null,
            'model' => trim((string) ($data['model'] ?? '')) ?: null,
            'version_name' => trim((string) ($data['version_name'] ?? $data['versionName'] ?? '')) ?: null,
            'license_plate' => strtoupper(trim((string) ($data['license_plate'] ?? $data['licensePlate'] ?? ''))) ?: null,
            'frame_number' => trim((string) ($data['frame_number'] ?? $data['frameNumber'] ?? '')) ?: null,
            'engine_number' => trim((string) ($data['engine_number'] ?? $data['engineNumber'] ?? '')) ?: null,
            'registration_date' => trim((string) ($data['registration_date'] ?? $data['registrationDate'] ?? '')) ?: null,
            'registration_place' => trim((string) ($data['registration_place'] ?? $data['registrationPlace'] ?? '')) ?: null,
            'manufacture_year' => $year === '' ? null : max(1900, min((int) date('Y') + 1, (int) $year)),
            'color' => trim((string) ($data['color'] ?? '')) ?: null,
            'usage_status' => $usage,
            'has_insurance' => !empty($data['has_insurance'] ?? $data['hasInsurance'] ?? 0) && (string) ($data['has_insurance'] ?? $data['hasInsurance'] ?? '') !== '0' ? 1 : 0,
            'insurance_expiry_date' => trim((string) ($data['insurance_expiry_date'] ?? $data['insuranceExpiryDate'] ?? '')) ?: null,
            'has_inspection' => !empty($data['has_inspection'] ?? $data['hasInspection'] ?? 0) && (string) ($data['has_inspection'] ?? $data['hasInspection'] ?? '') !== '0' ? 1 : 0,
            'inspection_expiry_date' => trim((string) ($data['inspection_expiry_date'] ?? $data['inspectionExpiryDate'] ?? '')) ?: null,
            'vehicle_photo_path' => trim((string) ($data['vehicle_photo_path'] ?? $data['vehiclePhotoPath'] ?? '')) ?: null,
            'plate_photo_path' => trim((string) ($data['plate_photo_path'] ?? $data['platePhotoPath'] ?? '')) ?: null,
            'registration_photo_path' => trim((string) ($data['registration_photo_path'] ?? $data['registrationPhotoPath'] ?? '')) ?: null,
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
            'vehicle_code' => (string) ($row['vehicle_code'] ?? ''),
            'household_id' => (int) $row['household_id'],
            'household_code' => (string) ($row['household_code'] ?? ''),
            'head_citizen_name' => (string) ($row['head_citizen_name'] ?? ''),
            'owner_citizen_id' => isset($row['owner_citizen_id']) ? (int) $row['owner_citizen_id'] : null,
            'owner_citizen_code' => (string) ($row['owner_citizen_code'] ?? ''),
            'owner_citizen_name' => (string) ($row['owner_citizen_name'] ?? ''),
            'owner_name' => (string) ($row['owner_name'] ?? $row['owner_citizen_name'] ?? ''),
            'vehicle_type' => (string) ($row['vehicle_type'] ?? ''),
            'detail_type' => (string) ($row['detail_type'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'model' => (string) ($row['model'] ?? ''),
            'version_name' => (string) ($row['version_name'] ?? ''),
            'license_plate' => (string) ($row['license_plate'] ?? ''),
            'frame_number' => (string) ($row['frame_number'] ?? ''),
            'engine_number' => (string) ($row['engine_number'] ?? ''),
            'registration_date' => $row['registration_date'] ?? null,
            'registration_place' => (string) ($row['registration_place'] ?? ''),
            'manufacture_year' => $row['manufacture_year'] !== null ? (int) $row['manufacture_year'] : null,
            'color' => (string) ($row['color'] ?? ''),
            'usage_status' => $usage,
            'usage_status_label' => self::USAGE_LABELS[$usage] ?? $usage,
            'has_insurance' => (int) ($row['has_insurance'] ?? 0) === 1,
            'insurance_expiry_date' => $row['insurance_expiry_date'] ?? null,
            'has_inspection' => (int) ($row['has_inspection'] ?? 0) === 1,
            'inspection_expiry_date' => $row['inspection_expiry_date'] ?? null,
            'vehicle_photo_path' => (string) ($row['vehicle_photo_path'] ?? ''),
            'plate_photo_path' => (string) ($row['plate_photo_path'] ?? ''),
            'registration_photo_path' => (string) ($row['registration_photo_path'] ?? ''),
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

    private function nextCode(): string
    {
        $row = $this->fetchOne('SELECT MAX(id) + 1 AS next_id FROM vehicles') ?: [];
        return 'PT-' . str_pad((string) max(1, (int) ($row['next_id'] ?? 1)), 6, '0', STR_PAD_LEFT);
    }

    private function ensureColumns(): void
    {
        $columns = [
            'vehicle_code' => 'VARCHAR(40) NULL',
            'owner_citizen_id' => 'BIGINT UNSIGNED NULL',
            'detail_type' => 'VARCHAR(120) NULL',
            'version_name' => 'VARCHAR(120) NULL',
            'registration_date' => 'DATE NULL',
            'registration_place' => 'VARCHAR(180) NULL',
            'has_insurance' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'insurance_expiry_date' => 'DATE NULL',
            'has_inspection' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'inspection_expiry_date' => 'DATE NULL',
            'vehicle_photo_path' => 'VARCHAR(255) NULL',
            'plate_photo_path' => 'VARCHAR(255) NULL',
            'registration_photo_path' => 'VARCHAR(255) NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('vehicles', $column)) $this->execute("ALTER TABLE vehicles ADD COLUMN $column $definition");
        }
        $this->execute("UPDATE vehicles SET usage_status='DAMAGED' WHERE usage_status='REPAIRING'");
        $this->execute("ALTER TABLE vehicles MODIFY usage_status ENUM('USING','INACTIVE','SOLD','LIQUIDATED','DAMAGED','LOST') NOT NULL DEFAULT 'USING'");
        $this->execute("UPDATE vehicles SET vehicle_code=CONCAT('PT-', LPAD(id, 6, '0')) WHERE vehicle_code IS NULL OR vehicle_code=''");
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')];
    }
}
