<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Services\CentralSuperAdminAuthService;
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
        $where = $this->tenantWhere('users');
        return (int) $this->fetchOne("SELECT COUNT(*) AS total FROM users WHERE $where", $this->withTenant())['total'];
    }

    public function paginate(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $where = ['status <> "DELETED"', $this->tenantWhere('users')];
        $params = $this->withTenant();

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
        $this->addTenantInsert('users', $columns, $params);

        $id = $this->insert('INSERT INTO users (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->findById($id);
    }

    public function create(array $data, array|int $actor): array
    {
        $actorUser = $this->actorUser($actor);
        $actorId = (int) $actorUser['id'];
        $email = $this->normalizeEmail($data['email'] ?? '');
        $username = $this->normalizeUsername((string) ($data['username'] ?? $this->usernameFromEmail($email)));
        $name = $this->displayName($data);
        $password = (string) ($data['password'] ?? '');
        $role = $this->roleFromPayload($data, 'VIEWER');
        $status = $this->statusFromPayload($data, 'ACTIVE');
        $this->assertRoleAssignmentAllowed(null, $role, $actorUser);

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
        $this->addTenantInsert('users', $columns, $params);

        try {
            $id = $this->insert('INSERT INTO users (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        } catch (PDOException $e) {
            $this->throwUserDataException($e);
        }

        return $this->findById($id);
    }

    public function updateUser(int $id, array $data, array|int $actor): array
    {
        $actorUser = $this->actorUser($actor);
        $actorId = (int) $actorUser['id'];
        $user = $this->findById($id);
        if (!$user) {
            throw new RuntimeException('Không tìm thấy người dùng');
        }
        if ($user['role'] === 'SUPER_ADMIN' && !$this->actorIsSuperAdmin($actorUser)) {
            throw new RuntimeException('Không sửa tài khoản Super Admin');
        }

        $sets = ['display_name=:display_name', 'role=:role', 'updated_by=:actor'];
        $params = ['id' => $id, 'actor' => $actorId];

        $name = $this->displayName($data, (string) $user['display_name']);
        $this->validateDisplayName($name);
        $params['display_name'] = $name;
        $params['role'] = $this->roleFromPayload($data, (string) $user['role']);
        $this->assertRoleAssignmentAllowed((string) $user['role'], (string) $params['role'], $actorUser);

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
            $this->execute('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id=:id AND ' . $this->tenantWhere('users'), $this->withTenant($params));
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

        $this->execute('UPDATE users SET status="DELETED", deleted_at=NOW(), deleted_by=:actor WHERE id=:id AND ' . $this->tenantWhere('users'), $this->withTenant(['id' => $id, 'actor' => $actorId]));
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
        $this->execute('UPDATE users SET password_hash=:hash, updated_by=:actor WHERE id=:id AND ' . $this->tenantWhere('users'), $this->withTenant(['id' => $id, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'actor' => $actorId]));
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT ' . $this->userSelectList() . ' FROM users WHERE id = :id AND status <> "DELETED" AND ' . $this->tenantWhere('users'), $this->withTenant(['id' => $id]));
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT ' . $this->userSelectList() . ' FROM users WHERE email = :email AND status <> "DELETED" AND ' . $this->tenantWhere('users'), $this->withTenant(['email' => $this->normalizeEmail($email)]));
    }

    public function findByUsername(string $username): ?array
    {
        if (!$this->hasColumn('username')) {
            return null;
        }
        return $this->fetchOne('SELECT ' . $this->userSelectList() . ' FROM users WHERE username = :username AND status <> "DELETED" AND ' . $this->tenantWhere('users'), $this->withTenant(['username' => $this->normalizeUsername($username)]));
    }

    public function login(string $email, string $password): array
    {
        $login = strtolower(trim($email));
        $centralSuperAdmin = (new CentralSuperAdminAuthService())->authenticate($login, $password);
        if ($centralSuperAdmin !== null) {
            return $this->createCentralSuperAdminSession($centralSuperAdmin);
        }

        $user = filter_var($login, FILTER_VALIDATE_EMAIL) ? $this->findByEmail($login) : $this->findByUsername($login);
        if (strlen($password) > 1024 || !$user || $user['status'] !== 'ACTIVE' || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Invalid account or password');
        }
        if ((string) $user['role'] === 'SUPER_ADMIN') {
            throw new RuntimeException('Invalid account or password');
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->execute('UPDATE users SET password_hash=:hash WHERE id=:id AND ' . $this->tenantWhere('users'), $this->withTenant(['id' => $user['id'], 'hash' => password_hash($password, PASSWORD_DEFAULT)]));
        }
        return $this->createLoginSession($user);
    }

    private function createCentralSuperAdminSession(array $centralUser): array
    {
        $holder = $this->findCentralSuperAdminSessionHolder();
        $this->ensureCentralSessionColumns();
        $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = :id AND ' . $this->tenantWhere('users'), $this->withTenant(['id' => $holder['id']]));

        $token = bin2hex(random_bytes(32));
        $config = require BASE_PATH . '/config/app.php';
        $ttl = min((int) $config['session_ttl_seconds'], max(2, (int) $config['idle_timeout_seconds']));
        $columns = ['user_id', 'token_hash', 'ip_address', 'user_agent', 'expires_at', 'central_user_id', 'central_email', 'central_username', 'central_display_name', 'central_role'];
        $params = [
            'user_id' => (int) $holder['id'],
            'token_hash' => hash('sha256', $token),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
            'central_user_id' => (int) ($centralUser['id'] ?? 0),
            'central_email' => (string) ($centralUser['email'] ?? ''),
            'central_username' => (string) ($centralUser['username'] ?? ''),
            'central_display_name' => (string) ($centralUser['display_name'] ?? $centralUser['email'] ?? ''),
            'central_role' => 'SUPER_ADMIN',
        ];
        $this->addTenantInsert('user_sessions', $columns, $params);
        $this->insert('INSERT INTO user_sessions (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return ['token' => $token, 'csrfToken' => $this->csrfToken($token), 'expiresIn' => $ttl, 'user' => $this->publicUser($this->centralSessionUser($centralUser, $holder))];
    }

    private function findCentralSuperAdminSessionHolder(): array
    {
        $holder = $this->fetchOne('SELECT ' . $this->userSelectList() . ' FROM users WHERE status <> "DELETED" AND ' . $this->tenantWhere('users') . ' ORDER BY FIELD(status, "ACTIVE", "INACTIVE"), FIELD(role, "ADMIN", "SUPER_ADMIN", "OFFICER", "VIEWER"), id ASC LIMIT 1', $this->withTenant());
        if (!$holder) {
            throw new RuntimeException('Tenant chua co tai khoan noi bo de gan session trung tam');
        }
        return $holder;
    }

    private function centralSessionUser(array $centralUser, array $holder): array
    {
        $holder['username'] = (string) ($centralUser['username'] ?? '');
        $holder['email'] = (string) ($centralUser['email'] ?? '');
        $holder['display_name'] = (string) ($centralUser['display_name'] ?? $centralUser['email'] ?? 'Super Admin');
        $holder['role'] = 'SUPER_ADMIN';
        $holder['status'] = 'ACTIVE';
        return $holder;
    }

    private function ensureCentralSessionColumns(): void
    {
        $columns = [
            'central_user_id' => 'BIGINT UNSIGNED NULL AFTER user_id',
            'central_email' => 'VARCHAR(190) NULL AFTER central_user_id',
            'central_username' => 'VARCHAR(60) NULL AFTER central_email',
            'central_display_name' => 'VARCHAR(190) NULL AFTER central_username',
            'central_role' => 'VARCHAR(30) NULL AFTER central_display_name',
        ];
        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('user_sessions', $column)) {
                $this->execute('ALTER TABLE user_sessions ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }

    private function createLoginSession(array $user): array
    {
        $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = :id AND ' . $this->tenantWhere('users'), $this->withTenant(['id' => $user['id']]));
        $token = bin2hex(random_bytes(32));
        $config = require BASE_PATH . '/config/app.php';
        $ttl = min((int) $config['session_ttl_seconds'], max(2, (int) $config['idle_timeout_seconds']));
        $columns = ['user_id', 'token_hash', 'ip_address', 'user_agent', 'expires_at'];
        $params = ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null, 'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), 'expires_at' => date('Y-m-d H:i:s', time() + $ttl)];
        $this->addTenantInsert('user_sessions', $columns, $params);
        $this->insert('INSERT INTO user_sessions (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        $fresh = $this->findById((int) $user['id']);
        return ['token' => $token, 'csrfToken' => $this->csrfToken($token), 'expiresIn' => $ttl, 'user' => $this->publicUser($fresh)];
    }

    public function csrfToken(string $token): string
    {
        $config = require BASE_PATH . '/config/app.php';
        $key = (string) ($config['app_key'] ?? $config['name'] ?? 'app');
        return hash_hmac('sha256', $token, $key);
    }

    public function findByToken(string $token): ?array
    {
        $this->ensureCentralSessionColumns();
        $row = $this->fetchOne('SELECT ' . $this->userSelectList('u') . ', s.central_user_id, s.central_email, s.central_username, s.central_display_name, s.central_role FROM user_sessions s INNER JOIN users u ON u.id = s.user_id WHERE s.token_hash = :hash AND s.revoked_at IS NULL AND s.expires_at > NOW() AND ' . $this->tenantWhere('s', 'user_sessions') . ' LIMIT 1', $this->withTenant(['hash' => hash('sha256', $token)]));
        if (!$row) {
            return null;
        }
        if (!empty($row['central_user_id']) && (string) ($row['central_role'] ?? '') === 'SUPER_ADMIN') {
            $row['username'] = (string) ($row['central_username'] ?? '');
            $row['email'] = (string) ($row['central_email'] ?? '');
            $row['display_name'] = (string) ($row['central_display_name'] ?? $row['central_email'] ?? 'Super Admin');
            $row['role'] = 'SUPER_ADMIN';
            $row['status'] = 'ACTIVE';
            return $row;
        }
        return (string) ($row['status'] ?? '') === 'ACTIVE' ? $row : null;
    }

    public function revoke(string $token): void
    {
        $this->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = :hash', ['hash' => hash('sha256', $token)]);
    }

    public function touchSession(string $token): void
    {
        $config = require BASE_PATH . '/config/app.php';
        $ttl = min((int) $config['session_ttl_seconds'], max(2, (int) $config['idle_timeout_seconds']));
        $this->execute('UPDATE user_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL :ttl SECOND) WHERE token_hash = :hash AND revoked_at IS NULL AND expires_at > NOW()', ['ttl' => $ttl, 'hash' => hash('sha256', $token)]);
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
        if ($module === 'agricultural_land' && in_array($role, ['OFFICER', 'VIEWER'], true)) return $action === 'read';

        if ($role === 'VIEWER') {
            return (in_array($module, ['dashboard','household','household_business','agricultural_land','agriculture','livestock','defense_security','finance','work_tasks','work_calendar','documents','photo_gallery','houses','public_assets','complaints','citizen','poverty','report','statistics','gis'], true) && $action === 'read') || ($module === 'notification' && in_array($action, ['read','update'], true));
        }

        $permission = $this->fetchOne('SELECT allowed FROM permissions WHERE role = :role AND module = :module AND action = :action', ['role' => $role, 'module' => $module, 'action' => $action]);
        if ($permission) return (bool) $permission['allowed'];
        if ($role === 'OFFICER') return ($module === 'agricultural_land' && $action === 'read') || (in_array($module, ['dashboard','household','household_business','agriculture','livestock','defense_security','party_members','poverty','finance','work_tasks','work_calendar','documents','photo_gallery','houses','public_assets','complaints','citizen','movement','report'], true) && in_array($action, ['read','create','update','upload','export','restore'], true)) || ($module === 'statistics' && $action === 'read') || ($module === 'notification' && in_array($action, ['read','update'], true)) || ($module === 'gis' && $action === 'read');
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

        $this->execute('UPDATE users SET status=:status, updated_by=:actor WHERE id=:id AND ' . $this->tenantWhere('users'), $this->withTenant(['id' => $id, 'status' => $status, 'actor' => $actorId]));
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

    private function actorUser(array|int $actor): array
    {
        if (is_array($actor)) {
            if (!empty($actor['id'])) return $actor;
            throw new RuntimeException('Người thực hiện không hợp lệ');
        }

        $user = $this->findById($actor);
        if (!$user) throw new RuntimeException('Người thực hiện không hợp lệ');
        return $user;
    }

    private function actorIsSuperAdmin(array $actor): bool
    {
        return (string) ($actor['role'] ?? '') === 'SUPER_ADMIN';
    }

    private function assertRoleAssignmentAllowed(?string $currentRole, string $nextRole, array $actor): void
    {
        $protectedRoles = ['SUPER_ADMIN', 'ADMIN'];
        $touchesProtectedRole = ($currentRole !== null && in_array($currentRole, $protectedRoles, true))
            || in_array($nextRole, $protectedRoles, true);

        if ($touchesProtectedRole && !$this->actorIsSuperAdmin($actor)) {
            throw new RuntimeException('Chỉ tài khoản Super Admin mới được cấp hoặc thay đổi quyền quản trị');
        }
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
        $params = $this->withTenant(['email' => $email]);
        $where = 'email = :email AND status <> "DELETED" AND ' . $this->tenantWhere('users');
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
        $params = $this->withTenant(['username' => $username]);
        $where = 'username = :username AND status <> "DELETED" AND ' . $this->tenantWhere('users');
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
