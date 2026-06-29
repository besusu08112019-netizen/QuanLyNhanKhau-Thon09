<?php

namespace App\Models;

use App\Core\BaseModel;

final class Household extends BaseModel
{
    public function paginate(array $filters): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $where = ['h.status <> "DELETED"'];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'h.status = :status'; $params['status'] = $filters['status']; }
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $where[] = '(h.household_code LIKE :q_code OR h.head_citizen_name LIKE :q_head OR h.address LIKE :q_address OR h.phone LIKE :q_phone OR h.area_code LIKE :q_area)';
            $params['q_code'] = $q;
            $params['q_head'] = $q;
            $params['q_address'] = $q;
            $params['q_phone'] = $q;
            $params['q_area'] = $q;
        }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM households h $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT h.*, COALESCE(v.total_members,0) AS member_count_real, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id $sqlWhere ORDER BY h.household_code LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT h.*, COALESCE(v.total_members,0) AS member_count_real, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id WHERE h.id = :id AND h.status <> "DELETED"', ['id' => $id]);
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
        $members = (int) $this->fetchOne('SELECT COUNT(*) AS total FROM citizens WHERE household_id = :id AND status <> "DELETED"', ['id' => $id])['total'];
        if ($members > 0) throw new \RuntimeException('Không thể xóa hộ đang có ' . $members . ' nhân khẩu hoạt động');
        $this->execute('UPDATE households SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    private function params(array $data, int $userId): array
    {
        $code = strtoupper(trim((string) ($data['householdCode'] ?? $data['household_code'] ?? '')));
        $address = trim((string) ($data['address'] ?? ''));
        if ($code === '') throw new \RuntimeException('Mã hộ là bắt buộc');
        if ($address === '') throw new \RuntimeException('Địa chỉ là bắt buộc');
        return [
            'code' => $code,
            'head' => trim((string) ($data['headCitizenName'] ?? $data['head_citizen_name'] ?? '')) ?: null,
            'address' => $address,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'area' => trim((string) ($data['areaCode'] ?? $data['area_code'] ?? '')) ?: null,
            'meritorious' => $this->bool($data['meritoriousFamily'] ?? $data['meritorious_family'] ?? 0),
            'poor' => $this->bool($data['poorHousehold'] ?? $data['poor_household'] ?? 0),
            'near_poor' => $this->bool($data['nearPoorHousehold'] ?? $data['near_poor_household'] ?? 0),
            'disabled' => $this->bool($data['disabledHousehold'] ?? $data['disabled_household'] ?? 0),
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'status' => $data['status'] ?? 'ACTIVE',
            'user' => $userId,
        ];
    }

    private function ensureUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $params = ['code' => $code];
        $sql = 'SELECT id FROM households WHERE household_code=:code AND status <> "DELETED"';
        if ($ignoreId) { $sql .= ' AND id <> :id'; $params['id'] = $ignoreId; }
        if ($this->fetchOne($sql, $params)) throw new \RuntimeException('Mã hộ đã tồn tại');
    }

    private function bool(mixed $value): int
    {
        $text = mb_strtolower(trim((string) $value));
        return in_array($text, ['1','true','yes','co','có','x'], true) ? 1 : 0;
    }
}
