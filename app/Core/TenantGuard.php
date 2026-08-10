<?php

namespace App\Core;

use App\Services\TenantRegistryStatusService;

final class TenantGuard
{
    private const LOCKED_MESSAGE = 'Đơn vị hiện đang bị khóa.';

    public static function enforce(Request $request, ?TenantRegistryStatusService $statusService = null): void
    {
        if (!PortalContext::isTenant()) {
            return;
        }

        $status = ($statusService ?? new TenantRegistryStatusService())->statusForHost(PortalContext::host());
        if (($status['active'] ?? false) === true) {
            return;
        }

        self::deny($request, $status);
    }

    private static function deny(Request $request, array $status): void
    {
        $message = self::LOCKED_MESSAGE;
        $reason = strtoupper((string) ($status['reason'] ?? 'TENANT_LOCKED'));
        $errorCode = in_array($reason, ['REGISTRY_UNAVAILABLE', 'TENANT_NOT_REGISTERED', 'INVALID_HOST'], true)
            ? $reason
            : 'TENANT_LOCKED';

        if (self::expectsJson($request)) {
            Response::json([
                'ok' => false,
                'success' => false,
                'error' => $errorCode,
                'message' => $message,
                'errors' => [],
                'status' => 423,
                'tenant' => $status['tenant'] ?? null,
            ], 423);
        }

        http_response_code(423);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo self::lockedPage();
        exit;
    }

    private static function expectsJson(Request $request): bool
    {
        if (str_starts_with($request->path(), '/api/')) {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    private static function lockedPage(): string
    {
        return '<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Hệ thống tạm khóa</title><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f6f9;color:#111827;font-family:Arial,sans-serif}.panel{width:min(560px,calc(100% - 32px));background:#fff;border:1px solid #d7dee8;border-radius:8px;padding:32px;box-shadow:0 24px 80px rgba(15,23,42,.12)}.mark{width:48px;height:48px;border-radius:8px;background:#991b1b;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;margin-bottom:18px}h1{font-size:24px;line-height:1.25;margin:0 0 12px;text-transform:uppercase}p{font-size:16px;line-height:1.6;margin:0;color:#4b5563}</style></head><body><main class="panel"><div class="mark">!</div><h1>Hệ thống tạm khóa</h1><p>Đơn vị này hiện đang bị tạm khóa bởi quản trị hệ thống. Vui lòng liên hệ quản trị viên để được hỗ trợ.</p></main></body></html>';
    }
}
