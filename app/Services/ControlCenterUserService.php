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
        $this->audit->write($actor, 'user.created', (int) ($user['unitId'] ?? 0), 'Táº¡o tÃ i khoáº£n há»‡ thá»‘ng', ['user_id' => $user['id'] ?? null, 'role' => $user['role'] ?? null]);
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
        $this->audit->write($actor, 'user.updated', (int) ($user['unitId'] ?? 0), 'Cáº­p nháº­t tÃ i khoáº£n há»‡ thá»‘ng', ['user_id' => $id, 'fields' => array_keys($data)]);
        return $user;
    }

    public function deactivate(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.users.deactivate');
        $user = $this->findExisting($id);
        $this->assertSystemAdminSafety($user, (string) $user['role'], 'INACTIVE', $actor);
        if ((string) ($user['status'] ?? '') === 'INACTIVE') {
            throw new InvalidArgumentException('TÃ i khoáº£n Ä‘Ã£ ngá»«ng sá»­ dá»¥ng');
        }
        $updated = $this->repository->setStatus($id, 'INACTIVE', (int) $actor['id']);
        $this->audit->write($actor, 'user.deactivated', (int) ($updated['unitId'] ?? 0), 'VÃ´ hiá»‡u hÃ³a tÃ i khoáº£n há»‡ thá»‘ng', ['user_id' => $id]);
        return $updated;
    }

    public function activate(int $id): array
    {
        $actor = $this->authorization->authorize('control_center.users.activate');
        $user = $this->findExisting($id);
        if ((string) ($user['status'] ?? '') === 'ACTIVE') {
            throw new InvalidArgumentException('TÃ i khoáº£n Ä‘Ã£ Ä‘ang hoáº¡t Ä‘á»™ng');
        }
        $updated = $this->repository->setStatus($id, 'ACTIVE', (int) $actor['id']);
        $this->audit->write($actor, 'user.activated', (int) ($updated['unitId'] ?? 0), 'KÃ­ch hoáº¡t tÃ i khoáº£n há»‡ thá»‘ng', ['user_id' => $id]);
        return $updated;
    }

    public function resetPassword(int $id, array $input): array
    {
        $actor = $this->authorization->authorize('control_center.users.reset_password');
        $user = $this->findExisting($id);
        if ((string) $user['role'] === 'SYSTEM_ADMIN' && (int) $user['id'] !== (int) $actor['id']) {
            throw new InvalidArgumentException('ChÆ°a cho phÃ©p Ä‘áº·t láº¡i máº­t kháº©u SYSTEM_ADMIN khÃ¡c trong tÃ­nh nÄƒng nÃ y');
        }
        $password = (string) ($input['password'] ?? '');
        $this->assertPassword($password);
        $updated = $this->repository->resetPassword($id, $password, (int) $actor['id']);
        $this->audit->write($actor, 'user.password_reset', (int) ($updated['unitId'] ?? $user['unitId'] ?? 0), 'Äáº·t láº¡i máº­t kháº©u tÃ i khoáº£n há»‡ thá»‘ng', ['user_id' => $id]);
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
            throw new InvalidArgumentException('TÃªn Ä‘Äƒng nháº­p khÃ´ng há»£p lá»‡');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email khÃ´ng há»£p lá»‡');
        }
        if ($displayName === '' || mb_strlen($displayName, 'UTF-8') > 190) {
            throw new InvalidArgumentException('Há» tÃªn khÃ´ng há»£p lá»‡');
        }
        if (!isset(self::ROLE_MAP[$role])) {
            throw new InvalidArgumentException($role === 'COMMUNE_ADMIN' ? 'COMMUNE_ADMIN chÆ°a sáºµn sÃ ng trong tÃ­nh nÄƒng nÃ y' : 'Vai trÃ² khÃ´ng há»£p lá»‡');
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Tráº¡ng thÃ¡i tÃ i khoáº£n khÃ´ng há»£p lá»‡');
        }
        if ($unitId <= 0 || !$this->repository->unitExists($unitId)) {
            throw new InvalidArgumentException('ÄÆ¡n vá»‹ khÃ´ng há»£p lá»‡');
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
            throw new InvalidArgumentException('Email Ä‘Ã£ tá»“n táº¡i trong Ä‘Æ¡n vá»‹');
        }
        if ($this->repository->existsByUsername($data['username'], (int) $data['unit_id'], $ignoreId)) {
            throw new InvalidArgumentException('TÃªn Ä‘Äƒng nháº­p Ä‘Ã£ tá»“n táº¡i trong Ä‘Æ¡n vá»‹');
        }
    }

    private function assertSystemAdminSafety(array $user, string $nextRole, string $nextStatus, array $actor): void
    {
        if ((int) $user['id'] === (int) $actor['id'] && $nextStatus === 'INACTIVE') {
            throw new InvalidArgumentException('KhÃ´ng Ä‘Æ°á»£c vÃ´ hiá»‡u hÃ³a tÃ i khoáº£n Ä‘ang Ä‘Äƒng nháº­p');
        }
        if ((string) $user['role'] === 'SYSTEM_ADMIN' && ($nextRole !== 'SYSTEM_ADMIN' || $nextStatus !== 'ACTIVE') && $this->repository->activeSystemAdminCount((int) $user['id']) < 1) {
            throw new InvalidArgumentException('KhÃ´ng Ä‘Æ°á»£c vÃ´ hiá»‡u hÃ³a SYSTEM_ADMIN cuá»‘i cÃ¹ng');
        }
    }

    private function findExisting(int $id): array
    {
        $user = $this->repository->find($id);
        if (!$user) {
            throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y tÃ i khoáº£n');
        }
        return $user;
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < 8 || strlen($password) > 1024) {
            throw new InvalidArgumentException('Máº­t kháº©u tá»‘i thiá»ƒu 8 kÃ½ tá»±');
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
