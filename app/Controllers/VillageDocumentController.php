<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\VillageDocument;
use App\Services\FileStorageService;
use Throwable;

final class VillageDocumentController extends BaseController
{
    private VillageDocument $documents;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->documents = new VillageDocument();
    }

    public function index(): void { $this->requirePermission('documents', 'read'); $this->ok($this->documents->paginate($this->filters())); }
    public function catalogs(): void { $this->requirePermission('documents', 'read'); $this->ok($this->documents->catalogs()); }
    public function dashboard(): void { $this->requirePermission('documents', 'read'); $this->ok($this->documents->dashboard($this->filters())); }
    public function report(): void { $this->requirePermission('documents', 'read'); $this->ok($this->documents->report($this->filters())); }

    public function show(string $id): void
    {
        $this->requirePermission('documents', 'read');
        $row = $this->documents->find((int)$id);
        if (!$row) $this->fail('Khong tim thay van ban', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requireAdminDocumentWrite('create');
        try {
            $row = $this->documents->upsert((array)$this->input(), (int)$user['id']);
            $this->audit($user, 'documents', 'create', 'Them van ban', $row['id'], ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requireAdminDocumentWrite('update');
        try {
            $before = $this->documents->find((int)$id);
            if (!$before) $this->fail('Khong tim thay van ban', 404);
            $row = $this->documents->upsert((array)$this->input(), (int)$user['id'], (int)$id);
            $this->audit($user, 'documents', 'update', 'Cap nhat van ban', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requireAdminDocumentWrite('delete');
        $before = $this->documents->find((int)$id);
        if (!$before) $this->fail('Khong tim thay van ban', 404);
        $deleted = $this->documents->deletePermanently((int)$id);
        $storage = new FileStorageService();
        $removed = [];
        foreach (($deleted['files'] ?? []) as $file) {
            $removed[] = ['id' => $file['id'] ?? null, 'path' => $file['stored_path'] ?? '', 'deleted' => $storage->deleteStoredFile((string)($file['stored_path'] ?? ''))];
        }
        $this->audit($user, 'documents', 'delete', 'Xoa van ban', $id, ['before' => $before, 'files' => $removed, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null], 'WARN');
        $this->ok(['id' => (int)$id]);
    }

    public function uploadAttachment(string $id): void
    {
        $user = $this->requireAdminDocumentWrite('upload');
        $existing = $this->documents->find((int)$id);
        if (!$existing) $this->fail('Khong tim thay van ban', 404);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) $this->fail('Vui long chon file van ban', 422);
        $storage = new FileStorageService();
        $info = $storage->inspectOfficeDocumentUpload($file);
        if ((string)$this->query('replace', '') === '1') {
            foreach (($existing['attachments'] ?? []) as $oldFile) {
                $deleted = $this->documents->deleteAttachment((int)$id, (int)$oldFile['id'], (int)$user['id']);
                $storage->deleteStoredFile((string)($deleted['stored_path'] ?? ''));
            }
        }
        $stored = $storage->storeUpload($file, 'document_record', $this->categoryForMime($info['mime']), $info['extension']);
        $stored['mime'] = $info['mime'];
        $stored['extension'] = $info['extension'];
        $row = $this->documents->addAttachment((int)$id, $stored, $file, (int)$user['id']);
        $this->audit($user, 'documents', 'upload', 'Dinh kem file van ban', $id, ['file' => $row, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        $this->ok($row);
    }

    public function downloadPrimary(string $id): void
    {
        $this->streamAttachment((int)$id, 0, false);
    }

    public function previewAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, true); }
    public function downloadAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, false); }

    public function deleteAttachment(string $id, string $fileId): void
    {
        $user = $this->requireAdminDocumentWrite('delete');
        $before = $this->documents->attachment((int)$id, (int)$fileId);
        if (!$before) $this->fail('Khong tim thay file dinh kem', 404);
        $deleted = $this->documents->deleteAttachment((int)$id, (int)$fileId, (int)$user['id']);
        $storage = new FileStorageService();
        $removed = $storage->deleteStoredFile((string)($deleted['stored_path'] ?? ''));
        $this->audit($user, 'documents', 'delete_attachment', 'Xoa file dinh kem van ban', $id, ['file' => $before, 'physical_deleted' => $removed, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        $this->ok(['id' => (int)$fileId]);
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('documents', 'export');
        $report = $this->documents->report($this->filters());
        $this->audit($user, 'documents', 'export', 'Xuat Excel van ban', null, ['totalRows' => $report['totalRows']]);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="bao-cao-van-ban-' . date('Ymd_His') . '.xls"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"></head><body><h1>' . htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') . '</h1><table border="1"><thead><tr>';
        foreach ($report['headers'] as $header) echo '<th>' . htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($report['rows'] as $row) { echo '<tr>'; foreach ($row as $cell) echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>'; echo '</tr>'; }
        echo '</tbody></table></body></html>';
        exit;
    }

    public function exportPdf(): void
    {
        $user = $this->requirePermission('documents', 'export');
        $report = $this->documents->report($this->filters());
        $this->audit($user, 'documents', 'export', 'Xuat PDF van ban', null, ['totalRows' => $report['totalRows']]);
        $pdf = new SimplePdf();
        $pdf->addPrintHeader('Thon 09', $report['title']);
        $pdf->addMeta('Thoi gian xuat: ' . date('d/m/Y H:i:s'));
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('Truong thon');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bao-cao-van-ban-' . date('Ymd_His') . '.pdf"');
        echo $pdf->output();
        exit;
    }

    private function streamAttachment(int $id, int $fileId, bool $preview): void
    {
        $user = $this->requirePermission('documents', 'read');
        $file = $fileId > 0 ? $this->documents->attachment($id, $fileId) : $this->documents->primaryAttachment($id);
        if (!$file) $this->fail('Khong tim thay file dinh kem', 404);
        $storage = new FileStorageService();
        $path = $storage->safeFilePath((string)$file['stored_path']);
        if (!$path || !is_file($path)) $this->fail('File khong con ton tai', 404);
        $mime = mime_content_type($path) ?: (string)$file['mime_type'];
        if (!$this->allowedMime($mime, (string)$file['original_name'])) $this->fail('Dinh dang file khong duoc ho tro', 415);
        if ($preview && $mime !== 'application/pdf') $preview = false;
        if (!$preview) $this->audit($user, 'documents', 'download', 'Tai xuong van ban', $id, ['file' => $file['id'] ?? null, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($preview ? 'inline' : 'attachment') . '; filename="' . rawurlencode(basename((string)$file['original_name'])) . '"');
        readfile($path);
        exit;
    }

    private function filters(): array
    {
        return ['page' => $this->query('page', 1), 'pageSize' => $this->query('pageSize', 20), 'search' => $this->query('search', $this->query('q', '')), 'category_id' => $this->query('category_id', $this->query('categoryId', '')), 'status' => $this->query('status', ''), 'year' => $this->query('year', ''), 'date_from' => $this->query('date_from', $this->query('dateFrom', '')), 'date_to' => $this->query('date_to', $this->query('dateTo', '')), 'sort' => $this->query('sort', 'created_at'), 'direction' => $this->query('direction', 'DESC')];
    }

    private function categoryForMime(string $mime): string { return 'documents'; }
    private function allowedMime(string $mime, string $name = ''): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return in_array($mime, ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip','application/x-zip-compressed','application/octet-stream'], true)
            && in_array($extension, ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip'], true);
    }

    private function requireAdminDocumentWrite(string $action): array
    {
        $user = $this->requirePermission('documents', $action);
        if (!in_array((string)($user['role'] ?? ''), ['SUPER_ADMIN', 'ADMIN'], true)) {
            $this->fail('Chi quan tri vien moi duoc thuc hien thao tac nay', 403);
        }
        return $user;
    }
}
