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
        $this->requirePermission('household', 'read');
        $this->ok($this->areas->all());
    }

    public function storeArea(): void
    {
        $user = $this->requirePermission('household', 'update');
        $area = $this->areas->save($this->input(), (int) $user['id']);
        $this->audit($user, 'gis', 'save_polygon', 'Lưu ranh giới khu vực bản đồ', $area['id'] ?? null, ['area_code' => $area['area_code'] ?? null]);
        $this->ok($area);
    }

    public function deleteArea(string $id): void
    {
        $user = $this->requirePermission('household', 'update');
        $this->areas->delete((int) $id, (int) $user['id']);
        $this->audit($user, 'gis', 'delete_polygon', 'Xóa ranh giới khu vực bản đồ', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function exportPdf(): void
    {
        $this->requirePermission('household', 'read');
        $rows = $this->areas->pdfRows();
        $pdfRows = [];
        foreach ($rows as $index => $row) {
            $pdfRows[] = [
                (string) ($index + 1),
                (string) $row['name'],
                (string) $row['area_code'],
                (string) ((int) $row['households']),
                (string) ((int) $row['citizens']),
                (string) ((int) $row['temporary']),
                (string) ((int) $row['away']),
            ];
        }
        $pdf = new SimplePdf();
        $pdf->addTitle('BAN DO DIA BAN - THONG KE THEO KHU VUC');
        $pdf->addMeta('He thong Quan ly Hanh chinh Thon 09 - Xa Hong Phong');
        $pdf->addMeta('Ngay xuat: ' . date('d/m/Y H:i'));
        $pdf->addTable(['STT','Khu vuc','Ma KV','So ho','Nhan khau','Tam tru','Tam vang'], $pdfRows ?: [['','Chua co du lieu ranh gioi khu vuc','','','','','']]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="ban_do_dia_ban.pdf"');
        echo $pdf->output();
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
