<?php

namespace App\Core;

use App\Services\TenantRegistryStatusService;

final class TenantGuard
{
    private const LOCKED_MESSAGE = 'ÄÆ¡n vá»‹ hiá»‡n Ä‘ang bá»‹ khÃ³a.';

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
        $message = self::lockedMessage();
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

    private static function lockedMessage(): string
    {
        return html_entity_decode('&#272;&#417;n v&#7883; hi&#7879;n &#273;ang b&#7883; kh&#243;a.', ENT_QUOTES, 'UTF-8');
    }

    private static function lockedPage(): string
    {
        $title = html_entity_decode('H&#7879; th&#7889;ng t&#7841;m kh&#243;a', ENT_QUOTES, 'UTF-8');
        $message = html_entity_decode('&#272;&#417;n v&#7883; n&#224;y hi&#7879;n &#273;ang b&#7883; t&#7841;m kh&#243;a b&#7903;i qu&#7843;n tr&#7883; h&#7879; th&#7889;ng. Vui l&#242;ng li&#234;n h&#7879; qu&#7843;n tr&#7883; vi&#234;n &#273;&#7875; &#273;&#432;&#7907;c h&#7895; tr&#7907;.', ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . $title . '</title><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f6f9;color:#111827;font-family:Arial,sans-serif}.panel{width:min(560px,calc(100% - 32px));background:#fff;border:1px solid #d7dee8;border-radius:8px;padding:32px;box-shadow:0 24px 80px rgba(15,23,42,.12)}.mark{width:48px;height:48px;border-radius:8px;background:#991b1b;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;margin-bottom:18px}h1{font-size:24px;line-height:1.25;margin:0 0 12px;text-transform:uppercase}p{font-size:16px;line-height:1.6;margin:0;color:#4b5563}</style></head><body><main class="panel"><div class="mark">!</div><h1>' . $title . '</h1><p>' . $message . '</p></main></body></html>';
    }
}
