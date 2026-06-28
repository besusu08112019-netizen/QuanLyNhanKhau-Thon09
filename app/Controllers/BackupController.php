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
        $this->ok($this->backups->page($this->query()));
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
        $user = $this->requirePermission('backup', 'restore');
        $result = $this->backups->restoreSql((string) $this->input('sql', ''), (int) $user['id']);
        $this->audit($user, 'backup', 'restore', 'Phục hồi dữ liệu từ SQL', null, $result, 'WARN');
        $this->ok($result);
    }
}
