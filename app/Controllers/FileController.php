<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Response;
use App\Models\FileAttachment;

final class FileController extends BaseController
{
    private FileAttachment $files;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->files = new FileAttachment();
    }

    public function upload(): void
    {
        $module = preg_replace('/[^a-z_]/', '', (string) ($_POST['module'] ?? ''));
        $entityId = (int) ($_POST['entityId'] ?? 0);
        $fileType = strtoupper(preg_replace('/[^A-Z_]/', '', (string) ($_POST['fileType'] ?? 'OTHER')));
        if (!in_array($module, ['household','citizen','settings'], true)) $this->fail('Module upload không hợp lệ');
        if ($entityId <= 0 && $module !== 'settings') $this->fail('Mã dữ liệu upload không hợp lệ');
        $user = $this->requirePermission($module === 'citizen' ? 'citizen' : ($module === 'household' ? 'household' : 'settings'), 'update');
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) $this->fail('Vui lòng chọn file');

        $file = $_FILES['file'];
        if ((int) $file['size'] > 20 * 1024 * 1024) $this->fail('File tối đa 20MB');
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $allowed = $this->allowedMimeTypes();
        if (!isset($allowed[$mime])) $this->fail('Định dạng file chưa được hỗ trợ');
        if (in_array($fileType, ['PHOTO','LOGO','BACKGROUND','IMAGE'], true) && !str_starts_with($mime, 'image/')) $this->fail('Loại file này phải là hình ảnh');
        if ($mime === 'image/svg+xml') $this->validateSafeSvgUpload($file['tmp_name'], $module, $fileType);

        $folder = $this->moduleFolder($module) . '/' . date('Y/m');
        $dir = BASE_PATH . '/uploads/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) $this->fail('Không tạo được thư mục upload');
        $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $path = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $path)) $this->fail('Không lưu được file upload');

        $relative = 'uploads/' . $folder . '/' . $stored;
        $row = $this->files->create([
            'module' => $module,
            'entity_id' => $entityId,
            'file_type' => $fileType,
            'original_name' => basename((string) $file['name']),
            'stored_name' => $stored,
            'file_path' => $relative,
            'mime_type' => $mime,
            'file_size' => (int) $file['size'],
        ], (int) $user['id']);
        $this->audit($user, $module, 'upload', 'Upload file đính kèm', $entityId, ['file' => $row['id'], 'type' => $fileType]);
        $this->ok($row);
    }

    public function index(string $module, string $entityId): void
    {
        $this->requirePermission($module === 'citizen' ? 'citizen' : 'household', 'read');
        $this->ok($this->files->byEntity($module, (int) $entityId));
    }

    public function download(string $id): void
    {
        $file = $this->files->find((int) $id);
        if (!$file) $this->fail('Không tìm thấy file', 404);
        $this->requirePermission($file['module'] === 'citizen' ? 'citizen' : ($file['module'] === 'household' ? 'household' : 'settings'), 'read');
        $path = BASE_PATH . '/' . ltrim((string) $file['file_path'], '/');
        if (!is_file($path)) $this->fail('File không còn tồn tại trên máy chủ', 404);
        header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . rawurlencode((string) $file['original_name']) . '"');
        readfile($path);
        exit;
    }

    public function destroy(string $id): void
    {
        $file = $this->files->find((int) $id);
        if (!$file) $this->fail('Không tìm thấy file', 404);
        $user = $this->requirePermission($file['module'] === 'citizen' ? 'citizen' : ($file['module'] === 'household' ? 'household' : 'settings'), 'update');
        $this->files->softDelete((int) $id, (int) $user['id']);
        $this->audit($user, (string) $file['module'], 'delete_file', 'Xóa file đính kèm', $file['entity_id'] ?? null, ['file' => (int) $id, 'name' => $file['original_name'] ?? '']);
        $this->ok(['id' => (int) $id]);
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
        ];
    }

    private function validateSafeSvgUpload(string $path, string $module, string $fileType): void
    {
        if ($module !== 'settings' || !in_array($fileType, ['LOGO','IMAGE'], true)) {
            $this->fail('SVG chỉ được phép dùng cho logo hoặc hình cấu hình giao diện');
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            $this->fail('File SVG không hợp lệ');
        }

        $lower = strtolower($content);
        $blockedPatterns = [
            '<script',
            '</script',
            'javascript:',
            'data:text/html',
            'data:application/javascript',
            ' onload=',
            ' onerror=',
            ' onclick=',
            ' onmouseover=',
            '<foreignobject',
            '<iframe',
            '<object',
            '<embed',
        ];
        foreach ($blockedPatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                $this->fail('SVG có nội dung không an toàn');
            }
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if (!$xml || strtolower($xml->getName()) !== 'svg') {
            $this->fail('File SVG không đúng định dạng');
        }
    }

    private function moduleFolder(string $module): string
    {
        return match ($module) {
            'household' => 'households',
            'citizen' => 'citizens',
            'settings' => 'settings',
            default => 'other',
        };
    }
}
