<?php

namespace App\Controllers;

use App\Core\BaseController;

final class UserController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('user', 'read');
        $this->ok($this->users()->paginate($this->query()));
    }

    public function show(string $id): void
    {
        $this->requirePermission('user', 'read');
        $user = $this->users()->findById((int) $id);
        $user ? $this->ok($this->users()->publicUser($user)) : $this->fail('KhÃ´ng tÃ¬m tháº¥y ngÆ°á»i dÃ¹ng', 404);
    }

    public function store(): void
    {
        $actor = $this->requirePermission('user', 'create');
        $input = (array) $this->input();
        $this->requireInputFields($input, ['username' => 'TÃªn Ä‘Äƒng nháº­p', 'email' => 'Email', 'password' => 'Máº­t kháº©u', 'role' => 'Vai trÃ²']);
        $user = $this->users()->create($input, $actor);
        $this->audit($actor, 'user', 'create', 'Táº¡o ngÆ°á»i dÃ¹ng', $user['id']);
        $this->ok($this->users()->publicUser($user));
    }

    public function update(string $id): void
    {
        $actor = $this->requirePermission('user', 'update');
        $user = $this->users()->updateUser((int) $id, (array) $this->input(), $actor);
        $this->audit($actor, 'user', 'update', 'Cáº­p nháº­t ngÆ°á»i dÃ¹ng', $id);
        $this->ok($this->users()->publicUser($user));
    }

    public function destroy(string $id): void
    {
        $actor = $this->requirePermission('user', 'delete');
        $this->users()->deleteUser((int) $id, (int) $actor['id']);
        $this->audit($actor, 'user', 'delete', 'XÃ³a ngÆ°á»i dÃ¹ng', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function resetPassword(string $id): void
    {
        $actor = $this->requirePermission('user', 'update');
        $password = (string) $this->input('password', '');
        $this->users()->changePassword((int) $id, $password, (int) $actor['id']);
        $this->audit($actor, 'user', 'reset_password', 'Äá»•i máº­t kháº©u ngÆ°á»i dÃ¹ng', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function lock(string $id): void
    {
        $actor = $this->requirePermission('user', 'update');
        $this->users()->lock((int) $id, (int) $actor['id']);
        $this->audit($actor, 'user', 'lock', 'KhÃ³a ngÆ°á»i dÃ¹ng', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function unlock(string $id): void
    {
        $actor = $this->requirePermission('user', 'update');
        $this->users()->unlock((int) $id, (int) $actor['id']);
        $this->audit($actor, 'user', 'unlock', 'Má»Ÿ khÃ³a ngÆ°á»i dÃ¹ng', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function roles(): void
    {
        $this->requirePermission('user', 'read');
        $this->ok($this->users()->roles());
    }
}
