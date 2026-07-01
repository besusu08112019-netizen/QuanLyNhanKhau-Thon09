<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Dashboard;
use App\Models\SystemSetting;

final class SettingController extends BaseController
{
    private ?SystemSetting $settings = null;

    public function __construct($request)
    {
        parent::__construct($request);
    }

    public function index(): void
    {
        $this->requirePermission('settings', 'read');
        $this->ok($this->settings()->all());
    }

    public function publicLoginConfig(): void
    {
        try {
            $dashboard = new Dashboard();
            $metrics = $dashboard->metrics();
            $settings = $this->settings()->all();
        } catch (\Throwable $e) {
            $this->logPublicConfigFailure($e);
            $metrics = [];
            $settings = $this->defaultPublicSettings();
        }

        $this->ok([
            'settings' => array_replace($this->defaultPublicSettings(), is_array($settings) ? $settings : []),
            'metrics' => $this->loginMetrics(is_array($metrics) ? $metrics : []),
            'generatedAt' => date('c'),
        ]);
    }

    public function update(): void
    {
        $user = $this->requirePermission('settings', 'update');
        $settings = $this->settings()->updateMany($this->input(), (int) $user['id']);
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
        $settings = $this->settings()->updateMany([$key => ''], (int) $user['id']);
        $this->audit($user, 'settings', 'delete', 'Xóa media giao diện', null, ['key' => $key]);
        $this->ok($settings);
    }


    public function media(string $folder, string $kind, string $year, string $month, string $file): void
    {
        if (!in_array($folder, ['logo','background','news','gallery'], true)) $this->fail('Media không hợp lệ', 404);
        if (!in_array($kind, ['original','thumb'], true)) $this->fail('Media không hợp lệ', 404);
        if (!preg_match('/^\d{4}$/', $year) || !preg_match('/^\d{2}$/', $month)) $this->fail('Media không hợp lệ', 404);
        $name = basename($file);
        if ($name !== $file || !preg_match('/^[a-f0-9]{32}\.(png|jpg|jpeg|svg|webp)$/i', $name)) $this->fail('Media không hợp lệ', 404);
        $path = BASE_PATH . '/uploads/' . $folder . '/' . $kind . '/' . $year . '/' . $month . '/' . $name;
        $base = realpath(BASE_PATH . '/uploads');
        $real = realpath($path);
        if (!$base || !$real || strpos($real, $base) !== 0 || !is_file($real)) $this->fail('Không tìm thấy media', 404);
        $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
        header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }

    private function settings(): SystemSetting
    {
        return $this->settings ??= new SystemSetting();
    }

    private function defaultPublicSettings(): array
    {
        return [
            'logoUrl' => null,
            'backgroundUrl' => null,
            'backgroundImages' => [],
            'systemName' => 'Hệ thống Quản lý Hành chính',
            'hamletName' => 'Thôn 09',
            'communeName' => 'Xã Hồng Phong',
            'slogan' => 'Vì Nhân dân phục vụ',
            'version' => 'v2.0',
            'copyright' => '© Thôn 09 - Xã Hồng Phong',
            'introTitle' => 'Giới thiệu Thôn 09 - Xã Hồng Phong',
            'introContent' => '',
            'introImageUrl' => null,
            'contactAddress' => '',
            'contactPhone' => '',
            'contactEmail' => '',
            'contactWebsite' => '',
        ];
    }

    private function logPublicConfigFailure(\Throwable $e): void
    {
        error_log('[PUBLIC_LOGIN_CONFIG_ERROR] ' . json_encode([
            'time' => date('c'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
        $image = false;
        if ($extension === 'png') $image = imagecreatefrompng($source);
        elseif ($extension === 'jpg') $image = imagecreatefromjpeg($source);
        elseif ($extension === 'webp' && function_exists('imagecreatefromwebp')) $image = imagecreatefromwebp($source);
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
        return strpos($content, '<script') !== false || strpos($content, 'javascript:') !== false || preg_match('/\son[a-z]+\s*=/', $content) === 1;
    }

    private function versionedUrl(string $relative): string
    {
        $path = BASE_PATH . '/' . ltrim($relative, '/');
        $version = is_file($path) ? filemtime($path) : time();
        $parts = explode('/', trim($relative, '/'));
        if (count($parts) === 6 && $parts[0] === 'uploads') {
            return '/api/media/' . rawurlencode($parts[1]) . '/' . rawurlencode($parts[2]) . '/' . rawurlencode($parts[3]) . '/' . rawurlencode($parts[4]) . '/' . rawurlencode($parts[5]) . '?v=' . $version;
        }
        return $relative . '?v=' . $version;
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
