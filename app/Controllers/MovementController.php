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
        $row ? $this->ok($row) : $this->fail('Khong tim thay bien dong', 404);
    }

    public function store(): void
    {
        $user = $this->requirePermission('movement', 'create');
        $row = $this->movements->create($this->input(), (int) $user['id']);
        $this->audit($user, 'movement', 'create', 'Tao bien dong nhan khau', $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('movement', 'update');
        $before = $this->movements->find((int) $id);
        if (!$before) $this->fail('Khong tim thay bien dong', 404);
        $row = $this->movements->update((int) $id, $this->input(), (int) $user['id']);
        $this->audit($user, 'movement', 'update', 'Cap nhat bien dong nhan khau', $row['id'], ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $this->requirePermission('movement', 'delete');
        $this->fail('Bien dong dan cu la nhat ky lich su, khong duoc xoa.', 409);
    }

    public function types(): void
    {
        $this->requirePermission('movement', 'read');
        $this->ok($this->movements->types());
    }
}
