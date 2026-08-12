<?php

namespace App\Controllers;

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Authorization\ControlCenterAuthorizationFactory;
use App\Core\BaseController;
use App\Core\Response;
use App\Services\ControlCenterAuditService;
use App\Services\TenantInstallerService;
use RuntimeException;
use Throwable;

final class TenantInstallerController extends BaseController
{
    private TenantInstallerService $service;

    public function __construct(\App\Core\Request $request)
    {
        parent::__construct($request);
        $this->service = new TenantInstallerService(new ControlCenterAuditService());
    }

    public function start(): void
    {
        $this->respond(function (): array {
            $actor = ControlCenterAuthorizationFactory::make($this->request)->authorize('control_center.units.create');
            return $this->service->start($this->input(), $actor);
        });
    }

    public function preflight(): void
    {
        $this->respond(function (): array {
            $actor = ControlCenterAuthorizationFactory::make($this->request)->authorize('control_center.units.create');
            return $this->service->preflight($this->input(), $actor);
        });
    }

    public function databaseCheck(): void
    {
        $this->respond(function (): array {
            $actor = ControlCenterAuthorizationFactory::make($this->request)->authorize('control_center.units.create');
            return $this->service->databaseCheck($this->input(), $actor);
        });
    }

    public function dryRun(): void
    {
        $this->respond(function (): array {
            $actor = ControlCenterAuthorizationFactory::make($this->request)->authorize('control_center.units.create');
            return $this->service->dryRun($this->input(), $actor);
        });
    }

    public function show(string $id): void
    {
        $this->respond(function () use ($id): array {
            ControlCenterAuthorizationFactory::make($this->request)->authorize('control_center.units.read');
            return $this->service->status((int) $id);
        });
    }

    public function retry(string $id): void
    {
        $this->respond(function () use ($id): array {
            $actor = ControlCenterAuthorizationFactory::make($this->request)->authorize('control_center.units.create');
            return $this->service->retry((int) $id, $actor);
        });
    }

    public function rollback(string $id): void
    {
        $this->respond(function () use ($id): array {
            $actor = ControlCenterAuthorizationFactory::make($this->request)->authorize('control_center.units.create');
            return $this->service->rollback((int) $id, $actor);
        });
    }

    private function respond(callable $callback): void
    {
        try {
            $this->ok($callback());
        } catch (ControlCenterAuthorizationException $e) {
            Response::error($e->getMessage(), $e->status());
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            Response::error('Khá»Ÿi táº¡o Ä‘Æ¡n vá»‹ tháº¥t báº¡i', 500, ['detail' => $e->getMessage()]);
        }
    }
}
