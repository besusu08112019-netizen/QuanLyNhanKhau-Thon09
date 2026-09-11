<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;

final class Livestock extends BaseModel
{
    public const ANIMAL_TYPES = ["Tr\u{00E2}u","B\u{00F2}","D\u{00EA}","L\u{1EE3}n","Ch\u{00F3}","G\u{00E0}","V\u{1ECB}t","Ngan","Th\u{1ECF}","Chim","Kh\u{00E1}c"];
    public const FACILITY_TYPES = ["HOUSEHOLD"=>"H\u{1ED9} ch\u{0103}n nu\u{00F4}i","SMALL_FARM"=>"Gia tr\u{1EA1}i/c\u{01A1} s\u{1EDF} ch\u{0103}n nu\u{00F4}i","FARM"=>"Trang tr\u{1EA1}i ch\u{0103}n nu\u{00F4}i"];
    public const ANIMAL_GROUPS = [
        "PIG_SOW"=>["animal_type"=>"L\u{1EE3}n","label"=>"L\u{1EE3}n n\u{00E1}i"],
        "PIG_BOAR"=>["animal_type"=>"L\u{1EE3}n","label"=>"L\u{1EE3}n \u{0111}\u{1EF1}c gi\u{1ED1}ng","legacy"=>true],
        "PIGLET"=>["animal_type"=>"L\u{1EE3}n","label"=>"L\u{1EE3}n con theo m\u{1EB9}"],
        "PIG_MEAT"=>["animal_type"=>"L\u{1EE3}n","label"=>"L\u{1EE3}n th\u{1ECB}t"],
        "UNCLASSIFIED"=>["animal_type"=>"L\u{1EE3}n","label"=>"Ch\u{01B0}a ph\u{00E2}n lo\u{1EA1}i","legacy"=>true],
        "DOG_GUARD"=>["animal_type"=>"Ch\u{00F3}","label"=>"Ch\u{00F3} nu\u{00F4}i gi\u{1EEF} nh\u{00E0}"],
        "DOG_PET"=>["animal_type"=>"Ch\u{00F3}","label"=>"Ch\u{00F3} c\u{1EA3}nh"],
        "DOG_OTHER"=>["animal_type"=>"Ch\u{00F3}","label"=>"Ch\u{00F3} nu\u{00F4}i kh\u{00E1}c"],
    ];
    private const PIG_CREATE_GROUPS = ['PIG_SOW','PIGLET','PIG_MEAT'];
    private const PIG_LEGACY_GROUPS = ['UNCLASSIFIED','PIG_BOAR'];
    private const DOG_GROUPS = ['DOG_GUARD','DOG_PET','DOG_OTHER'];
    public const DISEASE_LABELS = ["NONE"=>"Kh\u{00F4}ng c\u{00F3} d\u{1ECB}ch","SUSPECTED"=>"Nghi d\u{1ECB}ch","INFECTED"=>"C\u{00F3} d\u{1ECB}ch b\u{1EC7}nh","RECOVERED"=>"\u{0110}\u{00E3} x\u{1EED} l\u{00FD}"];
    public const STATUS_LABELS = ["ACTIVE"=>"\u{0110}ang ho\u{1EA1}t \u{0111}\u{1ED9}ng","PAUSED"=>"T\u{1EA1}m d\u{1EEB}ng","INACTIVE"=>"Ng\u{1EEB}ng ho\u{1EA1}t \u{0111}\u{1ED9}ng","DELETED"=>"\u{0110}\u{00E3} x\u{00F3}a"];

    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
    }

    private function assertSchemaReady(): void
    {
        foreach (array_keys($this->requiredColumnContracts()) as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException('Livestock schema is not provisioned: missing table ' . $table);
            }
        }
        foreach ($this->requiredColumnContracts() as $table => $columns) {
            foreach ($columns as $column => $expected) {
                $actual = $this->columnContract($table, $column);
                if ($actual === null) {
                    throw new RuntimeException('Livestock schema is not provisioned: missing column ' . $table . '.' . $column);
                }
                if ($actual !== $expected) {
                    throw new RuntimeException('Livestock schema is not provisioned: column contract drift ' . $table . '.' . $column);
                }
            }
        }
        foreach ($this->requiredIndexes() as $table => $indexes) {
            foreach ($indexes as $index => $columns) {
                if (!$this->indexExists($table, $index, $columns)) {
                    throw new RuntimeException('Livestock schema is not provisioned: missing index ' . $table . '.' . $index);
                }
            }
        }
        foreach ($this->requiredForeignKeys() as $table => $foreignKeys) {
            foreach ($foreignKeys as $foreignKey) {
                if (!$this->foreignKeyExists($table, ...$foreignKey)) {
                    throw new RuntimeException('Livestock schema is not provisioned: missing foreign key ' . $foreignKey[0]);
                }
            }
        }
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        $activeStatuses = array_filter(self::STATUS_LABELS, fn($k) => $k !== 'DELETED', ARRAY_FILTER_USE_KEY);
        $selectableFacilityTypes = array_intersect_key(self::FACILITY_TYPES, array_flip(['HOUSEHOLD', 'FARM']));
        return [
            'animal_types'=>array_map(fn($v)=>['value'=>$v,'label'=>$v], self::ANIMAL_TYPES),
            'animal_groups'=>array_map(fn($k,$v)=>['value'=>$k,'label'=>$v['label'],'animal_type'=>$v['animal_type'],'legacy'=>!empty($v['legacy']),'selectable'=>empty($v['legacy'])], array_keys(self::ANIMAL_GROUPS), array_values(self::ANIMAL_GROUPS)),
            'facility_types'=>array_map(fn($k,$v)=>['value'=>$k,'label'=>$v], array_keys($selectableFacilityTypes), array_values($selectableFacilityTypes)),
            'disease_statuses'=>array_map(fn($k,$v)=>['value'=>$k,'label'=>$v], array_keys(self::DISEASE_LABELS), array_values(self::DISEASE_LABELS)),
            'statuses'=>array_map(fn($k,$v)=>['value'=>$k,'label'=>$v], array_keys($activeStatuses), array_values($activeStatuses)),
            'areas'=>$this->areaCatalog(),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page,$pageSize,$offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where,$params,$having,$order] = $this->facilityWhere($filters);
        $sql = $this->facilitySql($where, $having);
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM ($sql) x", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($sql . " $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($r)=>$this->normalizeFacility($r), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne($this->facilitySql('WHERE lf.id=:id AND lf.status <> "DELETED" AND '.$this->tenantWhere('lf','livestock_facilities'), '') . ' LIMIT 1', $this->withTenant(['id'=>$id]));
        if ($row) return $this->normalizeFacility($row);
        $legacy = $this->fetchOne('SELECT facility_id FROM livestock WHERE id=:id AND status <> "DELETED" AND '.$this->tenantWhere('livestock'), $this->withTenant(['id'=>$id]));
        return $legacy ? $this->find((int)$legacy['facility_id']) : null;
    }

    public function findByHousehold(int $householdId): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll($this->facilitySql('WHERE lf.household_id=:household_id AND lf.status <> "DELETED" AND '.$this->tenantWhere('lf','livestock_facilities'), '') . ' ORDER BY lf.facility_type DESC, lf.id DESC', $this->withTenant(['household_id'=>$householdId]));
        return array_map(fn($r)=>$this->normalizeFacility($r), $rows);
    }
    public function searchHouseholds(string $query, int $limit = 10): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll('SELECT h.id,h.household_code,h.head_citizen_name,h.address,h.phone,h.latitude,h.longitude,COALESCE(lc.facility_count,0) AS livestock_count FROM households h LEFT JOIN (SELECT household_id,COUNT(*) AS facility_count FROM livestock_facilities WHERE status <> "DELETED" AND '.$this->tenantWhere('livestock_facilities').' GROUP BY household_id) lc ON lc.household_id=h.id WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") AND '.$this->tenantWhere('h','households').' AND (LOWER(h.household_code) LIKE :code OR LOWER(h.head_citizen_name) LIKE :head OR LOWER(h.address) LIKE :address) ORDER BY h.household_code ASC LIMIT '.max(1,min(20,$limit)), $this->withTenant(['code'=>$keyword,'head'=>$keyword,'address'=>$keyword]));
        return array_map(fn($r)=>['id'=>(int)$r['id'],'household_code'=>(string)$r['household_code'],'head_citizen_name'=>(string)$r['head_citizen_name'],'address'=>(string)($r['address']??''),'phone'=>(string)($r['phone']??''),'latitude'=>$r['latitude']!==null&&$r['latitude']!==''?(float)$r['latitude']:null,'longitude'=>$r['longitude']!==null&&$r['longitude']!==''?(float)$r['longitude']:null,'livestock_count'=>(int)($r['livestock_count']??0)], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $facility = $this->facilityParams($data, $userId);
        $groups = $this->groupParamsList($data, $id);
        if ($id && !$this->facilityExists($id)) throw new RuntimeException('Khong tim thay co so chan nuoi');
        if ($id) {
            $facility['id'] = $id;
            $this->execute('UPDATE livestock_facilities SET household_id=:household_id, owner_name=:owner_name, facility_name=:facility_name, facility_type=:facility_type, location=:location, area_code=:area_code, farming_area_m2=:farming_area_m2, status=:status, note=:note, updated_by=:user WHERE id=:id AND '.$this->tenantWhere('livestock_facilities'), $this->withTenant($facility));
            $this->replaceGroups($id, $facility['household_id'], $groups, $userId);
            return $this->find($id);
        }
        $columns = ['household_id','owner_name','facility_name','facility_type','location','area_code','farming_area_m2','status','note','created_by','updated_by'];
        $insert = $facility + ['created_by'=>$userId,'updated_by'=>$userId];
        unset($insert['user']);
        $this->addTenantInsert('livestock_facilities', $columns, $insert);
        $newId = $this->insert('INSERT INTO livestock_facilities ('.implode(',',$columns).') VALUES (:'.implode(',:',$columns).')', $insert);
        $this->replaceGroups($newId, $facility['household_id'], $groups, $userId);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->facilityExists($id)) throw new RuntimeException('Khong tim thay co so chan nuoi');
        $this->execute('UPDATE livestock_facilities SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND '.$this->tenantWhere('livestock_facilities'), $this->withTenant(['id'=>$id,'user'=>$userId]));
        $this->execute('UPDATE livestock SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE facility_id=:id AND '.$this->tenantWhere('livestock'), $this->withTenant(['id'=>$id,'user'=>$userId]));
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where,$params] = $this->groupWhere($filters);
        $row = $this->fetchOne("SELECT COUNT(DISTINCT l.household_id) AS livestock_households, COUNT(DISTINCT lf.id) AS facility_total, COUNT(DISTINCT CASE WHEN lf.facility_type='FARM' THEN lf.id END) AS farm_total, COALESCE(SUM(l.quantity),0) AS livestock_total, COALESCE(SUM(CASE WHEN l.animal_type=" . $this->pigSql() . " THEN l.quantity ELSE 0 END),0) AS pig_total, COALESCE(SUM(CASE WHEN l.animal_group='PIG_SOW' THEN l.quantity ELSE 0 END),0) AS pig_sow_total, COALESCE(SUM(CASE WHEN l.animal_group='PIG_MEAT' THEN l.quantity ELSE 0 END),0) AS pig_meat_total, COALESCE(SUM(CASE WHEN l.animal_group='PIGLET' THEN l.quantity ELSE 0 END),0) AS piglet_total, COALESCE(SUM(CASE WHEN l.animal_group='PIG_BOAR' THEN l.quantity ELSE 0 END),0) AS pig_boar_total, COUNT(DISTINCT CASE WHEN l.animal_type=" . $this->pigSql() . " THEN l.household_id END) AS pig_households, COUNT(DISTINCT CASE WHEN l.animal_type=" . $this->pigSql() . " AND lf.facility_type='FARM' THEN lf.id END) AS pig_farms, COALESCE(SUM(CASE WHEN l.animal_type=" . $this->dogSql() . " THEN l.quantity ELSE 0 END),0) AS dog_total, COUNT(DISTINCT CASE WHEN l.animal_type=" . $this->dogSql() . " THEN l.household_id END) AS dog_households, COALESCE(SUM(CASE WHEN l.animal_type=" . $this->dogSql() . " AND l.vaccinated=1 THEN l.quantity ELSE 0 END),0) AS dog_vaccinated_total, COALESCE(SUM(CASE WHEN l.animal_type=" . $this->dogSql() . " AND l.vaccinated=0 THEN l.quantity ELSE 0 END),0) AS dog_unrecorded_vaccination_total, COUNT(DISTINCT CASE WHEN l.vaccinated=1 THEN l.household_id END) AS vaccinated_households, COUNT(DISTINCT CASE WHEN l.disease_status='INFECTED' THEN l.household_id END) AS disease_households FROM livestock l INNER JOIN livestock_facilities lf ON lf.id=l.facility_id INNER JOIN households h ON h.id=lf.household_id $where", $params) ?: [];
        return array_map('intval', ['livestock_households'=>$row['livestock_households']??0,'facility_total'=>$row['facility_total']??0,'farm_total'=>$row['farm_total']??0,'livestock_total'=>$row['livestock_total']??0,'pig_total'=>$row['pig_total']??0,'pig_sow_total'=>$row['pig_sow_total']??0,'pig_meat_total'=>$row['pig_meat_total']??0,'piglet_total'=>$row['piglet_total']??0,'pig_boar_total'=>$row['pig_boar_total']??0,'pig_households'=>$row['pig_households']??0,'pig_farms'=>$row['pig_farms']??0,'dog_total'=>$row['dog_total']??0,'dog_households'=>$row['dog_households']??0,'dog_vaccinated_total'=>$row['dog_vaccinated_total']??0,'dog_unrecorded_vaccination_total'=>$row['dog_unrecorded_vaccination_total']??0,'vaccinated_households'=>$row['vaccinated_households']??0,'disease_households'=>$row['disease_households']??0]);
    }

    public function charts(array $filters = []): array
    {
        $this->ensureSchema();
        [$where,$params] = $this->groupWhere($filters);
        return [
            'types'=>$this->fetchAll("SELECT l.animal_type AS label,COALESCE(SUM(l.quantity),0) AS value FROM livestock l INNER JOIN livestock_facilities lf ON lf.id=l.facility_id INNER JOIN households h ON h.id=lf.household_id $where GROUP BY l.animal_type ORDER BY value DESC,l.animal_type", $params),
            'groups'=>$this->fetchAll("SELECT COALESCE(NULLIF(l.animal_group,''),'UNCLASSIFIED') AS code,COALESCE(SUM(l.quantity),0) AS value FROM livestock l INNER JOIN livestock_facilities lf ON lf.id=l.facility_id INNER JOIN households h ON h.id=lf.household_id $where GROUP BY code ORDER BY value DESC", $params),
            'areas'=>$this->fetchAll("SELECT COALESCE(NULLIF(lf.area_code,''),NULLIF(h.area_code,''),'Chua phan khu') AS label,COALESCE(SUM(l.quantity),0) AS value FROM livestock l INNER JOIN livestock_facilities lf ON lf.id=l.facility_id INNER JOIN households h ON h.id=lf.household_id $where GROUP BY label ORDER BY value DESC,label LIMIT 20", $params),
        ];
    }

    public function topHouseholds(array $filters = []): array
    {
        $this->ensureSchema();
        [$where,$params] = $this->groupWhere($filters);
        $rows = $this->fetchAll("SELECT h.id AS household_id,h.household_code,h.head_citizen_name,COUNT(DISTINCT lf.id) AS facility_count,COALESCE(SUM(l.quantity),0) AS livestock_total FROM livestock l INNER JOIN livestock_facilities lf ON lf.id=l.facility_id INNER JOIN households h ON h.id=lf.household_id $where GROUP BY h.id,h.household_code,h.head_citizen_name ORDER BY livestock_total DESC,facility_count DESC LIMIT 10", $params);
        return array_map(fn($r)=>['household_id'=>(int)$r['household_id'],'household_code'=>(string)$r['household_code'],'head_citizen_name'=>(string)$r['head_citizen_name'],'facility_count'=>(int)$r['facility_count'],'livestock_total'=>(int)$r['livestock_total']], $rows);
    }

    public function report(string $mode, array $filters = []): array
    {
        if ($mode === 'pig_farms') { $filters['animal_type']=$this->pigType(); $filters['facility_type']='FARM'; }
        if ($mode === 'pig_sow') $filters['animal_group']='PIG_SOW';
        if ($mode === 'pig_meat') $filters['animal_group']='PIG_MEAT';
        if ($mode === 'pig_sow_and_meat') { $filters['has_pig_sow']='1'; $filters['has_pig_meat']='1'; }
        if ($mode === 'vaccinated') $filters['vaccinated']='1';
        if ($mode === 'unvaccinated') $filters['vaccinated']='0';
        if ($mode === 'disease') $filters['disease_status']='INFECTED';
        $filters['page']=1; $filters['pageSize']=100;
        $rows = $this->paginate($filters)['items'];
        $title = match($mode) { 'pig_farms'=>'Danh sach trang trai lon', 'pig_sow'=>'Danh sach ho nuoi lon nai', 'pig_meat'=>'Danh sach ho nuoi lon thit', 'pig_sow_and_meat'=>'Danh sach ho vua nuoi lon nai vua nuoi lon thit', 'by_type'=>'Tong dan theo loai vat nuoi', default=>'Danh sach co so chan nuoi' };
        return $this->table($title, ['Ho/Chu co so','Loai hinh','Ten co so','Vat nuoi','Tong dan','Dia diem','Trang thai','Chi tiet'], array_map(fn($r)=>[$r['owner_name'] ?: $r['head_citizen_name'],$r['facility_type_label'],$r['facility_name'],$r['animal_summary'],$this->reportQuantity($r,$filters),$r['location'],$r['status_label'],$r['group_summary']], $rows), $filters);
    }
    private function reportQuantity(array $row, array $filters): int
    {
        $group = strtoupper((string)($filters['animal_group'] ?? ''));
        return match ($group) {
            'PIG_SOW' => (int)($row['pig_sow_total'] ?? 0),
            'PIG_MEAT' => (int)($row['pig_meat_total'] ?? 0),
            'PIGLET' => (int)($row['piglet_total'] ?? 0),
            'PIG_BOAR' => (int)($row['pig_boar_total'] ?? 0),
            default => ((string)($filters['animal_type'] ?? '') === $this->pigType() ? (int)($row['pig_total'] ?? 0) : (int)($row['total_quantity'] ?? 0)),
        };
    }
    private function facilitySql(string $where, string $having): string
    {
        $detail = "GROUP_CONCAT(CONCAT(l.id, CHAR(31), l.animal_type, CHAR(31), COALESCE(l.animal_group,''), CHAR(31), COALESCE(l.breed,''), CHAR(31), l.quantity, CHAR(31), l.vaccinated, CHAR(31), COALESCE(l.vaccine_date,''), CHAR(31), l.disease_status, CHAR(31), COALESCE(l.barn_area,''), CHAR(31), COALESCE(l.note,'')) ORDER BY l.animal_type,l.animal_group,l.id SEPARATOR 0x1E) AS group_blob";
        return "SELECT lf.*,h.household_code,h.head_citizen_name,h.phone AS household_phone,h.address AS household_address,h.area_code AS household_area_code,h.latitude,h.longitude,COUNT(l.id) AS group_count,COALESCE(SUM(l.quantity),0) AS total_quantity,COALESCE(SUM(CASE WHEN l.animal_type=" . $this->pigSql() . " THEN l.quantity ELSE 0 END),0) AS pig_total,COALESCE(SUM(CASE WHEN l.animal_group='PIG_SOW' THEN l.quantity ELSE 0 END),0) AS pig_sow_total,COALESCE(SUM(CASE WHEN l.animal_group='PIG_BOAR' THEN l.quantity ELSE 0 END),0) AS pig_boar_total,COALESCE(SUM(CASE WHEN l.animal_group='PIGLET' THEN l.quantity ELSE 0 END),0) AS piglet_total,COALESCE(SUM(CASE WHEN l.animal_group='PIG_MEAT' THEN l.quantity ELSE 0 END),0) AS pig_meat_total,COALESCE(SUM(CASE WHEN l.animal_type=" . $this->dogSql() . " THEN l.quantity ELSE 0 END),0) AS dog_total,COALESCE(SUM(CASE WHEN l.animal_type=" . $this->dogSql() . " AND l.vaccinated=1 THEN l.quantity ELSE 0 END),0) AS dog_vaccinated_total,COALESCE(SUM(CASE WHEN l.animal_type=" . $this->dogSql() . " AND l.vaccinated=0 THEN l.quantity ELSE 0 END),0) AS dog_unrecorded_vaccination_total,GROUP_CONCAT(DISTINCT l.animal_type ORDER BY l.animal_type SEPARATOR ', ') AS animal_types,$detail FROM livestock_facilities lf INNER JOIN households h ON h.id=lf.household_id LEFT JOIN livestock l ON l.facility_id=lf.id AND l.status <> 'DELETED' AND ".$this->tenantWhere('l','livestock')." $where GROUP BY lf.id,lf.village_id,lf.household_id,lf.owner_name,lf.facility_name,lf.facility_type,lf.location,lf.area_code,lf.farming_area_m2,lf.status,lf.note,lf.created_at,lf.updated_at,lf.created_by,lf.updated_by,lf.deleted_at,lf.deleted_by,h.household_code,h.head_citizen_name,h.phone,h.address,h.area_code,h.latitude,h.longitude $having";
    }

    private function facilityWhere(array $filters): array
    {
        [$where,$params] = $this->facilityBaseWhere($filters);
        $having = [];
        if (!empty($filters['has_pig_sow'])) $having[]='pig_sow_total > 0';
        if (!empty($filters['has_pig_meat'])) $having[]='pig_meat_total > 0';
        if (!empty($filters['has_piglet'])) $having[]='piglet_total > 0';
        if (!empty($filters['has_pig_boar'])) $having[]='pig_boar_total > 0';
        $sortMap = ['household_code'=>'h.household_code','owner_name'=>'lf.owner_name','facility_type'=>'lf.facility_type','facility_name'=>'lf.facility_name','total_quantity'=>'total_quantity','status'=>'lf.status','updated_at'=>'COALESCE(lf.updated_at,lf.created_at)'];
        return ['WHERE '.implode(' AND ',$where), $params, $having ? 'HAVING '.implode(' AND ',$having) : '', $this->listOrder($filters,$sortMap,'household_code','ASC',['lf.id DESC'])];
    }

    private function facilityBaseWhere(array $filters): array
    {
        $where = ['lf.status <> "DELETED"',$this->tenantWhere('lf','livestock_facilities'),$this->tenantWhere('h','households'),'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")'];
        $params = $this->withTenant();
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $params['search']='%'.mb_strtolower($search,'UTF-8').'%'; $where[]='(LOWER(h.household_code) LIKE :search OR LOWER(h.head_citizen_name) LIKE :search OR LOWER(h.address) LIKE :search OR LOWER(lf.owner_name) LIKE :search OR LOWER(lf.facility_name) LIKE :search OR LOWER(lf.location) LIKE :search)'; }
        $facilityType = strtoupper(trim((string)($filters['facility_type'] ?? $filters['facilityType'] ?? '')));
        if ($facilityType !== '') { $where[]='lf.facility_type=:facility_type'; $params['facility_type']=$facilityType; }
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        if ($status !== '') { $where[]='lf.status=:status'; $params['status']=$status; }
        $householdId = (int)($filters['household_id'] ?? $filters['householdId'] ?? 0);
        if ($householdId > 0) { $where[]='lf.household_id=:household_id'; $params['household_id']=$householdId; }
        $area = trim((string)($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') { $where[]='(lf.area_code=:area_code OR ((lf.area_code IS NULL OR lf.area_code="") AND h.area_code=:area_code))'; $params['area_code']=$area; }
        $animalType = trim((string)($filters['animal_type'] ?? $filters['animalType'] ?? ''));
        if ($animalType !== '') { $where[]='EXISTS (SELECT 1 FROM livestock lx WHERE lx.facility_id=lf.id AND lx.status <> "DELETED" AND lx.animal_type=:animal_type AND '.$this->tenantWhere('lx','livestock').')'; $params['animal_type']=$animalType; }
        $animalGroup = strtoupper(trim((string)($filters['animal_group'] ?? $filters['animalGroup'] ?? '')));
        if ($animalGroup !== '') { $where[]='EXISTS (SELECT 1 FROM livestock lg WHERE lg.facility_id=lf.id AND lg.status <> "DELETED" AND lg.animal_group=:animal_group AND '.$this->tenantWhere('lg','livestock').')'; $params['animal_group']=$animalGroup; }
        return [$where,$params];
    }

    private function groupWhere(array $filters): array
    {
        [$where,$params] = $this->facilityBaseWhere($filters);
        $where[]='l.status <> "DELETED"'; $where[]=$this->tenantWhere('l','livestock');
        $disease = strtoupper(trim((string)($filters['disease_status'] ?? $filters['diseaseStatus'] ?? '')));
        if ($disease !== '') { $where[]='l.disease_status=:disease_status'; $params['disease_status']=$disease; }
        $vaccinated = trim((string)($filters['vaccinated'] ?? ''));
        if ($vaccinated === '1' || $vaccinated === '0') $where[]='l.vaccinated='.(int)$vaccinated;
        return ['WHERE '.implode(' AND ',$where), $params];
    }

    private function facilityParams(array $data, int $userId): array
    {
        $householdId = (int)($data['household_id'] ?? $data['householdId'] ?? 0);
        if ($householdId <= 0) throw new RuntimeException('Ho gia dinh la bat buoc');
        $household = $this->fetchOne('SELECT id,head_citizen_name,address,area_code FROM households h WHERE h.id=:id AND '.$this->tenantWhere('h','households').' AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', $this->withTenant(['id'=>$householdId]));
        if (!$household) throw new RuntimeException('Khong tim thay ho gia dinh');
        $type = strtoupper(trim((string)($data['facility_type'] ?? $data['facilityType'] ?? 'HOUSEHOLD')));
        if (!isset(self::FACILITY_TYPES[$type])) $type='HOUSEHOLD';
        $status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
        if (!isset(self::STATUS_LABELS[$status]) || $status === 'DELETED') $status='ACTIVE';
        return ['household_id'=>$householdId,'owner_name'=>trim((string)($data['owner_name'] ?? $data['ownerName'] ?? $household['head_citizen_name'] ?? '')) ?: null,'facility_name'=>trim((string)($data['facility_name'] ?? $data['facilityName'] ?? '')) ?: null,'facility_type'=>$type,'location'=>trim((string)($data['location'] ?? $data['address'] ?? $household['address'] ?? '')) ?: null,'area_code'=>trim((string)($data['area_code'] ?? $data['areaCode'] ?? $household['area_code'] ?? '')) ?: null,'farming_area_m2'=>$this->nullableDecimal($data['farming_area_m2'] ?? $data['farmingAreaM2'] ?? null),'status'=>$status,'note'=>trim((string)($data['facility_note'] ?? $data['facilityNote'] ?? $data['note'] ?? '')) ?: null,'user'=>$userId];
    }

    private function groupParamsList(array $data, ?int $facilityId = null): array
    {
        $raw = $data['groups'] ?? $data['animal_groups'] ?? null;
        if (is_string($raw)) $raw = json_decode($raw, true);
        $groups = is_array($raw) ? $raw : [['animal_type'=>$data['animal_type'] ?? $data['animalType'] ?? '', 'animal_group'=>$data['animal_group'] ?? $data['animalGroup'] ?? null, 'breed'=>$data['breed'] ?? '', 'quantity'=>$data['quantity'] ?? 0, 'vaccinated'=>$data['vaccinated'] ?? 0, 'vaccine_date'=>$data['vaccine_date'] ?? $data['vaccineDate'] ?? null, 'disease_status'=>$data['disease_status'] ?? $data['diseaseStatus'] ?? 'NONE', 'barn_area'=>$data['barn_area'] ?? $data['barnArea'] ?? '', 'note'=>$data['group_note'] ?? null]];
        $existingLegacy = $facilityId ? $this->existingLegacyGroups($facilityId) : [];
        $out=[];
        foreach ($groups as $item) {
            if (!is_array($item)) continue;
            $animalType = trim((string)($item['animal_type'] ?? $item['animalType'] ?? ''));
            if ($animalType === '') continue;
            if (!in_array($animalType, self::ANIMAL_TYPES, true)) throw new RuntimeException('Loai vat nuoi khong hop le');
            $group = strtoupper(trim((string)($item['animal_group'] ?? $item['animalGroup'] ?? '')));
            if ($animalType === $this->pigType()) {
                $allowed = array_merge(self::PIG_CREATE_GROUPS, array_intersect(self::PIG_LEGACY_GROUPS, $existingLegacy));
                if (!in_array($group, $allowed, true)) throw new RuntimeException('Nhom lon khong hop le');
            } elseif ($animalType === $this->dogType()) {
                if (!in_array($group, self::DOG_GROUPS, true)) throw new RuntimeException('Muc dich nuoi cho khong hop le');
            } else {
                $group = '';
            }
            $disease = strtoupper(trim((string)($item['disease_status'] ?? $item['diseaseStatus'] ?? 'NONE')));
            if (!isset(self::DISEASE_LABELS[$disease])) $disease='NONE';
            $out[]=['animal_type'=>$animalType,'animal_group'=>$group ?: null,'breed'=>trim((string)($item['breed'] ?? '')) ?: null,'quantity'=>max(0,(int)($item['quantity'] ?? 0)),'unit'=>trim((string)($item['unit'] ?? 'con')) ?: 'con','vaccinated'=>!empty($item['vaccinated']) && $item['vaccinated'] !== '0' ? 1 : 0,'vaccine_date'=>trim((string)($item['vaccine_date'] ?? $item['vaccineDate'] ?? '')) ?: null,'disease_status'=>$disease,'barn_area'=>trim((string)($item['barn_area'] ?? $item['barnArea'] ?? '')) ?: null,'note'=>trim((string)($item['note'] ?? '')) ?: null];
        }
        if (!$out) throw new RuntimeException('Can nhap it nhat mot nhom vat nuoi');
        return $out;
    }
    private function replaceGroups(int $facilityId, int $householdId, array $groups, int $userId): void
    {
        $this->execute('UPDATE livestock SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE facility_id=:facility_id AND '.$this->tenantWhere('livestock'), $this->withTenant(['facility_id'=>$facilityId,'user'=>$userId]));
        foreach ($groups as $group) {
            $columns = ['facility_id','household_id','animal_type','animal_group','breed','quantity','unit','vaccinated','vaccine_date','disease_status','barn_area','status','note','created_by','updated_by'];
            $params = $group + ['facility_id'=>$facilityId,'household_id'=>$householdId,'status'=>'ACTIVE','created_by'=>$userId,'updated_by'=>$userId];
            $this->addTenantInsert('livestock', $columns, $params);
            $this->insert('INSERT INTO livestock ('.implode(',',$columns).') VALUES (:'.implode(',:',$columns).')', $params);
        }
    }

    private function existingLegacyGroups(int $facilityId): array
    {
        $rows = $this->fetchAll('SELECT DISTINCT animal_group FROM livestock WHERE facility_id=:facility_id AND status <> "DELETED" AND animal_group IN ("UNCLASSIFIED","PIG_BOAR") AND '.$this->tenantWhere('livestock'), $this->withTenant(['facility_id'=>$facilityId]));
        return array_values(array_filter(array_map(fn($r)=>strtoupper((string)($r['animal_group'] ?? '')), $rows)));
    }

    private function normalizeFacility(array $row): array
    {
        $groups = $this->parseGroupBlob((string)($row['group_blob'] ?? ''));
        return ['id'=>(int)$row['id'],'facility_id'=>(int)$row['id'],'household_id'=>(int)$row['household_id'],'household_code'=>(string)($row['household_code'] ?? ''),'head_citizen_name'=>(string)($row['head_citizen_name'] ?? ''),'owner_name'=>(string)($row['owner_name'] ?? $row['head_citizen_name'] ?? ''),'facility_name'=>(string)($row['facility_name'] ?? ''),'facility_type'=>(string)($row['facility_type'] ?? 'HOUSEHOLD'),'facility_type_label'=>self::FACILITY_TYPES[$row['facility_type'] ?? 'HOUSEHOLD'] ?? "H\u{1ED9} ch\u{0103}n nu\u{00F4}i",'location'=>(string)($row['location'] ?? $row['household_address'] ?? ''),'address'=>(string)($row['household_address'] ?? ''),'phone'=>(string)($row['household_phone'] ?? ''),'area_code'=>(string)($row['area_code'] ?: ($row['household_area_code'] ?? '')),'farming_area_m2'=>$row['farming_area_m2']!==null&&$row['farming_area_m2']!==''?(float)$row['farming_area_m2']:null,'animal_types'=>array_values(array_unique(array_map(fn($g)=>$g['animal_type'],$groups))),'animal_summary'=>$this->animalSummary($groups),'group_summary'=>implode('; ',array_map(fn($g)=>$g['group_label'].': '.$g['quantity'],$groups)),'group_count'=>(int)($row['group_count'] ?? count($groups)),'total_quantity'=>(int)($row['total_quantity'] ?? array_sum(array_column($groups,'quantity'))),'quantity'=>(int)($row['total_quantity'] ?? 0),'pig_total'=>(int)($row['pig_total'] ?? 0),'pig_sow_total'=>(int)($row['pig_sow_total'] ?? 0),'pig_boar_total'=>(int)($row['pig_boar_total'] ?? 0),'piglet_total'=>(int)($row['piglet_total'] ?? 0),'pig_meat_total'=>(int)($row['pig_meat_total'] ?? 0),'dog_total'=>(int)($row['dog_total'] ?? 0),'dog_vaccinated_total'=>(int)($row['dog_vaccinated_total'] ?? 0),'dog_unrecorded_vaccination_total'=>(int)($row['dog_unrecorded_vaccination_total'] ?? 0),'status'=>(string)($row['status'] ?? 'ACTIVE'),'status_label'=>self::STATUS_LABELS[$row['status'] ?? 'ACTIVE'] ?? "\u{0110}ang ho\u{1EA1}t \u{0111}\u{1ED9}ng",'note'=>(string)($row['note'] ?? ''),'latitude'=>$row['latitude']!==null&&$row['latitude']!==''?(float)$row['latitude']:null,'longitude'=>$row['longitude']!==null&&$row['longitude']!==''?(float)$row['longitude']:null,'created_at'=>$row['created_at'] ?? null,'updated_at'=>$row['updated_at'] ?? null,'groups'=>$groups];
    }

    private function parseGroupBlob(string $blob): array
    {
        if ($blob === '') return [];
        $groups=[];
        foreach (explode(chr(30), $blob) as $part) {
            $p = explode(chr(31), $part, 10);
            if (count($p) < 10) continue;
            [$id,$type,$group,$breed,$qty,$vaccinated,$vaccineDate,$disease,$barn,$note] = $p;
            $groups[] = ['id'=>(int)$id,'animal_type'=>$type,'animal_group'=>$group ?: null,'group_label'=>$this->groupLabel($type,$group),'breed'=>$breed,'quantity'=>(int)$qty,'unit'=>'con','vaccinated'=>(int)$vaccinated===1,'vaccine_date'=>$vaccineDate ?: null,'disease_status'=>$disease,'disease_status_label'=>self::DISEASE_LABELS[$disease] ?? "Kh\u{00F4}ng c\u{00F3} d\u{1ECB}ch",'barn_area'=>$barn,'note'=>$note];
        }
        return $groups;
    }

    private function animalSummary(array $groups): string
    {
        $totals=[];
        foreach ($groups as $g) $totals[$g['animal_type']] = ($totals[$g['animal_type']] ?? 0) + (int)$g['quantity'];
        return implode(', ', array_map(fn($type,$qty)=>$type.': '.$qty, array_keys($totals), array_values($totals)));
    }

    private function groupLabel(string $animalType, ?string $group): string
    {
        if ($group && isset(self::ANIMAL_GROUPS[$group])) return self::ANIMAL_GROUPS[$group]['label'];
        return $animalType;
    }

    private function facilityExists(int $id): bool
    {
        return (bool)$this->fetchOne('SELECT id FROM livestock_facilities WHERE id=:id AND status <> "DELETED" AND '.$this->tenantWhere('livestock_facilities'), $this->withTenant(['id'=>$id]));
    }

    private function areaCatalog(): array
    {
        return $this->fetchAll('SELECT DISTINCT COALESCE(NULLIF(area_code,""),"") AS value,COALESCE(NULLIF(area_code,""),"Chua phan khu") AS label FROM households WHERE '.$this->tenantWhere('households').' ORDER BY label', $this->withTenant());
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || trim((string)$value) === '') return null;
        return max(0, (float)$value);
    }

    private function requiredColumnContracts(): array
    {
        return [
            'livestock_facilities' => [
                'id' => 'bigint(20) unsigned|NO|NULL|auto_increment',
                'village_id' => 'bigint(20) unsigned|YES|NULL|',
                'household_id' => 'bigint(20) unsigned|NO|NULL|',
                'owner_name' => 'varchar(160)|YES|NULL|',
                'facility_name' => 'varchar(180)|YES|NULL|',
                'facility_type' => "enum('household','small_farm','farm')|NO|HOUSEHOLD|",
                'location' => 'varchar(255)|YES|NULL|',
                'area_code' => 'varchar(80)|YES|NULL|',
                'farming_area_m2' => 'decimal(12,2)|YES|NULL|',
                'status' => "enum('active','paused','inactive','deleted')|NO|ACTIVE|",
                'note' => 'text|YES|NULL|',
                'created_at' => 'datetime|NO|current_timestamp()|',
                'updated_at' => 'datetime|YES|NULL|on update current_timestamp()',
                'created_by' => 'bigint(20) unsigned|YES|NULL|',
                'updated_by' => 'bigint(20) unsigned|YES|NULL|',
                'deleted_at' => 'datetime|YES|NULL|',
                'deleted_by' => 'bigint(20) unsigned|YES|NULL|',
            ],
            'livestock' => [
                'id' => 'bigint(20) unsigned|NO|NULL|auto_increment',
                'village_id' => 'bigint(20) unsigned|NO|1|',
                'facility_id' => 'bigint(20) unsigned|YES|NULL|',
                'household_id' => 'bigint(20) unsigned|NO|NULL|',
                'animal_type' => 'varchar(80)|NO|NULL|',
                'animal_group' => 'varchar(80)|YES|NULL|',
                'breed' => 'varchar(120)|YES|NULL|',
                'quantity' => 'int(10) unsigned|NO|0|',
                'unit' => 'varchar(30)|NO|con|',
                'vaccinated' => 'tinyint(1)|NO|0|',
                'vaccine_date' => 'date|YES|NULL|',
                'disease_status' => "enum('none','suspected','infected','recovered')|NO|NONE|",
                'barn_area' => 'varchar(255)|YES|NULL|',
                'status' => "enum('active','paused','inactive','deleted')|NO|ACTIVE|",
                'note' => 'text|YES|NULL|',
                'created_at' => 'datetime|NO|current_timestamp()|',
                'updated_at' => 'datetime|YES|NULL|on update current_timestamp()',
                'created_by' => 'bigint(20) unsigned|YES|NULL|',
                'updated_by' => 'bigint(20) unsigned|YES|NULL|',
                'deleted_at' => 'datetime|YES|NULL|',
                'deleted_by' => 'bigint(20) unsigned|YES|NULL|',
            ],
        ];
    }

    private function requiredIndexes(): array
    {
        return [
            'livestock_facilities' => [
                'idx_livestock_facility_village' => ['village_id'],
                'idx_livestock_facility_household' => ['household_id'],
                'idx_livestock_facility_type' => ['facility_type'],
                'idx_livestock_facility_status' => ['status'],
                'idx_livestock_facility_area' => ['area_code'],
            ],
            'livestock' => [
                'idx_livestock_village' => ['village_id'],
                'idx_livestock_facility' => ['facility_id'],
                'idx_livestock_household' => ['household_id'],
                'idx_livestock_animal_type' => ['animal_type'],
                'idx_livestock_animal_group' => ['animal_group'],
                'idx_livestock_status' => ['status'],
                'idx_livestock_vaccinated' => ['vaccinated'],
            ],
        ];
    }

    private function requiredForeignKeys(): array
    {
        return [
            'livestock_facilities' => [
                ['fk_livestock_facility_household', 'household_id', 'households', 'id', 'RESTRICT'],
            ],
            'livestock' => [
                ['fk_livestock_household', 'household_id', 'households', 'id', 'RESTRICT'],
                ['fk_livestock_facility', 'facility_id', 'livestock_facilities', 'id', 'SET NULL'],
            ],
        ];
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table', ['table' => $table]);
        return (int)($row['total'] ?? 0) > 0;
    }

    private function columnContract(string $table, string $column): ?string
    {
        $row = $this->fetchOne('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column', ['table' => $table, 'column' => $column]);
        if (!$row) return null;
        $default = $row['COLUMN_DEFAULT'];
        if (is_string($default) && strlen($default) >= 2 && $default[0] === "'" && substr($default, -1) === "'") {
            $default = substr($default, 1, -1);
        }
        return strtolower((string)$row['COLUMN_TYPE']) . '|' . strtoupper((string)$row['IS_NULLABLE']) . '|' . ($default === null ? 'NULL' : (string)$default) . '|' . strtolower((string)$row['EXTRA']);
    }

    private function indexExists(string $table, string $index, array $columns): bool
    {
        $rows = $this->fetchAll('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND INDEX_NAME=:index ORDER BY SEQ_IN_INDEX', ['table' => $table, 'index' => $index]);
        return array_map(fn($row) => (string)$row['COLUMN_NAME'], $rows) === $columns;
    }

    private function foreignKeyExists(string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn, string $deleteRule): bool
    {
        $row = $this->fetchOne(
            'SELECT rc.DELETE_RULE FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kcu.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME=kcu.CONSTRAINT_NAME WHERE kcu.TABLE_SCHEMA=DATABASE() AND kcu.TABLE_NAME=:table AND kcu.CONSTRAINT_NAME=:constraint_name AND kcu.COLUMN_NAME=:column_name AND kcu.REFERENCED_TABLE_NAME=:referenced_table AND kcu.REFERENCED_COLUMN_NAME=:referenced_column',
            ['table' => $table, 'constraint_name' => $constraint, 'column_name' => $column, 'referenced_table' => $referencedTable, 'referenced_column' => $referencedColumn]
        );
        return strtoupper((string)($row['DELETE_RULE'] ?? '')) === $deleteRule;
    }

    private function pigType(): string
    {
        return "L\u{1EE3}n";
    }

    private function pigSql(): string
    {
        return $this->db->quote($this->pigType());
    }

    private function dogType(): string
    {
        return "Ch\u{00F3}";
    }

    private function dogSql(): string
    {
        return $this->db->quote($this->dogType());
    }
    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title'=>$title,'headers'=>$headers,'rows'=>$rows,'totalRows'=>count($rows),'filters'=>$filters,'generatedAt'=>date('c')];
    }
}
