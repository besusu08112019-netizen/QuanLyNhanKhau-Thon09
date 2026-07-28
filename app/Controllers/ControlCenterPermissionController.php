<?php

namespace App\Controllers;

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Authorization\ControlCenterAuthorizationFactory;
use App\Core\BaseController;
use App\Core\Response;
use App\Repositories\ControlCenterPermissionRepository;
use App\Services\ControlCenterAuditService;
use App\Services\ControlCenterPermissionService;
use InvalidArgumentException;

final class ControlCenterPermissionController extends BaseController
{
    private ControlCenterPermissionService $service;

    public function __construct(\App\Core\Request $request)
    {
        parent::__construct($request);
        $this->service = new ControlCenterPermissionService(
            new ControlCenterPermissionRepository(),
            ControlCenterAuthorizationFactory::make($request),
            new ControlCenterAuditService()
        );
    }

    public function index(): void
    {
        $this->respond(fn(): array => $this->service->matrix());
    }

    public function update(): void
    {
        $this->respond(fn(): array => $this->service->update($this->input()));
    }

    public function reset(): void
    {
        $this->respond(fn(): array => $this->service->reset($this->input()));
    }

    private function respond(callable $callback): void
    {
        try {
            $this->ok($callback());
        } catch (ControlCenterAuthorizationException $e) {
            Response::error($e->getMessage(), $e->status());
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        }
    }
}
