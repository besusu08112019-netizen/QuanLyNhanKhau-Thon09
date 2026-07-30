<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Repositories\ControlCenterPermissionRepository;
use InvalidArgumentException;

final class ControlCenterPermissionService
{
    private const ROLES = [
        'SYSTEM_ADMIN' => 'Quản trị hệ thống',
        'VILLAGE_ADMIN' => 'Quản trị thôn',
        'STAFF' => 'Cán bộ nhập liệu',
        'VIEWER' => 'Chỉ xem',
    ];

    private const GROUPS = [
        'units' => [
            'name' => 'Đơn vị hành chính',
            'permissions' => [
                'control_center.units.read' => 'Xem đơn vị',
                'control_center.units.create' => 'Thêm đơn vị',
                'control_center.units.install' => 'Cài đặt tenant',
                'control_center.units.update' => 'Sửa đơn vị',
                'control_center.units.lock' => 'Khóa đơn vị',
                'control_center.units.activate' => 'Kích hoạt đơn vị',
            ],
        ],
        'tenants' => [
            'name' => 'Quản lý Tenant',
            'permissions' => [
                'tenant.view' => 'Xem Tenant',
                'tenant.create' => 'Thêm Tenant',
                'tenant.update' => 'Sửa Tenant',
                'tenant.lock' => 'Khóa Tenant',
                'tenant.unlock' => 'Mở khóa Tenant',
                'tenant.delete' => 'Xóa Tenant',
                'tenant.activity.view' => 'Xem nhật ký Tenant',
            ],
        ],
        'users' => [
            'name' => 'Tài khoản hệ thống',
            'permissions' => [
                'control_center.users.read' => 'Xem tài khoản',
                'control_center.users.create' => 'Thêm tài khoản',
                'control_center.users.update' => 'Sửa tài khoản',
                'control_center.users.deactivate' => 'Ngừng tài khoản',
                'control_center.users.activate' => 'Kích hoạt tài khoản',
                'control_center.users.reset_password' => 'Đặt lại mật khẩu',
            ],
        ],
        'permissions' => [
            'name' => 'Phân quyền',
            'permissions' => [
                'control_center.permissions.read' => 'Xem phân quyền',
                'control_center.permissions.update' => 'Cập nhật phân quyền',
            ],
        ],
        'dashboard' => [
            'name' => 'Dashboard',
            'permissions' => [
                'control_center.dashboard.read' => 'Xem dashboard',
            ],
        ],
        'operations' => [
            'name' => 'Vận hành sau',
            'permissions' => [
                'control_center.monitoring.read' => 'Xem giám sát',
                'control_center.audit.read' => 'Xem nhật ký',
                'control_center.configuration.read' => 'Xem cấu hình',
                'control_center.notification.read' => 'Xem thông báo',
                'control_center.ai.read' => 'Dùng AI',
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
            $this->audit?->write($actor, 'permission.updated', null, 'Cập nhật phân quyền', [
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
        $this->audit?->write($actor, 'permission.reset', null, 'Khôi phục phân quyền mặc định', [
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
            throw new InvalidArgumentException('Vai trò không hợp lệ');
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
        throw new InvalidArgumentException('Quyền không hợp lệ');
    }

    private function assertMutable(string $role, string $permission, bool $allowed): void
    {
        if ($role === 'SYSTEM_ADMIN' && in_array($permission, self::CORE_SYSTEM_ADMIN_PERMISSIONS, true) && !$allowed) {
            throw new InvalidArgumentException('Không được tắt quyền quản trị cốt lõi');
        }
    }

    private function requireActor(string $permission): array
    {
        if (!$this->authorization) {
            throw new InvalidArgumentException('Authorization chưa sẵn sàng');
        }
        return $this->authorization->authorize($permission);
    }
}
