<?php

namespace App\Services;

use App\Core\Database;
use PDO;

final class PopulationMovementService
{
    private PDO $db;
    private array $columnCache = [];
    private array $enumCache = [];

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    public function afterCitizenCreated(array $citizen, array $input, int $userId): void
    {
        $this->applyCitizenBusinessFields((int) $citizen['id'], $input, $userId);
        $fresh = $this->citizen((int) $citizen['id']) ?: $citizen;
        $type = $this->isBirth($input) ? 'BIRTH' : 'MOVE_IN';
        $this->recordMovement($fresh, $type, [
            'from_address' => $this->text($input, ['moveInPlace', 'move_in_place', 'fromAddress', 'from_address']),
            'to_address' => $fresh['current_address'] ?? $fresh['household_address'] ?? null,
            'reason' => $this->text($input, ['moveInType', 'move_in_type', 'formationSource', 'formation_source', 'reason']),
            'effective_date' => $this->date($input, ['moveInDate', 'move_in_date', 'effectiveDate', 'effective_date']) ?? date('Y-m-d'),
            'document_number' => $this->text($input, ['decisionNumber', 'decision_number', 'documentNumber', 'document_number']),
            'note' => $type === 'BIRTH' ? 'Tự động ghi nhận khai sinh khi thêm nhân khẩu' : 'Tự động ghi nhận chuyển đến khi thêm nhân khẩu',
            'after_data' => $fresh,
        ], $userId);
        $this->syncHouseholdStatus((int) ($fresh['household_id'] ?? $citizen['household_id'] ?? 0), $userId);
    }

    public function afterCitizenUpdated(array $before, array $after, array $input, int $userId): void
    {
        $this->applyCitizenBusinessFields((int) $after['id'], $input, $userId);
        $fresh = $this->citizen((int) $after['id']) ?: $after;

        if (($before['life_status'] ?? '') !== 'DECEASED' && ($fresh['life_status'] ?? '') === 'DECEASED') {
            $this->recordMovement($fresh, 'DEATH', [
                'from_address' => $before['current_address'] ?? $before['household_address'] ?? null,
                'reason' => $this->text($input, ['moveOutReason', 'move_out_reason', 'reason']) ?: 'Khai tử',
                'effective_date' => $this->date($input, ['moveOutDate', 'move_out_date', 'effectiveDate', 'effective_date']) ?? date('Y-m-d'),
                'document_number' => $this->text($input, ['decisionNumber', 'decision_number', 'documentNumber', 'document_number']),
                'before_data' => $before,
                'after_data' => $fresh,
            ], $userId);
        }

        $freshTransferredOut = ($fresh['residency_status'] ?? '') === 'TRANSFERRED_OUT' || $this->hasMoveOutSignal($input);
        if (($before['residency_status'] ?? '') !== 'TRANSFERRED_OUT' && $freshTransferredOut) {
            $this->recordMovement($fresh, 'MOVE_OUT', [
                'from_address' => $before['current_address'] ?? $before['household_address'] ?? null,
                'to_address' => $fresh['move_out_place'] ?? $this->text($input, ['moveOutPlace', 'move_out_place']),
                'reason' => $fresh['move_out_reason'] ?? $this->text($input, ['moveOutReason', 'move_out_reason', 'reason']),
                'effective_date' => $fresh['move_out_date'] ?? $this->date($input, ['moveOutDate', 'move_out_date', 'effectiveDate', 'effective_date']) ?? date('Y-m-d'),
                'document_number' => $fresh['decision_number'] ?? $this->text($input, ['decisionNumber', 'decision_number', 'documentNumber', 'document_number']),
                'before_data' => $before,
                'after_data' => $fresh,
            ], $userId);
        }

        if ((int) ($before['household_id'] ?? 0) !== (int) ($fresh['household_id'] ?? 0)) {
            $this->recordMovement($fresh, 'MOVE_IN', [
                'from_address' => $before['household_address'] ?? null,
                'to_address' => $fresh['household_address'] ?? $fresh['current_address'] ?? null,
                'reason' => 'Chuyển hộ / nhập hộ',
                'effective_date' => $this->date($input, ['moveInDate', 'move_in_date', 'effectiveDate', 'effective_date']) ?? date('Y-m-d'),
                'before_data' => $before,
                'after_data' => $fresh,
            ], $userId);
        }

        if (($before['relationship'] ?? '') !== ($fresh['relationship'] ?? '') && in_array('Chủ hộ', [$before['relationship'] ?? '', $fresh['relationship'] ?? ''], true)) {
            $this->recordMovement($fresh, 'HOUSEHOLD_HEAD_CHANGE', [
                'reason' => 'Thay đổi chủ hộ',
                'effective_date' => date('Y-m-d'),
                'before_data' => ['relationship' => $before['relationship'] ?? null, 'head_citizen_name' => $before['head_citizen_name'] ?? null],
                'after_data' => ['relationship' => $fresh['relationship'] ?? null, 'head_citizen_name' => $fresh['head_citizen_name'] ?? null],
            ], $userId);
        }

        if ($this->hasMeaningfulCitizenChange($before, $fresh)) {
            $this->recordMovement($fresh, 'CITIZEN_UPDATE', [
                'reason' => $this->text($input, ['reason']) ?: 'Cập nhật thông tin nhân khẩu',
                'effective_date' => date('Y-m-d'),
                'before_data' => $this->compactCitizenHistory($before),
                'after_data' => $this->compactCitizenHistory($fresh),
            ], $userId);
        }

        $this->syncHouseholdStatus((int) ($before['household_id'] ?? 0), $userId);
        $this->syncHouseholdStatus((int) ($fresh['household_id'] ?? 0), $userId);
    }

    public function markCitizenMovedOut(int $id, array $input, int $userId): void
    {
        $this->markOneCitizenMovedOut($id, $input, $userId);
    }

    public function markCitizensMovedOut(array $ids, array $input, int $userId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (!$ids) throw new \RuntimeException('Chưa chọn nhân khẩu cần chuyển đi');
        $this->db->beginTransaction();
        try {
            foreach ($ids as $id) {
                $this->markOneCitizenMovedOut($id, $input, $userId);
            }
            $this->db->commit();
            return count($ids);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function afterHouseholdCreated(array $household, array $input, int $userId): void
    {
        $this->applyHouseholdBusinessFields((int) $household['id'], $input, $userId);
    }

    public function afterHouseholdUpdated(array $before, array $after, array $input, int $userId): void
    {
        $this->applyHouseholdBusinessFields((int) $after['id'], $input, $userId);
        $fresh = $this->household((int) $after['id']) ?: $after;
        if (($before['status'] ?? '') !== ($fresh['status'] ?? '') && in_array(($fresh['status'] ?? ''), ['TRANSFERRED_OUT', 'ENDED', 'MERGED'], true)) {
            $type = ($fresh['status'] ?? '') === 'MERGED' ? 'HOUSEHOLD_MERGE' : 'MOVE_OUT';
            $this->recordHouseholdMovement($fresh, $type, $input, $userId, $before, $fresh);
        }
        if (($before['head_citizen_id'] ?? null) !== ($fresh['head_citizen_id'] ?? null)) {
            $this->recordHouseholdMovement($fresh, 'HOUSEHOLD_HEAD_CHANGE', $input, $userId, $before, $fresh);
        }
    }

    private function markOneCitizenMovedOut(int $id, array $input, int $userId): void
    {
        $before = $this->citizen($id);
        if (!$before) throw new \RuntimeException('Không tìm thấy nhân khẩu');

        $sets = ['status="INACTIVE"', 'presence_status="AWAY"', 'updated_by=:user'];
        $params = ['id' => $id, 'user' => $userId];
        if ($this->enumAllows('citizens', 'residency_status', 'TRANSFERRED_OUT')) {
            $sets[] = 'residency_status="TRANSFERRED_OUT"';
        }
        foreach ([
            'move_out_date' => ['moveOutDate', 'move_out_date', 'effectiveDate', 'effective_date'],
            'move_out_place' => ['moveOutPlace', 'move_out_place', 'toAddress', 'to_address'],
            'move_out_reason' => ['moveOutReason', 'move_out_reason', 'reason'],
            'decision_number' => ['decisionNumber', 'decision_number', 'documentNumber', 'document_number'],
        ] as $column => $keys) {
            if ($this->columnExists('citizens', $column)) {
                $sets[] = $column . '=:' . $column;
                $params[$column] = $column === 'move_out_date' ? ($this->date($input, $keys) ?? date('Y-m-d')) : $this->text($input, $keys);
            }
        }
        $stmt = $this->db->prepare('UPDATE citizens SET ' . implode(',', $sets) . ' WHERE id=:id');
        $stmt->execute($params);

        $after = $this->citizen($id) ?: $before;
        $this->recordMovement($after, 'MOVE_OUT', [
            'from_address' => $before['current_address'] ?? $before['household_address'] ?? null,
            'to_address' => $after['move_out_place'] ?? $this->text($input, ['moveOutPlace', 'move_out_place', 'toAddress', 'to_address']),
            'reason' => $after['move_out_reason'] ?? $this->text($input, ['moveOutReason', 'move_out_reason', 'reason']) ?? 'Chuyển đi',
            'effective_date' => $after['move_out_date'] ?? $this->date($input, ['moveOutDate', 'move_out_date', 'effectiveDate', 'effective_date']) ?? date('Y-m-d'),
            'document_number' => $after['decision_number'] ?? null,
            'before_data' => $before,
            'after_data' => $after,
        ], $userId);
        $this->syncHouseholdStatus((int) ($before['household_id'] ?? 0), $userId);
    }

    private function applyCitizenBusinessFields(int $id, array $input, int $userId): void
    {
        $map = [
            'move_out_date' => ['moveOutDate', 'move_out_date'],
            'move_out_place' => ['moveOutPlace', 'move_out_place'],
            'move_out_reason' => ['moveOutReason', 'move_out_reason'],
            'move_in_date' => ['moveInDate', 'move_in_date'],
            'move_in_place' => ['moveInPlace', 'move_in_place'],
            'move_in_type' => ['moveInType', 'move_in_type'],
            'formation_source' => ['formationSource', 'formation_source'],
            'decision_number' => ['decisionNumber', 'decision_number', 'documentNumber', 'document_number'],
        ];
        $sets = [];
        $params = ['id' => $id, 'user' => $userId];
        foreach ($map as $column => $keys) {
            if (!$this->columnExists('citizens', $column)) continue;
            $value = str_ends_with($column, '_date') ? $this->date($input, $keys) : $this->text($input, $keys);
            if ($value === null || $value === '') continue;
            $sets[] = $column . '=:' . $column;
            $params[$column] = $value;
        }
        if ($this->hasMoveOutSignal($input)) {
            $sets[] = 'status="INACTIVE"';
            $sets[] = 'presence_status="AWAY"';
            if ($this->enumAllows('citizens', 'residency_status', 'TRANSFERRED_OUT')) {
                $sets[] = 'residency_status="TRANSFERRED_OUT"';
            }
        }
        if (!$sets) return;
        $sets[] = 'updated_by=:user';
        $stmt = $this->db->prepare('UPDATE citizens SET ' . implode(',', array_unique($sets)) . ' WHERE id=:id');
        $stmt->execute($params);
    }

    private function applyHouseholdBusinessFields(int $id, array $input, int $userId): void
    {
        $map = [
            'household_move_out_date' => ['householdMoveOutDate', 'household_move_out_date'],
            'household_move_out_place' => ['householdMoveOutPlace', 'household_move_out_place'],
            'household_move_in_date' => ['householdMoveInDate', 'household_move_in_date'],
            'household_move_in_place' => ['householdMoveInPlace', 'household_move_in_place'],
        ];
        $sets = [];
        $params = ['id' => $id, 'user' => $userId];
        foreach ($map as $column => $keys) {
            if (!$this->columnExists('households', $column)) continue;
            $value = str_ends_with($column, '_date') ? $this->date($input, $keys) : $this->text($input, $keys);
            if ($value === null || $value === '') continue;
            $sets[] = $column . '=:' . $column;
            $params[$column] = $value;
        }
        if (!$sets) return;
        $sets[] = 'updated_by=:user';
        $stmt = $this->db->prepare('UPDATE households SET ' . implode(',', $sets) . ' WHERE id=:id');
        $stmt->execute($params);
    }

    private function recordHouseholdMovement(array $household, string $type, array $input, int $userId, ?array $before = null, ?array $after = null): void
    {
        $citizenId = (int) ($household['head_citizen_id'] ?? 0);
        if ($citizenId <= 0) {
            $citizenId = (int) ($this->scalar('SELECT id FROM citizens WHERE household_id=:id AND status <> "DELETED" ORDER BY relationship="Chủ hộ" DESC, id LIMIT 1', ['id' => (int) $household['id']]) ?? 0);
        }
        if ($citizenId <= 0) return;
        $citizen = $this->citizen($citizenId);
        if (!$citizen) return;
        $this->recordMovement($citizen, $type, [
            'from_address' => $before['address'] ?? null,
            'to_address' => $this->text($input, ['householdMoveOutPlace', 'household_move_out_place', 'householdMoveInPlace', 'household_move_in_place']) ?? ($after['address'] ?? null),
            'reason' => $this->text($input, ['reason']) ?: $type,
            'effective_date' => $this->date($input, ['householdMoveOutDate', 'household_move_out_date', 'householdMoveInDate', 'household_move_in_date']) ?? date('Y-m-d'),
            'object_type' => 'household',
            'object_id' => (int) $household['id'],
            'object_code' => $household['household_code'] ?? null,
            'actor_name' => $household['head_citizen_name'] ?? null,
            'before_data' => $before,
            'after_data' => $after,
        ], $userId);
    }

    private function syncHouseholdStatus(int $householdId, int $userId): void
    {
        if ($householdId <= 0) return;
        $residencyClause = $this->enumAllows('citizens', 'residency_status', 'TRANSFERRED_OUT') ? ' AND residency_status <> "TRANSFERRED_OUT"' : '';
        $count = (int) ($this->scalar('SELECT COUNT(*) FROM citizens WHERE household_id=:id AND status="ACTIVE" AND life_status="ALIVE"' . $residencyClause, ['id' => $householdId]) ?? 0);
        if ($count === 0) {
            $ended = $this->enumAllows('households', 'status', 'ENDED') ? 'ENDED' : 'INACTIVE';
            $blocked = $this->enumAllows('households', 'status', 'MERGED') ? '("DELETED","MERGED")' : '("DELETED")';
            $stmt = $this->db->prepare('UPDATE households SET status=:status, updated_by=:user WHERE id=:id AND status NOT IN ' . $blocked);
            $stmt->execute(['id' => $householdId, 'user' => $userId, 'status' => $ended]);
        }
    }

    private function recordMovement(array $citizen, string $type, array $payload, int $userId): void
    {
        $columns = ['citizen_id','household_id','type','from_address','to_address','reason','effective_date','document_number','note','status','created_by'];
        $values = [':citizen_id',':household_id',':type',':from_address',':to_address',':reason',':effective_date',':document_number',':note','"ACTIVE"',':created_by'];
        $params = [
            'citizen_id' => (int) $citizen['id'],
            'household_id' => (int) ($citizen['household_id'] ?? 0) ?: null,
            'type' => $this->safeMovementType($type),
            'from_address' => $payload['from_address'] ?? null,
            'to_address' => $payload['to_address'] ?? null,
            'reason' => $payload['reason'] ?? null,
            'effective_date' => $payload['effective_date'] ?? date('Y-m-d'),
            'document_number' => $payload['document_number'] ?? null,
            'note' => $payload['note'] ?? null,
            'created_by' => $userId,
        ];
        foreach (['object_type','object_id','object_code','actor_name','before_data','after_data'] as $column) {
            if (!$this->columnExists('movements', $column)) continue;
            $columns[] = $column;
            $values[] = ':' . $column;
            $params[$column] = match ($column) {
                'object_type' => $payload['object_type'] ?? 'citizen',
                'object_id' => $payload['object_id'] ?? (int) $citizen['id'],
                'object_code' => $payload['object_code'] ?? ($citizen['citizen_code'] ?? null),
                'actor_name' => $payload['actor_name'] ?? ($citizen['full_name'] ?? null),
                'before_data' => $this->jsonOrNull($payload['before_data'] ?? null),
                'after_data' => $this->jsonOrNull($payload['after_data'] ?? null),
            };
        }
        $stmt = $this->db->prepare('INSERT INTO movements (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')');
        $stmt->execute($params);
    }

    private function citizen(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT c.*, h.household_code, h.address AS household_address, h.head_citizen_name FROM citizens c LEFT JOIN households h ON h.id=c.household_id WHERE c.id=:id AND c.status <> "DELETED"');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function household(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM households WHERE id=:id AND status <> "DELETED"');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function hasMeaningfulCitizenChange(array $before, array $after): bool
    {
        foreach (['citizen_code','household_id','full_name','gender','date_of_birth','identity_number','relationship','current_address','marital_status','life_status','residency_status','presence_status'] as $key) {
            if (($before[$key] ?? null) != ($after[$key] ?? null)) return true;
        }
        return false;
    }

    private function compactCitizenHistory(array $row): array
    {
        return array_intersect_key($row, array_flip(['citizen_code','household_id','household_code','full_name','gender','date_of_birth','identity_number','relationship','current_address','marital_status','life_status','residency_status','presence_status']));
    }

    private function isBirth(array $input): bool
    {
        $text = mb_strtolower((string) ($this->text($input, ['formationSource', 'formation_source', 'moveInType', 'move_in_type']) ?? ''));
        return str_contains($text, 'khai sinh') || str_contains($text, 'birth') || str_contains($text, 'sinh');
    }

    private function hasMoveOutSignal(array $input): bool
    {
        return $this->date($input, ['moveOutDate', 'move_out_date']) !== null || $this->text($input, ['moveOutPlace', 'move_out_place']) !== null || mb_strtolower((string) ($input['movementAction'] ?? '')) === 'move_out';
    }

    private function text(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string) $data[$key]) !== '') return trim((string) $data[$key]);
        }
        return null;
    }

    private function date(array $data, array $keys): ?string
    {
        $value = $this->text($data, $keys);
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnCache)) return $this->columnCache[$key];
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        return $this->columnCache[$key] = ((int) $stmt->fetchColumn() > 0);
    }

    private function enumAllows(string $table, string $column, string $value): bool
    {
        if (!$this->columnExists($table, $column)) return false;
        $key = $table . '.' . $column . '.' . $value;
        if (array_key_exists($key, $this->enumCache)) return $this->enumCache[$key];
        $stmt = $this->db->prepare('SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
        $stmt->execute(['table' => $table, 'column' => $column]);
        $type = (string) $stmt->fetchColumn();
        return $this->enumCache[$key] = str_contains($type, "'" . $value . "'");
    }

    private function safeMovementType(string $type): string
    {
        if ($this->enumAllows('movements', 'type', $type)) return $type;
        return match ($type) {
            'HOUSEHOLD_SPLIT', 'HOUSEHOLD_MERGE', 'HOUSEHOLD_HEAD_CHANGE', 'CITIZEN_UPDATE', 'RESTORE' => 'OTHER',
            default => $type,
        };
    }

    private function jsonOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) return $value;
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}
