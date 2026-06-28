<?php

namespace App\Core;

use App\Models\AuditLog;
use App\Models\User;

abstract class BaseController
{
    protected User $users;
    protected AuditLog $logs;

    public function __construct(protected Request $request)
    {
        $this->users = new User();
        $this->logs = new AuditLog();
    }

    protected function input(?string $key = null, mixed $default = null): mixed { return $this->request->input($key, $default); }
    protected function query(?string $key = null, mixed $default = null): mixed { return $this->request->query($key, $default); }
    protected function ok(mixed $data = null): void { Response::ok($data); }
    protected function fail(string $message, int $status = 400): void { Response::error($message, $status); }

    protected function user(): array
    {
        $token = $this->request->bearerToken();
        $user = $token ? $this->users->findByToken($token) : null;
        if (!$user) {
            Response::error('Vui lòng đăng nhập', 401);
        }
        return $user;
    }

    protected function requirePermission(string $module, string $action): array
    {
        $user = $this->user();
        if (!$this->users->can($user, $module, $action)) {
            Response::error('Không có quyền ' . $action . ' module ' . $module, 403);
        }
        return $user;
    }

    protected function audit(?array $user, string $module, string $action, string $message, mixed $entityId = null, array $metadata = [], string $level = 'INFO'): void
    {
        $this->logs->write($user['id'] ?? null, $user['email'] ?? null, $module, $action, $message, $entityId === null ? null : (string) $entityId, $metadata, $level);
    }
}
