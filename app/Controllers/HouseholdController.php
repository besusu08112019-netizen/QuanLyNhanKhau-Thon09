<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Household;

final class HouseholdController extends BaseController
{
    private Household $households;
    public function __construct($request) { parent::__construct($request); $this->households = new Household(); }

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
    public function show(string $id): void { $this->requirePermission('household', 'read'); $row = $this->households->find((int) $id); $row ? $this->ok($row) : $this->fail('Không tìm thấy hộ dân', 404); }
    public function store(): void { $user = $this->requirePermission('household', 'create'); $row = $this->households->create($this->input(), (int) $user['id']); $this->audit($user, 'household', 'create', 'Tạo hộ dân', $row['id']); $this->ok($row); }
    public function update(string $id): void { $user = $this->requirePermission('household', 'update'); $row = $this->households->update((int) $id, $this->input(), (int) $user['id']); $this->audit($user, 'household', 'update', 'Cập nhật hộ dân', $id); $this->ok($row); }
    public function destroy(string $id): void { $user = $this->requirePermission('household', 'delete'); $this->households->softDelete((int) $id, (int) $user['id']); $this->audit($user, 'household', 'delete', 'Xóa mềm hộ dân', $id); $this->ok(['id' => (int) $id]); }
    public function bulkDelete(): void { $user = $this->requirePermission('household', 'delete'); $ids = (array) $this->input('ids', []); $success = 0; $errors = []; foreach ($ids as $id) { try { $this->households->softDelete((int) $id, (int) $user['id']); $success++; } catch (\Throwable $e) { $errors[] = ['id' => $id, 'message' => $e->getMessage()]; } } $this->audit($user, 'household', 'delete', 'Xóa nhiều hộ dân', null, ['ids' => $ids, 'success' => $success, 'errors' => $errors]); $this->ok(['success' => $success, 'errors' => $errors]); }
}
