<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Backup;
use App\Models\SystemAdmin;
use Throwable;

final class SystemAdminController extends BaseController
{
    private SystemAdmin $admin;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->admin = new SystemAdmin();
    }

    public function overview(): void { $this->safeRead(fn() => $this->admin->overview()); }
    public function health(): void { $this->safeRead(fn() => $this->admin->health()); }
    public function sessions(): void { $this->safeRead(fn() => $this->admin->sessions($this->query())); }
    public function memory(): void { $this->safeRead(fn() => $this->admin->memory()); }
    public function performance(): void { $this->safeRead(fn() => $this->admin->performance()); }
    public function security(): void { $this->safeRead(fn() => $this->admin->security()); }
    public function configuration(): void { $this->safeRead(fn() => $this->admin->configuration()); }

    public function revokeSession(string $id): void
    {
        $user = $this->requireAdmin(true);
        $count = $this->admin->revokeSession((int) $id);
        $this->audit($user, 'system_admin', 'session_revoke', 'Đăng xuất một phiên', $id, ['count' => $count], 'WARN');
        $this->ok(['revoked' => $count]);
    }

    public function revokeAllSessions(): void
    {
        $user = $this->requireAdmin(true);
        $count = $this->admin->revokeAllSessions((int) $user['id']);
        $this->audit($user, 'system_admin', 'session_revoke_all', 'Đăng xuất tất cả phiên khác', null, ['count' => $count], 'WARN');
        $this->ok(['revoked' => $count]);
    }

    public function cleanup(): void
    {
        $user = $this->requireAdmin(true);
        $target = (string) $this->input('target', '');
        $result = $this->admin->cleanup($target);
        $this->audit($user, 'system_admin', 'cleanup', 'Dọn dẹp bộ nhớ hệ thống', null, ['target' => $target] + $result, 'WARN');
        $this->ok($result);
    }

    public function createBackup(): void
    {
        $user = $this->requireAdmin(true);
        $type = (string) $this->input('type', 'database');
        $backup = (new Backup())->createSqlDump((int) $user['id']);
        $this->audit($user, 'backup', 'export', 'Tạo backup Sprint 17', null, ['type' => $type, 'fileName' => $backup['fileName'], 'size' => $backup['size'], 'checksum' => $backup['checksum']]);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $backup['fileName'] . '"');
        echo $backup['content'];
        exit;
    }

    private function safeRead(callable $callback): void
    {
        $this->requireAdmin(false);
        try {
            $this->ok($callback());
        } catch (Throwable $e) {
            error_log('[SYSTEM_ADMIN_WIDGET_ERROR] ' . $e->getMessage());
            $this->ok(['status' => 'error', 'message' => $e->getMessage(), 'items' => []]);
        }
    }

    private function requireAdmin(bool $write): array
    {
        return $this->requireSuperAdmin('system_admin', $write ? 'update' : 'read');
    }
}
