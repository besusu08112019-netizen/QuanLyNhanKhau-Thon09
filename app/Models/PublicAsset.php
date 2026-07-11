<?php

namespace App\Models;

use App\Core\BaseModel;

final class PublicAsset extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS public_asset_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(120) NOT NULL,
  name VARCHAR(180) NOT NULL,
  icon VARCHAR(80) NOT NULL DEFAULT 'fa-building-columns',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_public_asset_types_name (name),
  KEY idx_public_asset_types_category (category),
  KEY idx_public_asset_types_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS public_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_code VARCHAR(40) NOT NULL UNIQUE,
  asset_name VARCHAR(255) NOT NULL,
  type_id BIGINT UNSIGNED NULL,
  type_name VARCHAR(180) NULL,
  category VARCHAR(120) NULL,
  area_code VARCHAR(80) NULL,
  address VARCHAR(500) NULL,
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  gps_accuracy DECIMAL(10,2) NULL,
  cover_photo_url VARCHAR(500) NULL,
  description TEXT NULL,
  managing_unit VARCHAR(255) NULL,
  manager_name VARCHAR(255) NULL,
  manager_phone VARCHAR(80) NULL,
  note TEXT NULL,
  status ENUM('ACTIVE','REPAIRING','SUSPENDED','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_public_assets_type (type_id),
  KEY idx_public_assets_area (area_code),
  KEY idx_public_assets_status (status),
  KEY idx_public_assets_location (latitude, longitude),
  CONSTRAINT fk_public_assets_type FOREIGN KEY (type_id) REFERENCES public_asset_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->seedTypes();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        $types = $this->fetchAll('SELECT id, category, name, icon FROM public_asset_types WHERE is_active=1 ORDER BY sort_order ASC, category ASC, name ASC');
        $areas = $this->fetchAll('SELECT DISTINCT area_code FROM public_assets WHERE status <> "DELETED" AND area_code IS NOT NULL AND area_code <> "" ORDER BY area_code ASC');
        return [
            'types' => array_map(fn($r) => ['value' => (string)$r['id'], 'label' => (string)$r['name'], 'category' => (string)$r['category'], 'icon' => (string)$r['icon']], $types),
            'areas' => array_map(fn($r) => ['value' => (string)$r['area_code'], 'label' => (string)$r['area_code']], $areas),
            'statuses' => $this->pairs($this->statuses()),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll("SELECT pa.*, COALESCE(pat.name, pa.type_name) AS resolved_type_name, COALESCE(pat.category, pa.category) AS resolved_category, pat.icon AS type_icon FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where $order LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => array_map(fn($r) => $this->normalize($r), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int)ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT pa.*, COALESCE(pat.name, pa.type_name) AS resolved_type_name, COALESCE(pat.category, pa.category) AS resolved_category, pat.icon AS type_icon FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id WHERE pa.id=:id AND pa.status <> "DELETED"', ['id' => $id]);
        return $row ? $this->normalize($row) : null;
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->find($id)) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y c\u00f4ng tr\u00ecnh'));
        $params = $this->params($data, $userId);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE public_assets SET asset_name=:asset_name, type_id=:type_id, type_name=:type_name, category=:category, area_code=:area_code, address=:address, latitude=:latitude, longitude=:longitude, gps_accuracy=:gps_accuracy, cover_photo_url=:cover_photo_url, description=:description, managing_unit=:managing_unit, manager_name=:manager_name, manager_phone=:manager_phone, note=:note, status=:status, updated_by=:updated_by WHERE id=:id', $params);
            return $this->find($id);
        }
        $params['asset_code'] = $this->nextCode();
        $newId = $this->insert('INSERT INTO public_assets (asset_code, asset_name, type_id, type_name, category, area_code, address, latitude, longitude, gps_accuracy, cover_photo_url, description, managing_unit, manager_name, manager_phone, note, status, created_by, updated_by) VALUES (:asset_code, :asset_name, :type_id, :type_name, :category, :area_code, :address, :latitude, :longitude, :gps_accuracy, :cover_photo_url, :description, :managing_unit, :manager_name, :manager_phone, :note, :status, :created_by, :updated_by)', $params);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y c\u00f4ng tr\u00ecnh'));
        $this->execute('UPDATE public_assets SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id', ['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total_assets, COALESCE(SUM(pa.status='ACTIVE'),0) AS active_assets, COALESCE(SUM(pa.status='REPAIRING'),0) AS repairing_assets, COALESCE(SUM(pa.status='SUSPENDED'),0) AS suspended_assets, COALESCE(SUM(pa.status='INACTIVE'),0) AS inactive_assets, COALESCE(SUM(pa.latitude IS NOT NULL AND pa.longitude IS NOT NULL),0) AS located_assets FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where", $params) ?: [];
        return [
            'metrics' => array_map('intval', $metrics),
            'charts' => [
                'types' => $this->fetchAll("SELECT COALESCE(pat.name, pa.type_name, :unknown) AS label, COUNT(*) AS value FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where GROUP BY label ORDER BY value DESC LIMIT 12", $params + ['unknown' => $this->u('Ch\u01b0a c\u1eadp nh\u1eadt')]),
                'statuses' => $this->fetchAll("SELECT pa.status AS label, COUNT(*) AS value FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where GROUP BY pa.status ORDER BY value DESC", $params),
                'areas' => $this->fetchAll("SELECT COALESCE(NULLIF(pa.area_code,''), :unknown) AS label, COUNT(*) AS value FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where GROUP BY label ORDER BY value DESC LIMIT 12", $params + ['unknown' => $this->u('Ch\u01b0a c\u1eadp nh\u1eadt')]),
            ],
        ];
    }

    public function gisFeatures(array $filters = []): array
    {
        $filters['located'] = '1';
        $filters['page'] = 1;
        $filters['pageSize'] = 2000;
        return $this->paginate($filters)['items'];
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['pa.status <> "DELETED"'];
        $params = [];
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $kw = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(pa.asset_code) LIKE :q OR LOWER(pa.asset_name) LIKE :q OR LOWER(pa.address) LIKE :q OR LOWER(pa.manager_name) LIKE :q OR LOWER(pa.managing_unit) LIKE :q)';
            $params['q'] = $kw;
        }
        foreach (['type_id' => 'pa.type_id', 'area_code' => 'pa.area_code', 'status' => 'pa.status'] as $key => $column) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        $located = trim((string)($filters['located'] ?? ''));
        if ($located === '1') $where[] = 'pa.latitude IS NOT NULL AND pa.longitude IS NOT NULL';
        if ($located === '0') $where[] = '(pa.latitude IS NULL OR pa.longitude IS NULL)';
        $sort = preg_replace('/[^a-z_]/', '', (string)($filters['sort'] ?? 'asset_code'));
        $direction = strtoupper((string)($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sortMap = ['asset_code' => 'pa.asset_code', 'asset_name' => 'pa.asset_name', 'type_name' => 'resolved_type_name', 'area_code' => 'pa.area_code', 'manager_name' => 'pa.manager_name', 'status' => 'pa.status', 'updated_at' => 'COALESCE(pa.updated_at,pa.created_at)'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = 'ORDER BY ' . ($sortMap[$sort] ?? 'pa.asset_code') . ' ' . $direction . ', pa.id DESC';
        return $result;
    }

    private function params(array $data, int $userId): array
    {
        $name = trim((string)($data['asset_name'] ?? $data['name'] ?? ''));
        if ($name === '') throw new \RuntimeException($this->u('T\u00ean c\u00f4ng tr\u00ecnh l\u00e0 b\u1eaft bu\u1ed9c'));
        $typeId = (int)($data['type_id'] ?? $data['typeId'] ?? 0);
        $type = $typeId > 0 ? $this->fetchOne('SELECT id, name, category FROM public_asset_types WHERE id=:id AND is_active=1', ['id' => $typeId]) : null;
        $status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
        if (!isset($this->statuses()[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        return [
            'asset_name' => $name,
            'type_id' => $type ? (int)$type['id'] : null,
            'type_name' => $type ? (string)$type['name'] : $this->nullable($data['type_name'] ?? $data['typeName'] ?? ''),
            'category' => $type ? (string)$type['category'] : $this->nullable($data['category'] ?? ''),
            'area_code' => $this->nullable($data['area_code'] ?? $data['areaCode'] ?? ''),
            'address' => $this->nullable($data['address'] ?? ''),
            'latitude' => $this->coord($data['latitude'] ?? null),
            'longitude' => $this->coord($data['longitude'] ?? null),
            'gps_accuracy' => $this->nullableNumber($data['gps_accuracy'] ?? $data['gpsAccuracy'] ?? null),
            'cover_photo_url' => $this->nullable($data['cover_photo_url'] ?? $data['coverPhotoUrl'] ?? ''),
            'description' => $this->nullable($data['description'] ?? ''),
            'managing_unit' => $this->nullable($data['managing_unit'] ?? $data['managingUnit'] ?? ''),
            'manager_name' => $this->nullable($data['manager_name'] ?? $data['managerName'] ?? ''),
            'manager_phone' => $this->nullable($data['manager_phone'] ?? $data['managerPhone'] ?? ''),
            'note' => $this->nullable($data['note'] ?? ''),
            'status' => $status,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function normalize(array $row): array
    {
        $status = (string)($row['status'] ?? 'ACTIVE');
        return [
            'id' => (int)$row['id'],
            'asset_code' => (string)$row['asset_code'],
            'asset_name' => (string)$row['asset_name'],
            'type_id' => $row['type_id'] !== null ? (int)$row['type_id'] : null,
            'type_name' => (string)($row['resolved_type_name'] ?? $row['type_name'] ?? ''),
            'category' => (string)($row['resolved_category'] ?? $row['category'] ?? ''),
            'type_icon' => (string)($row['type_icon'] ?? 'fa-building-columns'),
            'area_code' => (string)($row['area_code'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
            'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float)$row['longitude'] : null,
            'gps_accuracy' => $row['gps_accuracy'] !== null ? (float)$row['gps_accuracy'] : null,
            'cover_photo_url' => (string)($row['cover_photo_url'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'managing_unit' => (string)($row['managing_unit'] ?? ''),
            'manager_name' => (string)($row['manager_name'] ?? ''),
            'manager_phone' => (string)($row['manager_phone'] ?? ''),
            'note' => (string)($row['note'] ?? ''),
            'status' => $status,
            'status_label' => $this->statuses()[$status] ?? $status,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function seedTypes(): void
    {
        $items = [
            ['Hành chính','Trụ sở thôn','fa-landmark'], ['Hành chính','Nhà văn hóa','fa-building-columns'], ['Hành chính','Nhà sinh hoạt cộng đồng','fa-people-roof'],
            ['Giáo dục','Trường học','fa-school'], ['Giáo dục','Điểm trường','fa-chalkboard-user'], ['Giáo dục','Nhà trẻ','fa-child-reaching'],
            ['Y tế','Trạm y tế','fa-kit-medical'],
            ['Tôn giáo, tín ngưỡng','Đình','fa-place-of-worship'], ['Tôn giáo, tín ngưỡng','Chùa','fa-vihara'], ['Tôn giáo, tín ngưỡng','Đền','fa-gopuram'], ['Tôn giáo, tín ngưỡng','Miếu','fa-place-of-worship'], ['Tôn giáo, tín ngưỡng','Nhà thờ','fa-church'], ['Tôn giáo, tín ngưỡng','Điểm sinh hoạt tôn giáo','fa-hands-praying'],
            ['Văn hóa - Thể thao','Sân bóng','fa-futbol'], ['Văn hóa - Thể thao','Sân chơi','fa-children'], ['Văn hóa - Thể thao','Khu thể thao','fa-dumbbell'],
            ['Hạ tầng','Cầu','fa-road-bridge'], ['Hạ tầng','Đường','fa-road'], ['Hạ tầng','Trạm điện','fa-bolt'], ['Hạ tầng','Trạm bơm','fa-faucet-drip'], ['Hạ tầng','Hồ chứa nước','fa-water'], ['Hạ tầng','Công trình thủy lợi','fa-person-digging'],
            ['Môi trường','Điểm tập kết rác','fa-recycle'], ['Môi trường','Nhà vệ sinh công cộng','fa-restroom'],
        ];
        $order = 10;
        foreach ($items as [$category, $name, $icon]) {
            $this->execute('INSERT INTO public_asset_types (category, name, icon, sort_order) VALUES (:category,:name,:icon,:sort_order) ON DUPLICATE KEY UPDATE category=VALUES(category), icon=VALUES(icon), sort_order=VALUES(sort_order), is_active=1', ['category' => $category, 'name' => $name, 'icon' => $icon, 'sort_order' => $order]);
            $order += 10;
        }
    }

    private function nextCode(): string { $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM public_assets'); return 'CT09-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : $value; }
    private function nullableNumber(mixed $value): ?float { $value = trim((string)($value ?? '')); return $value === '' ? null : (float)str_replace(',', '.', $value); }
    private function coord(mixed $value): ?float { $value = trim((string)($value ?? '')); return $value === '' ? null : (float)str_replace(',', '.', $value); }
    private function pairs(array $map): array { return array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys($map), array_values($map)); }
    private function statuses(): array { return ['ACTIVE' => $this->u('\u0110ang s\u1eed d\u1ee5ng'), 'REPAIRING' => $this->u('\u0110ang s\u1eeda ch\u1eefa'), 'SUSPENDED' => $this->u('T\u1ea1m ng\u1eebng'), 'INACTIVE' => $this->u('Kh\u00f4ng c\u00f2n s\u1eed d\u1ee5ng'), 'DELETED' => $this->u('\u0110\u00e3 x\u00f3a')]; }
    private function u(string $value): string { return json_decode('"' . $value . '"') ?: $value; }
}