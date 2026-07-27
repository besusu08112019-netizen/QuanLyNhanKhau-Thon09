<?php

namespace App\Models;

use App\Core\BaseModel;

final class PartyMember extends BaseModel
{
    public const MEMBER_TYPES = [
        'OFFICIAL' => 'Đảng viên chính thức',
        'PROBATIONARY' => 'Đảng viên dự bị',
    ];

    public const STATUS_LABELS = [
        'ACTIVE' => 'Đang sinh hoạt',
        'TRANSFERRED_OUT' => 'Chuyển sinh hoạt đi',
        'TRANSFERRED_IN' => 'Chuyển sinh hoạt đến',
        'EXEMPT' => 'Miễn sinh hoạt',
        'TEMP_EXEMPT' => 'Tạm miễn sinh hoạt',
        'RETIRED' => 'Nghỉ hưu',
        'DECEASED' => 'Từ trần',
        'LEFT_PARTY' => 'Ra khỏi Đảng',
        'DELETED' => 'Đã xóa',
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS party_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  party_member_code VARCHAR(80) NULL,
  party_card_number VARCHAR(80) NULL,
  joined_party_date DATE NULL,
  official_party_date DATE NULL,
  branch_name VARCHAR(180) NULL,
  parent_party_org VARCHAR(180) NULL,
  party_position VARCHAR(180) NULL,
  government_position VARCHAR(180) NULL,
  education_level VARCHAR(180) NULL,
  professional_level VARCHAR(180) NULL,
  political_theory_level VARCHAR(180) NULL,
  member_type VARCHAR(30) NOT NULL DEFAULT 'OFFICIAL',
  activity_status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_party_members_village_citizen (village_id, citizen_id),
  UNIQUE KEY uq_party_members_village_code (village_id, party_member_code),
  KEY idx_party_members_branch (village_id, branch_name),
  KEY idx_party_members_type (village_id, member_type),
  KEY idx_party_members_activity_status (village_id, activity_status),
  KEY idx_party_members_position (village_id, party_position),
  KEY idx_party_members_joined_date (joined_party_date),
  CONSTRAINT fk_party_members_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->backfillFromCitizens();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();

        return [
            'member_types' => $this->options(self::MEMBER_TYPES),
            'statuses' => $this->options(self::STATUS_LABELS, ['DELETED']),
            'branches' => $this->distinctOptions('branch_name'),
            'positions' => $this->distinctOptions('party_position'),
            'political_theory_levels' => $this->distinctOptions('political_theory_level'),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM party_members pm INNER JOIN citizens c ON c.id=pm.citizen_id INNER JOIN households h ON h.id=c.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id, bool $includeDeleted = false): ?array
    {
        $this->ensureSchema();
        $where = ['pm.id=:id', $this->activeHouseholdWhere('h'), $this->tenantWhere('pm', 'party_members'), $this->tenantWhere('c', 'citizens'), $this->tenantWhere('h', 'households')];
        if (!$includeDeleted) $where[] = 'pm.status <> "DELETED"';
        $row = $this->fetchOne($this->selectSql() . ' WHERE ' . implode(' AND ', $where), $this->withTenant(['id' => $id]));
        return $row ? $this->normalize($row) : null;
    }

    public function searchCitizens(string $query, int $limit = 10): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll(
            'SELECT c.id, c.citizen_code, c.full_name, c.date_of_birth, c.gender, c.identity_number, c.phone, h.household_code, h.address
             FROM citizens c
             INNER JOIN households h ON h.id=c.household_id
             LEFT JOIN party_members pm ON pm.citizen_id=c.id AND pm.village_id=c.village_id
             WHERE c.status <> "DELETED" AND ' . $this->activeHouseholdWhere('h') . '
               AND pm.id IS NULL
               AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households') . '
               AND (LOWER(c.full_name) LIKE :q OR LOWER(c.citizen_code) LIKE :q OR LOWER(COALESCE(c.identity_number,"")) LIKE :q OR LOWER(h.household_code) LIKE :q)
             ORDER BY c.full_name ASC, c.citizen_code ASC
             LIMIT ' . max(1, min(20, $limit)),
            $this->withTenant(['q' => $keyword])
        );
        return array_map(fn($row) => [
            'id' => (int) $row['id'],
            'citizen_code' => (string) ($row['citizen_code'] ?? ''),
            'full_name' => (string) ($row['full_name'] ?? ''),
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'age' => $this->age($row['date_of_birth'] ?? null),
            'gender' => (string) ($row['gender'] ?? ''),
            'identity_number' => (string) ($row['identity_number'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'household_code' => (string) ($row['household_code'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
        ], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->find($id)) throw new \RuntimeException('Không tìm thấy hồ sơ Đảng viên');
        $params = $this->params($data, $userId, $id);
        if ($id) {
            $params['id'] = $id;
            $this->execute(
                'UPDATE party_members SET party_member_code=:party_member_code, party_card_number=:party_card_number, joined_party_date=:joined_party_date, official_party_date=:official_party_date, branch_name=:branch_name, parent_party_org=:parent_party_org, party_position=:party_position, government_position=:government_position, education_level=:education_level, professional_level=:professional_level, political_theory_level=:political_theory_level, member_type=:member_type, activity_status=:activity_status, note=:note, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('party_members'),
                $this->withTenant($params)
            );
            $row = $this->find($id);
        } else {
            $columns = ['citizen_id','party_member_code','party_card_number','joined_party_date','official_party_date','branch_name','parent_party_org','party_position','government_position','education_level','professional_level','political_theory_level','member_type','activity_status','note','status','created_by','updated_by'];
            $insert = $params + ['status' => 'ACTIVE', 'created_by' => $userId, 'updated_by' => $userId];
            $this->addTenantInsert('party_members', $columns, $insert);
            $newId = $this->insert('INSERT INTO party_members (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $insert);
            $row = $this->find($newId);
        }
        $this->syncCitizenPartyFlag((int) $params['citizen_id'], true, $userId);
        return $row ?: [];
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        $row = $this->find($id);
        if (!$row) throw new \RuntimeException('Không tìm thấy hồ sơ Đảng viên');
        $this->execute('UPDATE party_members SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('party_members'), $this->withTenant(['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]));
        $this->syncCitizenPartyFlag((int) $row['citizen_id'], false, $userId);
    }

    public function restore(int $id, int $userId): array
    {
        $this->ensureSchema();
        $row = $this->find($id, true);
        if (!$row) throw new \RuntimeException('Không tìm thấy hồ sơ Đảng viên');
        $this->execute('UPDATE party_members SET status="ACTIVE", deleted_at=NULL, deleted_by=NULL, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('party_members'), $this->withTenant(['id' => $id, 'user' => $userId]));
        $this->syncCitizenPartyFlag((int) $row['citizen_id'], true, $userId);
        return $this->find($id) ?: [];
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN pm.member_type='OFFICIAL' THEN 1 ELSE 0 END),0) AS official,
                COALESCE(SUM(CASE WHEN pm.member_type='PROBATIONARY' THEN 1 ELSE 0 END),0) AS probationary,
                COALESCE(SUM(CASE WHEN c.gender='Nam' THEN 1 ELSE 0 END),0) AS male,
                COALESCE(SUM(CASE WHEN c.gender='Nữ' THEN 1 ELSE 0 END),0) AS female,
                COALESCE(SUM(CASE WHEN pm.activity_status='RETIRED' THEN 1 ELSE 0 END),0) AS retired,
                COALESCE(SUM(CASE WHEN pm.activity_status IN ('EXEMPT','TEMP_EXEMPT') THEN 1 ELSE 0 END),0) AS exempt,
                COALESCE(SUM(CASE WHEN pm.activity_status IN ('TRANSFERRED_OUT','TRANSFERRED_IN') THEN 1 ELSE 0 END),0) AS transferred,
                COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR, COALESCE(pm.official_party_date, pm.joined_party_date), CURDATE()) >= 30 AND MOD(TIMESTAMPDIFF(YEAR, COALESCE(pm.official_party_date, pm.joined_party_date), CURDATE()), 5)=0 THEN 1 ELSE 0 END),0) AS badge_due
             FROM party_members pm INNER JOIN citizens c ON c.id=pm.citizen_id INNER JOIN households h ON h.id=c.household_id $where",
            $params
        ) ?: [];
        return array_map('intval', [
            'total' => $row['total'] ?? 0,
            'official' => $row['official'] ?? 0,
            'probationary' => $row['probationary'] ?? 0,
            'male' => $row['male'] ?? 0,
            'female' => $row['female'] ?? 0,
            'retired' => $row['retired'] ?? 0,
            'exempt' => $row['exempt'] ?? 0,
            'transferred' => $row['transferred'] ?? 0,
            'badge_due' => $row['badge_due'] ?? 0,
        ]);
    }

    public function charts(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        return [
            'age' => $this->fetchAll("SELECT CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 30 THEN 'Dưới 30' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 40 THEN '30-39' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 50 THEN '40-49' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 60 THEN '50-59' ELSE '60+' END AS label, COUNT(*) AS value FROM party_members pm INNER JOIN citizens c ON c.id=pm.citizen_id INNER JOIN households h ON h.id=c.household_id $where GROUP BY label ORDER BY MIN(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()))", $params),
            'gender' => $this->fetchAll("SELECT COALESCE(NULLIF(c.gender,''),'Khác') AS label, COUNT(*) AS value FROM party_members pm INNER JOIN citizens c ON c.id=pm.citizen_id INNER JOIN households h ON h.id=c.household_id $where GROUP BY label ORDER BY value DESC", $params),
            'branch' => $this->fetchAll("SELECT COALESCE(NULLIF(pm.branch_name,''),'Chưa cập nhật') AS label, COUNT(*) AS value FROM party_members pm INNER JOIN citizens c ON c.id=pm.citizen_id INNER JOIN households h ON h.id=c.household_id $where GROUP BY label ORDER BY value DESC, label LIMIT 12", $params),
        ];
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'official') $filters['member_type'] = 'OFFICIAL';
        if ($mode === 'probationary') $filters['member_type'] = 'PROBATIONARY';
        $this->ensureSchema();
        [$where, $params, $order] = $this->where($filters);
        $items = array_map(fn($row) => $this->normalize($row), $this->fetchAll($this->selectSql() . " $where $order", $params));
        $title = match ($mode) {
            'branch' => 'Báo cáo Đảng viên theo chi bộ',
            'age' => 'Báo cáo Đảng viên theo độ tuổi',
            'gender' => 'Báo cáo Đảng viên theo giới tính',
            'position' => 'Báo cáo Đảng viên theo chức vụ',
            'official' => 'Danh sách Đảng viên chính thức',
            'probationary' => 'Danh sách Đảng viên dự bị',
            'status' => 'Báo cáo tình trạng sinh hoạt Đảng',
            default => 'Danh sách Đảng viên',
        };
        $rows = [];
        foreach ($items as $index => $r) {
            $rows[] = [
                $index + 1,
                $r['full_name'],
                $r['party_member_code'],
                $r['branch_name'],
                $r['party_position'],
                $r['member_type_label'],
                $r['activity_status_label'],
                $this->date($r['joined_party_date']),
                $this->date($r['official_party_date']),
                $r['gender'],
                $this->date($r['date_of_birth']),
            ];
        }
        return [
            'title' => $title,
            'headers' => ['STT','Họ tên','Mã Đảng viên','Chi bộ','Chức vụ','Loại Đảng viên','Tình trạng','Ngày vào Đảng','Ngày chính thức','Giới tính','Ngày sinh'],
            'rows' => $rows,
            'totalRows' => count($items),
            'filters' => $filters,
            'summary' => ['Tổng số Đảng viên' => count($items)],
            'meta' => [
                'period_label' => $this->filterSummary($filters),
                'report_date' => 'Ngày xuất: ' . date('d/m/Y H:i:s'),
            ],
            'orientation' => 'portrait',
            'generatedAt' => date('c')
        ];
    }

    private function selectSql(): string
    {
        return 'SELECT pm.*, c.citizen_code, c.full_name, c.date_of_birth, c.gender, c.identity_number, c.phone, c.education_level AS citizen_education_level, h.household_code, h.address, h.area_code
            FROM party_members pm
            INNER JOIN citizens c ON c.id=pm.citizen_id
            INNER JOIN households h ON h.id=c.household_id';
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['pm.status <> "DELETED"', 'c.status <> "DELETED"', $this->activeHouseholdWhere('h'), $this->tenantWhere('pm', 'party_members'), $this->tenantWhere('c', 'citizens'), $this->tenantWhere('h', 'households')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(c.full_name) LIKE :q OR LOWER(c.citizen_code) LIKE :q OR LOWER(COALESCE(c.identity_number,"")) LIKE :q OR LOWER(COALESCE(pm.party_member_code,"")) LIKE :q OR LOWER(COALESCE(pm.party_card_number,"")) LIKE :q OR LOWER(COALESCE(pm.branch_name,"")) LIKE :q)';
        }
        foreach (['branch_name' => ['branch','branch_name'], 'party_position' => ['position','party_position'], 'gender' => ['gender'], 'member_type' => ['member_type','memberType'], 'activity_status' => ['activity_status','activityStatus','status']] as $column => $keys) {
            $value = $this->filterValue($filters, $keys);
            if ($value === '') continue;
            $qualified = $column === 'gender' ? 'c.gender' : 'pm.' . $column;
            $param = preg_replace('/[^a-z_]/', '', $column);
            $where[] = $qualified . ' = :' . $param;
            $params[$param] = $column === 'gender' ? $value : strtoupper($value);
            if (in_array($column, ['branch_name', 'party_position'], true)) $params[$param] = $value;
        }
        $ageFrom = trim((string) ($filters['age_from'] ?? $filters['ageFrom'] ?? ''));
        if ($ageFrom !== '') { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= :age_from'; $params['age_from'] = (int) $ageFrom; }
        $ageTo = trim((string) ($filters['age_to'] ?? $filters['ageTo'] ?? ''));
        if ($ageTo !== '') { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= :age_to'; $params['age_to'] = (int) $ageTo; }
        $sortMap = ['full_name' => 'c.full_name', 'party_member_code' => 'pm.party_member_code', 'branch_name' => 'pm.branch_name', 'party_position' => 'pm.party_position', 'member_type' => 'pm.member_type', 'activity_status' => 'pm.activity_status', 'joined_party_date' => 'pm.joined_party_date'];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = $this->listOrder($filters, $sortMap, 'full_name', 'ASC', ['pm.id DESC']);
        return $result;
    }

    private function params(array $data, int $userId, ?int $id): array
    {
        $citizenId = $id ? (int) (($this->find($id)['citizen_id'] ?? 0)) : (int) ($data['citizen_id'] ?? $data['citizenId'] ?? 0);
        if ($citizenId <= 0) throw new \RuntimeException('Nhân khẩu là bắt buộc');
        if (!$this->fetchOne('SELECT c.id FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.id=:id AND c.status <> "DELETED" AND ' . $this->activeHouseholdWhere('h') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households'), $this->withTenant(['id' => $citizenId]))) throw new \RuntimeException('Không tìm thấy nhân khẩu');
        $duplicate = $this->fetchOne('SELECT id FROM party_members WHERE citizen_id=:citizen_id AND ' . $this->tenantWhere('party_members') . ' AND (:id=0 OR id<>:id) LIMIT 1', $this->withTenant(['citizen_id' => $citizenId, 'id' => (int) ($id ?? 0)]));
        if ($duplicate) throw new \RuntimeException('Nhân khẩu này đã có hồ sơ Đảng viên');
        $memberType = strtoupper(trim((string) ($data['member_type'] ?? $data['memberType'] ?? 'OFFICIAL')));
        if (!isset(self::MEMBER_TYPES[$memberType])) $memberType = 'OFFICIAL';
        $activityStatus = strtoupper(trim((string) ($data['activity_status'] ?? $data['activityStatus'] ?? 'ACTIVE')));
        if (!isset(self::STATUS_LABELS[$activityStatus]) || $activityStatus === 'DELETED') $activityStatus = 'ACTIVE';
        return [
            'citizen_id' => $citizenId,
            'party_member_code' => $this->nullable($data['party_member_code'] ?? $data['partyMemberCode'] ?? null),
            'party_card_number' => $this->nullable($data['party_card_number'] ?? $data['partyCardNumber'] ?? null),
            'joined_party_date' => $this->dateOrNull($data['joined_party_date'] ?? $data['joinedPartyDate'] ?? null),
            'official_party_date' => $this->dateOrNull($data['official_party_date'] ?? $data['officialPartyDate'] ?? null),
            'branch_name' => $this->nullable($data['branch_name'] ?? $data['branchName'] ?? null),
            'parent_party_org' => $this->nullable($data['parent_party_org'] ?? $data['parentPartyOrg'] ?? null),
            'party_position' => $this->nullable($data['party_position'] ?? $data['partyPosition'] ?? null),
            'government_position' => $this->nullable($data['government_position'] ?? $data['governmentPosition'] ?? null),
            'education_level' => $this->nullable($data['education_level'] ?? $data['educationLevel'] ?? null),
            'professional_level' => $this->nullable($data['professional_level'] ?? $data['professionalLevel'] ?? null),
            'political_theory_level' => $this->nullable($data['political_theory_level'] ?? $data['politicalTheoryLevel'] ?? null),
            'member_type' => $memberType,
            'activity_status' => $activityStatus,
            'note' => $this->nullable($data['note'] ?? null),
            'user' => $userId,
        ];
    }

    private function filterSummary(array $filters): string
    {
        $labels = [];
        $map = [
            'branch_name' => 'Chi bộ',
            'member_type' => 'Loại Đảng viên',
            'activity_status' => 'Tình trạng',
            'party_position' => 'Chức vụ',
            'gender' => 'Giới tính',
            'age_from' => 'Tuổi từ',
            'age_to' => 'Tuổi đến',
            'search' => 'Từ khóa',
        ];
        foreach ($map as $key => $label) {
            $value = trim((string) ($filters[$key] ?? $filters[lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))))] ?? ''));
            if ($value === '') continue;
            if ($key === 'member_type') $value = self::MEMBER_TYPES[strtoupper($value)] ?? $value;
            if ($key === 'activity_status') $value = self::STATUS_LABELS[strtoupper($value)] ?? $value;
            $labels[] = $label . ': ' . $value;
        }
        return $labels ? 'Điều kiện lọc: ' . implode('; ', $labels) : 'Điều kiện lọc: Tất cả Đảng viên';
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['citizen_id'] = (int) $row['citizen_id'];
        $row['age'] = $this->age($row['date_of_birth'] ?? null);
        $row['member_type_label'] = self::MEMBER_TYPES[$row['member_type'] ?? 'OFFICIAL'] ?? (string) ($row['member_type'] ?? '');
        $row['activity_status_label'] = self::STATUS_LABELS[$row['activity_status'] ?? 'ACTIVE'] ?? (string) ($row['activity_status'] ?? '');
        $row['photo_url'] = null;
        return $row;
    }

    private function backfillFromCitizens(): void
    {
        $this->execute('INSERT IGNORE INTO party_members (village_id, citizen_id, member_type, activity_status, status, created_at, updated_at)
            SELECT c.village_id, c.id, "OFFICIAL", "ACTIVE", "ACTIVE", NOW(), NOW()
            FROM citizens c
            INNER JOIN households h ON h.id=c.household_id AND h.village_id=c.village_id
            WHERE c.party_member=1 AND c.status <> "DELETED" AND ' . $this->activeHouseholdWhere('h') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households'));
    }

    private function syncCitizenPartyFlag(int $citizenId, bool $enabled, int $userId): void
    {
        $this->execute('UPDATE citizens SET party_member=:party_member, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('citizens'), $this->withTenant(['id' => $citizenId, 'party_member' => $enabled ? 1 : 0, 'updated_by' => $userId]));
    }

    private function distinctOptions(string $column): array
    {
        $rows = $this->fetchAll('SELECT DISTINCT ' . $column . ' AS value FROM party_members WHERE status <> "DELETED" AND ' . $this->tenantWhere('party_members') . ' AND ' . $column . ' IS NOT NULL AND TRIM(' . $column . ') <> "" ORDER BY ' . $column . ' ASC LIMIT 100', $this->withTenant());
        return array_map(fn($row) => ['value' => (string) $row['value'], 'label' => (string) $row['value']], $rows);
    }

    private function activeHouseholdWhere(string $alias): string
    {
        return $alias . '.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")';
    }

    private function options(array $labels, array $exclude = []): array
    {
        return array_values(array_map(fn($key) => ['value' => $key, 'label' => $labels[$key]], array_filter(array_keys($labels), fn($key) => !in_array($key, $exclude, true))));
    }

    private function filterValue(array $filters, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') return $value;
        }
        return '';
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : null;
    }

    private function age(mixed $date): ?int
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $date)) return null;
        try { return (int) (new \DateTimeImmutable((string) $date))->diff(new \DateTimeImmutable('today'))->y; } catch (\Throwable) { return null; }
    }

    private function date(?string $value): string
    {
        if (!$value || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) return '';
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
}
