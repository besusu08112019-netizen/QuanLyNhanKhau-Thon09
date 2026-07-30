<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\RuntimePaths;
use App\Models\User;

final class AuthController extends BaseController
{
    private const LOGIN_WINDOW_SECONDS = 900;
    private const LOGIN_MAX_FAILURES = 8;

    public function setup(): void
    {
        $email = trim((string) $this->input('email', $this->input('username', '')));
        $name = trim((string) $this->input('displayName', $email));
        $password = (string) $this->input('password', '');
        if (!$email || strlen($password) < 8) $this->fail('Email va mat khau toi thieu 8 ky tu la bat buoc');
        $user = $this->users()->createFirstAdmin($email, $name, $password);
        $this->audit($user, 'user', 'create', 'Tao tai khoan quan tri dau tien', $user['id']);
        $this->ok($this->users()->publicUser($user));
    }

    public function login(): void
    {
        $login = (string) $this->input('username', $this->input('email', ''));
        $this->assertLoginAllowed($login);

        try {
            $users = new User();
            $result = $users->login($login, (string) $this->input('password', ''));
            $this->clearLoginFailures($login);
            $this->auditLogin($result['user'], 'read', 'Dang nhap he thong', $result['user']['id']);
            $this->ok($result);
        } catch (\Throwable) {
            $this->recordLoginFailure($login);
            $this->auditLogin(null, 'login_failed', 'Dang nhap that bai', null, ['login_hash' => hash('sha256', strtolower(trim($login))), 'ip' => $this->clientIp(), 'user_agent' => $this->userAgent()], 'WARN');
            $this->fail('Invalid account or password', 401);
        }
    }

    public function logout(): void
    {
        $user = $this->user();
        $this->verifyCsrfToken();
        $token = $this->request->bearerToken();
        if ($token) $this->users()->revoke($token);
        $this->destroyPhpSession();
        $this->audit($user, 'user', 'read', 'Dang xuat he thong', $user['id']);
        $this->ok(['loggedOutAt' => date('c')]);
    }

    public function keepAlive(): void
    {
        $user = $this->user();
        $this->verifyCsrfToken();
        $token = $this->request->bearerToken();
        if (!$token) {
            $this->fail('Vui long dang nhap', 401);
        }
        $this->users()->touchSession($token);
        $this->ok(['activeUntil' => date('c', time() + $this->idleTimeoutSeconds()), 'user' => $this->users()->publicUser($user)]);
    }

    public function me(): void
    {
        $this->ok($this->users()->publicUser($this->user()));
    }

    private function assertLoginAllowed(string $login): void
    {
        $bucket = $this->loginBucket();
        $row = $bucket[$this->loginKey($login)] ?? ['count' => 0, 'first' => time()];
        if ((time() - (int) ($row['first'] ?? 0)) > self::LOGIN_WINDOW_SECONDS) return;
        if ((int) ($row['count'] ?? 0) >= self::LOGIN_MAX_FAILURES) {
            $this->fail('Too many login attempts. Please try again later.', 429);
        }
    }

    private function recordLoginFailure(string $login): void
    {
        $bucket = $this->loginBucket();
        $key = $this->loginKey($login);
        $row = $bucket[$key] ?? ['count' => 0, 'first' => time()];
        if ((time() - (int) ($row['first'] ?? 0)) > self::LOGIN_WINDOW_SECONDS) {
            $row = ['count' => 0, 'first' => time()];
        }
        $row['count'] = (int) ($row['count'] ?? 0) + 1;
        $bucket[$key] = $row;
        $this->saveLoginBucket($bucket);
    }

    private function clearLoginFailures(string $login): void
    {
        $bucket = $this->loginBucket();
        unset($bucket[$this->loginKey($login)]);
        $this->saveLoginBucket($bucket);
    }

    private function loginBucket(): array
    {
        $path = $this->loginBucketPath();
        if (!is_file($path)) return [];
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) return [];
        $cutoff = time() - self::LOGIN_WINDOW_SECONDS;
        return array_filter($data, static fn($row) => (int) ($row['first'] ?? 0) >= $cutoff);
    }

    private function saveLoginBucket(array $bucket): void
    {
        $path = $this->loginBucketPath();
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($path, json_encode($bucket, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function loginBucketPath(): string
    {
        return RuntimePaths::cacheRoot() . '/login-rate-limit.json';
    }

    private function loginKey(string $login): string
    {
        return hash('sha256', strtolower(trim($login)) . '|' . $this->clientIp());
    }

    private function auditLogin(?array $user, string $action, string $message, mixed $entityId = null, array $metadata = [], string $level = 'INFO'): void
    {
        try {
            $this->audit($user, 'user', $action, $message, $entityId, $metadata, $level);
        } catch (\Throwable $e) {
            error_log('[AUTH_AUDIT_ERROR] ' . $e->getMessage());
        }
    }

    private function idleTimeoutSeconds(): int
    {
        $config = require BASE_PATH . '/config/app.php';
        return max(2, (int) $config['idle_timeout_seconds']);
    }

    private function destroyPhpSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        $params = session_get_cookie_params();
        if (session_name() !== '') {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        @session_destroy();
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
