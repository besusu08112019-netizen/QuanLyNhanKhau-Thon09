<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Permission;

final class PermissionController extends BaseController
{
    private Permission $permissions;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->permissions = new Permission();
    }

    public function index(): void
    {
        $this->requireSuperAdmin('permission', 'read');
        $this->ok($this->permissions->matrix());
    }

    public function update(): void
    {
        $user = $this->requireSuperAdmin('permission', 'update');
        $matrix = $this->permissions->updateMany((array) $this->input('items', []), (int) $user['id']);
        $this->audit($user, 'permission', 'update', 'Cập nhật phân quyền');
        $this->ok($matrix);
    }
}
