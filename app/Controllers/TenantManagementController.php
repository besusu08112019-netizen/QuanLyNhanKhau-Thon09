<?php

namespace App\Controllers;

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Authorization\ControlCenterAuthorizationFactory;
use App\Core\BaseController;
use App\Core\Response;
use App\Repositories\TenantRegistryRepository;
use App\Services\ControlCenterAuditService;
use App\Services\TenantManagementService;
use InvalidArgumentException;
use RuntimeException;

final class TenantManagementController extends BaseController
{
    private TenantManagementService $service;

    public function __construct(\App\Core\Request $request)
    {
        parent::__construct($request);
        $this->service = new TenantManagementService(
            new TenantRegistryRepository(),
            ControlCenterAuthorizationFactory::make($request),
            new ControlCenterAuditService()
        );
    }

    public function index(): void
    {
        $this->respond(fn(): array => $this->service->list($this->query()));
    }

    public function show(string $id): void
    {
        $this->respond(fn(): array => $this->service->find((int) $id));
    }

    public function store(): void
    {
        $this->respond(fn(): array => $this->service->create($this->input()));
    }

    public function update(string $id): void
    {
        $this->respond(fn(): array => $this->service->update((int) $id, $this->input()));
    }

    public function lock(string $id): void
    {
        $this->respond(fn(): array => $this->service->lock((int) $id, $this->input()));
    }

    public function unlock(string $id): void
    {
        $this->respond(fn(): array => $this->service->unlock((int) $id, $this->input()));
    }

    public function destroy(string $id): void
    {
        $this->respond(fn(): array => $this->service->softDelete((int) $id, $this->input()));
    }

    public function activity(string $id): void
    {
        $this->respond(fn(): array => $this->service->activity((int) $id, $this->query()));
    }

    private function respond(callable $callback): void
    {
        try {
            $this->ok($callback());
        } catch (ControlCenterAuthorizationException $e) {
            Response::error($e->getMessage(), $e->status());
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
