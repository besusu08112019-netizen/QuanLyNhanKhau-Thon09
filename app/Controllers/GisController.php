<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\GisArea;
use App\Models\GisHouseholdLocation;
use App\Models\GisSearch;
use App\Models\SystemLog;
use Throwable;

class GisController extends BaseController
{
    private ?GisArea $areas = null;
    private ?GisHouseholdLocation $locations = null;

    private function areasModel(): GisArea
    {
        if ($this->areas === null) {
            $this->areas = new GisArea();
        }
        return $this->areas;
    }

    private function locationModel(): GisHouseholdLocation
    {
        if ($this->locations === null) {
            $this->locations = new GisHouseholdLocation();
        }
        return $this->locations;
    }

    public function areas(): void
    {
        try {
            $this->requirePermission('gis', 'read');
            $data = $this->areasModel()->all();
            $areas = $data['areas'] ?? [];
            $this->ok([
                'areas' => $areas,
                'items' => $areas,
                'summary' => $data['summary'] ?? [],
                'unassigned' => $data['unassigned'] ?? ['households' => 0],
            ]);
        } catch (Throwable $e) {
            $this->logException('GET /api/gis/areas', $e);
            $this->fail($this->safeExceptionMessage(json_decode('"Kh\u00f4ng t\u1ea3i \u0111\u01b0\u1ee3c d\u1eef li\u1ec7u GIS"', true), $e), 500);
        }
    }

    public function households(): void
    {
        try {
            $this->requirePermission('gis', 'read');
            $filters = $this->householdFiltersFromQuery();
            $items = (string) $this->query('light', '') === '1'
                ? $this->locationModel()->lightMarkers($filters)
                : $this->locationModel()->markers($filters);
            $this->ok($items);
        } catch (Throwable $e) {
            $this->logException('GET /api/gis/households', $e);
            $this->fail($this->safeExceptionMessage(json_decode('"Kh\u00f4ng t\u1ea3i \u0111\u01b0\u1ee3c v\u1ecb tr\u00ed h\u1ed9 tr\u00ean GIS"', true), $e), 500);
        }
    }

    public function householdDetail(int $id): void
    {
        try {
            $this->requirePermission('gis', 'read');
            $this->ok($this->locationModel()->detail($id));
        } catch (Throwable $e) {
            $this->logException('GET /api/gis/households/' . $id . '/detail', $e);
            $this->fail($this->safeExceptionMessage(json_decode('"Kh\u00f4ng t\u1ea3i \u0111\u01b0\u1ee3c chi ti\u1ebft h\u1ed9 tr\u00ean GIS"', true), $e), 404);
        }
    }

    public function search(): void
    {
        $query = trim((string) $this->query('q', ''));

        try {
            $this->requirePermission('gis', 'read');
            if (mb_strlen($query) < 2) {
                $this->ok(['items' => []]);
                return;
            }

            if (!class_exists(GisSearch::class) && defined('APP_ROOT')) {
                require_once APP_ROOT . '/app/Models/GisSearch.php';
            }

            $items = (new GisSearch())->households($query, 10);
            $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            $this->logException('GET /api/gis/search', $e);
            $this->fail($this->safeExceptionMessage(json_decode('"Kh\u00f4ng t\u00ecm ki\u1ebfm \u0111\u01b0\u1ee3c h\u1ed9 tr\u00ean b\u1ea3n \u0111\u1ed3"', true), $e), 500);
        }
    }

    public function storeArea(): void
    {
        $payload = $this->jsonPayload(false);
        try {
            $user = $this->requirePermission('gis', 'update');
            $area = $this->areasModel()->save($payload, (int) ($user['id'] ?? 0));
            $this->locationModel()->recalculateAreaCodes();
            $this->writeLog('CREATE', 'gis_areas', (string) ($area['id'] ?? ''), $area);
            $this->ok($area);
        } catch (Throwable $e) {
            $this->logException('POST /api/gis/areas', $e, $payload);
            $this->fail('Không lưu được khu vực: ' . $e->getMessage(), 400);
        }
    }

    public function updateArea(int $id): void
    {
        $payload = $this->jsonPayload(false);
        try {
            $user = $this->requirePermission('gis', 'update');
            $payload['id'] = $id;
            $area = $this->areasModel()->save($payload, (int) ($user['id'] ?? 0));
            $this->locationModel()->recalculateAreaCodes();
            $this->writeLog('UPDATE', 'gis_areas', (string) $id, $area);
            $this->ok($area);
        } catch (Throwable $e) {
            $this->logException('PUT /api/gis/areas/' . $id, $e, $payload);
            $this->fail('Không cập nhật được khu vực: ' . $e->getMessage(), 400);
        }
    }

    public function deleteArea(int $id): void
    {
        try {
            $user = $this->requirePermission('gis', 'delete');
            $this->areasModel()->delete($id, (int) ($user['id'] ?? 0));
            $this->locationModel()->recalculateAreaCodes();
            $area = ['id' => $id, 'deleted' => true];
            $this->writeLog('DELETE', 'gis_areas', (string) $id, $area);
            $this->ok($area);
        } catch (Throwable $e) {
            $this->logException('DELETE /api/gis/areas/' . $id, $e);
            $this->fail('Không xóa được khu vực: ' . $e->getMessage(), 400);
        }
    }

    public function saveHouseholdLocation(int $id): void
    {
        $payload = $this->jsonPayload(false);
        try {
            $this->requirePermission('gis', 'update');
            $item = $this->locationModel()->saveLocation($id, $payload, $this->currentUserId());
            $this->writeLog('UPDATE', 'household_location', (string) $id, $item);
            $this->ok($item);
        } catch (Throwable $e) {
            $this->logException('PUT /api/gis/households/' . $id . '/location', $e, $payload);
            $this->fail('Không lưu được vị trí hộ: ' . $e->getMessage(), 400);
        }
    }

    public function clearHouseholdLocation(int $id): void
    {
        try {
            $this->requirePermission('gis', 'update');
            $item = $this->locationModel()->clearLocation($id, $this->currentUserId());
            $this->writeLog('DELETE', 'household_location', (string) $id, $item ?? []);
            $this->ok($item ?? ['id' => $id, 'removed' => true]);
        } catch (Throwable $e) {
            $this->logException('DELETE /api/gis/households/' . $id . '/location', $e);
            $this->fail('Không xóa được vị trí hộ. Vui lòng thử lại hoặc kiểm tra quyền truy cập.', 400);
        }
    }

    public function exportPdf(): void
    {
        $this->requirePermission('gis', 'export');
        $data = $this->areasModel()->all();
        $areas = $data['areas'] ?? [];
        $filename = 'ban-do-dia-ban-' . date('Ymd-His') . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Bản đồ địa bàn</title>';
        echo '<style>body{font-family:Arial,sans-serif;color:#0f172a}table{border-collapse:collapse;width:100%}th,td{border:1px solid #cbd5e1;padding:8px;text-align:left}th{background:#e2e8f0}.sw{display:inline-block;width:14px;height:14px;border-radius:4px;margin-right:6px}</style>';
        echo '</head><body><h1>Báo cáo bản đồ địa bàn</h1><p>Ngày xuất: ' . date('d/m/Y H:i') . '</p>';
        echo '<table><thead><tr><th>Khu vực</th><th>Mã</th><th>Màu</th><th>Diện tích (m²)</th><th>Số hộ</th><th>Nhân khẩu</th><th>Ghi chú</th></tr></thead><tbody>';
        foreach ($areas as $area) {
            $stats = $area['stats'] ?? [];
            echo '<tr><td>' . htmlspecialchars((string) $area['name']) . '</td><td>' . htmlspecialchars((string) $area['area_code']) . '</td><td><span class="sw" style="background:' . htmlspecialchars((string) $area['color']) . '"></span>' . htmlspecialchars((string) $area['color']) . '</td><td>' . number_format((float) ($stats['area_m2'] ?? 0)) . '</td><td>' . (int) ($stats['households'] ?? 0) . '</td><td>' . (int) ($stats['citizens'] ?? 0) . '</td><td>' . htmlspecialchars((string) ($area['note'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    private function boundsFromQuery(): ?array
    {
        $south = $this->query('south');
        $west = $this->query('west');
        $north = $this->query('north');
        $east = $this->query('east');
        if ($south === null || $west === null || $north === null || $east === null) {
            return null;
        }
        return [
            'south' => (float) $south,
            'west' => (float) $west,
            'north' => (float) $north,
            'east' => (float) $east,
        ];
    }

    private function householdFiltersFromQuery(): array
    {
        $filters = [
            'search' => $this->query('q'),
            'area_code' => $this->query('area_code'),
            'located' => $this->query('located'),
        ];
        foreach (['party', 'children', 'elderly', 'poor', 'near_poor', 'labor', 'permanent', 'temporary'] as $key) {
            $filters[$key] = $this->query($key);
        }
        $bounds = $this->boundsFromQuery();
        if ($bounds !== null) {
            $filters += $bounds;
        }
        return $filters;
    }
    private function boolQuery(string $key, bool $default): bool
    {
        $value = $this->query($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return !in_array(strtolower((string) $value), ['0', 'false', 'no'], true);
    }

    private function jsonPayload(bool $throw = true): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
        if ($throw) {
            throw new \RuntimeException('Dữ liệu JSON không hợp lệ');
        }
        return [];
    }

    private function currentUserId(): int
    {
        try {
            return (int) ($this->user()['id'] ?? 0);
        } catch (Throwable $ignored) {
            return 0;
        }
    }

    private function writeLog(string $action, string $module, string $target, ?array $data = null): void
    {
        try {
            if (!class_exists(SystemLog::class) && defined('APP_ROOT') && is_file(APP_ROOT . '/app/Models/SystemLog.php')) {
                require_once APP_ROOT . '/app/Models/SystemLog.php';
            }
            if (class_exists(SystemLog::class)) {
                (new SystemLog())->record([
                    'user_id' => $this->currentUserId() ?: null,
                    'username' => null,
                    'action' => $action,
                    'module' => $module,
                    'target_id' => $target,
                    'description' => $module . ' ' . $action,
                    'metadata' => $data ?? [],
                ]);
            }
        } catch (Throwable $ignored) {
            // Log writing must not block GIS operations.
        }
    }

    private function logException(string $context, Throwable $e, array $request = []): void
    {
        error_log('[GIS] ' . $context . ' ' . json_encode([
            'request' => $request,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], JSON_UNESCAPED_UNICODE));
    }
}
