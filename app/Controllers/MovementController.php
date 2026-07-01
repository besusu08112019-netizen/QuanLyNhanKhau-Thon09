<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Movement;

final class MovementController extends BaseController
{
    private Movement $movements;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->movements = new Movement();
    }

    public function index(): void
    {
        $this->requirePermission('movement', 'read');
        $this->ok($this->movements->paginate($this->query()));
    }

    public function show(string $id): void
    {
        $this->requirePermission('movement', 'read');
        $row = $this->movements->find((int) $id);
        $row ? $this->ok($row) : $this->fail('Không tìm thấy biến động', 404);
    }

    public function store(): void
    {
        $user = $this->requirePermission('movement', 'create');
        $row = $this->movements->create($this->input(), (int) $user['id']);
        $this->audit($user, 'movement', 'create', 'Tạo biến động nhân khẩu', $row['id']);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('movement', 'update');
        $row = $this->movements->update((int) $id, $this->input(), (int) $user['id']);
        $this->audit($user, 'movement', 'update', 'Cập nhật biến động nhân khẩu', $id);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('movement', 'delete');
        $this->movements->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, 'movement', 'delete', 'Xóa biến động nhân khẩu', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function types(): void
    {
        $this->requirePermission('movement', 'read');
        $this->ok($this->movements->types());
    }
}
