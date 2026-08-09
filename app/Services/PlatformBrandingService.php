<?php

namespace App\Services;

use App\Repositories\PlatformSettingsRepository;
use InvalidArgumentException;
use RuntimeException;

final class PlatformBrandingService
{
    public const ASSETS = [
        'control_center_logo' => [
            'key' => 'branding.control_center_logo',
            'folder' => 'control-center-logo',
            'prefix' => 'platform-logo',
            'maxBytes' => 2097152,
            'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
        ],
        'favicon' => [
            'key' => 'branding.favicon',
            'folder' => 'favicon',
            'prefix' => 'platform-favicon',
            'maxBytes' => 1048576,
            'extensions' => ['png', 'ico'],
            'mimes' => ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'],
        ],
        'default_tenant_logo' => [
            'key' => 'branding.default_tenant_logo',
            'folder' => 'default-tenant-logo',
            'prefix' => 'tenant-logo',
            'maxBytes' => 2097152,
            'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
        ],
        'default_login_background' => [
            'key' => 'branding.default_login_background',
            'folder' => 'default-login-background',
            'prefix' => 'login-background',
            'maxBytes' => 5242880,
            'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'mimes' => ['image/png', 'image/jpeg', 'image/webp'],
        ],
    ];

    private const LEGACY_KEYS = [
        'control_center_logo' => 'identity.logo_url',
        'favicon' => 'identity.favicon_url',
        'default_tenant_logo' => 'identity.tenant_logo_url',
        'default_login_background' => 'identity.login_background_url',
    ];

    public function __construct(private ?PlatformSettingsRepository $repository = null)
    {
        $this->repository ??= new PlatformSettingsRepository();
    }

    public function publicBranding(): array
    {
        $branding = [];
        foreach (self::ASSETS as $type => $definition) {
            $stored = (string) $this->repository->value($definition['key'], '');
            if ($stored === '' && isset(self::LEGACY_KEYS[$type])) {
                $stored = (string) $this->repository->value(self::LEGACY_KEYS[$type], '');
            }
            $branding[$type] = [
                'type' => $type,
                'configured' => $stored !== '',
                'url' => $stored !== '' ? $this->publicUrl($type, $stored) : '',
                'stored' => $stored,
            ];
        }
        return $branding;
    }

    public function assetUrl(string $type): string
    {
        $asset = $this->publicBranding()[$type] ?? null;
        return is_array($asset) ? (string) ($asset['url'] ?? '') : '';
    }

    public function storeUploadedAsset(string $type, array $file): array
    {
        $definition = $this->definition($type);
        $inspection = $this->inspectImage($file, $definition);
        $dir = $this->assetRoot() . '/' . $definition['folder'];
        $this->ensureSafeDirectory($dir);
        $name = $definition['prefix'] . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $inspection['extension'];
        $target = $dir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new RuntimeException('Không lưu được file upload');
        }
        @chmod($target, 0644);
        return [
            'type' => $type,
            'stored' => $name,
            'url' => $this->publicUrl($type, $name),
            'mime' => $inspection['mime'],
            'extension' => $inspection['extension'],
            'size' => (int) $file['size'],
            'width' => $inspection['width'],
            'height' => $inspection['height'],
        ];
    }

    public function resolveAssetPath(string $type, string $file): array
    {
        $definition = $this->definition($type);
        $name = basename($file);
        if ($name !== $file || !preg_match('/^[a-z0-9-]+\.(png|jpg|jpeg|webp|ico)$/i', $name)) {
            throw new InvalidArgumentException('Asset không hợp lệ');
        }
        $path = $this->assetRoot() . '/' . $definition['folder'] . '/' . $name;
        $base = realpath($this->assetRoot());
        $real = realpath($path);
        if (!$base || !$real || strpos($real, $base) !== 0 || !is_file($real)) {
            throw new InvalidArgumentException('Không tìm thấy asset');
        }
        $mime = $this->mimeType($real);
        return ['path' => $real, 'mime' => $mime, 'mtime' => filemtime($real) ?: time()];
    }

    public function publicUrl(string $type, string $stored): string
    {
        if ($stored === '') return '';
        if (str_starts_with($stored, '/api/platform/assets/')) return $stored;
        if (str_starts_with($stored, '/')) return $stored;
        $path = $this->assetRoot() . '/' . $this->definition($type)['folder'] . '/' . basename($stored);
        $version = is_file($path) ? (string) filemtime($path) : (string) time();
        return '/api/platform/assets/' . rawurlencode($type) . '/' . rawurlencode(basename($stored)) . '?v=' . rawurlencode($version);
    }

    public function settingKey(string $type): string
    {
        return $this->definition($type)['key'];
    }

    public function definition(string $type): array
    {
        $type = strtolower(trim($type));
        if (!isset(self::ASSETS[$type])) {
            throw new InvalidArgumentException('Loại asset không hợp lệ');
        }
        return self::ASSETS[$type];
    }

    private function inspectImage(array $file, array $definition): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new InvalidArgumentException('Vui lòng chọn file ảnh hợp lệ');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > (int) $definition['maxBytes']) {
            throw new InvalidArgumentException('Dung lượng ảnh không hợp lệ');
        }
        $original = basename((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension === 'jpeg') $extension = 'jpg';
        if (!in_array($extension, $definition['extensions'], true)) {
            throw new InvalidArgumentException('Định dạng ảnh không được phép');
        }
        $blocked = ['php', 'phtml', 'phar', 'cgi', 'exe', 'js', 'html', 'htm', 'svg', 'sh', 'bat', 'cmd'];
        if (in_array($extension, $blocked, true)) {
            throw new InvalidArgumentException('Không cho phép upload file thực thi');
        }
        $path = (string) $file['tmp_name'];
        $mime = $this->mimeType($path);
        if (!in_array($mime, $definition['mimes'], true)) {
            throw new InvalidArgumentException('MIME ảnh không hợp lệ');
        }
        if ($extension === 'ico') {
            if (!in_array($mime, ['image/x-icon', 'image/vnd.microsoft.icon'], true)) {
                throw new InvalidArgumentException('Favicon ICO không hợp lệ');
            }
            return ['mime' => $mime, 'extension' => 'ico', 'width' => 0, 'height' => 0];
        }
        $info = @getimagesize($path);
        if (!$info || (int) ($info[0] ?? 0) < 1 || (int) ($info[1] ?? 0) < 1) {
            throw new InvalidArgumentException('File không phải ảnh hợp lệ');
        }
        return ['mime' => $mime, 'extension' => $extension, 'width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    private function mimeType(string $path): string
    {
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            if (is_string($mime) && $mime !== '') return $mime;
        }
        return mime_content_type($path) ?: 'application/octet-stream';
    }

    private function ensureSafeDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Không tạo được thư mục asset');
        }
        $root = $this->assetRoot();
        foreach ([$root, $dir] as $path) {
            $htaccess = $path . '/.htaccess';
            if (!is_file($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\nRemoveHandler .php .phtml .phar .cgi .pl .asp .aspx .jsp\nphp_flag engine off\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|asp|aspx|jsp|js|html|htm|svg)$\">\nRequire all denied\n</FilesMatch>\n");
            }
        }
    }

    private function assetRoot(): string
    {
        return rtrim((defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/platform-assets', '/\\');
    }
}
