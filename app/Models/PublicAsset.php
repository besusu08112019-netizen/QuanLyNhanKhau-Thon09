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
  campus_area DECIMAL(14,2) NULL,
  building_area DECIMAL(14,2) NULL,
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
  KEY idx_public_assets_campus_area (campus_area),
  KEY idx_public_assets_status (status),
  KEY idx_public_assets_location (latitude, longitude),
  CONSTRAINT fk_public_assets_type FOREIGN KEY (type_id) REFERENCES public_asset_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureColumn('public_assets', 'campus_area', 'DECIMAL(14,2) NULL AFTER area_code');
        $this->ensureColumn('public_assets', 'building_area', 'DECIMAL(14,2) NULL AFTER campus_area');
        $this->ensureColumn('public_assets', 'construction_year', 'SMALLINT UNSIGNED NULL AFTER building_area');
        $this->ensureColumn('public_assets', 'operation_year', 'SMALLINT UNSIGNED NULL AFTER construction_year');
        $this->ensureColumn('public_assets', 'gps_updated_at', 'DATETIME NULL AFTER gps_accuracy');
        $this->ensureColumn('public_assets', 'manager_position', 'VARCHAR(255) NULL AFTER manager_name');
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS public_asset_inventory_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  parent_name VARCHAR(180) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_public_asset_inventory_groups_name (name),
  KEY idx_public_asset_inventory_groups_active (is_active),
  KEY idx_public_asset_inventory_groups_parent (parent_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS public_asset_inventory_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_asset_id BIGINT UNSIGNED NOT NULL,
  inventory_code VARCHAR(60) NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  group_id BIGINT UNSIGNED NULL,
  group_name VARCHAR(180) NULL,
  quantity DECIMAL(14,2) NOT NULL DEFAULT 1,
  unit VARCHAR(80) NULL,
  condition_status ENUM('NEW','GOOD','IN_USE','MAINTENANCE','LIGHT_DAMAGE','HEAVY_DAMAGE','NEEDS_REPAIR','LIQUIDATED','DELETED') NOT NULL DEFAULT 'IN_USE',
  start_use_date DATE NULL,
  location_in_asset VARCHAR(255) NULL,
  note TEXT NULL,
  photo_url VARCHAR(500) NULL,
  status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_public_asset_inventory_code (inventory_code),
  KEY idx_public_asset_inventory_asset (public_asset_id),
  KEY idx_public_asset_inventory_group (group_id),
  KEY idx_public_asset_inventory_condition (condition_status),
  KEY idx_public_asset_inventory_status (status),
  CONSTRAINT fk_public_asset_inventory_asset FOREIGN KEY (public_asset_id) REFERENCES public_assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_asset_inventory_group FOREIGN KEY (group_id) REFERENCES public_asset_inventory_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute("ALTER TABLE public_asset_inventory_items MODIFY condition_status ENUM('NEW','GOOD','IN_USE','MAINTENANCE','LIGHT_DAMAGE','HEAVY_DAMAGE','NEEDS_REPAIR','LIQUIDATED','DELETED') NOT NULL DEFAULT 'IN_USE'");
        $this->seedTypes();
        $this->seedInventoryGroups();
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

    public function inventoryCatalogs(): array
    {
        $this->ensureSchema();
        $groups = $this->fetchAll('SELECT id, name, parent_name FROM public_asset_inventory_groups WHERE is_active=1 ORDER BY sort_order ASC, name ASC');
        return [
            'groups' => array_map(fn($r) => ['value' => (string)$r['id'], 'label' => (string)$r['name'], 'parent' => (string)($r['parent_name'] ?? '')], $groups),
            'conditions' => $this->pairs($this->inventoryStatuses()),
            'units' => array_map(fn($v) => ['value' => $v, 'label' => $v], ['cái', 'bộ', 'chiếc', 'máy', 'm²', 'm', 'hộp', 'bình']),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll("SELECT pa.*, COALESCE(pat.name, pa.type_name) AS resolved_type_name, COALESCE(pat.category, pa.category) AS resolved_category, pat.icon AS type_icon FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($r) => $this->normalize($r), $rows), $page, $pageSize, $total);
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
        $existing = $id ? $this->find($id) : null;
        if ($id && !$existing) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y c\u00f4ng tr\u00ecnh'));
        $params = $this->params($data, $userId);
        if ($id) {
            $params['cover_photo_url'] = $this->coverPhotoPath($id);
        }
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE public_assets SET asset_name=:asset_name, type_id=:type_id, type_name=:type_name, category=:category, area_code=:area_code, campus_area=:campus_area, building_area=:building_area, construction_year=:construction_year, operation_year=:operation_year, address=:address, latitude=:latitude, longitude=:longitude, gps_accuracy=:gps_accuracy, gps_updated_at=:gps_updated_at, cover_photo_url=:cover_photo_url, description=:description, managing_unit=:managing_unit, manager_name=:manager_name, manager_position=:manager_position, manager_phone=:manager_phone, note=:note, status=:status, updated_by=:updated_by WHERE id=:id', $params);
            return $this->find($id);
        }
        $params['asset_code'] = $this->nextCode();
        $newId = $this->insert('INSERT INTO public_assets (asset_code, asset_name, type_id, type_name, category, area_code, campus_area, building_area, construction_year, operation_year, address, latitude, longitude, gps_accuracy, gps_updated_at, cover_photo_url, description, managing_unit, manager_name, manager_position, manager_phone, note, status, created_by, updated_by) VALUES (:asset_code, :asset_name, :type_id, :type_name, :category, :area_code, :campus_area, :building_area, :construction_year, :operation_year, :address, :latitude, :longitude, :gps_accuracy, :gps_updated_at, :cover_photo_url, :description, :managing_unit, :manager_name, :manager_position, :manager_phone, :note, :status, :created_by, :updated_by)', $params);
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
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total_assets, COALESCE(SUM(pa.status='ACTIVE'),0) AS active_assets, COALESCE(SUM(pa.status='REPAIRING'),0) AS repairing_assets, COALESCE(SUM(pa.status='SUSPENDED'),0) AS suspended_assets, COALESCE(SUM(pa.status='INACTIVE'),0) AS inactive_assets, COALESCE(SUM(pa.latitude IS NOT NULL AND pa.longitude IS NOT NULL),0) AS located_assets, COALESCE(SUM(pa.campus_area),0) AS total_campus_area, COALESCE(SUM(pa.building_area),0) AS total_building_area FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where", $params) ?: [];
        return [
            'metrics' => array_map('floatval', $metrics),
            'charts' => [
                'types' => $this->fetchAll("SELECT COALESCE(pat.name, pa.type_name, :unknown) AS label, COUNT(*) AS value FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where GROUP BY label ORDER BY value DESC LIMIT 12", $params + ['unknown' => $this->u('Ch\u01b0a c\u1eadp nh\u1eadt')]),
                'statuses' => $this->fetchAll("SELECT pa.status AS label, COUNT(*) AS value FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where GROUP BY pa.status ORDER BY value DESC", $params),
                'areas' => $this->fetchAll("SELECT COALESCE(NULLIF(pa.area_code,''), :unknown) AS label, COUNT(*) AS value FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where GROUP BY label ORDER BY value DESC LIMIT 12", $params + ['unknown' => $this->u('Ch\u01b0a c\u1eadp nh\u1eadt')]),
                'area_by_type' => $this->fetchAll("SELECT COALESCE(pat.name, pa.type_name, :unknown) AS label, COUNT(*) AS value, COALESCE(SUM(pa.campus_area),0) AS campus_area, COALESCE(SUM(pa.building_area),0) AS building_area FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where GROUP BY label HAVING campus_area > 0 OR building_area > 0 ORDER BY campus_area DESC LIMIT 12", $params + ['unknown' => $this->u('Ch\u01b0a c\u1eadp nh\u1eadt')]),
                'area_by_category' => $this->fetchAll("SELECT COALESCE(pat.category, pa.category, :unknown) AS label, COUNT(*) AS value, COALESCE(SUM(pa.campus_area),0) AS campus_area, COALESCE(SUM(pa.building_area),0) AS building_area FROM public_assets pa LEFT JOIN public_asset_types pat ON pat.id=pa.type_id $where GROUP BY label HAVING campus_area > 0 OR building_area > 0 ORDER BY campus_area DESC LIMIT 12", $params + ['unknown' => $this->u('Ch\u01b0a c\u1eadp nh\u1eadt')]),
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

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'missing_gps') $filters['located'] = '0';
        if ($mode === 'located') $filters['located'] = '1';
        $filters['page'] = 1;
        $filters['pageSize'] = 500;

        if ($mode === 'inventory') {
            return $this->inventoryReport($filters);
        }

        $rows = $this->paginate($filters)['items'];
        $title = match ($mode) {
            'missing_gps' => $this->u('Danh s\u00e1ch c\u00f4ng tr\u00ecnh ch\u01b0a c\u00f3 GPS'),
            'located' => $this->u('Danh s\u00e1ch c\u00f4ng tr\u00ecnh \u0111\u00e3 \u0111\u1ecbnh v\u1ecb GPS'),
            default => $this->u('Danh s\u00e1ch c\u00f4ng tr\u00ecnh c\u00f4ng c\u1ed9ng'),
        };

        return $this->table($title, [
            $this->u('M\u00e3 c\u00f4ng tr\u00ecnh'),
            $this->u('T\u00ean c\u00f4ng tr\u00ecnh'),
            $this->u('Lo\u1ea1i'),
            $this->u('Nh\u00f3m'),
            $this->u('Khu v\u1ef1c'),
            $this->u('\u0110\u1ecba ch\u1ec9'),
            $this->u('Di\u1ec7n t\u00edch khu\u00f4n vi\u00ean'),
            $this->u('Di\u1ec7n t\u00edch x\u00e2y d\u1ef1ng'),
            $this->u('GPS'),
            $this->u('\u0110\u01a1n v\u1ecb qu\u1ea3n l\u00fd'),
            $this->u('Ng\u01b0\u1eddi qu\u1ea3n l\u00fd'),
            $this->u('Tr\u1ea1ng th\u00e1i'),
        ], array_map(fn($row) => [
            $row['asset_code'],
            $row['asset_name'],
            $row['type_name'],
            $row['category'],
            $row['area_code'],
            $row['address'],
            $this->areaText($row['campus_area']),
            $this->areaText($row['building_area']),
            $this->gpsReportText($row),
            $row['managing_unit'],
            trim((string)($row['manager_name'] ?? '') . (($row['manager_phone'] ?? '') !== '' ? ' - ' . $row['manager_phone'] : '')),
            $row['status_label'],
        ], $rows), $filters);
    }

    public function inventoryList(int $assetId): array
    {
        $this->ensureSchema();
        $asset = $this->find($assetId);
        if (!$asset) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y c\u00f4ng tr\u00ecnh'));
        if (!$this->inventoryAllowed($asset)) {
            return [
                'enabled' => false,
                'message' => $this->u('Kh\u00f4ng \u00e1p d\u1ee5ng ki\u1ec3m k\u00ea t\u00e0i s\u1ea3n cho c\u01a1 s\u1edf t\u00f4n gi\u00e1o, t\u00edn ng\u01b0\u1ee1ng'),
                'items' => [],
                'summary' => $this->inventorySummary([]),
            ];
        }
        $rows = $this->fetchAll('SELECT pii.*, pig.name AS resolved_group_name, pig.parent_name FROM public_asset_inventory_items pii LEFT JOIN public_asset_inventory_groups pig ON pig.id=pii.group_id WHERE pii.public_asset_id=:id AND pii.status <> "DELETED" ORDER BY pii.item_name ASC, pii.id DESC', ['id' => $assetId]);
        $items = array_map(fn($r) => $this->normalizeInventoryItem($r), $rows);
        return ['enabled' => true, 'items' => $items, 'summary' => $this->inventorySummary($items)];
    }

    public function inventoryDashboard(array $filters = []): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll('SELECT pii.*, COALESCE(pig.name, pii.group_name) AS resolved_group_name, pa.asset_name FROM public_asset_inventory_items pii INNER JOIN public_assets pa ON pa.id=pii.public_asset_id LEFT JOIN public_asset_inventory_groups pig ON pig.id=pii.group_id WHERE pii.status <> "DELETED" AND pa.status <> "DELETED" ORDER BY pii.id DESC');
        $items = array_map(fn($r) => $this->normalizeInventoryItem($r), $rows);
        return $this->inventorySummary($items);
    }

    public function findInventoryItem(int $assetId, int $itemId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT pii.*, pig.name AS resolved_group_name, pig.parent_name FROM public_asset_inventory_items pii LEFT JOIN public_asset_inventory_groups pig ON pig.id=pii.group_id WHERE pii.public_asset_id=:asset_id AND pii.id=:id AND pii.status <> "DELETED"', ['asset_id' => $assetId, 'id' => $itemId]);
        return $row ? $this->normalizeInventoryItem($row) : null;
    }

    public function upsertInventoryItem(int $assetId, array $data, int $userId, ?int $itemId = null): array
    {
        $this->assertInventoryAllowed($assetId);
        $existing = $itemId ? $this->findInventoryItem($assetId, $itemId) : null;
        if ($itemId && !$existing) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y t\u00e0i s\u1ea3n ki\u1ec3m k\u00ea'));
        $params = $this->inventoryParams($assetId, $data, $userId, $existing);
        if ($itemId) {
            $params['id'] = $itemId;
            $this->execute('UPDATE public_asset_inventory_items SET inventory_code=:inventory_code, item_name=:item_name, group_id=:group_id, group_name=:group_name, quantity=:quantity, unit=:unit, condition_status=:condition_status, start_use_date=:start_use_date, location_in_asset=:location_in_asset, note=:note, photo_url=:photo_url, updated_by=:updated_by WHERE id=:id AND public_asset_id=:public_asset_id AND status <> "DELETED"', $params);
            return $this->findInventoryItem($assetId, $itemId);
        }
        $id = $this->insert('INSERT INTO public_asset_inventory_items (public_asset_id, inventory_code, item_name, group_id, group_name, quantity, unit, condition_status, start_use_date, location_in_asset, note, photo_url, created_by, updated_by) VALUES (:public_asset_id, :inventory_code, :item_name, :group_id, :group_name, :quantity, :unit, :condition_status, :start_use_date, :location_in_asset, :note, :photo_url, :created_by, :updated_by)', $params);
        return $this->findInventoryItem($assetId, $id);
    }

    public function softDeleteInventoryItem(int $assetId, int $itemId, int $userId): void
    {
        $this->assertInventoryAllowed($assetId);
        if (!$this->findInventoryItem($assetId, $itemId)) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y t\u00e0i s\u1ea3n ki\u1ec3m k\u00ea'));
        $this->execute('UPDATE public_asset_inventory_items SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE public_asset_id=:asset_id AND id=:id', ['asset_id' => $assetId, 'id' => $itemId, 'user' => $userId]);
    }

    public function setInventoryPhoto(int $assetId, int $itemId, ?string $url, int $userId): ?array
    {
        $this->assertInventoryAllowed($assetId);
        $this->execute('UPDATE public_asset_inventory_items SET photo_url=:url, updated_by=:user WHERE public_asset_id=:asset_id AND id=:id AND status <> "DELETED"', ['asset_id' => $assetId, 'id' => $itemId, 'url' => $url, 'user' => $userId]);
        return $this->findInventoryItem($assetId, $itemId);
    }

    public function inventoryPhotoPath(int $assetId, int $itemId): ?string
    {
        $row = $this->fetchOne('SELECT photo_url FROM public_asset_inventory_items WHERE public_asset_id=:asset_id AND id=:id AND status <> "DELETED"', ['asset_id' => $assetId, 'id' => $itemId]);
        $path = $row ? ($row['photo_url'] ?: null) : null;
        if ($path && $this->isInventoryPhotoApiPath($path, $assetId, $itemId)) {
            $recovered = $this->latestUploadPathFromAudit('inventory_upload_photo', (string)$itemId);
            if ($recovered) {
                $this->execute('UPDATE public_asset_inventory_items SET photo_url=:url WHERE public_asset_id=:asset_id AND id=:id AND photo_url=:old', ['asset_id' => $assetId, 'id' => $itemId, 'url' => $recovered, 'old' => $path]);
                return $recovered;
            }
        }
        return $path;
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['pa.status <> "DELETED"'];
        $params = [];
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $kw = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(pa.asset_code) LIKE :q_asset_code OR LOWER(pa.asset_name) LIKE :q_asset_name OR LOWER(pa.address) LIKE :q_address OR LOWER(pa.manager_name) LIKE :q_manager_name OR LOWER(pa.managing_unit) LIKE :q_managing_unit)';
            foreach (['q_asset_code', 'q_asset_name', 'q_address', 'q_manager_name', 'q_managing_unit'] as $key) {
                $params[$key] = $kw;
            }
        }
        foreach (['type_id' => 'pa.type_id', 'area_code' => 'pa.area_code', 'status' => 'pa.status'] as $key => $column) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        $located = trim((string)($filters['located'] ?? ''));
        if ($located === '1') $where[] = 'pa.latitude IS NOT NULL AND pa.longitude IS NOT NULL';
        if ($located === '0') $where[] = '(pa.latitude IS NULL OR pa.longitude IS NULL)';
        foreach (['area_min' => ['pa.campus_area', '>='], 'area_max' => ['pa.campus_area', '<=']] as $key => $rule) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '' && is_numeric(str_replace(',', '.', $value))) {
                $where[] = $rule[0] . ' ' . $rule[1] . ' :' . $key;
                $params[$key] = (float)str_replace(',', '.', $value);
            }
        }
        $sortMap = ['asset_code' => 'pa.asset_code', 'asset_name' => 'pa.asset_name', 'type_name' => 'resolved_type_name', 'area_code' => 'pa.area_code', 'managing_unit' => 'pa.managing_unit', 'manager_name' => 'pa.manager_name', 'status' => 'pa.status', 'campus_area' => 'pa.campus_area', 'building_area' => 'pa.building_area', 'updated_at' => 'COALESCE(pa.updated_at,pa.created_at)'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = $this->listOrder($filters, $sortMap, 'asset_code', 'ASC', ['pa.id ASC']);
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
            'campus_area' => $this->positiveNumber($data['campus_area'] ?? $data['campusArea'] ?? null, true, $this->u('Di\u1ec7n t\u00edch khu\u00f4n vi\u00ean ph\u1ea3i l\u00e0 s\u1ed1 d\u01b0\u01a1ng')),
            'building_area' => $this->positiveNumber($data['building_area'] ?? $data['buildingArea'] ?? null, false, $this->u('Di\u1ec7n t\u00edch x\u00e2y d\u1ef1ng ph\u1ea3i l\u00e0 s\u1ed1 d\u01b0\u01a1ng')),
            'construction_year' => $this->year($data['construction_year'] ?? $data['constructionYear'] ?? null, false, $this->u('N\u0103m x\u00e2y d\u1ef1ng kh\u00f4ng h\u1ee3p l\u1ec7')),
            'operation_year' => $this->year($data['operation_year'] ?? $data['operationYear'] ?? null, false, $this->u('N\u0103m \u0111\u01b0a v\u00e0o s\u1eed d\u1ee5ng kh\u00f4ng h\u1ee3p l\u1ec7')),
            'address' => $this->nullable($data['address'] ?? ''),
            'latitude' => $latitude = $this->coord($data['latitude'] ?? null),
            'longitude' => $longitude = $this->coord($data['longitude'] ?? null),
            'gps_accuracy' => $this->nullableNumber($data['gps_accuracy'] ?? $data['gpsAccuracy'] ?? null),
            'gps_updated_at' => ($latitude !== null && $longitude !== null) ? date('Y-m-d H:i:s') : null,
            'cover_photo_url' => null,
            'description' => $this->nullable($data['description'] ?? ''),
            'managing_unit' => $this->nullable($data['managing_unit'] ?? $data['managingUnit'] ?? ''),
            'manager_name' => $this->nullable($data['manager_name'] ?? $data['managerName'] ?? ''),
            'manager_position' => $this->nullable($data['manager_position'] ?? $data['managerPosition'] ?? ''),
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
            'campus_area' => $row['campus_area'] !== null && $row['campus_area'] !== '' ? (float)$row['campus_area'] : null,
            'building_area' => $row['building_area'] !== null && $row['building_area'] !== '' ? (float)$row['building_area'] : null,
            'construction_year' => $row['construction_year'] !== null ? (int)$row['construction_year'] : null,
            'operation_year' => $row['operation_year'] !== null ? (int)$row['operation_year'] : null,
            'address' => (string)($row['address'] ?? ''),
            'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float)$row['longitude'] : null,
            'gps_accuracy' => $row['gps_accuracy'] !== null ? (float)$row['gps_accuracy'] : null,
            'gps_updated_at' => $row['gps_updated_at'] ?? null,
            'cover_photo_url' => $this->coverPhotoUrl($row),
            'description' => (string)($row['description'] ?? ''),
            'managing_unit' => (string)($row['managing_unit'] ?? ''),
            'manager_name' => (string)($row['manager_name'] ?? ''),
            'manager_position' => (string)($row['manager_position'] ?? ''),
            'manager_phone' => (string)($row['manager_phone'] ?? ''),
            'note' => (string)($row['note'] ?? ''),
            'status' => $status,
            'status_label' => $this->statuses()[$status] ?? $status,
            'inventory_enabled' => $this->inventoryAllowed($row),
            'inventory_allowed' => $this->inventoryAllowed($row),
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
    private function seedInventoryGroups(): void
    {
        $items = [
            ['Nội thất', 'Nội thất'], ['Bàn', 'Nội thất'], ['Ghế', 'Nội thất'], ['Tủ', 'Nội thất'], ['Kệ', 'Nội thất'],
            ['Thiết bị điện', 'Thiết bị điện'], ['Quạt', 'Thiết bị điện'], ['Điều hòa', 'Thiết bị điện'], ['Đèn', 'Thiết bị điện'], ['Bình nóng lạnh', 'Thiết bị điện'],
            ['Thiết bị điện tử', 'Thiết bị điện tử'], ['Điện tử', 'Thiết bị điện tử'], ['Máy tính', 'Thiết bị điện tử'], ['Máy in', 'Thiết bị điện tử'], ['Máy chiếu', 'Thiết bị điện tử'], ['Loa', 'Thiết bị điện tử'], ['Amply', 'Thiết bị điện tử'], ['Micro', 'Thiết bị điện tử'], ['Camera', 'Thiết bị điện tử'],
            ['Thiết bị PCCC', 'Thiết bị PCCC'], ['PCCC', 'Thiết bị PCCC'], ['Bình chữa cháy', 'Thiết bị PCCC'], ['Tủ PCCC', 'Thiết bị PCCC'], ['Chuông báo cháy', 'Thiết bị PCCC'],
            ['Thiết bị khác', 'Thiết bị khác'], ['Dụng cụ vệ sinh', 'Thiết bị khác'], ['Thiết bị thể thao', 'Thiết bị khác'], ['Thiết bị y tế', 'Thiết bị khác'], ['Thiết bị văn phòng', 'Thiết bị khác'],
        ];
        $order = 10;
        foreach ($items as [$name, $parent]) {
            $this->execute('INSERT INTO public_asset_inventory_groups (name, parent_name, sort_order) VALUES (:name,:parent,:sort_order) ON DUPLICATE KEY UPDATE parent_name=VALUES(parent_name), sort_order=VALUES(sort_order), is_active=1', ['name' => $name, 'parent' => $parent, 'sort_order' => $order]);
            $order += 10;
        }
    }

    private function inventoryParams(int $assetId, array $data, int $userId, ?array $existing = null): array
    {
        $name = trim((string)($data['item_name'] ?? $data['itemName'] ?? ''));
        if ($name === '') throw new \RuntimeException($this->u('T\u00ean t\u00e0i s\u1ea3n l\u00e0 b\u1eaft bu\u1ed9c'));
        $groupId = (int)($data['group_id'] ?? $data['groupId'] ?? 0);
        $group = $groupId > 0 ? $this->fetchOne('SELECT id, name FROM public_asset_inventory_groups WHERE id=:id AND is_active=1', ['id' => $groupId]) : null;
        $condition = strtoupper(trim((string)($data['condition_status'] ?? $data['conditionStatus'] ?? 'IN_USE')));
        if (!isset($this->inventoryStatuses()[$condition]) || $condition === 'DELETED') $condition = 'IN_USE';
        $code = trim((string)($data['inventory_code'] ?? $data['inventoryCode'] ?? $existing['inventory_code'] ?? ''));
        if ($code === '') $code = $this->nextInventoryCode($assetId);
        $date = trim((string)($data['start_use_date'] ?? $data['startUseDate'] ?? ''));
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new \RuntimeException($this->u('Ng\u00e0y \u0111\u01b0a v\u00e0o s\u1eed d\u1ee5ng kh\u00f4ng h\u1ee3p l\u1ec7'));
        $photoUrl = $existing ? $this->inventoryPhotoPath($assetId, (int)$existing['id']) : null;
        return [
            'public_asset_id' => $assetId,
            'inventory_code' => $code,
            'item_name' => $name,
            'group_id' => $group ? (int)$group['id'] : null,
            'group_name' => $group ? (string)$group['name'] : $this->nullable($data['group_name'] ?? $data['groupName'] ?? ''),
            'quantity' => $this->positiveNumber($data['quantity'] ?? 1, true, $this->u('S\u1ed1 l\u01b0\u1ee3ng t\u00e0i s\u1ea3n ph\u1ea3i l\u00e0 s\u1ed1 d\u01b0\u01a1ng')),
            'unit' => $this->nullable($data['unit'] ?? ''),
            'condition_status' => $condition,
            'start_use_date' => $date !== '' ? $date : null,
            'location_in_asset' => $this->nullable($data['location_in_asset'] ?? $data['locationInAsset'] ?? ''),
            'note' => $this->nullable($data['note'] ?? ''),
            'photo_url' => $photoUrl,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function normalizeInventoryItem(array $row): array
    {
        $status = (string)($row['condition_status'] ?? 'IN_USE');
        return [
            'id' => (int)$row['id'],
            'public_asset_id' => (int)$row['public_asset_id'],
            'inventory_code' => (string)$row['inventory_code'],
            'item_name' => (string)$row['item_name'],
            'group_id' => $row['group_id'] !== null ? (int)$row['group_id'] : null,
            'group_name' => (string)($row['resolved_group_name'] ?? $row['group_name'] ?? ''),
            'quantity' => (float)($row['quantity'] ?? 0),
            'unit' => (string)($row['unit'] ?? ''),
            'condition_status' => $status,
            'condition_label' => $this->inventoryStatuses()[$status] ?? $status,
            'start_use_date' => $row['start_use_date'] ?? null,
            'location_in_asset' => (string)($row['location_in_asset'] ?? ''),
            'note' => (string)($row['note'] ?? ''),
            'photo_url' => $this->inventoryPhotoUrl($row),
            'asset_name' => (string)($row['asset_name'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function inventorySummary(array $items): array
    {
        $summary = ['total_items' => 0, 'total_quantity' => 0, 'by_group' => [], 'by_condition' => [], 'by_asset' => []];
        foreach ($items as $item) {
            $summary['total_items']++;
            $summary['total_quantity'] += (float)($item['quantity'] ?? 0);
            $group = $item['group_name'] ?: $this->u('Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u');
            $condition = $item['condition_label'] ?? $item['condition_status'] ?? $this->u('Ch\u01b0a c\u00f3 d\u1eef li\u1ec7u');
            $asset = $item['asset_name'] ?: (string)($item['public_asset_id'] ?? '');
            $summary['by_group'][$group] = ($summary['by_group'][$group] ?? 0) + 1;
            $summary['by_condition'][$condition] = ($summary['by_condition'][$condition] ?? 0) + 1;
            $summary['by_asset'][$asset] = ($summary['by_asset'][$asset] ?? 0) + 1;
        }
        $summary['by_group'] = $this->chartRows($summary['by_group']);
        $summary['by_condition'] = $this->chartRows($summary['by_condition']);
        $summary['by_asset'] = $this->chartRows($summary['by_asset']);
        return $summary;
    }

    private function chartRows(array $map): array
    {
        arsort($map);
        return array_map(fn($label, $value) => ['label' => (string)$label, 'value' => (int)$value], array_keys($map), array_values($map));
    }

    private function inventoryReport(array $filters): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $rows = $this->fetchAll("SELECT pa.asset_code, pa.asset_name, pii.inventory_code, pii.item_name, COALESCE(pig.name, pii.group_name) AS group_name, pii.quantity, pii.unit, pii.condition_status, pii.start_use_date, pii.location_in_asset, pii.note FROM public_asset_inventory_items pii INNER JOIN public_assets pa ON pa.id=pii.public_asset_id LEFT JOIN public_asset_types pat ON pat.id=pa.type_id LEFT JOIN public_asset_inventory_groups pig ON pig.id=pii.group_id $where AND pii.status <> \"DELETED\" ORDER BY pa.asset_code ASC, pii.item_name ASC", $params);
        return $this->table($this->u('Danh s\u00e1ch t\u00e0i s\u1ea3n ki\u1ec3m k\u00ea c\u00f4ng tr\u00ecnh'), [
            $this->u('M\u00e3 c\u00f4ng tr\u00ecnh'),
            $this->u('T\u00ean c\u00f4ng tr\u00ecnh'),
            $this->u('M\u00e3 t\u00e0i s\u1ea3n'),
            $this->u('T\u00ean t\u00e0i s\u1ea3n'),
            $this->u('Nh\u00f3m'),
            $this->u('S\u1ed1 l\u01b0\u1ee3ng'),
            $this->u('\u0110\u01a1n v\u1ecb'),
            $this->u('T\u00ecnh tr\u1ea1ng'),
            $this->u('Ng\u00e0y \u0111\u01b0a v\u00e0o s\u1eed d\u1ee5ng'),
            $this->u('V\u1ecb tr\u00ed'),
            $this->u('Ghi ch\u00fa'),
        ], array_map(fn($row) => [
            $row['asset_code'],
            $row['asset_name'],
            $row['inventory_code'],
            $row['item_name'],
            $row['group_name'],
            (float)($row['quantity'] ?? 0),
            $row['unit'],
            $this->inventoryStatuses()[$row['condition_status'] ?? 'IN_USE'] ?? (string)($row['condition_status'] ?? ''),
            $row['start_use_date'] ?? '',
            $row['location_in_asset'] ?? '',
            $row['note'] ?? '',
        ], $rows), $filters);
    }

    private function assertInventoryAllowed(int $assetId): array
    {
        $asset = $this->find($assetId);
        if (!$asset) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y c\u00f4ng tr\u00ecnh'));
        if (!$this->inventoryAllowed($asset)) throw new \RuntimeException($this->u('Kh\u00f4ng \u00e1p d\u1ee5ng ki\u1ec3m k\u00ea t\u00e0i s\u1ea3n cho c\u01a1 s\u1edf t\u00f4n gi\u00e1o, t\u00edn ng\u01b0\u1ee1ng'));
        return $asset;
    }

    private function inventoryAllowed(array $row): bool
    {
        $haystack = $this->plainText(($row['resolved_category'] ?? $row['category'] ?? '') . ' ' . ($row['resolved_type_name'] ?? $row['type_name'] ?? '') . ' ' . ($row['asset_name'] ?? ''));
        foreach (['ton giao', 'tin nguong', 'dinh', 'chua', 'den', 'mieu', 'nha tho', 'thanh that', 'diem sinh hoat ton giao'] as $needle) {
            if (str_contains($haystack, $needle)) return false;
        }
        return true;
    }

    private function plainText(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $converted !== false ? $converted : $value;
        return preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';
    }

    private function nextInventoryCode(int $assetId): string { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM public_asset_inventory_items WHERE public_asset_id=:id', ['id' => $assetId]); return 'TS' . str_pad((string)$assetId, 5, '0', STR_PAD_LEFT) . '-' . str_pad((string)(((int)($row['total'] ?? 0)) + 1), 3, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : $value; }
    private function nullableNumber(mixed $value): ?float { $value = trim((string)($value ?? '')); return $value === '' ? null : (float)str_replace(',', '.', $value); }
    private function positiveNumber(mixed $value, bool $required, string $message): ?float { $value = trim((string)($value ?? '')); if ($value === '') { if ($required) throw new \RuntimeException($message); return null; } $number = (float)str_replace(',', '.', $value); if ($number <= 0) throw new \RuntimeException($message); return $number; }
    private function year(mixed $value, bool $required, string $message): ?int { $value = trim((string)($value ?? '')); if ($value === '') { if ($required) throw new \RuntimeException($message); return null; } $year = (int)$value; $current = (int)date('Y') + 1; if ($year < 1800 || $year > $current) throw new \RuntimeException($message); return $year; }
    private function table(string $title, array $headers, array $rows, array $filters): array { return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')]; }
    private function areaText(mixed $value): string { $number = (float)($value ?? 0); return $number > 0 ? number_format($number, 2, ',', '.') . ' m2' : ''; }
    private function gpsReportText(array $row): string { return $row['latitude'] !== null && $row['longitude'] !== null ? number_format((float)$row['latitude'], 6, '.', '') . ', ' . number_format((float)$row['longitude'], 6, '.', '') : $this->u('Ch\u01b0a c\u00f3 GPS'); }
    public function setCoverPhoto(int $id, ?string $url, int $userId): ?array { $this->ensureSchema(); $this->execute('UPDATE public_assets SET cover_photo_url=:url, updated_by=:user WHERE id=:id AND status <> "DELETED"', ['id' => $id, 'url' => $url, 'user' => $userId]); return $this->find($id); }
    private function coord(mixed $value): ?float { $value = trim((string)($value ?? '')); return $value === '' ? null : (float)str_replace(',', '.', $value); }
    private function ensureColumn(string $table, string $column, string $definition): void { if ($this->columnExists($table, $column)) return; $this->execute('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition); }
    public function coverPhotoPath(int $id): ?string
    {
        $row = $this->fetchOne('SELECT cover_photo_url FROM public_assets WHERE id=:id AND status <> "DELETED"', ['id' => $id]);
        $path = $row ? ($row['cover_photo_url'] ?: null) : null;
        if ($path && $this->isCoverPhotoApiPath($path, $id)) {
            $recovered = $this->latestUploadPathFromAudit('upload_photo', (string)$id);
            if ($recovered) {
                $this->execute('UPDATE public_assets SET cover_photo_url=:url WHERE id=:id AND cover_photo_url=:old', ['id' => $id, 'url' => $recovered, 'old' => $path]);
                return $recovered;
            }
        }
        return $path;
    }
    private function coverPhotoUrl(array $row): string { $url = (string)($row['cover_photo_url'] ?? ''); return str_starts_with(ltrim($url, '/'), 'uploads/') ? '/api/public-assets/' . (int)$row['id'] . '/photo' : $url; }
    private function inventoryPhotoUrl(array $row): string { $url = (string)($row['photo_url'] ?? ''); return str_starts_with(ltrim($url, '/'), 'uploads/') ? '/api/public-assets/' . (int)$row['public_asset_id'] . '/inventory/' . (int)$row['id'] . '/photo' : $url; }
    private function isCoverPhotoApiPath(string $path, int $id): bool { return $this->storedUrlPath($path) === '/api/public-assets/' . $id . '/photo'; }
    private function isInventoryPhotoApiPath(string $path, int $assetId, int $itemId): bool { return $this->storedUrlPath($path) === '/api/public-assets/' . $assetId . '/inventory/' . $itemId . '/photo'; }
    private function storedUrlPath(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value));
        if (preg_match('#^https?://#i', $value)) {
            $parts = parse_url($value);
            $value = (string)($parts['path'] ?? '');
        }
        return '/' . ltrim($value, '/');
    }
    private function latestUploadPathFromAudit(string $action, string $entityId): ?string
    {
        try {
            $rows = $this->fetchAll('SELECT metadata FROM audit_logs WHERE module="public_assets" AND action=:action AND entity_id=:entity_id ORDER BY created_at DESC, id DESC LIMIT 5', ['action' => $action, 'entity_id' => $entityId]);
        } catch (\Throwable) {
            return null;
        }
        foreach ($rows as $row) {
            $metadata = json_decode((string)($row['metadata'] ?? ''), true);
            $path = is_array($metadata) ? (string)($metadata['file']['file_path'] ?? '') : '';
            if (str_starts_with(ltrim($path, '/'), 'uploads/')) return '/' . ltrim(str_replace('\\', '/', $path), '/');
        }
        return null;
    }
    private function pairs(array $map): array { return array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys($map), array_values($map)); }
    private function statuses(): array { return ['ACTIVE' => $this->u('\u0110ang s\u1eed d\u1ee5ng'), 'REPAIRING' => $this->u('\u0110ang s\u1eeda ch\u1eefa'), 'SUSPENDED' => $this->u('T\u1ea1m ng\u1eebng'), 'INACTIVE' => $this->u('Kh\u00f4ng c\u00f2n s\u1eed d\u1ee5ng'), 'DELETED' => $this->u('\u0110\u00e3 x\u00f3a')]; }
    private function inventoryStatuses(): array { return ['NEW' => $this->u('M\u1edbi'), 'GOOD' => $this->u('T\u1ed1t'), 'IN_USE' => $this->u('\u0110ang s\u1eed d\u1ee5ng'), 'MAINTENANCE' => $this->u('C\u1ea7n b\u1ea3o d\u01b0\u1ee1ng'), 'LIGHT_DAMAGE' => $this->u('H\u1ecfng nh\u1eb9'), 'HEAVY_DAMAGE' => $this->u('H\u1ecfng n\u1eb7ng'), 'NEEDS_REPAIR' => $this->u('C\u1ea7n s\u1eeda ch\u1eefa'), 'LIQUIDATED' => $this->u('Thanh l\u00fd'), 'DELETED' => $this->u('\u0110\u00e3 x\u00f3a')]; }
    private function u(string $value): string { return json_decode('"' . $value . '"') ?: $value; }
}
