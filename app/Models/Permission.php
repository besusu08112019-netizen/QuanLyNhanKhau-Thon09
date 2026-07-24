<?php

namespace App\Models;

use App\Core\BaseModel;

final class Permission extends BaseModel
{
    private const ROLES = ['SUPER_ADMIN', 'ADMIN', 'OFFICER', 'VIEWER'];
    private const MODULES = ['dashboard','household','household_business','agriculture','livestock','vehicles','contributions','finance','work_tasks','work_calendar','documents','photo_gallery','houses','public_assets','complaints','citizen','movement','report','pdf','import','export','print','profile','file','gis','photo','video','gps','notification','user','permission','logs','settings','backup','system_admin'];
    private const ACTIONS = ['read','create','update','delete','upload','download','import','export','print','approve','restore','backup'];

    public function matrix(): array
    {
        $roles = [
            'SUPER_ADMIN' => 'Super Admin',
            'ADMIN' => 'Admin',
            'OFFICER' => 'Cán bộ',
            'VIEWER' => 'Khách',
        ];
        $modules = self::MODULES;
        $actions = self::ACTIONS;
        $rows = $this->fetchAll('SELECT role, module, action, allowed FROM permissions');
        $matrix = [];
        foreach ($roles as $role => $label) {
            $matrix[$role] = ['role' => $role, 'label' => $label, 'permissions' => []];
            foreach ($modules as $module) foreach ($actions as $action) $matrix[$role]['permissions'][$module][$action] = $this->defaultAllowed($role, $module, $action);
        }
        foreach ($rows as $row) {
            $role = $row['role'];
            if (!isset($matrix[$role]) || in_array($role, ['SUPER_ADMIN','ADMIN'], true)) continue;
            $matrix[$role]['permissions'][$row['module']][$row['action']] = (bool) $row['allowed'];
        }
        $matrix['SUPER_ADMIN']['adminNote'] = 'Toàn quyền hệ thống.';
        $matrix['ADMIN']['adminNote'] = 'Toàn quyền hệ thống.';
        return ['roles' => array_values($matrix), 'modules' => $modules, 'actions' => $actions];
    }

    public function updateMany(array $items, int $userId): array
    {
        foreach ($items as $item) {
            $role = (string) ($item['role'] ?? '');
            if (in_array($role, ['SUPER_ADMIN', 'ADMIN'], true)) continue;
            $module = preg_replace('/[^a-z_]/', '', (string) ($item['module'] ?? ''));
            $action = preg_replace('/[^a-z_]/', '', (string) ($item['action'] ?? ''));
            if (!in_array($role, self::ROLES, true) || !in_array($module, self::MODULES, true) || !in_array($action, self::ACTIONS, true)) continue;
            $allowed = !empty($item['allowed']) ? 1 : 0;
            $this->execute('INSERT INTO permissions (role, module, action, allowed, updated_by) VALUES (:role,:module,:action,:allowed,:user) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed), updated_by=VALUES(updated_by)', ['role' => $role, 'module' => $module, 'action' => $action, 'allowed' => $allowed, 'user' => $userId]);
        }
        return $this->matrix();
    }

    private function defaultAllowed(string $role, string $module, string $action): bool
    {
        if ($role === 'SUPER_ADMIN') return true;
        if ($role === 'ADMIN') return true;
        if ($role === 'OFFICER') return (in_array($module, ['dashboard','household','household_business','agriculture','livestock','vehicles','contributions','finance','work_tasks','work_calendar','documents','photo_gallery','houses','public_assets','complaints','citizen','movement','report'], true) && in_array($action, ['read','create','update','upload','export'], true)) || ($module === 'notification' && in_array($action, ['read','update'], true)) || ($module === 'gis' && $action === 'read');
        if ($role === 'VIEWER') return (in_array($module, ['dashboard','household','household_business','agriculture','livestock','vehicles','contributions','finance','work_tasks','work_calendar','documents','photo_gallery','houses','public_assets','complaints','citizen','report','gis'], true) && $action === 'read') || ($module === 'notification' && in_array($action, ['read','update'], true));
        return false;
    }
}
