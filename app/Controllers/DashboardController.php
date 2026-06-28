<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Dashboard;

final class DashboardController extends BaseController
{
    public function summary(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok((new Dashboard())->summary());
    }
}
