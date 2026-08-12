<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;
use Throwable;

final class RuralCleanWater extends BaseModel
{
    private const CONNECTION_LABELS = [
        'PIPED' => 'NÆ°á»›c mÃ¡y/cÃ´ng trÃ¬nh cáº¥p nÆ°á»›c táº­p trung',
        'BOREHOLE_WELL' => 'Giáº¿ng khoan',
        'DUG_WELL' => 'Giáº¿ng Ä‘Ã o',
        'WELL' => 'Giáº¿ng khoan/giáº¿ng Ä‘Ã o',
        'RAINWATER' => 'NÆ°á»›c mÆ°a',
        'PURCHASED' => 'NÆ°á»›c mua/bÃ¬nh',
        'OTHER' => 'Nguá»“n nÆ°á»›c khÃ¡c',
    ];
    private const SUPPLY_FORM_LABELS = ['CENTRALIZED'=>'CÃ´ng trÃ¬nh cáº¥p nÆ°á»›c táº­p trung','HOUSEHOLD_SCALE'=>'CÃ´ng trÃ¬nh cáº¥p nÆ°á»›c quy mÃ´ há»™ gia Ä‘Ã¬nh','OTHER'=>'KhÃ¡c'];
    private const CLEAN_STATUS_LABELS = ['COMPLIANT'=>'Äáº¡t quy chuáº©n','NON_COMPLIANT'=>'KhÃ´ng Ä‘áº¡t quy chuáº©n','UNKNOWN'=>'ChÆ°a xÃ¡c Ä‘á»‹nh'];
    private const HYGIENIC_STATUS_LABELS = ['YES'=>'CÃ³','NO'=>'KhÃ´ng','UNKNOWN'=>'ChÆ°a xÃ¡c Ä‘á»‹nh'];
    private const METER_LABELS = ['YES'=>'CÃ³','NO'=>'KhÃ´ng','NOT_APPLICABLE'=>'KhÃ´ng Ã¡p dá»¥ng'];
    private const BASIS_LABELS = ['TEST_RESULT'=>'Káº¿t quáº£ kiá»ƒm nghiá»‡m','PROVIDER_CONFIRMATION'=>'XÃ¡c nháº­n cá»§a Ä‘Æ¡n vá»‹ cáº¥p nÆ°á»›c','AUTHORITY_LIST'=>'Danh sÃ¡ch Ä‘Æ°á»£c cÆ¡ quan cÃ³ tháº©m quyá»n xÃ¡c nháº­n','OTHER'=>'KhÃ¡c','NONE'=>'ChÆ°a cÃ³ cÄƒn cá»©'];
    private const STATUS_LABELS = ['ACTIVE'=>'Äang sá»­ dá»¥ng','INACTIVE'=>'Táº¡m ngá»«ng','NEEDS_REPAIR'=>'Cáº§n sá»­a chá»¯a','DISCONNECTED'=>'ÄÃ£ ngáº¯t','DELETED'=>'ÄÃ£ xÃ³a'];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS rural_clean_water (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  connection_type ENUM('PIPED','BOREHOLE_WELL','DUG_WELL','WELL','RAINWATER','PURCHASED','OTHER') NOT NULL DEFAULT 'PIPED',
  water_supply_form ENUM('CENTRALIZED','HOUSEHOLD_SCALE','OTHER') NULL,
  water_source VARCHAR(255) NULL,
  provider_name VARCHAR(255) NULL,
  meter_number VARCHAR(120) NULL,
  has_water_meter ENUM('YES','NO','NOT_APPLICABLE') NOT NULL DEFAULT 'NOT_APPLICABLE',
  contract_number VARCHAR(120) NULL,
  installed_date DATE NULL,
  monthly_usage_m3 DECIMAL(12,2) NOT NULL DEFAULT 0,
  monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_clean_standard TINYINT(1) NOT NULL DEFAULT 0,
  clean_water_status ENUM('COMPLIANT','NON_COMPLIANT','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  hygienic_water_status ENUM('YES','NO','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  last_test_date DATE NULL,
  test_result VARCHAR(120) NULL,
  verification_basis ENUM('TEST_RESULT','PROVIDER_CONFIRMATION','AUTHORITY_LIST','OTHER','NONE') NOT NULL DEFAULT 'NONE',
  confirmation_date DATE NULL,
  confirmation_agency VARCHAR(255) NULL,
  status ENUM('ACTIVE','INACTIVE','NEEDS_REPAIR','DISCONNECTED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_rural_clean_water_household (household_id),
  KEY idx_rural_clean_water_type (connection_type),
  KEY idx_rural_clean_water_standard (is_clean_standard),
  KEY idx_rural_clean_water_status (status),
  CONSTRAINT fk_rural_clean_water_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureTenantColumn('rural_clean_water');
        $this->extendSchema();
    }

    public function catalogs(): array
    {
        return ['connection_types'=>$this->pairs(self::CONNECTION_LABELS),'supply_forms'=>$this->pairs(self::SUPPLY_FORM_LABELS),'clean_water_statuses'=>$this->pairs(self::CLEAN_STATUS_LABELS),'hygienic_water_statuses'=>$this->pairs(self::HYGIENIC_STATUS_LABELS),'meter_statuses'=>$this->pairs(self::METER_LABELS),'verification_basis'=>$this->pairs(self::BASIS_LABELS),'statuses'=>$this->pairs(self::STATUS_LABELS)];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page,$pageSize,$offset]=$this->page((int)($filters['page']??1),(int)($filters['pageSize']??20));
        [$where,$params,$order]=$this->where($filters);
        $from=$this->householdWaterFrom();
        $total=(int)(($this->fetchOne("SELECT COUNT(*) AS total $from $where",$params)?:[])['total']??0);
        $rows=$this->fetchAll("SELECT w.*, h.id AS household_id_base, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code $from $where $order LIMIT $pageSize OFFSET $offset",$params);
        return $this->paginated(array_map(fn($row)=>$this->normalize($row),$rows),$page,$pageSize,$total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row=$this->fetchOne('SELECT w.*, h.id AS household_id_base, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id WHERE w.id=:id AND w.status <> "DELETED" AND '.$this->tenantWhere('w','rural_clean_water').' AND '.$this->tenantWhere('h','households').' AND '.$this->activeHouseholdCondition('h'),$this->withTenant(['id'=>$id]));
        return $row ? $this->normalize($row) : null;
    }

    public function byHousehold(int $householdId): array
    {
        $this->ensureSchema();
        $rows=$this->fetchAll('SELECT w.*, h.id AS household_id_base, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id WHERE w.household_id=:household_id AND w.status <> "DELETED" AND '.$this->tenantWhere('w','rural_clean_water').' AND '.$this->tenantWhere('h','households').' ORDER BY w.id DESC',$this->withTenant(['household_id'=>$householdId]));
        return array_map(fn($row)=>$this->normalize($row),$rows);
    }
    public function searchHouseholds(string $query, int $limit = 12): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) return [];

        $limit = max(1, min(20, $limit));
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $sql = 'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code, CASE WHEN lw.id IS NULL THEN 0 ELSE 1 END AS water_count FROM households h LEFT JOIN (' . $this->latestWaterSql() . ') lw ON lw.household_id=h.id WHERE ' . $this->activeHouseholdCondition('h') . ' AND ' . $this->tenantWhere('h', 'households') . ' AND (LOWER(h.household_code) LIKE :code OR LOWER(h.head_citizen_name) LIKE :head OR LOWER(h.address) LIKE :address OR LOWER(h.area_code) LIKE :area) ORDER BY h.household_code ASC LIMIT ' . $limit;
        $rows = $this->fetchAll($sql, $this->withTenant(['code' => $keyword, 'head' => $keyword, 'address' => $keyword, 'area' => $keyword]));

        if (count($rows) < $limit) {
            $rows = $this->mergeHouseholdSearchRows($rows, $query, $limit);
        }

        return array_map(fn($row) => [
            'id' => (int) $row['id'],
            'household_code' => (string) $row['household_code'],
            'head_citizen_name' => (string) $row['head_citizen_name'],
            'address' => (string) ($row['address'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'water_count' => (int) ($row['water_count'] ?? 0),
        ], $rows);
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->find($id)) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y báº£n ghi nÆ°á»›c sáº¡ch');
        $params=$this->params($data,$userId);
        if (!$id && $this->currentRecordForHousehold((int) $params['household_id'])) {
            throw new RuntimeException(json_decode('"H\u1ed9 n\u00e0y \u0111\u00e3 c\u00f3 th\u00f4ng tin n\u01b0\u1edbc sinh ho\u1ea1t."'));
        }
        if ($id) {
            $params['id']=$id;
            $this->execute('UPDATE rural_clean_water SET household_id=:household_id, connection_type=:connection_type, water_supply_form=:water_supply_form, water_source=:water_source, provider_name=:provider_name, meter_number=:meter_number, has_water_meter=:has_water_meter, contract_number=:contract_number, installed_date=:installed_date, monthly_usage_m3=:monthly_usage_m3, monthly_fee=:monthly_fee, is_clean_standard=:is_clean_standard, clean_water_status=:clean_water_status, hygienic_water_status=:hygienic_water_status, last_test_date=:last_test_date, test_result=:test_result, verification_basis=:verification_basis, confirmation_date=:confirmation_date, confirmation_agency=:confirmation_agency, status=:status, note=:note, updated_by=:updated_by WHERE id=:id AND '.$this->tenantWhere('rural_clean_water'),$this->withTenant($params));
            return $this->find($id);
        }
        $columns=['household_id','connection_type','water_supply_form','water_source','provider_name','meter_number','has_water_meter','contract_number','installed_date','monthly_usage_m3','monthly_fee','is_clean_standard','clean_water_status','hygienic_water_status','last_test_date','test_result','verification_basis','confirmation_date','confirmation_agency','status','note','created_by','updated_by'];
        $this->addTenantInsert('rural_clean_water',$columns,$params);
        $newId=$this->insert('INSERT INTO rural_clean_water ('.implode(',',$columns).') VALUES (:'.implode(',:',$columns).')',$params);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y báº£n ghi nÆ°á»›c sáº¡ch');
        $this->execute('UPDATE rural_clean_water SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id AND '.$this->tenantWhere('rural_clean_water'),$this->withTenant(['id'=>$id,'deleted_by'=>$userId,'updated_by'=>$userId]));
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        $stats=$this->stats($filters);
        return ['metrics'=>$stats,'charts'=>['connection_types'=>$this->chart('connection_type',self::CONNECTION_LABELS,$filters),'supply_forms'=>$this->chart('water_supply_form',self::SUPPLY_FORM_LABELS,$filters),'clean_status'=>$this->chart('clean_water_status_effective',self::CLEAN_STATUS_LABELS,$filters),'hygienic_status'=>$this->chart('hygienic_water_status_effective',self::HYGIENIC_STATUS_LABELS,$filters)],'warning'=>$stats['unknown_households']>0?['message'=>'CÃ²n '.$stats['unknown_households'].' há»™ chÆ°a xÃ¡c Ä‘á»‹nh tÃ¬nh tráº¡ng nÆ°á»›c sáº¡ch.','metric'=>'unknown']:null];
    }

    public function stats(array $filters = []): array
    {
        $this->ensureSchema();
        [$where,$params]=$this->where($filters,false,false);
        $from=$this->householdWaterFrom();
        $row=$this->fetchOne("SELECT COUNT(*) AS total_households, COALESCE(SUM(CASE WHEN COALESCE(w.hygienic_water_status,'UNKNOWN')='YES' THEN 1 ELSE 0 END),0) AS hygienic_water_households, COALESCE(SUM(CASE WHEN COALESCE(w.clean_water_status, CASE WHEN w.is_clean_standard=1 THEN 'COMPLIANT' ELSE 'UNKNOWN' END, 'UNKNOWN')='COMPLIANT' THEN 1 ELSE 0 END),0) AS clean_water_households, COALESCE(SUM(CASE WHEN w.water_supply_form='CENTRALIZED' THEN 1 ELSE 0 END),0) AS centralized_water_households, COALESCE(SUM(CASE WHEN w.water_supply_form='HOUSEHOLD_SCALE' THEN 1 ELSE 0 END),0) AS household_scale_water_households, COALESCE(SUM(CASE WHEN COALESCE(w.clean_water_status,'UNKNOWN')='NON_COMPLIANT' THEN 1 ELSE 0 END),0) AS non_compliant_households, COALESCE(SUM(CASE WHEN w.id IS NULL OR COALESCE(w.clean_water_status, CASE WHEN w.is_clean_standard=1 THEN 'COMPLIANT' ELSE 'UNKNOWN' END, 'UNKNOWN')='UNKNOWN' THEN 1 ELSE 0 END),0) AS unknown_households $from $where",$params)?:[];
        $total=(int)($row['total_households']??0); $clean=(int)($row['clean_water_households']??0);
        return ['total_households'=>$total,'hygienic_water_households'=>(int)($row['hygienic_water_households']??0),'clean_water_households'=>$clean,'centralized_water_households'=>(int)($row['centralized_water_households']??0),'household_scale_water_households'=>(int)($row['household_scale_water_households']??0),'non_compliant_households'=>(int)($row['non_compliant_households']??0),'unknown_households'=>(int)($row['unknown_households']??0),'clean_water_rate'=>$total>0?round($clean*100/$total,2):0.0];
    }

    public function report(string $mode, array $filters = []): array
    {
        $this->ensureSchema();
        if (in_array($mode,['all','summary'],true)) return $this->summaryReport($filters);
        if ($mode==='standard') $filters['metric']='clean';
        if ($mode==='not_standard') $filters['metric']='unknown';
        if ($mode==='non_compliant') $filters['metric']='non_compliant';
        if ($mode==='hygienic') $filters['metric']='hygienic';
        if ($mode==='centralized') $filters['metric']='centralized';
        if ($mode==='household_scale') $filters['metric']='household_scale';
        $filters['page']=1; $filters['pageSize']=500;
        $rows=$this->paginate($filters)['items'];
        $title=match($mode){'standard'=>'Danh sÃ¡ch há»™ sá»­ dá»¥ng nÆ°á»›c sáº¡ch Ä‘áº¡t quy chuáº©n','not_standard'=>'Danh sÃ¡ch há»™ chÆ°a xÃ¡c Ä‘á»‹nh tÃ¬nh tráº¡ng nÆ°á»›c sáº¡ch','non_compliant'=>'Danh sÃ¡ch há»™ sá»­ dá»¥ng nÆ°á»›c khÃ´ng Ä‘áº¡t quy chuáº©n','hygienic'=>'Danh sÃ¡ch há»™ sá»­ dá»¥ng nÆ°á»›c há»£p vá»‡ sinh','centralized'=>'Danh sÃ¡ch há»™ sá»­ dá»¥ng nÆ°á»›c tá»« cÃ´ng trÃ¬nh cáº¥p nÆ°á»›c táº­p trung','household_scale'=>'Danh sÃ¡ch há»™ sá»­ dá»¥ng nÆ°á»›c quy mÃ´ há»™ gia Ä‘Ã¬nh',default=>'Danh sÃ¡ch chi tiáº¿t nÆ°á»›c sáº¡ch nÃ´ng thÃ´n'};
        return $this->table($title,$this->detailHeaders(),array_map(fn($r)=>$this->detailRow($r),$rows),$filters);
    }

    private function summaryReport(array $filters): array
    {
        $stats=$this->stats($filters); $total=max(1,$stats['total_households']);
        $rows=[['Tá»•ng sá»‘ há»™',$stats['total_households'],'100%'],['Há»™ sá»­ dá»¥ng nÆ°á»›c há»£p vá»‡ sinh',$stats['hygienic_water_households'],$this->percent($stats['hygienic_water_households'],$total)],['Há»™ sá»­ dá»¥ng nÆ°á»›c sáº¡ch Ä‘áº¡t quy chuáº©n',$stats['clean_water_households'],$this->percent($stats['clean_water_households'],$total)],['Há»™ sá»­ dá»¥ng nÆ°á»›c táº­p trung',$stats['centralized_water_households'],$this->percent($stats['centralized_water_households'],$total)],['Há»™ sá»­ dá»¥ng nÆ°á»›c quy mÃ´ há»™ gia Ä‘Ã¬nh',$stats['household_scale_water_households'],$this->percent($stats['household_scale_water_households'],$total)],['KhÃ´ng Ä‘áº¡t quy chuáº©n',$stats['non_compliant_households'],$this->percent($stats['non_compliant_households'],$total)],['ChÆ°a xÃ¡c Ä‘á»‹nh',$stats['unknown_households'],$this->percent($stats['unknown_households'],$total)]];
        return $this->table('BÃ¡o cÃ¡o tÃ¬nh hÃ¬nh sá»­ dá»¥ng nÆ°á»›c sáº¡ch nÃ´ng thÃ´n',['Chá»‰ tiÃªu','Sá»‘ há»™','Tá»· lá»‡'],$rows,$filters)+['summary'=>$stats,'detailType'=>'rural-clean-water-detail'];
    }
    private function where(array $filters, bool $withOrder = true, bool $includeWaterFilters = true): array
    {
        $where = [$this->activeHouseholdCondition('h'), $this->tenantWhere('h', 'households')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(h.household_code LIKE :search OR h.head_citizen_name LIKE :search OR h.address LIKE :search OR w.meter_number LIKE :search OR w.provider_name LIKE :search OR w.water_source LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $area = trim((string) ($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') {
            $where[] = 'h.area_code = :area_code';
            $params['area_code'] = $area;
        }
        if ($includeWaterFilters) {
            $this->appendWaterFilters($where, $params, $filters);
        }
        $sortMap = [
            'household_code' => 'h.household_code',
            'head_citizen_name' => 'h.head_citizen_name',
            'connection_type' => 'w.connection_type',
            'clean_water_status' => $this->cleanStatusExpr(),
            'hygienic_water_status' => $this->hygienicStatusExpr(),
            'water_supply_form' => 'w.water_supply_form',
            'status' => 'w.status',
            'updated_at' => 'COALESCE(w.updated_at,w.created_at)',
        ];
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) {
            $result[] = $this->listOrder($filters, $sortMap, 'household_code', 'ASC', ['h.id ASC']);
        }
        return $result;
    }

    private function appendWaterFilters(array &$where, array &$params, array $filters): void
    {
        foreach (['connection_type' => 'w.connection_type', 'water_supply_form' => 'w.water_supply_form', 'status' => 'w.status'] as $key => $column) {
            $value = strtoupper(trim((string) ($filters[$key] ?? $filters[str_replace('_', '', $key)] ?? '')));
            if ($value !== '') {
                $where[] = "$column = :$key";
                $params[$key] = $value;
            }
        }

        $cleanExpr = $this->cleanStatusExpr();
        $hygienicExpr = $this->hygienicStatusExpr();
        $clean = strtoupper(trim((string) ($filters['clean_water_status'] ?? $filters['cleanWaterStatus'] ?? '')));
        if (isset(self::CLEAN_STATUS_LABELS[$clean])) {
            $where[] = $clean === 'UNKNOWN' ? "(w.id IS NULL OR $cleanExpr = :clean_water_status)" : "$cleanExpr = :clean_water_status";
            $params['clean_water_status'] = $clean;
        }
        $hygienic = strtoupper(trim((string) ($filters['hygienic_water_status'] ?? $filters['hygienicWaterStatus'] ?? '')));
        if (isset(self::HYGIENIC_STATUS_LABELS[$hygienic])) {
            $where[] = $hygienic === 'UNKNOWN' ? "(w.id IS NULL OR $hygienicExpr = :hygienic_water_status)" : "$hygienicExpr = :hygienic_water_status";
            $params['hygienic_water_status'] = $hygienic;
        }
        $standard = trim((string) ($filters['is_clean_standard'] ?? $filters['isCleanStandard'] ?? ''));
        if ($standard === '1') {
            $where[] = "$cleanExpr = \"COMPLIANT\"";
        }
        if ($standard === '0') {
            $where[] = "(w.id IS NULL OR $cleanExpr = \"UNKNOWN\")";
        }
        $metric = trim((string) ($filters['metric'] ?? ''));
        $metricWhere = [
            'hygienic' => "$hygienicExpr = \"YES\"",
            'clean' => "$cleanExpr = \"COMPLIANT\"",
            'centralized' => 'w.water_supply_form = "CENTRALIZED"',
            'household_scale' => 'w.water_supply_form = "HOUSEHOLD_SCALE"',
            'non_compliant' => "$cleanExpr = \"NON_COMPLIANT\"",
            'unknown' => "(w.id IS NULL OR $cleanExpr = \"UNKNOWN\")",
        ];
        if (isset($metricWhere[$metric])) {
            $where[] = $metricWhere[$metric];
        }
    }
    private function mergeHouseholdSearchRows(array $rows, string $query, int $limit): array
    {
        $seen = [];
        foreach ($rows as $row) $seen[(int) $row['id']] = true;

        $needle = $this->normalizeSearchText($query);
        if ($needle === '') return $rows;

        $candidates = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code, CASE WHEN lw.id IS NULL THEN 0 ELSE 1 END AS water_count FROM households h LEFT JOIN (' . $this->latestWaterSql() . ') lw ON lw.household_id=h.id WHERE ' . $this->activeHouseholdCondition('h') . ' AND ' . $this->tenantWhere('h', 'households') . ' ORDER BY h.household_code ASC LIMIT 1000',
            $this->withTenant()
        );

        foreach ($candidates as $row) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) continue;
            $haystack = $this->normalizeSearchText(implode(' ', [
                $row['household_code'] ?? '',
                $row['head_citizen_name'] ?? '',
                $row['address'] ?? '',
                $row['area_code'] ?? '',
            ]));
            if ($haystack !== '' && str_contains($haystack, $needle)) {
                $rows[] = $row;
                $seen[$id] = true;
                if (count($rows) >= $limit) break;
            }
        }

        return $rows;
    }

    private function normalizeSearchText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $groups = [
            'a' => '/[\\x{00E0}\\x{00E1}\\x{1EA1}\\x{1EA3}\\x{00E3}\\x{00E2}\\x{1EA7}\\x{1EA5}\\x{1EAD}\\x{1EA9}\\x{1EAB}\\x{0103}\\x{1EB1}\\x{1EAF}\\x{1EB7}\\x{1EB3}\\x{1EB5}]/u',
            'e' => '/[\\x{00E8}\\x{00E9}\\x{1EB9}\\x{1EBB}\\x{1EBD}\\x{00EA}\\x{1EC1}\\x{1EBF}\\x{1EC7}\\x{1EC3}\\x{1EC5}]/u',
            'i' => '/[\\x{00EC}\\x{00ED}\\x{1ECB}\\x{1EC9}\\x{0129}]/u',
            'o' => '/[\\x{00F2}\\x{00F3}\\x{1ECD}\\x{1ECF}\\x{00F5}\\x{00F4}\\x{1ED3}\\x{1ED1}\\x{1ED9}\\x{1ED5}\\x{1ED7}\\x{01A1}\\x{1EDD}\\x{1EDB}\\x{1EE3}\\x{1EDF}\\x{1EE1}]/u',
            'u' => '/[\\x{00F9}\\x{00FA}\\x{1EE5}\\x{1EE7}\\x{0169}\\x{01B0}\\x{1EEB}\\x{1EE9}\\x{1EF1}\\x{1EED}\\x{1EEF}]/u',
            'y' => '/[\\x{1EF3}\\x{00FD}\\x{1EF5}\\x{1EF7}\\x{1EF9}]/u',
            'd' => '/[\\x{0111}]/u',
        ];
        foreach ($groups as $ascii => $pattern) {
            $value = (string) preg_replace($pattern, $ascii, $value);
        }
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function params(array $data, int $userId): array
    {
        $householdId=(int)($data['household_id']??$data['householdId']??0);
        if($householdId<=0) throw new RuntimeException('Há»™ gia Ä‘Ã¬nh lÃ  báº¯t buá»™c');
        if(!$this->fetchOne('SELECT h.id FROM households h WHERE h.id=:id AND '.$this->tenantWhere('h','households').' AND '.$this->activeHouseholdCondition('h'),$this->withTenant(['id'=>$householdId]))) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y há»™ gia Ä‘Ã¬nh');
        $type=$this->enum($data['connection_type']??$data['connectionType']??'PIPED',self::CONNECTION_LABELS,'OTHER');
        $cleanStatus=$this->enum($data['clean_water_status']??$data['cleanWaterStatus']??'',self::CLEAN_STATUS_LABELS,'UNKNOWN');
        $hygienicStatus=$this->enum($data['hygienic_water_status']??$data['hygienicWaterStatus']??'',self::HYGIENIC_STATUS_LABELS,'UNKNOWN');
        $supplyForm=$this->enum($data['water_supply_form']??$data['waterSupplyForm']??'',self::SUPPLY_FORM_LABELS,$this->defaultSupplyForm($type));
        $meter=$this->enum($data['has_water_meter']??$data['hasWaterMeter']??'',self::METER_LABELS,'NOT_APPLICABLE');
        $basis=$this->enum($data['verification_basis']??$data['verificationBasis']??'',self::BASIS_LABELS,'NONE');
        $status=$this->enum($data['status']??'ACTIVE',self::STATUS_LABELS,'ACTIVE'); if($status==='DELETED') $status='ACTIVE';
        return ['household_id'=>$householdId,'connection_type'=>$type,'water_supply_form'=>$supplyForm,'water_source'=>$this->nullable($data['water_source']??$data['waterSource']??''),'provider_name'=>$this->nullable($data['provider_name']??$data['providerName']??''),'meter_number'=>$this->nullable($data['meter_number']??$data['meterNumber']??''),'has_water_meter'=>$meter,'contract_number'=>$this->nullable($data['contract_number']??$data['contractNumber']??''),'installed_date'=>$this->dateValue($data['installed_date']??$data['installedDate']??''),'monthly_usage_m3'=>$this->number($data['monthly_usage_m3']??$data['monthlyUsageM3']??0),'monthly_fee'=>$this->number($data['monthly_fee']??$data['monthlyFee']??0),'is_clean_standard'=>$cleanStatus==='COMPLIANT'?1:0,'clean_water_status'=>$cleanStatus,'hygienic_water_status'=>$hygienicStatus,'last_test_date'=>$this->dateValue($data['last_test_date']??$data['lastTestDate']??''),'test_result'=>$this->nullable($data['test_result']??$data['testResult']??''),'verification_basis'=>$basis,'confirmation_date'=>$this->dateValue($data['confirmation_date']??$data['confirmationDate']??''),'confirmation_agency'=>$this->nullable($data['confirmation_agency']??$data['confirmationAgency']??''),'status'=>$status,'note'=>$this->nullable($data['note']??''),'created_by'=>$userId,'updated_by'=>$userId];
    }

    private function normalize(array $row): array
    {
        $hasRecord=!empty($row['id']); $type=(string)($row['connection_type']??''); $supply=(string)($row['water_supply_form']??'');
        $clean=$hasRecord?(string)($row['clean_water_status']??((int)($row['is_clean_standard']??0)===1?'COMPLIANT':'UNKNOWN')):'UNKNOWN';
        $hygienic=$hasRecord?(string)($row['hygienic_water_status']??'UNKNOWN'):'UNKNOWN'; $meter=$hasRecord?(string)($row['has_water_meter']??'NOT_APPLICABLE'):'NOT_APPLICABLE'; $basis=$hasRecord?(string)($row['verification_basis']??'NONE'):'NONE';
        return ['id'=>$hasRecord?(int)$row['id']:null,'has_water_record'=>$hasRecord,'household_id'=>(int)($row['household_id']??$row['household_id_base']??0),'household_code'=>(string)($row['household_code']??''),'head_citizen_name'=>(string)($row['head_citizen_name']??''),'area_code'=>(string)($row['area_code']??''),'address'=>(string)($row['household_address']??''),'phone'=>(string)($row['household_phone']??''),'connection_type'=>$type,'connection_type_label'=>$type!==''?(self::CONNECTION_LABELS[$type]??self::CONNECTION_LABELS['OTHER']):'ChÆ°a xÃ¡c Ä‘á»‹nh','water_supply_form'=>$supply,'water_supply_form_label'=>$supply!==''?(self::SUPPLY_FORM_LABELS[$supply]??self::SUPPLY_FORM_LABELS['OTHER']):'ChÆ°a xÃ¡c Ä‘á»‹nh','water_source'=>(string)($row['water_source']??''),'provider_name'=>(string)($row['provider_name']??''),'meter_number'=>(string)($row['meter_number']??''),'has_water_meter'=>$meter,'has_water_meter_label'=>self::METER_LABELS[$meter]??self::METER_LABELS['NOT_APPLICABLE'],'contract_number'=>(string)($row['contract_number']??''),'installed_date'=>$row['installed_date']??null,'monthly_usage_m3'=>(float)($row['monthly_usage_m3']??0),'monthly_fee'=>(float)($row['monthly_fee']??0),'is_clean_standard'=>$clean==='COMPLIANT','clean_water_status'=>$clean,'clean_water_status_label'=>self::CLEAN_STATUS_LABELS[$clean]??self::CLEAN_STATUS_LABELS['UNKNOWN'],'hygienic_water_status'=>$hygienic,'hygienic_water_status_label'=>self::HYGIENIC_STATUS_LABELS[$hygienic]??self::HYGIENIC_STATUS_LABELS['UNKNOWN'],'last_test_date'=>$row['last_test_date']??null,'test_result'=>(string)($row['test_result']??''),'verification_basis'=>$basis,'verification_basis_label'=>self::BASIS_LABELS[$basis]??self::BASIS_LABELS['NONE'],'confirmation_date'=>$row['confirmation_date']??null,'confirmation_agency'=>(string)($row['confirmation_agency']??''),'status'=>(string)($row['status']??''),'status_label'=>isset($row['status'])?(self::STATUS_LABELS[$row['status']]??self::STATUS_LABELS['ACTIVE']):'ChÆ°a cÃ³ thÃ´ng tin','note'=>(string)($row['note']??''),'created_at'=>$row['created_at']??null,'updated_at'=>$row['updated_at']??null];
    }    private function householdWaterFrom(): string
    {
        return 'FROM households h
                LEFT JOIN (' . $this->latestWaterSql() . ') lw ON lw.household_id=h.id
                LEFT JOIN rural_clean_water w ON w.id=lw.id';
    }

    private function latestWaterSql(): string
    {
        return 'SELECT household_id, MAX(id) AS id FROM rural_clean_water WHERE status <> "DELETED" AND ' . $this->tenantWhere('rural_clean_water') . ' GROUP BY household_id';
    }

    private function activeHouseholdCondition(string $alias): string
    {
        return $alias . '.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")';
    }

    private function currentRecordForHousehold(int $householdId): ?array
    {
        return $this->fetchOne(
            'SELECT id FROM rural_clean_water WHERE household_id=:household_id AND status <> "DELETED" AND ' . $this->tenantWhere('rural_clean_water') . ' ORDER BY id DESC LIMIT 1',
            $this->withTenant(['household_id' => $householdId])
        );
    }

    private function cleanStatusExpr(): string
    {
        return 'CASE WHEN w.id IS NULL THEN "UNKNOWN" ELSE COALESCE(w.clean_water_status, CASE WHEN w.is_clean_standard=1 THEN "COMPLIANT" ELSE "UNKNOWN" END, "UNKNOWN") END';
    }

    private function hygienicStatusExpr(): string
    {
        return 'CASE WHEN w.id IS NULL THEN "UNKNOWN" ELSE COALESCE(w.hygienic_water_status,"UNKNOWN") END';
    }

    private function chart(string $field, array $labels, array $filters): array
    {
        [$where, $params] = $this->where($filters, false, false);
        $from = $this->householdWaterFrom();
        $expr = match ($field) {
            'clean_water_status_effective' => $this->cleanStatusExpr(),
            'hygienic_water_status_effective' => $this->hygienicStatusExpr(),
            default => 'COALESCE(w.' . $field . ', "UNKNOWN")',
        };
        $rows = $this->fetchAll("SELECT $expr AS code, COUNT(*) AS value $from $where GROUP BY code ORDER BY value DESC", $params);
        return array_map(fn ($row) => [
            'code' => (string) $row['code'],
            'label' => $labels[$row['code']] ?? 'ChÆ°a xÃ¡c Ä‘á»‹nh',
            'value' => (int) $row['value'],
        ], $rows);
    }

    private function detailHeaders(): array
    {
        return ['MÃ£ há»™', 'Chá»§ há»™', 'Khu vá»±c', 'Nguá»“n nÆ°á»›c chÃ­nh', 'HÃ¬nh thá»©c cáº¥p nÆ°á»›c', 'TÃ¬nh tráº¡ng nÆ°á»›c sáº¡ch', 'NÆ°á»›c há»£p vá»‡ sinh', 'ÄÆ¡n vá»‹/cÃ´ng trÃ¬nh cáº¥p nÆ°á»›c', 'CÃ³ Ä‘á»“ng há»“ nÆ°á»›c', 'Thá»i Ä‘iá»ƒm báº¯t Ä‘áº§u', 'CÄƒn cá»© xÃ¡c Ä‘á»‹nh', 'NgÃ y xÃ¡c nháº­n', 'ÄÆ¡n vá»‹ xÃ¡c nháº­n', 'Ghi chÃº'];
    }

    private function detailRow(array $r): array
    {
        return [
            $r['household_code'],
            $r['head_citizen_name'],
            $r['area_code'],
            $r['connection_type_label'],
            $r['water_supply_form_label'],
            $r['clean_water_status_label'],
            $r['hygienic_water_status_label'],
            $r['provider_name'] ?: $r['water_source'],
            $r['has_water_meter_label'],
            $r['installed_date'],
            $r['verification_basis_label'],
            $r['confirmation_date'],
            $r['confirmation_agency'],
            $r['note'],
        ];
    }

    private function extendSchema(): void
    {
        $this->tryExecute("ALTER TABLE rural_clean_water MODIFY COLUMN connection_type ENUM('PIPED','BOREHOLE_WELL','DUG_WELL','WELL','RAINWATER','PURCHASED','OTHER') NOT NULL DEFAULT 'PIPED'");
        $this->addColumn('water_supply_form', "ENUM('CENTRALIZED','HOUSEHOLD_SCALE','OTHER') NULL AFTER connection_type");
        $this->addColumn('clean_water_status', "ENUM('COMPLIANT','NON_COMPLIANT','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN' AFTER is_clean_standard");
        $this->addColumn('hygienic_water_status', "ENUM('YES','NO','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN' AFTER clean_water_status");
        $this->addColumn('has_water_meter', "ENUM('YES','NO','NOT_APPLICABLE') NOT NULL DEFAULT 'NOT_APPLICABLE' AFTER meter_number");
        $this->addColumn('verification_basis', "ENUM('TEST_RESULT','PROVIDER_CONFIRMATION','AUTHORITY_LIST','OTHER','NONE') NOT NULL DEFAULT 'NONE' AFTER test_result");
        $this->addColumn('confirmation_date', 'DATE NULL AFTER verification_basis');
        $this->addColumn('confirmation_agency', 'VARCHAR(255) NULL AFTER confirmation_date');
        $this->tryExecute('CREATE INDEX idx_rural_clean_water_supply_form ON rural_clean_water (water_supply_form)');
        $this->tryExecute('CREATE INDEX idx_rural_clean_water_clean_status ON rural_clean_water (clean_water_status)');
        $this->tryExecute('CREATE INDEX idx_rural_clean_water_hygienic ON rural_clean_water (hygienic_water_status)');
    }

    private function addColumn(string $column, string $definition): void
    {
        if (!$this->columnExists('rural_clean_water', $column)) {
            $this->execute('ALTER TABLE rural_clean_water ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    private function tryExecute(string $sql): void
    {
        try {
            $this->execute($sql);
        } catch (Throwable) {
        }
    }

    private function defaultSupplyForm(string $type): string
    {
        return match ($type) {
            'PIPED' => 'CENTRALIZED',
            'BOREHOLE_WELL', 'DUG_WELL', 'WELL', 'RAINWATER' => 'HOUSEHOLD_SCALE',
            default => 'OTHER',
        };
    }

    private function enum(mixed $value, array $map, string $default): string
    {
        $value = strtoupper(trim((string) $value));
        return isset($map[$value]) ? $value : $default;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function number(mixed $value): float
    {
        return max(0, (float) str_replace(',', '.', (string) $value));
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function pairs(array $map): array
    {
        return array_map(fn ($k, $v) => ['value' => $k, 'label' => $v], array_keys($map), array_values($map));
    }

    private function percent(int $count, int $total): string
    {
        return number_format($total > 0 ? $count * 100 / $total : 0, 2, ',', '.') . '%';
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c'), 'orientation' => 'landscape'];
    }
}
