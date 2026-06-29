<?php

namespace App\Models;

use App\Core\BaseModel;

final class Citizen extends BaseModel
{
    private const POLITICAL_FIELDS = [
        'party_member' => 'Đảng viên',
        'youth_union_member' => 'Đoàn viên Thanh niên',
        'women_union_member' => 'Hội viên Hội Phụ nữ',
        'farmers_union_member' => 'Hội viên Hội Nông dân',
        'veterans_union_member' => 'Hội viên Hội Cựu chiến binh',
        'elderly_union_member' => 'Hội viên Hội Người cao tuổi',
    ];

    private const POLICY_FIELDS = [
        'meritorious_person' => 'Người có công',
        'martyr_relative' => 'Thân nhân liệt sĩ',
        'wounded_soldier' => 'Thương binh',
        'sick_soldier' => 'Bệnh binh',
        'disabled_person' => 'Người khuyết tật',
        'social_assistance' => 'Bảo trợ xã hội',
    ];

    private const LABOR_FIELDS = [
        'employed' => 'Có việc làm',
        'unemployed' => 'Thất nghiệp',
        'freelance_labor' => 'Lao động tự do',
        'out_province_labor' => 'Lao động ngoài tỉnh',
        'foreign_labor' => 'Lao động nước ngoài',
        'pupil' => 'Học sinh',
        'student' => 'Sinh viên',
        'retired' => 'Nghỉ hưu',
    ];

    public static function extendedFields(): array
    {
        return self::POLITICAL_FIELDS + self::POLICY_FIELDS + self::LABOR_FIELDS;
    }

    public function paginate(array $filters): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$sqlWhere, $params] = $this->where($filters);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name, NULL AS birth_place, NULL AS hometown, NULL AS workplace, NULL AS note, NULL AS photo_url FROM citizens c INNER JOIN households h ON h.id=c.household_id $sqlWhere ORDER BY h.household_code, CASE WHEN c.relationship='Chủ hộ' THEN 0 ELSE 1 END, c.full_name LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function findByIdentity(string $identity): ?array
    {
        $identity = trim($identity);
        if ($identity === '') return null;
        return $this->fetchOne('SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.identity_number=:identity AND c.status <> "DELETED"', ['identity' => $identity]);
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name, NULL AS birth_place, NULL AS hometown, NULL AS workplace, NULL AS note, NULL AS photo_url FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.id=:id AND c.status <> "DELETED"', ['id' => $id]);
    }

    public function create(array $data, int $userId): array
    {
        $params = $this->params($data, $userId);
        if ($params['code'] === '') $params['code'] = $this->nextCode((int) $params['household_id']);
        $this->ensureUniqueIdentity($params['identity']);
        $this->ensureSingleHead((int) $params['household_id'], null, $params['relationship']);
        $columns = ['citizen_code','household_id','full_name','gender','date_of_birth','identity_number','identity_issue_date','identity_issue_place','relationship','ethnicity','religion','occupation','phone','residency_status','current_address','education_level','marital_status','life_status','presence_status','status','created_by'];
        $values = [':code',':household_id',':full_name',':gender',':dob',':identity',':issue_date',':issue_place',':relationship',':ethnicity',':religion',':occupation',':phone',':residency',':current_address',':education',':marital',':life',':presence','"ACTIVE"',':user'];
        foreach ($this->activeExtendedColumns() as $column) { $columns[] = $column; $values[] = ':' . $column; }
        $id = $this->insert('INSERT INTO citizens (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')', $params);
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
        $this->ensureSingleHead((int) $params['household_id'], $id, $params['relationship']);
        $sets = ['citizen_code=:code','household_id=:household_id','full_name=:full_name','gender=:gender','date_of_birth=:dob','identity_number=:identity','identity_issue_date=:issue_date','identity_issue_place=:issue_place','relationship=:relationship','ethnicity=:ethnicity','religion=:religion','occupation=:occupation','phone=:phone','residency_status=:residency','current_address=:current_address','education_level=:education','marital_status=:marital','life_status=:life','presence_status=:presence','updated_by=:user'];
        foreach ($this->activeExtendedColumns() as $column) $sets[] = $column . '=:' . $column;
        $this->execute('UPDATE citizens SET ' . implode(',', $sets) . ' WHERE id=:id', $params);
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

    private function where(array $filters): array
    {
        $where = ['c.status <> "DELETED"']; $params = [];
        if (!empty($filters['status'])) { $where[] = 'c.life_status = :life_status'; $params['life_status'] = $filters['status']; }
        if (!empty($filters['presenceStatus'])) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if (!empty($filters['residencyStatus'])) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if (!empty($filters['householdId'])) { $where[] = '(h.household_code = :household OR c.household_id = :household_id)'; $params['household'] = $filters['householdId']; $params['household_id'] = (int) $filters['householdId']; }
        $category = $this->categoryKey($filters['household_type'] ?? $filters['householdType'] ?? $filters['category'] ?? '');
        if ($category !== '') $this->addCategoryWhere($where, $params, $category);
        foreach ($this->activeExtendedColumns() as $column) {
            $value = $filters[$column] ?? $filters[$this->camel($column)] ?? null;
            if ($value !== null && $value !== '') { $where[] = 'c.' . $column . ' = :' . $column; $params[$column] = $this->boolValue($value); }
        }
        if (!empty($filters['gender'])) { $where[] = 'c.gender = :gender'; $params['gender'] = $filters['gender']; }
        if (!empty($filters['ethnicity'])) { $where[] = 'c.ethnicity LIKE :ethnicity'; $params['ethnicity'] = '%' . $filters['ethnicity'] . '%'; }
        if (!empty($filters['religion'])) { $where[] = 'c.religion LIKE :religion'; $params['religion'] = '%' . $filters['religion'] . '%'; }
        if (!empty($filters['occupation'])) { $where[] = 'c.occupation LIKE :occupation'; $params['occupation'] = '%' . $filters['occupation'] . '%'; }
        if (!empty($filters['ageFrom'])) { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= :age_from'; $params['age_from'] = (int) $filters['ageFrom']; }
        if (!empty($filters['ageTo'])) { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= :age_to'; $params['age_to'] = (int) $filters['ageTo']; }
        if (!empty($filters['search'])) {
            $mapped = $this->searchFlag((string) $filters['search']);
            if ($mapped && $this->columnExists('citizens', $mapped)) {
                $where[] = 'c.' . $mapped . ' = 1';
            } else {
                $q = '%' . $filters['search'] . '%';
                $where[] = '(c.citizen_code LIKE :q_code OR c.full_name LIKE :q_name OR c.identity_number LIKE :q_identity OR c.phone LIKE :q_phone OR h.household_code LIKE :q_household OR h.head_citizen_name LIKE :q_head OR c.occupation LIKE :q_occupation OR c.ethnicity LIKE :q_ethnicity OR c.religion LIKE :q_religion)';
                $params['q_code'] = $q; $params['q_name'] = $q; $params['q_identity'] = $q; $params['q_phone'] = $q; $params['q_household'] = $q; $params['q_head'] = $q; $params['q_occupation'] = $q; $params['q_ethnicity'] = $q; $params['q_religion'] = $q;
            }
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
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
        $params = [
            'code' => strtoupper(trim((string) ($data['citizenCode'] ?? $data['citizen_code'] ?? $fallback['citizen_code'] ?? ''))),
            'household_id' => (int) $householdRow['id'],
            'full_name' => $fullName,
            'gender' => in_array(($data['gender'] ?? $fallback['gender'] ?? 'Khác'), ['Nam','Nữ','Khác'], true) ? ($data['gender'] ?? $fallback['gender'] ?? 'Khác') : 'Khác',
            'dob' => $dob,
            'identity' => trim((string) ($data['identityNumber'] ?? $data['identity_number'] ?? $fallback['identity_number'] ?? '')) ?: null,
            'issue_date' => $data['identityIssueDate'] ?? $data['identity_issue_date'] ?? $fallback['identity_issue_date'] ?? null,
            'issue_place' => trim((string) ($data['identityIssuePlace'] ?? $data['identity_issue_place'] ?? $fallback['identity_issue_place'] ?? '')) ?: null,
            'relationship' => $this->relationship($data['relationship'] ?? $data['memberType'] ?? $data['member_type'] ?? $fallback['relationship'] ?? 'Khác'),
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
        foreach ($this->activeExtendedColumns() as $column) $params[$column] = $this->boolValue($data[$column] ?? $data[$this->camel($column)] ?? $fallback[$column] ?? 0);
        return $params;
    }

    private function activeExtendedColumns(): array { return $this->existingColumns('citizens', array_keys(self::extendedFields())); }
    private function boolValue(mixed $value): int { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['1','true','yes','co','có','x'], true) ? 1 : 0; }
    private function camel(string $column): string { return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $column); }
    private function searchFlag(string $search): ?string
    {
        $text = $this->normalize($search);
        $map = [
            'dang vien' => 'party_member', 'doan vien' => 'youth_union_member', 'hoi phu nu' => 'women_union_member', 'nong dan' => 'farmers_union_member', 'cuu chien binh' => 'veterans_union_member', 'nguoi cao tuoi' => 'elderly_union_member',
            'nguoi co cong' => 'meritorious_person', 'than nhan liet si' => 'martyr_relative', 'thuong binh' => 'wounded_soldier', 'benh binh' => 'sick_soldier', 'khuyet tat' => 'disabled_person', 'bao tro xa hoi' => 'social_assistance',
            'co viec lam' => 'employed', 'that nghiep' => 'unemployed', 'lao dong tu do' => 'freelance_labor', 'ngoai tinh' => 'out_province_labor', 'nuoc ngoai' => 'foreign_labor', 'hoc sinh' => 'pupil', 'sinh vien' => 'student', 'nghi huu' => 'retired',
        ];
        foreach ($map as $needle => $column) if (str_contains($text, $needle)) return $column;
        return null;
    }
    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $from = ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ'];
        $to   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
        return trim(preg_replace('/\s+/', ' ', str_replace($from, $to, $value)));
    }

    private function addCategoryWhere(array &$where, array &$params, string $category): void
    {
        match ($category) {
            'poor' => $where[] = 'h.poor_household = 1',
            'near_poor' => $where[] = 'h.near_poor_household = 1',
            'meritorious' => $where[] = 'h.meritorious_family = 1',
            'normal' => $where[] = 'h.poor_household = 0 AND h.near_poor_household = 0 AND h.meritorious_family = 0 AND h.disabled_household = 0',
            'other' => $where[] = 'h.disabled_household = 1',
            'escaped_poverty', 'policy' => $this->addTextCategoryWhere($where, $params, $category),
            default => null,
        };
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category): void
    {
        $label = ['escaped_poverty' => 'Hộ mới thoát nghèo', 'policy' => 'Hộ chính sách'][$category] ?? $category;
        $where[] = '(h.note LIKE :household_category_label OR h.note LIKE :household_category_key)';
        $params['household_category_label'] = '%' . $label . '%';
        $params['household_category_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function categoryKey(mixed $value): string
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

    private function ensureSingleHead(int $householdId, ?int $ignoreId, string $relationship): void
    {
        if ($relationship !== 'Chủ hộ') return;
        $params = ['household_id' => $householdId];
        $sql = 'SELECT id, full_name FROM citizens WHERE household_id=:household_id AND relationship="Chủ hộ" AND status <> "DELETED"';
        if ($ignoreId) { $sql .= ' AND id <> :id'; $params['id'] = $ignoreId; }
        $head = $this->fetchOne($sql, $params);
        if ($head) throw new \RuntimeException('Hộ này đã có Chủ hộ: ' . $head['full_name']);
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

    private function relationship(mixed $value): string { $text = trim((string) $value); return $text === 'Chủ hộ' ? 'Chủ hộ' : ($text ?: 'Khác'); }
    private function residency(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['temporary','temporary_residence','tạm trú','tam tru'], true) ? 'TEMPORARY' : 'PERMANENT'; }
    private function presence(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['away','đi vắng','di vang','tam vang','tạm vắng'], true) ? 'AWAY' : 'AT_HOME'; }
    private function life(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['deceased','dead','đã chết','da chet'], true) ? 'DECEASED' : 'ALIVE'; }
}
