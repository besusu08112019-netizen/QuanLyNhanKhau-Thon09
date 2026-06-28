<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\AuditLog;

final class LogController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('logs', 'read');
        $this->ok((new AuditLog())->page($this->query()));
    }
}
