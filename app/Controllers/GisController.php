<?php

namespace App\Controllers;

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
            $this->requirePermission('dashboard', 'read');
            $includeStats = $this->boolQuery('stats', true);
            $bounds = $this->boundsFromQuery();
            $items = $this->areasModel()->all($includeStats, $bounds);
            $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            $this->logException('GET /api/gis/areas', $e);
            $this->error('Không tải được GIS: ' . $e->getMessage(), 500);
        }
    }

    public function households(): void
    {
        try {
            $this->requirePermission('household', 'read');
            $items = $this->locationModel()->markers($this->householdFiltersFromQuery());
            $this->ok(['items' => $items]);
        } catch (Throwable $e) {
            $this->logException('GET /api/gis/households', $e);
            $this->error('Không tải được vị trí hộ: ' . $e->getMessage(), 500);
        }
    }

    public function search(): void
    {
        $query = trim((string) $this->query('q', ''));

        try {
            $this->requirePermission('household', 'read');
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
            $this->error('Không tìm kiếm được hộ trên bản đồ: ' . $e->getMessage(), 500);
        }
    }

    public function storeArea(): void
    {
        try {
            $this->requirePermission('settings', 'update');
            $payload = $this->jsonPayload();
            $area = $this->areasModel()->create($payload, $this->currentUserId());
            $this->locationModel()->recalculateAreaCodes();
            $this->writeLog('CREATE', 'gis_areas', (string) $area['id'], $area);
            $this->ok($area, 'Đã lưu khu vực');
        } catch (Throwable $e) {
            $this->logException('POST /api/gis/areas', $e, $this->jsonPayload(false));
            $this->error('Không lưu được khu vực: ' . $e->getMessage(), 400);
        }
    }

    public function updateArea(int $id): void
    {
        try {
            $this->requirePermission('settings', 'update');
            $payload = $this->jsonPayload();
            $area = $this->areasModel()->update($id, $payload);
            $this->locationModel()->recalculateAreaCodes();
            $this->writeLog('UPDATE', 'gis_areas', (string) $id, $area);
            $this->ok($area, 'Đã cập nhật khu vực');
        } catch (Throwable $e) {
            $this->logException('PUT /api/gis/areas/' . $id, $e, $this->jsonPayload(false));
            $this->error('Không cập nhật được khu vực: ' . $e->getMessage(), 400);
        }
    }

    public function deleteArea(int $id): void
    {
        try {
            $this->requirePermission('settings', 'delete');
            $area = $this->areasModel()->delete($id);
            $this->locationModel()->recalculateAreaCodes();
            $this->writeLog('DELETE', 'gis_areas', (string) $id, $area);
            $this->ok($area, 'Đã xóa khu vực');
        } catch (Throwable $e) {
            $this->logException('DELETE /api/gis/areas/' . $id, $e);
            $this->error('Không xóa được khu vực: ' . $e->getMessage(), 400);
        }
    }

    public function saveHouseholdLocation(int $id): void
    {
        try {
            $this->requirePermission('household', 'update');
            $payload = $this->jsonPayload();
            $item = $this->locationModel()->saveLocation($id, $payload, $this->currentUserId());
            $this->writeLog('UPDATE', 'household_location', (string) $id, $item);
            $this->ok($item, 'Đã lưu vị trí hộ');
        } catch (Throwable $e) {
            $this->logException('PUT /api/gis/households/' . $id . '/location', $e, $this->jsonPayload(false));
            $this->error('Không lưu được vị trí hộ: ' . $e->getMessage(), 400);
        }
    }

    public function clearHouseholdLocation(int $id): void
    {
        try {
            $this->requirePermission('household', 'update');
            $item = $this->locationModel()->clearLocation($id, $this->currentUserId());
            $this->writeLog('DELETE', 'household_location', (string) $id, $item);
            $this->ok($item, 'Đã xóa vị trí hộ');
        } catch (Throwable $e) {
            $this->logException('DELETE /api/gis/households/' . $id . '/location', $e);
            $this->error('Không xóa được vị trí hộ: ' . $e->getMessage(), 400);
        }
    }

    public function exportPdf(): void
    {
        $this->requirePermission('report', 'export');
        $areas = $this->areasModel()->all(true, null);
        $filename = 'ban-do-dia-ban-' . date('Ymd-His') . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Bản đồ địa bàn</title>';
        echo '<style>body{font-family:Arial,sans-serif;color:#0f172a}table{border-collapse:collapse;width:100%}th,td{border:1px solid #cbd5e1;padding:8px;text-align:left}th{background:#e2e8f0}.sw{display:inline-block;width:14px;height:14px;border-radius:4px;margin-right:6px}</style>';
        echo '</head><body><h1>Báo cáo bản đồ địa bàn</h1><p>Ngày xuất: ' . date('d/m/Y H:i') . '</p>';
        echo '<table><thead><tr><th>Khu vực</th><th>Mã</th><th>Màu</th><th>Diện tích (m²)</th><th>Số hộ</th><th>Nhân khẩu</th><th>Ghi chú</th></tr></thead><tbody>';
        foreach ($areas as $area) {
            echo '<tr><td>' . htmlspecialchars((string) $area['name']) . '</td><td>' . htmlspecialchars((string) $area['area_code']) . '</td><td><span class="sw" style="background:' . htmlspecialchars((string) $area['color']) . '"></span>' . htmlspecialchars((string) $area['color']) . '</td><td>' . number_format((float) $area['area_m2']) . '</td><td>' . (int) $area['household_count'] . '</td><td>' . (int) $area['citizen_count'] . '</td><td>' . htmlspecialchars((string) ($area['note'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></body></html>';
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
        return [
            'search' => $this->query('q'),
            'area_code' => $this->query('area_code'),
            'located' => $this->query('located'),
            'bounds' => $this->boundsFromQuery(),
        ];
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
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    private function writeLog(string $action, string $module, string $target, array $data = []): void
    {
        try {
            (new SystemLog())->record([
                'user_id' => $_SESSION['user']['id'] ?? null,
                'username' => $_SESSION['user']['username'] ?? null,
                'action' => $action,
                'module' => $module,
                'target_id' => $target,
                'description' => $module . ' ' . $action,
                'metadata' => $data,
            ]);
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
