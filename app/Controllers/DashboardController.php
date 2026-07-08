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

    public function overview(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->overviewDashboard($this->query()));
    }

    public function households(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->householdDashboard($this->query()));
    }

    public function population(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->populationDashboard($this->query()));
    }

    public function business(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->businessDashboard($this->query()));
    }

    public function vehicles(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->vehicleDashboard($this->query()));
    }

    public function livestock(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->livestockDashboard($this->query()));
    }

    public function gis(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->gisDashboard($this->query()));
    }

    public function reports(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->ok($this->dashboard->reportsDashboard($this->query()));
    }
}
