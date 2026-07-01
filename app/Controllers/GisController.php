<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\GisArea;

final class GisController extends BaseController
{
    private GisArea $areas;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->areas = new GisArea();
    }

    public function areas(): void
    {
        try {
            $this->requirePermission('household', 'read');
            $this->ok($this->areas->all());
        } catch (\Throwable $e) {
            $this->logApiFailure('GET /api/gis/areas', [], $e);
            throw $e;
        }
    }

    public function storeArea(): void
    {
        $input = $this->input();
        try {
            $user = $this->requirePermission('household', 'update');
            $area = $this->areas->save($input, (int) $user['id']);
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
            $area = $this->areas->save(is_array($input) ? $input : ['id' => (int) $id], (int) $user['id']);
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
            $this->areas->delete((int) $id, (int) $user['id']);
            $this->audit($user, 'gis', 'delete_polygon', 'Xóa ranh giới khu vực bản đồ', $id);
            $this->ok(['id' => (int) $id]);
        } catch (\Throwable $e) {
            $this->logApiFailure('DELETE /api/gis/areas/' . $id, ['id' => $id], $e);
            throw $e;
        }
    }

    public function exportPdf(): void
    {
        try {
            $this->requirePermission('household', 'read');
            $rows = $this->areas->pdfRows();
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

    private function logApiFailure(string $endpoint, array $request, \Throwable $e): void
    {
        $safeRequest = $request;
        if (isset($safeRequest['polygon']) && is_array($safeRequest['polygon'])) $safeRequest['polygon_points'] = count($safeRequest['polygon']);
        if (isset($safeRequest['geometry']) && is_array($safeRequest['geometry'])) $safeRequest['geometry_points'] = count($safeRequest['geometry']);
        unset($safeRequest['polygon'], $safeRequest['geometry']);
        $payload = [
            'time' => date('c'),
            'endpoint' => $endpoint,
            'request' => $safeRequest,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ];
        error_log('[GIS_API_ERROR] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
