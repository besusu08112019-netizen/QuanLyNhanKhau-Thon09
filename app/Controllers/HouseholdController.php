<?php

namespace App\Controllers;

use App\Core\BaseController;
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
        $this->households->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, 'household', 'delete', 'Kết thúc hộ dân', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function bulkDelete(): void
    {
        $user = $this->requirePermission('household', 'delete');
        $ids = (array) $this->input('ids', []);
        $deleted = $this->households->bulkSoftDelete($ids, (int) $user['id']);
        $this->audit($user, 'household', 'delete', 'Kết thúc hàng loạt hộ gia đình', null, ['ids' => array_values(array_map('intval', $ids)), 'deleted' => $deleted]);
        $this->ok(['success' => $deleted, 'errors' => []]);
    }
}
