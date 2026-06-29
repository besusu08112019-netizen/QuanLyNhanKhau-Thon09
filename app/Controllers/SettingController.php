<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Dashboard;
use App\Models\SystemSetting;

final class SettingController extends BaseController
{
    private SystemSetting $settings;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->settings = new SystemSetting();
    }

    public function index(): void
    {
        $this->requirePermission('settings', 'read');
        $this->ok($this->settings->all());
    }

    public function publicLoginConfig(): void
    {
        $dashboard = new Dashboard();
        $metrics = $dashboard->metrics();
        $this->ok([
            'settings' => $this->settings->all(),
            'metrics' => $this->loginMetrics($metrics),
            'generatedAt' => date('c'),
        ]);
    }

    public function update(): void
    {
        $user = $this->requirePermission('settings', 'update');
        $settings = $this->settings->updateMany($this->input(), (int) $user['id']);
        $this->audit($user, 'settings', 'update', 'Cập nhật cấu hình hệ thống');
        $this->ok($settings);
    }

    public function uploadMedia(): void
    {
        $user = $this->requirePermission('settings', 'update');
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) $this->fail('Vui lòng chọn file');
        $file = $_FILES['file'];
        if ((int) $file['size'] > 2 * 1024 * 1024) $this->fail('File tối đa 2MB');

        $original = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $allowedExtensions = ['png','jpg','jpeg','svg','webp'];
        $allowedMimes = ['image/png','image/jpeg','image/svg+xml','image/webp'];
        if (!in_array($extension, $allowedExtensions, true) || (!in_array($mime, $allowedMimes, true) && $extension !== 'svg')) {
            $this->fail('Chỉ cho phép PNG, JPG, JPEG, SVG hoặc WebP');
        }

        $dir = BASE_PATH . '/uploads/ui/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) $this->fail('Không tạo được thư mục upload');
        $stored = bin2hex(random_bytes(16)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
        $path = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $path)) $this->fail('Không lưu được file upload');
        $relative = 'uploads/ui/' . date('Y/m') . '/' . $stored;
        $this->audit($user, 'settings', 'upload', 'Upload media giao diện', null, ['file' => $relative, 'mime' => $mime, 'size' => (int) $file['size']]);
        $this->ok(['url' => $relative, 'name' => basename($original), 'mime' => $mime, 'size' => (int) $file['size']]);
    }

    public function deleteMedia(): void
    {
        $user = $this->requirePermission('settings', 'update');
        $key = (string) $this->input('key', '');
        if (!in_array($key, ['logoUrl','backgroundUrl','backgroundImages'], true)) $this->fail('Loại media không hợp lệ');
        $settings = $this->settings->updateMany([$key => ''], (int) $user['id']);
        $this->audit($user, 'settings', 'delete', 'Xóa media giao diện', null, ['key' => $key]);
        $this->ok($settings);
    }

    private function loginMetrics(array $metrics): array
    {
        return [
            'total_households' => (int) ($metrics['total_households'] ?? 0),
            'total_citizens' => (int) ($metrics['total_citizens'] ?? 0),
            'party_member_count' => (int) ($metrics['party_member_count'] ?? 0),
            'male_count' => (int) ($metrics['male_count'] ?? 0),
            'female_count' => (int) ($metrics['female_count'] ?? 0),
            'away_count' => (int) ($metrics['away_count'] ?? 0),
        ];
    }
}
