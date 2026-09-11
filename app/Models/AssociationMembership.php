<?php

namespace App\Models;

use App\Core\BaseModel;

final class AssociationMembership extends BaseModel
{
    public const STATUS_LABELS = [
        'ACTIVE' => 'Đang tham gia',
        'PAUSED' => 'Tạm dừng',
        'LEFT' => 'Đã rời tổ chức',
        'DELETED' => 'Đã xóa',
    ];

    private const DEFAULT_POSITIONS = ['Chi hội trưởng', 'Chi hội phó', 'Tổ trưởng', 'Tổ phó', 'Hội viên'];
    private const POSITION_CATALOG = [
        'FARMERS_UNION' => ['Chi hội trưởng', 'Chi hội phó', 'Tổ trưởng', 'Tổ phó', 'Hội viên'],
        'WOMEN_UNION' => ['Chi hội trưởng', 'Chi hội phó', 'Tổ trưởng', 'Tổ phó', 'Hội viên'],
        'VETERANS_UNION' => ['Chi hội trưởng', 'Chi hội phó', 'Hội viên'],
        'YOUTH_UNION' => ['Bí thư Chi đoàn', 'Phó Bí thư Chi đoàn', 'Đoàn viên'],
    ];

    public function catalogs(): array
    {
        $organizations = $this->fetchAll('SELECT id, code, name, organization_type FROM association_organizations WHERE status="ACTIVE" AND organization_type="ASSOCIATION" AND ' . $this->tenantWhere('association_organizations') . ' ORDER BY sort_order, name', $this->withTenant());
        $areas = $this->fetchAll('SELECT DISTINCT h.area_code AS value FROM association_memberships am INNER JOIN citizens c ON c.id=am.citizen_id INNER JOIN households h ON h.id=c.household_id WHERE am.status <> "DELETED" AND ' . $this->tenantWhere('am', 'association_memberships') . ' AND h.area_code IS NOT NULL AND h.area_code <> "" ORDER BY h.area_code', $this->withTenant());
        return [
            'organizations' => array_map(fn($r) => ['value' => (string) $r['id'], 'label' => (string) $r['name'], 'code' => (string) $r['code'], 'type' => (string) $r['organization_type']], $organizations),
            'positions' => $this->positionCatalog($organizations),
            'statuses' => array_map(fn($code, $label) => ['value' => $code, 'label' => $label], array_keys(self::STATUS_LABELS), self::STATUS_LABELS),
            'areas' => array_map(fn($r) => ['value' => (string) $r['value'], 'label' => (string) $r['value']], $areas),
        ];
    }

    public function paginate(array $filters): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->where($filters);
        $from = 'association_memberships am INNER JOIN association_organizations ao ON ao.id=am.organization_id INNER JOIN citizens c ON c.id=am.citizen_id INNER JOIN households h ON h.id=c.household_id';
        $total = (int) (($this->fetchOne("SELECT COUNT(*) total FROM $from WHERE $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " WHERE $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        [$where, $params] = $this->where(['id' => $id], false);
        $row = $this->fetchOne($this->selectSql() . " WHERE am.id=:id AND $where", $params);
        return $row ? $this->normalize($row) : null;
    }

    public function searchCitizens(string $query, ?int $organizationId = null): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $where = ['c.status <> "DELETED"', $this->activeHouseholdWhere('h'), $this->tenantWhere('c', 'citizens'), $this->tenantWhere('h', 'households')];
        $params = $this->withTenant(['q' => '%' . mb_strtolower($query, 'UTF-8') . '%']);
        $where[] = '(LOWER(c.full_name) LIKE :q OR LOWER(c.citizen_code) LIKE :q OR LOWER(COALESCE(c.identity_number,"")) LIKE :q OR LOWER(h.household_code) LIKE :q)';
        $rows = $this->fetchAll('SELECT c.id,c.citizen_code,c.full_name,c.date_of_birth,c.gender,c.identity_number,c.phone,h.household_code,h.address,h.area_code FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.full_name,c.citizen_code LIMIT 20', $params);
        return array_map(fn($r) => ['id' => (int) $r['id'], 'citizen_code' => (string) $r['citizen_code'], 'full_name' => (string) $r['full_name'], 'date_of_birth' => $r['date_of_birth'], 'age' => $this->age($r['date_of_birth']), 'gender' => (string) $r['gender'], 'identity_number' => (string) $r['identity_number'], 'phone' => (string) $r['phone'], 'household_code' => (string) $r['household_code'], 'address' => (string) $r['address'], 'area_code' => (string) $r['area_code']], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $citizenId = (int) ($data['citizen_id'] ?? 0);
        $organizationId = (int) ($data['organization_id'] ?? 0);
        if (!$citizenId || !$organizationId) throw new \InvalidArgumentException('Nhân khẩu và tổ chức là bắt buộc');
        $citizen = $this->fetchOne('SELECT id FROM citizens WHERE id=:id AND status <> "DELETED" AND ' . $this->tenantWhere('citizens'), $this->withTenant(['id' => $citizenId]));
        if (!$citizen) throw new \InvalidArgumentException('Nhân khẩu không tồn tại');
        $org = $this->fetchOne('SELECT id FROM association_organizations WHERE id=:id AND status="ACTIVE" AND organization_type="ASSOCIATION" AND ' . $this->tenantWhere('association_organizations'), $this->withTenant(['id' => $organizationId]));
        if (!$org) throw new \InvalidArgumentException('Tổ chức không tồn tại');
        $duplicateSql = 'SELECT am.id FROM association_memberships am WHERE am.citizen_id=:citizen_id AND am.organization_id=:organization_id AND am.status NOT IN ("DELETED","LEFT") AND ' . $this->tenantWhere('am', 'association_memberships');
        $duplicateParams = $this->withTenant(['citizen_id' => $citizenId, 'organization_id' => $organizationId]);
        if ($id) { $duplicateSql .= ' AND am.id<>:membership_id'; $duplicateParams['membership_id'] = $id; }
        if ($this->fetchOne($duplicateSql, $duplicateParams)) throw new \InvalidArgumentException('Duplicate association membership');
        $params = [
            'citizen_id' => $citizenId, 'organization_id' => $organizationId,
            'membership_code' => $this->nullable($data['membership_code'] ?? null), 'card_number' => $this->nullable($data['card_number'] ?? null),
            'position_name' => $this->nullable($data['position_name'] ?? null), 'joined_date' => $this->dateOrNull($data['joined_date'] ?? null),
            'status' => $this->normalizeStatus($data['status'] ?? 'ACTIVE'), 'note' => $this->nullable($data['note'] ?? null), 'user' => $userId, 'created_by' => $userId, 'updated_by' => $userId,
        ];
        if ($id) {
            if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy thành viên');
            $params['id'] = $id;
            $this->execute('UPDATE association_memberships SET organization_id=:organization_id, membership_code=:membership_code, card_number=:card_number, position_name=:position_name, joined_date=:joined_date, status=:status, note=:note, updated_by=:user, deleted_at=NULL, deleted_by=NULL WHERE id=:id AND ' . $this->tenantWhere('association_memberships'), $this->withTenant($params));
        } else {
            $columns = ['citizen_id','organization_id','membership_code','card_number','position_name','joined_date','status','note','created_by','updated_by'];
            $this->addTenantInsert('association_memberships', $columns, $params);
            $id = $this->insert('INSERT INTO association_memberships (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        }
        return $this->find((int) $id) ?: [];
    }

    public function leave(int $id, int $userId): void
    {
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy thành viên');
        $this->execute('UPDATE association_memberships SET status="LEFT", updated_by=:user, deleted_at=NULL, deleted_by=NULL WHERE id=:id AND ' . $this->tenantWhere('association_memberships'), $this->withTenant(['id' => $id, 'user' => $userId]));
    }

    public function dashboard(array $filters = []): array
    {
        [$where, $params] = $this->where($filters, false);
        $row = $this->fetchOne('SELECT COUNT(*) total, COUNT(DISTINCT am.citizen_id) people FROM association_memberships am INNER JOIN association_organizations ao ON ao.id=am.organization_id INNER JOIN citizens c ON c.id=am.citizen_id INNER JOIN households h ON h.id=c.household_id WHERE ' . $where, $params) ?: [];
        $organizations = $this->fetchOne('SELECT COUNT(*) total FROM association_organizations ao WHERE ao.status="ACTIVE" AND ao.organization_type="ASSOCIATION" AND ' . $this->tenantWhere('ao', 'association_organizations'), $this->withTenant()) ?: [];
        return ['total' => (int) ($row['total'] ?? 0), 'people' => (int) ($row['people'] ?? 0), 'organizations' => (int) ($organizations['total'] ?? 0)];
    }

    public function report(array $filters): array
    {
        $filters['pageSize'] = 100;
        $data = $this->paginate($filters);
        return [
            'title' => $this->reportTitle((string) ($filters['organization_code'] ?? '')),
            'headers' => ['Mã nhân khẩu','Họ và tên','Ngày sinh','Tuổi','Giới tính','Mã hộ','Khu vực','Tổ chức','Chức vụ','Số thẻ','Ngày tham gia','Trạng thái'],
            'rows' => array_map(fn($r) => [$r['citizen_code'],$r['full_name'],$r['date_of_birth'],$r['age'],$r['gender'],$r['household_code'],$r['area_code'],$r['organization_name'],$r['position_name'],$r['card_number'],$r['joined_date'],$r['status_label']], $data['items']),
            'totalRows' => count($data['items']),
            'filters' => $filters,
        ];
    }

    private function selectSql(): string { return 'SELECT am.*,ao.code organization_code,ao.name organization_name,c.citizen_code,c.full_name,c.date_of_birth,c.gender,c.identity_number,c.phone,h.household_code,h.address,h.area_code FROM association_memberships am INNER JOIN association_organizations ao ON ao.id=am.organization_id INNER JOIN citizens c ON c.id=am.citizen_id INNER JOIN households h ON h.id=c.household_id'; }
    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['am.status <> "DELETED"', 'ao.organization_type="ASSOCIATION"', 'c.status <> "DELETED"', $this->activeHouseholdWhere('h'), $this->tenantWhere('am', 'association_memberships'), $this->tenantWhere('ao', 'association_organizations'), $this->tenantWhere('c', 'citizens'), $this->tenantWhere('h', 'households')];
        $params = $this->withTenant();
        if (isset($filters['id'])) { $where[] = 'am.id=:id'; $params['id'] = (int) $filters['id']; }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') { $where[] = '(LOWER(c.full_name) LIKE :q OR LOWER(c.citizen_code) LIKE :q OR LOWER(COALESCE(c.identity_number,"")) LIKE :q OR LOWER(h.household_code) LIKE :q OR LOWER(COALESCE(am.card_number,"")) LIKE :q)'; $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%'; }
        foreach (['organization_id' => 'am.organization_id', 'organization_code' => 'ao.code', 'status' => 'am.status', 'area_code' => 'h.area_code'] as $key => $column) { if (($value = trim((string) ($filters[$key] ?? ''))) !== '' && !($key === 'status' && strtoupper($value) === 'ALL')) { $where[] = "$column=:$key"; $params[$key] = $key === 'organization_id' ? (int) $value : strtoupper($value); } }
        if (!isset($filters['status']) || trim((string) $filters['status']) === '') $where[] = 'am.status IN ("ACTIVE","PAUSED")';
        foreach (['from_age' => '>=', 'to_age' => '<='] as $key => $op) { if (($value = trim((string) ($filters[$key] ?? ''))) !== '') { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) ' . $op . ' :' . $key; $params[$key] = (int) $value; } }
        $order = $withOrder ? $this->listOrder($filters, ['full_name'=>'c.full_name','date_of_birth'=>'c.date_of_birth','organization_name'=>'ao.name','joined_date'=>'am.joined_date','status'=>'am.status'], 'full_name', 'ASC', ['c.id ASC']) : '';
        return [implode(' AND ', $where), $params, $order];
    }
    private function normalize(array $r): array { $r['id']=(int)$r['id'];$r['citizen_id']=(int)$r['citizen_id'];$r['organization_id']=(int)$r['organization_id'];$r['age']=$this->age($r['date_of_birth']);$r['status_label']=self::STATUS_LABELS[$r['status']]??$r['status'];return $r; }
    private function positionCatalog(array $organizations): array
    {
        $items = [];
        foreach ($organizations as $org) {
            $code = (string) ($org['code'] ?? '');
            $positions = self::POSITION_CATALOG[$code] ?? self::DEFAULT_POSITIONS;
            foreach ($positions as $position) {
                $items[] = [
                    'value' => $position,
                    'label' => $position,
                    'organization_id' => (string) ($org['id'] ?? ''),
                    'organization_code' => $code,
                ];
            }
        }
        return $items;
    }
    private function age(?string $date): ?int { if (!$date) return null; try { $dob = new \DateTimeImmutable($date); $today = new \DateTimeImmutable('today'); return $dob > $today ? null : $dob->diff($today)->y; } catch (\Throwable) { return null; } }
    private function normalizeStatus(mixed $v): string { $v=strtoupper(trim((string)$v)); return array_key_exists($v,self::STATUS_LABELS)&&$v!=='DELETED'?$v:'ACTIVE'; }
    private function nullable(mixed $v): ?string { $v=trim((string)($v??''));return $v===''?null:$v; }
    private function dateOrNull(mixed $v): ?string { $v=trim((string)($v??''));if($v==='')return null;if(preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/',$v,$m))return sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[2],(int)$m[1]);return $v; }
    private function activeHouseholdWhere(string $a): string { return "$a.status NOT IN (\"DELETED\",\"ENDED\",\"MERGED\",\"TRANSFERRED_OUT\",\"MOVED_OUT\",\"INACTIVE\")"; }
    private function reportTitle(string $organizationCode): string
    {
        return match (strtoupper(trim($organizationCode))) {
            'FARMERS_UNION' => 'Báo cáo Hội viên Hội Nông dân',
            'WOMEN_UNION' => 'Báo cáo Hội viên Hội Phụ nữ',
            'VETERANS_UNION' => 'Báo cáo Hội viên Hội Cựu chiến binh',
            'YOUTH_UNION' => 'Báo cáo Đoàn viên Đoàn Thanh niên',
            default => 'Danh sách thành viên đoàn thể',
        };
    }
}
