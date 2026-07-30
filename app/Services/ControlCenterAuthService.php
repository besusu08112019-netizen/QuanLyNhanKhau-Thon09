<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

final class ControlCenterAuthService
{
    private ?PDO $db = null;

    public function login(string $login, string $password): array
    {
        $login = strtolower(trim($login));
        if ($login === '' || strlen($password) < 1 || strlen($password) > 1024) {
            throw new RuntimeException('Tài khoản hoặc mật khẩu không đúng');
        }

        $user = $this->findLoginUser($login);
        if (!$user || (string) $user['status'] !== 'ACTIVE' || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Tài khoản hoặc mật khẩu không đúng');
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $stmt = $this->db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $stmt->execute(['id' => $user['id'], 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        $stmt = $this->db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);

        $token = bin2hex(random_bytes(32));
        $config = require BASE_PATH . '/config/app.php';
        $ttl = min((int) $config['session_ttl_seconds'], max(2, (int) $config['idle_timeout_seconds']));
        $session = $this->db()->prepare(
            'INSERT INTO user_sessions (village_id, user_id, token_hash, ip_address, user_agent, expires_at)
             VALUES (:village_id, :user_id, :token_hash, :ip, :agent, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        );
        $session->execute([
            'village_id' => (int) $user['village_id'],
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'ttl' => $ttl,
        ]);

        $fresh = $this->findById((int) $user['id']) ?? $user;
        return [
            'token' => $token,
            'csrfToken' => $this->csrfToken($token),
            'expiresIn' => $ttl,
            'user' => $this->publicUser($fresh),
        ];
    }

    public function me(string $token): array
    {
        $user = $this->findByToken($token);
        if (!$user) {
            throw new RuntimeException('Vui lòng đăng nhập');
        }
        return $this->publicUser($user);
    }

    public function logout(string $token): array
    {
        if ($token !== '') {
            $stmt = $this->db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE token_hash = :hash');
            $stmt->execute(['hash' => hash('sha256', $token)]);
        }
        return ['loggedOutAt' => date('c')];
    }

    public function csrfToken(string $token): string
    {
        $config = require BASE_PATH . '/config/app.php';
        $key = (string) ($config['app_key'] ?? $config['name'] ?? 'app');
        return hash_hmac('sha256', $token, $key);
    }

    private function findLoginUser(string $login): ?array
    {
        $usernameCondition = $this->hasColumn('username') ? ' OR LOWER(username) = :login_username' : '';
        $sql = 'SELECT ' . $this->userSelectList() . '
                FROM users
                WHERE status = "ACTIVE"
                  AND (LOWER(email) = :login_email' . $usernameCondition . ')
                ORDER BY FIELD(role, "SUPER_ADMIN", "ADMIN", "OFFICER", "VIEWER"), id
                LIMIT 1';
        $params = ['login_email' => $login];
        if ($this->hasColumn('username')) {
            $params['login_username'] = $login;
        }
        return $this->fetchOne($sql, $params);
    }

    private function findByToken(string $token): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT ' . $this->userSelectList('u') . '
             FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = :hash
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()
               AND u.status = "ACTIVE"
             LIMIT 1'
        );
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT ' . $this->userSelectList() . ' FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    private function publicUser(array $user): array
    {
        $sourceRole = (string) $user['role'];
        return [
            'id' => (int) $user['id'],
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) $user['email'],
            'displayName' => (string) $user['display_name'],
            'role' => $this->platformRole($sourceRole),
            'sourceRole' => $sourceRole,
            'status' => (string) $user['status'],
            'unitId' => (int) $user['village_id'],
            'lastLoginAt' => $user['last_login_at'] ?? null,
        ];
    }

    private function platformRole(string $sourceRole): string
    {
        return [
            'SUPER_ADMIN' => 'SYSTEM_ADMIN',
            'ADMIN' => 'VILLAGE_ADMIN',
            'OFFICER' => 'STAFF',
            'VIEWER' => 'VIEWER',
        ][$sourceRole] ?? $sourceRole;
    }

    private function userSelectList(string $alias = ''): string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        return implode(',', [
            $p . 'id',
            $this->hasColumn('username') ? $p . 'username' : 'NULL AS username',
            $p . 'village_id',
            $p . 'email',
            $p . 'display_name',
            $p . 'password_hash',
            $p . 'role',
            $p . 'status',
            $p . 'last_login_at',
        ]);
    }

    private function hasColumn(string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        $stmt = $this->db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "users" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        $row = $stmt->fetch();
        return $cache[$column] = ((int) ($row['total'] ?? 0) > 0);
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function db(): PDO
    {
        return $this->db ??= Database::pdo();
    }
}
