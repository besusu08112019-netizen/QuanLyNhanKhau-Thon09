<?php

namespace App\Controllers;

use App\Core\BaseController;

final class UserController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->ok($this->users()->paginate($this->query()));
    }

    public function show(string $id): void
    {
        $this->requireAdmin();
        $user = $this->users()->findById((int) $id);
        $user ? $this->ok($this->users()->publicUser($user)) : $this->fail('Không tìm thấy người dùng', 404);
    }

    public function store(): void
    {
        $actor = $this->requireAdmin();
        $user = $this->users()->create($this->input(), (int) $actor['id']);
        $this->audit($actor, 'user', 'create', 'Tạo người dùng', $user['id']);
        $this->ok($this->users()->publicUser($user));
    }

    public function update(string $id): void
    {
        $actor = $this->requireAdmin();
        $user = $this->users()->updateUser((int) $id, $this->input(), (int) $actor['id']);
        $this->audit($actor, 'user', 'update', 'Cập nhật người dùng', $id);
        $this->ok($this->users()->publicUser($user));
    }

    public function destroy(string $id): void
    {
        $actor = $this->requireAdmin();
        $this->users()->deleteUser((int) $id, (int) $actor['id']);
        $this->audit($actor, 'user', 'delete', 'Xóa người dùng', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function lock(string $id): void
    {
        $actor = $this->requireAdmin();
        $this->users()->lock((int) $id, (int) $actor['id']);
        $this->audit($actor, 'user', 'lock', 'Khóa người dùng', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function unlock(string $id): void
    {
        $actor = $this->requireAdmin();
        $this->users()->unlock((int) $id, (int) $actor['id']);
        $this->audit($actor, 'user', 'unlock', 'Mở khóa người dùng', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function roles(): void
    {
        $this->requireAdmin();
        $this->ok($this->users()->roles());
    }

    private function requireAdmin(): array
    {
        $user = $this->user();
        $this->verifyCsrfToken();
        if ($user['role'] !== 'SUPER_ADMIN') $this->fail('Chỉ Super Admin được thao tác quản trị người dùng', 403);
        return $user;
    }
}
