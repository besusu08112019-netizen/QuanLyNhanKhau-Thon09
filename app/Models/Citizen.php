<?php

namespace App\Models;

use App\Core\BaseModel;

final class Citizen extends BaseModel
{
    public function page(array $filters): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $where = ['c.status <> "DELETED"']; $params = [];
        if (!empty($filters['status'])) { $where[] = 'c.life_status = :life_status'; $params['life_status'] = $filters['status']; }
        if (!empty($filters['presenceStatus'])) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if (!empty($filters['householdId'])) { $where[] = '(h.household_code = :household OR c.household_id = :household_id)'; $params['household'] = $filters['householdId']; $params['household_id'] = (int) $filters['householdId']; }
        if (!empty($filters['search'])) { $where[] = '(c.citizen_code LIKE :q OR c.full_name LIKE :q OR c.identity_number LIKE :q OR c.phone LIKE :q OR h.household_code LIKE :q)'; $params['q'] = '%' . $filters['search'] . '%'; }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name FROM citizens c INNER JOIN households h ON h.id=c.household_id $sqlWhere ORDER BY h.household_code, CASE WHEN c.relationship='Chủ hộ' THEN 0 ELSE 1 END, c.full_name LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.id=:id AND c.status <> "DELETED"', ['id' => $id]);
    }

    public function create(array $data, int $userId): array
    {
        $params = $this->params($data, $userId);
        if ($params['code'] === '') $params['code'] = $this->nextCode((int) $params['household_id']);
        $this->ensureUniqueIdentity($params['identity']);
        $id = $this->insert('INSERT INTO citizens (citizen_code, household_id, full_name, gender, date_of_birth, identity_number, identity_issue_date, identity_issue_place, relationship, ethnicity, religion, occupation, phone, residency_status, current_address, education_level, marital_status, life_status, presence_status, status, created_by) VALUES (:code,:household_id,:full_name,:gender,:dob,:identity,:issue_date,:issue_place,:relationship,:ethnicity,:religion,:occupation,:phone,:residency,:current_address,:education,:marital,:life,:presence,"ACTIVE",:user)', $params);
        $this->syncHouseholdHead((int) $params['household_id']);
        return $this->find($id);
    }

    public function update(int $id, array $data, int $userId): array
    {
        $before = $this->find($id);
        if (!$before) throw new \RuntimeException('Không tìm thấy nhân khẩu');
        $params = $this->params($data, $userId, $before); $params['id'] = $id;
        if ($params['code'] === '') $params['code'] = (string) $before['citizen_code'];
        $this->ensureUniqueIdentity($params['identity'], $id);
        $this->execute('UPDATE citizens SET citizen_code=:code, household_id=:household_id, full_name=:full_name, gender=:gender, date_of_birth=:dob, identity_number=:identity, identity_issue_date=:issue_date, identity_issue_place=:issue_place, relationship=:relationship, ethnicity=:ethnicity, religion=:religion, occupation=:occupation, phone=:phone, residency_status=:residency, current_address=:current_address, education_level=:education, marital_status=:marital, life_status=:life, presence_status=:presence, updated_by=:user WHERE id=:id', $params);
        $this->syncHouseholdHead((int) $before['household_id']);
        $this->syncHouseholdHead((int) $params['household_id']);
        return $this->find($id);
    }

    public function softDelete(int $id, int $userId): void
    {
        $person = $this->find($id);
        $this->execute('UPDATE citizens SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
        if ($person) $this->syncHouseholdHead((int) $person['household_id']);
    }

    public function restore(int $id, int $userId): void
    {
        $this->execute('UPDATE citizens SET status="ACTIVE", deleted_at=NULL, deleted_by=NULL, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
        $person = $this->find($id);
        if ($person) $this->syncHouseholdHead((int) $person['household_id']);
    }

    private function params(array $data, int $userId, ?array $fallback = null): array
    {
        $household = new Household();
        $householdKey = $data['householdId'] ?? $data['householdCode'] ?? $fallback['household_id'] ?? '';
        $householdRow = is_numeric($householdKey) ? $household->find((int) $householdKey) : $household->findByCode((string) $householdKey);
        if (!$householdRow) throw new \RuntimeException('Không tìm thấy Mã hộ');

        $fullName = trim((string) ($data['fullName'] ?? $data['full_name'] ?? $fallback['full_name'] ?? ''));
        $dob = $data['dateOfBirth'] ?? $data['date_of_birth'] ?? $fallback['date_of_birth'] ?? null;
        if ($fullName === '') throw new \RuntimeException('Họ và tên là bắt buộc');
        if (!$dob || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dob)) throw new \RuntimeException('Ngày sinh không hợp lệ');

        return [
            'code' => strtoupper(trim((string) ($data['citizenCode'] ?? $data['citizen_code'] ?? $fallback['citizen_code'] ?? ''))),
            'household_id' => (int) $householdRow['id'],
            'full_name' => $fullName,
            'gender' => in_array(($data['gender'] ?? $fallback['gender'] ?? 'Khác'), ['Nam','Nữ','Khác'], true) ? ($data['gender'] ?? $fallback['gender'] ?? 'Khác') : 'Khác',
            'dob' => $dob,
            'identity' => trim((string) ($data['identityNumber'] ?? $data['identity_number'] ?? $fallback['identity_number'] ?? '')) ?: null,
            'issue_date' => $data['identityIssueDate'] ?? $data['identity_issue_date'] ?? $fallback['identity_issue_date'] ?? null,
            'issue_place' => trim((string) ($data['identityIssuePlace'] ?? $data['identity_issue_place'] ?? $fallback['identity_issue_place'] ?? '')) ?: null,
            'relationship' => trim((string) ($data['relationship'] ?? $fallback['relationship'] ?? 'Khác')),
            'ethnicity' => trim((string) ($data['ethnicity'] ?? $fallback['ethnicity'] ?? '')) ?: null,
            'religion' => trim((string) ($data['religion'] ?? $fallback['religion'] ?? '')) ?: null,
            'occupation' => trim((string) ($data['occupation'] ?? $fallback['occupation'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? $fallback['phone'] ?? '')) ?: null,
            'residency' => $this->residency($data['residency_status'] ?? $data['permanentAddress'] ?? $fallback['residency_status'] ?? 'PERMANENT'),
            'current_address' => trim((string) ($data['currentAddress'] ?? $data['current_address'] ?? $fallback['current_address'] ?? '')) ?: null,
            'education' => trim((string) ($data['educationLevel'] ?? $data['education_level'] ?? $fallback['education_level'] ?? '')) ?: null,
            'marital' => trim((string) ($data['maritalStatus'] ?? $data['marital_status'] ?? $fallback['marital_status'] ?? '')) ?: null,
            'life' => $this->life($data['status'] ?? $data['life_status'] ?? $fallback['life_status'] ?? 'ALIVE'),
            'presence' => $this->presence($data['presenceStatus'] ?? $data['presence_status'] ?? $fallback['presence_status'] ?? 'AT_HOME'),
            'user' => $userId,
        ];
    }

    private function ensureUniqueIdentity(?string $identity, ?int $ignoreId = null): void
    {
        if (!$identity) return;
        $params = ['identity' => $identity];
        $sql = 'SELECT id FROM citizens WHERE identity_number=:identity AND status <> "DELETED"';
        if ($ignoreId) { $sql .= ' AND id <> :id'; $params['id'] = $ignoreId; }
        if ($this->fetchOne($sql, $params)) throw new \RuntimeException('CCCD đã tồn tại');
    }

    private function nextCode(int $householdId): string
    {
        $household = $this->fetchOne('SELECT household_code FROM households WHERE id=:id', ['id' => $householdId]);
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($household['household_code'] ?? 'NK')));
        $count = (int) $this->fetchOne('SELECT COUNT(*) AS total FROM citizens WHERE household_id=:id', ['id' => $householdId])['total'] + 1;
        do { $code = $prefix . '-NK' . str_pad((string) $count++, 3, '0', STR_PAD_LEFT); }
        while ($this->fetchOne('SELECT id FROM citizens WHERE citizen_code=:code', ['code' => $code]));
        return $code;
    }

    private function syncHouseholdHead(int $householdId): void
    {
        $head = $this->fetchOne('SELECT id, full_name FROM citizens WHERE household_id=:household_id AND relationship="Chủ hộ" AND status <> "DELETED" ORDER BY id LIMIT 1', ['household_id' => $householdId]);
        $this->execute('UPDATE households SET head_citizen_id=:head_id, head_citizen_name=:head_name WHERE id=:household_id', ['household_id' => $householdId, 'head_id' => $head['id'] ?? null, 'head_name' => $head['full_name'] ?? null]);
    }

    private function residency(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['temporary','temporary_residence','tạm trú','tam tru'], true) ? 'TEMPORARY' : 'PERMANENT'; }
    private function presence(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['away','đi vắng','di vang','tam vang','tạm vắng'], true) ? 'AWAY' : 'AT_HOME'; }
    private function life(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['deceased','dead','đã chết','da chet'], true) ? 'DECEASED' : 'ALIVE'; }
}
