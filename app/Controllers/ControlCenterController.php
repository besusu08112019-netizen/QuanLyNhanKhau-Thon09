<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\PortalContext;
use App\Core\Response;
use App\Services\ControlCenterService;

final class ControlCenterController extends BaseController
{
    private ControlCenterService $service;

    public function __construct(\App\Core\Request $request)
    {
        parent::__construct($request);
        $this->service = new ControlCenterService();
    }

    public function status(): void
    {
        $this->respond($this->service->status());
    }

    public function dashboard(): void
    {
        $this->respond($this->service->dashboard());
    }

    public function units(): void
    {
        $this->respond(['items' => $this->service->units()]);
    }

    public function accounts(): void
    {
        $this->respond($this->service->accounts());
    }

    public function monitoring(): void
    {
        $this->respond($this->service->monitoring());
    }

    public function auditLogs(): void
    {
        $this->respond($this->service->audit($this->query()));
    }

    private function respond(array $data): void
    {
        Response::ok([
            'portal' => PortalContext::type(),
            'host' => PortalContext::host(),
        ] + $data);
    }
}
