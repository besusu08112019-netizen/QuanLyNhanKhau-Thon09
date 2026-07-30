<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Core\Database;
use App\Core\Request;

final class ControlCenterSuperAdminAuthorization implements ControlCenterAuthorizationInterface
{
    private const SOURCE_ROLE = 'SUPER_ADMIN';
    private const PLATFORM_ROLE = 'SYSTEM_ADMIN';

    public function __construct(private Request $request)
    {
    }

    public function authorize(string $permission): array
    {
        $token = $this->request->bearerToken();
        if (!$token) {
            throw new ControlCenterAuthorizationException('Vui lòng đăng nhập', 401);
        }

        $this->verifyCsrfToken($token);

        $user = $this->findUserByToken($token);
        if (!$user || (string) ($user['role'] ?? '') !== self::SOURCE_ROLE) {
            throw new ControlCenterAuthorizationException('Không có quyền thực hiện thao tác', 403);
        }

        return [
            'id' => (int) $user['id'],
            'email' => (string) ($user['email'] ?? ''),
            'displayName' => (string) ($user['display_name'] ?? ''),
            'sourceRole' => self::SOURCE_ROLE,
            'platformRole' => self::PLATFORM_ROLE,
            'permission' => $permission,
        ];
    }

    private function verifyCsrfToken(string $token): void
    {
        if (in_array($this->request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $submitted = (string) $this->request->header('x-csrf-token', '');
        $expected = $this->csrfToken($token);
        if ($submitted === '' || !hash_equals($expected, $submitted)) {
            throw new ControlCenterAuthorizationException('CSRF token không hợp lệ', 419);
        }
    }

    private function csrfToken(string $token): string
    {
        $config = require BASE_PATH . '/config/app.php';
        $key = (string) ($config['app_key'] ?? $config['name'] ?? 'app');
        return hash_hmac('sha256', $token, $key);
    }

    private function findUserByToken(string $token): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.email, u.display_name, u.role, u.status
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
}
