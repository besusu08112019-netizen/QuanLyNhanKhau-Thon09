<?php

namespace App\Models;

use App\Core\BaseModel;
use DateTimeImmutable;

final class User extends BaseModel
{
    public function count(): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS total FROM users')['total'];
    }

    public function createFirstAdmin(string $email, string $displayName, string $password): array
    {
        if ($this->count() > 0) {
            throw new \RuntimeException('Hệ thống đã có tài khoản quản trị');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id = $this->insert('INSERT INTO users (email, display_name, password_hash, role, status) VALUES (:email, :display_name, :password_hash, "SUPER_ADMIN", "ACTIVE")', [
            'email' => strtolower(trim($email)), 'display_name' => trim($displayName), 'password_hash' => $hash,
        ]);
        return $this->findById($id);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE id = :id AND status <> "DELETED"', ['id' => $id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne('SELECT * FROM users WHERE email = :email AND status <> "DELETED"', ['email' => strtolower(trim($email))]);
    }

    public function login(string $email, string $password): array
    {
        $user = $this->findByEmail($email);
        if (!$user || $user['status'] !== 'ACTIVE' || !password_verify($password, (string) $user['password_hash'])) {
            throw new \RuntimeException('Tài khoản hoặc mật khẩu không đúng');
        }
        $this->execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);
        $token = bin2hex(random_bytes(32));
        $config = require BASE_PATH . '/config/app.php';
        $expires = (new DateTimeImmutable('now'))->modify('+' . (int) ($config['session_ttl_seconds'] ?? 21600) . ' seconds')->format('Y-m-d H:i:s');
        $this->insert('INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, expires_at) VALUES (:user_id, :token_hash, :ip, :agent, :expires_at)', [
            'user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), 'expires_at' => $expires,
        ]);
        $user = $this->findById((int) $user['id']);
        return ['token' => $token, 'expiresIn' => (int) ($config['session_ttl_seconds'] ?? 21600), 'user' => $this->publicUser($user)];
    }

    public function findByToken(string $token): ?array
    {
        return $this->fetchOne('SELECT u.* FROM user_sessions s INNER JOIN users u ON u.id = s.user_id WHERE s.token_hash = :hash AND s.revoked_at IS NULL AND s.expires_at > NOW() AND u.status = "ACTIVE"', ['hash' => hash('sha256', $token)]);
    }

    public function revoke(string $token): void
    {
        $this->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = :hash', ['hash' => hash('sha256', $token)]);
    }

    public function publicUser(?array $user): ?array
    {
        if (!$user) return null;
        return ['id' => (int) $user['id'], 'email' => $user['email'], 'displayName' => $user['display_name'], 'role' => $user['role'], 'status' => $user['status'], 'lastLoginAt' => $user['last_login_at']];
    }

    public function can(array $user, string $module, string $action): bool
    {
        if (in_array($user['role'], ['SUPER_ADMIN', 'ADMIN'], true)) return true;
        $permission = $this->fetchOne('SELECT allowed FROM permissions WHERE role = :role AND module = :module AND action = :action', ['role' => $user['role'], 'module' => $module, 'action' => $action]);
        if ($permission) return (bool) $permission['allowed'];
        if ($user['role'] === 'OFFICER') return in_array($module, ['dashboard','household','citizen','movement','report','pdf','import'], true) && in_array($action, ['read','create','update','delete','export'], true);
        if ($user['role'] === 'VIEWER') return in_array($module, ['dashboard','household','citizen','report'], true) && $action === 'read';
        return false;
    }
}
