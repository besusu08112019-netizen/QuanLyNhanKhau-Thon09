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

    private const CATALOGS = [
        'economic_type' => ['Sản xuất nông nghiệp','Trồng trọt','Chăn nuôi','Nuôi trồng thủy sản','Lâm nghiệp','Tiểu thủ công nghiệp','Thương mại','Dịch vụ','Xây dựng','Vận tải','Chế biến thực phẩm','Kinh doanh online','Khác'],
        'main_product' => ['Lúa','Rau','Hoa','Cây ăn quả','Gia súc','Gia cầm','Thủy sản','Gỗ','May mặc','Tạp hóa','Nhà hàng','Dịch vụ','Khác'],
        'business_scale' => ['Hộ cá thể','Tổ hợp tác','Hợp tác xã','Doanh nghiệp tư nhân','Công ty TNHH','Công ty cổ phần','Khác'],
        'image_category' => ['Mặt tiền','Khu sản xuất','Nhà xưởng','Máy móc','Kho hàng','Biển hiệu','Khác'],
        'document_category' => ['Giấy phép kinh doanh','Mã số thuế','Giấy chứng nhận ATTP','Chứng nhận OCOP','VietGAP','GlobalGAP','Hồ sơ khác'],
        'ocop_star' => ['3 sao','4 sao','5 sao'],
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
        $columns = [
            'economic_type' => 'VARCHAR(120) NULL AFTER business_type',
            'main_products' => 'TEXT NULL AFTER economic_type',
            'business_scale' => 'VARCHAR(120) NULL AFTER main_products',
            'is_ocop' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER business_scale',
            'ocop_product' => 'VARCHAR(255) NULL AFTER is_ocop',
            'ocop_star' => 'TINYINT UNSIGNED NULL AFTER ocop_product',
            'food_safety_certified' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER ocop_star',
            'food_safety_certificate_no' => 'VARCHAR(120) NULL AFTER food_safety_certified',
            'food_safety_expired_date' => 'DATE NULL AFTER food_safety_certificate_no',
            'social_insurance' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER food_safety_expired_date',
            'insured_workers' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER social_insurance',
        ];
        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('household_business', $column)) {
                $this->execute("ALTER TABLE household_business ADD COLUMN $column $definition");
            }
        }
        $this->execute('CREATE TABLE IF NOT EXISTS household_business_catalogs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, catalog_type VARCHAR(50) NOT NULL, value VARCHAR(150) NOT NULL, label VARCHAR(150) NOT NULL, sort_order INT UNSIGNED NOT NULL DEFAULT 0, status ENUM("ACTIVE","INACTIVE") NOT NULL DEFAULT "ACTIVE", created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_hb_catalog (catalog_type, value), KEY idx_hb_catalog_type (catalog_type, status, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->execute('CREATE TABLE IF NOT EXISTS household_business_files (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, household_business_id BIGINT UNSIGNED NOT NULL, file_kind ENUM("IMAGE","DOCUMENT") NOT NULL, category VARCHAR(120) NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, mime_type VARCHAR(120) NOT NULL, file_size BIGINT UNSIGNED NOT NULL DEFAULT 0, status ENUM("ACTIVE","DELETED") NOT NULL DEFAULT "ACTIVE", created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_by BIGINT UNSIGNED NULL, deleted_at DATETIME NULL, deleted_by BIGINT UNSIGNED NULL, KEY idx_hb_files_business (household_business_id, status, file_kind), KEY idx_hb_files_category (category), CONSTRAINT fk_hb_files_business FOREIGN KEY (household_business_id) REFERENCES household_business(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->seedCatalogs();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll('SELECT hbc.catalog_type, hbc.value, hbc.label FROM household_business_catalogs hbc WHERE hbc.status="ACTIVE" ORDER BY hbc.catalog_type, hbc.sort_order, hbc.label');
        $out = [];
        foreach (array_keys(self::CATALOGS) as $type) $out[$type] = [];
        foreach ($rows as $row) $out[$row['catalog_type']][] = ['value' => $row['value'], 'label' => $row['label']];
        $out['business_type'] = array_map(fn($code, $label) => ['value' => $code, 'label' => $label], array_keys(self::TYPE_LABELS), array_values(self::TYPE_LABELS));
        return $out;
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM household_business hb INNER JOIN households h ON h.id = hb.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT hb.*, h.id AS household_id_real, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude AS household_latitude, h.longitude AS household_longitude, COALESCE(v.total_members,0) AS member_count
             FROM household_business hb
             INNER JOIN households h ON h.id = hb.household_id
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
            'SELECT hb.*, h.id AS household_id_real, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude AS household_latitude, h.longitude AS household_longitude,
                    COALESCE(v.total_members,0) AS member_count
             FROM household_business hb
             INNER JOIN households h ON h.id = hb.household_id
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             WHERE hb.id = :id AND hb.status <> "DELETED" AND h.status <> "DELETED"',
            ['id' => $id]
        );
        return $row ? $this->normalize($row) : null;
    }

    public function findByHousehold(int $householdId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT hb.*, h.id AS household_id_real, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude AS household_latitude, h.longitude AS household_longitude, COALESCE(v.total_members,0) AS member_count
             FROM household_business hb
             INNER JOIN households h ON h.id = hb.household_id
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             WHERE hb.household_id = :id AND hb.status <> "DELETED" AND h.status <> "DELETED"',
            ['id' => $householdId]
        );
        return $row ? $this->normalize($row) : null;
    }

    public function searchHouseholds(string $query, int $limit = 10): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.latitude, h.longitude, hb.id AS business_id
             FROM households h
             LEFT JOIN household_business hb ON hb.household_id = h.id AND hb.status <> "DELETED"
             WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND (
                    LOWER(h.household_code) LIKE :household_code
                 OR LOWER(h.head_citizen_name) LIKE :head_name
                 OR LOWER(h.address) LIKE :address
               )
             ORDER BY h.household_code ASC
             LIMIT ' . max(1, min(20, $limit)),
            ['household_code' => $keyword, 'head_name' => $keyword, 'address' => $keyword]
        );
        return array_map(fn($row) => [
            'id' => (int) $row['id'],
            'household_code' => (string) $row['household_code'],
            'head_citizen_name' => (string) $row['head_citizen_name'],
            'householder_name' => (string) $row['head_citizen_name'],
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float) $row['longitude'] : null,
            'has_business' => !empty($row['business_id']),
        ], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->params($data, $userId);
        $before = $id ? $this->find($id) : null;
        if ($id && !$before) throw new \RuntimeException('Không tìm thấy thông tin hộ sản xuất/kinh doanh');
        if ($id) $params['id'] = $id;

        $existing = $this->fetchOne('SELECT hb.id FROM household_business hb WHERE hb.household_id = :household_id AND hb.status <> "DELETED"' . ($id ? ' AND hb.id <> :id' : ''), $id ? ['household_id' => $params['household_id'], 'id' => $id] : ['household_id' => $params['household_id']]);
        if ($existing) throw new \RuntimeException('Hộ này đã có hồ sơ sản xuất & kinh doanh.');

        if ($id) {
            $this->execute(
                'UPDATE household_business SET household_id=:household_id,business_type=:business_type,economic_type=:economic_type,main_products=:main_products,business_scale=:business_scale,is_ocop=:is_ocop,ocop_product=:ocop_product,ocop_star=:ocop_star,food_safety_certified=:food_safety_certified,food_safety_certificate_no=:food_safety_certificate_no,food_safety_expired_date=:food_safety_expired_date,social_insurance=:social_insurance,insured_workers=:insured_workers,business_name=:business_name,owner_name=:owner_name,production_sector=:production_sector,business_sector=:business_sector,business_license=:business_license,license_date=:license_date,license_place=:license_place,tax_code=:tax_code,start_date=:start_date,worker_count=:worker_count,annual_revenue=:annual_revenue,phone=:phone,email=:email,address=:address,latitude=:latitude,longitude=:longitude,status=:status,note=:note,updated_by=:user WHERE id=:id',
                $params
            );
            return $this->find($id);
        }

        $insertParams = $params;
        $insertParams['created_by'] = $userId;
        $insertParams['updated_by'] = $userId;
        unset($insertParams['user']);
        $insertSql = 'INSERT INTO household_business (household_id,business_type,economic_type,main_products,business_scale,is_ocop,ocop_product,ocop_star,food_safety_certified,food_safety_certificate_no,food_safety_expired_date,social_insurance,insured_workers,business_name,owner_name,production_sector,business_sector,business_license,license_date,license_place,tax_code,start_date,worker_count,annual_revenue,phone,email,address,latitude,longitude,status,note,created_by,updated_by) VALUES (:household_id,:business_type,:economic_type,:main_products,:business_scale,:is_ocop,:ocop_product,:ocop_star,:food_safety_certified,:food_safety_certificate_no,:food_safety_expired_date,:social_insurance,:insured_workers,:business_name,:owner_name,:production_sector,:business_sector,:business_license,:license_date,:license_place,:tax_code,:start_date,:worker_count,:annual_revenue,:phone,:email,:address,:latitude,:longitude,:status,:note,:created_by,:updated_by)';
        $this->debugSql('household_business.insert', $insertSql, $insertParams);
        $newId = $this->insert($insertSql, $insertParams);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy thông tin hộ sản xuất/kinh doanh');
        $deleteSql = 'UPDATE household_business SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id';
        $deleteParams = ['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId];
        $this->debugSql('household_business.delete', $deleteSql, $deleteParams);
        $this->execute($deleteSql, $deleteParams);
    }

    public function members(int $householdId): array
    {
        return $this->fetchAll('SELECT p.id, p.citizen_code, p.full_name, p.relationship, p.gender, p.date_of_birth, p.identity_number, p.phone, p.residency_status, p.presence_status FROM citizens p WHERE p.household_id = :id AND p.status <> "DELETED" ORDER BY CASE WHEN p.relationship = "Chủ hộ" THEN 0 ELSE 1 END, p.full_name', ['id' => $householdId]);
    }

    public function dashboard(): array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT
                COALESCE(SUM(CASE WHEN hb.business_type = "PRODUCTION" THEN 1 ELSE 0 END),0) AS production,
                COALESCE(SUM(CASE WHEN hb.business_type = "BUSINESS" THEN 1 ELSE 0 END),0) AS business,
                COALESCE(SUM(CASE WHEN hb.business_type = "BOTH" THEN 1 ELSE 0 END),0) AS both,
                COALESCE(SUM(hb.worker_count),0) AS workers,
                COALESCE(SUM(hb.is_ocop=1),0) AS ocop,
                COALESCE(SUM(hb.food_safety_certified=1),0) AS food_safety,
                COALESCE(SUM(hb.social_insurance=1),0) AS social_insurance,
                COALESCE(SUM(hb.insured_workers),0) AS insured_workers
             FROM household_business hb WHERE hb.status <> "DELETED"'
        ) ?: [];
        return [
            'production_households' => (int) ($row['production'] ?? 0),
            'business_households' => (int) ($row['business'] ?? 0),
            'production_business_households' => (int) ($row['both'] ?? 0),
            'business_worker_total' => (int) ($row['workers'] ?? 0),
            'ocop_households' => (int) ($row['ocop'] ?? 0),
            'food_safety_households' => (int) ($row['food_safety'] ?? 0),
            'social_insurance_households' => (int) ($row['social_insurance'] ?? 0),
            'insured_worker_total' => (int) ($row['insured_workers'] ?? 0),
        ];
    }

    public function charts(): array
    {
        $this->ensureSchema();
        return [
            'types' => $this->fetchAll('SELECT hb.business_type AS code, hb.business_type AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY hb.business_type ORDER BY hb.business_type'),
            'economicTypes' => $this->fetchAll('SELECT COALESCE(NULLIF(hb.economic_type,""),"Chưa cập nhật") AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY label ORDER BY value DESC, label'),
            'sectors' => $this->fetchAll('SELECT COALESCE(NULLIF(hb.production_sector,""), NULLIF(hb.business_sector,""), "Chưa cập nhật") AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY label ORDER BY value DESC, label LIMIT 10'),
            'scales' => $this->fetchAll('SELECT COALESCE(NULLIF(hb.business_scale,""),"Chưa cập nhật") AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY label ORDER BY value DESC, label'),
            'statuses' => $this->fetchAll('SELECT hb.status AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY hb.status ORDER BY hb.status'),
            'ocop' => $this->fetchAll('SELECT CASE WHEN hb.is_ocop=1 THEN "Tham gia OCOP" ELSE "Không OCOP" END AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY hb.is_ocop ORDER BY hb.is_ocop DESC'),
            'ocopStars' => $this->fetchAll('SELECT CONCAT(hb.ocop_star," sao") AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" AND hb.is_ocop=1 AND hb.ocop_star IS NOT NULL GROUP BY hb.ocop_star ORDER BY hb.ocop_star'),
            'foodSafety' => $this->fetchAll('SELECT CASE WHEN hb.food_safety_certified=1 THEN "Có ATTP" ELSE "Chưa có ATTP" END AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY hb.food_safety_certified ORDER BY hb.food_safety_certified DESC'),
            'socialInsurance' => $this->fetchAll('SELECT CASE WHEN hb.social_insurance=1 THEN "Có BHXH" ELSE "Chưa có BHXH" END AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY hb.social_insurance ORDER BY hb.social_insurance DESC'),
            'workers' => $this->fetchAll('SELECT CASE WHEN hb.worker_count=0 THEN "0" WHEN hb.worker_count<=2 THEN "1-2" WHEN hb.worker_count<=5 THEN "3-5" WHEN hb.worker_count<=10 THEN "6-10" ELSE "Trên 10" END AS label, COUNT(*) AS value FROM household_business hb WHERE hb.status <> "DELETED" GROUP BY label ORDER BY MIN(hb.worker_count)'),
        ];
    }

    public function report(string $mode, array $filters = []): array
    {
        $filters['pageSize'] = 100;
        $filters['page'] = 1;
        if ($mode === 'production') $filters['business_type'] = 'PRODUCTION';
        if ($mode === 'business') $filters['business_type'] = 'BUSINESS';
        if ($mode === 'gis') $filters['located'] = '1';
        if ($mode === 'ocop') $filters['ocop'] = '1';
        if ($mode === 'food_safety') $filters['food_safety'] = '1';
        if ($mode === 'social_insurance') $filters['social_insurance'] = '1';
        $rows = $this->paginate($filters)['items'];
        $title = match ($mode) {
            'production' => 'Danh sách hộ sản xuất',
            'business' => 'Danh sách hộ kinh doanh',
            'sector' => 'Báo cáo hộ sản xuất/kinh doanh theo ngành nghề',
            'status' => 'Báo cáo hộ sản xuất/kinh doanh theo trạng thái',
            'gis' => 'Báo cáo hộ sản xuất/kinh doanh theo khu vực GIS',
            'ocop' => 'Báo cáo hộ OCOP',
            'food_safety' => 'Báo cáo hộ có chứng nhận ATTP',
            'social_insurance' => 'Báo cáo hộ tham gia BHXH',
            'economic_type' => 'Báo cáo theo loại hình kinh tế',
            'scale' => 'Báo cáo theo quy mô hoạt động',
            'product' => 'Báo cáo theo sản phẩm chính',
            default => 'Danh sách hộ sản xuất và kinh doanh',
        };
        $body = array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['business_name'], $r['business_type_label'], $r['economic_type'], $r['business_scale'], $r['sector_label'], implode(', ', $r['main_products']), $r['worker_count'], $r['is_ocop'] ? trim(($r['ocop_product'] ?: 'OCOP') . ' ' . ($r['ocop_star'] ? $r['ocop_star'] . ' sao' : '')) : 'Không', $r['food_safety_certified'] ? ($r['food_safety_certificate_no'] ?: 'Có') : 'Không', $r['social_insurance'] ? ((int) $r['insured_workers'] . ' lao động') : 'Không', $r['status_label']], $rows);
        return $this->table($title, ['Mã hộ','Chủ hộ','Tên cơ sở','Loại hình','Loại hình kinh tế','Quy mô','Ngành nghề','Sản phẩm chính','Lao động','OCOP','ATTP','BHXH','Trạng thái'], $body, $filters);
    }

    private function where(array $filters): array
    {
        $where = ['hb.status <> "DELETED"', 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $searchKeyword = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $searchColumns = [
                'h.household_code' => 'q_household_code',
                'h.head_citizen_name' => 'q_head_name',
                'h.address' => 'q_address',
                'hb.business_name' => 'q_business_name',
                'hb.owner_name' => 'q_owner_name',
                'hb.production_sector' => 'q_production_sector',
                'hb.business_sector' => 'q_business_sector',
                'hb.phone' => 'q_phone',
                'hb.tax_code' => 'q_tax_code',
                'hb.economic_type' => 'q_economic_type',
                'hb.business_scale' => 'q_business_scale',
                'hb.main_products' => 'q_main_products',
            ];
            $parts = [];
            foreach ($searchColumns as $column => $param) {
                $parts[] = "LOWER($column) LIKE :$param";
                $params[$param] = $searchKeyword;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        $type = $this->businessType($filters['business_type'] ?? $filters['businessType'] ?? '');
        if ($type !== '') {
            $where[] = 'hb.business_type = :business_type';
            $params['business_type'] = $type;
        }
        foreach (['economic_type' => 'economic_type', 'business_scale' => 'business_scale'] as $input => $column) {
            $value = trim((string) ($filters[$input] ?? ''));
            if ($value !== '') { $where[] = "hb.$column = :$input"; $params[$input] = $value; }
        }
        $product = trim((string) ($filters['product'] ?? ''));
        if ($product !== '') { $where[] = 'hb.main_products LIKE :product'; $params['product'] = '%' . $product . '%'; }
        foreach (['ocop' => 'is_ocop', 'food_safety' => 'food_safety_certified', 'social_insurance' => 'social_insurance'] as $filter => $column) {
            $value = trim((string) ($filters[$filter] ?? ''));
            if ($value === '1' || $value === '0') $where[] = "COALESCE(hb.$column,0) = " . (int) $value;
        }
        $sector = trim((string) ($filters['sector'] ?? ''));
        if ($sector !== '') {
            $sectorKeyword = '%' . mb_strtolower($sector, 'UTF-8') . '%';
            $where[] = '(LOWER(hb.production_sector) LIKE :sector_production OR LOWER(hb.business_sector) LIKE :sector_business)';
            $params['sector_production'] = $sectorKeyword;
            $params['sector_business'] = $sectorKeyword;
        }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'hb.status = :status';
            $params['status'] = $status;
        }
        foreach (['license' => 'business_license', 'tax' => 'tax_code'] as $filter => $column) {
            $value = trim((string) ($filters[$filter] ?? ''));
            if ($value === '1') $where[] = "hb.$column IS NOT NULL AND hb.$column <> ''";
            if ($value === '0') $where[] = "(hb.$column IS NULL OR hb.$column = '')";
        }
        $located = trim((string) ($filters['located'] ?? ''));
        if ($located === '1') $where[] = '((hb.latitude IS NOT NULL AND hb.longitude IS NOT NULL) OR (h.latitude IS NOT NULL AND h.longitude IS NOT NULL))';
        if ($located === '0') $where[] = '((hb.latitude IS NULL OR hb.longitude IS NULL) AND (h.latitude IS NULL OR h.longitude IS NULL))';
        $sort = preg_replace('/[^a-z_]/', '', (string) ($filters['sort'] ?? 'household_code'));
        $direction = strtoupper((string) ($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sortMap = [
            'household_code' => 'h.household_code',
            'head_citizen_name' => 'h.head_citizen_name',
            'business_name' => 'hb.business_name',
            'business_type' => 'hb.business_type',
            'economic_type' => 'hb.economic_type',
            'business_scale' => 'hb.business_scale',
            'sector' => 'sector_label',
            'worker_count' => 'hb.worker_count',
            'status' => 'hb.status',
            'address' => 'COALESCE(NULLIF(hb.address,""), h.address)',
        ];
        return ['WHERE ' . implode(' AND ', $where), $params, 'ORDER BY ' . ($sortMap[$sort] ?? 'h.household_code') . ' ' . $direction . ', h.household_code ASC'];
    }

    private function params(array $data, int $userId): array
    {
        $householdId = (int) ($data['household_id'] ?? $data['householdId'] ?? 0);
        if ($householdId <= 0) throw new \RuntimeException('Hộ gia đình là bắt buộc');
        if (!$this->fetchOne('SELECT h.id FROM households h WHERE h.id = :id AND h.status <> "DELETED"', ['id' => $householdId])) throw new \RuntimeException('Không tìm thấy hộ gia đình liên kết');
        $workerCount = max(0, (int) ($data['worker_count'] ?? $data['workerCount'] ?? 0));
        $isOcop = $this->boolValue($data['is_ocop'] ?? $data['isOcop'] ?? 0);
        $ocopStar = $isOcop ? (int) ($data['ocop_star'] ?? $data['ocopStar'] ?? 0) : null;
        if ($isOcop && !in_array($ocopStar, [3,4,5], true)) throw new \RuntimeException('Số sao OCOP phải là 3, 4 hoặc 5');
        $foodSafety = $this->boolValue($data['food_safety_certified'] ?? $data['foodSafetyCertified'] ?? 0);
        $socialInsurance = $this->boolValue($data['social_insurance'] ?? $data['socialInsurance'] ?? 0);
        $insuredWorkers = max(0, (int) ($data['insured_workers'] ?? $data['insuredWorkers'] ?? 0));
        if (!$socialInsurance) $insuredWorkers = 0;
        if ($workerCount > 0 && $insuredWorkers > $workerCount) throw new \RuntimeException('Số lao động tham gia BHXH không được lớn hơn tổng số lao động');
        return [
            'household_id' => $householdId,
            'business_type' => $this->businessType($data['business_type'] ?? $data['businessType'] ?? 'RESIDENT') ?: 'RESIDENT',
            'economic_type' => $this->catalogValue('economic_type', $data['economic_type'] ?? $data['economicType'] ?? null),
            'main_products' => json_encode($this->products($data['main_products'] ?? $data['mainProducts'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'business_scale' => $this->catalogValue('business_scale', $data['business_scale'] ?? $data['businessScale'] ?? null),
            'is_ocop' => $isOcop ? 1 : 0,
            'ocop_product' => $isOcop ? $this->nullable($data['ocop_product'] ?? $data['ocopProduct'] ?? null) : null,
            'ocop_star' => $isOcop ? $ocopStar : null,
            'food_safety_certified' => $foodSafety ? 1 : 0,
            'food_safety_certificate_no' => $foodSafety ? $this->nullable($data['food_safety_certificate_no'] ?? $data['foodSafetyCertificateNo'] ?? null) : null,
            'food_safety_expired_date' => $foodSafety ? $this->dateOrNull($data['food_safety_expired_date'] ?? $data['foodSafetyExpiredDate'] ?? null) : null,
            'social_insurance' => $socialInsurance ? 1 : 0,
            'insured_workers' => $insuredWorkers,
            'business_name' => $this->nullable($data['business_name'] ?? $data['businessName'] ?? null),
            'owner_name' => $this->nullable($data['owner_name'] ?? $data['ownerName'] ?? null),
            'production_sector' => $this->nullable($data['production_sector'] ?? $data['productionSector'] ?? null),
            'business_sector' => $this->nullable($data['business_sector'] ?? $data['businessSector'] ?? $data['sector'] ?? null),
            'business_license' => $this->nullable($data['business_license'] ?? $data['businessLicense'] ?? null),
            'license_date' => $this->dateOrNull($data['license_date'] ?? $data['licenseDate'] ?? null),
            'license_place' => $this->nullable($data['license_place'] ?? $data['licensePlace'] ?? null),
            'tax_code' => $this->nullable($data['tax_code'] ?? $data['taxCode'] ?? null),
            'start_date' => $this->dateOrNull($data['start_date'] ?? $data['startDate'] ?? null),
            'worker_count' => $workerCount,
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

    private function debugSql(string $context, string $sql, array $params): void
    {
        if (!$this->debugEnabled()) return;
        error_log('[HOUSEHOLD_BUSINESS_SQL] ' . json_encode(['context' => $context, 'sql' => $sql, 'params' => $params], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function debugEnabled(): bool
    {
        $value = strtolower((string) (getenv('APP_DEBUG') ?: getenv('THON09_DEBUG') ?: ''));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
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
            'economic_type' => (string) ($row['economic_type'] ?? ''),
            'main_products' => $this->decodeProducts($row['main_products'] ?? ''),
            'business_scale' => (string) ($row['business_scale'] ?? ''),
            'is_ocop' => (int) ($row['is_ocop'] ?? 0) === 1,
            'ocop_product' => (string) ($row['ocop_product'] ?? ''),
            'ocop_star' => $row['ocop_star'] !== null && $row['ocop_star'] !== '' ? (int) $row['ocop_star'] : null,
            'food_safety_certified' => (int) ($row['food_safety_certified'] ?? 0) === 1,
            'food_safety_certificate_no' => (string) ($row['food_safety_certificate_no'] ?? ''),
            'food_safety_expired_date' => $row['food_safety_expired_date'] ?? null,
            'social_insurance' => (int) ($row['social_insurance'] ?? 0) === 1,
            'insured_workers' => (int) ($row['insured_workers'] ?? 0),
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

    public function files(int $businessId, string $kind = ''): array
    {
        $this->ensureSchema();
        $params = ['id' => $businessId];
        $where = 'hbf.household_business_id=:id AND hbf.status="ACTIVE"';
        if ($kind !== '') { $where .= ' AND hbf.file_kind=:kind'; $params['kind'] = strtoupper($kind); }
        return array_map(fn($r) => $this->normalizeFile($r), $this->fetchAll("SELECT hbf.*, u.display_name AS uploaded_by_name, u.email AS uploaded_by_email FROM household_business_files hbf LEFT JOIN users u ON u.id=hbf.created_by WHERE $where ORDER BY hbf.created_at DESC, hbf.id DESC", $params));
    }

    public function file(int $fileId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT hbf.*, hb.household_id FROM household_business_files hbf INNER JOIN household_business hb ON hb.id=hbf.household_business_id WHERE hbf.id=:id AND hbf.status="ACTIVE" AND hb.status <> "DELETED"', ['id' => $fileId]);
        return $row ? $this->normalizeFile($row) : null;
    }

    public function addFile(int $businessId, string $kind, string $category, array $stored, array $file, string $mime, int $userId): array
    {
        $this->ensureSchema();
        if (!$this->find($businessId)) throw new \RuntimeException('Không tìm thấy hồ sơ sản xuất/kinh doanh');
        $kind = strtoupper($kind) === 'IMAGE' ? 'IMAGE' : 'DOCUMENT';
        $category = $this->catalogValue($kind === 'IMAGE' ? 'image_category' : 'document_category', $category) ?: 'Khác';
        $id = $this->insert('INSERT INTO household_business_files (household_business_id,file_kind,category,original_name,stored_name,file_path,mime_type,file_size,created_by) VALUES (:business,:kind,:category,:original,:stored,:path,:mime,:size,:user)', ['business' => $businessId, 'kind' => $kind, 'category' => $category, 'original' => (string) ($file['name'] ?? ''), 'stored' => $stored['stored_name'], 'path' => $stored['file_path'], 'mime' => $mime, 'size' => (int) ($file['size'] ?? 0), 'user' => $userId]);
        return $this->file($id) ?: ['id' => $id];
    }

    public function deleteFile(int $fileId, int $userId): ?array
    {
        $this->ensureSchema();
        $before = $this->file($fileId);
        if (!$before) return null;
        $this->execute('UPDATE household_business_files SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id', ['id' => $fileId, 'user' => $userId]);
        return $before;
    }

    private function normalizeFile(array $row): array
    {
        return ['id' => (int) $row['id'], 'household_business_id' => (int) $row['household_business_id'], 'file_kind' => (string) $row['file_kind'], 'category' => (string) $row['category'], 'original_name' => (string) $row['original_name'], 'stored_name' => (string) $row['stored_name'], 'file_path' => (string) $row['file_path'], 'mime_type' => (string) $row['mime_type'], 'file_size' => (int) $row['file_size'], 'created_at' => $row['created_at'] ?? null, 'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null, 'uploaded_by' => (string) ($row['uploaded_by_name'] ?? $row['uploaded_by_email'] ?? '')];
    }

    private function seedCatalogs(): void
    {
        foreach (self::CATALOGS as $type => $values) foreach ($values as $index => $label) $this->execute('INSERT IGNORE INTO household_business_catalogs (catalog_type,value,label,sort_order) VALUES (:type,:value,:label,:sort)', ['type' => $type, 'value' => $label, 'label' => $label, 'sort' => $index + 1]);
    }

    private function catalogValue(string $type, mixed $value): ?string
    {
        $text = $this->nullable($value);
        if ($text === null) return null;
        $row = $this->fetchOne('SELECT hbc.value FROM household_business_catalogs hbc WHERE hbc.catalog_type=:type AND hbc.value=:value AND hbc.status="ACTIVE"', ['type' => $type, 'value' => $text]);
        return $row ? (string) $row['value'] : $text;
    }

    private function products(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[,;\n]+/u', $value);
        }
        if (!is_array($value)) $value = [];
        $items = [];
        foreach ($value as $item) { $text = trim((string) $item); if ($text !== '' && !in_array($text, $items, true)) $items[] = mb_substr($text, 0, 120); }
        return array_slice($items, 0, 30);
    }

    private function decodeProducts(mixed $value): array { return $this->products((string) $value); }
    private function boolValue(mixed $value): bool { return in_array(strtolower(trim((string) $value)), ['1','true','yes','on','co','có'], true); }

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
