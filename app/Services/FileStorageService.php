<?php

namespace App\Services;

final class FileStorageService
{
    public const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;

    public function normalizeEntityType(string $value): string
    {
        $type = preg_replace('/[^a-z_]/', '', strtolower(trim($value)));
        return match ($type) {
            'households' => 'household',
            'person', 'persons', 'citizens' => 'citizen',
            'setting' => 'settings',
            default => $type,
        };
    }

    public function validateEntity(string $entityType, int $entityId): void
    {
        if (!in_array($entityType, ['household', 'citizen', 'settings', 'house'], true)) {
            throw new \RuntimeException('Module upload không hợp lệ');
        }
        if ($entityType !== 'settings' && $entityId <= 0) {
            throw new \RuntimeException('Mã dữ liệu upload không hợp lệ');
        }
    }

    public function moduleForEntity(string $entityType): string
    {
        return $entityType;
    }

    public function permissionModule(string $entityType): string
    {
        return match ($entityType) {
            'citizen' => 'citizen',
            'household' => 'household',
            'house' => 'houses',
            default => 'settings',
        };
    }

    public function normalizeFileType(string $value): string
    {
        $fileType = preg_replace('/[^A-Z_]/', '', strtoupper($value ?: 'OTHER'));
        return in_array($fileType, ['PHOTO','DOCUMENT','SCAN','WORD','EXCEL','IMAGE','VIDEO','AUDIO','LOGO','BACKGROUND','OTHER'], true) ? $fileType : 'OTHER';
    }

    public function normalizeCategory(string $value, string $fileType, string $mime): string
    {
        $category = preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
        if ($category !== '') return $category;
        if (str_starts_with($mime, 'image/')) return 'images';
        if (str_starts_with($mime, 'video/')) return 'videos';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        return match ($fileType) {
            'PHOTO', 'IMAGE', 'LOGO', 'BACKGROUND' => 'images',
            'VIDEO' => 'videos',
            'AUDIO' => 'audio',
            default => 'documents',
        };
    }

    public function validateUploadedFile(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload không hợp lệ');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \RuntimeException('File upload rỗng');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new \RuntimeException('File tối đa ' . (int) (self::MAX_UPLOAD_BYTES / 1024 / 1024) . 'MB');
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Vui lòng chọn file');
        }
    }

    public function inspectUpload(array $file, string $fileType, string $entityType): array
    {
        $this->validateUploadedFile($file);
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $allowed = $this->allowedMimeTypes();
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Định dạng file chưa được hỗ trợ');
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === 'jpeg') $extension = 'jpg';
        if ($extension === '' || $extension !== $allowed[$mime]) {
            throw new \RuntimeException('File extension is not supported');
        }
        if (in_array($fileType, ['PHOTO','LOGO','BACKGROUND','IMAGE'], true) && !str_starts_with($mime, 'image/')) {
            throw new \RuntimeException('Loại file này phải là hình ảnh');
        }
        if ($fileType === 'VIDEO' && !str_starts_with($mime, 'video/')) {
            throw new \RuntimeException('Loại file này phải là video');
        }
        if ($fileType === 'AUDIO' && !str_starts_with($mime, 'audio/')) {
            throw new \RuntimeException('Loại file này phải là âm thanh');
        }
        if ($mime === 'image/svg+xml') {
            $this->validateSafeSvgUpload($file['tmp_name'], $entityType, $fileType);
        }
        return ['mime' => $mime, 'extension' => $allowed[$mime]];
    }

    public function storeUpload(array $file, string $entityType, string $category, string $extension): array
    {
        $folder = $this->entityFolder($entityType) . '/' . $this->categoryFolder($category) . '/' . date('Y/m');
        $root = 'uploads';
        $dir = BASE_PATH . '/' . $root . '/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Không tạo được thư mục upload');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('Không lưu được file upload');
        }
        return ['stored_name' => $stored, 'file_path' => $root . '/' . $folder . '/' . $stored];
    }

    public function safeFilePath(string $relative): ?string
    {
        $candidate = $this->resolveFilePath($relative);
        return $candidate['path'];
    }

    public function filePathDiagnostics(string $relative): array
    {
        return $this->resolveFilePath($relative);
    }

    private function resolveFilePath(string $relative): array
    {
        $input = trim($relative);
        $normalized = $this->normalizeStoredPath($input);
        $roots = $this->storageRoots();
        $candidates = $this->filePathCandidates($normalized);

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if (!$real || !is_file($real)) continue;
            foreach ($roots as $base) {
                $basePrefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if ($real === $base || str_starts_with($real, $basePrefix)) {
                    return ['path' => $real, 'input' => $input, 'normalized' => $normalized, 'checked' => $candidates];
                }
            }
        }

        $basename = basename($normalized);
        if ($basename !== '' && $basename !== '.') {
            foreach ($roots as $base) {
                $found = $this->findFileByBasename($base, $basename);
                if ($found) return ['path' => $found, 'input' => $input, 'normalized' => $normalized, 'checked' => $candidates];
            }
        }

        return ['path' => null, 'input' => $input, 'normalized' => $normalized, 'checked' => $candidates];
    }

    private function normalizeStoredPath(string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https?://#i', $path)) {
            $parts = parse_url($path);
            $path = (string) ($parts['path'] ?? '');
        }
        $path = rawurldecode($path);
        $path = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', BASE_PATH);
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        return ltrim($path, '/\\');
    }

    private function filePathCandidates(string $relative): array
    {
        $relative = ltrim($relative, '/\\');
        $variants = [$relative];
        foreach (['storage/', 'uploads/', 'public/storage/', 'public/uploads/'] as $prefix) {
            if (!str_starts_with($relative, $prefix)) $variants[] = $prefix . $relative;
        }
        $basename = basename($relative);
        if ($basename && $basename !== $relative) {
            $variants[] = 'storage/' . $basename;
            $variants[] = 'uploads/' . $basename;
        }
        $paths = [];
        foreach (array_unique(array_filter($variants)) as $variant) {
            $paths[] = BASE_PATH . '/' . $variant;
        }
        return $paths;
    }

    private function storageRoots(): array
    {
        $roots = [];
        foreach (['storage', 'uploads'] as $root) {
            $base = realpath(BASE_PATH . '/' . $root);
            if ($base) $roots[] = $base;
        }
        return $roots;
    }

    private function findFileByBasename(string $base, string $basename): ?string
    {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getFilename() !== $basename) continue;
                $real = $file->getRealPath();
                return $real !== false ? $real : null;
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    public function canPreview(string $mime): bool
    {
        return str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'video/')
            || str_starts_with($mime, 'audio/')
            || in_array($mime, ['application/pdf', 'text/csv', 'text/plain'], true);
    }

    private function allowedMimeTypes(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/csv' => 'csv',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
        ];
    }

    private function validateSafeSvgUpload(string $path, string $entityType, string $fileType): void
    {
        if ($entityType !== 'settings' || !in_array($fileType, ['LOGO','IMAGE'], true)) {
            throw new \RuntimeException('SVG chỉ được phép dùng cho logo hoặc hình cấu hình giao diện');
        }
        if (filesize($path) > 1024 * 1024) {
            throw new \RuntimeException('SVG file maximum size is 1MB');
        }
        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            throw new \RuntimeException('File SVG không hợp lệ');
        }
        $lower = strtolower($content);
        foreach (['<script','</script','javascript:','data:text/html','data:application/javascript',' onload=',' onerror=',' onclick=',' onmouseover=','<foreignobject','<iframe','<object','<embed'] as $pattern) {
            if (str_contains($lower, $pattern)) {
                throw new \RuntimeException('SVG có nội dung không an toàn');
            }
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
        if (!$xml || strtolower($xml->getName()) !== 'svg') {
            throw new \RuntimeException('File SVG không đúng định dạng');
        }
    }

    private function entityFolder(string $entityType): string
    {
        return match ($entityType) {
            'household' => 'households',
            'citizen' => 'persons',
            'settings' => 'settings',
            'house' => 'houses',
            'public_asset' => 'public-assets',
            'public_asset_inventory' => 'public-assets/inventory',
            default => 'documents',
        };
    }

    private function categoryFolder(string $category): string
    {
        $category = strtolower($category);
        if (str_contains($category, 'image') || str_contains($category, 'photo') || str_contains($category, 'portrait') || str_contains($category, 'cccd')) return 'images';
        if (str_contains($category, 'video')) return 'videos';
        if (str_contains($category, 'audio')) return 'audio';
        return 'documents';
    }
}
