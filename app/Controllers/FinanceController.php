<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\Finance;
use App\Services\FileStorageService;
use Throwable;

final class FinanceController extends BaseController
{
    private Finance $finance;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->finance = new Finance();
    }

    public function index(): void { $this->requirePermission('finance', 'read'); $this->ok($this->finance->paginate($this->filters())); }
    public function catalogs(): void { $this->requirePermission('finance', 'read'); $this->ok($this->finance->catalogs()); }
    public function dashboard(): void { $this->requirePermission('finance', 'read'); $this->ok($this->finance->dashboard($this->filters())); }
    public function report(): void { $this->requirePermission('finance', 'read'); $this->ok($this->finance->report($this->filters())); }

    public function show(string $id): void
    {
        $this->requirePermission('finance', 'read');
        $row = $this->finance->find((int)$id);
        if (!$row) $this->fail('Khong tim thay phieu thu chi', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('finance', 'create');
        try {
            $row = $this->finance->upsert((array)$this->input(), (int)$user['id']);
            $this->audit($user, 'finance', 'create', 'Them phieu thu chi', $row['id'], ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('finance', 'update');
        try {
            $before = $this->finance->find((int)$id);
            if (!$before) $this->fail('Khong tim thay phieu thu chi', 404);
            $row = $this->finance->upsert((array)$this->input(), (int)$user['id'], (int)$id);
            $this->audit($user, 'finance', 'update', 'Cap nhat phieu thu chi', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('finance', 'delete');
        $before = $this->finance->find((int)$id);
        if (!$before) $this->fail('Khong tim thay phieu thu chi', 404);
        $this->finance->softDelete((int)$id, (int)$user['id']);
        $this->audit($user, 'finance', 'delete', 'Xoa phieu thu chi', $id, ['before' => $before], 'WARN');
        $this->ok(['id' => (int)$id]);
    }

    public function uploadAttachment(string $id): void
    {
        $user = $this->requirePermission('finance', 'upload');
        if (!$this->finance->find((int)$id)) $this->fail('Khong tim thay phieu thu chi', 404);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) $this->fail('Vui long chon file dinh kem', 422);
        $storage = new FileStorageService();
        $info = $storage->inspectUpload($file, $this->fileType($file), 'finance_transaction');
        if (!$this->allowedMime($info['mime'])) throw new \RuntimeException('Dinh dang file khong duoc ho tro');
        $stored = $storage->storeUpload($file, 'finance_transaction', $this->categoryForMime($info['mime']), $info['extension']);
        $stored['mime'] = $info['mime'];
        $row = $this->finance->addAttachment((int)$id, $stored, $file, (int)$user['id']);
        $this->audit($user, 'finance', 'upload', 'Dinh kem chung tu thu chi', $id, ['file' => $row]);
        $this->ok($row);
    }

    public function previewAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, true); }
    public function downloadAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, false); }

    public function deleteAttachment(string $id, string $fileId): void
    {
        $user = $this->requirePermission('finance', 'delete');
        $before = $this->finance->attachment((int)$id, (int)$fileId);
        if (!$before) $this->fail('Khong tim thay file dinh kem', 404);
        $this->finance->deleteAttachment((int)$id, (int)$fileId, (int)$user['id']);
        $this->audit($user, 'finance', 'delete_attachment', 'Xoa chung tu thu chi', $id, ['file' => $before]);
        $this->ok(['id' => (int)$fileId]);
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('finance', 'export');
        $report = $this->finance->report($this->filters());
        $this->audit($user, 'finance', 'export', 'Xuat Excel thu chi', null, ['totalRows' => $report['totalRows']]);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="bao-cao-thu-chi-' . date('Ymd_His') . '.xls"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"></head><body><h1>' . htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>Tong thu: ' . number_format((float)$report['summary']['total_income'], 0, ',', '.') . ' | Tong chi: ' . number_format((float)$report['summary']['total_expense'], 0, ',', '.') . ' | Con lai: ' . number_format((float)$report['summary']['balance'], 0, ',', '.') . '</p><table border="1"><thead><tr>';
        foreach ($report['headers'] as $header) echo '<th>' . htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($report['rows'] as $row) { echo '<tr>'; foreach ($row as $cell) echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>'; echo '</tr>'; }
        echo '</tbody></table></body></html>';
        exit;
    }

    public function exportPdf(): void
    {
        $user = $this->requirePermission('finance', 'export');
        $report = $this->finance->report($this->filters());
        $this->audit($user, 'finance', 'export', 'Xuat PDF thu chi', null, ['totalRows' => $report['totalRows']]);
        $pdf = new SimplePdf();
        $pdf->addPrintHeader('Thon 09', $report['title']);
        $pdf->addMeta('Tong thu: ' . number_format((float)$report['summary']['total_income'], 0, ',', '.') . ' VND');
        $pdf->addMeta('Tong chi: ' . number_format((float)$report['summary']['total_expense'], 0, ',', '.') . ' VND');
        $pdf->addMeta('Con lai: ' . number_format((float)$report['summary']['balance'], 0, ',', '.') . ' VND');
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('Nguoi lap bieu');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bao-cao-thu-chi-' . date('Ymd_His') . '.pdf"');
        echo $pdf->output();
        exit;
    }

    private function streamAttachment(int $id, int $fileId, bool $preview): void
    {
        $this->requirePermission('finance', 'read');
        $file = $this->finance->attachment($id, $fileId);
        if (!$file) $this->fail('Khong tim thay file dinh kem', 404);
        $storage = new FileStorageService();
        $path = $storage->safeFilePath((string)$file['stored_path']);
        if (!$path || !is_file($path)) $this->fail('File khong con ton tai', 404);
        $mime = mime_content_type($path) ?: (string)$file['mime_type'];
        if (!$this->allowedMime($mime)) $this->fail('Dinh dang file khong duoc ho tro', 415);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($preview ? 'inline' : 'attachment') . '; filename="' . basename((string)$file['original_name']) . '"');
        readfile($path);
        exit;
    }

    private function filters(): array
    {
        return ['page' => $this->query('page', 1), 'pageSize' => $this->query('pageSize', 20), 'search' => $this->query('search', $this->query('q', '')), 'transaction_type' => $this->query('transaction_type', $this->query('transactionType', '')), 'fund_id' => $this->query('fund_id', $this->query('fundId', '')), 'category_id' => $this->query('category_id', $this->query('categoryId', '')), 'status' => $this->query('status', ''), 'date_from' => $this->query('date_from', $this->query('dateFrom', '')), 'date_to' => $this->query('date_to', $this->query('dateTo', '')), 'sort' => $this->query('sort', 'transaction_date'), 'direction' => $this->query('direction', 'DESC')];
    }

    private function fileType(array $file): string { $mime = !empty($file['tmp_name']) ? (mime_content_type($file['tmp_name']) ?: '') : ''; return str_starts_with($mime, 'image/') ? 'IMAGE' : 'DOCUMENT'; }
    private function categoryForMime(string $mime): string { return str_starts_with($mime, 'image/') ? 'images' : 'documents'; }
    private function allowedMime(string $mime): bool { return in_array($mime, ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','image/jpeg','image/png','image/webp'], true); }
}
