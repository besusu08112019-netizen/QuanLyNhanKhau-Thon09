<?php

namespace App\Models;

use App\Core\BaseModel;

final class House extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS houses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  house_code VARCHAR(40) NOT NULL UNIQUE,
  house_name VARCHAR(255) NULL,
  address VARCHAR(500) NULL,
  house_type VARCHAR(120) NULL,
  structure_type VARCHAR(120) NULL,
  floors INT UNSIGNED NOT NULL DEFAULT 1,
  land_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  building_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  floor_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  build_year INT UNSIGNED NULL,
  renovated_year INT UNSIGNED NULL,
  `condition` VARCHAR(80) NULL,
  solidity VARCHAR(80) NULL,
  `usage` VARCHAR(120) NULL,
  legal_status VARCHAR(120) NULL,
  electric_meter VARCHAR(120) NULL,
  water_meter VARCHAR(120) NULL,
  internet TINYINT(1) NOT NULL DEFAULT 0,
  security_camera TINYINT(1) NOT NULL DEFAULT 0,
  fire_extinguisher TINYINT(1) NOT NULL DEFAULT 0,
  fire_risk VARCHAR(30) NOT NULL DEFAULT 'LOW',
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  gps_accuracy DECIMAL(10,2) NULL,
  notes TEXT NULL,
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_houses_household (household_id),
  KEY idx_houses_type (house_type),
  KEY idx_houses_condition (`condition`),
  KEY idx_houses_fire_risk (fire_risk),
  KEY idx_houses_status (status),
  CONSTRAINT fk_houses_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS house_structures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_id BIGINT UNSIGNED NOT NULL,
  structure_type VARCHAR(120) NOT NULL,
  structure_name VARCHAR(255) NULL,
  area DECIMAL(14,2) NOT NULL DEFAULT 0,
  build_year INT UNSIGNED NULL,
  `condition` VARCHAR(80) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_house_structures_house (house_id),
  KEY idx_house_structures_type (structure_type),
  CONSTRAINT fk_house_structures_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS house_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  stored_name VARCHAR(255) NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  photo_type VARCHAR(120) NULL,
  description VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_house_photos_house (house_id),
  KEY idx_house_photos_type (photo_type),
  CONSTRAINT fk_house_photos_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function catalogs(): array
    {
        return [
            'house_types' => $this->listPairs($this->houseTypes()),
            'structure_types' => $this->listPairs($this->structureTypes()),
            'conditions' => $this->listPairs($this->conditions()),
            'solidities' => $this->listPairs($this->solidities()),
            'usages' => $this->listPairs($this->usages()),
            'legal_statuses' => $this->listPairs($this->legalStatuses()),
            'fire_risks' => $this->pairs($this->fireRisks()),
            'statuses' => $this->pairs($this->statuses()),
            'photo_types' => $this->listPairs($this->photoTypes()),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM houses hs INNER JOIN households h ON h.id=hs.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT hs.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.area_code, h.address AS household_address,
                    COALESCE(sc.structure_count,0) AS structure_count, ph.file_path AS cover_photo
             FROM houses hs
             INNER JOIN households h ON h.id=hs.household_id
             LEFT JOIN (SELECT house_id, COUNT(*) AS structure_count FROM house_structures GROUP BY house_id) sc ON sc.house_id=hs.id
             LEFT JOIN (SELECT p1.house_id, p1.file_path FROM house_photos p1 INNER JOIN (SELECT house_id, MIN(id) AS id FROM house_photos WHERE deleted_at IS NULL GROUP BY house_id) p2 ON p2.id=p1.id) ph ON ph.house_id=hs.id
             $where $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalize($row), $rows), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int)ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            "SELECT hs.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.area_code, h.address AS household_address,
                    COALESCE(sc.structure_count,0) AS structure_count, ph.file_path AS cover_photo
             FROM houses hs
             INNER JOIN households h ON h.id=hs.household_id
             LEFT JOIN (SELECT house_id, COUNT(*) AS structure_count FROM house_structures GROUP BY house_id) sc ON sc.house_id=hs.id
             LEFT JOIN (SELECT p1.house_id, p1.file_path FROM house_photos p1 INNER JOIN (SELECT house_id, MIN(id) AS id FROM house_photos WHERE deleted_at IS NULL GROUP BY house_id) p2 ON p2.id=p1.id) ph ON ph.house_id=hs.id
             WHERE hs.id=:id AND hs.status <> 'DELETED' AND h.status NOT IN ('DELETED','ENDED','MERGED','TRANSFERRED_OUT','MOVED_OUT','INACTIVE')",
            ['id' => $id]
        );
        if (!$row) return null;
        $house = $this->normalize($row);
        $house['structures'] = $this->structures($id);
        $house['photos'] = $this->photos($id);
        return $house;
    }

    public function byHousehold(int $householdId): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll("SELECT hs.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.area_code, h.address AS household_address, COALESCE(sc.structure_count,0) AS structure_count, ph.file_path AS cover_photo FROM houses hs INNER JOIN households h ON h.id=hs.household_id LEFT JOIN (SELECT house_id, COUNT(*) AS structure_count FROM house_structures GROUP BY house_id) sc ON sc.house_id=hs.id LEFT JOIN (SELECT p1.house_id, p1.file_path FROM house_photos p1 INNER JOIN (SELECT house_id, MIN(id) AS id FROM house_photos WHERE deleted_at IS NULL GROUP BY house_id) p2 ON p2.id=p1.id) ph ON ph.house_id=hs.id WHERE hs.household_id=:household_id AND hs.status <> 'DELETED' ORDER BY hs.house_code ASC", ['household_id' => $householdId]);
        return array_map(fn($row) => $this->normalize($row), $rows);
    }

    public function searchHouseholds(string $query, int $limit = 12): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.latitude, h.longitude, COALESCE(hc.house_count,0) AS house_count
             FROM households h
             LEFT JOIN (SELECT household_id, COUNT(*) AS house_count FROM houses WHERE status <> "DELETED" GROUP BY household_id) hc ON hc.household_id=h.id
             WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND (LOWER(h.household_code) LIKE :code OR LOWER(h.head_citizen_name) LIKE :head OR LOWER(h.address) LIKE :address)
             ORDER BY h.household_code ASC LIMIT ' . max(1, min(20, $limit)),
            ['code' => $keyword, 'head' => $keyword, 'address' => $keyword]
        );
        return array_map(fn($row) => [
            'id' => (int)$row['id'],
            'household_code' => (string)$row['household_code'],
            'head_citizen_name' => (string)$row['head_citizen_name'],
            'address' => (string)($row['address'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float)$row['longitude'] : null,
            'house_count' => (int)($row['house_count'] ?? 0),
        ], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->find($id)) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y nh\u00e0 \u1edf'));
        $params = $this->params($data, $userId);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE houses SET household_id=:household_id, house_name=:house_name, address=:address, house_type=:house_type, structure_type=:structure_type, floors=:floors, land_area=:land_area, building_area=:building_area, floor_area=:floor_area, build_year=:build_year, renovated_year=:renovated_year, `condition`=:condition, solidity=:solidity, `usage`=:usage, legal_status=:legal_status, electric_meter=:electric_meter, water_meter=:water_meter, internet=:internet, security_camera=:security_camera, fire_extinguisher=:fire_extinguisher, fire_risk=:fire_risk, latitude=:latitude, longitude=:longitude, gps_accuracy=:gps_accuracy, notes=:notes, status=:status, updated_by=:updated_by WHERE id=:id', $params);
            $this->syncStructures($id, $data['structures'] ?? []);
            return $this->find($id);
        }
        $params['house_code'] = $this->nextCode();
        $newId = $this->insert('INSERT INTO houses (household_id, house_code, house_name, address, house_type, structure_type, floors, land_area, building_area, floor_area, build_year, renovated_year, `condition`, solidity, `usage`, legal_status, electric_meter, water_meter, internet, security_camera, fire_extinguisher, fire_risk, latitude, longitude, gps_accuracy, notes, status, created_by, updated_by) VALUES (:household_id, :house_code, :house_name, :address, :house_type, :structure_type, :floors, :land_area, :building_area, :floor_area, :build_year, :renovated_year, :condition, :solidity, :usage, :legal_status, :electric_meter, :water_meter, :internet, :security_camera, :fire_extinguisher, :fire_risk, :latitude, :longitude, :gps_accuracy, :notes, :status, :created_by, :updated_by)', $params);
        $this->syncStructures($newId, $data['structures'] ?? []);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y nh\u00e0 \u1edf'));
        $this->execute('UPDATE houses SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id', ['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]);
    }

    public function addPhoto(int $houseId, array $stored, array $file, string $mime, string $type, string $description, int $userId): array
    {
        $this->ensureSchema();
        if (!$this->find($houseId)) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y nh\u00e0 \u1edf'));
        $id = $this->insert('INSERT INTO house_photos (house_id, file_path, stored_name, original_name, mime_type, file_size, photo_type, description, created_by) VALUES (:house_id, :file_path, :stored_name, :original_name, :mime_type, :file_size, :photo_type, :description, :created_by)', [
            'house_id' => $houseId,
            'file_path' => $stored['file_path'],
            'stored_name' => $stored['stored_name'],
            'original_name' => basename((string)($file['name'] ?? '')),
            'mime_type' => $mime,
            'file_size' => (int)($file['size'] ?? 0),
            'photo_type' => $type ?: $this->u('Kh\u00e1c'),
            'description' => $description ?: null,
            'created_by' => $userId,
        ]);
        return $this->photo($id) ?: ['id' => $id];
    }

    public function deletePhoto(int $id, int $userId): ?array
    {
        $photo = $this->photo($id);
        if (!$photo) return null;
        $this->execute('UPDATE house_photos SET deleted_at=NOW(), deleted_by=:deleted_by WHERE id=:id', ['id' => $id, 'deleted_by' => $userId]);
        return $photo;
    }

    public function photo(int $id): ?array
    {
        $row = $this->fetchOne('SELECT * FROM house_photos WHERE id=:id AND deleted_at IS NULL', ['id' => $id]);
        return $row ? $this->normalizePhoto($row) : null;
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $row = $this->fetchOne("SELECT COUNT(*) AS total_houses, COUNT(DISTINCT hs.household_id) AS total_households, COALESCE(SUM(CASE WHEN hs.solidity=:solid THEN 1 ELSE 0 END),0) AS solid_houses, COALESCE(SUM(CASE WHEN hs.solidity=:semi THEN 1 ELSE 0 END),0) AS semi_solid_houses, COALESCE(SUM(CASE WHEN hs.solidity=:temp THEN 1 ELSE 0 END),0) AS temporary_houses, COALESCE(SUM(CASE WHEN hs.`condition`=:degraded THEN 1 ELSE 0 END),0) AS degraded_houses, COALESCE(SUM(CASE WHEN hs.floors>=2 THEN 1 ELSE 0 END),0) AS multi_floor_houses, COALESCE(SUM(hs.security_camera=1),0) AS camera_houses, COALESCE(SUM(hs.internet=1),0) AS internet_houses, COALESCE(SUM(hs.fire_extinguisher=1),0) AS extinguisher_houses, COALESCE(SUM(hs.fire_risk='HIGH'),0) AS high_fire_risk_houses, COALESCE(SUM(sc.structure_count),0) AS structure_total FROM houses hs INNER JOIN households h ON h.id=hs.household_id LEFT JOIN (SELECT house_id, COUNT(*) AS structure_count FROM house_structures GROUP BY house_id) sc ON sc.house_id=hs.id $where", $params + ['solid' => $this->u('Ki\u00ean c\u1ed1'), 'semi' => $this->u('B\u00e1n ki\u00ean c\u1ed1'), 'temp' => $this->u('Nh\u00e0 t\u1ea1m'), 'degraded' => $this->u('Xu\u1ed1ng c\u1ea5p')]) ?: [];
        return [
            'metrics' => array_map('intval', $row),
            'charts' => [
                'types' => $this->fetchAll("SELECT COALESCE(NULLIF(hs.house_type,''), :unknown) AS label, COUNT(*) AS value FROM houses hs INNER JOIN households h ON h.id=hs.household_id $where GROUP BY label ORDER BY value DESC LIMIT 10", $params + ['unknown' => $this->u('Ch\u01b0a c\u1eadp nh\u1eadt')]),
                'conditions' => $this->fetchAll("SELECT COALESCE(NULLIF(hs.`condition`,''), :unknown) AS label, COUNT(*) AS value FROM houses hs INNER JOIN households h ON h.id=hs.household_id $where GROUP BY label ORDER BY value DESC", $params + ['unknown' => $this->u('Ch\u01b0a c\u1eadp nh\u1eadt')]),
                'fire_risks' => $this->fetchAll("SELECT hs.fire_risk AS label, COUNT(*) AS value FROM houses hs INNER JOIN households h ON h.id=hs.household_id $where GROUP BY hs.fire_risk ORDER BY value DESC", $params),
            ],
        ];
    }

    public function gisFeatures(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $rows = $this->fetchAll("SELECT hs.id, hs.house_code, hs.house_type, hs.address, hs.latitude, hs.longitude, hs.gps_accuracy, hs.fire_risk, h.household_code, h.head_citizen_name, ph.file_path AS cover_photo FROM houses hs INNER JOIN households h ON h.id=hs.household_id LEFT JOIN (SELECT p1.house_id, p1.file_path FROM house_photos p1 INNER JOIN (SELECT house_id, MIN(id) AS id FROM house_photos WHERE deleted_at IS NULL GROUP BY house_id) p2 ON p2.id=p1.id) ph ON ph.house_id=hs.id $where AND hs.latitude IS NOT NULL AND hs.longitude IS NOT NULL ORDER BY hs.house_code ASC LIMIT 2000", $params);
        return array_map(fn($row) => $this->normalizeGis($row), $rows);
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'degraded') $filters['condition'] = $this->u('Xu\u1ed1ng c\u1ea5p');
        if ($mode === 'temporary') $filters['solidity'] = $this->u('Nh\u00e0 t\u1ea1m');
        if ($mode === 'high_fire_risk') $filters['fire_risk'] = 'HIGH';
        if ($mode === 'missing_gps') $filters['located'] = '0';
        if ($mode === 'business_usage') $filters['usage'] = $this->u('K\u1ebft h\u1ee3p kinh doanh');
        $filters['page'] = 1;
        $filters['pageSize'] = 500;
        if ($mode === 'structures') {
            $this->ensureSchema();
            [$where, $params] = $this->where($filters, false);
            $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, hs.house_code, hs.house_name, s.structure_type, s.structure_name, s.area, s.build_year, s.`condition` FROM house_structures s INNER JOIN houses hs ON hs.id=s.house_id INNER JOIN households h ON h.id=hs.household_id $where ORDER BY h.household_code, hs.house_code, s.structure_type", $params);
            return $this->table($this->u('B\u00e1o c\u00e1o c\u00f4ng tr\u00ecnh ph\u1ee5'), [$this->u('M\u00e3 h\u1ed9'),$this->u('Ch\u1ee7 h\u1ed9'),$this->u('M\u00e3 nh\u00e0'),$this->u('T\u00ean nh\u00e0'),$this->u('Lo\u1ea1i c\u00f4ng tr\u00ecnh'),$this->u('T\u00ean c\u00f4ng tr\u00ecnh'),$this->u('Di\u1ec7n t\u00edch'),$this->u('N\u0103m x\u00e2y'),$this->u('T\u00ecnh tr\u1ea1ng')], array_map(fn($r) => [$r['household_code'],$r['head_citizen_name'],$r['house_code'],$r['house_name'],$r['structure_type'],$r['structure_name'],$r['area'],$r['build_year'],$r['condition']], $rows), $filters);
        }
        $rows = $this->paginate($filters)['items'];
        $title = match ($mode) {
            'degraded' => $this->u('Danh s\u00e1ch nh\u00e0 xu\u1ed1ng c\u1ea5p'),
            'temporary' => $this->u('Danh s\u00e1ch nh\u00e0 t\u1ea1m'),
            'high_fire_risk' => $this->u('Danh s\u00e1ch nh\u00e0 nguy c\u01a1 PCCC cao'),
            'missing_gps' => $this->u('Danh s\u00e1ch nh\u00e0 ch\u01b0a c\u00f3 GPS'),
            'business_usage' => $this->u('Danh s\u00e1ch nh\u00e0 k\u1ebft h\u1ee3p kinh doanh'),
            default => $this->u('Danh s\u00e1ch nh\u00e0 \u1edf'),
        };
        return $this->table($title, [$this->u('M\u00e3 nh\u00e0'),$this->u('M\u00e3 h\u1ed9'),$this->u('Ch\u1ee7 h\u1ed9'),$this->u('\u0110\u1ecba ch\u1ec9'),$this->u('Lo\u1ea1i nh\u00e0'),$this->u('S\u1ed1 t\u1ea7ng'),$this->u('Di\u1ec7n t\u00edch s\u00e0n'),$this->u('T\u00ecnh tr\u1ea1ng'),$this->u('PCCC')], array_map(fn($r) => [$r['house_code'],$r['household_code'],$r['head_citizen_name'],$r['address'],$r['house_type'],$r['floors'],$r['floor_area'],$r['condition'],$r['fire_risk_label']], $rows), $filters);
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['hs.status <> "DELETED"', 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = [];
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $kw = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(hs.house_code) LIKE :q_code OR LOWER(h.household_code) LIKE :q_hcode OR LOWER(h.head_citizen_name) LIKE :q_head OR LOWER(hs.house_name) LIKE :q_name OR LOWER(hs.address) LIKE :q_address OR LOWER(h.address) LIKE :q_haddress)';
            $params += ['q_code' => $kw, 'q_hcode' => $kw, 'q_head' => $kw, 'q_name' => $kw, 'q_address' => $kw, 'q_haddress' => $kw];
        }
        foreach (['house_type' => 'hs.house_type', 'structure_type' => 'hs.structure_type', 'condition' => 'hs.`condition`', 'solidity' => 'hs.solidity', 'usage' => 'hs.`usage`', 'legal_status' => 'hs.legal_status', 'fire_risk' => 'hs.fire_risk', 'status' => 'hs.status'] as $key => $column) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        foreach (['internet' => 'hs.internet', 'security_camera' => 'hs.security_camera', 'fire_extinguisher' => 'hs.fire_extinguisher'] as $key => $column) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value === '1' || $value === '0') $where[] = "$column = " . (int)$value;
        }
        $floors = trim((string)($filters['floors'] ?? ''));
        if ($floors !== '') { $where[] = 'hs.floors >= :floors'; $params['floors'] = max(0, (int)$floors); }
        $located = trim((string)($filters['located'] ?? ''));
        if ($located === '1') $where[] = 'hs.latitude IS NOT NULL AND hs.longitude IS NOT NULL';
        if ($located === '0') $where[] = '(hs.latitude IS NULL OR hs.longitude IS NULL)';
        $sort = preg_replace('/[^a-z_]/', '', (string)($filters['sort'] ?? 'house_code'));
        $direction = strtoupper((string)($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sortMap = ['house_code' => 'hs.house_code', 'household_code' => 'h.household_code', 'head_citizen_name' => 'h.head_citizen_name', 'house_type' => 'hs.house_type', 'floors' => 'hs.floors', 'condition' => 'hs.`condition`', 'fire_risk' => 'hs.fire_risk', 'updated_at' => 'COALESCE(hs.updated_at,hs.created_at)'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = 'ORDER BY ' . ($sortMap[$sort] ?? 'hs.house_code') . ' ' . $direction . ', hs.id DESC';
        return $result;
    }

    private function params(array $data, int $userId): array
    {
        $householdId = (int)($data['household_id'] ?? $data['householdId'] ?? 0);
        if ($householdId <= 0) throw new \RuntimeException($this->u('H\u1ed9 gia \u0111\u00ecnh l\u00e0 b\u1eaft bu\u1ed9c'));
        if (!$this->fetchOne('SELECT h.id FROM households h WHERE h.id=:id AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', ['id' => $householdId])) throw new \RuntimeException($this->u('Kh\u00f4ng t\u00ecm th\u1ea5y h\u1ed9 gia \u0111\u00ecnh'));
        $status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
        if (!isset($this->statuses()[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        $fireRisk = strtoupper(trim((string)($data['fire_risk'] ?? $data['fireRisk'] ?? 'LOW')));
        if (!isset($this->fireRisks()[$fireRisk])) $fireRisk = 'LOW';
        return [
            'household_id' => $householdId,
            'house_name' => $this->nullable($data['house_name'] ?? $data['houseName'] ?? ''),
            'address' => $this->nullable($data['address'] ?? ''),
            'house_type' => $this->nullable($data['house_type'] ?? $data['houseType'] ?? ''),
            'structure_type' => $this->nullable($data['structure_type'] ?? $data['structureType'] ?? ''),
            'floors' => max(0, (int)($data['floors'] ?? 1)),
            'land_area' => $this->number($data['land_area'] ?? $data['landArea'] ?? 0),
            'building_area' => $this->number($data['building_area'] ?? $data['buildingArea'] ?? 0),
            'floor_area' => $this->number($data['floor_area'] ?? $data['floorArea'] ?? 0),
            'build_year' => $this->year($data['build_year'] ?? $data['buildYear'] ?? null),
            'renovated_year' => $this->year($data['renovated_year'] ?? $data['renovatedYear'] ?? null),
            'condition' => $this->nullable($data['condition'] ?? ''),
            'solidity' => $this->nullable($data['solidity'] ?? ''),
            'usage' => $this->nullable($data['usage'] ?? ''),
            'legal_status' => $this->nullable($data['legal_status'] ?? $data['legalStatus'] ?? ''),
            'electric_meter' => $this->nullable($data['electric_meter'] ?? $data['electricMeter'] ?? ''),
            'water_meter' => $this->nullable($data['water_meter'] ?? $data['waterMeter'] ?? ''),
            'internet' => $this->bool($data['internet'] ?? 0),
            'security_camera' => $this->bool($data['security_camera'] ?? $data['securityCamera'] ?? 0),
            'fire_extinguisher' => $this->bool($data['fire_extinguisher'] ?? $data['fireExtinguisher'] ?? 0),
            'fire_risk' => $fireRisk,
            'latitude' => $this->coord($data['latitude'] ?? null),
            'longitude' => $this->coord($data['longitude'] ?? null),
            'gps_accuracy' => $this->nullableNumber($data['gps_accuracy'] ?? $data['gpsAccuracy'] ?? null),
            'notes' => $this->nullable($data['notes'] ?? $data['note'] ?? ''),
            'status' => $status,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function syncStructures(int $houseId, mixed $items): void
    {
        if (is_string($items)) $items = json_decode($items, true);
        if (!is_array($items)) return;
        $this->execute('DELETE FROM house_structures WHERE house_id=:house_id', ['house_id' => $houseId]);
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $type = trim((string)($item['structure_type'] ?? $item['type'] ?? ''));
            if ($type === '') continue;
            $this->insert('INSERT INTO house_structures (house_id, structure_type, structure_name, area, build_year, `condition`, notes) VALUES (:house_id, :structure_type, :structure_name, :area, :build_year, :condition, :notes)', [
                'house_id' => $houseId,
                'structure_type' => $type,
                'structure_name' => $this->nullable($item['structure_name'] ?? $item['name'] ?? ''),
                'area' => $this->number($item['area'] ?? 0),
                'build_year' => $this->year($item['build_year'] ?? null),
                'condition' => $this->nullable($item['condition'] ?? ''),
                'notes' => $this->nullable($item['notes'] ?? $item['note'] ?? ''),
            ]);
        }
    }

    private function structures(int $houseId): array
    {
        $rows = $this->fetchAll('SELECT * FROM house_structures WHERE house_id=:house_id ORDER BY id ASC', ['house_id' => $houseId]);
        return array_map(fn($r) => ['id' => (int)$r['id'], 'house_id' => (int)$r['house_id'], 'structure_type' => (string)$r['structure_type'], 'structure_name' => (string)($r['structure_name'] ?? ''), 'area' => (float)($r['area'] ?? 0), 'build_year' => $r['build_year'] !== null ? (int)$r['build_year'] : null, 'condition' => (string)($r['condition'] ?? ''), 'notes' => (string)($r['notes'] ?? '')], $rows);
    }

    private function photos(int $houseId): array
    {
        return array_map(fn($r) => $this->normalizePhoto($r), $this->fetchAll('SELECT * FROM house_photos WHERE house_id=:house_id AND deleted_at IS NULL ORDER BY id DESC', ['house_id' => $houseId]));
    }

    private function normalize(array $row): array
    {
        return [
            'id' => (int)$row['id'], 'household_id' => (int)$row['household_id'], 'house_code' => (string)$row['house_code'], 'household_code' => (string)($row['household_code'] ?? ''), 'head_citizen_name' => (string)($row['head_citizen_name'] ?? ''),
            'house_name' => (string)($row['house_name'] ?? ''), 'address' => (string)($row['address'] ?? $row['household_address'] ?? ''), 'household_address' => (string)($row['household_address'] ?? ''), 'phone' => (string)($row['household_phone'] ?? ''), 'area_code' => (string)($row['area_code'] ?? ''),
            'house_type' => (string)($row['house_type'] ?? ''), 'structure_type' => (string)($row['structure_type'] ?? ''), 'floors' => (int)($row['floors'] ?? 0), 'land_area' => (float)($row['land_area'] ?? 0), 'building_area' => (float)($row['building_area'] ?? 0), 'floor_area' => (float)($row['floor_area'] ?? 0),
            'build_year' => $row['build_year'] !== null ? (int)$row['build_year'] : null, 'renovated_year' => $row['renovated_year'] !== null ? (int)$row['renovated_year'] : null, 'condition' => (string)($row['condition'] ?? ''), 'solidity' => (string)($row['solidity'] ?? ''), 'usage' => (string)($row['usage'] ?? ''), 'legal_status' => (string)($row['legal_status'] ?? ''),
            'electric_meter' => (string)($row['electric_meter'] ?? ''), 'water_meter' => (string)($row['water_meter'] ?? ''), 'internet' => (int)($row['internet'] ?? 0) === 1, 'security_camera' => (int)($row['security_camera'] ?? 0) === 1, 'fire_extinguisher' => (int)($row['fire_extinguisher'] ?? 0) === 1,
            'fire_risk' => (string)($row['fire_risk'] ?? 'LOW'), 'fire_risk_label' => $this->fireRisks()[$row['fire_risk'] ?? 'LOW'] ?? $this->u('Th\u1ea5p'), 'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float)$row['latitude'] : null, 'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float)$row['longitude'] : null, 'gps_accuracy' => $row['gps_accuracy'] !== null ? (float)$row['gps_accuracy'] : null,
            'notes' => (string)($row['notes'] ?? ''), 'status' => (string)($row['status'] ?? 'ACTIVE'), 'status_label' => $this->statuses()[$row['status'] ?? 'ACTIVE'] ?? $this->u('\u0110ang s\u1eed d\u1ee5ng'), 'structure_count' => (int)($row['structure_count'] ?? 0), 'cover_photo' => (string)($row['cover_photo'] ?? ''), 'cover_photo_url' => $this->photoUrl((string)($row['cover_photo'] ?? '')), 'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function normalizePhoto(array $row): array
    {
        return ['id' => (int)$row['id'], 'house_id' => (int)$row['house_id'], 'file_path' => (string)$row['file_path'], 'url' => $this->photoUrl((string)$row['file_path']), 'stored_name' => (string)($row['stored_name'] ?? ''), 'original_name' => (string)($row['original_name'] ?? ''), 'mime_type' => (string)($row['mime_type'] ?? ''), 'file_size' => (int)($row['file_size'] ?? 0), 'photo_type' => (string)($row['photo_type'] ?? ''), 'description' => (string)($row['description'] ?? ''), 'created_at' => $row['created_at'] ?? null];
    }

    private function normalizeGis(array $row): array
    {
        return ['id' => (int)$row['id'], 'house_code' => (string)$row['house_code'], 'household_code' => (string)$row['household_code'], 'head_citizen_name' => (string)$row['head_citizen_name'], 'house_type' => (string)($row['house_type'] ?? ''), 'address' => (string)($row['address'] ?? ''), 'latitude' => (float)$row['latitude'], 'longitude' => (float)$row['longitude'], 'gps_accuracy' => $row['gps_accuracy'] !== null ? (float)$row['gps_accuracy'] : null, 'fire_risk' => (string)$row['fire_risk'], 'cover_photo_url' => $this->photoUrl((string)($row['cover_photo'] ?? ''))];
    }

    private function nextCode(): string
    {
        $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM houses');
        return 'NO09-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function table(string $title, array $headers, array $rows, array $filters): array { return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')]; }
    private function photoUrl(string $path): string { return $path !== '' ? '/' . ltrim(str_replace('\\', '/', $path), '/') : ''; }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : $value; }
    private function number(mixed $value): float { return max(0, (float)str_replace(',', '.', (string)$value)); }
    private function nullableNumber(mixed $value): ?float { $value = trim((string)($value ?? '')); return $value === '' ? null : (float)$value; }
    private function coord(mixed $value): ?float { $value = trim((string)($value ?? '')); return $value === '' ? null : (float)$value; }
    private function year(mixed $value): ?int { $year = (int)($value ?? 0); return $year >= 1800 && $year <= 2100 ? $year : null; }
    private function bool(mixed $value): int { return !empty($value) && $value !== '0' ? 1 : 0; }
    private function pairs(array $map): array { return array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys($map), array_values($map)); }
    private function listPairs(array $values): array { return array_map(fn($v) => ['value' => $v, 'label' => $v], $values); }
    private function u(string $value): string { return json_decode('"' . $value . '"') ?: $value; }

    private function houseTypes(): array { return [$this->u('Nh\u00e0 \u1edf ri\u00eang l\u1ebb'),$this->u('Nh\u00e0 c\u1ea5p 4'),$this->u('Nh\u00e0 nhi\u1ec1u t\u1ea7ng'),$this->u('Nh\u00e0 k\u1ebft h\u1ee3p kinh doanh'),$this->u('Nh\u00e0 tr\u1ecd'),$this->u('Kh\u00e1c')]; }
    private function structureTypes(): array { return [$this->u('B\u1ebfp'),$this->u('Nh\u00e0 v\u1ec7 sinh'),$this->u('Chu\u1ed3ng b\u00f2'),$this->u('Chu\u1ed3ng l\u1ee3n'),$this->u('Chu\u1ed3ng d\u00ea'),$this->u('Kho'),$this->u('Nh\u00e0 xe'),$this->u('Gi\u1ebfng'),$this->u('Ao'),$this->u('X\u01b0\u1edfng'),$this->u('Nh\u00e0 tr\u1ecd'),$this->u('Nh\u00e0 k\u00ednh'),$this->u('Nh\u00e0 l\u01b0\u1edbi'),$this->u('C\u00f4ng tr\u00ecnh kh\u00e1c')]; }
    private function conditions(): array { return [$this->u('T\u1ed1t'),$this->u('Trung b\u00ecnh'),$this->u('Xu\u1ed1ng c\u1ea5p'),$this->u('Nguy hi\u1ec3m')]; }
    private function solidities(): array { return [$this->u('Ki\u00ean c\u1ed1'),$this->u('B\u00e1n ki\u00ean c\u1ed1'),$this->u('Nh\u00e0 t\u1ea1m')]; }
    private function usages(): array { return [$this->u('Nh\u00e0 \u1edf'),$this->u('K\u1ebft h\u1ee3p kinh doanh'),$this->u('Cho thu\u00ea'),$this->u('S\u1ea3n xu\u1ea5t'),$this->u('Kh\u00e1c')]; }
    private function legalStatuses(): array { return [$this->u('C\u00f3 gi\u1ea5y ch\u1ee9ng nh\u1eadn'),$this->u('Ch\u01b0a c\u00f3 gi\u1ea5y ch\u1ee9ng nh\u1eadn'),$this->u('\u0110ang ho\u00e0n thi\u1ec7n h\u1ed3 s\u01a1'),$this->u('Kh\u00e1c')]; }
    private function photoTypes(): array { return [$this->u('M\u1eb7t ti\u1ec1n'),$this->u('B\u00ean h\u00f4ng'),$this->u('Ph\u00eda sau'),$this->u('B\u00ean trong'),$this->u('C\u00f4ng tr\u00ecnh ph\u1ee5'),$this->u('Kh\u00e1c')]; }
    private function fireRisks(): array { return ['LOW' => $this->u('Th\u1ea5p'), 'MEDIUM' => $this->u('Trung b\u00ecnh'), 'HIGH' => $this->u('Cao')]; }
    private function statuses(): array { return ['ACTIVE' => $this->u('\u0110ang s\u1eed d\u1ee5ng'), 'INACTIVE' => $this->u('T\u1ea1m ng\u1eebng'), 'DELETED' => $this->u('\u0110\u00e3 x\u00f3a')]; }
}
