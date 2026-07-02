<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\GisArea;
use App\Models\GisHouseholdLocation;

final class GisController extends BaseController
{
    private ?GisArea $areas = null;
    private ?GisHouseholdLocation $locations = null;

    public function __construct($request)
    {
        parent::__construct($request);
    }

    public function areas(): void
    {
        try {
            $this->requirePermission('household', 'read');
        } catch (\Throwable $e) {
            $this->logApiFailure('GET /api/gis/areas auth', [], $e);
            $this->fail('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.', 401);
        }

        try {
            $this->ok($this->areasModel()->all());
        } catch (\Throwable $e) {
            $this->logApiFailure('GET /api/gis/areas', [], $e);
            $this->ok($this->emptyAreasResponse($e));
        }
    }

    public function households(): void
    {
        try {
            $this->requirePermission('household', 'read');
            $this->ok($this->locationModel()->markers($_GET));
        } catch (\Throwable $e) {
            $this->logApiFailure('GET /api/gis/households', $_GET, $e);
            throw $e;
        }
    }

    public function storeArea(): void
    {
        $input = $this->input();
        try {
            $user = $this->requirePermission('household', 'update');
            $area = $this->areasModel()->save($input, (int) $user['id']);
            $this->locationModel()->recalculateAreaCodes();
            $this->audit($user, 'gis', 'save_polygon', 'Lưu ranh giới khu vực bản đồ', $area['id'] ?? null, ['area_code' => $area['area_code'] ?? null]);
            $this->ok($area);
        } catch (\Throwable $e) {
            $this->logApiFailure('POST /api/gis/areas', is_array($input) ? $input : [], $e);
            throw $e;
        }
    }

    public function updateArea(string $id): void
    {
        $input = $this->input();
        if (is_array($input)) $input['id'] = (int) $id;
        try {
            $user = $this->requirePermission('household', 'update');
            $area = $this->areasModel()->save(is_array($input) ? $input : ['id' => (int) $id], (int) $user['id']);
            $this->locationModel()->recalculateAreaCodes();
            $this->audit($user, 'gis', 'update_polygon', 'Cập nhật ranh giới khu vực bản đồ', $area['id'] ?? $id, ['area_code' => $area['area_code'] ?? null]);
            $this->ok($area);
        } catch (\Throwable $e) {
            $this->logApiFailure('PUT /api/gis/areas/' . $id, is_array($input) ? $input : [], $e);
            throw $e;
        }
    }

    public function deleteArea(string $id): void
    {
        try {
            $user = $this->requirePermission('household', 'update');
            $this->areasModel()->delete((int) $id, (int) $user['id']);
            $this->locationModel()->recalculateAreaCodes();
            $this->audit($user, 'gis', 'delete_polygon', 'Xóa ranh giới khu vực bản đồ', $id);
            $this->ok(['id' => (int) $id]);
        } catch (\Throwable $e) {
            $this->logApiFailure('DELETE /api/gis/areas/' . $id, ['id' => $id], $e);
            throw $e;
        }
    }

    public function saveHouseholdLocation(string $id): void
    {
        $input = $this->input();
        try {
            $user = $this->requirePermission('household', 'update');
            $marker = $this->locationModel()->saveLocation((int) $id, is_array($input) ? $input : [], (int) $user['id']);
            $this->audit($user, 'gis', 'save_household_location', 'Cập nhật vị trí hộ gia đình trên bản đồ', $id, ['area_code' => $marker['area_code'] ?? null]);
            $this->ok($marker);
        } catch (\Throwable $e) {
            $this->logApiFailure('PUT /api/gis/households/' . $id . '/location', is_array($input) ? $input : [], $e);
            throw $e;
        }
    }

    public function clearHouseholdLocation(string $id): void
    {
        try {
            $user = $this->requirePermission('household', 'update');
            $this->locationModel()->clearLocation((int) $id, (int) $user['id']);
            $this->audit($user, 'gis', 'clear_household_location', 'Xóa vị trí hộ gia đình trên bản đồ', $id);
            $this->ok(['id' => (int) $id]);
        } catch (\Throwable $e) {
            $this->logApiFailure('DELETE /api/gis/households/' . $id . '/location', ['id' => $id], $e);
            throw $e;
        }
    }

    public function exportPdf(): void
    {
        try {
            $this->requirePermission('household', 'read');
            $rows = $this->areasModel()->pdfRows();
            $pdfRows = [];
            foreach ($rows as $index => $row) {
                $pdfRows[] = [(string) ($index + 1), (string) $row['name'], (string) $row['area_code'], (string) ((int) $row['households']), (string) ((int) $row['citizens']), (string) ((int) $row['temporary']), (string) ((int) $row['away'])];
            }
            $pdf = new SimplePdf();
            $pdf->addTitle('BAN DO DIA BAN - THONG KE THEO KHU VUC');
            $pdf->addMeta('He thong Quan ly Hanh chinh Thon 09 - Xa Hong Phong');
            $pdf->addMeta('Ngay xuat: ' . date('d/m/Y H:i'));
            $pdf->addTable(['STT','Khu vuc','Ma KV','So ho','Nhan khau','Tam tru','Tam vang'], $pdfRows ?: [['','Chua co du lieu ranh gioi khu vuc','','','','','']]);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="ban_do_dia_ban.pdf"');
            echo $pdf->output();
        } catch (\Throwable $e) {
            $this->logApiFailure('GET /api/gis/export-pdf', [], $e);
            throw $e;
        }
    }

    private function areasModel(): GisArea
    {
        return $this->areas ??= new GisArea();
    }

    private function locationModel(): GisHouseholdLocation
    {
        return $this->locations ??= new GisHouseholdLocation();
    }

    private function emptyAreasResponse(\Throwable $e): array
    {
        return [
            'areas' => [],
            'unassigned' => ['households' => 0],
            'summary' => ['areas' => 0, 'households' => 0, 'citizens' => 0, 'located' => 0, 'unlocated' => 0, 'poor_households' => 0, 'near_poor_households' => 0, 'temporary' => 0, 'away' => 0, 'area_m2' => 0, 'density' => 0],
            'warning' => 'Không tải được dữ liệu GIS. Chi tiết đã được ghi log.',
        ];
    }

    private function logApiFailure(string $endpoint, array $request, \Throwable $e): void
    {
        $safeRequest = $request;
        if (isset($safeRequest['polygon']) && is_array($safeRequest['polygon'])) $safeRequest['polygon_points'] = count($safeRequest['polygon']);
        if (isset($safeRequest['geometry']) && is_array($safeRequest['geometry'])) $safeRequest['geometry_points'] = count($safeRequest['geometry']);
        unset($safeRequest['polygon'], $safeRequest['geometry']);
        $payload = [
            'time' => date('c'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'endpoint' => $endpoint,
            'headers' => function_exists('getallheaders') ? getallheaders() : [],
            'request' => $safeRequest,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ];
        error_log('[GIS_API_ERROR] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
