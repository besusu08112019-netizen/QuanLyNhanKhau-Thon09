<?php

namespace App\Models;

use App\Core\BaseModel;

final class AgriculturalLandZone extends BaseModel
{
    private const ZONES = 'agricultural_land_zones';
    private const SETTINGS = 'agricultural_land_settings';
    private const USAGE_TYPES = 'land_usage_types';
    private const USAGE_AREAS = 'agricultural_land_zone_usage_areas';
    private const AREA_FIELDS = [
        'total_area',
        'long_term_allocated_area',
        'public_utility_area',
        'leased_area',
        'converted_area',
    ];
    private const FIXED_UNIT = 'mau';
    private const DEFAULT_USAGE_TYPES = [
        ['LUA', 'Lúa'],
        ['NGO', 'Ngô'],
        ['LAC', 'Lạc'],
        ['RAU_MAU', 'Rau màu'],
        ['HOA_MAU', 'Hoa màu'],
        ['CAY_AN_QUA', 'Cây ăn quả'],
        ['CAY_LAU_NAM', 'Cây lâu năm'],
        ['THUY_SAN', 'Nuôi trồng thủy sản'],
        ['KHAC', 'Khác'],
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agricultural_land_zones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
  zone_code VARCHAR(40) NOT NULL,
  zone_name VARCHAR(255) NOT NULL,
  input_unit ENUM('mau') NOT NULL DEFAULT 'mau',
  report_year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_area_m2 DECIMAL(16,4) NOT NULL DEFAULT 0,
  long_term_allocated_area_m2 DECIMAL(16,4) NOT NULL DEFAULT 0,
  public_utility_area_m2 DECIMAL(16,4) NOT NULL DEFAULT 0,
  leased_area_m2 DECIMAL(16,4) NOT NULL DEFAULT 0,
  converted_area_m2 DECIMAL(16,4) NOT NULL DEFAULT 0,
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  polygon_json LONGTEXT NULL,
  photo_url VARCHAR(500) NULL,
  irrigation_note TEXT NULL,
  production_group_name VARCHAR(255) NULL,
  main_crop_type VARCHAR(255) NULL,
  annual_note TEXT NULL,
  note TEXT NULL,
  status ENUM('ACTIVE','INACTIVE','CONVERTING','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_agricultural_land_zone_code_year (village_id, zone_code, report_year),
  KEY idx_agricultural_land_zones_village (village_id),
  KEY idx_agricultural_land_zones_name (zone_name),
  KEY idx_agricultural_land_zones_year (report_year),
  KEY idx_agricultural_land_zones_status (status),
  KEY idx_agricultural_land_zones_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureTenantColumn(self::ZONES);
        foreach (self::AREA_FIELDS as $field) {
            $this->ensureColumn(self::ZONES, $field . '_m2', 'DECIMAL(16,4) NOT NULL DEFAULT 0');
        }
        foreach ([
            'input_unit' => "ENUM('mau') NOT NULL DEFAULT 'mau'",
            'report_year' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'latitude' => 'DECIMAL(11,8) NULL',
            'longitude' => 'DECIMAL(11,8) NULL',
            'polygon_json' => 'LONGTEXT NULL',
            'photo_url' => 'VARCHAR(500) NULL',
            'irrigation_note' => 'TEXT NULL',
            'production_group_name' => 'VARCHAR(255) NULL',
            'main_crop_type' => 'VARCHAR(255) NULL',
            'annual_note' => 'TEXT NULL',
        ] as $column => $definition) {
            $this->ensureColumn(self::ZONES, $column, $definition);
        }
        try {
            $this->execute("ALTER TABLE agricultural_land_zones MODIFY status ENUM('ACTIVE','INACTIVE','CONVERTING','DELETED') NOT NULL DEFAULT 'ACTIVE'");
        } catch (\Throwable) {
        }

        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS land_usage_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(180) NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_land_usage_types_code (village_id, code),
  KEY idx_land_usage_types_active (village_id, is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureTenantColumn(self::USAGE_TYPES);

        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agricultural_land_zone_usage_areas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
  zone_id BIGINT UNSIGNED NOT NULL,
  usage_type_id BIGINT UNSIGNED NOT NULL,
  area_m2 DECIMAL(16,4) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_agricultural_land_zone_usage (zone_id, usage_type_id),
  KEY idx_agricultural_land_zone_usage_village (village_id),
  KEY idx_agricultural_land_zone_usage_type (usage_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureTenantColumn(self::USAGE_AREAS);

        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS agricultural_land_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  default_unit ENUM('mau') NOT NULL DEFAULT 'mau',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_agricultural_land_settings_village (village_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->seedUsageTypes();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        $years = $this->fetchAll('SELECT DISTINCT report_year FROM agricultural_land_zones WHERE status <> "DELETED" AND ' . $this->tenantWhere(self::ZONES) . ' ORDER BY report_year DESC', $this->withTenant());
        $yearValues = array_values(array_unique(array_filter(array_map(fn($row) => (int)($row['report_year'] ?? 0), $years))));
        if (!in_array((int)date('Y'), $yearValues, true)) array_unshift($yearValues, (int)date('Y'));
        return [
            'units' => [['value' => 'mau', 'label' => 'mẫu']],
            'statuses' => $this->statuses(),
            'years' => $yearValues,
            'usage_types' => $this->usageTypes(false),
            'default_unit' => 'mau',
            'default_year' => (int)date('Y'),
        ];
    }

    public function settings(): array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT default_unit FROM agricultural_land_settings WHERE ' . $this->tenantWhere(self::SETTINGS), $this->withTenant());
        if (!$row) {
            $this->insert('INSERT INTO agricultural_land_settings (village_id, default_unit) VALUES (:village_id, "mau")', $this->withTenant());
            $row = $this->fetchOne('SELECT default_unit FROM agricultural_land_settings WHERE ' . $this->tenantWhere(self::SETTINGS), $this->withTenant());
        }
        return [
            'default_unit' => 'mau',
        ];
    }

    public function updateSettings(array $data): array
    {
        $this->ensureSchema();
        $this->execute(
            'INSERT INTO agricultural_land_settings (village_id, default_unit) VALUES (:village_id, :default_unit) ON DUPLICATE KEY UPDATE default_unit=VALUES(default_unit), updated_at=NOW()',
            $this->withTenant(['default_unit' => 'mau'])
        );
        return $this->settings();
    }

    public function usageTypes(bool $activeOnly = true): array
    {
        $this->ensureSchema();
        $where = [$this->tenantWhere(self::USAGE_TYPES)];
        if ($activeOnly) $where[] = 'is_active=1';
        $rows = $this->fetchAll('SELECT id, code, name, display_order, is_active FROM land_usage_types WHERE ' . implode(' AND ', $where) . ' ORDER BY display_order ASC, name ASC', $this->withTenant());
        return array_map(fn($row) => [
            'id' => (int)$row['id'],
            'value' => (string)$row['id'],
            'code' => (string)$row['code'],
            'name' => (string)$row['name'],
            'label' => (string)$row['name'],
            'display_order' => (int)$row['display_order'],
            'is_active' => (bool)$row['is_active'],
        ], $rows);
    }

    public function upsertUsageType(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $code = strtoupper(preg_replace('/[^A-Z0-9_]/', '_', trim((string)($data['code'] ?? ''))));
        $name = trim((string)($data['name'] ?? ''));
        if ($code === '' || $name === '') throw new \RuntimeException('Vui lòng nhập mã và tên loại sử dụng đất');
        if (mb_strlen($code, 'UTF-8') > 60) throw new \RuntimeException('Mã loại sử dụng đất không được vượt quá 60 ký tự');
        if (mb_strlen($name, 'UTF-8') > 180) throw new \RuntimeException('Tên loại sử dụng đất không được vượt quá 180 ký tự');
        $params = [
            'code' => $code,
            'name' => $name,
            'display_order' => (int)($data['display_order'] ?? $data['displayOrder'] ?? 0),
            'is_active' => !empty($data['is_active']) || !empty($data['isActive']) ? 1 : 0,
            'updated_by' => $userId,
        ];
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE land_usage_types SET code=:code, name=:name, display_order=:display_order, is_active=:is_active, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere(self::USAGE_TYPES), $this->withTenant($params));
            return $this->usageType($id);
        }
        $params['created_by'] = $userId;
        $columns = ['code', 'name', 'display_order', 'is_active', 'created_by', 'updated_by'];
        $this->addTenantInsert(self::USAGE_TYPES, $columns, $params);
        $newId = $this->insert('INSERT INTO land_usage_types (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->usageType($newId);
    }

    public function deleteUsageType(int $id, int $userId): void
    {
        $this->ensureSchema();
        $this->execute('UPDATE land_usage_types SET is_active=0, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere(self::USAGE_TYPES), $this->withTenant(['id' => $id, 'updated_by' => $userId]));
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters, false);
        $order = $this->listOrder($filters, [
            'zone_code' => 'zone_code',
            'zone_name' => 'zone_name',
            'total_area' => 'total_area_m2',
            'report_year' => 'report_year',
            'status' => 'status',
            'updated_at' => 'updated_at',
        ], 'zone_code', 'ASC', ['id ASC']);
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM agricultural_land_zones WHERE $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll("SELECT * FROM agricultural_land_zones WHERE $where $order LIMIT $pageSize OFFSET $offset", $params);
        $usageAreas = $this->usageAreasForZones(array_map(fn($row) => (int)$row['id'], $rows));
        return $this->paginated(array_map(fn($row) => $this->normalize($row, $usageAreas[(int)$row['id']] ?? []), $rows), $page, $pageSize, $total, ['unit' => 'mau']);
    }

    public function find(int $id, ?string $unit = null): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM agricultural_land_zones WHERE id=:id AND status <> "DELETED" AND ' . $this->tenantWhere(self::ZONES), $this->withTenant(['id' => $id]));
        return $row ? $this->normalize($row) : null;
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $existing = $id ? $this->find($id) : null;
        if ($id && !$existing) throw new \RuntimeException('Không tìm thấy khu đất');
        $params = $this->params($data, $userId);
        $this->assertUniqueZoneCodeYear($params['zone_code'], (int)$params['report_year'], $id);
        $this->assertUsageAreasWithinTotal((array)($data['usage_areas'] ?? $data['usageAreas'] ?? []), $params['total_area_m2']);
        if ($id) {
            if ($existing && $params['zone_code'] !== $existing['zone_code']) {
                throw new \RuntimeException('Mã khu không được thay đổi sau khi tạo');
            }
            $params['id'] = $id;
            $assignments = ['zone_name=:zone_name', 'input_unit=:input_unit', 'report_year=:report_year'];
            foreach (self::AREA_FIELDS as $field) $assignments[] = $field . '_m2=:' . $field . '_m2';
            $assignments = array_merge($assignments, ['latitude=:latitude', 'longitude=:longitude', 'polygon_json=:polygon_json', 'irrigation_note=:irrigation_note', 'production_group_name=:production_group_name', 'main_crop_type=:main_crop_type', 'annual_note=:annual_note', 'note=:note', 'status=:status', 'updated_by=:updated_by']);
            $this->execute('UPDATE agricultural_land_zones SET ' . implode(',', $assignments) . ' WHERE id=:id AND ' . $this->tenantWhere(self::ZONES), $this->withTenant($params));
            $this->replaceUsageAreas($id, (array)($data['usage_areas'] ?? $data['usageAreas'] ?? []), $params['total_area_m2']);
            return $this->find($id);
        }
        $columns = ['zone_code', 'zone_name', 'input_unit', 'report_year'];
        foreach (self::AREA_FIELDS as $field) $columns[] = $field . '_m2';
        $columns = array_merge($columns, ['latitude', 'longitude', 'polygon_json', 'irrigation_note', 'production_group_name', 'main_crop_type', 'annual_note', 'note', 'status', 'created_by', 'updated_by']);
        $this->addTenantInsert(self::ZONES, $columns, $params);
        $newId = $this->insert('INSERT INTO agricultural_land_zones (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        $this->replaceUsageAreas($newId, (array)($data['usage_areas'] ?? $data['usageAreas'] ?? []), $params['total_area_m2']);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy khu đất');
        $this->execute('UPDATE agricultural_land_zones SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere(self::ZONES), $this->withTenant(['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]));
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        $filters['status'] = 'ACTIVE';
        $filters['report_year'] = (int)($filters['report_year'] ?? $filters['reportYear'] ?? 0) ?: (int)date('Y');
        [$where, $params] = $this->where($filters, true);
        $row = $this->fetchOne("SELECT COUNT(*) AS zones_count, " . $this->sumSelect() . " FROM agricultural_land_zones WHERE $where", $params) ?: [];
        $unit = 'mau';
        $metrics = ['zones_count' => (int)($row['zones_count'] ?? 0), 'active_zones' => (int)($row['zones_count'] ?? 0), 'unit' => $unit, 'report_year' => (int)$filters['report_year']];
        foreach (self::AREA_FIELDS as $field) $metrics[$field] = $this->decimalText($row[$field . '_m2'] ?? 0);
        return [
            'metrics' => $metrics,
            'charts' => [
                'land_fund' => $this->landFundChart($row, $unit),
                'usage' => $this->usageChart($filters, $unit),
                'allocation_ratio' => [
                    ['label' => 'Đất giao dài hạn', 'value' => $metrics['long_term_allocated_area']],
                    ['label' => 'Đất công ích', 'value' => $metrics['public_utility_area']],
                ],
                'converted_area' => [
                    ['label' => 'Diện tích đất giao đã chuyển đổi cơ cấu sản xuất', 'value' => $metrics['converted_area']],
                ],
                'by_zone' => array_map(fn($item) => ['label' => $item['zone_name'], 'value' => $this->decimalText($item['total_area_m2'] ?? 0)], $this->fetchAll("SELECT zone_name, total_area_m2 FROM agricultural_land_zones WHERE $where ORDER BY total_area_m2 DESC, zone_name ASC LIMIT 12", $params)),
            ],
        ];
    }

    public function report(string $mode, array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['pageSize'] = 1000;
        if ($mode === 'year_compare' || $mode === 'year-compare') return $this->yearCompareReport($filters);
        if ($mode === 'village') $filters['status'] = 'ACTIVE';
        $items = $this->paginate($filters)['items'];
        $usageTypes = $this->usageTypes(false);
        $usageColumns = array_map(fn($type) => $type['name'], $usageTypes);
        $title = match ($mode) {
            'village' => 'Báo cáo quỹ đất nông nghiệp toàn thôn',
            'zone' => 'Báo cáo quỹ đất nông nghiệp theo khu',
            'year' => 'Báo cáo quỹ đất nông nghiệp theo năm',
            default => 'Danh sách khu đất nông nghiệp',
        };
        return [
            'title' => $title,
            'columns' => array_merge(['Mã khu', 'Tên khu', 'Năm', 'Tổng diện tích', 'Đất giao dài hạn', 'Đất công ích', 'Đất thuê', 'Đất giao đã chuyển đổi', 'Trạng thái'], $usageColumns),
            'rows' => array_map(function ($row) use ($usageTypes) {
                $base = [
                    $row['zone_code'], $row['zone_name'], $row['report_year'] ?: '',
                    $this->areaText($row['total_area'], $row['unit']),
                    $this->areaText($row['long_term_allocated_area'], $row['unit']),
                    $this->areaText($row['public_utility_area'], $row['unit']),
                    $this->areaText($row['leased_area'], $row['unit']),
                    $this->areaText($row['converted_area'], $row['unit']),
                    $row['status_label'],
                ];
                foreach ($usageTypes as $type) {
                    $match = $row['usage_areas'][$type['id']] ?? null;
                    $base[] = $this->areaText((float)($match['area'] ?? 0), $row['unit']);
                }
                return $base;
            }, $items),
            'totalRows' => count($items),
            'filters' => $filters,
            'meta' => ['generated_at' => date('c'), 'unit' => $this->displayUnit($filters), 'source' => 'agricultural_land_zones', 'business_note' => 'Đất chuyển đổi là diện tích nằm trong đất giao dài hạn, không phải quỹ đất phát sinh thêm.'],
        ];
    }

    private function yearCompareReport(array $filters): array
    {
        $unit = 'mau';
        [$where, $params] = $this->where(['status' => 'ACTIVE'] + $filters, false);
        $rows = $this->fetchAll("SELECT report_year, " . $this->sumSelect() . " FROM agricultural_land_zones WHERE $where GROUP BY report_year ORDER BY report_year ASC", $params);
        return [
            'title' => 'So sánh quỹ đất nông nghiệp giữa các năm',
            'columns' => ['Năm', 'Tổng diện tích', 'Đất giao dài hạn', 'Đất công ích', 'Đất thuê', 'Đất giao đã chuyển đổi'],
            'rows' => array_map(fn($row) => [
                (string)$row['report_year'],
                $this->areaText($row['total_area_m2'] ?? 0, $unit),
                $this->areaText($row['long_term_allocated_area_m2'] ?? 0, $unit),
                $this->areaText($row['public_utility_area_m2'] ?? 0, $unit),
                $this->areaText($row['leased_area_m2'] ?? 0, $unit),
                $this->areaText($row['converted_area_m2'] ?? 0, $unit),
            ], $rows),
            'totalRows' => count($rows),
            'filters' => $filters,
            'meta' => ['generated_at' => date('c'), 'unit' => $unit, 'source' => 'agricultural_land_zones', 'business_note' => 'Đất chuyển đổi là diện tích nằm trong đất giao dài hạn, không phải quỹ đất phát sinh thêm.'],
        ];
    }

    private function params(array $data, int $userId): array
    {
        $zoneCode = strtoupper(trim((string)($data['zone_code'] ?? $data['zoneCode'] ?? '')));
        $zoneName = trim((string)($data['zone_name'] ?? $data['zoneName'] ?? ''));
        $latitude = $this->nullableDecimal($data['latitude'] ?? null);
        $longitude = $this->nullableDecimal($data['longitude'] ?? null);
        $polygonJson = $this->nullable($data['polygon_json'] ?? $data['polygonJson'] ?? null);
        if ($zoneCode === '' || $zoneName === '') throw new \RuntimeException('Vui lòng nhập mã khu và tên khu');
        if (!preg_match('/^[A-Z0-9_-]{1,40}$/', $zoneCode)) throw new \RuntimeException('Mã khu chỉ gồm chữ, số, gạch ngang hoặc gạch dưới, tối đa 40 ký tự');
        if (mb_strlen($zoneName, 'UTF-8') > 255) throw new \RuntimeException('Tên khu không được vượt quá 255 ký tự');
        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) throw new \RuntimeException('Vĩ độ không hợp lệ');
        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) throw new \RuntimeException('Kinh độ không hợp lệ');
        if ($polygonJson !== null && json_decode($polygonJson, true) === null && json_last_error() !== JSON_ERROR_NONE) throw new \RuntimeException('Polygon JSON không hợp lệ');
        $params = [
            'zone_code' => $zoneCode,
            'zone_name' => $zoneName,
            'input_unit' => 'mau',
            'report_year' => max(1900, min(2100, (int)($data['report_year'] ?? $data['reportYear'] ?? date('Y')))),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'polygon_json' => $polygonJson,
            'irrigation_note' => $this->nullable($data['irrigation_note'] ?? $data['irrigationNote'] ?? null),
            'production_group_name' => $this->nullable($data['production_group_name'] ?? $data['productionGroupName'] ?? null),
            'main_crop_type' => $this->nullable($data['main_crop_type'] ?? $data['mainCropType'] ?? null),
            'annual_note' => $this->nullable($data['annual_note'] ?? $data['annualNote'] ?? null),
            'note' => $this->nullable($data['note'] ?? null),
            'status' => $this->validStatus((string)($data['status'] ?? 'ACTIVE')),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
        foreach (self::AREA_FIELDS as $field) $params[$field . '_m2'] = $this->areaDecimal($data[$field] ?? $data[$this->camel($field)] ?? 0);
        if ((float)$params['total_area_m2'] <= 0) throw new \RuntimeException('Tổng diện tích phải lớn hơn 0');
        $this->assertLandFundTotals($params);
        return $params;
    }

    private function where(array $filters, bool $defaultCurrentYear): array
    {
        $where = ['status <> "DELETED"', $this->tenantWhere(self::ZONES)];
        $params = $this->withTenant();
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(LOWER(zone_code) LIKE :search OR LOWER(zone_name) LIKE :search OR LOWER(note) LIKE :search)';
            $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        }
        $status = (string)($filters['status'] ?? '');
        if (in_array($status, ['ACTIVE', 'INACTIVE', 'CONVERTING'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        $year = (int)($filters['report_year'] ?? $filters['reportYear'] ?? 0);
        if ($year <= 0 && $defaultCurrentYear) $year = (int)date('Y');
        if ($year > 0) {
            $where[] = 'report_year = :report_year';
            $params['report_year'] = $year;
        }
        $zoneCode = trim((string)($filters['zone_code'] ?? $filters['zoneCode'] ?? ''));
        if ($zoneCode !== '') {
            $where[] = 'zone_code = :zone_code';
            $params['zone_code'] = $zoneCode;
        }
        return [implode(' AND ', $where), $params];
    }

    private function normalize(array $row, ?array $usageAreas = null): array
    {
        $result = [
            'id' => (int)$row['id'],
            'zone_code' => (string)$row['zone_code'],
            'zone_name' => (string)$row['zone_name'],
            'unit' => 'mau',
            'input_unit' => 'mau',
            'report_year' => (int)($row['report_year'] ?? 0),
            'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
            'polygon_json' => (string)($row['polygon_json'] ?? ''),
            'irrigation_note' => (string)($row['irrigation_note'] ?? ''),
            'production_group_name' => (string)($row['production_group_name'] ?? ''),
            'main_crop_type' => (string)($row['main_crop_type'] ?? ''),
            'annual_note' => (string)($row['annual_note'] ?? ''),
            'note' => (string)($row['note'] ?? ''),
            'status' => (string)$row['status'],
            'status_label' => $this->statusLabel((string)$row['status']),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
        foreach (self::AREA_FIELDS as $field) {
            $value = $this->decimalText($row[$field . '_m2'] ?? 0);
            $result[$field . '_m2'] = $value;
            $result[$field] = $value;
            $result[$this->camel($field)] = $result[$field];
        }
        $result['usage_areas'] = $usageAreas ?? $this->usageAreas((int)$row['id']);
        return $result;
    }

    private function replaceUsageAreas(int $zoneId, array $items, string $totalArea): void
    {
        $validTypeIds = $this->validUsageTypeIds(array_map(fn($item) => (int)($item['usage_type_id'] ?? $item['usageTypeId'] ?? $item['id'] ?? 0), $items));
        $usageTotal = 0.0;
        $pending = [];
        foreach ($items as $item) {
            $typeId = (int)($item['usage_type_id'] ?? $item['usageTypeId'] ?? $item['id'] ?? 0);
            if ($typeId <= 0) continue;
            if (!in_array($typeId, $validTypeIds, true)) throw new \RuntimeException('Loại sử dụng đất không hợp lệ');
            $area = $this->areaDecimal($item['area'] ?? $item['value'] ?? 0);
            $usageTotal += (float)$area;
            if ($usageTotal > (float)$totalArea + 0.0001) throw new \RuntimeException('Tổng diện tích cơ cấu sử dụng đất không được lớn hơn tổng diện tích khu');
            $pending[] = ['zone_id' => $zoneId, 'usage_type_id' => $typeId, 'area_m2' => $area];
        }
        $this->execute('DELETE FROM agricultural_land_zone_usage_areas WHERE zone_id=:zone_id AND ' . $this->tenantWhere(self::USAGE_AREAS), $this->withTenant(['zone_id' => $zoneId]));
        foreach ($pending as $params) {
            $columns = ['zone_id', 'usage_type_id', 'area_m2'];
            $this->addTenantInsert(self::USAGE_AREAS, $columns, $params);
            $this->insert('INSERT INTO agricultural_land_zone_usage_areas (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        }
    }

    private function usageAreas(int $zoneId): array
    {
        $rows = $this->fetchAll('SELECT zua.usage_type_id, lut.code, lut.name, zua.area_m2 FROM agricultural_land_zone_usage_areas zua INNER JOIN land_usage_types lut ON lut.id=zua.usage_type_id WHERE zua.zone_id=:zone_id AND ' . $this->tenantWhere('zua', self::USAGE_AREAS) . ' ORDER BY lut.display_order ASC, lut.name ASC', $this->withTenant(['zone_id' => $zoneId]));
        return $this->normalizeUsageAreaRows($rows);
    }

    private function usageAreasForZones(array $zoneIds): array
    {
        $zoneIds = array_values(array_unique(array_filter(array_map('intval', $zoneIds), fn($id) => $id > 0)));
        if ($zoneIds === []) return [];
        $params = $this->withTenant();
        $placeholders = [];
        foreach ($zoneIds as $index => $id) {
            $key = 'zone_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $rows = $this->fetchAll(
            'SELECT zua.zone_id, zua.usage_type_id, lut.code, lut.name, zua.area_m2
             FROM agricultural_land_zone_usage_areas zua
             INNER JOIN land_usage_types lut ON lut.id=zua.usage_type_id
             WHERE zua.zone_id IN (' . implode(',', $placeholders) . ') AND ' . $this->tenantWhere('zua', self::USAGE_AREAS) . '
             ORDER BY zua.zone_id ASC, lut.display_order ASC, lut.name ASC',
            $params
        );
        $result = [];
        foreach ($rows as $row) {
            $zoneId = (int)$row['zone_id'];
            $result[$zoneId] ??= [];
            $result[$zoneId][(int)$row['usage_type_id']] = $this->normalizeUsageAreaRow($row);
        }
        return $result;
    }

    private function normalizeUsageAreaRows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $id = (int)$row['usage_type_id'];
            $result[$id] = $this->normalizeUsageAreaRow($row);
        }
        return $result;
    }

    private function normalizeUsageAreaRow(array $row): array
    {
        $id = (int)$row['usage_type_id'];
        $area = $this->decimalText($row['area_m2'] ?? 0);
        return ['usage_type_id' => $id, 'code' => (string)$row['code'], 'name' => (string)$row['name'], 'area_m2' => $area, 'area' => $area];
    }

    private function validUsageTypeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if ($ids === []) return [];
        $params = $this->withTenant();
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $key = 'usage_type_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $rows = $this->fetchAll('SELECT id FROM land_usage_types WHERE id IN (' . implode(',', $placeholders) . ') AND ' . $this->tenantWhere(self::USAGE_TYPES), $params);
        return array_map(fn($row) => (int)$row['id'], $rows);
    }

    private function usageChart(array $filters, string $unit): array
    {
        $year = (int)($filters['report_year'] ?? $filters['reportYear'] ?? 0) ?: (int)date('Y');
        $params = [
            'type_village_id' => $this->tenantId(),
            'area_village_id' => $this->tenantId(),
            'zone_village_id' => $this->tenantId(),
            'report_year' => $year,
        ];
        $rows = $this->fetchAll(
            'SELECT lut.name AS label, COALESCE(SUM(CASE WHEN z.id IS NULL THEN 0 ELSE zua.area_m2 END),0) AS value_m2
             FROM land_usage_types lut
             LEFT JOIN agricultural_land_zone_usage_areas zua ON zua.usage_type_id=lut.id AND zua.village_id=:area_village_id
             LEFT JOIN agricultural_land_zones z ON z.id=zua.zone_id AND z.village_id=:zone_village_id AND z.status="ACTIVE" AND z.report_year=:report_year
             WHERE lut.is_active=1 AND lut.village_id=:type_village_id
             GROUP BY lut.id, lut.name
             ORDER BY lut.display_order ASC, lut.name ASC',
            $params
        );
        return array_map(fn($row) => ['label' => $row['label'], 'value' => $this->decimalText($row['value_m2'] ?? 0)], $rows);
    }

    private function landFundChart(array $row, string $unit): array
    {
        return [
            ['label' => 'Đất giao dài hạn', 'value' => $this->decimalText($row['long_term_allocated_area_m2'] ?? 0)],
            ['label' => 'Đất công ích', 'value' => $this->decimalText($row['public_utility_area_m2'] ?? 0)],
            ['label' => 'Đất thuê', 'value' => $this->decimalText($row['leased_area_m2'] ?? 0)],
        ];
    }

    private function sumSelect(): string
    {
        return implode(', ', array_map(fn($field) => 'COALESCE(SUM(' . $field . '_m2),0) AS ' . $field . '_m2', self::AREA_FIELDS));
    }

    private function seedUsageTypes(): void
    {
        $count = (int)(($this->fetchOne('SELECT COUNT(*) AS total FROM land_usage_types WHERE ' . $this->tenantWhere(self::USAGE_TYPES), $this->withTenant()) ?: [])['total'] ?? 0);
        if ($count > 0) return;
        foreach (self::DEFAULT_USAGE_TYPES as $index => [$code, $name]) {
            $params = ['code' => $code, 'name' => $name, 'display_order' => ($index + 1) * 10, 'is_active' => 1];
            $columns = ['code', 'name', 'display_order', 'is_active'];
            $this->addTenantInsert(self::USAGE_TYPES, $columns, $params);
            $this->insert('INSERT INTO land_usage_types (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        }
    }

    private function usageType(int $id): array
    {
        $row = $this->fetchOne('SELECT id, code, name, display_order, is_active FROM land_usage_types WHERE id=:id AND ' . $this->tenantWhere(self::USAGE_TYPES), $this->withTenant(['id' => $id]));
        if (!$row) throw new \RuntimeException('Không tìm thấy loại sử dụng đất');
        return ['id' => (int)$row['id'], 'code' => (string)$row['code'], 'name' => (string)$row['name'], 'display_order' => (int)$row['display_order'], 'is_active' => (bool)$row['is_active']];
    }

    private function statuses(): array
    {
        return [
            ['value' => 'ACTIVE', 'label' => 'Đang sử dụng'],
            ['value' => 'CONVERTING', 'label' => 'Đang chuyển đổi'],
            ['value' => 'INACTIVE', 'label' => 'Ngừng sử dụng'],
        ];
    }

    private function validStatus(string $status): string
    {
        return in_array($status, ['ACTIVE', 'INACTIVE', 'CONVERTING'], true) ? $status : 'ACTIVE';
    }

    private function statusLabel(string $status): string
    {
        foreach ($this->statuses() as $item) if ($item['value'] === $status) return $item['label'];
        return $status;
    }

    private function displayUnit(array $filters): string
    {
        return 'mau';
    }

    private function validUnit(string $unit): string
    {
        return 'mau';
    }

    private function areaDecimal(mixed $value): string
    {
        $text = trim(str_replace(',', '.', (string)$value));
        if ($text === '') $text = '0';
        if (!preg_match('/^\d+(?:\.\d{1,4})?$/', $text)) throw new \RuntimeException('Diện tích không hợp lệ');
        if ((float)$text < 0) throw new \RuntimeException('Diện tích không được âm');
        return $this->decimalText($text);
    }

    private function assertUniqueZoneCodeYear(string $zoneCode, int $reportYear, ?int $id = null): void
    {
        $params = $this->withTenant(['zone_code' => $zoneCode, 'report_year' => $reportYear]);
        $where = 'zone_code=:zone_code AND report_year=:report_year AND status <> "DELETED" AND ' . $this->tenantWhere(self::ZONES);
        if ($id) {
            $where .= ' AND id <> :id';
            $params['id'] = $id;
        }
        $row = $this->fetchOne('SELECT id FROM agricultural_land_zones WHERE ' . $where . ' LIMIT 1', $params);
        if ($row) throw new \RuntimeException('Mã khu đã tồn tại trong năm thống kê này');
    }

    private function assertUsageAreasWithinTotal(array $items, string $totalArea): void
    {
        $validTypeIds = $this->validUsageTypeIds(array_map(fn($item) => (int)($item['usage_type_id'] ?? $item['usageTypeId'] ?? $item['id'] ?? 0), $items));
        $usageTotal = 0.0;
        foreach ($items as $item) {
            $typeId = (int)($item['usage_type_id'] ?? $item['usageTypeId'] ?? $item['id'] ?? 0);
            if ($typeId <= 0) continue;
            if (!in_array($typeId, $validTypeIds, true)) throw new \RuntimeException('Loại sử dụng đất không hợp lệ');
            $usageTotal += (float)$this->areaDecimal($item['area'] ?? $item['value'] ?? 0);
            if ($usageTotal > (float)$totalArea + 0.0001) {
                throw new \RuntimeException('Tổng diện tích cơ cấu sử dụng đất không được lớn hơn tổng diện tích khu');
            }
        }
    }

    private function assertLandFundTotals(array $params): void
    {
        $total = (float)$params['total_area_m2'];
        $longTerm = (float)$params['long_term_allocated_area_m2'];
        $publicUtility = (float)$params['public_utility_area_m2'];
        $leased = (float)$params['leased_area_m2'];
        $converted = (float)$params['converted_area_m2'];
        $expected = $longTerm + $publicUtility + $leased;
        if (abs($total - $expected) > 0.0001) {
            throw new \RuntimeException('Tổng diện tích phải bằng Đất giao dài hạn + Đất công ích + Đất thuê');
        }
        if ($converted > $longTerm + 0.0001) {
            throw new \RuntimeException('Đất chuyển đổi không được lớn hơn diện tích đất giao dài hạn');
        }
    }

    private function areaText(float|int|string $value, string $unit): string
    {
        return $this->decimalText($value) . ' mẫu';
    }

    private function decimalText(mixed $value): string
    {
        $text = trim(str_replace(',', '.', (string)$value));
        if ($text === '') return '0';
        if (!is_numeric($text)) return '0';
        $text = rtrim(rtrim($text, '0'), '.');
        return $text === '' ? '0' : $text;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : (float)str_replace(',', '.', $value);
    }

    private function camel(string $snake): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $snake))));
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) return;
        $this->execute('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
}
