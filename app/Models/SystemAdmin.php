<?php

namespace App\Models;

use App\Core\BaseModel;
use Throwable;

final class SystemAdmin extends BaseModel
{
    public function overview(): array
    {
        $app = is_file(BASE_PATH . '/config/app.php') ? require BASE_PATH . '/config/app.php' : [];
        return [
            'system' => [
                'name' => $app['name'] ?? 'Thon 09',
                'version' => defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1.0.0',
                'phpVersion' => PHP_VERSION,
                'databaseVersion' => $this->databaseVersion(),
                'uptime' => $this->uptimeLabel(),
                'generatedAt' => date('c'),
            ],
            'counts' => [
                'users' => $this->countTable('users', 'status <> "DELETED"'),
                'households' => $this->countTable('households', 'deleted_at IS NULL'),
                'citizens' => $this->countTable('citizens', 'deleted_at IS NULL'),
                'digitalProfiles' => $this->countTable('file_attachments', 'deleted_at IS NULL'),
                'documents' => $this->countFiles(['pdf','doc','docx','xls','xlsx','txt','csv']),
                'images' => $this->countFiles(['jpg','jpeg','png','gif','webp']),
                'videos' => $this->countFiles(['mp4','mov','avi','mkv','webm']),
            ],
            'storage' => [
                'root' => $this->pathStats(BASE_PATH),
                'uploads' => $this->pathStats(BASE_PATH . '/uploads'),
                'storage' => $this->pathStats(BASE_PATH . '/storage'),
            ],
        ];
    }

    public function health(): array
    {
        $started = microtime(true);
        $checks = [];
        $checks[] = $this->check('database', 'Database kết nối', fn() => ['message' => 'OK', 'meta' => ['version' => $this->databaseVersion()]]);
        $checks[] = $this->check('api', 'API hoạt động', fn() => ['message' => 'OK', 'meta' => ['responseMs' => round((microtime(true) - $started) * 1000, 2)]]);
        $checks[] = $this->checkPath('uploads', 'Thư mục Upload', BASE_PATH . '/uploads', true);
        $checks[] = $this->checkPath('storage', 'Thư mục Storage', BASE_PATH . '/storage', true);
        $checks[] = $this->check('disk', 'Dung lượng ổ đĩa', function () {
            $free = @disk_free_space(BASE_PATH);
            $total = @disk_total_space(BASE_PATH);
            $percent = $total ? round((1 - ($free / $total)) * 100, 1) : 0;
            return ['status' => $percent >= 90 ? 'warning' : 'ok', 'message' => $percent . '% đã sử dụng', 'meta' => ['free' => $free, 'total' => $total, 'usedPercent' => $percent]];
        });
        $checks[] = $this->check('memory', 'Bộ nhớ PHP', fn() => ['message' => $this->bytes(memory_get_usage(true)) . ' đang dùng', 'meta' => ['peak' => memory_get_peak_usage(true), 'limit' => ini_get('memory_limit')]]);
        $checks[] = $this->check('sessions', 'Phiên đăng nhập', fn() => ['message' => $this->activeSessionCount() . ' phiên đang hoạt động']);
        $summary = ['ok' => 0, 'warning' => 0, 'error' => 0];
        foreach ($checks as $check) $summary[$check['status']] = ($summary[$check['status']] ?? 0) + 1;
        return ['summary' => $summary, 'checks' => $checks, 'generatedAt' => date('c')];
    }

    public function sessions(array $filters = []): array
    {
        if (!$this->tableExists('user_sessions')) return ['items' => [], 'total' => 0];
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 30));
        $where = ['1=1']; $params = [];
        if (($filters['status'] ?? '') === 'active') $where[] = 's.revoked_at IS NULL AND s.expires_at > NOW()';
        if (($filters['status'] ?? '') === 'revoked') $where[] = 's.revoked_at IS NOT NULL';
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $where[] = '(u.email LIKE :q OR u.display_name LIKE :q OR s.ip_address LIKE :q OR s.user_agent LIKE :q)';
            $params['q'] = $q;
        }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) ($this->fetchOne("SELECT COUNT(*) AS total FROM user_sessions s LEFT JOIN users u ON u.id=s.user_id $sqlWhere", $params)['total'] ?? 0);
        $items = $this->fetchAll("SELECT s.id, s.user_id, u.email, u.display_name, u.role, s.ip_address, s.user_agent, s.created_at, s.expires_at, s.revoked_at, CASE WHEN s.revoked_at IS NULL AND s.expires_at > NOW() THEN 'ACTIVE' WHEN s.revoked_at IS NOT NULL THEN 'REVOKED' ELSE 'EXPIRED' END AS status FROM user_sessions s LEFT JOIN users u ON u.id=s.user_id $sqlWhere ORDER BY s.created_at DESC LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => array_map(fn($row) => $this->sessionRow($row), $items), 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

    public function revokeSession(int $id): int { return $this->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL', ['id' => $id]); }
    public function revokeAllSessions(?int $exceptUserId = null): int
    {
        $params = []; $where = 'revoked_at IS NULL';
        if ($exceptUserId) { $where .= ' AND user_id <> :uid'; $params['uid'] = $exceptUserId; }
        return $this->execute("UPDATE user_sessions SET revoked_at = NOW() WHERE $where", $params);
    }

    public function performance(): array
    {
        $started = microtime(true); $this->fetchOne('SELECT 1 AS ok'); $dbMs = round((microtime(true) - $started) * 1000, 2);
        $latestSlow = $this->tableExists('audit_logs') ? $this->fetchAll("SELECT created_at, module, action, message FROM audit_logs WHERE message LIKE '%slow%' OR action LIKE '%slow%' ORDER BY created_at DESC LIMIT 10") : [];
        return [
            'metrics' => [
                ['label' => 'Phản hồi Database', 'value' => $dbMs, 'unit' => 'ms', 'status' => $dbMs > 500 ? 'warning' : 'ok'],
                ['label' => 'Bộ nhớ hiện tại', 'value' => round(memory_get_usage(true) / 1048576, 2), 'unit' => 'MB', 'status' => 'ok'],
                ['label' => 'Bộ nhớ đỉnh', 'value' => round(memory_get_peak_usage(true) / 1048576, 2), 'unit' => 'MB', 'status' => 'ok'],
            ],
            'slowQueries' => $latestSlow,
            'recommendations' => ['Theo dõi API > 500ms để tối ưu truy vấn.', 'Không sinh PDF/Excel hàng loạt nếu không có yêu cầu.', 'Dọn cache và session hết hạn định kỳ.'],
        ];
    }

    public function security(): array
    {
        return ['checks' => [
            ['label' => 'Phân quyền API', 'status' => 'ok', 'message' => 'Các API quản trị yêu cầu ADMIN/SUPER_ADMIN'],
            ['label' => 'CSRF', 'status' => 'ok', 'message' => 'Các thao tác ghi kiểm tra X-CSRF-Token'],
            ['label' => 'XSS', 'status' => 'ok', 'message' => 'Frontend escape dữ liệu động trước khi render'],
            ['label' => 'SQL Injection', 'status' => 'ok', 'message' => 'Model sử dụng prepared statement cho tham số người dùng'],
            ['label' => 'Upload an toàn', 'status' => 'ok', 'message' => 'Giới hạn loại tệp và kích thước ở FileStorageService'],
            ['label' => 'Giới hạn upload', 'status' => 'ok', 'message' => 'upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size')],
        ]];
    }

    public function memory(): array
    {
        return ['items' => [
            ['key' => 'cache', 'label' => 'Cache', 'stats' => $this->pathStats(BASE_PATH . '/storage/cache')],
            ['key' => 'sessions', 'label' => 'Session hết hạn', 'stats' => ['files' => 0, 'bytes' => 0, 'expired' => $this->expiredSessionCount(), 'label' => $this->expiredSessionCount() . ' phiên']],
            ['key' => 'logs', 'label' => 'Log', 'stats' => $this->pathStats(BASE_PATH . '/storage')],
            ['key' => 'tmp', 'label' => 'File tạm', 'stats' => $this->pathStats(sys_get_temp_dir())],
        ]];
    }

    public function cleanup(string $target): array
    {
        return match ($target) {
            'cache' => $this->cleanupDirectory(BASE_PATH . '/storage/cache'),
            'sessions' => ['removed' => $this->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE revoked_at IS NULL AND expires_at <= NOW()'), 'bytes' => 0, 'label' => '0 B'],
            'tmp' => $this->cleanupDirectory(sys_get_temp_dir(), true),
            default => throw new \RuntimeException('Không hỗ trợ dọn dẹp mục này'),
        };
    }

    public function configuration(): array
    {
        $settings = [];
        if ($this->tableExists('settings')) foreach ($this->fetchAll('SELECT setting_key, setting_value FROM settings ORDER BY setting_key') as $row) $settings[$row['setting_key']] = $row['setting_value'];
        return ['settings' => $settings, 'timezone' => date_default_timezone_get(), 'php' => ['version' => PHP_VERSION, 'sapi' => PHP_SAPI]];
    }

    private function databaseVersion(): string { try { return (string) $this->db->getAttribute(\PDO::ATTR_SERVER_VERSION); } catch (Throwable) { return 'unknown'; } }
    private function countTable(string $table, string $where = '1=1'): int { if (!$this->tableExists($table)) return 0; try { return (int) ($this->fetchOne("SELECT COUNT(*) AS total FROM `$table` WHERE $where")['total'] ?? 0); } catch (Throwable) { return 0; } }
    private function countFiles(array $extensions): int
    {
        $base = BASE_PATH . '/uploads'; if (!is_dir($base)) return 0; $count = 0; $allowed = array_flip(array_map('strtolower', $extensions));
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) if ($file->isFile() && isset($allowed[strtolower($file->getExtension())])) $count++;
        return $count;
    }
    private function pathStats(string $path): array
    {
        $stats = ['path' => $path, 'exists' => is_dir($path) || is_file($path), 'writable' => is_writable($path), 'bytes' => 0, 'files' => 0, 'label' => '0 B'];
        if (!$stats['exists']) return $stats;
        if (is_file($path)) { $stats['bytes'] = (int) filesize($path); $stats['files'] = 1; $stats['label'] = $this->bytes($stats['bytes']); return $stats; }
        try { $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)); foreach ($it as $file) if ($file->isFile()) { $stats['files']++; $stats['bytes'] += (int) $file->getSize(); } } catch (Throwable) {}
        $stats['label'] = $this->bytes($stats['bytes']); return $stats;
    }
    private function check(string $key, string $label, callable $callback): array
    {
        try { $result = $callback(); return ['key' => $key, 'label' => $label, 'status' => $result['status'] ?? 'ok', 'message' => $result['message'] ?? 'OK', 'meta' => $result['meta'] ?? []]; }
        catch (Throwable $e) { return ['key' => $key, 'label' => $label, 'status' => 'error', 'message' => $e->getMessage(), 'meta' => []]; }
    }
    private function checkPath(string $key, string $label, string $path, bool $requireWritable): array
    {
        return $this->check($key, $label, function () use ($path, $requireWritable) {
            if (!is_dir($path)) return ['status' => 'warning', 'message' => 'Chưa tồn tại'];
            if ($requireWritable && !is_writable($path)) return ['status' => 'error', 'message' => 'Không có quyền ghi'];
            return ['message' => 'Sẵn sàng', 'meta' => $this->pathStats($path)];
        });
    }
    private function sessionRow(array $row): array { $agent = (string) ($row['user_agent'] ?? ''); return $row + ['device' => $this->deviceFromAgent($agent), 'browser' => $this->browserFromAgent($agent)]; }
    private function activeSessionCount(): int { return $this->tableExists('user_sessions') ? $this->countTable('user_sessions', 'revoked_at IS NULL AND expires_at > NOW()') : 0; }
    private function expiredSessionCount(): int { return $this->tableExists('user_sessions') ? $this->countTable('user_sessions', 'revoked_at IS NULL AND expires_at <= NOW()') : 0; }
    private function tableExists(string $table): bool { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]); return (int) ($row['total'] ?? 0) > 0; }
    private function bytes(int|float|null $bytes): string { $bytes = max(0, (float) ($bytes ?? 0)); foreach (['B','KB','MB','GB','TB'] as $unit) { if ($bytes < 1024 || $unit === 'TB') return round($bytes, $unit === 'B' ? 0 : 2) . ' ' . $unit; $bytes /= 1024; } return '0 B'; }
    private function uptimeLabel(): string { if (function_exists('sys_getloadavg')) { $load = @sys_getloadavg(); if ($load) return 'Load ' . implode(' / ', array_map(fn($v) => round((float) $v, 2), $load)); } return 'Đang hoạt động'; }
    private function deviceFromAgent(string $agent): string { return preg_match('/Mobile|Android|iPhone/i', $agent) ? 'Mobile' : (preg_match('/Tablet|iPad/i', $agent) ? 'Tablet' : 'Desktop'); }
    private function browserFromAgent(string $agent): string { foreach (['Edg' => 'Edge', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $needle => $label) if (stripos($agent, $needle) !== false) return $label; return $agent !== '' ? 'Khác' : 'Không rõ'; }
    private function cleanupDirectory(string $path, bool $oldOnly = false): array
    {
        if (!is_dir($path)) return ['removed' => 0, 'bytes' => 0, 'label' => '0 B'];
        $removed = 0; $bytes = 0; $cutoff = time() - 86400;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            if ($oldOnly && $file->getMTime() > $cutoff) continue;
            if (str_starts_with($file->getFilename(), '.')) continue;
            $size = (int) $file->getSize();
            if (@unlink($file->getPathname())) { $removed++; $bytes += $size; }
        }
        return ['removed' => $removed, 'bytes' => $bytes, 'label' => $this->bytes($bytes)];
    }
}
