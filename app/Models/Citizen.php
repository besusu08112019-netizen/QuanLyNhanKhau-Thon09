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
        $id = $this->insert('INSERT INTO citizens (citizen_code, household_id, full_name, gender, date_of_birth, identity_number, identity_issue_date, identity_issue_place, relationship, ethnicity, religion, occupation, phone, residency_status, current_address, education_level, marital_status, life_status, presence_status, status, created_by) VALUES (:code,:household_id,:full_name,:gender,:dob,:identity,:issue_date,:issue_place,:relationship,:ethnicity,:religion,:occupation,:phone,:residency,:current_address,:education,:marital,:life,:presence,"ACTIVE",:user)', $this->params($data, $userId));
        $this->syncHead($id);
        return $this->find($id);
    }

    public function update(int $id, array $data, int $userId): array
    {
        $params = $this->params($data, $userId); $params['id'] = $id;
        $this->execute('UPDATE citizens SET citizen_code=:code, household_id=:household_id, full_name=:full_name, gender=:gender, date_of_birth=:dob, identity_number=:identity, identity_issue_date=:issue_date, identity_issue_place=:issue_place, relationship=:relationship, ethnicity=:ethnicity, religion=:religion, occupation=:occupation, phone=:phone, residency_status=:residency, current_address=:current_address, education_level=:education, marital_status=:marital, life_status=:life, presence_status=:presence, updated_by=:user WHERE id=:id', $params);
        $this->syncHead($id);
        return $this->find($id);
    }

    public function softDelete(int $id, int $userId): void { $this->execute('UPDATE citizens SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]); }
    public function restore(int $id, int $userId): void { $this->execute('UPDATE citizens SET status="ACTIVE", deleted_at=NULL, deleted_by=NULL, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]); }

    private function params(array $data, int $userId): array
    {
        $household = new Household();
        $householdRow = is_numeric($data['householdId'] ?? '') ? $household->find((int) $data['householdId']) : $household->findByCode((string) ($data['householdId'] ?? $data['householdCode'] ?? ''));
        if (!$householdRow) throw new \RuntimeException('Không tìm thấy Mã hộ');
        return [
            'code' => strtoupper(trim((string) ($data['citizenCode'] ?? $data['citizen_code'] ?? ''))),
            'household_id' => (int) $householdRow['id'],
            'full_name' => trim((string) ($data['fullName'] ?? $data['full_name'] ?? '')),
            'gender' => $data['gender'] ?? 'Khác',
            'dob' => $data['dateOfBirth'] ?? $data['date_of_birth'] ?? null,
            'identity' => trim((string) ($data['identityNumber'] ?? $data['identity_number'] ?? '')) ?: null,
            'issue_date' => $data['identityIssueDate'] ?? $data['identity_issue_date'] ?? null,
            'issue_place' => trim((string) ($data['identityIssuePlace'] ?? $data['identity_issue_place'] ?? '')) ?: null,
            'relationship' => trim((string) ($data['relationship'] ?? 'Khác')),
            'ethnicity' => trim((string) ($data['ethnicity'] ?? '')) ?: null,
            'religion' => trim((string) ($data['religion'] ?? '')) ?: null,
            'occupation' => trim((string) ($data['occupation'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'residency' => $this->residency($data['permanentAddress'] ?? $data['residency_status'] ?? 'PERMANENT'),
            'current_address' => trim((string) ($data['currentAddress'] ?? $data['current_address'] ?? '')) ?: null,
            'education' => trim((string) ($data['educationLevel'] ?? $data['education_level'] ?? '')) ?: null,
            'marital' => trim((string) ($data['maritalStatus'] ?? $data['marital_status'] ?? '')) ?: null,
            'life' => $this->life($data['status'] ?? $data['life_status'] ?? 'ALIVE'),
            'presence' => $this->presence($data['presenceStatus'] ?? $data['presence_status'] ?? 'AT_HOME'),
            'user' => $userId,
        ];
    }

    private function residency(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['temporary','temporary_residence','tạm trú','tam tru','TEMPORARY'], true) ? 'TEMPORARY' : 'PERMANENT'; }
    private function presence(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['away','đi vắng','di vang','tam vang','tạm vắng','AWAY'], true) ? 'AWAY' : 'AT_HOME'; }
    private function life(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['deceased','dead','đã chết','da chet','DECEASED'], true) ? 'DECEASED' : 'ALIVE'; }

    private function syncHead(int $id): void
    {
        $person = $this->find($id);
        if ($person && $person['relationship'] === 'Chủ hộ') {
            $this->execute('UPDATE households SET head_citizen_id=:person_id, head_citizen_name=:name WHERE id=:household_id', ['person_id' => $id, 'name' => $person['full_name'], 'household_id' => $person['household_id']]);
        }
    }
}
