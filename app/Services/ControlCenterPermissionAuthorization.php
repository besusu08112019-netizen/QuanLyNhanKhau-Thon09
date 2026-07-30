<?php

namespace App\Services;

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Authorization\ControlCenterAuthorizationInterface;
use App\Core\Request;
use App\Repositories\ControlCenterPermissionRepository;

final class ControlCenterPermissionAuthorization implements ControlCenterAuthorizationInterface
{
    public function __construct(
        private Request $request,
        private ?ControlCenterPermissionRepository $repository = null
    ) {
        $this->repository ??= new ControlCenterPermissionRepository();
    }

    public function authorize(string $permission): array
    {
        $token = $this->request->bearerToken();
        if (!$token) {
            throw new ControlCenterAuthorizationException('Vui lòng đăng nhập', 401);
        }

        $this->verifyCsrfToken($token);

        $user = $this->repository->findUserByToken($token);
        if (!$user) {
            throw new ControlCenterAuthorizationException('Vui lòng đăng nhập', 401);
        }

        $platformRole = $this->repository->platformRole((string) $user['role']);
        if ($platformRole !== 'SYSTEM_ADMIN') {
            $permissionService = new ControlCenterPermissionService($this->repository);
            if (!$permissionService->isAllowed($platformRole, $permission)) {
                throw new ControlCenterAuthorizationException('Không có quyền thực hiện thao tác', 403);
            }
        }

        return [
            'id' => (int) $user['id'],
            'village_id' => (int) ($user['village_id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'displayName' => (string) ($user['display_name'] ?? ''),
            'sourceRole' => (string) $user['role'],
            'platformRole' => $platformRole,
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
}
