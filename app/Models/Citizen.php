<?php

namespace App\Models;

use App\Config\CitizenPolicyDefaults;
use App\Core\BaseModel;
use App\Models\PolicyAlert;
use App\Policies\AgePolicy;
use App\Policies\HouseholdRelationPolicy;
use App\Policies\InsurancePolicy;
use App\Services\StudentStatusService;

final class Citizen extends BaseModel
{
    private bool $healthInsuranceSchemaEnsured = false;
    private ?PopulationStatistics $statistics = null;
    private const POLITICAL_FIELDS = [
        'party_member' => 'Äáº£ng viÃªn',
        'youth_union_member' => 'ÄoÃ n viÃªn Thanh niÃªn',
        'women_union_member' => 'Há»™i viÃªn Há»™i Phá»¥ ná»¯',
        'farmers_union_member' => 'Há»™i viÃªn Há»™i NÃ´ng dÃ¢n',
        'veterans_union_member' => 'Há»™i viÃªn Há»™i Cá»±u chiáº¿n binh',
        'elderly_union_member' => 'Há»™i viÃªn Há»™i NgÆ°á»i cao tuá»•i',
    ];

    private const POLICY_FIELDS = [
        'martyr_relative' => 'ThÃ¢n nhÃ¢n liá»‡t sÄ©',
        'wounded_soldier' => 'ThÆ°Æ¡ng binh',
        'sick_soldier' => 'Bá»‡nh binh',
        'chemical_warfare_victim' => 'NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ nhiá»…m cháº¥t Ä‘á»™c hÃ³a há»c',
        'imprisoned_resistance_activist' => 'NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ Ä‘á»‹ch báº¯t tÃ¹, Ä‘Ã y',
        'youth_volunteer' => 'Thanh niÃªn xung phong',
        'resistance_hero' => 'Anh hÃ¹ng LLVTND / Anh hÃ¹ng Lao Ä‘á»™ng thá»i ká»³ khÃ¡ng chiáº¿n',
        'revolutionary_activist' => 'NgÆ°á»i hoáº¡t Ä‘á»™ng cÃ¡ch máº¡ng',
    ];

    private const SOCIAL_SECURITY_FIELDS = [
        'disabled_person' => 'NgÆ°á»i khuyáº¿t táº­t',
        'social_assistance' => 'Äang hÆ°á»Ÿng trá»£ cáº¥p xÃ£ há»™i',
    ];

    private const HEALTH_INSURANCE_FIELDS = [
        'has_health_insurance' => 'BHYT',
    ];

    private const HEALTH_INSURANCE_DETAIL_COLUMNS = ['health_insurance_number','health_insurance_group','health_insurance_start_date','health_insurance_end_date','health_insurance_facility'];

    private const HEALTH_INSURANCE_GROUPS = [
        'Há»™ gia Ä‘Ã¬nh', 'NgÆ°á»i nghÃ¨o', 'Cáº­n nghÃ¨o', 'Tráº» em dÆ°á»›i 6 tuá»•i', 'Há»c sinh - Sinh viÃªn',
        'NgÆ°á»i lao Ä‘á»™ng', 'NgÆ°á»i hÆ°á»Ÿng lÆ°Æ¡ng hÆ°u', 'NgÆ°á»i cÃ³ cÃ´ng', 'NgÆ°á»i cao tuá»•i', 'KhÃ¡c',
    ];

    private const LABOR_FIELDS = [
        'employed' => 'CÃ³ viá»‡c lÃ m',
        'unemployed' => 'Tháº¥t nghiá»‡p',
        'freelance_labor' => 'Lao Ä‘á»™ng tá»± do',
        'out_province_labor' => 'Lao Ä‘á»™ng ngoÃ i tá»‰nh',
        'foreign_labor' => 'Lao Ä‘á»™ng nÆ°á»›c ngoÃ i',
        'not_attending_school' => 'ChÆ°a Ä‘i há»c',
        'pupil' => 'Há»c sinh',
        'student' => 'Sinh viÃªn',
        'retired' => 'Nghá»‰ hÆ°u',
    ];

    public static function extendedFields(): array
    {
        return self::POLITICAL_FIELDS + self::POLICY_FIELDS + self::SOCIAL_SECURITY_FIELDS + self::HEALTH_INSURANCE_FIELDS + self::LABOR_FIELDS;
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
        $items = $this->fetchAll('SELECT ' . implode(', ', $baseColumns) . " FROM citizens c INNER JOIN households h ON h.id=c.household_id $sqlWhere ORDER BY h.household_code, CASE WHEN c.relationship='Chá»§ há»™' THEN 0 ELSE 1 END, c.full_name LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated($items, $page, $pageSize, $total, ['metrics' => $this->statistics()->metrics()]);
    }

    public function findByIdentity(string $identity): ?array
    {
        $this->ensureHealthInsuranceSchema();
        $identity = trim($identity);
        if ($identity === '') return null;
        return $this->fetchOne('SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.identity_number=:identity AND c.status <> "DELETED" AND ' . $this->tenantWhere('c', 'citizens'), $this->withTenant(['identity' => $identity]));
    }

    public function find(int $id): ?array
    {
        $this->ensureHealthInsuranceSchema();
        return $this->fetchOne('SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name, COALESCE(v.total_members,0) AS member_count_real, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count, NULL AS birth_place, NULL AS hometown, NULL AS workplace, NULL AS note, NULL AS photo_url, NULLIF(c.father_name, "") AS father_display_name, NULLIF(c.mother_name, "") AS mother_display_name FROM citizens c INNER JOIN households h ON h.id=c.household_id LEFT JOIN v_household_member_counts v ON v.household_id=h.id WHERE c.id=:id AND c.status <> "DELETED" AND ' . $this->tenantWhere('c', 'citizens'), $this->withTenant(['id' => $id]));
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
        $this->addTenantInsert('citizens', $columns, $params);
        if (in_array('village_id', $columns, true)) $values[] = ':village_id';
        $id = $this->insert('INSERT INTO citizens (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')', $params);
        $this->syncHouseholdHead((int) $params['household_id']);
        return $this->find($id);
    }

    public function update(int $id, array $data, int $userId): array
    {
        $this->ensureHealthInsuranceSchema();
        $before = $this->find($id);
        if (!$before) throw new \RuntimeException('KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u');
        $params = $this->params($data, $userId, $before); $params['id'] = $id;
        $params['code'] = (string) $before['citizen_code'];
        $this->ensureUniqueIdentity($params['identity'], $id);
        $this->ensureSingleHead((int) $params['household_id'], $id, $params['relationship']);
        $sets = ['citizen_code=:code','household_id=:household_id','full_name=:full_name','gender=:gender','date_of_birth=:dob','identity_number=:identity','identity_issue_date=:issue_date','identity_issue_place=:issue_place','relationship=:relationship','ethnicity=:ethnicity','religion=:religion','occupation=:occupation','father_name=:father_name','mother_name=:mother_name','phone=:phone','residency_status=:residency','current_address=:current_address','education_level=:education','marital_status=:marital','life_status=:life','presence_status=:presence','updated_by=:user'];
        foreach ($this->activeExtendedColumns() as $column) $sets[] = $column . '=:' . $column;
        foreach ($this->activeHealthInsuranceColumns() as $column) $sets[] = $column . '=:' . $column;
        $this->execute('UPDATE citizens SET ' . implode(',', $sets) . ' WHERE id=:id AND ' . $this->tenantWhere('citizens'), $this->withTenant($params));
        $this->syncHouseholdHead((int) $before['household_id']);
        $this->syncHouseholdHead((int) $params['household_id']);
        return $this->find($id);
    }

    public function softDelete(int $id, int $userId): void
    {
        $person = $this->find($id);
        if (!$person) throw new \RuntimeException('KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u');
        $activeMovements = (int) $this->fetchOne('SELECT COUNT(*) AS total FROM movements WHERE citizen_id = :id AND status <> "DELETED" AND ' . $this->tenantWhere('movements'), $this->withTenant(['id' => $id]))['total'];
        if ($activeMovements > 0) throw new \RuntimeException('NhÃ¢n kháº©u Ä‘ang cÃ³ dá»¯ liá»‡u biáº¿n Ä‘á»™ng liÃªn quan. Vui lÃ²ng xá»­ lÃ½ dá»¯ liá»‡u liÃªn káº¿t trÆ°á»›c khi xÃ³a.');
        $this->execute('UPDATE citizens SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id AND ' . $this->tenantWhere('citizens'), $this->withTenant(['id' => $id, 'user' => $userId]));
        $this->syncHouseholdHead((int) $person['household_id']);
    }

    public function bulkSoftDelete(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (!$ids) throw new \RuntimeException('ChÆ°a chá»n nhÃ¢n kháº©u cáº§n xÃ³a');
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
        $this->execute('UPDATE citizens SET status="ACTIVE", deleted_at=NULL, deleted_by=NULL, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('citizens'), $this->withTenant(['id' => $id, 'user' => $userId]));
        $person = $this->find($id);
        if ($person) $this->syncHouseholdHead((int) $person['household_id']);
    }

    private function where(array $filters): array
    {
        $isTemporaryAbsence = ($filters['presenceStatus'] ?? '') === 'AWAY';
        $where = $isTemporaryAbsence
            ? [$this->statistics()->temporaryAbsenceCitizenCondition('c'), $this->statistics()->temporaryAbsenceHouseholdCondition('h')]
            : [$this->statistics()->citizenCondition('c'), $this->statistics()->householdCondition('h')];
        $params = $this->withTenant();
        $where[] = $this->tenantWhere('c', 'citizens');
        $where[] = $this->tenantWhere('h', 'households');
        if (!empty($filters['status'])) { $where[] = 'c.life_status = :life_status'; $params['life_status'] = $filters['status']; }
        if (!empty($filters['presenceStatus']) && !$isTemporaryAbsence) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if (!empty($filters['residencyStatus'])) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if (!empty($filters['householdId'])) { $where[] = '(h.household_code = :household OR c.household_id = :household_id)'; $params['household'] = $filters['householdId']; $params['household_id'] = (int) $filters['householdId']; }
        $category = $this->categoryKey($filters['household_type'] ?? $filters['householdType'] ?? $filters['category'] ?? '');
        if ($category !== '') $this->addCategoryWhere($where, $params, $category);
        $meritoriousFilter = $filters['meritorious_person'] ?? $filters['meritoriousPerson'] ?? null;
        if ($meritoriousFilter !== null && $meritoriousFilter !== '') {
            $where[] = $this->meritoriousPolicyCondition('c', $this->boolValue($meritoriousFilter) === 1);
        }
        foreach ($this->activeExtendedColumns() as $column) {
            $value = $filters[$column] ?? $filters[$this->camel($column)] ?? null;
            if ($value !== null && $value !== '') {
                if ($column === 'pupil') {
                    $where[] = ((int) $this->boolValue($value) === 1 ? '' : 'NOT ') . StudentStatusService::studentSql('c');
                } else {
                    $where[] = 'c.' . $column . ' = :' . $column; $params[$column] = $this->boolValue($value);
                }
            }
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
        if (!empty($filters['ageFrom'])) { $where[] = AgePolicy::ageSql('c') . ' >= :age_from'; $params['age_from'] = (int) $filters['ageFrom']; }
        if (!empty($filters['ageTo'])) { $where[] = AgePolicy::ageSql('c') . ' <= :age_to'; $params['age_to'] = (int) $filters['ageTo']; }
        if (!empty($filters['policyAlert'])) {
            $condition = PolicyAlert::filterCondition((string) $filters['policyAlert'], 'c');
            if ($condition) $where[] = $condition;
        }
        if (!empty($filters['search'])) {
            $mapped = $this->searchFlag((string) $filters['search']);
            if ($mapped === '__meritorious_policy__') {
                $where[] = $this->meritoriousPolicyCondition('c');
            } elseif ($mapped && $this->columnExists('citizens', $mapped)) {
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
        if (!$householdRow) throw new \RuntimeException('KhÃ´ng tÃ¬m tháº¥y MÃ£ há»™');
        $fullName = trim((string) ($data['fullName'] ?? $data['full_name'] ?? $fallback['full_name'] ?? ''));
        $dob = $data['dateOfBirth'] ?? $data['date_of_birth'] ?? $fallback['date_of_birth'] ?? null;
        if ($fullName === '') throw new \RuntimeException('Há» vÃ  tÃªn lÃ  báº¯t buá»™c');
        if (!$dob || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dob)) throw new \RuntimeException('NgÃ y sinh khÃ´ng há»£p lá»‡');
        $studentDefaults = $fallback === null ? StudentStatusService::defaultFieldsForDateOfBirth((string) $dob) : [];
        $occupationDefault = $fallback === null ? InsurancePolicy::defaultOccupationForDateOfBirth((string) $dob) : null;
        $params = [
            'code' => strtoupper(trim((string) ($data['citizenCode'] ?? $data['citizen_code'] ?? $fallback['citizen_code'] ?? ''))),
            'household_id' => (int) $householdRow['id'],
            'full_name' => $fullName,
            'gender' => in_array(($data['gender'] ?? $fallback['gender'] ?? 'KhÃ¡c'), ['Nam','Ná»¯','KhÃ¡c'], true) ? ($data['gender'] ?? $fallback['gender'] ?? 'KhÃ¡c') : 'KhÃ¡c',
            'dob' => $dob,
            'identity' => trim((string) ($data['identityNumber'] ?? $data['identity_number'] ?? $fallback['identity_number'] ?? '')) ?: null,
            'issue_date' => $data['identityIssueDate'] ?? $data['identity_issue_date'] ?? $fallback['identity_issue_date'] ?? null,
            'issue_place' => trim((string) ($data['identityIssuePlace'] ?? $data['identity_issue_place'] ?? $fallback['identity_issue_place'] ?? '')) ?: null,
            'relationship' => $this->relationship($data['relationship'] ?? $data['memberType'] ?? $data['member_type'] ?? $fallback['relationship'] ?? HouseholdRelationPolicy::OTHER_RELATIVE, $data['gender'] ?? $fallback['gender'] ?? null),
            'ethnicity' => trim((string) ($data['ethnicity'] ?? $fallback['ethnicity'] ?? '')) ?: null,
            'religion' => trim((string) ($data['religion'] ?? $fallback['religion'] ?? '')) ?: null,
            'occupation' => trim((string) ($data['occupation'] ?? $fallback['occupation'] ?? $occupationDefault ?? '')) ?: null,
            'father_name' => $this->nullableString($data['fatherName'] ?? $data['father_name'] ?? $fallback['father_name'] ?? null, 255),
            'mother_name' => $this->nullableString($data['motherName'] ?? $data['mother_name'] ?? $fallback['mother_name'] ?? null, 255),
            'phone' => trim((string) ($data['phone'] ?? $fallback['phone'] ?? '')) ?: null,
            'residency' => $this->residency($data['residency_status'] ?? $data['permanentAddress'] ?? $fallback['residency_status'] ?? 'PERMANENT'),
            'current_address' => trim((string) ($data['currentAddress'] ?? $data['current_address'] ?? $fallback['current_address'] ?? '')) ?: null,
            'education' => trim((string) ($data['educationLevel'] ?? $data['education_level'] ?? $fallback['education_level'] ?? $studentDefaults['education'] ?? '')) ?: null,
            'marital' => trim((string) ($data['maritalStatus'] ?? $data['marital_status'] ?? $fallback['marital_status'] ?? '')) ?: null,
            'life' => $this->life($data['status'] ?? $data['life_status'] ?? $fallback['life_status'] ?? 'ALIVE'),
            'presence' => $this->presence($data['presenceStatus'] ?? $data['presence_status'] ?? $fallback['presence_status'] ?? 'AT_HOME'),
            'user' => $userId,
        ];
        $laborFields = ['not_attending_school','pupil','student','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','retired'];
        $ageDefaults = [];
        if ($fallback === null) {
            $age = AgePolicy::ageFromDate((string) $dob);
            if (!$this->anyFieldProvided($data, array_merge($laborFields, ['occupation','education_level']))) {
                foreach (['not_attending_school','pupil','student','employed'] as $column) {
                    if (array_key_exists($column, $studentDefaults)) $ageDefaults[$column] = $studentDefaults[$column];
                }
            }
            $ageDefaults += CitizenPolicyDefaults::defaultsForAge($age);
        }
        foreach ($this->activeExtendedColumns() as $column) {
            $camel = $this->camel($column);
            $params[$column] = $this->fieldProvided($data, $column)
                ? $this->boolValue($data[$column] ?? $data[$camel] ?? 0)
                : $this->boolValue($fallback[$column] ?? $ageDefaults[$column] ?? 0);
        }
        $this->applyHealthInsuranceParams($params, $data, $fallback, $params['occupation'] ?? null);
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
            'not_attending_school' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'has_health_insurance' => 'TINYINT(1) NOT NULL DEFAULT 1',
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
        if ($this->columnExists('citizens', 'has_health_insurance')) {
            $this->execute('ALTER TABLE citizens MODIFY COLUMN has_health_insurance TINYINT(1) NOT NULL DEFAULT 1');
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

    private function applyHealthInsuranceParams(array &$params, array $data, ?array $fallback, ?string $occupation): void
    {
        $active = $this->activeHealthInsuranceColumns();
        if (!$active) return;
        $occupationDefault = InsurancePolicy::defaultForLaborOccupation($occupation);
        if ($this->fieldProvided($data, 'has_health_insurance')) {
            $has = $this->boolValue($data['has_health_insurance'] ?? $data['hasHealthInsurance'] ?? $data['health_insurance'] ?? $data['healthInsurance'] ?? 0);
        } elseif ($fallback === null) {
            $has = $this->boolValue($occupationDefault ?? 0);
        } elseif ($occupationDefault === 1 && $this->occupationChanged($data, $fallback, $occupation)) {
            $has = 1;
        } else {
            $has = $this->boolValue($fallback['has_health_insurance'] ?? $fallback['health_insurance'] ?? 0);
        }
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
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) throw new \RuntimeException('NgÃ y BHYT khÃ´ng há»£p lá»‡');
        return $text;
    }

    private function healthInsuranceGroup(mixed $value): ?string
    {
        $text = $this->nullableString($value, 100);
        if ($text === null) return null;
        return in_array($text, self::HEALTH_INSURANCE_GROUPS, true) ? $text : mb_substr($text, 0, 100);
    }

    private function boolValue(mixed $value): int { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['1','true','yes','co','cÃ³','x'], true) ? 1 : 0; }
    private function camel(string $column): string { return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $column); }
    private function fieldProvided(array $data, string $column): bool
    {
        $camel = $this->camel($column);
        if (array_key_exists($column, $data) || array_key_exists($camel, $data)) return true;
        if ($column === 'has_health_insurance') return array_key_exists('health_insurance', $data) || array_key_exists('healthInsurance', $data);
        if ($column === 'not_attending_school') return array_key_exists('not_school', $data) || array_key_exists('notSchool', $data);
        return false;
    }

    private function anyFieldProvided(array $data, array $columns): bool
    {
        foreach ($columns as $column) {
            if ($this->fieldProvided($data, $column)) return true;
        }
        return false;
    }

    private function occupationChanged(array $data, array $fallback, ?string $occupation): bool
    {
        if (!$this->fieldProvided($data, 'occupation')) return false;
        return InsurancePolicy::normalize($occupation) !== InsurancePolicy::normalize($fallback['occupation'] ?? null);
    }

    private function searchFlag(string $search): ?string
    {
        $text = $this->normalize($search);
        $map = [
            'dang vien' => 'party_member', 'doan vien' => 'youth_union_member', 'hoi phu nu' => 'women_union_member', 'nong dan' => 'farmers_union_member', 'cuu chien binh' => 'veterans_union_member', 'nguoi cao tuoi' => 'elderly_union_member',
            'nguoi co cong' => '__meritorious_policy__', 'than nhan liet si' => 'martyr_relative', 'thuong binh' => 'wounded_soldier', 'benh binh' => 'sick_soldier', 'chat doc hoa hoc' => 'chemical_warfare_victim', 'tu day' => 'imprisoned_resistance_activist', 'thanh nien xung phong' => 'youth_volunteer', 'anh hung' => 'resistance_hero', 'cach mang' => 'revolutionary_activist', 'khuyet tat' => 'disabled_person', 'tro cap xa hoi' => 'social_assistance', 'bao tro xa hoi' => 'social_assistance', 'bhyt' => 'has_health_insurance', 'bao hiem y te' => 'has_health_insurance',
            'co viec lam' => 'employed', 'that nghiep' => 'unemployed', 'lao dong tu do' => 'freelance_labor', 'ngoai tinh' => 'out_province_labor', 'nuoc ngoai' => 'foreign_labor', 'chua di hoc' => 'not_attending_school', 'hoc sinh' => 'pupil', 'sinh vien' => 'student', 'nghi huu' => 'retired',
        ];
        foreach ($map as $needle => $column) if (str_contains($text, $needle)) return $column;
        return null;
    }

    private function meritoriousPolicyCondition(string $alias, bool $positive = true): string
    {
        $parts = [];
        foreach (array_keys(self::POLICY_FIELDS) as $column) {
            if ($this->columnExists('citizens', $column)) $parts[] = $alias . '.' . $column . ' = 1';
        }
        if (!$parts) return $positive ? '0=1' : '1=1';
        $condition = '(' . implode(' OR ', $parts) . ')';
        return $positive ? $condition : 'NOT ' . $condition;
    }
    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $from = ['Ã ','Ã¡','áº¡','áº£','Ã£','Ã¢','áº§','áº¥','áº­','áº©','áº«','Äƒ','áº±','áº¯','áº·','áº³','áºµ','Ã¨','Ã©','áº¹','áº»','áº½','Ãª','á»','áº¿','á»‡','á»ƒ','á»…','Ã¬','Ã­','á»‹','á»‰','Ä©','Ã²','Ã³','á»','á»','Ãµ','Ã´','á»“','á»‘','á»™','á»•','á»—','Æ¡','á»','á»›','á»£','á»Ÿ','á»¡','Ã¹','Ãº','á»¥','á»§','Å©','Æ°','á»«','á»©','á»±','á»­','á»¯','á»³','Ã½','á»µ','á»·','á»¹','Ä‘'];
        $to   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
        return trim(preg_replace('/\s+/', ' ', str_replace($from, $to, $value)));
    }

    private function addCategoryWhere(array &$where, array &$params, string $category): void
    {
        match ($category) {
            'poor' => $where[] = 'h.poor_household = 1',
            'near_poor' => $where[] = 'h.near_poor_household = 1',
            'meritorious' => $where[] = $this->meritoriousHouseholdExists('h'),
            'normal' => $where[] = 'h.poor_household = 0 AND h.near_poor_household = 0 AND NOT ' . $this->meritoriousHouseholdExists('h') . ' AND NOT ' . $this->disabledHouseholdExists('h'),
            'other' => $where[] = $this->disabledHouseholdExists('h'),
            'escaped_poverty', 'policy' => $this->addTextCategoryWhere($where, $params, $category),
            default => null,
        };
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category): void
    {
        $label = ['escaped_poverty' => 'Há»™ má»›i thoÃ¡t nghÃ¨o', 'policy' => 'Há»™ chÃ­nh sÃ¡ch'][$category] ?? $category;
        $where[] = '(h.note LIKE :household_category_label OR h.note LIKE :household_category_key)';
        $params['household_category_label'] = '%' . $label . '%';
        $params['household_category_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function meritoriousHouseholdExists(string $alias): string
    {
        $citizenPolicy = $this->meritoriousPolicyCondition('mhc');
        if ($citizenPolicy === '0=1') return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens mhc WHERE mhc.household_id=' . $alias . '.id AND ' . $this->statistics()->citizenCondition('mhc') . ' AND ' . $citizenPolicy . ')';
    }

    private function disabledHouseholdExists(string $alias): string
    {
        if (!$this->columnExists('citizens', 'disabled_person')) return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens dhc WHERE dhc.household_id=' . $alias . '.id AND ' . $this->statistics()->citizenCondition('dhc') . ' AND dhc.disabled_person=1)';
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
        if ($relationship !== 'Chá»§ há»™') return;
        $params = $this->withTenant(['household_id' => $householdId]);
        $sql = 'SELECT id, full_name FROM citizens WHERE household_id=:household_id AND relationship="Chá»§ há»™" AND status <> "DELETED"';
        $sql .= ' AND ' . $this->tenantWhere('citizens');
        if ($ignoreId) { $sql .= ' AND id <> :id'; $params['id'] = $ignoreId; }
        $head = $this->fetchOne($sql, $params);
        if ($head) throw new \RuntimeException('Há»™ nÃ y Ä‘Ã£ cÃ³ Chá»§ há»™: ' . $head['full_name']);
    }

    private function ensureUniqueIdentity(?string $identity, ?int $ignoreId = null): void
    {
        if (!$identity) return;
        $params = $this->withTenant(['identity' => $identity]);
        $sql = 'SELECT id FROM citizens WHERE identity_number=:identity AND status <> "DELETED" AND ' . $this->tenantWhere('citizens');
        if ($ignoreId) { $sql .= ' AND id <> :id'; $params['id'] = $ignoreId; }
        if ($this->fetchOne($sql, $params)) throw new \RuntimeException('CCCD Ä‘Ã£ tá»“n táº¡i');
    }

    private function nextCode(int $householdId): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) \App\Core\TenantContext::current()['code'])) ?: 'TENANT';
        $prefix .= '-NK';
        $count = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM citizens WHERE citizen_code LIKE :prefix AND ' . $this->tenantWhere('citizens'), $this->withTenant(['prefix' => $prefix . '%']))['total'] ?? 0) + 1;
        do { $code = $prefix . str_pad((string) $count++, 5, '0', STR_PAD_LEFT); }
        while ($this->fetchOne('SELECT id FROM citizens WHERE citizen_code=:code AND ' . $this->tenantWhere('citizens'), $this->withTenant(['code' => $code])));
        return $code;
    }

    private function syncHouseholdHead(int $householdId): void
    {
        $head = $this->fetchOne('SELECT id, full_name FROM citizens WHERE household_id=:household_id AND relationship="Chá»§ há»™" AND status <> "DELETED" AND ' . $this->tenantWhere('citizens') . ' ORDER BY id LIMIT 1', $this->withTenant(['household_id' => $householdId]));
        $this->execute('UPDATE households SET head_citizen_id=:head_id, head_citizen_name=:head_name WHERE id=:household_id AND ' . $this->tenantWhere('households'), $this->withTenant(['household_id' => $householdId, 'head_id' => $head['id'] ?? null, 'head_name' => $head['full_name'] ?? null]));
    }

    private function relationship(mixed $value, mixed $gender = null): string { return HouseholdRelationPolicy::normalizeRelationship($value, $gender); }
    private function residency(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['temporary','temporary_residence','táº¡m trÃº','tam tru'], true) ? 'TEMPORARY' : 'PERMANENT'; }
    private function presence(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['away','Ä‘i váº¯ng','di vang','tam vang','táº¡m váº¯ng'], true) ? 'AWAY' : 'AT_HOME'; }
    private function life(mixed $value): string { $text = mb_strtolower(trim((string) $value)); return in_array($text, ['deceased','dead','Ä‘Ã£ cháº¿t','da chet'], true) ? 'DECEASED' : 'ALIVE'; }
}
