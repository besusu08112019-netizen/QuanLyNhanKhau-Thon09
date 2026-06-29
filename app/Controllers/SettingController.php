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

        $type = strtolower((string) ($_POST['type'] ?? $this->input('type', 'ui')));
        $folderMap = ['logo' => 'logo', 'background' => 'background', 'news' => 'news', 'gallery' => 'gallery', 'intro' => 'gallery', 'ui' => 'gallery'];
        $folder = $folderMap[$type] ?? 'gallery';

        $original = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $allowedExtensions = ['png','jpg','svg','webp'];
        $allowedMimes = ['image/png','image/jpeg','image/svg+xml','image/webp'];
        if (!in_array($extension, $allowedExtensions, true) || (!in_array($mime, $allowedMimes, true) && $extension !== 'svg')) {
            $this->fail('Chỉ cho phép PNG, JPG, SVG hoặc WebP');
        }
        if ($extension === 'svg' && $this->svgContainsUnsafeContent($file['tmp_name'])) {
            $this->fail('SVG có nội dung không an toàn');
        }

        $datePath = date('Y/m');
        $originalDir = BASE_PATH . '/uploads/' . $folder . '/original/' . $datePath;
        $this->ensureUploadDir($originalDir);
        $basename = bin2hex(random_bytes(16));
        $stored = $basename . '.' . $extension;
        $path = $originalDir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $path)) $this->fail('Không lưu được file upload');

        $originalRelative = 'uploads/' . $folder . '/original/' . $datePath . '/' . $stored;
        $displayRelative = $originalRelative;
        if ($type === 'logo' && $extension !== 'svg') {
            $thumbDir = BASE_PATH . '/uploads/logo/thumb/' . $datePath;
            $this->ensureUploadDir($thumbDir);
            $thumbPath = $thumbDir . '/' . $basename . '.png';
            $this->createLogoThumbnail($path, $thumbPath, $extension);
            $displayRelative = 'uploads/logo/thumb/' . $datePath . '/' . $basename . '.png';
        }

        $this->audit($user, 'settings', 'upload', 'Upload media giao diện', null, ['file' => $displayRelative, 'original' => $originalRelative, 'type' => $type, 'mime' => $mime, 'size' => (int) $file['size']]);
        $this->ok([
            'url' => $this->versionedUrl($displayRelative),
            'originalUrl' => $this->versionedUrl($originalRelative),
            'name' => basename($original),
            'mime' => $mime,
            'size' => (int) $file['size'],
            'type' => $type,
        ]);
    }

    public function deleteMedia(): void
    {
        $user = $this->requirePermission('settings', 'update');
        $key = (string) $this->input('key', '');
        if (!in_array($key, ['logoUrl','backgroundUrl','backgroundImages','introImageUrl'], true)) $this->fail('Loại media không hợp lệ');
        $settings = $this->settings->updateMany([$key => ''], (int) $user['id']);
        $this->audit($user, 'settings', 'delete', 'Xóa media giao diện', null, ['key' => $key]);
        $this->ok($settings);
    }

    private function ensureUploadDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) $this->fail('Không tạo được thư mục upload');
    }

    private function createLogoThumbnail(string $source, string $target, string $extension): void
    {
        if (!extension_loaded('gd')) $this->fail('Máy chủ chưa bật GD Library để xử lý logo');
        [$width, $height] = getimagesize($source) ?: [0, 0];
        if ($width < 1 || $height < 1) $this->fail('File ảnh logo không hợp lệ');
        $image = match ($extension) {
            'png' => imagecreatefrompng($source),
            'jpg' => imagecreatefromjpeg($source),
            'webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($source) : false,
            default => false,
        };
        if (!$image) $this->fail('Không xử lý được định dạng logo này');

        $size = 256;
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);

        $scale = min($size / $width, $size / $height, 1);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $dstX = (int) floor(($size - $newWidth) / 2);
        $dstY = (int) floor(($size - $newHeight) / 2);
        imagecopyresampled($canvas, $image, $dstX, $dstY, 0, 0, $newWidth, $newHeight, $width, $height);
        if (!imagepng($canvas, $target, 3)) $this->fail('Không tạo được thumbnail logo');
        imagedestroy($image);
        imagedestroy($canvas);
    }

    private function svgContainsUnsafeContent(string $path): bool
    {
        $content = strtolower((string) file_get_contents($path));
        return str_contains($content, '<script') || str_contains($content, 'javascript:') || preg_match('/\son[a-z]+\s*=/', $content) === 1;
    }

    private function versionedUrl(string $relative): string
    {
        $path = BASE_PATH . '/' . ltrim($relative, '/');
        return $relative . (is_file($path) ? '?v=' . filemtime($path) : '?v=' . time());
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
