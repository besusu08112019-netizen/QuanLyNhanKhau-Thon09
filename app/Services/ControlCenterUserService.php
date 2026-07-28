<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Repositories\ControlCenterUserRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ControlCenterUserService
{
    private const STATUSES = ['ACTIVE', 'INACTIVE'];
    private const ROLE_MAP = [
        'SYSTEM_ADMIN' => 'SUPER_ADMIN',
        'VILLAGE_ADMIN' => 'ADMIN',
        'STAFF' => 'OFFICER',
        'VIEWER' => 'VIEWER',
    ];

    public function __construct(
        private ControlCenterUserRepository $repository,
        private ControlCenterAuthorizationInterface $authorization,
        private ControlCenterAuditService $audit
    ) {
    }

    public function list(array $filters = []): array
    {
        $this->authorization->authorize('control_center.users.read');
        try {
            return $this->repository->paginate($filters);
        } catch (Throwable $e) {
            error_log('[CONTROL_CENTER_USER_LIST_FALLBACK] ' . json_encode([
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return ['items' => [], 'page' => 1, 'pageSize' => 20, 'total' => 0, 'totalPages' => 1];
        }
    }

    public function find(int $id): array
    {
        $this->authorization->authorize('control_center.users.read');
        return $this->findExisting($id);
    }

    public function create(array $input): array
    {
        $actor = $this->authorization->authorize('control_center.users.create');
        $data = $this->validate($input, true, $actor);
        $this->assertUnique($data);
        $user = $this->repository->create($data);
        $this->audit->write($actor, 'user.created', (int) ($user['unitId'] ?? 0), 'Tao tai khoan he thong', ['user_id' => $user['id'] ?? null, 'role' => $user['role'] ?? null]);
        return $user;
    }

    public function update(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('control_center.users.update');
        $current = $this->findExisting($id);
        $data = $this->validate($input, false, $actor, $current);
        $this->assertUnique($data, $id);
        $this->assertSystemAdminSafety($current, $data['role'], $data['status'], $actor);
        $user = $this->repository->update($id, $data);
        $this->audit->write($actor, 'user.updated', (int) ($user['unitId'] ?? 0), 'Cap nhat tai khoan he thong', ['user_id' => $id, 'fields' => array_keys($data)]);
        return $user;
    }

    public function deactivate(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.users.deactivate');
        $user = $this->findExisting($id);
        $this->assertSystemAdminSafety($user, (string) $user['role'], 'INACTIVE', $actor);
        if ((string) ($user['status'] ?? '') === 'INACTIVE') {
            throw new InvalidArgumentException('Tai khoan da ngung su dung');
        }
        $updated = $this->repository->setStatus($id, 'INACTIVE', (int) $actor['id']);
        $this->audit->write($actor, 'user.deactivated', (int) ($updated['unitId'] ?? 0), 'Vo hieu hoa tai khoan he thong', ['user_id' => $id]);
        return $updated;
    }

    public function activate(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.users.activate');
        $user = $this->findExisting($id);
        if ((string) ($user['status'] ?? '') === 'ACTIVE') {
            throw new InvalidArgumentException('Tai khoan da dang hoat dong');
        }
        $updated = $this->repository->setStatus($id, 'ACTIVE', (int) $actor['id']);
        $this->audit->write($actor, 'user.activated', (int) ($updated['unitId'] ?? 0), 'Kich hoat tai khoan he thong', ['user_id' => $id]);
        return $updated;
    }

    public function resetPassword(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('control_center.users.reset_password');
        $user = $this->findExisting($id);
        if ((string) $user['role'] === 'SYSTEM_ADMIN' && (int) $user['id'] !== (int) $actor['id']) {
            throw new InvalidArgumentException('Chua cho phep reset mat khau SYSTEM_ADMIN khac trong feature nay');
        }
        $password = (string) ($input['password'] ?? '');
        $this->assertPassword($password);
        $updated = $this->repository->resetPassword($id, $password, (int) $actor['id']);
        $this->audit->write($actor, 'user.password_reset', (int) ($updated['unitId'] ?? $user['unitId'] ?? 0), 'Reset mat khau tai khoan he thong', ['user_id' => $id]);
        return $updated;
    }

    private function validate(array $input, bool $creating, array $actor, ?array $current = null): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? $current['email'] ?? '')));
        $displayName = trim((string) ($input['display_name'] ?? $input['displayName'] ?? $current['displayName'] ?? ''));
        $username = strtolower(trim((string) ($input['username'] ?? $current['username'] ?? $this->usernameFromEmail($email))));
        $role = strtoupper(trim((string) ($input['role'] ?? $current['role'] ?? 'VIEWER')));
        $status = strtoupper(trim((string) ($input['status'] ?? $current['status'] ?? 'ACTIVE')));
        $unitId = (int) ($input['unit_id'] ?? $input['unitId'] ?? $current['unitId'] ?? 0);

        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
            throw new InvalidArgumentException('Username khong hop le');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email khong hop le');
        }
        if ($displayName === '' || mb_strlen($displayName, 'UTF-8') > 190) {
            throw new InvalidArgumentException('Ho ten khong hop le');
        }
        if (!isset(self::ROLE_MAP[$role])) {
            throw new InvalidArgumentException($role === 'COMMUNE_ADMIN' ? 'COMMUNE_ADMIN chua san sang trong feature nay' : 'Vai tro khong hop le');
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Trang thai tai khoan khong hop le');
        }
        if ($unitId <= 0 || !$this->repository->unitExists($unitId)) {
            throw new InvalidArgumentException('Don vi khong hop le');
        }
        if ($creating) {
            $this->assertPassword((string) ($input['password'] ?? ''));
        }

        return [
            'username' => $username,
            'email' => $email,
            'display_name' => $displayName,
            'password' => (string) ($input['password'] ?? ''),
            'role' => $role,
            'source_role' => self::ROLE_MAP[$role],
            'status' => $status,
            'unit_id' => $unitId,
            'phone' => $this->nullable($input['phone'] ?? $current['phone'] ?? null),
            'position' => $this->nullable($input['position'] ?? $current['position'] ?? null),
            'actor_id' => (int) $actor['id'],
        ];
    }

    private function assertUnique(array $data, ?int $ignoreId = null): void
    {
        if ($this->repository->existsByEmail($data['email'], (int) $data['unit_id'], $ignoreId)) {
            throw new InvalidArgumentException('Email da ton tai trong don vi');
        }
        if ($this->repository->existsByUsername($data['username'], (int) $data['unit_id'], $ignoreId)) {
            throw new InvalidArgumentException('Username da ton tai trong don vi');
        }
    }

    private function assertSystemAdminSafety(array $user, string $nextRole, string $nextStatus, array $actor): void
    {
        if ((int) $user['id'] === (int) $actor['id'] && $nextStatus === 'INACTIVE') {
            throw new InvalidArgumentException('Khong duoc vo hieu hoa tai khoan dang dang nhap');
        }
        if ((string) $user['role'] === 'SYSTEM_ADMIN' && ($nextRole !== 'SYSTEM_ADMIN' || $nextStatus !== 'ACTIVE') && $this->repository->activeSystemAdminCount((int) $user['id']) < 1) {
            throw new InvalidArgumentException('Khong duoc vo hieu hoa SYSTEM_ADMIN cuoi cung');
        }
    }

    private function findExisting(int $id): array
    {
        $user = $this->repository->find($id);
        if (!$user) {
            throw new RuntimeException('Khong tim thay tai khoan');
        }
        return $user;
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < 8 || strlen($password) > 1024) {
            throw new InvalidArgumentException('Mat khau toi thieu 8 ky tu');
        }
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function usernameFromEmail(string $email): string
    {
        return preg_replace('/[^a-z0-9._-]/', '', strtolower(strtok($email, '@') ?: 'user')) ?: 'user';
    }
}
