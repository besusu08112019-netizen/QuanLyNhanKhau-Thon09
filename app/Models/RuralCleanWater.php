<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;

final class RuralCleanWater extends BaseModel
{
    private const CONNECTION_LABELS = [
        'PIPED' => 'Nước máy/công trình cấp nước tập trung',
        'BOREHOLE_WELL' => 'Giếng khoan',
        'DUG_WELL' => 'Giếng đào',
        'WELL' => 'Giếng khoan/giếng đào',
        'RAINWATER' => 'Nước mưa',
        'PURCHASED' => 'Nước mua/bình',
        'OTHER' => 'Nguồn nước khác',
    ];
    private const SUPPLY_FORM_LABELS = ['CENTRALIZED'=>'Công trình cấp nước tập trung','HOUSEHOLD_SCALE'=>'Công trình cấp nước quy mô hộ gia đình','OTHER'=>'Khác'];
    private const CLEAN_STATUS_LABELS = ['COMPLIANT'=>'Đạt quy chuẩn','NON_COMPLIANT'=>'Không đạt quy chuẩn','UNKNOWN'=>'Chưa xác định'];
    private const HYGIENIC_STATUS_LABELS = ['YES'=>'Có','NO'=>'Không','UNKNOWN'=>'Chưa xác định'];
    private const METER_LABELS = ['YES'=>'Có','NO'=>'Không','NOT_APPLICABLE'=>'Không áp dụng'];
    private const BASIS_LABELS = ['TEST_RESULT'=>'Kết quả kiểm nghiệm','PROVIDER_CONFIRMATION'=>'Xác nhận của đơn vị cấp nước','AUTHORITY_LIST'=>'Danh sách được cơ quan có thẩm quyền xác nhận','OTHER'=>'Khác','NONE'=>'Chưa có căn cứ'];
    private const STATUS_LABELS = ['ACTIVE'=>'Đang sử dụng','INACTIVE'=>'Tạm ngừng','NEEDS_REPAIR'=>'Cần sửa chữa','DISCONNECTED'=>'Đã ngắt','DELETED'=>'Đã xóa'];

    private const REQUIRED_COLUMNS = [
        'id',
        'village_id',
        'household_id',
        'connection_type',
        'water_supply_form',
        'water_source',
        'provider_name',
        'meter_number',
        'has_water_meter',
        'contract_number',
        'installed_date',
        'monthly_usage_m3',
        'monthly_fee',
        'is_clean_standard',
        'clean_water_status',
        'hygienic_water_status',
        'last_test_date',
        'test_result',
        'verification_basis',
        'confirmation_date',
        'confirmation_agency',
        'status',
        'note',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by',
    ];
    private const REQUIRED_INDEXES = [
        'idx_rural_clean_water_village',
        'idx_rural_clean_water_household',
        'idx_rural_clean_water_type',
        'idx_rural_clean_water_standard',
        'idx_rural_clean_water_status',
        'idx_rural_clean_water_supply_form',
        'idx_rural_clean_water_clean_status',
        'idx_rural_clean_water_hygienic',
    ];

    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
    }

    private function assertSchemaReady(): void
    {
        if (!$this->tableExists('rural_clean_water')) {
            throw new RuntimeException('Rural Clean Water schema is not provisioned: missing table rural_clean_water');
        }

        $missingColumns = array_values(array_filter(
            self::REQUIRED_COLUMNS,
            fn (string $column): bool => !$this->columnExists('rural_clean_water', $column)
        ));
        if ($missingColumns !== []) {
            throw new RuntimeException('Rural Clean Water schema is not provisioned: missing columns ' . implode(', ', $missingColumns));
        }

        $missingIndexes = array_values(array_filter(
            self::REQUIRED_INDEXES,
            fn (string $index): bool => !$this->indexExists('rural_clean_water', $index)
        ));
        if ($missingIndexes !== []) {
            throw new RuntimeException('Rural Clean Water schema is not provisioned: missing indexes ' . implode(', ', $missingIndexes));
        }

        if (!$this->foreignKeyExists('rural_clean_water', 'fk_rural_clean_water_household', 'household_id', 'households', 'id')) {
            throw new RuntimeException('Rural Clean Water schema is not provisioned: missing foreign key fk_rural_clean_water_household');
        }
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table]
        );
        return (int) ($row['total'] ?? 0) > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name',
            ['table' => $table, 'index_name' => $index]
        );
        return (int) ($row['total'] ?? 0) > 0;
    }

    private function foreignKeyExists(string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint_name AND COLUMN_NAME = :column_name AND REFERENCED_TABLE_NAME = :referenced_table AND REFERENCED_COLUMN_NAME = :referenced_column',
            ['table' => $table, 'constraint_name' => $constraint, 'column_name' => $column, 'referenced_table' => $referencedTable, 'referenced_column' => $referencedColumn]
        );
        return (int) ($row['total'] ?? 0) > 0;
    }

    public function catalogs(): array
    {
        return ['connection_types'=>$this->pairs(self::CONNECTION_LABELS),'supply_forms'=>$this->pairs(self::SUPPLY_FORM_LABELS),'clean_water_statuses'=>$this->pairs(self::CLEAN_STATUS_LABELS),'hygienic_water_statuses'=>$this->pairs(self::HYGIENIC_STATUS_LABELS),'meter_statuses'=>$this->pairs(self::METER_LABELS),'verification_basis'=>$this->pairs(self::BASIS_LABELS),'statuses'=>$this->pairs(self::STATUS_LABELS)];
    }

    public function paginate(array $filters): array
    {
        $this->assertSchemaReady();
        [$page,$pageSize,$offset]=$this->page((int)($filters['page']??1),(int)($filters['pageSize']??20));
        [$where,$params,$order]=$this->where($filters);
        $from=$this->householdWaterFrom();
        $total=(int)(($this->fetchOne("SELECT COUNT(*) AS total $from $where",$params)?:[])['total']??0);
        $rows=$this->fetchAll("SELECT w.*, h.id AS household_id_base, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code $from $where $order LIMIT $pageSize OFFSET $offset",$params);
        return $this->paginated(array_map(fn($row)=>$this->normalize($row),$rows),$page,$pageSize,$total);
    }

    public function find(int $id): ?array
    {
        $this->assertSchemaReady();
        $row=$this->fetchOne('SELECT w.*, h.id AS household_id_base, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id WHERE w.id=:id AND w.status <> "DELETED" AND '.$this->tenantWhere('w','rural_clean_water').' AND '.$this->tenantWhere('h','households').' AND '.$this->activeHouseholdCondition('h'),$this->withTenant(['id'=>$id]));
        return $row ? $this->normalize($row) : null;
    }

    public function byHousehold(int $householdId): array
    {
        $this->assertSchemaReady();
        $rows=$this->fetchAll('SELECT w.*, h.id AS household_id_base, h.household_code, h.head_citizen_name, h.phone AS household_phone, h.address AS household_address, h.area_code FROM rural_clean_water w INNER JOIN households h ON h.id=w.household_id WHERE w.household_id=:household_id AND w.status <> "DELETED" AND '.$this->tenantWhere('w','rural_clean_water').' AND '.$this->tenantWhere('h','households').' ORDER BY w.id DESC',$this->withTenant(['household_id'=>$householdId]));
        return array_map(fn($row)=>$this->normalize($row),$rows);
    }
    public function searchHouseholds(string $query, int $limit = 12): array
    {
        $this->assertSchemaReady();
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
        $this->assertSchemaReady();
        if ($id && !$this->find($id)) throw new RuntimeException('Không tìm thấy bản ghi nước sạch');
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
        $this->assertSchemaReady();
        if (!$this->find($id)) throw new RuntimeException('Không tìm thấy bản ghi nước sạch');
        $this->execute('UPDATE rural_clean_water SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id AND '.$this->tenantWhere('rural_clean_water'),$this->withTenant(['id'=>$id,'deleted_by'=>$userId,'updated_by'=>$userId]));
    }

    public function dashboard(array $filters = []): array
    {
        $this->assertSchemaReady();
        $stats=$this->stats($filters);
        return ['metrics'=>$stats,'charts'=>['connection_types'=>$this->chart('connection_type',self::CONNECTION_LABELS,$filters),'supply_forms'=>$this->chart('water_supply_form',self::SUPPLY_FORM_LABELS,$filters),'clean_status'=>$this->chart('clean_water_status_effective',self::CLEAN_STATUS_LABELS,$filters),'hygienic_status'=>$this->chart('hygienic_water_status_effective',self::HYGIENIC_STATUS_LABELS,$filters)],'warning'=>$stats['unknown_households']>0?['message'=>'Còn '.$stats['unknown_households'].' hộ chưa xác định tình trạng nước sạch.','metric'=>'unknown']:null];
    }

    public function stats(array $filters = []): array
    {
        $this->assertSchemaReady();
        [$where,$params]=$this->where($filters,false,false);
        $from=$this->householdWaterFrom();
        $row=$this->fetchOne("SELECT COUNT(*) AS total_households, COALESCE(SUM(CASE WHEN COALESCE(w.hygienic_water_status,'UNKNOWN')='YES' THEN 1 ELSE 0 END),0) AS hygienic_water_households, COALESCE(SUM(CASE WHEN COALESCE(w.clean_water_status, CASE WHEN w.is_clean_standard=1 THEN 'COMPLIANT' ELSE 'UNKNOWN' END, 'UNKNOWN')='COMPLIANT' THEN 1 ELSE 0 END),0) AS clean_water_households, COALESCE(SUM(CASE WHEN w.water_supply_form='CENTRALIZED' THEN 1 ELSE 0 END),0) AS centralized_water_households, COALESCE(SUM(CASE WHEN w.water_supply_form='HOUSEHOLD_SCALE' THEN 1 ELSE 0 END),0) AS household_scale_water_households, COALESCE(SUM(CASE WHEN COALESCE(w.clean_water_status,'UNKNOWN')='NON_COMPLIANT' THEN 1 ELSE 0 END),0) AS non_compliant_households, COALESCE(SUM(CASE WHEN w.id IS NULL OR COALESCE(w.clean_water_status, CASE WHEN w.is_clean_standard=1 THEN 'COMPLIANT' ELSE 'UNKNOWN' END, 'UNKNOWN')='UNKNOWN' THEN 1 ELSE 0 END),0) AS unknown_households $from $where",$params)?:[];
        $total=(int)($row['total_households']??0); $clean=(int)($row['clean_water_households']??0);
        return ['total_households'=>$total,'hygienic_water_households'=>(int)($row['hygienic_water_households']??0),'clean_water_households'=>$clean,'centralized_water_households'=>(int)($row['centralized_water_households']??0),'household_scale_water_households'=>(int)($row['household_scale_water_households']??0),'non_compliant_households'=>(int)($row['non_compliant_households']??0),'unknown_households'=>(int)($row['unknown_households']??0),'clean_water_rate'=>$total>0?round($clean*100/$total,2):0.0];
    }

    public function report(string $mode, array $filters = []): array
    {
        $this->assertSchemaReady();
        if (in_array($mode,['all','summary'],true)) return $this->summaryReport($filters);
        if ($mode==='standard') $filters['metric']='clean';
        if ($mode==='not_standard') $filters['metric']='unknown';
        if ($mode==='non_compliant') $filters['metric']='non_compliant';
        if ($mode==='hygienic') $filters['metric']='hygienic';
        if ($mode==='centralized') $filters['metric']='centralized';
        if ($mode==='household_scale') $filters['metric']='household_scale';
        $filters['page']=1; $filters['pageSize']=500;
        $rows=$this->paginate($filters)['items'];
        $title=match($mode){'standard'=>'Danh sách hộ sử dụng nước sạch đạt quy chuẩn','not_standard'=>'Danh sách hộ chưa xác định tình trạng nước sạch','non_compliant'=>'Danh sách hộ sử dụng nước không đạt quy chuẩn','hygienic'=>'Danh sách hộ sử dụng nước hợp vệ sinh','centralized'=>'Danh sách hộ sử dụng nước từ công trình cấp nước tập trung','household_scale'=>'Danh sách hộ sử dụng nước quy mô hộ gia đình',default=>'Danh sách chi tiết nước sạch nông thôn'};
        return $this->table($title,$this->detailHeaders(),array_map(fn($r)=>$this->detailRow($r),$rows),$filters);
    }

    private function summaryReport(array $filters): array
    {
        $stats=$this->stats($filters); $total=max(1,$stats['total_households']);
        $rows=[['Tổng số hộ',$stats['total_households'],'100%'],['Hộ sử dụng nước hợp vệ sinh',$stats['hygienic_water_households'],$this->percent($stats['hygienic_water_households'],$total)],['Hộ sử dụng nước sạch đạt quy chuẩn',$stats['clean_water_households'],$this->percent($stats['clean_water_households'],$total)],['Hộ sử dụng nước tập trung',$stats['centralized_water_households'],$this->percent($stats['centralized_water_households'],$total)],['Hộ sử dụng nước quy mô hộ gia đình',$stats['household_scale_water_households'],$this->percent($stats['household_scale_water_households'],$total)],['Không đạt quy chuẩn',$stats['non_compliant_households'],$this->percent($stats['non_compliant_households'],$total)],['Chưa xác định',$stats['unknown_households'],$this->percent($stats['unknown_households'],$total)]];
        return $this->table('Báo cáo tình hình sử dụng nước sạch nông thôn',['Chỉ tiêu','Số hộ','Tỷ lệ'],$rows,$filters)+['summary'=>$stats,'detailType'=>'rural-clean-water-detail'];
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
        if($householdId<=0) throw new RuntimeException('Hộ gia đình là bắt buộc');
        if(!$this->fetchOne('SELECT h.id FROM households h WHERE h.id=:id AND '.$this->tenantWhere('h','households').' AND '.$this->activeHouseholdCondition('h'),$this->withTenant(['id'=>$householdId]))) throw new RuntimeException('Không tìm thấy hộ gia đình');
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
        return ['id'=>$hasRecord?(int)$row['id']:null,'has_water_record'=>$hasRecord,'household_id'=>(int)($row['household_id']??$row['household_id_base']??0),'household_code'=>(string)($row['household_code']??''),'head_citizen_name'=>(string)($row['head_citizen_name']??''),'area_code'=>(string)($row['area_code']??''),'address'=>(string)($row['household_address']??''),'phone'=>(string)($row['household_phone']??''),'connection_type'=>$type,'connection_type_label'=>$type!==''?(self::CONNECTION_LABELS[$type]??self::CONNECTION_LABELS['OTHER']):'Chưa xác định','water_supply_form'=>$supply,'water_supply_form_label'=>$supply!==''?(self::SUPPLY_FORM_LABELS[$supply]??self::SUPPLY_FORM_LABELS['OTHER']):'Chưa xác định','water_source'=>(string)($row['water_source']??''),'provider_name'=>(string)($row['provider_name']??''),'meter_number'=>(string)($row['meter_number']??''),'has_water_meter'=>$meter,'has_water_meter_label'=>self::METER_LABELS[$meter]??self::METER_LABELS['NOT_APPLICABLE'],'contract_number'=>(string)($row['contract_number']??''),'installed_date'=>$row['installed_date']??null,'monthly_usage_m3'=>(float)($row['monthly_usage_m3']??0),'monthly_fee'=>(float)($row['monthly_fee']??0),'is_clean_standard'=>$clean==='COMPLIANT','clean_water_status'=>$clean,'clean_water_status_label'=>self::CLEAN_STATUS_LABELS[$clean]??self::CLEAN_STATUS_LABELS['UNKNOWN'],'hygienic_water_status'=>$hygienic,'hygienic_water_status_label'=>self::HYGIENIC_STATUS_LABELS[$hygienic]??self::HYGIENIC_STATUS_LABELS['UNKNOWN'],'last_test_date'=>$row['last_test_date']??null,'test_result'=>(string)($row['test_result']??''),'verification_basis'=>$basis,'verification_basis_label'=>self::BASIS_LABELS[$basis]??self::BASIS_LABELS['NONE'],'confirmation_date'=>$row['confirmation_date']??null,'confirmation_agency'=>(string)($row['confirmation_agency']??''),'status'=>(string)($row['status']??''),'status_label'=>isset($row['status'])?(self::STATUS_LABELS[$row['status']]??self::STATUS_LABELS['ACTIVE']):'Chưa có thông tin','note'=>(string)($row['note']??''),'created_at'=>$row['created_at']??null,'updated_at'=>$row['updated_at']??null];
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
            'label' => $labels[$row['code']] ?? 'Chưa xác định',
            'value' => (int) $row['value'],
        ], $rows);
    }

    private function detailHeaders(): array
    {
        return ['Mã hộ', 'Chủ hộ', 'Khu vực', 'Nguồn nước chính', 'Hình thức cấp nước', 'Tình trạng nước sạch', 'Nước hợp vệ sinh', 'Đơn vị/công trình cấp nước', 'Có đồng hồ nước', 'Thời điểm bắt đầu', 'Căn cứ xác định', 'Ngày xác nhận', 'Đơn vị xác nhận', 'Ghi chú'];
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
