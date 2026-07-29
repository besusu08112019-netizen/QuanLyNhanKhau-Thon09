<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Services\DataQualityService;

final class DataQualityController extends BaseController
{
    private DataQualityService $service;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->service = new DataQualityService();
    }

    public function summary(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->service->summary($this->query()));
    }

    public function issues(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->service->issueList($this->query()));
    }

    public function issueDetail(): void
    {
        $this->requirePermission('report', 'read');
        $code = trim((string) $this->query('code', ''));
        if ($code === '') {
            $this->fail('Thieu ma loi du lieu', 422);
        }
        $this->ok($this->service->issueDetail($code, $this->query()));
    }
}
