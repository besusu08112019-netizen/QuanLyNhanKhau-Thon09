<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Dashboard;

final class DashboardController extends BaseController
{
    private Dashboard $dashboard;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->dashboard = new Dashboard();
    }

    public function summary(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->summary($this->query()));
    }

    public function search(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->quickSearch($this->query()));
    }
    public function populationChart(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->populationChart($this->query()));
    }

    public function householdChart(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->householdChart($this->query()));
    }

    public function ageChart(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->ageChart($this->query()));
    }
}
