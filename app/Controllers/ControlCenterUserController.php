<?php

namespace App\Controllers;

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Authorization\ControlCenterAuthorizationFactory;
use App\Core\BaseController;
use App\Core\Response;
use App\Repositories\ControlCenterUserRepository;
use App\Services\ControlCenterAuditService;
use App\Services\ControlCenterUserService;
use InvalidArgumentException;
use RuntimeException;

final class ControlCenterUserController extends BaseController
{
    private ControlCenterUserService $service;

    public function __construct(\App\Core\Request $request)
    {
        parent::__construct($request);
        $this->service = new ControlCenterUserService(
            new ControlCenterUserRepository(),
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

    public function deactivate(string $id): void
    {
        $this->respond(fn(): array => $this->service->deactivate((int) $id));
    }

    public function activate(string $id): void
    {
        $this->respond(fn(): array => $this->service->activate((int) $id));
    }

    public function resetPassword(string $id): void
    {
        $this->respond(fn(): array => $this->service->resetPassword((int) $id, $this->input()));
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
