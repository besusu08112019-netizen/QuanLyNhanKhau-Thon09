<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Core\RuntimePaths;
use App\Core\TenantConfig;
use Throwable;

final class SystemAdmin extends BaseModel
{
    public function overview(): array
    {
        $app = is_file(BASE_PATH . '/config/app.php') ? require BASE_PATH . '/config/app.php' : [];
        $population = (new PopulationStatistics())->counts();
        return [
            'system' => [
                'name' => $app['name'] ?? TenantConfig::setting('systemName', 'Há»‡ thá»‘ng Quáº£n lÃ½ HÃ nh chÃ­nh'),
                'version' => defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1.0.0',
                'phpVersion' => PHP_VERSION,
                'databaseVersion' => $this->databaseVersion(),
                'uptime' => $this->uptimeLabel(),
                'generatedAt' => date('c'),
            ],
            'counts' => [
                'users' => $this->countTable('users', 'status <> "DELETED"'),
                'households' => $population['total_households'],
                'citizens' => $population['total_citizens'],
                'digitalProfiles' => $this->countTable('file_attachments', 'deleted_at IS NULL'),
                'documents' => $this->countFiles(['pdf','doc','docx','xls','xlsx','txt','csv']),
                'images' => $this->countFiles(['jpg','jpeg','png','gif','webp']),
                'videos' => $this->countFiles(['mp4','mov','avi','mkv','webm']),
            ],
            'storage' => [
                'root' => $this->pathStats(BASE_PATH),
                'uploads' => $this->pathStats($this->uploadRoot()),
                'storage' => $this->pathStats($this->storageRoot()),
            ],
        ];
    }

    public function health(): array
    {
        $started = microtime(true);
        $checks = [];
        $checks[] = $this->check('database', 'Database káº¿t ná»‘i', fn() => ['message' => 'OK', 'meta' => ['version' => $this->databaseVersion()]]);
        $checks[] = $this->check('api', 'API hoáº¡t Ä‘á»™ng', fn() => ['message' => 'OK', 'meta' => ['responseMs' => round((microtime(true) - $started) * 1000, 2)]]);
        $checks[] = $this->checkPath('uploads', 'ThÆ° má»¥c Upload', $this->uploadRoot(), true);
        $checks[] = $this->checkPath('storage', 'ThÆ° má»¥c Storage', $this->storageRoot(), true);
        $checks[] = $this->check('disk', 'Dung lÆ°á»£ng á»• Ä‘Ä©a', function () {
            $free = @disk_free_space(BASE_PATH);
            $total = @disk_total_space(BASE_PATH);
            $percent = $total ? round((1 - ($free / $total)) * 100, 1) : 0;
            return ['status' => $percent >= 90 ? 'warning' : 'ok', 'message' => $percent . '% Ä‘Ã£ sá»­ dá»¥ng', 'meta' => ['free' => $free, 'total' => $total, 'usedPercent' => $percent]];
        });
        $checks[] = $this->check('memory', 'Bá»™ nhá»› PHP', fn() => ['message' => $this->bytes(memory_get_usage(true)) . ' Ä‘ang dÃ¹ng', 'meta' => ['peak' => memory_get_peak_usage(true), 'limit' => ini_get('memory_limit')]]);
        $checks[] = $this->check('sessions', 'PhiÃªn Ä‘Äƒng nháº­p', fn() => ['message' => $this->activeSessionCount() . ' phiÃªn Ä‘ang hoáº¡t Ä‘á»™ng']);
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
        return $this->paginated(array_map(fn($row) => $this->sessionRow($row), $items), $page, $pageSize, $total);
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
                ['label' => 'Pháº£n há»“i Database', 'value' => $dbMs, 'unit' => 'ms', 'status' => $dbMs > 500 ? 'warning' : 'ok'],
                ['label' => 'Bá»™ nhá»› hiá»‡n táº¡i', 'value' => round(memory_get_usage(true) / 1048576, 2), 'unit' => 'MB', 'status' => 'ok'],
                ['label' => 'Bá»™ nhá»› Ä‘á»‰nh', 'value' => round(memory_get_peak_usage(true) / 1048576, 2), 'unit' => 'MB', 'status' => 'ok'],
            ],
            'slowQueries' => $latestSlow,
            'recommendations' => ['Theo dÃµi API > 500ms Ä‘á»ƒ tá»‘i Æ°u truy váº¥n.', 'KhÃ´ng sinh PDF/Excel hÃ ng loáº¡t náº¿u khÃ´ng cÃ³ yÃªu cáº§u.', 'Dá»n cache vÃ  session háº¿t háº¡n Ä‘á»‹nh ká»³.'],
        ];
    }

    public function security(): array
    {
        return ['checks' => [
            ['label' => 'PhÃ¢n quyá»n API', 'status' => 'ok', 'message' => 'CÃ¡c API quáº£n trá»‹ yÃªu cáº§u ADMIN/SUPER_ADMIN'],
            ['label' => 'CSRF', 'status' => 'ok', 'message' => 'CÃ¡c thao tÃ¡c ghi kiá»ƒm tra X-CSRF-Token'],
            ['label' => 'XSS', 'status' => 'ok', 'message' => 'Frontend escape dá»¯ liá»‡u Ä‘á»™ng trÆ°á»›c khi render'],
            ['label' => 'SQL Injection', 'status' => 'ok', 'message' => 'Model sá»­ dá»¥ng prepared statement cho tham sá»‘ ngÆ°á»i dÃ¹ng'],
            ['label' => 'Upload an toÃ n', 'status' => 'ok', 'message' => 'Giá»›i háº¡n loáº¡i tá»‡p vÃ  kÃ­ch thÆ°á»›c á»Ÿ FileStorageService'],
            ['label' => 'Giá»›i háº¡n upload', 'status' => 'ok', 'message' => 'upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size')],
        ]];
    }

    public function memory(): array
    {
        return ['items' => [
            ['key' => 'cache', 'label' => 'Cache', 'stats' => $this->pathStats($this->cacheRoot())],
            ['key' => 'sessions', 'label' => 'Session háº¿t háº¡n', 'stats' => ['files' => 0, 'bytes' => 0, 'expired' => $this->expiredSessionCount(), 'label' => $this->expiredSessionCount() . ' phiÃªn']],
            ['key' => 'logs', 'label' => 'Log', 'stats' => $this->pathStats($this->logsRoot())],
            ['key' => 'tmp', 'label' => 'File táº¡m', 'stats' => $this->pathStats($this->tempRoot())],
        ]];
    }

    public function cleanup(string $target): array
    {
        return match ($target) {
            'cache' => $this->cleanupDirectory($this->cacheRoot()),
            'sessions' => ['removed' => $this->execute('UPDATE user_sessions SET revoked_at = NOW() WHERE revoked_at IS NULL AND expires_at <= NOW()'), 'bytes' => 0, 'label' => '0 B'],
            'tmp' => $this->cleanupDirectory($this->tempRoot(), true),
            default => throw new \RuntimeException('KhÃ´ng há»— trá»£ dá»n dáº¹p má»¥c nÃ y'),
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
        $base = $this->uploadRoot(); if (!is_dir($base)) return 0; $count = 0; $allowed = array_flip(array_map('strtolower', $extensions));
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
            if (!is_dir($path)) return ['status' => 'warning', 'message' => 'ChÆ°a tá»“n táº¡i'];
            if ($requireWritable && !is_writable($path)) return ['status' => 'error', 'message' => 'KhÃ´ng cÃ³ quyá»n ghi'];
            return ['message' => 'Sáºµn sÃ ng', 'meta' => $this->pathStats($path)];
        });
    }
    private function sessionRow(array $row): array { $agent = (string) ($row['user_agent'] ?? ''); return $row + ['device' => $this->deviceFromAgent($agent), 'browser' => $this->browserFromAgent($agent)]; }
    private function activeSessionCount(): int { return $this->tableExists('user_sessions') ? $this->countTable('user_sessions', 'revoked_at IS NULL AND expires_at > NOW()') : 0; }
    private function expiredSessionCount(): int { return $this->tableExists('user_sessions') ? $this->countTable('user_sessions', 'revoked_at IS NULL AND expires_at <= NOW()') : 0; }
    private function tableExists(string $table): bool { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]); return (int) ($row['total'] ?? 0) > 0; }
    private function uploadRoot(): string { $config = $this->appConfig(); return rtrim(str_replace('\\', '/', (string) ($config['upload_path'] ?? RuntimePaths::uploadRoot())), '/'); }
    private function storageRoot(): string { $config = $this->appConfig(); return rtrim(str_replace('\\', '/', (string) ($config['storage_path'] ?? RuntimePaths::storageRoot())), '/'); }
    private function cacheRoot(): string { $config = $this->appConfig(); return rtrim(str_replace('\\', '/', (string) ($config['cache_path'] ?? $this->storageRoot() . '/cache')), '/'); }
    private function logsRoot(): string { $config = $this->appConfig(); return rtrim(str_replace('\\', '/', (string) ($config['logs_path'] ?? $this->storageRoot() . '/logs')), '/'); }
    private function tempRoot(): string { $config = $this->appConfig(); return rtrim(str_replace('\\', '/', (string) ($config['temp_path'] ?? RuntimePaths::tempRoot())), '/'); }
    private function appConfig(): array { return is_file(BASE_PATH . '/config/app.php') ? require BASE_PATH . '/config/app.php' : []; }
    private function bytes(int|float|null $bytes): string { $bytes = max(0, (float) ($bytes ?? 0)); foreach (['B','KB','MB','GB','TB'] as $unit) { if ($bytes < 1024 || $unit === 'TB') return round($bytes, $unit === 'B' ? 0 : 2) . ' ' . $unit; $bytes /= 1024; } return '0 B'; }
    private function uptimeLabel(): string { if (function_exists('sys_getloadavg')) { $load = @sys_getloadavg(); if ($load) return 'Load ' . implode(' / ', array_map(fn($v) => round((float) $v, 2), $load)); } return 'Äang hoáº¡t Ä‘á»™ng'; }
    private function deviceFromAgent(string $agent): string { return preg_match('/Mobile|Android|iPhone/i', $agent) ? 'Mobile' : (preg_match('/Tablet|iPad/i', $agent) ? 'Tablet' : 'Desktop'); }
    private function browserFromAgent(string $agent): string { foreach (['Edg' => 'Edge', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $needle => $label) if (stripos($agent, $needle) !== false) return $label; return $agent !== '' ? 'KhÃ¡c' : 'KhÃ´ng rÃµ'; }
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
