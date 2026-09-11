<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Services\HouseholdCategoryService;

final class Household extends BaseModel
{
    private ?PopulationStatistics $statistics = null;
    private ?HouseholdCategoryService $categoryService = null;

    private const MERITORIOUS_POLICY_COLUMNS = [
        'martyr_relative',
        'wounded_soldier',
        'sick_soldier',
        'chemical_warfare_victim',
        'imprisoned_resistance_activist',
        'youth_volunteer',
        'resistance_hero',
        'revolutionary_activist',
    ];

    public const CATEGORY_OPTIONS = [
        'poor' => 'Hộ nghèo',
        'near_poor' => 'Hộ cận nghèo',
        'escaped_poverty' => 'Hộ mới thoát nghèo',
        'policy' => 'Hộ chính sách',
        'meritorious' => 'Hộ có công',
        'normal' => 'Hộ bình thường',
        'other' => 'Khác',
    ];

    public const RESIDENCE_STATUS_LABELS = [
        'resident' => 'Sinh sống tại thôn',
        'away_for_work' => 'Đi làm ăn xa',
        'settled_elsewhere' => 'Sinh sống ổn định nơi khác',
        'partial' => 'Sinh sống một phần',
        'inactive' => 'Không còn cư trú',
    ];

    public function paginate(array $filters): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$sqlWhere, $params] = $this->where($filters);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id $sqlWhere", $params)['total'];
        $order = $this->listOrder($filters, ['household_code' => 'h.household_code', 'head_citizen_name' => 'h.head_citizen_name', 'area_code' => 'h.area_code', 'status' => 'h.status'], 'household_code', 'ASC', ['h.id ASC']);
        $meritoriousHouseholdExpr = $this->meritoriousHouseholdExists('h');
        $disabledHouseholdExpr = $this->disabledHouseholdExists('h');
        $currentHeadExpr = $this->currentHeadExistsExpression('h');
        $currentHeadIdExpr = $this->currentHeadValueExpression('h', 'id');
        $currentHeadNameExpr = $this->currentHeadValueExpression('h', 'full_name');
        $categoryExpressions = $this->categoryService()->selectExpressions('h');
        $items = $this->fetchAll("SELECT h.id, h.household_code, h.head_citizen_id, h.head_citizen_name, h.address, h.phone, h.area_code, h.residence_status, h.residence_status_mode, h.current_residence_place, h.residence_started_at, h.residence_expected_return_at, h.residence_note, h.member_residence_json, $meritoriousHouseholdExpr AS meritorious_policy, $disabledHouseholdExpr AS disabled_policy, $currentHeadExpr AS head_is_current, $currentHeadIdExpr AS current_head_citizen_id, $currentHeadNameExpr AS current_head_citizen_name, h.poor_household, h.near_poor_household, h.note, h.status, {$categoryExpressions}, COALESCE(v.total_members,0) AS member_count_real, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id $sqlWhere $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->withPhoto($this->withResidence($this->withCategory($row))), $items), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $meritoriousHouseholdExpr = $this->meritoriousHouseholdExists('h');
        $disabledHouseholdExpr = $this->disabledHouseholdExists('h');
        $currentHeadExpr = $this->currentHeadExistsExpression('h');
        $currentHeadIdExpr = $this->currentHeadValueExpression('h', 'id');
        $currentHeadNameExpr = $this->currentHeadValueExpression('h', 'full_name');
        $categoryExpressions = $this->categoryService()->selectExpressions('h');
        $row = $this->fetchOne('SELECT h.*, ' . $meritoriousHouseholdExpr . ' AS meritorious_policy, ' . $disabledHouseholdExpr . ' AS disabled_policy, ' . $currentHeadExpr . ' AS head_is_current, ' . $currentHeadIdExpr . ' AS current_head_citizen_id, ' . $currentHeadNameExpr . ' AS current_head_citizen_name, ' . $categoryExpressions . ', COALESCE(v.total_members,0) AS member_count_real, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count FROM households h LEFT JOIN v_household_member_counts v ON v.household_id = h.id WHERE h.id = :id AND h.status <> "DELETED" AND ' . $this->tenantWhere('h', 'households'), $this->withTenant(['id' => $id]));
        return $row ? $this->withPhoto($this->withResidence($this->withCategory($row))) : null;
    }

    public function findByCode(string $code): ?array { return $this->fetchOne('SELECT * FROM households WHERE household_code = :code AND status <> "DELETED" AND ' . $this->tenantWhere('households'), $this->withTenant(['code' => strtoupper(trim($code))])); }

    public function create(array $data, int $userId): array
    {
        $params = $this->params($data, $userId);
        $this->ensureUniqueCode($params['code']);
        $columns = ['household_code', 'head_citizen_name', 'address', 'phone', 'area_code', 'poor_household', 'near_poor_household', 'note', 'residence_status', 'residence_status_mode', 'current_residence_place', 'residence_started_at', 'residence_expected_return_at', 'residence_note', 'member_residence_json', 'status', 'created_by'];
        $this->addTenantInsert('households', $columns, $params);
        $id = $this->insert('INSERT INTO households (' . implode(',', $columns) . ') VALUES (:code,:head,:address,:phone,:area,:poor,:near_poor,:note,:residence_status,:residence_status_mode,:current_residence_place,:residence_started_at,:residence_expected_return_at,:residence_note,:member_residence_json,:status,:user' . (in_array('village_id', $columns, true) ? ',:village_id' : '') . ')', $params);
        return $this->find($id);
    }

    public function update(int $id, array $data, int $userId): array
    {
        $existing = $this->find($id);
        if (!$existing) throw new \RuntimeException('Không tìm thấy hộ dân');
        $params = $this->params($data, $userId, $existing); $params['id'] = $id;
        $this->ensureUniqueCode($params['code'], $id);
        $this->execute('UPDATE households SET household_code=:code, head_citizen_name=:head, address=:address, phone=:phone, area_code=:area, poor_household=:poor, near_poor_household=:near_poor, note=:note, residence_status=:residence_status, residence_status_mode=:residence_status_mode, current_residence_place=:current_residence_place, residence_started_at=:residence_started_at, residence_expected_return_at=:residence_expected_return_at, residence_note=:residence_note, member_residence_json=:member_residence_json, status=:status, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('households'), $this->withTenant($params));
        return $this->find($id);
    }

    public function softDelete(int $id, int $userId): void
    {
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $members = (int) $this->fetchOne('SELECT COUNT(*) AS total FROM citizens WHERE household_id = :id AND status <> "DELETED" AND COALESCE(life_status,"ALIVE") <> "DECEASED" AND COALESCE(residency_status,"PERMANENT") <> "TRANSFERRED_OUT" AND COALESCE(presence_status,"AT_HOME") <> "MOVED_OUT" AND ' . $this->tenantWhere('citizens'), $this->withTenant(['id' => $id]))['total'];
        if ($members > 0) throw new \RuntimeException('Hộ gia đình vẫn còn nhân khẩu hoặc dữ liệu liên quan. Vui lòng xử lý các dữ liệu liên kết trước khi kết thúc hộ.');
        $status = $this->enumAllows('households', 'status', 'ENDED') ? 'ENDED' : 'INACTIVE';
        $this->execute('UPDATE households SET status=:status, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('households'), $this->withTenant(['id' => $id, 'user' => $userId, 'status' => $status]));
    }

    public function bulkSoftDelete(array $ids, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (!$ids) throw new \RuntimeException('Chưa chọn hộ gia đình cần kết thúc');
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

    private function where(array $filters): array
    {
        $params = $this->withTenant();
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $recordStatus = strtoupper($status);
        $historicalStatuses = ['ENDED', 'INACTIVE', 'MERGED', 'TRANSFERRED_OUT', 'MOVED_OUT'];
        $includeHistorical = $this->bool($filters['includeHistorical'] ?? $filters['include_historical'] ?? 0) || in_array($recordStatus, $historicalStatuses, true);
        $where = [$includeHistorical ? $this->statistics()->historicalHouseholdCondition('h') : $this->activeHouseholdCondition('h'), $this->tenantWhere('h', 'households')];
        $residenceInput = trim((string) ($filters['residenceStatus'] ?? $filters['residence_status'] ?? $filters['householdResidenceStatus'] ?? ''));
        $residenceFilter = $residenceInput !== '' ? $this->residenceStatus($residenceInput) : '';
        if ($status !== '') {
            if (in_array($status, ['active', 'active_in_village'], true)) {
                $where[] = 'COALESCE(v.at_home_count,0) > 0';
            } elseif (in_array($status, ['temporary_absence', 'has_away', 'away'], true)) {
                $where[] = 'COALESCE(v.away_count,0) > 0';
            } elseif (in_array($status, ['empty_home', 'away_household', 'all_away'], true)) {
                $where[] = 'COALESCE(v.total_members,0) > 0 AND COALESCE(v.at_home_count,0) = 0 AND COALESCE(v.away_count,0) = COALESCE(v.total_members,0)';
            } elseif (in_array($status, ['needs_review', 'needs_status_review', 'no_current_members'], true)) {
                $where[] = 'COALESCE(v.total_members,0) = 0';
            } elseif (in_array($status, ['needs_head_review', 'missing_head', 'no_current_head'], true)) {
                $where[] = 'COALESCE(v.total_members,0) > 0 AND NOT (' . $this->currentHeadExistsExpression('h') . ')';
            } elseif (in_array($status, ['resident', 'away_for_work', 'settled_elsewhere', 'partial', 'inactive', 'outside'], true)) {
                $where[] = $this->residenceStatusSql('h', 'v') . ' = :legacy_residence_status';
                $params['legacy_residence_status'] = $this->residenceStatus($status);
            } else {
                $where[] = 'h.status = :status';
                $params['status'] = strtoupper($status);
            }
        }

        if ($residenceFilter !== '') {
            $where[] = $this->residenceStatusSql('h', 'v') . ' = :residence_status_filter';
            $params['residence_status_filter'] = $residenceFilter;
        }

        $category = $this->filterCategory($filters);
        if ($category) $this->addCategoryWhere($where, $params, $category);

        if (!empty($filters['search'])) {
            $qRaw = trim((string) $filters['search']);
            $q = '%' . $qRaw . '%';
            $categorySearch = $this->categoryKey($qRaw);
            $searchParts = ['h.household_code LIKE :q_code', 'h.head_citizen_name LIKE :q_head', 'h.address LIKE :q_address', 'h.phone LIKE :q_phone', 'h.area_code LIKE :q_area', 'h.note LIKE :q_note'];
            $params['q_code'] = $q;
            $params['q_head'] = $q;
            $params['q_address'] = $q;
            $params['q_phone'] = $q;
            $params['q_area'] = $q;
            $params['q_note'] = $q;
            if ($categorySearch) {
                $categoryParts = [];
                $this->addCategoryWhere($categoryParts, $params, $categorySearch, 'search_category');
                if ($categoryParts) $searchParts[] = '(' . implode(' AND ', $categoryParts) . ')';
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function activeHouseholdCondition(string $alias): string
    {
        return $this->statistics()->householdCondition($alias);
    }

    private function statistics(): PopulationStatistics
    {
        return $this->statistics ??= new PopulationStatistics();
    }

    private function categoryService(): HouseholdCategoryService
    {
        return $this->categoryService ??= new HouseholdCategoryService();
    }

    private function filterCategory(array $filters): string
    {
        foreach (['householdCategory', 'household_category', 'household_type', 'category', 'householdType'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') return $this->categoryKey($value);
        }
        return '';
    }

    private function addCategoryWhere(array &$where, array &$params, string $category, string $prefix = 'category'): void
    {
        match ($category) {
            'poor', 'near_poor', 'medium', 'normal', 'policy' => $where[] = $this->categoryService()->condition($category, 'h'),
            'meritorious' => $where[] = $this->meritoriousHouseholdExists('h'),
            'other' => $where[] = $this->disabledHouseholdExists('h'),
            'escaped_poverty' => $this->addTextCategoryWhere($where, $params, $category, $prefix),
            default => null,
        };
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category, string $prefix): void
    {
        $label = self::CATEGORY_OPTIONS[$category] ?? $category;
        $key = $prefix . '_' . preg_replace('/[^a-z_]/', '', $category);
        $where[] = '(h.note LIKE :' . $key . '_label OR h.note LIKE :' . $key . '_key)';
        $params[$key . '_label'] = '%' . $label . '%';
        $params[$key . '_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function params(array $data, int $userId, ?array $existing = null): array
    {
        $code = $this->requiredTextParam($data, ['householdCode', 'household_code'], $existing, 'household_code', true, 'Ma ho la bat buoc');
        $address = $this->requiredTextParam($data, ['address'], $existing, 'address', false, 'Dia chi la bat buoc');
        if ($code === '') throw new \RuntimeException('Mã hộ là bắt buộc');
        if ($address === '') throw new \RuntimeException('Địa chỉ là bắt buộc');
        $category = $this->categoryParam($data);
        $note = $this->noteParam($data, $existing, $category);
        $mode = $this->residenceStatusMode($data, $existing);
        $selectedResidenceStatus = $this->residenceStatus($data['residenceStatus'] ?? $data['residence_status'] ?? $existing['residence_status_stored'] ?? $existing['residence_status'] ?? 'resident');
        $storedResidenceStatus = $mode === 'AUTO' ? $this->autoResidenceStatus($existing ?? $data) : $selectedResidenceStatus;
        $currentResidencePlace = $this->optionalText($data, ['currentResidencePlace', 'current_residence_place'], $existing, 'current_residence_place');
        $residenceStartedAt = $this->optionalDate($data, ['residenceStartedAt', 'residence_started_at'], $existing, 'residence_started_at');
        $residenceExpectedReturnAt = $this->optionalDate($data, ['residenceExpectedReturnAt', 'residence_expected_return_at'], $existing, 'residence_expected_return_at');
        $residenceNote = $this->optionalText($data, ['residenceNote', 'residence_note'], $existing, 'residence_note');
        $memberResidenceJson = $this->optionalMemberResidenceJson($data, $existing);
        if ($storedResidenceStatus === 'settled_elsewhere' && $currentResidencePlace === null) {
            throw new \RuntimeException('Nơi đang sinh sống là bắt buộc khi hộ sinh sống ổn định nơi khác');
        }
        $area = $this->areaCodeParam($data, $existing);
        return [
            'code' => $code,
            'head' => $this->optionalText($data, ['headCitizenName', 'head_citizen_name'], $existing, 'head_citizen_name'),
            'address' => $address,
            'phone' => $this->optionalText($data, ['phone'], $existing, 'phone'),
            'area' => $area,
            'poor' => $this->legacyFlagParam($data, ['poorHousehold', 'poor_household'], $existing, 'poor_household', $category, 'poor'),
            'near_poor' => $this->legacyFlagParam($data, ['nearPoorHousehold', 'near_poor_household'], $existing, 'near_poor_household', $category, 'near_poor'),
            'note' => $note,
            'residence_status' => $storedResidenceStatus,
            'residence_status_mode' => $mode,
            'current_residence_place' => $currentResidencePlace,
            'residence_started_at' => $residenceStartedAt,
            'residence_expected_return_at' => $residenceExpectedReturnAt,
            'residence_note' => $residenceNote,
            'member_residence_json' => $memberResidenceJson,
            'status' => $this->statusParam($data, $existing),
            'user' => $userId,
        ];
    }

    private function requiredTextParam(array $data, array $keys, ?array $existing, string $existingKey, bool $uppercase, string $message): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $value = trim((string) ($data[$key] ?? ''));
                if ($value === '') throw new \RuntimeException($message);
                return $uppercase ? strtoupper($value) : $value;
            }
        }

        $value = trim((string) ($existing[$existingKey] ?? ''));
        if ($value === '') throw new \RuntimeException($message);
        return $uppercase ? strtoupper($value) : $value;
    }

    private function categoryParam(array $data): string
    {
        foreach (['householdType', 'household_type', 'category'] as $key) {
            if (array_key_exists($key, $data)) return $this->categoryKey($data[$key] ?? '');
        }
        return '';
    }

    private function categoryWasSubmitted(array $data): bool
    {
        foreach (['householdType', 'household_type', 'category'] as $key) {
            if (array_key_exists($key, $data)) return true;
        }
        return false;
    }

    private function noteParam(array $data, ?array $existing, string $category): ?string
    {
        $note = $this->optionalText($data, ['note'], $existing, 'note');
        if (in_array($category, ['policy','escaped_poverty'], true)) {
            $label = self::CATEGORY_OPTIONS[$category] ?? '';
            $note = trim(($note ? $note . '; ' : '') . $label);
        }
        return $note === '' ? null : $note;
    }

    private function legacyFlagParam(array $data, array $keys, ?array $existing, string $existingKey, string $category, string $categoryTrigger): int
    {
        if ($category === $categoryTrigger) return 1;
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) return $this->bool($data[$key]);
        }
        if ($this->categoryWasSubmitted($data)) return 0;
        return (int) ($existing[$existingKey] ?? 0);
    }

    private function statusParam(array $data, ?array $existing): string
    {
        if (!array_key_exists('status', $data)) return strtoupper((string) ($existing['status'] ?? 'ACTIVE'));

        $status = strtoupper(trim((string) ($data['status'] ?? '')));
        $allowed = ['ACTIVE', 'INACTIVE', 'TRANSFERRED_OUT', 'ENDED', 'MERGED', 'DELETED'];
        if (!in_array($status, $allowed, true)) throw new \RuntimeException('Trang thai ho khong hop le');
        return $status;
    }

    private function areaCodeParam(array $data, ?array $existing): ?string
    {
        foreach (['areaCode', 'area_code'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = trim((string) ($data[$key] ?? ''));
                return $value !== '' ? strtoupper($value) : null;
            }
        }

        if ($existing !== null) {
            $value = trim((string) ($existing['area_code'] ?? ''));
            return $value !== '' ? strtoupper($value) : null;
        }

        return null;
    }

    private function withCategory(array $row): array
    {
        if (array_key_exists('poverty_type', $row)) {
            $type = strtoupper(trim((string) $row['poverty_type']));
            $row['poverty_type'] = in_array($type, ['POOR', 'NEAR_POOR', 'MEDIUM', 'NONE'], true) ? $type : null;
        }
        if (array_key_exists('canonical_household_type_key', $row)) {
            $key = HouseholdCategoryService::normalizeKey($row['canonical_household_type_key']);
            $row['household_type_key'] = $key;
            $row['household_type'] = HouseholdCategoryService::LABELS[$key] ?? self::CATEGORY_OPTIONS['normal'];
        } else {
            $row['household_type'] = $this->categoryLabel($row);
            $row['household_type_key'] = $this->categoryKey($row['household_type']);
        }
        $atHome = (int) ($row['at_home_count'] ?? 0);
        $away = (int) ($row['away_count'] ?? 0);
        $currentMembers = (int) ($row['member_count_real'] ?? 0);
        $active = strtoupper((string) ($row['status'] ?? 'ACTIVE')) === 'ACTIVE';
        $headIsCurrent = (int) ($row['head_is_current'] ?? 0) === 1;
        $row['historical_head_citizen_id'] = $row['head_citizen_id'] ?? null;
        $row['historical_head_citizen_name'] = $row['head_citizen_name'] ?? null;
        $row['current_head_citizen_id'] = $headIsCurrent ? ($row['current_head_citizen_id'] ?? null) : null;
        $row['current_head_citizen_name'] = $headIsCurrent ? ($row['current_head_citizen_name'] ?? null) : null;
        $row['head_citizen_is_current'] = $headIsCurrent;
        $row['presence_status_key'] = $atHome > 0 ? 'active' : ($away > 0 ? 'empty_home' : 'no_members');
        $row['presence_status_label'] = match ($row['presence_status_key']) {
            'active' => 'Hoạt động tại thôn',
            'empty_home' => 'Không hoạt động ở thôn',
            default => 'Chưa có nhân khẩu',
        };
        $row['current_member_count'] = $currentMembers;
        $row['needs_head_review'] = $active && $currentMembers > 0 && !$headIsCurrent;
        $row['needs_household_status_review'] = $active && $currentMembers === 0;
        $row['review_alerts'] = [];
        if ($row['needs_head_review']) $row['review_alerts'][] = 'Thiếu chủ hộ hiện tại - cần chọn chủ hộ mới';
        if ($row['needs_household_status_review']) $row['review_alerts'][] = 'Hộ không còn nhân khẩu hiện tại - cần xác nhận trạng thái hộ';
        return $row;
    }


    private function withResidence(array $row): array
    {
        $storedStatus = $this->residenceStatus($row['residence_status'] ?? 'resident');
        $mode = $this->normalizeResidenceStatusMode($row['residence_status_mode'] ?? 'AUTO');
        $automaticStatus = $this->autoResidenceStatus($row);
        $status = $mode === 'AUTO' ? $automaticStatus : $storedStatus;
        $row['residence_status_stored'] = $storedStatus;
        $row['residenceStatusStored'] = $storedStatus;
        $row['residence_status_mode'] = $mode;
        $row['residenceStatusMode'] = $mode;
        $row['automatic_residence_status'] = $automaticStatus;
        $row['automaticResidenceStatus'] = $automaticStatus;
        $row['automatic_residence_status_label'] = self::RESIDENCE_STATUS_LABELS[$automaticStatus] ?? self::RESIDENCE_STATUS_LABELS['resident'];
        $row['automaticResidenceStatusLabel'] = $row['automatic_residence_status_label'];
        $row['residence_status'] = $status;
        $row['residence_status_label'] = self::RESIDENCE_STATUS_LABELS[$status] ?? self::RESIDENCE_STATUS_LABELS['resident'];
        $row['residenceStatus'] = $status;
        $row['residenceStatusLabel'] = $row['residence_status_label'];
        $row['residenceStatusIsManual'] = $mode === 'MANUAL';
        $row['currentResidencePlace'] = $row['current_residence_place'] ?? null;
        $row['residenceStartedAt'] = $row['residence_started_at'] ?? null;
        $row['residenceExpectedReturnAt'] = $row['residence_expected_return_at'] ?? null;
        $row['residenceNote'] = $row['residence_note'] ?? null;
        $decoded = [];
        if (!empty($row['member_residence_json'])) {
            $decoded = json_decode((string) $row['member_residence_json'], true);
            if (!is_array($decoded)) $decoded = [];
        }
        $row['member_residence'] = $decoded;
        $row['memberResidence'] = $decoded;
        return $row;
    }

    private function residenceStatusMode(array $data, ?array $existing = null): string
    {
        $explicitAuto = $data['useAutomaticResidenceStatus'] ?? $data['use_automatic_residence_status'] ?? null;
        if ($explicitAuto !== null && $explicitAuto !== '') return $this->bool($explicitAuto) ? 'AUTO' : 'MANUAL';
        $mode = $data['residenceStatusMode'] ?? $data['residence_status_mode'] ?? null;
        if ($mode !== null && $mode !== '') return $this->normalizeResidenceStatusMode($mode);
        if (array_key_exists('residenceStatus', $data) || array_key_exists('residence_status', $data)) return 'MANUAL';
        return $this->normalizeResidenceStatusMode($existing['residence_status_mode'] ?? 'AUTO');
    }

    private function normalizeResidenceStatusMode(mixed $value): string
    {
        return strtoupper(trim((string) $value)) === 'MANUAL' ? 'MANUAL' : 'AUTO';
    }

    private function autoResidenceStatus(array $row): string
    {
        $atHome = (int) ($row['at_home_count'] ?? $row['atHomeCount'] ?? $row['at_home'] ?? 0);
        $away = (int) ($row['away_count'] ?? $row['awayCount'] ?? $row['away'] ?? 0);
        $total = (int) ($row['member_count_real'] ?? $row['total_members'] ?? $row['member_count'] ?? $row['members'] ?? ($atHome + $away));
        return $total > 0 && $atHome === 0 && $away === $total ? 'away_for_work' : 'resident';
    }

    private function residenceStatusSql(string $householdAlias = 'h', string $countsAlias = 'v'): string
    {
        return "CASE WHEN COALESCE($householdAlias.residence_status_mode,'AUTO') = 'AUTO' AND COALESCE($countsAlias.total_members,0) > 0 AND COALESCE($countsAlias.at_home_count,0) = 0 AND COALESCE($countsAlias.away_count,0) = COALESCE($countsAlias.total_members,0) THEN 'away_for_work' ELSE COALESCE($householdAlias.residence_status,'resident') END";
    }

    private function residenceStatus(mixed $value): string
    {
        $status = strtolower(trim((string) $value));
        if ($status === 'outside') return 'settled_elsewhere';
        return array_key_exists($status, self::RESIDENCE_STATUS_LABELS) ? $status : 'resident';
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') return null;
        if (preg_match('/^\d{4}$/', $text)) return $text . '-01-01';
        if (preg_match('/^\d{4}-\d{2}$/', $text)) return $text . '-01';
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : null;
    }

    private function optionalText(array $data, array $keys, ?array $existing, string $existingKey): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $value = trim((string) $data[$key]);
                return $value === '' ? null : $value;
            }
        }
        $value = trim((string) ($existing[$existingKey] ?? ''));
        return $value === '' ? null : $value;
    }

    private function optionalDate(array $data, array $keys, ?array $existing, string $existingKey): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) return $this->dateOrNull($data[$key]);
        }
        return $this->dateOrNull($existing[$existingKey] ?? null);
    }

    private function optionalMemberResidenceJson(array $data, ?array $existing): ?string
    {
        foreach (['memberResidence', 'member_residence', 'member_residence_json'] as $key) {
            if (array_key_exists($key, $data)) return $this->memberResidenceJson($data[$key]);
        }
        $value = $existing['member_residence_json'] ?? null;
        return $value === '' ? null : ($value === null ? null : (string) $value);
    }

    private function memberResidenceJson(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return null;
    }

    private function currentHeadExistsExpression(string $alias): string
    {
        return 'EXISTS (SELECT 1 FROM citizens hhc WHERE hhc.household_id=' . $alias . '.id AND hhc.relationship="' . \App\Policies\HouseholdRelationPolicy::HEAD . '" AND ' . $this->statistics()->currentCitizenCondition('hhc') . ')';
    }

    private function currentHeadValueExpression(string $alias, string $column): string
    {
        return '(SELECT hhc.' . $column . ' FROM citizens hhc WHERE hhc.household_id=' . $alias . '.id AND hhc.relationship="' . \App\Policies\HouseholdRelationPolicy::HEAD . '" AND ' . $this->statistics()->currentCitizenCondition('hhc') . ' ORDER BY hhc.id LIMIT 1)';
    }

    public function categoryLabel(array $row): string
    {
        if (array_key_exists('canonical_household_type_key', $row)) {
            $key = HouseholdCategoryService::normalizeKey($row['canonical_household_type_key']);
            return HouseholdCategoryService::LABELS[$key] ?? HouseholdCategoryService::LABELS[HouseholdCategoryService::NORMAL];
        }
        if ((int) ($row['poor_household'] ?? 0) === 1) return self::CATEGORY_OPTIONS['poor'];
        if ((int) ($row['near_poor_household'] ?? 0) === 1) return self::CATEGORY_OPTIONS['near_poor'];
        if ((int) ($row['meritorious_policy'] ?? 0) === 1) return self::CATEGORY_OPTIONS['meritorious'];
        if ((int) ($row['disabled_policy'] ?? 0) === 1) return self::CATEGORY_OPTIONS['other'];
        $noteKey = $this->categoryKey((string) ($row['note'] ?? ''));
        if ($noteKey && isset(self::CATEGORY_OPTIONS[$noteKey])) return self::CATEGORY_OPTIONS[$noteKey];
        return self::CATEGORY_OPTIONS['normal'];
    }

    public function categoryKey(mixed $value): string
    {
        $text = $this->normalize((string) $value);
        if ($text === '') return '';
        return match (true) {
            str_contains($text, 'can ngheo') || str_contains($text, 'near poor') => 'near_poor',
            str_contains($text, 'trung binh') || str_contains($text, 'medium') || str_contains($text, 'average') => 'medium',
            str_contains($text, 'moi thoat ngheo') || str_contains($text, 'thoat ngheo') || str_contains($text, 'escaped poverty') => 'escaped_poverty',
            str_contains($text, 'chinh sach') || str_contains($text, 'policy') => 'policy',
            str_contains($text, 'co cong') || str_contains($text, 'gia dinh co cong') || str_contains($text, 'meritorious') => 'meritorious',
            str_contains($text, 'binh thuong') || str_contains($text, 'normal') || $text === 'khong' => 'normal',
            str_contains($text, 'khac') || str_contains($text, 'tan tat') || str_contains($text, 'khuyet tat') || str_contains($text, 'other') => 'other',
            str_contains($text, 'ngheo') || str_contains($text, 'poor') => 'poor',
            default => '',
        };
    }

    private function withPhoto(array $row): array
    {
        $row['photo_file_id'] = null;
        $row['photo_url'] = null;
        $row['household_photo_url'] = null;
        $row['thumbnail_url'] = null;
        $row['gallery_count'] = 0;
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0 || !$this->tableExists('file_attachments')) return $row;

        $columns = $this->existingColumns('file_attachments', ['id', 'module', 'entity_type', 'entity_id', 'status', 'file_type', 'mime_type']);
        if (!in_array('id', $columns, true) || !in_array('entity_id', $columns, true)) return $row;

        $where = ['entity_id = :entity_id'];
        $params = ['entity_id' => $id];
        if (in_array('entity_type', $columns, true) && in_array('module', $columns, true)) {
            $where[] = 'COALESCE(entity_type, module) = :entity_type';
            $params['entity_type'] = 'household';
        } elseif (in_array('entity_type', $columns, true)) {
            $where[] = 'entity_type = :entity_type';
            $params['entity_type'] = 'household';
        } elseif (in_array('module', $columns, true)) {
            $where[] = 'module = :entity_type';
            $params['entity_type'] = 'household';
        }
        if (in_array('status', $columns, true)) $where[] = '(status IS NULL OR status <> "DELETED")';

        $imageParts = [];
        if (in_array('file_type', $columns, true)) $imageParts[] = 'file_type IN ("PHOTO","IMAGE")';
        if (in_array('mime_type', $columns, true)) $imageParts[] = 'mime_type LIKE "image/%"';
        if ($imageParts) $where[] = '(' . implode(' OR ', $imageParts) . ')';

        $whereSql = implode(' AND ', $where);
        $count = $this->fetchOne('SELECT COUNT(*) AS total FROM file_attachments WHERE ' . $whereSql, $params);
        $photo = $this->fetchOne('SELECT id FROM file_attachments WHERE ' . $whereSql . ' ORDER BY id DESC LIMIT 1', $params);
        $fileId = isset($photo['id']) ? (int) $photo['id'] : 0;
        $row['gallery_count'] = (int) ($count['total'] ?? 0);
        if ($fileId > 0) {
            $row['photo_file_id'] = $fileId;
            $row['photo_url'] = '/api/files/' . $fileId . '/preview';
            $row['household_photo_url'] = $row['photo_url'];
            $row['thumbnail_url'] = $row['photo_url'];
        }
        return $row;
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
    private function ensureUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $params = $this->withTenant(['code' => $code]);
        $sql = 'SELECT id FROM households WHERE household_code=:code AND status <> "DELETED" AND ' . $this->tenantWhere('households');
        if ($ignoreId) { $sql .= ' AND id <> :id'; $params['id'] = $ignoreId; }
        if ($this->fetchOne($sql, $params)) throw new \RuntimeException('Mã hộ đã tồn tại');
    }

    private function enumAllows(string $table, string $column, string $value): bool
    {
        if (!$this->columnExists($table, $column)) return false;
        $row = $this->fetchOne('SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1', ['table' => $table, 'column' => $column]);
        return str_contains((string) ($row['COLUMN_TYPE'] ?? ''), "'" . $value . "'");
    }

    private function bool(mixed $value): int
    {
        $text = mb_strtolower(trim((string) $value));
        return in_array($text, ['1','true','yes','on','co','có','x'], true) ? 1 : 0;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function meritoriousCitizenExpression(string $alias): string
    {
        $parts = [];
        foreach (self::MERITORIOUS_POLICY_COLUMNS as $column) {
            if ($this->columnExists('citizens', $column)) $parts[] = $alias . '.' . $column . '=1';
        }
        return $parts ? '(' . implode(' OR ', $parts) . ')' : '0=1';
    }

    private function meritoriousHouseholdExists(string $alias): string
    {
        $citizenPolicy = $this->meritoriousCitizenExpression('mhc');
        if ($citizenPolicy === '0=1') return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens mhc WHERE mhc.household_id=' . $alias . '.id AND ' . $this->statistics()->citizenCondition('mhc') . ' AND ' . $citizenPolicy . ')';
    }

    private function disabledHouseholdExists(string $alias): string
    {
        if (!$this->columnExists('citizens', 'disabled_person')) return '0=1';
        return 'EXISTS (SELECT 1 FROM citizens dhc WHERE dhc.household_id=' . $alias . '.id AND ' . $this->statistics()->citizenCondition('dhc') . ' AND dhc.disabled_person=1)';
    }
}
