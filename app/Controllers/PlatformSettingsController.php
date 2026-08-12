<?php

namespace App\Controllers;

use App\Core\Authorization\ControlCenterAuthorizationException;
use App\Core\Authorization\ControlCenterAuthorizationFactory;
use App\Core\BaseController;
use App\Core\Response;
use App\Repositories\PlatformSettingsRepository;
use App\Services\ControlCenterAuditService;
use App\Services\PlatformBrandingService;
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

    public function uploadAsset(): void
    {
        $this->respond(fn(): array => $this->service->uploadAsset((string) ($_POST['asset_type'] ?? $this->input('asset_type', '')), $_FILES['file'] ?? []));
    }

    public function resetAsset(): void
    {
        $this->respond(fn(): array => $this->service->resetAsset((string) ($this->input('asset_type', $_POST['asset_type'] ?? ''))));
    }

    public function asset(string $type, string $file): void
    {
        try {
            $asset = (new PlatformBrandingService())->resolveAssetPath($type, $file);
            header('Content-Type: ' . $asset['mime']);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) $asset['mtime']) . ' GMT');
            readfile($asset['path']);
            exit;
        } catch (Throwable) {
            Response::error('KhÃ´ng tÃ¬m tháº¥y asset', 404);
        }
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
            Response::error('KhÃ´ng xá»­ lÃ½ Ä‘Æ°á»£c cáº¥u hÃ¬nh ná»n táº£ng', 500);
        }
    }
}
