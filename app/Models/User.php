<?php

namespace App\Models;

use App\Core\BaseModel;
use PDOException;
use RuntimeException;

final class User extends BaseModel
{
    private const ROLES = ['SUPER_ADMIN', 'ADMIN', 'OFFICER', 'VIEWER'];
    private const ROLE_ALIASES = [
        '1' => 'SUPER_ADMIN',
        '2' => 'ADMIN',
        '3' => 'OFFICER',
        '4' => 'VIEWER',
        'SUPER_ADMIN' => 'SUPER_ADMIN',
        'ADMIN' => 'ADMIN',
        'OFFICER' => 'OFFICER',
        'VIEWER' => 'VIEWER',
    ];
    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    public function count(): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS total FROM users')['total'];
    }

    public function paginate(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $where = ['status <> "DELETED"'];
        $params = [];

        if (!empty($filters['role'])) {
            $where[] = 'role = :role';
            $params['role'] = $this->role((string) $filters['role']);
        }

        if (!empty($filters['search'])) {
            $q = '%' . trim((string) $filters['search']) . '%';
            $parts = ['email LIKE :q_email', 'display_name LIKE :q_name'];
            $params['q_email'] = $q;
            $params['q_name'] = $q;
            if ($this->hasColumn('username')) {
                $parts[] = 'username LIKE :q_username';
                $params['q_username'] = $q;
            }
            if ($this->hasColumn('phone')) {
                $parts[] = 'phone LIKE :q_phone';
                $params['q_phone'] = $q;
            }
            if ($this->hasColumn('position')) {
                $parts[] = 'position LIKE :q_position';
                $params['q_position'] = $q;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM users $sqlWhere", $params)['total'];
        $items = $this->fetchAll('SELECT ' . $this->userSelectList() . " FROM users $sqlWhere ORDER BY role,email LIMIT $pageSize OFFSET $offset", $params);

        return $this->paginated($items, $page, $pageSize, $total);
    }

    public function roles(): array
    {
        return [
            ['value' => 'SUPER_ADMIN', 'label' => 'Super Admin'],
            ['value' => 'ADMIN', 'label' => 'Admin'],
            ['value' => 'OFFICER', 'label' => 'Cán bộ'],
            ['value' => 'VIEWER', 'label' => 'Khách'],
        ];
    }

    public function createFirstAdmin(string $email, string $displayName, string $password): array
    {
        if ($this->count() > 0) {
            throw new RuntimeException('Hệ thống đã có tài khoản quản trị');
        }

        $email = $this->normalizeEmail($email);
        $this->validateEmail($email);
        $this->assertPasswordPolicy($password);

        $columns = ['email', 'display_name', 'password_hash', 'role', 'status'];
        $params = [
            'email' => $email,
            'display_name' => trim($displayName),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ];

        if ($this->hasColumn('username')) {
            array_unshift($columns, 'username');
            $params['username'] = $this->usernameFromEmail($email);
        }

        $id = $this->insert('INSERT INTO users (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->findById($id);
    }

    public function create(array $data, int $actorId): array
    {
        $email = $this->normalizeEmail($data['email'] ?? '');
        $username = $this->normalizeUsername((string) ($data['username'] ?? $this->usernameFromEmail($email)));
        $name = $this->displayName($data);
        $password = (string) ($data['password'] ?? '');
        $role = $this->roleFromPayload($data, 'VIEWER');
        $status = $this->statusFromPayload($data, 'ACTIVE');

        $this->validateUsername($username);
        $this->validateEmail($email);
        $this->validateDisplayName($name);
        $this->assertPasswordPolicy($password);
        $this->assertUniqueEmail($email);
        $this->assertUniqueUsername($username);

        $columns = ['email', 'display_name', 'password_hash', 'role', 'status', 'created_by'];
        $params = [
            'email' => $email,
            'display_name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => $status,
            'created_by' => $actorId,
        ];

        if ($this->hasColumn('username')) {
            $columns[] = 'username';
            $params['username'] = $username;
        }
        if ($this->hasColumn('phone')) {
            $columns[] = 'phone';
            $params['phone'] = $this->nullable($data['phone'] ?? null);
        }
        if ($this->hasColumn('position')) {
            $columns[] = 'position';
            $params['position'] = $this->nullable($data['position'] ?? null);
        }

        try {
            $id = $this->insert('INSERT INTO users (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        } catch (PDOException $e) {
            $this->throwUserDataException($e);
        }

        return $this->findById($id);
    }

    public function updateUser(int $id, array $data, int $actorId): array
    {
        $user = $this->findById($id);
        if (!$user) {
            throw new RuntimeException('Không tìm thấy người dùng');
        }
        if ($user['role'] === 'SUPER_ADMIN') {
            throw new RuntimeException('Không sửa tài khoản Super Admin');
        }

        $sets = ['display_name=:display_name', 'role=:role', 'updated_by=:actor'];
        $params = ['id' => $id, 'actor' => $actorId];

        $name = $this->displayName($data, (string) $user['display_name']);
        $this->validateDisplayName($name);
        $params['display_name'] = $name;
        $params['role'] = $this->roleFromPayload($data, (string) $user['role']);

        if (array_key_exists('status', $data)) {
            $sets[] = 'status=:status';
            $params['status'] = $this->statusFromPayload($data, (string) $user['status']);
        }

        if (array_key_exists('email', $data) && trim((string) $data['email']) !== (string) $user['email']) {
            $email = $this->normalizeEmail($data['email']);
            $this->validateEmail($email);
            $this->assertUniqueEmail($email, $id);
            $sets[] = 'email=:email';
            $params['email'] = $email;
        }

        if ($this->hasColumn('username') && array_key_exists('username', $data) && trim((string) $data['username']) !== (string) ($user['username'] ?? '')) {
            $username = $this->normalizeUsername((string) $data['username']);
            $this->validateUsername($username);
            $this->assertUniqueUsername($username, $id);
            $sets[] = 'username=:username';
            $params['username'] = $username;
        }

        if ($this->hasColumn('phone')) {
            $sets[] = 'phone=:phone';
            $params['phone'] = $this->nullable($data['phone'] ?? $user['phone'] ?? null);
        }

        if ($this->hasColumn('position')) {
            $sets[] = 'position=:position';
            $params['position'] = $this->nullable($data['position'] ?? $user['position'] ?? null);
        }

        if (array_key_exists('password', $data) && trim((string) $data['password']) !== '') {
            $this->assertPasswordPolicy((string) $data['password']);
            $sets[] = 'password_hash=:password_hash';
            $params['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }

        try {
            $this->execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id=:id', $params);
        } catch (PDOException $e) {
            $this->throwUserDataException($e);
        }

        return $this->findById($id);
    }

    public function deleteUser(int $id, int $actorId): void
    {
        $user = $this->findById($id);
        if (!$user) {
            throw new RuntimeException('Không tìm thấy người dùng');
        }
        if ($user['role'] === 'SUPER_ADMIN') {
            throw new RuntimeException('Không xóa tài khoản Super Admin');
        }

        $this->execute('UPDATE users SET status="DELETED", deleted_at=NOW(), deleted_by=:actor WHERE id=:id', ['id' => $id, 'actor' => $actorId]);
    }

    public function lock(int $id, int $actorId): void
    {
        $this->setStatus($id, 'INACTIVE', $actorId);
    }

    public function unlock(int $id, int $actorId): void
    {
        $this->setStatus($id, 'ACTIVE', $actorId);
    }

    public function changePassword(int $id, string $password, int $actorId): void
    {
        $user = $this->findById($id);
        if (!$user) {
            throw new RuntimeException('Không tìm thấy người dùng');
        }
        if ($user['role'] === 'SUPER_ADMIN') {
            throw new RuntimeException('Không đổi mật khẩu tài khoản Super Admin');
        }

        $this->assertPasswordPolicy($password);
        $this->execute('UPDATE users SET password_hash=:hash, updated_by=:actor WHERE id=:id', ['id' => $id, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'actor' => $actorId]);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT ' . $this->userSelectList() . ' FROM users WHERE id = :id AND status <> "DELETED"', ['id' => $id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT ' . $this->userSelectList() . ' FROM users WHERE email = :email AND status <> "DELETED"', ['email' => $this->normalizeEmail($email)]);
    }

    public function findByUsername(string $username): ?array
    {
        if (!$this->hasColumn('username')) {
            return null;
        }
        return $this->fetchOne('SELECT ' . $this->userSelectList() . ' FROM users WHERE username = :username AND status <> "DELETED"', ['username' => $this->normalizeUsername($username)]);
    }

    public function login(string $email, string $password): array
    {
        $login = strtolower(trim($email));
        $user = filter_var($login, FILTER_VALIDATE_EMAIL) ? $this->findByEmail($login) : $this->findByUsername($login);
        if (strlen($password) > 1024 || !$user || $user['status'] !== 'ACTIVE' || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Invalid account or password');
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->execute('UPDATE users SET password_hash=:hash WHERE id=:id', ['id' => $user['id'], 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);
        $token = bin2hex(random_bytes(32));
        $config = require BASE_PATH . '/config/app.php';
        $ttl = (int) ($config['session_ttl_seconds'] ?? 21600);
        $this->insert('INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, expires_at) VALUES (:user_id, :token_hash, :ip, :agent, DATE_ADD(NOW(), INTERVAL :ttl SECOND))', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), 'ttl' => $ttl]);
        $user = $this->findById((int) $user['id']);
        return ['token' => $token, 'csrfToken' => $this->csrfToken($token), 'expiresIn' => $ttl, 'user' => $this->publicUser($user)];
    }

    public function csrfToken(string $token): string
    {
        $config = require BASE_PATH . '/config/app.php';
        $key = (string) ($config['app_key'] ?? $config['name'] ?? 'thon09');
        return hash_hmac('sha256', $token, $key);
    }

    public function findByToken(string $token): ?array
    {
        return $this->fetchOne('SELECT ' . $this->userSelectList('u') . ' FROM user_sessions s INNER JOIN users u ON u.id = s.user_id WHERE s.token_hash = :hash AND s.revoked_at IS NULL AND s.expires_at > NOW() AND u.status = "ACTIVE"', ['hash' => hash('sha256', $token)]);
    }

    public function revoke(string $token): void
    {
        $this->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = :hash', ['hash' => hash('sha256', $token)]);
    }

    public function publicUser(?array $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'username' => $user['username'] ?? '',
            'email' => $user['email'],
            'displayName' => $user['display_name'],
            'display_name' => $user['display_name'],
            'phone' => $user['phone'] ?? '',
            'position' => $user['position'] ?? '',
            'role' => $user['role'],
            'role_id' => $this->roleId((string) $user['role']),
            'status' => $user['status'],
            'created_at' => $user['created_at'] ?? null,
            'lastLoginAt' => $user['last_login_at'],
            'last_login_at' => $user['last_login_at'],
            'permissions' => $this->effectivePermissions($user),
        ];
    }

    public function can(array $user, string $module, string $action): bool
    {
        $role = (string) ($user['role'] ?? '');
        if ($role === 'SUPER_ADMIN' || $role === 'ADMIN') return true;

        if ($role === 'VIEWER') {
            return in_array($module, ['dashboard','household','household_business','agriculture','livestock','houses','citizen','report','gis'], true) && $action === 'read';
        }

        $permission = $this->fetchOne('SELECT allowed FROM permissions WHERE role = :role AND module = :module AND action = :action', ['role' => $role, 'module' => $module, 'action' => $action]);
        if ($permission) return (bool) $permission['allowed'];
        if ($role === 'OFFICER') return (in_array($module, ['dashboard','household','household_business','agriculture','livestock','houses','citizen','movement','report'], true) && in_array($action, ['read','create','update'], true)) || ($module === 'gis' && $action === 'read');
        return false;
    }

    private function effectivePermissions(array $user): array
    {
        try {
            $role = (string) ($user['role'] ?? '');
            $matrix = (new Permission())->matrix();
            foreach (($matrix['roles'] ?? []) as $row) {
                if (($row['role'] ?? '') === $role) return (array) ($row['permissions'] ?? []);
            }
        } catch (\Throwable $e) {
            error_log('[RBAC_PUBLIC_PERMISSIONS_ERROR] ' . $e->getMessage());
        }
        return [];
    }

    private function assertPasswordPolicy(string $password): void
    {
        $length = strlen($password);
        if ($length < 8 || $length > 1024) {
            throw new RuntimeException('Mật khẩu tối thiểu 8 ký tự');
        }
    }

    private function setStatus(int $id, string $status, int $actorId): void
    {
        $user = $this->findById($id);
        if (!$user) {
            throw new RuntimeException('Không tìm thấy người dùng');
        }
        if ($user['role'] === 'SUPER_ADMIN') {
            throw new RuntimeException('Không khóa tài khoản Super Admin');
        }

        $this->execute('UPDATE users SET status=:status, updated_by=:actor WHERE id=:id', ['id' => $id, 'status' => $status, 'actor' => $actorId]);
    }

    private function role(string $role): string
    {
        $key = strtoupper(trim($role));
        if (!isset(self::ROLE_ALIASES[$key])) {
            throw new RuntimeException('Vai trò không hợp lệ');
        }
        return self::ROLE_ALIASES[$key];
    }

    private function roleFromPayload(array $data, string $default): string
    {
        return $this->role((string) ($data['role'] ?? $data['role_id'] ?? $default));
    }

    private function roleId(string $role): int
    {
        return array_search($role, ['SUPER_ADMIN', 'ADMIN', 'OFFICER', 'VIEWER'], true) + 1;
    }

    private function statusFromPayload(array $data, string $default): string
    {
        $status = strtoupper(trim((string) ($data['status'] ?? $default)));
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Trạng thái không hợp lệ');
        }
        return $status;
    }

    private function userSelectList(string $alias = ''): string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        return implode(',', [
            $p . 'id',
            $this->hasColumn('username') ? $p . 'username' : 'NULL AS username',
            $p . 'email',
            $p . 'display_name',
            $this->hasColumn('phone') ? $p . 'phone' : 'NULL AS phone',
            $this->hasColumn('position') ? $p . 'position' : 'NULL AS position',
            $p . 'password_hash',
            $p . 'role',
            $p . 'status',
            $p . 'last_login_at',
            $p . 'created_at',
            $p . 'created_by',
            $p . 'updated_at',
            $p . 'updated_by',
            $p . 'deleted_at',
            $p . 'deleted_by',
        ]);
    }

    private function hasColumn(string $column): bool
    {
        return $this->columnExists('users', $column);
    }

    private function normalizeEmail(mixed $email): string
    {
        return strtolower(trim((string) $email));
    }

    private function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    private function displayName(array $data, string $default = ''): string
    {
        return trim((string) ($data['displayName'] ?? $data['display_name'] ?? $default));
    }

    private function validateUsername(string $username): void
    {
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
            throw new RuntimeException('Tên đăng nhập không hợp lệ');
        }
    }

    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email không hợp lệ');
        }
    }

    private function validateDisplayName(string $name): void
    {
        if ($name === '') {
            throw new RuntimeException('Họ tên là bắt buộc');
        }
    }

    private function assertUniqueEmail(string $email, ?int $ignoreId = null): void
    {
        $params = ['email' => $email];
        $where = 'email = :email AND status <> "DELETED"';
        if ($ignoreId !== null) {
            $where .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        if ($this->fetchOne('SELECT id FROM users WHERE ' . $where . ' LIMIT 1', $params)) {
            throw new RuntimeException('Email đã tồn tại');
        }
    }

    private function assertUniqueUsername(string $username, ?int $ignoreId = null): void
    {
        if (!$this->hasColumn('username')) {
            return;
        }
        $params = ['username' => $username];
        $where = 'username = :username AND status <> "DELETED"';
        if ($ignoreId !== null) {
            $where .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        if ($this->fetchOne('SELECT id FROM users WHERE ' . $where . ' LIMIT 1', $params)) {
            throw new RuntimeException('Tên đăng nhập đã tồn tại');
        }
    }

    private function throwUserDataException(PDOException $e): never
    {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $driverMessage = strtolower((string) ($e->errorInfo[2] ?? $e->getMessage()));

        if ($driverCode === 1062 || str_contains($driverMessage, 'duplicate')) {
            if (str_contains($driverMessage, 'username')) {
                throw new RuntimeException('Tên đăng nhập đã tồn tại', 0, $e);
            }
            if (str_contains($driverMessage, 'email')) {
                throw new RuntimeException('Email đã tồn tại', 0, $e);
            }
            throw new RuntimeException('Dữ liệu đã tồn tại', 0, $e);
        }

        throw $e;
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function usernameFromEmail(string $email): string
    {
        return preg_replace('/[^a-z0-9._-]/', '', strtolower(strtok($email, '@') ?: 'admin')) ?: 'admin';
    }
}
