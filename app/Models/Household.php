<?php

namespace App\Models;

use App\Core\BaseModel;

final class Household extends BaseModel
{
    public const CATEGORY_OPTIONS = [
        'poor' => 'Hộ nghèo',
        'near_poor' => 'Hộ cận nghèo',
        'escaped_poverty' => 'Hộ mới thoát nghèo',
        'policy' => 'Hộ chính sách',
        'meritorious' => 'Hộ có công',
        'normal' => 'Hộ bình thường',
        'other' => 'Khác',
    ];

    public function paginate(array $filters): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$sqlWhere, $params] = $this->where($filters);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM households h $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT h.id, h.household_code, h.head_citizen_id, h.head_citizen_name, h.address, h.phone, h.area_code, h.meritorious_family, h.poor_household, h.near_poor_household, h.disabled_household, h.note, h.status, COALESCE(v.total_members,0) AS member_count_real, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id $sqlWhere ORDER BY h.household_code LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => array_map(fn($row) => $this->withCategory($row), $items), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        $row = $this->fetchOne('SELECT h.*, COALESCE(v.total_members,0) AS member_count_real, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id WHERE h.id = :id AND h.status <> "DELETED"', ['id' => $id]);
        return $row ? $this->withCategory($row) : null;
    }

    public function findByCode(string $code): ?array { return $this->fetchOne('SELECT * FROM households WHERE household_code = :code AND status <> "DELETED"', ['code' => strtoupper(trim($code))]); }

    public function create(array $data, int $userId): array
    {
        $params = $this->params($data, $userId);
        $this->ensureUniqueCode($params['code']);
        $id = $this->insert('INSERT INTO households (household_code, head_citizen_name, address, phone, area_code, meritorious_family, poor_household, near_poor_household, disabled_household, note, status, created_by) VALUES (:code,:head,:address,:phone,:area,:meritorious,:poor,:near_poor,:disabled,:note,:status,:user)', $params);
        return $this->find($id);
    }

    public function update(int $id, array $data, int $userId): array
    {
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy hộ dân');
        $params = $this->params($data, $userId); $params['id'] = $id;
        $this->ensureUniqueCode($params['code'], $id);
        $this->execute('UPDATE households SET household_code=:code, head_citizen_name=:head, address=:address, phone=:phone, area_code=:area, meritorious_family=:meritorious, poor_household=:poor, near_poor_household=:near_poor, disabled_household=:disabled, note=:note, status=:status, updated_by=:user WHERE id=:id', $params);
        return $this->find($id);
    }

    public function softDelete(int $id, int $userId): void
    {
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $members = (int) $this->fetchOne('SELECT COUNT(*) AS total FROM citizens WHERE household_id = :id AND status <> "DELETED" AND COALESCE(life_status,"ALIVE") <> "DECEASED" AND COALESCE(residency_status,"PERMANENT") <> "TRANSFERRED_OUT"', ['id' => $id])['total'];
        if ($members > 0) throw new \RuntimeException('Hộ gia đình vẫn còn nhân khẩu hoặc dữ liệu liên quan. Vui lòng xử lý các dữ liệu liên kết trước khi kết thúc hộ.');
        $status = $this->enumAllows('households', 'status', 'ENDED') ? 'ENDED' : 'INACTIVE';
        $this->execute('UPDATE households SET status=:status, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId, 'status' => $status]);
    }

    public function bulkSoftDelete(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (!$ids) throw new \RuntimeException('Chưa chọn hộ gia đình cần kết thúc');
        $this->db->beginTransaction();
        try {
            foreach ($ids as $id) $this->softDelete($id, $userId);
            $this->db->commit();
            return count($ids);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function where(array $filters): array
    {
        $params = [];
        if (!empty($filters['status'])) {
            $where = ['h.status = :status', 'h.status <> "DELETED"'];
            $params['status'] = $filters['status'];
        } else {
            $where = [$this->activeHouseholdCondition('h')];
        }

        $category = $this->filterCategory($filters);
        if ($category) $this->addCategoryWhere($where, $params, $category);

        if (!empty($filters['search'])) {
            $qRaw = trim((string) $filters['search']);
            $q = '%' . $qRaw . '%';
            $categorySearch = $this->categoryKey($qRaw);
            $searchParts = ['h.household_code LIKE :q_code', 'h.head_citizen_name LIKE :q_head', 'h.address LIKE :q_address', 'h.phone LIKE :q_phone', 'h.area_code LIKE :q_area', 'h.note LIKE :q_note'];
            $params['q_code'] = $q;
            $params['q_head'] = $q;
            $params['q_address'] = $q;
            $params['q_phone'] = $q;
            $params['q_area'] = $q;
            $params['q_note'] = $q;
            if ($categorySearch) {
                $categoryParts = [];
                $this->addCategoryWhere($categoryParts, $params, $categorySearch, 'search_category');
                if ($categoryParts) $searchParts[] = '(' . implode(' AND ', $categoryParts) . ')';
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function activeHouseholdCondition(string $alias): string
    {
        return $alias . ".status NOT IN ('DELETED','ENDED','MERGED','TRANSFERRED_OUT','MOVED_OUT','INACTIVE')";
    }

    private function filterCategory(array $filters): string
    {
        foreach (['household_type', 'category', 'householdType'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') return $this->categoryKey($value);
        }
        return '';
    }

    private function addCategoryWhere(array &$where, array &$params, string $category, string $prefix = 'category'): void
    {
        match ($category) {
            'poor' => $where[] = 'h.poor_household = 1',
            'near_poor' => $where[] = 'h.near_poor_household = 1',
            'meritorious' => $where[] = 'h.meritorious_family = 1',
            'normal' => $where[] = 'h.poor_household = 0 AND h.near_poor_household = 0 AND h.meritorious_family = 0 AND h.disabled_household = 0',
            'other' => $where[] = 'h.disabled_household = 1',
            'escaped_poverty', 'policy' => $this->addTextCategoryWhere($where, $params, $category, $prefix),
            default => null,
        };
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category, string $prefix): void
    {
        $label = self::CATEGORY_OPTIONS[$category] ?? $category;
        $key = $prefix . '_' . preg_replace('/[^a-z_]/', '', $category);
        $where[] = '(h.note LIKE :' . $key . '_label OR h.note LIKE :' . $key . '_key)';
        $params[$key . '_label'] = '%' . $label . '%';
        $params[$key . '_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function params(array $data, int $userId): array
    {
        $code = strtoupper(trim((string) ($data['householdCode'] ?? $data['household_code'] ?? '')));
        $address = trim((string) ($data['address'] ?? ''));
        if ($code === '') throw new \RuntimeException('Mã hộ là bắt buộc');
        if ($address === '') throw new \RuntimeException('Địa chỉ là bắt buộc');
        $category = $this->categoryKey($data['householdType'] ?? $data['household_type'] ?? $data['category'] ?? '');
        $note = trim((string) ($data['note'] ?? '')) ?: null;
        if (in_array($category, ['policy','escaped_poverty'], true)) {
            $label = self::CATEGORY_OPTIONS[$category] ?? '';
            $note = trim(($note ? $note . '; ' : '') . $label);
        }
        return [
            'code' => $code,
            'head' => trim((string) ($data['headCitizenName'] ?? $data['head_citizen_name'] ?? '')) ?: null,
            'address' => $address,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'area' => trim((string) ($data['areaCode'] ?? $data['area_code'] ?? '')) ?: null,
            'meritorious' => $category === 'meritorious' ? 1 : $this->bool($data['meritoriousFamily'] ?? $data['meritorious_family'] ?? 0),
            'poor' => $category === 'poor' ? 1 : $this->bool($data['poorHousehold'] ?? $data['poor_household'] ?? 0),
            'near_poor' => $category === 'near_poor' ? 1 : $this->bool($data['nearPoorHousehold'] ?? $data['near_poor_household'] ?? 0),
            'disabled' => $category === 'other' ? 1 : $this->bool($data['disabledHousehold'] ?? $data['disabled_household'] ?? 0),
            'note' => $note,
            'status' => $data['status'] ?? 'ACTIVE',
            'user' => $userId,
        ];
    }

    private function withCategory(array $row): array
    {
        $row['household_type'] = $this->categoryLabel($row);
        $row['household_type_key'] = $this->categoryKey($row['household_type']);
        return $row;
    }

    public function categoryLabel(array $row): string
    {
        if ((int) ($row['poor_household'] ?? 0) === 1) return self::CATEGORY_OPTIONS['poor'];
        if ((int) ($row['near_poor_household'] ?? 0) === 1) return self::CATEGORY_OPTIONS['near_poor'];
        if ((int) ($row['meritorious_family'] ?? 0) === 1) return self::CATEGORY_OPTIONS['meritorious'];
        if ((int) ($row['disabled_household'] ?? 0) === 1) return self::CATEGORY_OPTIONS['other'];
        $noteKey = $this->categoryKey((string) ($row['note'] ?? ''));
        if ($noteKey && isset(self::CATEGORY_OPTIONS[$noteKey])) return self::CATEGORY_OPTIONS[$noteKey];
        return self::CATEGORY_OPTIONS['normal'];
    }

    public function categoryKey(mixed $value): string
    {
        $text = $this->normalize((string) $value);
        if ($text === '') return '';
        return match (true) {
            str_contains($text, 'can ngheo') || str_contains($text, 'near poor') => 'near_poor',
            str_contains($text, 'moi thoat ngheo') || str_contains($text, 'thoat ngheo') || str_contains($text, 'escaped poverty') => 'escaped_poverty',
            str_contains($text, 'chinh sach') || str_contains($text, 'policy') => 'policy',
            str_contains($text, 'co cong') || str_contains($text, 'gia dinh co cong') || str_contains($text, 'meritorious') => 'meritorious',
            str_contains($text, 'binh thuong') || str_contains($text, 'normal') || $text === 'khong' => 'normal',
            str_contains($text, 'khac') || str_contains($text, 'tan tat') || str_contains($text, 'khuyet tat') || str_contains($text, 'other') => 'other',
            str_contains($text, 'ngheo') || str_contains($text, 'poor') => 'poor',
            default => '',
        };
    }

    private function ensureUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $params = ['code' => $code];
        $sql = 'SELECT id FROM households WHERE household_code=:code AND status <> "DELETED"';
        if ($ignoreId) { $sql .= ' AND id <> :id'; $params['id'] = $ignoreId; }
        if ($this->fetchOne($sql, $params)) throw new \RuntimeException('Mã hộ đã tồn tại');
    }

    private function enumAllows(string $table, string $column, string $value): bool
    {
        if (!$this->columnExists($table, $column)) return false;
        $row = $this->fetchOne('SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1', ['table' => $table, 'column' => $column]);
        return str_contains((string) ($row['COLUMN_TYPE'] ?? ''), "'" . $value . "'");
    }

    private function bool(mixed $value): int
    {
        $text = mb_strtolower(trim((string) $value));
        return in_array($text, ['1','true','yes','co','có','x'], true) ? 1 : 0;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }
}
