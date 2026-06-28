<?php

namespace App\Models;

use App\Core\BaseModel;
use DateTimeImmutable;

final class User extends BaseModel
{
    private const ROLES = ['ADMIN','OFFICER','COLLABORATOR','VIEWER','DATA_ENTRY','NO_DELETE','NO_EXPORT'];

    public function count(): int { return (int) $this->fetchOne('SELECT COUNT(*) AS total FROM users')['total']; }

    public function page(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $where = ['status <> "DELETED"']; $params = [];
        if (!empty($filters['role'])) { $where[] = 'role = :role'; $params['role'] = $filters['role']; }
        if (!empty($filters['search'])) { $where[] = '(email LIKE :q OR display_name LIKE :q)'; $params['q'] = '%' . $filters['search'] . '%'; }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM users $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT id,email,display_name,role,status,last_login_at,created_at FROM users $sqlWhere ORDER BY role,email LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function roles(): array
    {
        return [
            ['value' => 'ADMIN', 'label' => 'Quản trị'],
            ['value' => 'OFFICER', 'label' => 'Cán bộ'],
            ['value' => 'COLLABORATOR', 'label' => 'Cộng tác viên'],
            ['value' => 'VIEWER', 'label' => 'Chỉ xem'],
            ['value' => 'DATA_ENTRY', 'label' => 'Chỉ nhập liệu'],
            ['value' => 'NO_DELETE', 'label' => 'Không được xóa'],
            ['value' => 'NO_EXPORT', 'label' => 'Không được xuất dữ liệu'],
        ];
    }

    public function createFirstAdmin(string $email, string $displayName, string $password): array
    {
        if ($this->count() > 0) throw new \RuntimeException('Hệ thống đã có tài khoản quản trị');
        $id = $this->insert('INSERT INTO users (email, display_name, password_hash, role, status) VALUES (:email, :display_name, :password_hash, "SUPER_ADMIN", "ACTIVE")', ['email' => strtolower(trim($email)), 'display_name' => trim($displayName), 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        return $this->findById($id);
    }

    public function create(array $data, int $actorId): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['displayName'] ?? $data['display_name'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = $this->role((string) ($data['role'] ?? 'VIEWER'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Email không hợp lệ');
        if ($name === '') throw new \RuntimeException('Tên người dùng là bắt buộc');
        if (strlen($password) < 8) throw new \RuntimeException('Mật khẩu tối thiểu 8 ký tự');
        if ($this->findByEmail($email)) throw new \RuntimeException('Email đã tồn tại');
        $id = $this->insert('INSERT INTO users (email, display_name, password_hash, role, status, created_by) VALUES (:email,:display_name,:password_hash,:role,"ACTIVE",:actor)', ['email' => $email, 'display_name' => $name, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'actor' => $actorId]);
        return $this->findById($id);
    }

    public function updateUser(int $id, array $data, int $actorId): array
    {
        $user = $this->findById($id); if (!$user) throw new \RuntimeException('Không tìm thấy người dùng');
        if ($user['role'] === 'SUPER_ADMIN') throw new \RuntimeException('Không sửa tài khoản quản trị tối cao');
        $name = trim((string) ($data['displayName'] ?? $data['display_name'] ?? $user['display_name']));
        $role = $this->role((string) ($data['role'] ?? $user['role']));
        $this->execute('UPDATE users SET display_name=:display_name, role=:role, updated_by=:actor WHERE id=:id', ['id' => $id, 'display_name' => $name, 'role' => $role, 'actor' => $actorId]);
        if (!empty($data['password'])) $this->changePassword($id, (string) $data['password'], $actorId);
        return $this->findById($id);
    }

    public function deleteUser(int $id, int $actorId): void
    {
        $user = $this->findById($id); if (!$user) throw new \RuntimeException('Không tìm thấy người dùng');
        if ($user['role'] === 'SUPER_ADMIN') throw new \RuntimeException('Không xóa tài khoản quản trị tối cao');
        $this->execute('UPDATE users SET status="DELETED", deleted_at=NOW(), deleted_by=:actor WHERE id=:id', ['id' => $id, 'actor' => $actorId]);
    }

    public function lock(int $id, int $actorId): void { $this->setStatus($id, 'INACTIVE', $actorId); }
    public function unlock(int $id, int $actorId): void { $this->setStatus($id, 'ACTIVE', $actorId); }

    public function changePassword(int $id, string $password, int $actorId): void
    {
        if (strlen($password) < 8) throw new \RuntimeException('Mật khẩu tối thiểu 8 ký tự');
        $this->execute('UPDATE users SET password_hash=:hash, updated_by=:actor WHERE id=:id', ['id' => $id, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'actor' => $actorId]);
    }

    public function findById(int $id): ?array { return $this->fetchOne('SELECT * FROM users WHERE id = :id AND status <> "DELETED"', ['id' => $id]); }
    public function findByEmail(string $email): ?array { return $this->fetchOne('SELECT * FROM users WHERE email = :email AND status <> "DELETED"', ['email' => strtolower(trim($email))]); }

    public function login(string $email, string $password): array
    {
        $user = $this->findByEmail($email);
        if (!$user || $user['status'] !== 'ACTIVE' || !password_verify($password, (string) $user['password_hash'])) throw new \RuntimeException('Tài khoản hoặc mật khẩu không đúng');
        $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);
        $token = bin2hex(random_bytes(32));
        $config = require BASE_PATH . '/config/app.php';
        $expires = (new DateTimeImmutable('now'))->modify('+' . (int) ($config['session_ttl_seconds'] ?? 21600) . ' seconds')->format('Y-m-d H:i:s');
        $this->insert('INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, expires_at) VALUES (:user_id, :token_hash, :ip, :agent, :expires_at)', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), 'expires_at' => $expires]);
        $user = $this->findById((int) $user['id']);
        return ['token' => $token, 'expiresIn' => (int) ($config['session_ttl_seconds'] ?? 21600), 'user' => $this->publicUser($user)];
    }

    public function findByToken(string $token): ?array { return $this->fetchOne('SELECT u.* FROM user_sessions s INNER JOIN users u ON u.id = s.user_id WHERE s.token_hash = :hash AND s.revoked_at IS NULL AND s.expires_at > NOW() AND u.status = "ACTIVE"', ['hash' => hash('sha256', $token)]); }
    public function revoke(string $token): void { $this->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = :hash', ['hash' => hash('sha256', $token)]); }

    public function publicUser(?array $user): ?array
    {
        if (!$user) return null;
        return ['id' => (int) $user['id'], 'email' => $user['email'], 'displayName' => $user['display_name'], 'role' => $user['role'], 'status' => $user['status'], 'lastLoginAt' => $user['last_login_at']];
    }

    public function can(array $user, string $module, string $action): bool
    {
        if (in_array($user['role'], ['SUPER_ADMIN', 'ADMIN'], true)) return true;
        if ($user['role'] === 'NO_DELETE' && $action === 'delete') return false;
        if ($user['role'] === 'NO_EXPORT' && in_array($action, ['export','print'], true)) return false;
        $permission = $this->fetchOne('SELECT allowed FROM permissions WHERE role = :role AND module = :module AND action = :action', ['role' => $user['role'], 'module' => $module, 'action' => $action]);
        if ($permission) return (bool) $permission['allowed'];
        if ($user['role'] === 'OFFICER') return in_array($module, ['dashboard','household','citizen','movement','report','pdf','import'], true) && in_array($action, ['read','create','update','delete','export','print'], true);
        if ($user['role'] === 'COLLABORATOR') return in_array($module, ['dashboard','household','citizen','movement','import'], true) && in_array($action, ['read','create','update'], true);
        if ($user['role'] === 'DATA_ENTRY') return in_array($module, ['dashboard','household','citizen','movement','import'], true) && in_array($action, ['read','create','update'], true);
        if ($user['role'] === 'VIEWER') return in_array($module, ['dashboard','household','citizen','report'], true) && $action === 'read';
        return false;
    }

    private function setStatus(int $id, string $status, int $actorId): void
    {
        $user = $this->findById($id); if (!$user) throw new \RuntimeException('Không tìm thấy người dùng');
        if ($user['role'] === 'SUPER_ADMIN') throw new \RuntimeException('Không khóa tài khoản quản trị tối cao');
        $this->execute('UPDATE users SET status=:status, updated_by=:actor WHERE id=:id', ['id' => $id, 'status' => $status, 'actor' => $actorId]);
    }

    private function role(string $role): string
    {
        return in_array($role, self::ROLES, true) ? $role : 'VIEWER';
    }
}
