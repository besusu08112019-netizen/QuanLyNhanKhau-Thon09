<?php

namespace App\Controllers;

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Authorization\ControlCenterAuthorizationFactory;
use App\Core\BaseController;
use App\Core\Response;
use App\Repositories\PlatformSettingsRepository;
use App\Services\ControlCenterAuditService;
use App\Services\PlatformSettingsService;
use InvalidArgumentException;
use Throwable;

final class PlatformSettingsController extends BaseController
{
    private PlatformSettingsService $service;

    public function __construct(\App\Core\Request $request)
    {
        parent::__construct($request);
        $this->service = new PlatformSettingsService(
            new PlatformSettingsRepository(),
            ControlCenterAuthorizationFactory::make($request),
            new ControlCenterAuditService()
        );
    }

    public function show(): void
    {
        $this->respond(fn(): array => $this->service->show());
    }

    public function update(): void
    {
        $this->respond(fn(): array => $this->service->update($this->input()));
    }

    public function updateSecret(): void
    {
        $this->respond(fn(): array => $this->service->updateSecret($this->input()));
    }

    public function checkRegistry(): void
    {
        $this->respond(fn(): array => $this->service->checkRegistry());
    }

    public function checkBackup(): void
    {
        $this->respond(fn(): array => $this->service->checkBackup());
    }

    public function testEmail(): void
    {
        $this->respond(fn(): array => $this->service->testEmail());
    }

    public function maintenance(): void
    {
        $this->respond(fn(): array => $this->service->setMaintenance($this->input()));
    }

    private function respond(callable $callback): void
    {
        try {
            $this->ok($callback());
        } catch (ControlCenterAuthorizationException $e) {
            Response::error($e->getMessage(), $e->status());
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[PLATFORM_SETTINGS_API_ERROR] ' . $e->getMessage());
            Response::error('Không xử lý được cấu hình nền tảng', 500);
        }
    }
}
