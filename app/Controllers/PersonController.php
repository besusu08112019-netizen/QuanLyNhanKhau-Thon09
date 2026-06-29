<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Citizen;

final class PersonController extends BaseController
{
    private Citizen $citizens;
    public function __construct($request) { parent::__construct($request); $this->citizens = new Citizen(); }

    public function index(): void
    {
        $this->requirePermission('citizen', 'read');
        $this->ok($this->citizens->page($this->personFilters()));
    }

    public function temporaryResidence(): void
    {
        $this->requirePermission('citizen', 'read');
        $filters = $this->personFilters();
        $filters['residencyStatus'] = 'TEMPORARY';
        $this->ok($this->citizens->page($filters));
    }

    public function temporaryAbsence(): void
    {
        $this->requirePermission('citizen', 'read');
        $filters = $this->personFilters();
        $filters['presenceStatus'] = 'AWAY';
        $this->ok($this->citizens->page($filters));
    }

    public function show(string $id): void { $this->requirePermission('citizen', 'read'); $row = $this->citizens->find((int) $id); $row ? $this->ok($row) : $this->fail('Không tìm thấy nhân khẩu', 404); }
    public function store(): void { $user = $this->requirePermission('citizen', 'create'); $row = $this->citizens->create($this->input(), (int) $user['id']); $this->audit($user, 'citizen', 'create', 'Tạo nhân khẩu', $row['id']); $this->ok($row); }
    public function update(string $id): void { $user = $this->requirePermission('citizen', 'update'); $row = $this->citizens->update((int) $id, $this->input(), (int) $user['id']); $this->audit($user, 'citizen', 'update', 'Cập nhật nhân khẩu', $id); $this->ok($row); }
    public function destroy(string $id): void { $user = $this->requirePermission('citizen', 'delete'); $this->citizens->softDelete((int) $id, (int) $user['id']); $this->audit($user, 'citizen', 'delete', 'Xóa mềm nhân khẩu', $id); $this->ok(['id' => (int) $id]); }
    public function restore(string $id): void { $user = $this->requirePermission('citizen', 'update'); $this->citizens->restore((int) $id, (int) $user['id']); $this->audit($user, 'citizen', 'update', 'Khôi phục nhân khẩu', $id); $this->ok(['id' => (int) $id]); }
    public function bulkDelete(): void { $user = $this->requirePermission('citizen', 'delete'); $ids = (array) $this->input('ids', []); $success = 0; $errors = []; foreach ($ids as $id) { try { $this->citizens->softDelete((int) $id, (int) $user['id']); $success++; } catch (\Throwable $e) { $errors[] = ['id' => $id, 'message' => $e->getMessage()]; } } $this->audit($user, 'citizen', 'delete', 'Xóa nhiều nhân khẩu', null, ['ids' => $ids, 'success' => $success, 'errors' => $errors]); $this->ok(['success' => $success, 'errors' => $errors]); }

    private function personFilters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'status' => $this->query('status', ''),
            'presenceStatus' => $this->query('presenceStatus', ''),
            'residencyStatus' => $this->query('residencyStatus', ''),
            'householdId' => $this->query('householdId', $this->query('householdCode', '')),
        ];
    }
}
