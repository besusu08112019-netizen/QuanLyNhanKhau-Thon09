<?php

namespace App\Controllers;

use App\Core\BaseController;
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
        if ((int) $file['size'] > 5 * 1024 * 1024) $this->fail('File tối đa 5MB');
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
        if (!isset($allowed[$mime])) $this->fail('Chỉ cho phép JPG, PNG hoặc PDF');
        if (in_array($fileType, ['PHOTO','LOGO','BACKGROUND'], true) && !str_starts_with($mime, 'image/')) $this->fail('Loại file này phải là hình ảnh');
        $dir = BASE_PATH . '/uploads/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) $this->fail('Không tạo được thư mục upload');
        $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $path = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $path)) $this->fail('Không lưu được file upload');
        $relative = 'uploads/' . date('Y/m') . '/' . $stored;
        $row = $this->files->create(['module' => $module, 'entity_id' => $entityId, 'file_type' => $fileType, 'original_name' => basename((string) $file['name']), 'stored_name' => $stored, 'file_path' => $relative, 'mime_type' => $mime, 'file_size' => (int) $file['size']], (int) $user['id']);
        $this->audit($user, $module, 'upload', 'Upload file đính kèm', $entityId, ['file' => $row['id'], 'type' => $fileType]);
        $this->ok($row);
    }

    public function index(string $module, string $entityId): void
    {
        $this->requirePermission($module === 'citizen' ? 'citizen' : 'household', 'read');
        $this->ok($this->files->byEntity($module, (int) $entityId));
    }
}
