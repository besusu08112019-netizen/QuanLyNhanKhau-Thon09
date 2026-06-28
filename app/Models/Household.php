<?php

namespace App\Models;

use App\Core\BaseModel;

final class Household extends BaseModel
{
    public function page(array $filters): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $where = ['h.status <> "DELETED"'];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'h.status = :status'; $params['status'] = $filters['status']; }
        if (!empty($filters['search'])) {
            $where[] = '(h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR h.address LIKE :q OR h.phone LIKE :q OR h.area_code LIKE :q)';
            $params['q'] = '%' . $filters['search'] . '%';
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
        $id = $this->insert('INSERT INTO households (household_code, head_citizen_name, address, phone, area_code, meritorious_family, poor_household, near_poor_household, disabled_household, note, status, created_by) VALUES (:code,:head,:address,:phone,:area,:meritorious,:poor,:near_poor,:disabled,:note,:status,:user)', $this->params($data, $userId));
        return $this->find($id);
    }

    public function update(int $id, array $data, int $userId): array
    {
        $params = $this->params($data, $userId); $params['id'] = $id;
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
        return [
            'code' => strtoupper(trim((string) ($data['householdCode'] ?? $data['household_code'] ?? ''))),
            'head' => trim((string) ($data['headCitizenName'] ?? $data['head_citizen_name'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')),
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

    private function bool(mixed $value): int
    {
        $text = mb_strtolower(trim((string) $value));
        return in_array($text, ['1','true','yes','co','có','x'], true) ? 1 : 0;
    }
}
