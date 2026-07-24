<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\WorkTask;
use App\Services\FileStorageService;
use Throwable;

final class WorkTaskController extends BaseController
{
    private WorkTask $tasks;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->tasks = new WorkTask();
    }

    public function index(): void { $this->requirePermission('work_tasks', 'read'); $this->ok($this->tasks->paginate($this->filters())); }
    public function catalogs(): void { $this->requirePermission('work_tasks', 'read'); $this->ok($this->tasks->catalogs()); }
    public function dashboard(): void { $this->requirePermission('work_tasks', 'read'); $this->ok($this->tasks->dashboard($this->filters())); }
    public function report(): void { $this->requirePermission('work_tasks', 'read'); $this->ok($this->tasks->report($this->filters())); }

    public function show(string $id): void
    {
        $this->requirePermission('work_tasks', 'read');
        $row = $this->tasks->find((int)$id);
        if (!$row) $this->fail('Không tìm thấy công việc', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('work_tasks', 'create');
        try {
            $row = $this->tasks->upsert((array)$this->input(), (int)$user['id'], $this->userName($user));
            $this->audit($user, 'work_tasks', 'create', 'Thêm công việc', $row['id'], ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('work_tasks', 'update');
        try {
            $before = $this->tasks->find((int)$id);
            if (!$before) $this->fail('Không tìm thấy công việc', 404);
            $row = $this->tasks->upsert((array)$this->input(), (int)$user['id'], $this->userName($user), (int)$id);
            $this->audit($user, 'work_tasks', 'update', 'Cập nhật công việc', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('work_tasks', 'delete');
        try {
            $before = $this->tasks->find((int)$id);
            if (!$before) $this->fail('Không tìm thấy công việc', 404);
            $this->tasks->softDelete((int)$id, (int)$user['id']);
            $this->audit($user, 'work_tasks', 'delete', 'Xóa công việc', $id, ['before' => $before], 'WARN');
            $this->ok(['id' => (int)$id]);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function addLog(string $id): void
    {
        $user = $this->requirePermission('work_tasks', 'update');
        try {
            $row = $this->tasks->addLog((int)$id, (array)$this->input(), (int)$user['id'], $this->userName($user));
            $this->audit($user, 'work_tasks', 'progress', 'Cập nhật nhật ký công việc', $id, ['log' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function uploadAttachment(string $id): void
    {
        $user = $this->requirePermission('work_tasks', 'upload');
        if (!$this->tasks->find((int)$id)) $this->fail('Không tìm thấy công việc', 404);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) $this->fail('Vui lòng chọn file đính kèm', 422);
        $storage = new FileStorageService();
        $info = $storage->inspectUpload($file, $this->fileType($file), 'work_task');
        if (!$this->allowedMime($info['mime'])) throw new \RuntimeException('Chỉ hỗ trợ ảnh, video, PDF, Word, Excel hoặc CSV');
        $stored = $storage->storeUpload($file, 'work_task', $this->categoryForMime($info['mime']), $info['extension']);
        $stored['mime'] = $info['mime'];
        $row = $this->tasks->addAttachment((int)$id, $stored, $file, (int)$user['id'], (int)($this->input('log_id', $this->input('logId', 0))) ?: null);
        $this->audit($user, 'work_tasks', 'upload', 'Đính kèm file công việc', $id, ['file' => $row]);
        $this->ok($row);
    }

    public function previewAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, true); }
    public function downloadAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, false); }

    public function deleteAttachment(string $id, string $fileId): void
    {
        $user = $this->requirePermission('work_tasks', 'delete');
        $before = $this->tasks->attachment((int)$id, (int)$fileId);
        if (!$before) $this->fail('Không tìm thấy file đính kèm', 404);
        $this->tasks->deleteAttachment((int)$id, (int)$fileId, (int)$user['id']);
        $this->audit($user, 'work_tasks', 'delete_attachment', 'Xóa file đính kèm công việc', $id, ['file' => $before]);
        $this->ok(['id' => (int)$fileId]);
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('work_tasks', 'export');
        $report = $this->tasks->report($this->filters());
        $this->audit($user, 'work_tasks', 'export', 'Xuất Excel báo cáo công việc', null, ['totalRows' => $report['totalRows']]);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="bao-cao-cong-viec-' . date('Ymd_His') . '.xls"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"></head><body><h1>' . htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') . '</h1><table border="1"><thead><tr>';
        foreach ($report['headers'] as $header) echo '<th>' . htmlspecialchars((string)$header, ENT_QUOTES, 'UTF-8') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($report['rows'] as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    public function exportPdf(): void
    {
        $user = $this->requirePermission('work_tasks', 'export');
        $report = $this->tasks->report($this->filters());
        $this->audit($user, 'work_tasks', 'export', 'Xuất PDF báo cáo công việc', null, ['totalRows' => $report['totalRows']]);
        $pdf = new SimplePdf();
        $pdf->addPrintHeader('Thôn 09', $report['title']);
        $pdf->addMeta('Thời gian xuất: ' . date('d/m/Y H:i:s'));
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('Trưởng thôn');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bao-cao-cong-viec-' . date('Ymd_His') . '.pdf"');
        echo $pdf->output();
        exit;
    }

    private function streamAttachment(int $id, int $fileId, bool $preview): void
    {
        $this->requirePermission('work_tasks', 'read');
        $file = $this->tasks->attachment($id, $fileId);
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
        header('Cache-Control: private, max-age=300');
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
            'priority_id' => $this->query('priority_id', $this->query('priorityId', '')),
            'status_id' => $this->query('status_id', $this->query('statusId', '')),
            'assigned_user_id' => $this->query('assigned_user_id', $this->query('assignedUserId', '')),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'date_from' => $this->query('date_from', $this->query('dateFrom', '')),
            'date_to' => $this->query('date_to', $this->query('dateTo', '')),
            'overdue' => $this->query('overdue', ''),
            'sort' => $this->query('sort', 'due_at'),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }

    private function userName(array $user): string
    {
        return trim((string)($user['display_name'] ?? $user['name'] ?? $user['email'] ?? ''));
    }

    private function fileType(array $file): string
    {
        $mime = !empty($file['tmp_name']) ? (mime_content_type($file['tmp_name']) ?: '') : '';
        if (str_starts_with($mime, 'image/')) return 'IMAGE';
        if (str_starts_with($mime, 'video/')) return 'VIDEO';
        return 'DOCUMENT';
    }

    private function categoryForMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'images';
        if (str_starts_with($mime, 'video/')) return 'videos';
        return 'documents';
    }

    private function allowedMime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg','image/png','image/webp','video/mp4','video/webm','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/csv'], true);
    }
}
