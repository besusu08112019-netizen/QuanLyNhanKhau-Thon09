<?php

namespace App\Models;

use App\Core\BaseModel;

final class User extends BaseModel
{
    private const ROLES = ['SUPER_ADMIN','ADMIN','OFFICER','VIEWER'];

    public function count(): int { return (int) $this->fetchOne('SELECT COUNT(*) AS total FROM users')['total']; }

    public function paginate(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $where = ['status <> "DELETED"']; $params = [];
        if (!empty($filters['role'])) { $where[] = 'role = :role'; $params['role'] = $filters['role']; }
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $where[] = '(email LIKE :q_email OR display_name LIKE :q_name OR username LIKE :q_username OR phone LIKE :q_phone OR position LIKE :q_position)';
            $params['q_email'] = $q;
            $params['q_name'] = $q;
            $params['q_username'] = $q;
            $params['q_phone'] = $q;
            $params['q_position'] = $q;
        }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM users $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT id,username,email,display_name,phone,position,role,status,last_login_at,created_at FROM users $sqlWhere ORDER BY role,email LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
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
        if ($this->count() > 0) throw new \RuntimeException('Hệ thống đã có tài khoản quản trị');
        $username = $this->usernameFromEmail($email);
        $id = $this->insert('INSERT INTO users (username, email, display_name, password_hash, role, status) VALUES (:username, :email, :display_name, :password_hash, "SUPER_ADMIN", "ACTIVE")', ['username' => $username, 'email' => strtolower(trim($email)), 'display_name' => trim($displayName), 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        return $this->findById($id);
    }

    public function create(array $data, int $actorId): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $username = strtolower(trim((string) ($data['username'] ?? $this->usernameFromEmail($email))));
        $name = trim((string) ($data['displayName'] ?? $data['display_name'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = $this->role((string) ($data['role'] ?? 'VIEWER'));
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) throw new \RuntimeException('Username không hợp lệ');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Email không hợp lệ');
        if ($name === '') throw new \RuntimeException('Họ tên là bắt buộc');
        $this->assertPasswordPolicy($password);
        if ($this->findByEmail($email)) throw new \RuntimeException('Email đã tồn tại');
        if ($this->findByUsername($username)) throw new \RuntimeException('Username đã tồn tại');
        $id = $this->insert('INSERT INTO users (username,email,display_name,phone,position,password_hash,role,status,created_by) VALUES (:username,:email,:display_name,:phone,:position,:password_hash,:role,"ACTIVE",:actor)', ['username' => $username, 'email' => $email, 'display_name' => $name, 'phone' => $this->nullable($data['phone'] ?? null), 'position' => $this->nullable($data['position'] ?? null), 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'actor' => $actorId]);
        return $this->findById($id);
    }

    public function updateUser(int $id, array $data, int $actorId): array
    {
        $user = $this->findById($id); if (!$user) throw new \RuntimeException('Không tìm thấy người dùng');
        if ($user['role'] === 'SUPER_ADMIN') throw new \RuntimeException('Không sửa tài khoản Super Admin');
        $name = trim((string) ($data['displayName'] ?? $data['display_name'] ?? $user['display_name']));
        if ($name === '') throw new \RuntimeException('Họ tên là bắt buộc');
        $role = $this->role((string) ($data['role'] ?? $user['role']));
        $this->execute('UPDATE users SET display_name=:display_name, phone=:phone, position=:position, role=:role, updated_by=:actor WHERE id=:id', ['id' => $id, 'display_name' => $name, 'phone' => $this->nullable($data['phone'] ?? $user['phone'] ?? null), 'position' => $this->nullable($data['position'] ?? $user['position'] ?? null), 'role' => $role, 'actor' => $actorId]);
        if (!empty($data['password'])) $this->changePassword($id, (string) $data['password'], $actorId);
        return $this->findById($id);
    }

    public function deleteUser(int $id, int $actorId): void
    {
        $user = $this->findById($id); if (!$user) throw new \RuntimeException('Không tìm thấy người dùng');
        if ($user['role'] === 'SUPER_ADMIN') throw new \RuntimeException('Không xóa tài khoản Super Admin');
        $this->execute('UPDATE users SET status="DELETED", deleted_at=NOW(), deleted_by=:actor WHERE id=:id', ['id' => $id, 'actor' => $actorId]);
    }

    public function lock(int $id, int $actorId): void { $this->setStatus($id, 'INACTIVE', $actorId); }
    public function unlock(int $id, int $actorId): void { $this->setStatus($id, 'ACTIVE', $actorId); }

    public function changePassword(int $id, string $password, int $actorId): void
    {
        $this->assertPasswordPolicy($password);
        $this->execute('UPDATE users SET password_hash=:hash, updated_by=:actor WHERE id=:id', ['id' => $id, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'actor' => $actorId]);
    }

    public function findById(int $id): ?array { return $this->fetchOne('SELECT * FROM users WHERE id = :id AND status <> "DELETED"', ['id' => $id]); }
    public function findByEmail(string $email): ?array { return $this->fetchOne('SELECT * FROM users WHERE email = :email AND status <> "DELETED"', ['email' => strtolower(trim($email))]); }
    public function findByUsername(string $username): ?array { return $this->fetchOne('SELECT * FROM users WHERE username = :username AND status <> "DELETED"', ['username' => strtolower(trim($username))]); }

    public function login(string $email, string $password): array
    {
        $login = strtolower(trim($email));
        $user = filter_var($login, FILTER_VALIDATE_EMAIL) ? $this->findByEmail($login) : $this->findByUsername($login);
        if (strlen($password) > 1024 || !$user || $user['status'] !== 'ACTIVE' || !password_verify($password, (string) $user['password_hash'])) throw new \RuntimeException('Invalid account or password');
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

    public function findByToken(string $token): ?array { return $this->fetchOne('SELECT u.* FROM user_sessions s INNER JOIN users u ON u.id = s.user_id WHERE s.token_hash = :hash AND s.revoked_at IS NULL AND s.expires_at > NOW() AND u.status = "ACTIVE"', ['hash' => hash('sha256', $token)]); }
    public function revoke(string $token): void { $this->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = :hash', ['hash' => hash('sha256', $token)]); }

    public function publicUser(?array $user): ?array
    {
        if (!$user) return null;
        return ['id' => (int) $user['id'], 'username' => $user['username'] ?? '', 'email' => $user['email'], 'displayName' => $user['display_name'], 'phone' => $user['phone'] ?? '', 'position' => $user['position'] ?? '', 'role' => $user['role'], 'status' => $user['status'], 'created_at' => $user['created_at'] ?? null, 'lastLoginAt' => $user['last_login_at'], 'permissions' => $this->effectivePermissions($user)];
    }

    public function can(array $user, string $module, string $action): bool
    {
        $role = (string) ($user['role'] ?? '');
        if ($role === 'SUPER_ADMIN' || $role === 'ADMIN') return true;

        if ($role === 'VIEWER') {
            return in_array($module, ['dashboard','household','household_business','agriculture','citizen','report','gis'], true) && $action === 'read';
        }


        $permission = $this->fetchOne('SELECT allowed FROM permissions WHERE role = :role AND module = :module AND action = :action', ['role' => $role, 'module' => $module, 'action' => $action]);
        if ($permission) return (bool) $permission['allowed'];
        if ($role === 'OFFICER') return (in_array($module, ['dashboard','household','household_business','agriculture','citizen','movement','report'], true) && in_array($action, ['read','create','update'], true)) || ($module === 'gis' && $action === 'read');
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
            throw new \RuntimeException('Password length is invalid');
        }
    }

    private function setStatus(int $id, string $status, int $actorId): void
    {
        $user = $this->findById($id); if (!$user) throw new \RuntimeException('Không tìm thấy người dùng');
        if ($user['role'] === 'SUPER_ADMIN') throw new \RuntimeException('Không khóa tài khoản Super Admin');
        $this->execute('UPDATE users SET status=:status, updated_by=:actor WHERE id=:id', ['id' => $id, 'status' => $status, 'actor' => $actorId]);
    }

    private function role(string $role): string { return in_array($role, self::ROLES, true) ? $role : 'VIEWER'; }
    private function nullable(mixed $value): ?string { $text = trim((string) ($value ?? '')); return $text === '' ? null : $text; }
    private function usernameFromEmail(string $email): string { return preg_replace('/[^a-z0-9._-]/', '', strtolower(strtok($email, '@') ?: 'admin')) ?: 'admin'; }
}
