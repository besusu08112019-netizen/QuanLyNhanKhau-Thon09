<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Policies\AgePolicy;
use RuntimeException;

final class CommunityOrganization extends BaseModel
{
    private const ORGANIZATIONS = [
        'WOMEN' => ['name' => 'Chi hội Phụ nữ', 'type' => 'MASS_ORGANIZATION', 'sort' => 10],
        'FARMER' => ['name' => 'Chi hội Nông dân', 'type' => 'MASS_ORGANIZATION', 'sort' => 20],
        'VETERAN' => ['name' => 'Chi hội Cựu chiến binh', 'type' => 'MASS_ORGANIZATION', 'sort' => 30],
        'YOUTH' => ['name' => 'Chi đoàn Thanh niên', 'type' => 'YOUTH_UNION', 'sort' => 40],
    ];

    private const DEFAULT_POSITIONS = [
        'WOMEN' => ['Chi hội trưởng', 'Chi hội phó', 'Tổ trưởng', 'Hội viên'],
        'FARMER' => ['Chi hội trưởng', 'Chi hội phó', 'Tổ trưởng', 'Hội viên'],
        'VETERAN' => ['Chi hội trưởng', 'Chi hội phó', 'Hội viên'],
        'YOUTH' => ['Bí thư Chi đoàn', 'Phó Bí thư', 'Ủy viên BCH', 'Đoàn viên'],
    ];

    private const STATUS_LABELS = [
        'ACTIVE' => 'Đang tham gia',
        'PAUSED' => 'Tạm ngừng',
        'TRANSFERRED' => 'Chuyển sinh hoạt',
        'ENDED' => 'Đã thôi tham gia',
        'DECEASED' => 'Đã mất',
        'DELETED' => 'Đã xóa',
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(190) NOT NULL,
  organization_type VARCHAR(80) NOT NULL DEFAULT 'MASS_ORGANIZATION',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_organizations_village_code (village_id, code),
  KEY idx_organizations_village_status (village_id, status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS organization_positions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NULL,
  organization_code VARCHAR(40) NULL,
  name VARCHAR(190) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_positions_village_org_name (village_id, organization_id, name),
  KEY idx_org_positions_village_org (village_id, organization_id, status, sort_order),
  CONSTRAINT fk_org_positions_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS organization_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NULL,
  position_id BIGINT UNSIGNED NULL,
  subgroup_name VARCHAR(190) NULL,
  member_number VARCHAR(120) NULL,
  joined_date DATE NULL,
  ended_date DATE NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
  active_member_key VARCHAR(80) NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_org_member_current (village_id, organization_id, active_member_key),
  KEY idx_org_members_village_org (village_id, organization_id, status),
  KEY idx_org_members_village_citizen (village_id, citizen_id),
  KEY idx_org_members_village_person (village_id, person_id),
  KEY idx_org_members_position (village_id, position_id),
  KEY idx_org_members_joined (village_id, joined_date),
  CONSTRAINT fk_org_members_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_org_members_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT,
  CONSTRAINT fk_org_members_person FOREIGN KEY (person_id) REFERENCES citizens(id) ON DELETE RESTRICT,
  CONSTRAINT fk_org_members_position FOREIGN KEY (position_id) REFERENCES organization_positions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS organization_member_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NULL,
  old_position_id BIGINT UNSIGNED NULL,
  new_position_id BIGINT UNSIGNED NULL,
  change_type VARCHAR(40) NOT NULL,
  note TEXT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by BIGINT UNSIGNED NULL,
  KEY idx_org_member_history_member (village_id, member_id, changed_at),
  KEY idx_org_member_history_citizen (village_id, citizen_id, changed_at),
  CONSTRAINT fk_org_member_history_member FOREIGN KEY (member_id) REFERENCES organization_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->seedDefaults();
        $this->seedPermissions();
    }

    public function catalogs(array $filters = []): array
    {
        $this->ensureSchema();
        $orgCode = strtoupper(trim((string) ($filters['organization_code'] ?? $filters['organization'] ?? '')));
        $organizations = $this->organizations();
        return [
            'organizations' => $organizations,
            'positions' => $this->positions($orgCode),
            'statuses' => $this->pairs(self::STATUS_LABELS),
            'areas' => $this->areaOptions(),
        ];
    }

    public function organizations(): array
    {
        $this->ensureSchema();
        return array_map(fn(array $row) => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'organization_type' => (string) $row['organization_type'],
            'status' => (string) $row['status'],
        ], $this->fetchAll('SELECT id, code, name, organization_type, status FROM organizations WHERE status="ACTIVE" AND ' . $this->tenantWhere('organizations') . ' ORDER BY sort_order ASC, name ASC', $this->withTenant()));
    }

    public function positions(string $organizationCode = ''): array
    {
        $this->ensureSchema();
        $params = $this->withTenant();
        $where = ['p.status="ACTIVE"', $this->tenantWhere('p', 'organization_positions')];
        if ($organizationCode !== '') {
            $where[] = 'o.code=:organization_code';
            $params['organization_code'] = $organizationCode;
        }
        $rows = $this->fetchAll(
            'SELECT p.id, p.name, p.organization_id, o.code AS organization_code
             FROM organization_positions p
             LEFT JOIN organizations o ON o.id=p.organization_id AND o.village_id=p.village_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.sort_order ASC, p.sort_order ASC, p.name ASC',
            $params
        );
        return array_map(fn(array $row) => [
            'value' => (int) $row['id'],
            'id' => (int) $row['id'],
            'label' => (string) $row['name'],
            'organization_id' => (int) $row['organization_id'],
            'organization_code' => (string) ($row['organization_code'] ?? ''),
        ], $rows);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        $where = ['o.status="ACTIVE"', $this->tenantWhere('o', 'organizations')];
        $params = $this->withTenant();
        $org = strtoupper(trim((string) ($filters['organization_code'] ?? $filters['organizationCode'] ?? $filters['organization'] ?? '')));
        if ($org !== '' && $org !== 'ALL') {
            $where[] = 'o.code=:organization_code';
            $params['organization_code'] = $org;
        }
        $rows = $this->fetchAll(
            'SELECT o.code, o.name,
                    COUNT(om.id) AS total,
                    COALESCE(SUM(CASE WHEN c.gender="Nam" THEN 1 ELSE 0 END),0) AS male,
                    COALESCE(SUM(CASE WHEN c.gender="Nữ" THEN 1 ELSE 0 END),0) AS female,
                    COALESCE(SUM(CASE WHEN p.name IS NOT NULL AND p.name NOT IN ("Hội viên","Đoàn viên") THEN 1 ELSE 0 END),0) AS officer_count,
                    COALESCE(SUM(CASE WHEN om.status="ACTIVE" THEN 1 ELSE 0 END),0) AS active_count
             FROM organizations o
             LEFT JOIN organization_members om ON om.organization_id=o.id AND om.village_id=o.village_id AND om.status <> "DELETED"
             LEFT JOIN citizens c ON c.id=om.citizen_id AND c.village_id=om.village_id
             LEFT JOIN organization_positions p ON p.id=om.position_id AND p.village_id=om.village_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY o.id, o.code, o.name
             ORDER BY o.sort_order ASC, o.name ASC',
            $params
        );
        $items = [];
        $totalActive = 0;
        foreach ($rows as $row) {
            $item = [
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'total_members' => (int) $row['total'],
                'male' => (int) $row['male'],
                'female' => (int) $row['female'],
                'officer_count' => (int) $row['officer_count'],
                'active_count' => (int) $row['active_count'],
            ];
            $totalActive += $item['active_count'];
            $items[] = $item;
        }
        return [
            'metrics' => ['total_active_members' => $totalActive],
            'organizations' => $items,
            'warnings' => $this->movementWarnings($filters),
            'generatedAt' => date('c'),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters, true);
        $total = (int) (($this->fetchOne('SELECT COUNT(*) AS total ' . $this->fromSql() . ' ' . $where, $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . ' ' . $where . ' ' . $order . " LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne($this->selectSql() . ' WHERE om.id=:id AND om.status <> "DELETED" AND ' . $this->tenantWhere('om', 'organization_members'), $this->withTenant(['id' => $id]));
        return $row ? $this->normalize($row) : null;
    }

    public function byCitizen(int $citizenId): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll(
            $this->selectSql() . ' WHERE om.citizen_id=:citizen_id AND om.status <> "DELETED" AND ' . $this->tenantWhere('om', 'organization_members') . ' ORDER BY o.sort_order ASC, om.joined_date DESC, om.id DESC',
            $this->withTenant(['citizen_id' => $citizenId])
        );
        return array_map(fn($row) => $this->normalize($row), $rows);
    }

    public function searchCitizens(string $query, int $limit = 12, string $organizationCode = ''): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) return [];
        $params = $this->withTenant(['q' => '%' . mb_strtolower($query, 'UTF-8') . '%']);
        $duplicateJoin = '';
        $duplicateSelect = '0 AS has_current_membership';
        if ($organizationCode !== '') {
            $org = $this->organizationByCode($organizationCode);
            if ($org) {
                $duplicateJoin = 'LEFT JOIN organization_members dup ON dup.citizen_id=c.id AND dup.village_id=c.village_id AND dup.organization_id=:dup_org_id AND dup.active_member_key IS NOT NULL AND dup.status <> "DELETED"';
                $duplicateSelect = 'CASE WHEN dup.id IS NULL THEN 0 ELSE 1 END AS has_current_membership';
                $params['dup_org_id'] = (int) $org['id'];
            }
        }
        $rows = $this->fetchAll(
            'SELECT c.id, c.citizen_code, c.full_name, c.date_of_birth, c.gender, c.identity_number, c.phone, c.residency_status, c.presence_status,
                    h.household_code, h.address, h.area_code, ' . $duplicateSelect . '
             FROM citizens c
             INNER JOIN households h ON h.id=c.household_id AND h.village_id=c.village_id
             ' . $duplicateJoin . '
             WHERE c.status <> "DELETED"
               AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND ' . $this->tenantWhere('c', 'citizens') . '
               AND (LOWER(c.full_name) LIKE :q OR LOWER(c.citizen_code) LIKE :q OR LOWER(COALESCE(c.identity_number,"")) LIKE :q OR LOWER(h.household_code) LIKE :q)
             ORDER BY c.full_name ASC, c.citizen_code ASC
             LIMIT ' . max(1, min(20, $limit)),
            $params
        );
        return array_map(fn(array $row) => [
            'id' => (int) $row['id'],
            'citizen_id' => (int) $row['id'],
            'citizen_code' => (string) ($row['citizen_code'] ?? ''),
            'full_name' => (string) ($row['full_name'] ?? ''),
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'age' => $this->age($row['date_of_birth'] ?? null),
            'gender' => (string) ($row['gender'] ?? ''),
            'identity_number' => (string) ($row['identity_number'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'household_code' => (string) ($row['household_code'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
            'residency_status' => (string) ($row['residency_status'] ?? ''),
            'presence_status' => (string) ($row['presence_status'] ?? ''),
            'has_current_membership' => (int) ($row['has_current_membership'] ?? 0) === 1,
        ], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $before = $id ? $this->find($id) : null;
        if ($id && !$before) throw new RuntimeException('Không tìm thấy hội viên');
        $params = $this->params($data, $userId, $id);
        if ($id) {
            $params['id'] = $id;
            $this->execute(
                'UPDATE organization_members
                 SET organization_id=:organization_id, citizen_id=:citizen_id, person_id=:person_id, position_id=:position_id,
                     subgroup_name=:subgroup_name, member_number=:member_number, joined_date=:joined_date, ended_date=:ended_date,
                     status=:status, active_member_key=:active_member_key, note=:note, updated_by=:updated_by
                 WHERE id=:id AND ' . $this->tenantWhere('organization_members'),
                $this->withTenant($params)
            );
            $row = $this->find($id);
            $this->writeHistory((int) $id, $before, $row, 'UPDATE', $userId);
            return $row ?: [];
        }

        $columns = ['organization_id','citizen_id','person_id','position_id','subgroup_name','member_number','joined_date','ended_date','status','active_member_key','note','created_by','updated_by'];
        $this->addTenantInsert('organization_members', $columns, $params);
        $newId = $this->insert('INSERT INTO organization_members (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        $row = $this->find($newId);
        $this->writeHistory($newId, null, $row, 'CREATE', $userId);
        return $row ?: [];
    }

    public function endMembership(int $id, array $data, int $userId): array
    {
        $row = $this->find($id);
        if (!$row) throw new RuntimeException('Không tìm thấy hội viên');
        $input = $row + [
            'status' => strtoupper(trim((string) ($data['status'] ?? 'ENDED'))),
            'ended_date' => $this->dateValue($data['ended_date'] ?? $data['endedDate'] ?? date('Y-m-d')),
            'note' => trim((string) ($data['note'] ?? $row['note'] ?? '')),
        ];
        return $this->upsert($input, $userId, $id);
    }

    public function softDelete(int $id, int $userId): void
    {
        $before = $this->find($id);
        if (!$before) throw new RuntimeException('Không tìm thấy hội viên');
        $this->execute('UPDATE organization_members SET status="DELETED", active_member_key=NULL, deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('organization_members'), $this->withTenant(['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]));
        $after = $before;
        $after['status'] = 'DELETED';
        $this->writeHistory($id, $before, $after, 'DELETE', $userId);
    }

    public function history(int $id): array
    {
        $this->ensureSchema();
        return array_map(fn(array $row) => [
            'id' => (int) $row['id'],
            'member_id' => (int) $row['member_id'],
            'change_type' => (string) $row['change_type'],
            'old_status' => (string) ($row['old_status'] ?? ''),
            'new_status' => (string) ($row['new_status'] ?? ''),
            'old_position' => (string) ($row['old_position'] ?? ''),
            'new_position' => (string) ($row['new_position'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
            'changed_at' => $row['changed_at'] ?? null,
            'changed_by_name' => (string) ($row['changed_by_name'] ?? ''),
        ], $this->fetchAll(
            'SELECT h.*, oldp.name AS old_position, newp.name AS new_position, u.display_name AS changed_by_name
             FROM organization_member_history h
             LEFT JOIN organization_positions oldp ON oldp.id=h.old_position_id AND oldp.village_id=h.village_id
             LEFT JOIN organization_positions newp ON newp.id=h.new_position_id AND newp.village_id=h.village_id
             LEFT JOIN users u ON u.id=h.changed_by AND u.village_id=h.village_id
             WHERE h.member_id=:id AND ' . $this->tenantWhere('h', 'organization_member_history') . '
             ORDER BY h.changed_at DESC, h.id DESC',
            $this->withTenant(['id' => $id])
        ));
    }

    public function report(array $filters = []): array
    {
        $this->ensureSchema();
        $filters['page'] = 1;
        $filters['pageSize'] = min(1000, max(100, (int) ($filters['pageSize'] ?? 1000)));
        $page = $this->paginate($filters);
        return [
            'title' => 'Báo cáo Đoàn thể - Chi hội',
            'summary' => $this->dashboard($filters),
            'headers' => ['STT','Tổ chức','Họ và tên','Ngày sinh','Tuổi','Giới tính','Mã hộ','Khu vực','Chức vụ','Ngày tham gia','Trạng thái','Ghi chú'],
            'rows' => array_map(function (array $row, int $index): array {
                return [
                    $index + 1,
                    $row['organization_name'],
                    $row['full_name'],
                    $row['date_of_birth'],
                    $row['age'],
                    $row['gender'],
                    $row['household_code'],
                    $row['area_code'],
                    $row['position_name'],
                    $row['joined_date'],
                    $row['status_label'],
                    $row['note'],
                ];
            }, $page['items'], array_keys($page['items'])),
            'generatedAt' => date('c'),
        ];
    }

    private function params(array $data, int $userId, ?int $id): array
    {
        $organizationId = (int) ($data['organization_id'] ?? $data['organizationId'] ?? 0);
        $organizationCode = strtoupper(trim((string) ($data['organization_code'] ?? $data['organizationCode'] ?? $data['organization'] ?? '')));
        $organization = $organizationId > 0 ? $this->organizationById($organizationId) : $this->organizationByCode($organizationCode);
        if (!$organization) throw new RuntimeException('Vui lòng chọn tổ chức/chi hội');
        $citizenId = (int) ($data['citizen_id'] ?? $data['person_id'] ?? $data['personId'] ?? $data['citizenId'] ?? 0);
        if ($citizenId <= 0) throw new RuntimeException('Vui lòng chọn nhân khẩu từ danh sách');
        $citizen = $this->citizen($citizenId);
        if (!$citizen) throw new RuntimeException('Không tìm thấy nhân khẩu trong tenant hiện tại');
        $status = $this->enum((string) ($data['status'] ?? 'ACTIVE'), self::STATUS_LABELS, 'ACTIVE');
        if ($status === 'DELETED') $status = 'ACTIVE';
        $positionId = (int) ($data['position_id'] ?? $data['positionId'] ?? 0);
        if ($positionId > 0 && !$this->positionBelongsToOrganization($positionId, (int) $organization['id'])) {
            throw new RuntimeException('Chức vụ không thuộc tổ chức đã chọn');
        }
        $activeKey = $this->currentStatus($status) ? (string) $citizenId : null;
        if ($activeKey !== null) {
            $duplicate = $this->fetchOne(
                'SELECT id FROM organization_members WHERE organization_id=:organization_id AND active_member_key=:active_member_key AND status <> "DELETED" AND ' . $this->tenantWhere('organization_members') . ($id ? ' AND id<>:id' : '') . ' LIMIT 1',
                $this->withTenant(array_filter(['organization_id' => (int) $organization['id'], 'active_member_key' => $activeKey, 'id' => $id], fn($value) => $value !== null))
            );
            if ($duplicate) throw new RuntimeException('Nhân khẩu này đã có thông tin đang tham gia tổ chức đã chọn');
        }
        return [
            'organization_id' => (int) $organization['id'],
            'citizen_id' => $citizenId,
            'person_id' => $citizenId,
            'position_id' => $positionId > 0 ? $positionId : null,
            'subgroup_name' => $this->nullable($data['subgroup_name'] ?? $data['subgroupName'] ?? ''),
            'member_number' => $this->nullable($data['member_number'] ?? $data['memberNumber'] ?? ''),
            'joined_date' => $this->dateValue($data['joined_date'] ?? $data['joinedDate'] ?? ''),
            'ended_date' => $this->dateValue($data['ended_date'] ?? $data['endedDate'] ?? ''),
            'status' => $status,
            'active_member_key' => $activeKey,
            'note' => $this->nullable($data['note'] ?? ''),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function where(array $filters, bool $withOrder): array
    {
        $where = ['o.status="ACTIVE"', 'om.status <> "DELETED"', $this->tenantWhere('om', 'organization_members')];
        $params = $this->withTenant();
        $org = strtoupper(trim((string) ($filters['organization_code'] ?? $filters['organizationCode'] ?? $filters['organization'] ?? '')));
        if ($org !== '' && $org !== 'ALL') {
            $where[] = 'o.code=:organization_code';
            $params['organization_code'] = $org;
        }
        foreach (['status' => 'om.status', 'gender' => 'c.gender', 'area_code' => 'h.area_code'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? $filters[str_replace('_', '', $key)] ?? ''));
            if ($value !== '' && strtoupper($value) !== 'ALL') {
                $where[] = "$column=:$key";
                $params[$key] = $key === 'status' ? strtoupper($value) : $value;
            }
        }
        $positionId = (int) ($filters['position_id'] ?? $filters['positionId'] ?? 0);
        if ($positionId > 0) {
            $where[] = 'om.position_id=:position_id';
            $params['position_id'] = $positionId;
        }
        $joinedYear = (int) ($filters['joined_year'] ?? $filters['joinedYear'] ?? 0);
        if ($joinedYear > 1900) {
            $where[] = 'YEAR(om.joined_date)=:joined_year';
            $params['joined_year'] = $joinedYear;
        }
        $ageSql = AgePolicy::ageSql('c');
        $ageFrom = (int) ($filters['age_from'] ?? $filters['ageFrom'] ?? 0);
        $ageTo = (int) ($filters['age_to'] ?? $filters['ageTo'] ?? 0);
        if ($ageFrom > 0) $where[] = "$ageSql >= " . $ageFrom;
        if ($ageTo > 0) $where[] = "$ageSql <= " . $ageTo;
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(c.full_name LIKE :search OR c.citizen_code LIKE :search OR h.household_code LIKE :search OR om.member_number LIKE :search OR om.subgroup_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        if (!$withOrder) return [$sqlWhere, $params];
        $sortMap = [
            'full_name' => 'c.full_name',
            'organization' => 'o.sort_order',
            'joined_date' => 'om.joined_date',
            'status' => 'om.status',
            'area_code' => 'h.area_code',
            'age' => $ageSql,
        ];
        return [$sqlWhere, $params, $this->listOrder($filters, $sortMap, 'organization', 'ASC', ['c.full_name ASC', 'om.id DESC'])];
    }

    private function selectSql(): string
    {
        $ageSql = AgePolicy::ageSql('c');
        return 'SELECT om.*, o.code AS organization_code, o.name AS organization_name, p.name AS position_name,
                       c.citizen_code, c.full_name, c.date_of_birth, c.gender, c.phone, c.identity_number, c.residency_status, c.presence_status, c.status AS citizen_status,
                       h.household_code, h.address, h.area_code, ' . $ageSql . ' AS age
                ' . $this->fromSql();
    }

    private function fromSql(): string
    {
        return 'FROM organization_members om
                INNER JOIN organizations o ON o.id=om.organization_id AND o.village_id=om.village_id
                INNER JOIN citizens c ON c.id=om.citizen_id AND c.village_id=om.village_id
                INNER JOIN households h ON h.id=c.household_id AND h.village_id=c.village_id
                LEFT JOIN organization_positions p ON p.id=om.position_id AND p.village_id=om.village_id';
    }

    private function normalize(array $row): array
    {
        $warning = '';
        $citizenStatus = strtoupper((string) ($row['citizen_status'] ?? ''));
        $presence = strtoupper((string) ($row['presence_status'] ?? ''));
        $residency = strtoupper((string) ($row['residency_status'] ?? ''));
        if (in_array($citizenStatus, ['DECEASED'], true) || strtoupper((string) ($row['status'] ?? '')) === 'DECEASED') {
            $warning = 'Hồ sơ nhân khẩu đã mất, cần rà soát tình trạng hội viên';
        } elseif (in_array($presence, ['AWAY', 'TEMPORARY_ABSENCE', 'LONG_TERM_ABSENCE'], true) || in_array($residency, ['MOVED_OUT', 'TRANSFERRED_OUT', 'SETTLED_ELSEWHERE'], true)) {
            $warning = 'Hồ sơ nhân khẩu có biến động cư trú, cần rà soát tình trạng hội viên';
        }
        $status = (string) ($row['status'] ?? 'ACTIVE');
        return [
            'id' => (int) $row['id'],
            'organization_id' => (int) $row['organization_id'],
            'organization_code' => (string) $row['organization_code'],
            'organization_name' => (string) $row['organization_name'],
            'citizen_id' => (int) $row['citizen_id'],
            'person_id' => (int) ($row['person_id'] ?? $row['citizen_id']),
            'citizen_code' => (string) ($row['citizen_code'] ?? ''),
            'full_name' => (string) ($row['full_name'] ?? ''),
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'age' => isset($row['age']) ? (int) $row['age'] : $this->age($row['date_of_birth'] ?? null),
            'gender' => (string) ($row['gender'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'identity_number' => (string) ($row['identity_number'] ?? ''),
            'household_code' => (string) ($row['household_code'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
            'position_id' => isset($row['position_id']) ? (int) $row['position_id'] : null,
            'position_name' => (string) ($row['position_name'] ?? ''),
            'subgroup_name' => (string) ($row['subgroup_name'] ?? ''),
            'member_number' => (string) ($row['member_number'] ?? ''),
            'joined_date' => $row['joined_date'] ?? null,
            'ended_date' => $row['ended_date'] ?? null,
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? $status,
            'note' => (string) ($row['note'] ?? ''),
            'warning' => $warning,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function movementWarnings(array $filters): array
    {
        $filters['status'] = 'ACTIVE';
        [$where, $params] = $this->where($filters, false);
        $where .= ' AND (c.status="DECEASED" OR c.presence_status IN ("AWAY","TEMPORARY_ABSENCE","LONG_TERM_ABSENCE") OR c.residency_status IN ("MOVED_OUT","TRANSFERRED_OUT","SETTLED_ELSEWHERE"))';
        $rows = $this->fetchAll($this->selectSql() . ' ' . $where . ' ORDER BY c.full_name ASC LIMIT 20', $params);
        return array_map(fn($row) => $this->normalize($row), $rows);
    }

    private function writeHistory(int $memberId, ?array $before, ?array $after, string $type, int $userId): void
    {
        if (!$after && !$before) return;
        $source = $after ?: $before;
        $this->execute(
            'INSERT INTO organization_member_history (village_id, member_id, organization_id, citizen_id, old_status, new_status, old_position_id, new_position_id, change_type, note, changed_by)
             VALUES (:village_id,:member_id,:organization_id,:citizen_id,:old_status,:new_status,:old_position_id,:new_position_id,:change_type,:note,:changed_by)',
            $this->withTenant([
                'member_id' => $memberId,
                'organization_id' => (int) ($source['organization_id'] ?? 0),
                'citizen_id' => (int) ($source['citizen_id'] ?? 0),
                'old_status' => $before['status'] ?? null,
                'new_status' => $after['status'] ?? null,
                'old_position_id' => $before['position_id'] ?? null,
                'new_position_id' => $after['position_id'] ?? null,
                'change_type' => $type,
                'note' => $after['note'] ?? $before['note'] ?? null,
                'changed_by' => $userId,
            ])
        );
    }

    private function seedDefaults(): void
    {
        foreach (self::ORGANIZATIONS as $code => $org) {
            $this->execute(
                'INSERT INTO organizations (village_id, code, name, organization_type, sort_order, status)
                 VALUES (:village_id,:code,:name,:type,:sort,"ACTIVE")
                 ON DUPLICATE KEY UPDATE name=VALUES(name), organization_type=VALUES(organization_type), sort_order=VALUES(sort_order), status="ACTIVE"',
                $this->withTenant(['code' => $code, 'name' => $org['name'], 'type' => $org['type'], 'sort' => $org['sort']])
            );
            $organization = $this->organizationByCode($code);
            if (!$organization) continue;
            foreach (self::DEFAULT_POSITIONS[$code] ?? [] as $index => $name) {
                $this->execute(
                    'INSERT IGNORE INTO organization_positions (village_id, organization_id, organization_code, name, sort_order, status)
                     VALUES (:village_id,:organization_id,:organization_code,:name,:sort_order,"ACTIVE")',
                    $this->withTenant(['organization_id' => (int) $organization['id'], 'organization_code' => $code, 'name' => $name, 'sort_order' => ($index + 1) * 10])
                );
            }
        }
    }

    private function seedPermissions(): void
    {
        foreach (['SUPER_ADMIN','ADMIN'] as $role) {
            foreach (['read','create','update','delete','export','print','manage'] as $action) {
                $this->execute('INSERT IGNORE INTO permissions (role,module,action,allowed) VALUES (:role,"organizations",:action,1)', ['role' => $role, 'action' => $action]);
            }
        }
        foreach (['read','create','update','export','print'] as $action) {
            $this->execute('INSERT IGNORE INTO permissions (role,module,action,allowed) VALUES ("OFFICER","organizations",:action,1)', ['action' => $action]);
        }
        $this->execute('INSERT IGNORE INTO permissions (role,module,action,allowed) VALUES ("VIEWER","organizations","read",1)');
    }

    private function organizationByCode(string $code): ?array
    {
        if ($code === '') return null;
        return $this->fetchOne('SELECT * FROM organizations WHERE code=:code AND status="ACTIVE" AND ' . $this->tenantWhere('organizations'), $this->withTenant(['code' => $code]));
    }

    private function organizationById(int $id): ?array
    {
        return $id > 0 ? $this->fetchOne('SELECT * FROM organizations WHERE id=:id AND status="ACTIVE" AND ' . $this->tenantWhere('organizations'), $this->withTenant(['id' => $id])) : null;
    }

    private function citizen(int $id): ?array
    {
        return $this->fetchOne('SELECT c.id FROM citizens c INNER JOIN households h ON h.id=c.household_id AND h.village_id=c.village_id WHERE c.id=:id AND c.status <> "DELETED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") AND ' . $this->tenantWhere('c', 'citizens'), $this->withTenant(['id' => $id]));
    }

    private function positionBelongsToOrganization(int $positionId, int $organizationId): bool
    {
        return (bool) $this->fetchOne('SELECT id FROM organization_positions WHERE id=:id AND organization_id=:organization_id AND status="ACTIVE" AND ' . $this->tenantWhere('organization_positions'), $this->withTenant(['id' => $positionId, 'organization_id' => $organizationId]));
    }

    private function areaOptions(): array
    {
        $rows = $this->fetchAll('SELECT DISTINCT area_code FROM households WHERE area_code IS NOT NULL AND area_code <> "" AND status <> "DELETED" AND ' . $this->tenantWhere('households') . ' ORDER BY area_code ASC', $this->withTenant());
        return array_map(fn($row) => ['value' => (string) $row['area_code'], 'label' => (string) $row['area_code']], $rows);
    }

    private function currentStatus(string $status): bool
    {
        return in_array($status, ['ACTIVE', 'PAUSED'], true);
    }

    private function enum(string $value, array $labels, string $default): string
    {
        $value = strtoupper(trim($value));
        return isset($labels[$value]) ? $value : $default;
    }

    private function pairs(array $labels): array
    {
        return array_map(fn($key, $label) => ['value' => $key, 'label' => $label], array_keys($labels), array_values($labels));
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function dateValue(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $text, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : null;
    }

    private function age(?string $date): ?int
    {
        if (!$date) return null;
        try {
            return max(0, (int) (new \DateTimeImmutable($date))->diff(new \DateTimeImmutable('today'))->y);
        } catch (\Throwable) {
            return null;
        }
    }
}
