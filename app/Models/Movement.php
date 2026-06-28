<?php

namespace App\Models;

use App\Core\BaseModel;

final class Movement extends BaseModel
{
    public function page(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $where = ['m.status <> "DELETED"'];
        $params = [];
        if (!empty($filters['type'])) { $where[] = 'm.type = :type'; $params['type'] = $filters['type']; }
        if (!empty($filters['dateFrom'])) { $where[] = 'm.effective_date >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'm.effective_date <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['search'])) { $where[] = '(c.full_name LIKE :q OR c.identity_number LIKE :q OR h.household_code LIKE :q OR m.reason LIKE :q OR m.document_number LIKE :q)'; $params['q'] = '%' . $filters['search'] . '%'; }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM movements m INNER JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT m.*, c.full_name, c.identity_number, c.citizen_code, h.household_code FROM movements m INNER JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id $sqlWhere ORDER BY m.effective_date DESC, m.id DESC LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT m.*, c.full_name, c.identity_number, c.citizen_code, h.household_code FROM movements m INNER JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id WHERE m.id=:id AND m.status <> "DELETED"', ['id' => $id]);
    }

    public function create(array $data, int $userId): array
    {
        $params = $this->params($data, $userId);
        $id = $this->insert('INSERT INTO movements (citizen_id, household_id, type, from_address, to_address, reason, effective_date, document_number, note, status, created_by) VALUES (:citizen_id,:household_id,:type,:from_address,:to_address,:reason,:effective_date,:document_number,:note,"ACTIVE",:user)', $params);
        return $this->find($id);
    }

    public function update(int $id, array $data, int $userId): array
    {
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy biến động');
        $params = $this->params($data, $userId); $params['id'] = $id;
        $this->execute('UPDATE movements SET citizen_id=:citizen_id, household_id=:household_id, type=:type, from_address=:from_address, to_address=:to_address, reason=:reason, effective_date=:effective_date, document_number=:document_number, note=:note, updated_by=:user WHERE id=:id', $params);
        return $this->find($id);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->execute('UPDATE movements SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    public function types(): array
    {
        return [
            ['value' => 'BIRTH', 'label' => 'Sinh'],
            ['value' => 'DEATH', 'label' => 'Tử'],
            ['value' => 'MOVE_IN', 'label' => 'Chuyển đến'],
            ['value' => 'MOVE_OUT', 'label' => 'Chuyển đi'],
            ['value' => 'TEMPORARY_RESIDENCE', 'label' => 'Tạm trú'],
            ['value' => 'TEMPORARY_ABSENCE', 'label' => 'Tạm vắng'],
            ['value' => 'OTHER', 'label' => 'Khác'],
        ];
    }

    private function params(array $data, int $userId): array
    {
        $citizenId = (int) ($data['citizenId'] ?? $data['citizen_id'] ?? 0);
        if ($citizenId <= 0) throw new \RuntimeException('Nhân khẩu là bắt buộc');
        $citizen = $this->fetchOne('SELECT id, household_id FROM citizens WHERE id=:id AND status <> "DELETED"', ['id' => $citizenId]);
        if (!$citizen) throw new \RuntimeException('Không tìm thấy nhân khẩu');
        $type = (string) ($data['type'] ?? 'OTHER');
        $allowed = array_column($this->types(), 'value');
        if (!in_array($type, $allowed, true)) $type = 'OTHER';
        $date = (string) ($data['effectiveDate'] ?? $data['effective_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new \RuntimeException('Ngày biến động không hợp lệ');
        return [
            'citizen_id' => $citizenId,
            'household_id' => (int) ($data['householdId'] ?? $data['household_id'] ?? $citizen['household_id']),
            'type' => $type,
            'from_address' => trim((string) ($data['fromAddress'] ?? $data['from_address'] ?? '')) ?: null,
            'to_address' => trim((string) ($data['toAddress'] ?? $data['to_address'] ?? '')) ?: null,
            'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
            'effective_date' => $date,
            'document_number' => trim((string) ($data['documentNumber'] ?? $data['document_number'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'user' => $userId,
        ];
    }
}
