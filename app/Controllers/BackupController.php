<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Backup;

final class BackupController extends BaseController
{
    private Backup $backups;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->backups = new Backup();
    }

    public function index(): void
    {
        $this->requirePermission('backup', 'read');
        $this->ok($this->backups->paginate($this->query()));
    }

    public function create(): void
    {
        $user = $this->requirePermission('backup', 'export');
        $backup = $this->backups->createSqlDump((int) $user['id']);
        $this->audit($user, 'backup', 'export', 'Tạo bản sao lưu SQL', null, ['fileName' => $backup['fileName'], 'size' => $backup['size'], 'checksum' => $backup['checksum']]);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $backup['fileName'] . '"');
        echo $backup['content'];
        exit;
    }

    public function restore(): void
    {
        $user = $this->requireSuperAdmin('backup', 'restore');
        $result = $this->backups->restoreSql($this->restoreSqlContent(), (int) $user['id']);
        $this->audit($user, 'backup', 'restore', 'Phục hồi dữ liệu từ SQL', null, $result, 'WARN');
        $this->ok($result);
    }

    private function restoreSqlContent(): string
    {
        if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            if (($_FILES['file']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new \RuntimeException('Invalid restore file');
            if ((int) ($_FILES['file']['size'] ?? 0) <= 0 || (int) ($_FILES['file']['size'] ?? 0) > 20 * 1024 * 1024) throw new \RuntimeException('Restore file size is invalid');
            $name = strtolower((string) $_FILES['file']['name']);
            if (!str_ends_with($name, '.sql')) throw new \RuntimeException('Only .sql restore files are supported');
            $content = file_get_contents($_FILES['file']['tmp_name']);
            return is_string($content) ? $content : '';
        }
        return (string) $this->input('sql', '');
    }
}
