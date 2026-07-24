<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\WorkCalendar;
use App\Services\FileStorageService;
use Throwable;

final class WorkCalendarController extends BaseController
{
    private WorkCalendar $calendar;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->calendar = new WorkCalendar();
    }

    public function index(): void { $this->requirePermission('work_calendar', 'read'); $this->ok($this->calendar->paginate($this->filters())); }
    public function catalogs(): void { $this->requirePermission('work_calendar', 'read'); $this->ok($this->calendar->catalogs()); }
    public function dashboard(): void { $this->requirePermission('work_calendar', 'read'); $this->ok($this->calendar->dashboard($this->filters())); }
    public function report(): void { $this->requirePermission('work_calendar', 'read'); $this->ok($this->calendar->report($this->filters())); }

    public function show(string $id): void
    {
        $this->requirePermission('work_calendar', 'read');
        $row = $this->calendar->find((int)$id);
        if (!$row) $this->fail('Không tìm thấy lịch công tác', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('work_calendar', 'create');
        try {
            $row = $this->calendar->upsert((array)$this->input(), (int)$user['id'], $this->userName($user));
            $this->audit($user, 'work_calendar', 'create', 'Thêm lịch công tác', $row['id'], ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('work_calendar', 'update');
        try {
            $before = $this->calendar->find((int)$id);
            if (!$before) $this->fail('Không tìm thấy lịch công tác', 404);
            $row = $this->calendar->upsert((array)$this->input(), (int)$user['id'], $this->userName($user), (int)$id);
            $this->audit($user, 'work_calendar', 'update', 'Cập nhật lịch công tác', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('work_calendar', 'delete');
        try {
            $before = $this->calendar->find((int)$id);
            if (!$before) $this->fail('Không tìm thấy lịch công tác', 404);
            $this->calendar->softDelete((int)$id, (int)$user['id']);
            $this->audit($user, 'work_calendar', 'delete', 'Xóa lịch công tác', $id, ['before' => $before], 'WARN');
            $this->ok(['id' => (int)$id]);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function uploadAttachment(string $id): void
    {
        $user = $this->requirePermission('work_calendar', 'upload');
        if (!$this->calendar->find((int)$id)) $this->fail('Không tìm thấy lịch công tác', 404);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) $this->fail('Vui lòng chọn file đính kèm', 422);
        $storage = new FileStorageService();
        $info = $storage->inspectUpload($file, $this->fileType($file), 'work_calendar');
        if (!$this->allowedMime($info['mime'])) throw new \RuntimeException('Định dạng file không được hỗ trợ');
        $stored = $storage->storeUpload($file, 'work_calendar', $this->categoryForMime($info['mime']), $info['extension']);
        $stored['mime'] = $info['mime'];
        $row = $this->calendar->addAttachment((int)$id, $stored, $file, (int)$user['id']);
        $this->audit($user, 'work_calendar', 'upload', 'Đính kèm file lịch công tác', $id, ['file' => $row]);
        $this->ok($row);
    }

    public function previewAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, true); }
    public function downloadAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, false); }

    public function deleteAttachment(string $id, string $fileId): void
    {
        $user = $this->requirePermission('work_calendar', 'delete');
        $before = $this->calendar->attachment((int)$id, (int)$fileId);
        if (!$before) $this->fail('Không tìm thấy file đính kèm', 404);
        $this->calendar->deleteAttachment((int)$id, (int)$fileId, (int)$user['id']);
        $this->audit($user, 'work_calendar', 'delete_attachment', 'Xóa file đính kèm lịch công tác', $id, ['file' => $before]);
        $this->ok(['id' => (int)$fileId]);
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('work_calendar', 'export');
        $report = $this->calendar->report($this->filters());
        $this->audit($user, 'work_calendar', 'export', 'Xuất Excel lịch công tác', null, ['totalRows' => $report['totalRows']]);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="bao-cao-lich-cong-tac-' . date('Ymd_His') . '.xls"');
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
        $user = $this->requirePermission('work_calendar', 'export');
        $report = $this->calendar->report($this->filters());
        $this->audit($user, 'work_calendar', 'export', 'Xuất PDF lịch công tác', null, ['totalRows' => $report['totalRows']]);
        $pdf = new SimplePdf();
        $pdf->addPrintHeader('Thôn 09', $report['title']);
        $pdf->addMeta('Thời gian xuất: ' . date('d/m/Y H:i:s'));
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('Trưởng thôn');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bao-cao-lich-cong-tac-' . date('Ymd_His') . '.pdf"');
        echo $pdf->output();
        exit;
    }

    private function streamAttachment(int $id, int $fileId, bool $preview): void
    {
        $this->requirePermission('work_calendar', 'read');
        $file = $this->calendar->attachment($id, $fileId);
        if (!$file) $this->fail('Không tìm thấy file đính kèm', 404);
        $storage = new FileStorageService();
        $path = $storage->safeFilePath((string)$file['stored_path']);
        if (!$path || !is_file($path)) $this->fail('File không còn tồn tại', 404);
        $mime = mime_content_type($path) ?: (string)$file['mime_type'];
        if (!$this->allowedMime($mime)) $this->fail('Định dạng file không được hỗ trợ', 415);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($preview ? 'inline' : 'attachment') . '; filename="' . basename((string)$file['original_name']) . '"');
        readfile($path);
        exit;
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'category_id' => $this->query('category_id', $this->query('categoryId', '')),
            'status' => $this->query('status', ''),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'date_from' => $this->query('date_from', $this->query('dateFrom', '')),
            'date_to' => $this->query('date_to', $this->query('dateTo', '')),
            'sort' => $this->query('sort', 'start_at'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }

    private function userName(array $user): string { return trim((string)($user['display_name'] ?? $user['name'] ?? $user['email'] ?? '')); }
    private function fileType(array $file): string { $mime = !empty($file['tmp_name']) ? (mime_content_type($file['tmp_name']) ?: '') : ''; if (str_starts_with($mime, 'image/')) return 'IMAGE'; if (str_starts_with($mime, 'video/')) return 'VIDEO'; return 'DOCUMENT'; }
    private function categoryForMime(string $mime): string { if (str_starts_with($mime, 'image/')) return 'images'; if (str_starts_with($mime, 'video/')) return 'videos'; return 'documents'; }
    private function allowedMime(string $mime): bool { return in_array($mime, ['image/jpeg','image/png','image/webp','video/mp4','video/webm','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/csv'], true); }
}
