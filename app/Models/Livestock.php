<?php

namespace App\Models;

use App\Core\BaseModel;

final class Livestock extends BaseModel
{
    public const ANIMAL_TYPES = ['Trâu','Bò','Lợn','Dê','Gà','Vịt','Ngan','Chim','Ong','Khác'];
    public const DISEASE_LABELS = [
        'NONE' => 'Không có dịch',
        'SUSPECTED' => 'Nghi dịch',
        'INFECTED' => 'Có dịch bệnh',
        'RECOVERED' => 'Đã xử lý',
    ];
    public const STATUS_LABELS = [
        'ACTIVE' => 'Đang nuôi',
        'INACTIVE' => 'Ngừng nuôi',
        'DELETED' => 'Đã xóa',
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS livestock (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  animal_type VARCHAR(80) NOT NULL,
  breed VARCHAR(120) NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  vaccinated TINYINT(1) NOT NULL DEFAULT 0,
  vaccine_date DATE NULL,
  disease_status ENUM('NONE','SUSPECTED','INFECTED','RECOVERED') NOT NULL DEFAULT 'NONE',
  barn_area VARCHAR(255) NULL,
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_livestock_household (household_id),
  KEY idx_livestock_animal_type (animal_type),
  KEY idx_livestock_status (status),
  KEY idx_livestock_vaccinated (vaccinated),
  CONSTRAINT fk_livestock_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function catalogs(): array
    {
        return [
            'animal_types' => array_map(fn($v) => ['value' => $v, 'label' => $v], self::ANIMAL_TYPES),
            'disease_statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::DISEASE_LABELS), array_values(self::DISEASE_LABELS)),
            'statuses' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(self::STATUS_LABELS), array_values(self::STATUS_LABELS)),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM livestock l INNER JOIN households h ON h.id = l.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT l.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude
             FROM livestock l
             INNER JOIN households h ON h.id = l.household_id
             $where
             $order
             LIMIT $pageSize OFFSET $offset",
            $params
        );
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            'SELECT l.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude
             FROM livestock l
             INNER JOIN households h ON h.id = l.household_id
             WHERE l.id = :id AND l.status <> "DELETED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")',
            ['id' => $id]
        );
        return $row ? $this->normalize($row) : null;
    }

    public function findByHousehold(int $householdId): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll(
            'SELECT l.*, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code, h.latitude, h.longitude
             FROM livestock l
             INNER JOIN households h ON h.id = l.household_id
             WHERE l.household_id = :household_id AND l.status <> "DELETED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
             ORDER BY l.animal_type ASC, l.id DESC',
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
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.latitude, h.longitude, COALESCE(lc.livestock_count,0) AS livestock_count
             FROM households h
             LEFT JOIN (
                SELECT l.household_id, COUNT(*) AS livestock_count
                FROM livestock l
                WHERE l.status <> "DELETED"
                GROUP BY l.household_id
             ) lc ON lc.household_id = h.id
             WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND (LOWER(h.household_code) LIKE :code OR LOWER(h.head_citizen_name) LIKE :head OR LOWER(h.address) LIKE :address)
             ORDER BY h.household_code ASC
             LIMIT ' . max(1, min(20, $limit)),
            ['code' => $keyword, 'head' => $keyword, 'address' => $keyword]
        );
        return array_map(fn($row) => [
            'id' => (int) $row['id'],
            'household_code' => (string) $row['household_code'],
            'head_citizen_name' => (string) $row['head_citizen_name'],
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float) $row['longitude'] : null,
            'livestock_count' => (int) ($row['livestock_count'] ?? 0),
        ], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->params($data, $userId, $id);
        if ($id && !$this->find($id)) throw new \RuntimeException('Không tìm thấy bản ghi vật nuôi');
        if ($id) {
            $params['id'] = $id;
            $this->execute(
                'UPDATE livestock SET household_id=:household_id, animal_type=:animal_type, breed=:breed, quantity=:quantity, vaccinated=:vaccinated, vaccine_date=:vaccine_date, disease_status=:disease_status, barn_area=:barn_area, status=:status, note=:note, updated_by=:user WHERE id=:id',
                $params
            );
            return $this->find($id);
        }
        $insertParams = $params;
        $insertParams['created_by'] = $userId;
        $insertParams['updated_by'] = $userId;
        unset($insertParams['user']);
        $newId = $this->insert(
            'INSERT INTO livestock (household_id, animal_type, breed, quantity, vaccinated, vaccine_date, disease_status, barn_area, status, note, created_by, updated_by) VALUES (:household_id, :animal_type, :breed, :quantity, :vaccinated, :vaccine_date, :disease_status, :barn_area, :status, :note, :created_by, :updated_by)',
            $insertParams
        );
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy bản ghi vật nuôi');
        $this->execute('UPDATE livestock SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id', ['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $row = $this->fetchOne(
            "SELECT
                COUNT(DISTINCT l.household_id) AS livestock_households,
                COUNT(*) AS livestock_records,
                COALESCE(SUM(l.quantity),0) AS livestock_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Trâu' THEN l.quantity ELSE 0 END),0) AS buffalo_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Bò' THEN l.quantity ELSE 0 END),0) AS cow_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Lợn' THEN l.quantity ELSE 0 END),0) AS pig_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Dê' THEN l.quantity ELSE 0 END),0) AS goat_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Gà' THEN l.quantity ELSE 0 END),0) AS chicken_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Vịt' THEN l.quantity ELSE 0 END),0) AS duck_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Ngan' THEN l.quantity ELSE 0 END),0) AS muscovy_duck_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Chim' THEN l.quantity ELSE 0 END),0) AS bird_total,
                COALESCE(SUM(CASE WHEN l.animal_type='Ong' THEN l.quantity ELSE 0 END),0) AS bee_total,
                COALESCE(SUM(CASE WHEN l.animal_type IN ('Gà','Vịt','Ngan','Chim') THEN l.quantity ELSE 0 END),0) AS poultry_total,
                COUNT(DISTINCT CASE WHEN l.vaccinated=1 THEN l.household_id END) AS vaccinated_households,
                COUNT(DISTINCT CASE WHEN l.vaccinated=0 THEN l.household_id END) AS unvaccinated_households,
                COUNT(DISTINCT CASE WHEN l.disease_status='INFECTED' THEN l.household_id END) AS disease_households
             FROM livestock l
             INNER JOIN households h ON h.id = l.household_id
             $where",
            $params
        ) ?: [];
        return array_map('intval', [
            'livestock_households' => $row['livestock_households'] ?? 0,
            'livestock_records' => $row['livestock_records'] ?? 0,
            'livestock_total' => $row['livestock_total'] ?? 0,
            'buffalo_total' => $row['buffalo_total'] ?? 0,
            'cow_total' => $row['cow_total'] ?? 0,
            'pig_total' => $row['pig_total'] ?? 0,
            'goat_total' => $row['goat_total'] ?? 0,
            'chicken_total' => $row['chicken_total'] ?? 0,
            'duck_total' => $row['duck_total'] ?? 0,
            'muscovy_duck_total' => $row['muscovy_duck_total'] ?? 0,
            'bird_total' => $row['bird_total'] ?? 0,
            'bee_total' => $row['bee_total'] ?? 0,
            'poultry_total' => $row['poultry_total'] ?? 0,
            'vaccinated_households' => $row['vaccinated_households'] ?? 0,
            'unvaccinated_households' => $row['unvaccinated_households'] ?? 0,
            'disease_households' => $row['disease_households'] ?? 0,
        ]);
    }

    public function charts(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        return [
            'types' => $this->fetchAll("SELECT l.animal_type AS label, COALESCE(SUM(l.quantity),0) AS value FROM livestock l INNER JOIN households h ON h.id = l.household_id $where GROUP BY l.animal_type ORDER BY value DESC, l.animal_type LIMIT 12", $params),
            'scale' => $this->fetchAll("SELECT CASE WHEN l.quantity <= 10 THEN '1-10 con' WHEN l.quantity <= 50 THEN '11-50 con' WHEN l.quantity <= 100 THEN '51-100 con' ELSE 'Trên 100 con' END AS label, COUNT(*) AS value FROM livestock l INNER JOIN households h ON h.id = l.household_id $where GROUP BY label ORDER BY MIN(l.quantity)", $params),
            'areas' => $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),'Chưa phân khu') AS label, COALESCE(SUM(l.quantity),0) AS value FROM livestock l INNER JOIN households h ON h.id = l.household_id $where GROUP BY label ORDER BY value DESC, label LIMIT 10", $params),
            'vaccination' => $this->fetchAll("SELECT CASE WHEN l.vaccinated=1 THEN 'Đã tiêm phòng' ELSE 'Chưa tiêm phòng' END AS label, COUNT(*) AS value FROM livestock l INNER JOIN households h ON h.id = l.household_id $where GROUP BY l.vaccinated ORDER BY l.vaccinated DESC", $params),
        ];
    }

    public function topHouseholds(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $rows = $this->fetchAll(
            "SELECT h.id AS household_id, h.household_code, h.head_citizen_name, COUNT(l.id) AS livestock_types, COALESCE(SUM(l.quantity),0) AS livestock_total
             FROM livestock l
             INNER JOIN households h ON h.id = l.household_id
             $where
             GROUP BY h.id, h.household_code, h.head_citizen_name
             ORDER BY livestock_total DESC, livestock_types DESC, h.household_code ASC
             LIMIT 10",
            $params
        );
        return array_map(fn($r) => ['household_id' => (int) $r['household_id'], 'household_code' => (string) $r['household_code'], 'head_citizen_name' => (string) $r['head_citizen_name'], 'livestock_types' => (int) $r['livestock_types'], 'livestock_total' => (int) $r['livestock_total']], $rows);
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'vaccinated') $filters['vaccinated'] = '1';
        if ($mode === 'unvaccinated') $filters['vaccinated'] = '0';
        if ($mode === 'disease') $filters['disease_status'] = 'INFECTED';
        $filters['page'] = 1;
        $filters['pageSize'] = 100;
        $rows = $this->paginate($filters)['items'];
        $title = match ($mode) {
            'by_type' => 'Báo cáo vật nuôi theo loại',
            'vaccinated' => 'Danh sách vật nuôi đã tiêm phòng',
            'unvaccinated' => 'Danh sách vật nuôi chưa tiêm phòng',
            'disease' => 'Danh sách hộ có dịch bệnh vật nuôi',
            default => 'Danh sách vật nuôi',
        };
        return $this->table($title, ['Mã hộ','Chủ hộ','Loại','Giống','Số lượng','Tiêm phòng','Dịch bệnh','Trạng thái','Địa chỉ'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['animal_type'], $r['breed'], $r['quantity'], $r['vaccinated'] ? 'Đã tiêm' : 'Chưa tiêm', $r['disease_status_label'], $r['status_label'], $r['address']], $rows), $filters);
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['l.status <> "DELETED"', 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $keyword = '%' . mb_strtolower($search, 'UTF-8') . '%';
            foreach (['h.household_code' => 'q_code', 'h.head_citizen_name' => 'q_head', 'h.address' => 'q_address', 'l.animal_type' => 'q_type', 'l.breed' => 'q_breed', 'l.barn_area' => 'q_barn', 'l.note' => 'q_note'] as $column => $param) {
                $parts[] = "LOWER($column) LIKE :$param";
                $params[$param] = $keyword;
            }
            $where[] = '(' . implode(' OR ', $parts ?? []) . ')';
        }
        $type = trim((string) ($filters['animal_type'] ?? $filters['animalType'] ?? ''));
        if ($type !== '') { $where[] = 'l.animal_type = :animal_type'; $params['animal_type'] = $type; }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') { $where[] = 'l.status = :status'; $params['status'] = $status; }
        $disease = strtoupper(trim((string) ($filters['disease_status'] ?? $filters['diseaseStatus'] ?? '')));
        if ($disease !== '') { $where[] = 'l.disease_status = :disease_status'; $params['disease_status'] = $disease; }
        $vaccinated = trim((string) ($filters['vaccinated'] ?? ''));
        if ($vaccinated === '1' || $vaccinated === '0') $where[] = 'l.vaccinated = ' . (int) $vaccinated;
        $area = trim((string) ($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') { $where[] = 'h.area_code = :area_code'; $params['area_code'] = $area; }
        $barnArea = trim((string) ($filters['barn_area'] ?? $filters['barnArea'] ?? $filters['classification'] ?? ''));
        if ($barnArea !== '') { $where[] = 'LOWER(l.barn_area) LIKE :barn_area'; $params['barn_area'] = '%' . mb_strtolower($barnArea, 'UTF-8') . '%'; }
        $from = trim((string) ($filters['date_from'] ?? $filters['dateFrom'] ?? ''));
        if ($from !== '') { $where[] = 'DATE(COALESCE(l.updated_at, l.created_at)) >= :date_from'; $params['date_from'] = $from; }
        $to = trim((string) ($filters['date_to'] ?? $filters['dateTo'] ?? ''));
        if ($to !== '') { $where[] = 'DATE(COALESCE(l.updated_at, l.created_at)) <= :date_to'; $params['date_to'] = $to; }
        $sortMap = ['household_code' => 'h.household_code', 'head_citizen_name' => 'h.head_citizen_name', 'animal_type' => 'l.animal_type', 'breed' => 'l.breed', 'quantity' => 'l.quantity', 'vaccinated' => 'l.vaccinated', 'status' => 'l.status', 'updated_at' => 'COALESCE(l.updated_at, l.created_at)'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = $this->listOrder($filters, $sortMap, 'household_code', 'ASC', ['h.household_code ASC', 'l.animal_type ASC', 'l.id ASC']);
        return $result;
    }

    private function params(array $data, int $userId, ?int $id): array
    {
        $householdId = (int) ($data['household_id'] ?? $data['householdId'] ?? 0);
        if ($householdId <= 0) throw new \RuntimeException('Hộ gia đình là bắt buộc');
        if (!$this->fetchOne('SELECT h.id FROM households h WHERE h.id = :id AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', ['id' => $householdId])) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $animalType = trim((string) ($data['animal_type'] ?? $data['animalType'] ?? ''));
        if ($animalType === '') throw new \RuntimeException(json_decode('"Lo\u1ea1i v\u1eadt nu\u00f4i l\u00e0 b\u1eaft bu\u1ed9c"', true));
        $breed = trim((string) ($data['breed'] ?? '')) ?: null;
        $barnArea = trim((string) ($data['barn_area'] ?? $data['barnArea'] ?? '')) ?: null;
        $duplicate = $this->fetchOne(
            'SELECT l.id FROM livestock l
             WHERE l.household_id = :household_id
               AND LOWER(l.animal_type) = LOWER(:animal_type)
               AND LOWER(COALESCE(l.breed,"")) = LOWER(:breed)
               AND LOWER(COALESCE(l.barn_area,"")) = LOWER(:barn_area)
               AND l.status <> "DELETED"
               AND (:current_id = 0 OR l.id <> :exclude_id)
             LIMIT 1',
            ['household_id' => $householdId, 'animal_type' => $animalType, 'breed' => $breed ?? '', 'barn_area' => $barnArea ?? '', 'current_id' => (int) ($id ?? 0), 'exclude_id' => (int) ($id ?? 0)]
        );
        if ($duplicate) throw new \RuntimeException(json_decode('"H\u1ed9 n\u00e0y \u0111\u00e3 c\u00f3 b\u1ea3n ghi tr\u00f9ng lo\u1ea1i, ph\u00e2n lo\u1ea1i v\u00e0 gi\u1ed1ng v\u1eadt nu\u00f4i."', true));
        $disease = strtoupper(trim((string) ($data['disease_status'] ?? $data['diseaseStatus'] ?? 'NONE')));
        if (!isset(self::DISEASE_LABELS[$disease])) $disease = 'NONE';
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::STATUS_LABELS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        return [
            'household_id' => $householdId,
            'animal_type' => $animalType,
            'breed' => $breed,
            'quantity' => max(0, (int) ($data['quantity'] ?? 0)),
            'vaccinated' => !empty($data['vaccinated']) && $data['vaccinated'] !== '0' ? 1 : 0,
            'vaccine_date' => trim((string) ($data['vaccine_date'] ?? $data['vaccineDate'] ?? '')) ?: null,
            'disease_status' => $disease,
            'barn_area' => $barnArea,
            'status' => $status,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'user' => $userId,
        ];
    }

    private function normalize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'household_id' => (int) $row['household_id'],
            'household_code' => (string) ($row['household_code'] ?? ''),
            'head_citizen_name' => (string) ($row['head_citizen_name'] ?? ''),
            'animal_type' => (string) ($row['animal_type'] ?? ''),
            'breed' => (string) ($row['breed'] ?? ''),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'vaccinated' => (int) ($row['vaccinated'] ?? 0) === 1,
            'vaccine_date' => $row['vaccine_date'] ?? null,
            'disease_status' => (string) ($row['disease_status'] ?? 'NONE'),
            'disease_status_label' => self::DISEASE_LABELS[$row['disease_status'] ?? 'NONE'] ?? 'Không có dịch',
            'barn_area' => (string) ($row['barn_area'] ?? ''),
            'status' => (string) ($row['status'] ?? 'ACTIVE'),
            'status_label' => self::STATUS_LABELS[$row['status'] ?? 'ACTIVE'] ?? 'Đang nuôi',
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

