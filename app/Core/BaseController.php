<?php

namespace App\Core;

use App\Models\AuditLog;
use App\Models\User;
use Throwable;

abstract class BaseController
{
    private ?User $usersModel = null;
    private ?AuditLog $logsModel = null;

    public function __construct(protected Request $request)
    {
    }

    protected function input(?string $key = null, mixed $default = null): mixed
    {
        return $this->request->input($key, $default);
    }

    protected function query(?string $key = null, mixed $default = null): mixed
    {
        return $this->request->query($key, $default);
    }

    protected function ok(mixed $data = null): void
    {
        Response::ok($data);
    }

    protected function fail(string $message, int $status = 400): void
    {
        Response::error($message, $status);
    }

    protected function debugEnabled(): bool
    {
        return filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
    }

    protected function safeExceptionMessage(string $message, Throwable $exception): string
    {
        return $this->debugEnabled() ? $message . ': ' . $exception->getMessage() : $message;
    }

    protected function requireInputFields(array $input, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field => $label) {
            $value = $input[$field] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $missing[$field] = $label;
            }
        }

        if ($missing) {
            Response::json(['ok' => false, 'error' => ['message' => 'Dữ liệu không hợp lệ', 'details' => ['missing' => $missing]]], 422);
        }
    }

    protected function users(): User
    {
        return $this->usersModel ??= new User();
    }

    protected function logs(): AuditLog
    {
        return $this->logsModel ??= new AuditLog();
    }

    protected function user(): array
    {
        $token = $this->request->bearerToken();
        $user = $token ? $this->users()->findByToken($token) : null;
        if (!$user) {
            Response::error('Vui lòng đăng nhập', 401);
        }
        return $user;
    }

    protected function verifyCsrfToken(): void
    {
        if (in_array($this->request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = (string) ($this->request->bearerToken() ?? '');
        $submitted = (string) $this->request->header('x-csrf-token', '');
        $expected = $token !== '' ? $this->users()->csrfToken($token) : '';

        if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
            Response::error('CSRF token không hợp lệ', 419);
        }
    }

    protected function requirePermission(string $module, string $action): array
    {
        $user = $this->user();
        $this->verifyCsrfToken();
        if (!$this->users()->can($user, $module, $action)) {
            Response::error('Không có quyền ' . $action . ' module ' . $module, 403);
        }
        return $user;
    }

    protected function auditPermissionDenied(?array $user, string $module, string $action): void
    {
        try {
            $this->audit($user, $module, 'permission_denied', 'Từ chối thao tác không đủ quyền', null, [
                'role' => $user['role'] ?? null,
                'denied_action' => $action,
                'endpoint' => $this->request->method() . ' ' . $this->request->path(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'time' => date('c'),
            ], 'WARN');
        } catch (Throwable $e) {
            error_log('[RBAC_DENIED_AUDIT_ERROR] ' . $e->getMessage());
        }
    }

    protected function audit(?array $user, string $module, string $action, string $message, mixed $entityId = null, array $metadata = [], string $level = 'INFO'): void
    {
        $this->logs()->write($user['id'] ?? null, $user['email'] ?? null, $module, $action, $message, $entityId === null ? null : (string) $entityId, $metadata, $level);
    }
}
