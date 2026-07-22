<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\SystemInsight;

final class InsightController extends BaseController
{
    private SystemInsight $insights;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->insights = new SystemInsight();
    }

    public function search(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requirePermission('household', 'read');
        $this->requirePermission('citizen', 'read');
        $q = trim((string) $this->query('q', $this->query('search', '')));
        $limit = (int) $this->query('limit', 20);
        $this->ok($this->insights->globalSearch($q, $limit));
    }

    public function alerts(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requirePermission('household', 'read');
        $this->requirePermission('citizen', 'read');
        $this->ok($this->insights->smartAlerts());
    }
}
