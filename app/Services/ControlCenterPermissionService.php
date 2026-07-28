<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Repositories\ControlCenterPermissionRepository;
use InvalidArgumentException;

final class ControlCenterPermissionService
{
    private const ROLES = [
        'SYSTEM_ADMIN' => 'Quan tri he thong',
        'VILLAGE_ADMIN' => 'Quan tri thon',
        'STAFF' => 'Can bo nhap lieu',
        'VIEWER' => 'Chi xem',
    ];

    private const GROUPS = [
        'units' => [
            'name' => 'Don vi hanh chinh',
            'permissions' => [
                'control_center.units.read' => 'Xem don vi',
                'control_center.units.create' => 'Them don vi',
                'control_center.units.update' => 'Sua don vi',
                'control_center.units.lock' => 'Khoa don vi',
                'control_center.units.activate' => 'Kich hoat don vi',
            ],
        ],
        'users' => [
            'name' => 'Tai khoan he thong',
            'permissions' => [
                'control_center.users.read' => 'Xem tai khoan',
                'control_center.users.create' => 'Them tai khoan',
                'control_center.users.update' => 'Sua tai khoan',
                'control_center.users.deactivate' => 'Ngung tai khoan',
                'control_center.users.activate' => 'Kich hoat tai khoan',
                'control_center.users.reset_password' => 'Reset mat khau',
            ],
        ],
        'permissions' => [
            'name' => 'Phan quyen',
            'permissions' => [
                'control_center.permissions.read' => 'Xem phan quyen',
                'control_center.permissions.update' => 'Cap nhat phan quyen',
            ],
        ],
        'dashboard' => [
            'name' => 'Dashboard',
            'permissions' => [
                'control_center.dashboard.read' => 'Xem dashboard',
            ],
        ],
        'operations' => [
            'name' => 'Van hanh sau',
            'permissions' => [
                'control_center.monitoring.read' => 'Xem monitoring',
                'control_center.audit.read' => 'Xem audit',
                'control_center.configuration.read' => 'Xem cau hinh',
                'control_center.notification.read' => 'Xem thong bao',
                'control_center.ai.read' => 'Dung AI',
            ],
        ],
    ];

    private const CORE_SYSTEM_ADMIN_PERMISSIONS = [
        'control_center.permissions.read',
        'control_center.permissions.update',
        'control_center.users.read',
        'control_center.users.update',
        'control_center.dashboard.read',
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
            $this->audit?->write($actor, 'permission.updated', null, 'Cap nhat phan quyen', [
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
        $this->audit?->write($actor, 'permission.reset', null, 'Khoi phuc phan quyen mac dinh', [
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
            throw new InvalidArgumentException('Role khong hop le');
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
        throw new InvalidArgumentException('Permission khong hop le');
    }

    private function assertMutable(string $role, string $permission, bool $allowed): void
    {
        if ($role === 'SYSTEM_ADMIN' && in_array($permission, self::CORE_SYSTEM_ADMIN_PERMISSIONS, true) && !$allowed) {
            throw new InvalidArgumentException('Khong duoc tat quyen quan tri cot loi');
        }
    }

    private function requireActor(string $permission): array
    {
        if (!$this->authorization) {
            throw new InvalidArgumentException('Authorization chua san sang');
        }
        return $this->authorization->authorize($permission);
    }
}
