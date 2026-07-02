<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Models\Household;
use App\Services\PopulationMovementService;

final class HouseholdController extends BaseController
{
    private Household $households;
    private PopulationMovementService $movementService;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->households = new Household();
        $this->movementService = new PopulationMovementService();
    }

    public function index(): void
    {
        $this->requirePermission('household', 'read');
        $category = trim((string) $this->query('household_type', $this->query('householdType', $this->query('category', ''))));
        if ($category === '') $category = trim((string) $this->query('category', ''));
        $this->ok($this->households->paginate([
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'status' => $this->query('status', ''),
            'household_type' => $category,
            'category' => $category,
        ]));
    }

    public function show(string $id): void
    {
        $this->requirePermission('household', 'read');
        $row = $this->households->find((int) $id);
        $row ? $this->ok($row) : $this->fail('Không tìm thấy hộ dân', 404);
    }

    public function store(): void
    {
        $user = $this->requirePermission('household', 'create');
        $input = $this->input();
        $row = $this->households->create($input, (int) $user['id']);
        $this->movementService->afterHouseholdCreated($row, $input, (int) $user['id']);
        $row = $this->households->find((int) $row['id']) ?: $row;
        $this->audit($user, 'household', 'create', 'Tạo hộ dân', $row['id']);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('household', 'update');
        $before = $this->households->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy hộ dân', 404);
        $input = $this->input();
        $row = $this->households->update((int) $id, $input, (int) $user['id']);
        $this->movementService->afterHouseholdUpdated($before, $row, $input, (int) $user['id']);
        $row = $this->households->find((int) $id) ?: $row;
        $this->audit($user, 'household', 'update', 'Cập nhật hộ dân và ghi biến động dân cư', $id);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('household', 'delete');
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $before = $this->households->find((int) $id);
            if (!$before) $this->fail('Không tìm thấy hộ dân', 404);
            $this->households->softDelete((int) $id, (int) $user['id']);
            $after = $this->households->find((int) $id) ?: $before;
            $movementAfter = $after;
            $movementAfter['status'] = 'ENDED';
            $this->movementService->afterHouseholdUpdated($before, $movementAfter, $this->input() + ['reason' => 'Kết thúc hộ'], (int) $user['id']);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        $this->audit($user, 'household', 'delete', 'Kết thúc hộ dân', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function bulkDelete(): void
    {
        $user = $this->requirePermission('household', 'delete');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->input('ids', [])), fn($id) => $id > 0)));
        if (!$ids) $this->fail('Chưa chọn hộ gia đình cần kết thúc', 400);

        $db = Database::pdo();
        $deleted = 0;
        $db->beginTransaction();
        try {
            foreach ($ids as $id) {
                $before = $this->households->find($id);
                if (!$before) throw new \RuntimeException('Không tìm thấy hộ dân ID ' . $id);
                $this->households->softDelete($id, (int) $user['id']);
                $after = $this->households->find($id) ?: $before;
                $movementAfter = $after;
                $movementAfter['status'] = 'ENDED';
                $this->movementService->afterHouseholdUpdated($before, $movementAfter, ['reason' => 'Kết thúc hộ hàng loạt'], (int) $user['id']);
                $deleted++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }

        $this->audit($user, 'household', 'delete', 'Kết thúc hàng loạt hộ gia đình', null, ['ids' => $ids, 'deleted' => $deleted]);
        $this->ok(['success' => $deleted, 'errors' => []]);
    }
}
