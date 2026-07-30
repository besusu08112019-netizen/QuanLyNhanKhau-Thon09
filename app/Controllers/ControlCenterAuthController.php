<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Response;
use App\Services\ControlCenterAuthService;
use App\Services\ControlCenterAuditService;
use RuntimeException;

final class ControlCenterAuthController extends BaseController
{
    private ControlCenterAuthService $service;
    private ControlCenterAuditService $audit;

    public function __construct(\App\Core\Request $request)
    {
        parent::__construct($request);
        $this->service = new ControlCenterAuthService();
        $this->audit = new ControlCenterAuditService();
    }

    public function login(): void
    {
        try {
            $result = $this->service->login(
                (string) $this->input('username', $this->input('email', '')),
                (string) $this->input('password', '')
            );
            $this->audit->write($this->actor($result['user']), 'auth.login', (int) ($result['user']['unitId'] ?? 0), 'Đăng nhập Community Control Center');
            $this->ok($result);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        }
    }

    public function me(): void
    {
        try {
            $this->ok($this->service->me((string) $this->request->bearerToken()));
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 401);
        }
    }

    public function logout(): void
    {
        $token = (string) $this->request->bearerToken();
        $this->verifyCsrfToken($token);
        $result = $this->service->logout($token);
        $this->audit->write(null, 'auth.logout', null, 'Đăng xuất Community Control Center');
        $this->ok($result);
    }

    protected function verifyCsrfToken(string $token = ''): void
    {
        $submitted = (string) $this->request->header('x-csrf-token', '');
        $expected = $token !== '' ? $this->service->csrfToken($token) : '';
        if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
            Response::error('CSRF token không hợp lệ', 419);
        }
    }

    private function actor(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'displayName' => (string) ($user['displayName'] ?? ''),
            'sourceRole' => (string) ($user['sourceRole'] ?? ''),
            'platformRole' => (string) ($user['role'] ?? ''),
            'village_id' => (int) ($user['unitId'] ?? 0),
        ];
    }
}
