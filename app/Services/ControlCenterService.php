<?php

namespace App\Services;

use App\Core\RuntimePaths;
use App\Repositories\ControlCenterRepository;
use Throwable;

final class ControlCenterService
{
    public function __construct(private ?ControlCenterRepository $repository = null)
    {
        $this->repository ??= new ControlCenterRepository();
    }

    public function dashboard(): array
    {
        return $this->withFallback(
            fn(): array => $this->repository->dashboardMetrics(),
            [
                'totalUnits' => 0,
                'totalHouseholds' => 0,
                'totalCitizens' => 0,
                'totalChildren' => 0,
                'totalElderly' => 0,
                'totalWorkers' => 0,
                'totalPartyMembers' => 0,
                'healthInsuranceRate' => 0.0,
            ],
            'dashboard'
        );
    }

    public function units(): array
    {
        return $this->withFallback(fn(): array => $this->repository->units(), [], 'units');
    }

    public function accounts(): array
    {
        return [
            'roles' => $this->withFallback(fn(): array => $this->repository->accountRoleSummary(), $this->emptyRoles(), 'accounts'),
            'permissionDetailEnabled' => false,
        ];
    }

    public function monitoring(): array
    {
        $database = $this->withFallback(
            fn(): array => $this->repository->databaseHealth(),
            ['ok' => false, 'error' => ['message' => 'Database diagnostics unavailable']],
            'database_health'
        );

        $storagePath = BASE_PATH . '/storage/' . RuntimePaths::tenantKey();
        $logsPath = $storagePath . '/logs';

        return [
            'version' => defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1',
            'runtime' => [
                'phpVersion' => PHP_VERSION,
                'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
                'memoryLimit' => ini_get('memory_limit') ?: '',
                'timezone' => date_default_timezone_get(),
            ],
            'database' => [
                'ok' => (bool) ($database['ok'] ?? false),
                'database' => $database['attempts'][0]['database'] ?? ($database['config']['database'] ?? null),
                'message' => ($database['ok'] ?? false) ? 'Connected' : (string) ($database['error']['message'] ?? 'Unavailable'),
            ],
            'storage' => [
                'path' => $storagePath,
                'writable' => is_dir($storagePath) && is_writable($storagePath),
                'logsWritable' => is_dir($logsPath) && is_writable($logsPath),
                'freeBytes' => disk_free_space(BASE_PATH) ?: 0,
                'totalBytes' => disk_total_space(BASE_PATH) ?: 0,
            ],
            'healthCheck' => [
                'status' => ($database['ok'] ?? false) ? 'OK' : 'DEGRADED',
                'checkedAt' => date('c'),
            ],
        ];
    }

    public function status(): array
    {
        return [
            'status' => 'ready',
            'phase' => 'phase2',
        ];
    }

    private function emptyRoles(): array
    {
        return array_map(static fn(string $code): array => [
            'code' => $code,
            'name' => str_replace('_', ' ', ucwords(strtolower($code), '_')),
            'users' => 0,
            'status' => 'ACTIVE',
        ], ['SYSTEM_ADMIN', 'COMMUNE_ADMIN', 'VILLAGE_ADMIN', 'STAFF', 'VIEWER']);
    }

    private function withFallback(callable $callback, array $fallback, string $context): array
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            error_log('[CONTROL_CENTER_SERVICE_FALLBACK] ' . json_encode([
                'context' => $context,
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $fallback;
        }
    }
}
