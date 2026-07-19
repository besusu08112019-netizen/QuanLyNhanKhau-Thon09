<?php

namespace App\Models;

use App\Core\BaseModel;

final class Citizen extends BaseModel
{
    private bool $healthInsuranceSchemaEnsured = false;
    private ?PopulationStatistics $statistics = null;
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

    private const HEALTH_INSURANCE_FIELDS = [
        'has_health_insurance' => 'BHYT',
    ];

    private const HEALTH_INSURANCE_DETAIL_COLUMNS = ['health_insurance_number','health_insurance_group','health_insurance_start_date','health_insurance_end_date','health_insurance_facility'];

    private const HEALTH_INSURANCE_GROUPS = [
        'Hộ gia đình', 'Người nghèo', 'Cận nghèo', 'Trẻ em dưới 6 tuổi', 'Học sinh - Sinh viên',
        'Người lao động', 'Người hưởng lương hưu', 'Người có công', 'Người cao tuổi', 'Khác',
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
        return self::POLITICAL_FIELDS + self::POLICY_FIELDS + self::HEALTH_INSURANCE_FIELDS + self::LABOR_FIELDS;
    }

    public function paginate(array $filters): array
    {
        $this->ensureHealthInsuranceSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$sqlWhere, $params] = $this->where($filters);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $sqlWhere", $params)['total'];
        $baseColumns = [
            'c.id', 'c.citizen_code', 'c.household_id', 'c.full_name', 'c.gender', 'c.date_of_birth',
            'c.identity_number', 'c.relationship', 'c.ethnicity', 'c.religion', 'c.occupation', 'c.phone',
            'c.residency_status', 'c.current_address', 'c.education_level', 'c.marital_status',
            'c.life_status', 'c.presence_status', 'c.status',
        ];
        foreach ($this->activeExtendedColumns() as $column) {
            $baseColumns[] = 'c.' . $column;
        }
        foreach ($this->activeHealthInsuranceColumns() as $column) {
            $baseColumns[] = 'c.' . $column;
        }
        $baseColumns[] = 'h.household_code';
        $baseColumns[] = 'h.address AS household_address';
        $baseColumns[] = 'h.head_citizen_name';
        $items = $this->fetchAll('SELECT ' . implode(', ', $baseColumns) . " FROM citizens c INNER JOIN households h ON h.id=c.household_id $sqlWhere ORDER BY h.household_code, CASE WHEN c.relationship='Chủ hộ' THEN 0 ELSE 1 END, c.full_name LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function findByIdentity(string $identity): ?array
    {
        $this->ensureHealthInsuranceSchema();
        $identity = trim($identity);
        if ($identity === '') return null;
        return $this->fetchOne('SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.identity_number=:identity AND c.status <> "DELETED"', ['identity' => $identity]);
    }

    public function find(int $id): ?array
    {
        $this->ensureHealthInsuranceSchema();
        return $this->fetchOne('SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name, COALESCE(v.total_members,0) AS member_count_real, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count, NULL AS birth_place, NULL AS hometown, NULL AS workplace, NULL AS note, NULL AS photo_url, NULLIF(c.father_name, "") AS father_display_name, NULLIF(c.mother_name, "") AS mother_display_name FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN v_household_member_counts v ON v.household_id=h.id WHERE c.id=:id AND c.status <> "DELETED"', ['id' => $id]);
    }

    public function create(array $data, int $userId): array
    {
        $this->ensureHealthInsuranceSchema();
        $params = $this->params($data, $userId);
        $params['code'] = $this->nextCode((int) $params['household_id']);
        $this->ensureUniqueIdentity($params['identity']);
        $this->ensureSingleHead((int) $params['household_id'], null, $params['relationship']);
        $columns = ['citizen_code','household_id','full_name','gender','date_of_birth','identity_number','identity_issue_date','identity_issue_place','relationship','ethnicity','religion','occupation','father_name','mother_name','phone','residency_status','current_address','education_level','marital_status','life_status','presence_status','status','created_by'];
        $values = [':code',':household_id',':full_name',':gender',':dob',':identity',':issue_date',':issue_place',':relationship',':ethnicity',':religion',':occupation',':father_name',':mother_name',':phone',':residency',':current_address',':education',':marital',':life',':presence','"ACTIVE"',':user'];
        foreach ($this->activeExtendedColumns() as $column) { $columns[] = $column; $values[] = ':' . $column; }
        foreach ($this->activeHealthInsuranceColumns() as $column) { $columns[] = $column; $values[] = ':' . $column; }
        $id = $this->insert('INSERT INTO citizens (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')', $params);
        $this->syncHouseholdHead((int) $params['household_id']);
        return $this->find($id);
    }

    public function update(int $id, array $data, int $userId): array
    {
        $this->ensureHealthInsuranceSchema();
        $before = $this->find($id);
        if (!$before) throw new \RuntimeException('Không tìm thấy nhân khẩu');
        $params = $this->params($data, $userId, $before); $params['id'] = $id;
        $params['code'] = (string) $before['citizen_code'];
        $this->ensureUniqueIdentity($params['identity'], $id);
        $this->ensureSingleHead((int) $params['household_id'], $id, $params['relationship']);
        $sets = ['citizen_code=:code','household_id=:household_id','full_name=:full_name','gender=:gender','date_of_birth=:dob','identity_number=:identity','identity_issue_date=:issue_date','identity_issue_place=:issue_place','relationship=:relationship','ethnicity=:ethnicity','religion=:religion','occupation=:occupation','father_name=:father_name','mother_name=:mother_name','phone=:phone','residency_status=:residency','current_address=:current_address','education_level=:education','marital_status=:marital','life_status=:life','presence_status=:presence','updated_by=:user'];
        foreach ($this->activeExtendedColumns() as $column) $sets[] = $column . '=:' . $column;
        foreach ($this->activeHealthInsuranceColumns() as $column) $sets[] = $column . '=:' . $column;
        $this->execute('UPDATE citizens SET ' . implode(',', $sets) . ' WHERE id=:id', $params);
        $this->syncHouseholdHead((int) $before['household_id']);
        $this->syncHouseholdHead((int) $params['household_id']);
        return $this->find($id);
    }

    public function softDelete(int $id, int $userId): void
    {
        $person = $this->find($id);
        if (!$person) throw new \RuntimeException('Không tìm thấy nhân khẩu');
        $activeMovements = (int) $this->fetchOne('SELECT COUNT(*) AS total FROM movements WHERE citizen_id = :id AND status <> "DELETED"', ['id' => $id])['total'];
        if ($activeMovements > 0) throw new \RuntimeException('Nhân khẩu đang có dữ liệu biến động liên quan. Vui lòng xử lý dữ liệu liên kết trước khi xóa.');
        $this->execute('UPDATE citizens SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
        $this->syncHouseholdHead((int) $person['household_id']);
    }

    public function bulkSoftDelete(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (!$ids) throw new \RuntimeException('Chưa chọn nhân khẩu cần xóa');
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

    public function restore(int $id, int $userId): void
    {
        $this->execute('UPDATE citizens SET status="ACTIVE", deleted_at=NULL, deleted_by=NULL, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
        $person = $this->find($id);
        if ($person) $this->syncHouseholdHead((int) $person['household_id']);
    }

    private function where(array $filters): array
    {
        $where = [$this->statistics()->citizenCondition('c'), $this->statistics()->householdCondition('h')]; $params = [];
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
        if (!empty($filters['maritalStatus'])) { $where[] = 'c.marital_status = :marital_status'; $params['marital_status'] = $filters['maritalStatus']; }
        if (!empty($filters['educationLevel'])) { $where[] = 'c.education_level = :education_level'; $params['education_level'] = $filters['educationLevel']; }
        if (!empty($filters['workplace']) && $this->columnExists('citizens', 'workplace')) { $where[] = 'c.workplace LIKE :workplace'; $params['workplace'] = '%' . $filters['workplace'] . '%'; }
        if (!empty($filters['nationality']) && $this->columnExists('citizens', 'nationality')) { $where[] = 'c.nationality LIKE :nationality'; $params['nationality'] = '%' . $filters['nationality'] . '%'; }
        if (!empty($filters['bloodType']) && $this->columnExists('citizens', 'blood_type')) { $where[] = 'c.blood_type = :blood_type'; $params['blood_type'] = $filters['bloodType']; }
        if (!empty($filters['ageFrom'])) { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= :age_from'; $params['age_from'] = (int) $filters['ageFrom']; }
        if (!empty($filters['ageTo'])) { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= :age_to'; $params['age_to'] = (int) $filters['ageTo']; }
        if (!empty($filters['search'])) {
            $mapped = $this->searchFlag((string) $filters['search']);
            if ($mapped && $this->columnExists('citizens', $mapped)) {
                $where[] = 'c.' . $mapped . ' = 1';
            } else {
                $q = '%' . $filters['search'] . '%';
                $searchColumns = ['c.citizen_code LIKE :q_code', 'c.full_name LIKE :q_name', 'c.identity_number LIKE :q_identity'];
                $params['q_code'] = $q; $params['q_name'] = $q; $params['q_identity'] = $q;
                if ($this->columnExists('citizens', 'personal_id')) { $searchColumns[] = 'c.personal_id LIKE :q_personal_id'; $params['q_personal_id'] = $q; }
                if ($this->columnExists('citizens', 'national_id')) { $searchColumns[] = 'c.national_id LIKE :q_national_id'; $params['q_national_id'] = $q; }
                $where[] = '(' . implode(' OR ', $searchColumns) . ')';
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
            'father_name' => $this->nullableString($data['fatherName'] ?? $data['father_name'] ?? $fallback['father_name'] ?? null, 255),
            'mother_name' => $this->nullableString($data['motherName'] ?? $data['mother_name'] ?? $fallback['mother_name'] ?? null, 255),
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
        $this->applyHealthInsuranceParams($params, $data, $fallback);
        return $params;
    }

    private function activeExtendedColumns(): array
    {
        $columns = $this->existingColumns('citizens', array_keys(self::extendedFields()));
        if ($this->healthInsuranceSchemaEnsured && !in_array('has_health_insurance', $columns, true)) $columns[] = 'has_health_insurance';
        return $columns;
    }

    private function statistics(): PopulationStatistics
    {
        return $this->statistics ??= new PopulationStatistics();
    }

    private function activeHealthInsuranceColumns(): array
    {
        return $this->healthInsuranceSchemaEnsured ? self::HEALTH_INSURANCE_DETAIL_COLUMNS : $this->existingColumns('citizens', self::HEALTH_INSURANCE_DETAIL_COLUMNS);
    }

    public function ensureHealthInsuranceSchema(): void
    {
        if ($this->healthInsuranceSchemaEnsured) return;
        $columns = [
            'father_name' => 'VARCHAR(255) NULL',
            'mother_name' => 'VARCHAR(255) NULL',
            'has_health_insurance' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'health_insurance_number' => 'VARCHAR(20) NULL',
            'health_insurance_group' => 'VARCHAR(100) NULL',
            'health_insurance_start_date' => 'DATE NULL',
            'health_insurance_end_date' => 'DATE NULL',
            'health_insurance_facility' => 'VARCHAR(255) NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('citizens', $column)) {
                $this->execute('ALTER TABLE citizens ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        $this->createHealthInsuranceIndexIfMissing();
        $this->healthInsuranceSchemaEnsured = true;
    }

    private function createHealthInsuranceIndexIfMissing(): void
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index', ['table' => 'citizens', 'index' => 'idx_citizens_health_insurance']);
        if ((int) ($row['total'] ?? 0) === 0) {
            $this->execute('CREATE INDEX idx_citizens_health_insurance ON citizens (has_health_insurance, health_insurance_end_date)');
        }
    }

    private function applyHealthInsuranceParams(array &$params, array $data, ?array $fallback): void
    {
        $active = $this->activeHealthInsuranceColumns();
        if (!$active) return;
        $has = $this->boolValue($data['has_health_insurance'] ?? $data['hasHealthInsurance'] ?? $data['health_insurance'] ?? $data['healthInsurance'] ?? $fallback['has_health_insurance'] ?? $fallback['health_insurance'] ?? 0);
        $params['has_health_insurance'] = $has;
        $params['health_insurance_number'] = $has ? $this->nullableString($data['health_insurance_number'] ?? $data['healthInsuranceNumber'] ?? $fallback['health_insurance_number'] ?? null, 20) : null;
        $params['health_insurance_group'] = $has ? $this->healthInsuranceGroup($data['health_insurance_group'] ?? $data['healthInsuranceGroup'] ?? $fallback['health_insurance_group'] ?? null) : null;
        $params['health_insurance_start_date'] = $has ? $this->nullableDate($data['health_insurance_start_date'] ?? $data['healthInsuranceStartDate'] ?? $fallback['health_insurance_start_date'] ?? null) : null;
        $params['health_insurance_end_date'] = $has ? $this->nullableDate($data['health_insurance_end_date'] ?? $data['healthInsuranceEndDate'] ?? $fallback['health_insurance_end_date'] ?? null) : null;
        $params['health_insurance_facility'] = $has ? $this->nullableString($data['health_insurance_facility'] ?? $data['healthInsuranceFacility'] ?? $fallback['health_insurance_facility'] ?? null, 255) : null;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        return mb_substr($text, 0, $max);
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) throw new \RuntimeException('Ngày BHYT không hợp lệ');
        return $text;
    }

    private function healthInsuranceGroup(mixed $value): ?string
    {
        $text = $this->nullableString($value, 100);
        if ($text === null) return null;
        return in_array($text, self::HEALTH_INSURANCE_GROUPS, true) ? $text : mb_substr($text, 0, 100);
    }

    private function boolValue(mixed $value): int { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['1','true','yes','co','có','x'], true) ? 1 : 0; }
    private function camel(string $column): string { return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $column); }
    private function searchFlag(string $search): ?string
    {
        $text = $this->normalize($search);
        $map = [
            'dang vien' => 'party_member', 'doan vien' => 'youth_union_member', 'hoi phu nu' => 'women_union_member', 'nong dan' => 'farmers_union_member', 'cuu chien binh' => 'veterans_union_member', 'nguoi cao tuoi' => 'elderly_union_member',
            'nguoi co cong' => 'meritorious_person', 'than nhan liet si' => 'martyr_relative', 'thuong binh' => 'wounded_soldier', 'benh binh' => 'sick_soldier', 'khuyet tat' => 'disabled_person', 'bao tro xa hoi' => 'social_assistance', 'bhyt' => 'has_health_insurance', 'bao hiem y te' => 'has_health_insurance',
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
        $prefix = 'H09-NK';
        $count = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM citizens WHERE citizen_code LIKE :prefix', ['prefix' => $prefix . '%'])['total'] ?? 0) + 1;
        do { $code = $prefix . str_pad((string) $count++, 5, '0', STR_PAD_LEFT); }
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
