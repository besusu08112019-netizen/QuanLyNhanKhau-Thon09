<?php

namespace App\Models;

use App\Core\BaseModel;

final class Permission extends BaseModel
{
    public function matrix(): array
    {
        $roles = ['ADMIN' => 'Quản trị', 'OFFICER' => 'Cán bộ', 'VIEWER' => 'Chỉ xem', 'DATA_ENTRY' => 'Chỉ nhập liệu', 'NO_DELETE' => 'Không được xóa', 'NO_EXPORT' => 'Không được xuất dữ liệu'];
        $modules = ['dashboard','household','citizen','movement','report','pdf','import','export','print','user','permission','logs','settings','backup'];
        $actions = ['read','create','update','delete','export','print','restore'];
        $rows = $this->fetchAll('SELECT role, module, action, allowed FROM permissions');
        $matrix = [];
        foreach ($roles as $role => $label) {
            $matrix[$role] = ['role' => $role, 'label' => $label, 'permissions' => []];
            foreach ($modules as $module) foreach ($actions as $action) $matrix[$role]['permissions'][$module][$action] = false;
        }
        foreach ($rows as $row) {
            $role = $row['role'];
            if (!isset($matrix[$role])) continue;
            $matrix[$role]['permissions'][$row['module']][$row['action']] = (bool) $row['allowed'];
        }
        $matrix['ADMIN']['adminNote'] = 'Admin toàn quyền, hệ thống luôn cho phép ở tầng bảo mật.';
        return ['roles' => array_values($matrix), 'modules' => $modules, 'actions' => $actions];
    }

    public function updateMany(array $items, int $userId): array
    {
        foreach ($items as $item) {
            $role = (string) ($item['role'] ?? '');
            if ($role === 'SUPER_ADMIN' || $role === 'ADMIN') continue;
            $module = preg_replace('/[^a-z_]/', '', (string) ($item['module'] ?? ''));
            $action = preg_replace('/[^a-z_]/', '', (string) ($item['action'] ?? ''));
            if ($role === '' || $module === '' || $action === '') continue;
            $allowed = !empty($item['allowed']) ? 1 : 0;
            $this->execute('INSERT INTO permissions (role, module, action, allowed, updated_by) VALUES (:role,:module,:action,:allowed,:user) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed), updated_by=VALUES(updated_by)', ['role' => $role, 'module' => $module, 'action' => $action, 'allowed' => $allowed, 'user' => $userId]);
        }
        return $this->matrix();
    }
}
