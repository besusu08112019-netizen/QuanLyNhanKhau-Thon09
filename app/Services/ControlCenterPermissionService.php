<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Repositories\ControlCenterPermissionRepository;
use InvalidArgumentException;

final class ControlCenterPermissionService
{
    private const ROLES = [
        'SYSTEM_ADMIN' => 'Quáº£n trá»‹ há»‡ thá»‘ng',
        'VILLAGE_ADMIN' => 'Quáº£n trá»‹ thÃ´n',
        'STAFF' => 'CÃ¡n bá»™ nháº­p liá»‡u',
        'VIEWER' => 'Chá»‰ xem',
    ];

    private const GROUPS = [
        'units' => [
            'name' => 'ÄÆ¡n vá»‹ hÃ nh chÃ­nh',
            'permissions' => [
                'control_center.units.read' => 'Xem Ä‘Æ¡n vá»‹',
                'control_center.units.create' => 'ThÃªm Ä‘Æ¡n vá»‹',
                'control_center.units.install' => 'CÃ i Ä‘áº·t tenant',
                'control_center.units.update' => 'Sá»­a Ä‘Æ¡n vá»‹',
                'control_center.units.lock' => 'KhÃ³a Ä‘Æ¡n vá»‹',
                'control_center.units.activate' => 'KÃ­ch hoáº¡t Ä‘Æ¡n vá»‹',
            ],
        ],
        'tenants' => [
            'name' => 'Quáº£n lÃ½ Tenant',
            'permissions' => [
                'tenant.view' => 'Xem Tenant',
                'tenant.create' => 'ThÃªm Tenant',
                'tenant.update' => 'Sá»­a Tenant',
                'tenant.lock' => 'KhÃ³a Tenant',
                'tenant.unlock' => 'Má»Ÿ khÃ³a Tenant',
                'tenant.delete' => 'XÃ³a Tenant',
                'tenant.activity.view' => 'Xem nháº­t kÃ½ Tenant',
            ],
        ],
        'users' => [
            'name' => 'TÃ i khoáº£n há»‡ thá»‘ng',
            'permissions' => [
                'control_center.users.read' => 'Xem tÃ i khoáº£n',
                'control_center.users.create' => 'ThÃªm tÃ i khoáº£n',
                'control_center.users.update' => 'Sá»­a tÃ i khoáº£n',
                'control_center.users.deactivate' => 'Ngá»«ng tÃ i khoáº£n',
                'control_center.users.activate' => 'KÃ­ch hoáº¡t tÃ i khoáº£n',
                'control_center.users.reset_password' => 'Äáº·t láº¡i máº­t kháº©u',
            ],
        ],
        'permissions' => [
            'name' => 'PhÃ¢n quyá»n',
            'permissions' => [
                'control_center.permissions.read' => 'Xem phÃ¢n quyá»n',
                'control_center.permissions.update' => 'Cáº­p nháº­t phÃ¢n quyá»n',
            ],
        ],
        'dashboard' => [
            'name' => 'Dashboard',
            'permissions' => [
                'control_center.dashboard.read' => 'Xem dashboard',
            ],
        ],
        'operations' => [
            'name' => 'Váº­n hÃ nh sau',
            'permissions' => [
                'control_center.monitoring.read' => 'Xem giÃ¡m sÃ¡t',
                'control_center.audit.read' => 'Xem nháº­t kÃ½',
                'control_center.configuration.read' => 'Xem cáº¥u hÃ¬nh',
                'control_center.configuration.update' => 'Cáº­p nháº­t cáº¥u hÃ¬nh',
                'control_center.configuration.security' => 'Quáº£n lÃ½ cáº¥u hÃ¬nh báº£o máº­t',
                'control_center.notification.read' => 'Xem thÃ´ng bÃ¡o',
                'control_center.ai.read' => 'DÃ¹ng AI',
            ],
        ],
    ];

    private const CORE_SYSTEM_ADMIN_PERMISSIONS = [
        'control_center.permissions.read',
        'control_center.permissions.update',
        'control_center.users.read',
        'control_center.users.update',
        'control_center.dashboard.read',
        'control_center.configuration.read',
        'control_center.configuration.update',
        'control_center.configuration.security',
    ];

    public function __construct(
        private ControlCenterPermissionRepository $repository,
        private ?ControlCenterAuthorizationInterface $authorization = null,
        private ?ControlCenterAuditService $audit = null
    ) {
        $this->audit ??= new ControlCenterAuditService();
    }

    public function matrix(): array
    {
        if ($this->authorization) {
            $this->authorization->authorize('control_center.permissions.read');
        }
        return $this->buildMatrix();
    }

    public function update(array $input): array
    {
        $actor = $this->requireActor('control_center.permissions.update');
        $items = (array) ($input['items'] ?? []);
        foreach ($items as $item) {
            $role = $this->validateRole((string) ($item['role'] ?? ''));
            $permission = $this->validatePermission((string) ($item['permission'] ?? ''));
            $allowed = (bool) ($item['allowed'] ?? false);
            $this->assertMutable($role, $permission, $allowed);
            $this->repository->set($role, $permission, $allowed, (int) $actor['id']);
            $this->audit?->write($actor, 'permission.updated', null, 'Cáº­p nháº­t phÃ¢n quyá»n', [
                'role' => $role,
                'permission' => $permission,
                'allowed' => $allowed,
            ]);
        }
        return $this->buildMatrix();
    }

    public function reset(array $input): array
    {
        $actor = $this->requireActor('control_center.permissions.update');
        $role = $this->validateRole((string) ($input['role'] ?? ''));
        $permission = $this->validatePermission((string) ($input['permission'] ?? ''));
        $this->assertMutable($role, $permission, $this->defaultAllowed($role, $permission));
        $this->repository->reset($role, $permission);
        $this->audit?->write($actor, 'permission.reset', null, 'KhÃ´i phá»¥c phÃ¢n quyá»n máº·c Ä‘á»‹nh', [
            'role' => $role,
            'permission' => $permission,
        ]);
        return $this->buildMatrix();
    }

    public function isAllowed(string $platformRole, string $permission): bool
    {
        $role = $this->validateRole($platformRole);
        $permission = $this->validatePermission($permission);
        $overrides = $this->repository->overrides();
        $key = $role . '|' . $permission;
        return array_key_exists($key, $overrides) ? (bool) $overrides[$key] : $this->defaultAllowed($role, $permission);
    }

    public function catalog(): array
    {
        return self::GROUPS;
    }

    private function buildMatrix(): array
    {
        $overrides = $this->repository->overrides();
        $groups = [];
        $matrix = [];
        foreach (self::GROUPS as $groupId => $group) {
            $permissions = [];
            foreach ($group['permissions'] as $key => $label) {
                $permissions[] = [
                    'key' => $key,
                    'label' => $label,
                    'action' => substr($key, strrpos($key, '.') + 1),
                ];
                foreach (self::ROLES as $role => $roleLabel) {
                    $matrixKey = $role . '|' . $key;
                    $locked = $role === 'SYSTEM_ADMIN' && in_array($key, self::CORE_SYSTEM_ADMIN_PERMISSIONS, true);
                    $matrix[] = [
                        'role' => $role,
                        'permission' => $key,
                        'allowed' => array_key_exists($matrixKey, $overrides) ? (bool) $overrides[$matrixKey] : $this->defaultAllowed($role, $key),
                        'locked' => $locked,
                        'source' => array_key_exists($matrixKey, $overrides) ? 'override' : 'default',
                    ];
                }
            }
            $groups[] = ['id' => $groupId, 'name' => $group['name'], 'permissions' => $permissions];
        }

        return [
            'roles' => array_map(static fn(string $role, string $label): array => ['role' => $role, 'label' => $label], array_keys(self::ROLES), self::ROLES),
            'groups' => $groups,
            'matrix' => $matrix,
        ];
    }

    private function defaultAllowed(string $role, string $permission): bool
    {
        if ($role === 'SYSTEM_ADMIN') {
            return true;
        }
        return false;
    }

    private function validateRole(string $role): string
    {
        $role = strtoupper(trim($role));
        if (!array_key_exists($role, self::ROLES)) {
            throw new InvalidArgumentException('Vai trÃ² khÃ´ng há»£p lá»‡');
        }
        return $role;
    }

    private function validatePermission(string $permission): string
    {
        $permission = strtolower(trim($permission));
        foreach (self::GROUPS as $group) {
            if (array_key_exists($permission, $group['permissions'])) {
                return $permission;
            }
        }
        throw new InvalidArgumentException('Quyá»n khÃ´ng há»£p lá»‡');
    }

    private function assertMutable(string $role, string $permission, bool $allowed): void
    {
        if ($role === 'SYSTEM_ADMIN' && in_array($permission, self::CORE_SYSTEM_ADMIN_PERMISSIONS, true) && !$allowed) {
            throw new InvalidArgumentException('KhÃ´ng Ä‘Æ°á»£c táº¯t quyá»n quáº£n trá»‹ cá»‘t lÃµi');
        }
    }

    private function requireActor(string $permission): array
    {
        if (!$this->authorization) {
            throw new InvalidArgumentException('Authorization chÆ°a sáºµn sÃ ng');
        }
        return $this->authorization->authorize($permission);
    }
}
