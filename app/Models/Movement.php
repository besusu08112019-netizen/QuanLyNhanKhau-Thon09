<?php

namespace App\Models;

use App\Core\BaseModel;

final class Movement extends BaseModel
{
    private const TYPES = [
        'BIRTH' => 'Sinh',
        'DEATH' => 'Tử',
        'MOVE_IN' => 'Chuyển đến',
        'MOVE_OUT' => 'Chuyển đi',
        'HOUSEHOLD_SPLIT' => 'Tách hộ',
        'HOUSEHOLD_MERGE' => 'Nhập hộ',
        'HOUSEHOLD_HEAD_CHANGE' => 'Thay đổi chủ hộ',
        'CITIZEN_UPDATE' => 'Thay đổi thông tin nhân khẩu',
        'RESTORE' => 'Hoàn tác',
        'TEMPORARY_RESIDENCE' => 'Tạm trú',
        'TEMPORARY_ABSENCE' => 'Tạm vắng',
        'OTHER' => 'Khác',
    ];

    public function paginate(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$sqlWhere, $params] = $this->where($filters);
        try {
            $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM movements m INNER JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id $sqlWhere", $params)['total'];
            $items = $this->fetchAll("SELECT m.*, c.full_name, c.identity_number, c.citizen_code, h.household_code FROM movements m INNER JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id $sqlWhere ORDER BY m.effective_date DESC, m.id DESC LIMIT $pageSize OFFSET $offset", $params);
        } catch (\Throwable $e) {
            return $this->fallbackPaginate($filters, $page, $pageSize, $offset, $e);
        }
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    private function fallbackPaginate(array $filters, int $page, int $pageSize, int $offset, \Throwable $sourceError): array
    {
        try {
            [$sqlWhere, $params, $orderBy, $select, $joins] = $this->fallbackQueryParts($filters);
            $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM movements m $joins $sqlWhere", $params)['total'];
            $items = $this->fetchAll("SELECT $select FROM movements m $joins $sqlWhere ORDER BY $orderBy DESC, m.id DESC LIMIT $pageSize OFFSET $offset", $params);
            return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
        } catch (\Throwable $fallbackError) {
            error_log('[MOVEMENT_PAGINATE_FALLBACK_FAILED] ' . $sourceError->getMessage() . ' | ' . $fallbackError->getMessage());
            return ['items' => [], 'page' => $page, 'pageSize' => $pageSize, 'total' => 0, 'totalPages' => 1];
        }
    }

    private function fallbackQueryParts(array $filters): array
    {
        $citizenId = $this->firstExistingColumn('movements', ['citizen_id', 'citizenId']);
        $householdId = $this->firstExistingColumn('movements', ['household_id', 'householdId']);
        $effectiveDate = $this->firstExistingColumn('movements', ['effective_date', 'effectiveDate']);
        $documentNumber = $this->firstExistingColumn('movements', ['document_number', 'documentNumber']);
        $fromAddress = $this->firstExistingColumn('movements', ['from_address', 'fromAddress']);
        $toAddress = $this->firstExistingColumn('movements', ['to_address', 'toAddress']);
        $type = $this->firstExistingColumn('movements', ['type']);
        $reason = $this->firstExistingColumn('movements', ['reason']);
        $note = $this->firstExistingColumn('movements', ['note']);
        $status = $this->firstExistingColumn('movements', ['status']);

        $joins = '';
        if ($citizenId) $joins .= ' LEFT JOIN citizens c ON c.id=m.' . $citizenId;
        if ($householdId) $joins .= ' LEFT JOIN households h ON h.id=m.' . $householdId;
        $where = [];
        $params = [];
        if ($status) $where[] = 'COALESCE(m.' . $status . ', "ACTIVE") <> "DELETED"';
        else $where[] = '1=1';
        if (!empty($filters['type']) && $type) { $where[] = 'm.' . $type . ' = :type'; $params['type'] = $filters['type']; }
        if (!empty($filters['dateFrom']) && $effectiveDate) { $where[] = 'm.' . $effectiveDate . ' >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo']) && $effectiveDate) { $where[] = 'm.' . $effectiveDate . ' <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['month']) && $effectiveDate) { $where[] = 'DATE_FORMAT(m.' . $effectiveDate . ', "%Y-%m") = :month'; $params['month'] = $filters['month']; }
        if (!empty($filters['year']) && $effectiveDate) { $where[] = 'YEAR(m.' . $effectiveDate . ') = :year'; $params['year'] = (int) $filters['year']; }
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $parts = [];
            if ($citizenId) $parts[] = 'c.full_name LIKE :q_name';
            if ($citizenId) $parts[] = 'c.identity_number LIKE :q_identity';
            if ($citizenId) $parts[] = 'c.citizen_code LIKE :q_citizen';
            if ($householdId) $parts[] = 'h.household_code LIKE :q_household';
            if ($reason) $parts[] = 'm.' . $reason . ' LIKE :q_reason';
            if ($documentNumber) $parts[] = 'm.' . $documentNumber . ' LIKE :q_document';
            if ($note) $parts[] = 'm.' . $note . ' LIKE :q_note';
            if ($parts) {
                $where[] = '(' . implode(' OR ', $parts) . ')';
                if ($citizenId) {
                    $params['q_name'] = $q;
                    $params['q_identity'] = $q;
                    $params['q_citizen'] = $q;
                }
                if ($householdId) $params['q_household'] = $q;
                if ($reason) $params['q_reason'] = $q;
                if ($documentNumber) $params['q_document'] = $q;
                if ($note) $params['q_note'] = $q;
            }
        }

        $select = implode(', ', [
            'm.*',
            $citizenId ? 'c.full_name' : 'NULL AS full_name',
            $citizenId ? 'c.identity_number' : 'NULL AS identity_number',
            $citizenId ? 'c.citizen_code' : 'NULL AS citizen_code',
            $householdId ? 'h.household_code' : 'NULL AS household_code',
            $this->aliasExpr($citizenId, 'citizen_id'),
            $this->aliasExpr($householdId, 'household_id'),
            $this->aliasExpr($effectiveDate, 'effective_date'),
            $this->aliasExpr($documentNumber, 'document_number'),
            $this->aliasExpr($fromAddress, 'from_address'),
            $this->aliasExpr($toAddress, 'to_address'),
        ]);
        return ['WHERE ' . implode(' AND ', $where), $params, $effectiveDate ? 'm.' . $effectiveDate : 'm.id', $select, $joins];
    }

    private function firstExistingColumn(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) return $column;
        }
        return null;
    }

    private function aliasExpr(?string $column, string $alias): string
    {
        return $column ? 'm.' . $column . ' AS ' . $alias : 'NULL AS ' . $alias;
    }


    public function page(array $filters = []): array
    {
        return $this->paginate($filters);
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT m.*, c.full_name, c.identity_number, c.citizen_code, h.household_code FROM movements m INNER JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id WHERE m.id=:id AND m.status <> "DELETED"', ['id' => $id]);
    }

    public function create(array $data, int $userId): array
    {
        return $this->record($data, $userId);
    }

    public function update(int $id, array $data, int $userId): array
    {
        throw new \RuntimeException('Biến động dân cư là nhật ký lịch sử, không được sửa trực tiếp.');
    }

    public function softDelete(int $id, int $userId): void
    {
        throw new \RuntimeException('Biến động dân cư là nhật ký lịch sử, không được xóa.');
    }

    public function types(): array
    {
        $items = [];
        foreach (self::TYPES as $value => $label) {
            $items[] = ['value' => $value, 'label' => $label];
        }
        return $items;
    }

    public function record(array $data, int $userId): array
    {
        $params = $this->params($data, $userId);
        $columns = ['citizen_id','household_id','type','from_address','to_address','reason','effective_date','document_number','note','status','created_by'];
        $values = [':citizen_id',':household_id',':type',':from_address',':to_address',':reason',':effective_date',':document_number',':note','"ACTIVE"',':user'];
        foreach (['object_type','object_id','object_code','actor_name','before_data','after_data'] as $column) {
            if ($this->columnExists('movements', $column)) {
                $columns[] = $column;
                $values[] = ':' . $column;
            }
        }
        $id = $this->insert('INSERT INTO movements (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')', $params);
        return $this->find($id) ?: ['id' => $id] + $params;
    }

    private function where(array $filters): array
    {
        $where = ['m.status <> "DELETED"'];
        $params = [];
        if (!empty($filters['type'])) { $where[] = 'm.type = :type'; $params['type'] = $filters['type']; }
        if (!empty($filters['dateFrom'])) { $where[] = 'm.effective_date >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'm.effective_date <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['month'])) { $where[] = 'DATE_FORMAT(m.effective_date, "%Y-%m") = :month'; $params['month'] = $filters['month']; }
        if (!empty($filters['year'])) { $where[] = 'YEAR(m.effective_date) = :year'; $params['year'] = (int) $filters['year']; }
        if (!empty($filters['householdId'])) {
            $where[] = '(m.household_id = :household_id OR h.household_code = :household_code)';
            $params['household_id'] = (int) $filters['householdId'];
            $params['household_code'] = (string) $filters['householdId'];
        }
        if (!empty($filters['citizenId'])) {
            $where[] = '(m.citizen_id = :citizen_id OR c.citizen_code = :citizen_code OR c.identity_number = :citizen_identity)';
            $params['citizen_id'] = (int) $filters['citizenId'];
            $params['citizen_code'] = (string) $filters['citizenId'];
            $params['citizen_identity'] = (string) $filters['citizenId'];
        }
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $where[] = '(c.full_name LIKE :q_name OR c.identity_number LIKE :q_identity OR c.citizen_code LIKE :q_citizen OR h.household_code LIKE :q_household OR m.reason LIKE :q_reason OR m.document_number LIKE :q_document OR m.note LIKE :q_note)';
            $params['q_name'] = $q;
            $params['q_identity'] = $q;
            $params['q_citizen'] = $q;
            $params['q_household'] = $q;
            $params['q_reason'] = $q;
            $params['q_document'] = $q;
            $params['q_note'] = $q;
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function params(array $data, int $userId): array
    {
        $citizenId = (int) ($data['citizenId'] ?? $data['citizen_id'] ?? 0);
        if ($citizenId <= 0) throw new \RuntimeException('Nhân khẩu là bắt buộc khi ghi biến động');
        $citizen = $this->fetchOne('SELECT c.id, c.household_id, c.full_name, c.citizen_code, c.identity_number FROM citizens c WHERE c.id=:id AND c.status <> "DELETED"', ['id' => $citizenId]);
        if (!$citizen) throw new \RuntimeException('Không tìm thấy nhân khẩu để ghi biến động');

        $type = strtoupper((string) ($data['type'] ?? 'OTHER'));
        if (!isset(self::TYPES[$type])) $type = 'OTHER';

        $date = (string) ($data['effectiveDate'] ?? $data['effective_date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        return [
            'citizen_id' => $citizenId,
            'household_id' => (int) ($data['householdId'] ?? $data['household_id'] ?? $citizen['household_id']),
            'type' => $type,
            'from_address' => trim((string) ($data['fromAddress'] ?? $data['from_address'] ?? '')) ?: null,
            'to_address' => trim((string) ($data['toAddress'] ?? $data['to_address'] ?? '')) ?: null,
            'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
            'effective_date' => $date,
            'document_number' => trim((string) ($data['documentNumber'] ?? $data['document_number'] ?? $data['decisionNumber'] ?? $data['decision_number'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'object_type' => trim((string) ($data['objectType'] ?? $data['object_type'] ?? 'citizen')) ?: 'citizen',
            'object_id' => (int) ($data['objectId'] ?? $data['object_id'] ?? $citizenId),
            'object_code' => trim((string) ($data['objectCode'] ?? $data['object_code'] ?? $citizen['citizen_code'] ?? '')) ?: null,
            'actor_name' => trim((string) ($data['actorName'] ?? $data['actor_name'] ?? $citizen['full_name'] ?? '')) ?: null,
            'before_data' => $this->jsonOrNull($data['beforeData'] ?? $data['before_data'] ?? null),
            'after_data' => $this->jsonOrNull($data['afterData'] ?? $data['after_data'] ?? null),
            'user' => $userId,
        ];
    }

    private function jsonOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) return $value;
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}
