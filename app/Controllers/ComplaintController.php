<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\Complaint;
use App\Services\FileStorageService;

final class ComplaintController extends BaseController
{
    private Complaint $complaints;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->complaints = new Complaint();
    }

    public function index(): void { $this->requirePermission('complaints', 'read'); $this->ok($this->complaints->paginate($this->filters())); }
    public function catalogs(): void { $this->requirePermission('complaints', 'read'); $this->ok($this->complaints->catalogs()); }
    public function dashboard(): void { $this->requirePermission('complaints', 'read'); $this->ok($this->complaints->dashboard($this->filters())); }
    public function gis(): void { $this->requirePermission('complaints', 'read'); $this->ok(['items' => $this->complaints->gisFeatures($this->filters())]); }
    public function householdSearch(): void { $this->requirePermission('complaints', 'read'); $this->ok(['items' => $this->complaints->householdSearch((string)$this->query('q', $this->query('search', '')))]); }
    public function citizenSearch(): void { $this->requirePermission('complaints', 'read'); $this->ok(['items' => $this->complaints->citizenSearch((string)$this->query('q', $this->query('search', '')), (int)$this->query('household_id', $this->query('householdId', 0)) ?: null)]); }

    public function show(string $id): void
    {
        $this->requirePermission('complaints', 'read');
        $row = $this->complaints->find((int)$id);
        if (!$row) $this->fail('Không tìm thấy phản ánh', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('complaints', 'create');
        $row = $this->complaints->upsert((array)$this->input(), (int)$user['id'], $this->userName($user));
        $this->audit($user, 'complaints', 'create', 'Thêm phản ánh - kiến nghị', $row['id'], ['after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('complaints', 'update');
        $before = $this->complaints->find((int)$id);
        if (!$before) $this->fail('Không tìm thấy phản ánh', 404);
        $row = $this->complaints->upsert((array)$this->input(), (int)$user['id'], $this->userName($user), (int)$id);
        $this->audit($user, 'complaints', 'update', 'Cập nhật phản ánh - kiến nghị', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('complaints', 'delete');
        $before = $this->complaints->find((int)$id);
        if (!$before) $this->fail('Không tìm thấy phản ánh', 404);
        $this->complaints->softDelete((int)$id, (int)$user['id']);
        $this->audit($user, 'complaints', 'delete', 'Xóa phản ánh - kiến nghị', $id, ['before' => $before]);
        $this->ok(['id' => (int)$id]);
    }

    public function addHistory(string $id): void
    {
        $user = $this->requirePermission('complaints', 'update');
        $row = $this->complaints->addHistory((int)$id, (array)$this->input(), (int)$user['id'], $this->userName($user));
        $this->audit($user, 'complaints', 'status_change', 'Cập nhật nhật ký xử lý phản ánh', $id, ['history' => $row]);
        $this->ok($row);
    }

    public function assign(string $id): void
    {
        $user = $this->requirePermission('complaints', 'update');
        $row = $this->complaints->assign((int)$id, (array)$this->input(), (int)$user['id']);
        $this->audit($user, 'complaints', 'assign', 'Giao việc xử lý phản ánh', $id, ['assignment' => $row]);
        $this->ok($row);
    }

    public function evaluate(string $id): void
    {
        $user = $this->requirePermission('complaints', 'update');
        $row = $this->complaints->evaluate((int)$id, (array)$this->input(), (int)$user['id']);
        $this->audit($user, 'complaints', 'evaluate', 'Đánh giá kết quả xử lý phản ánh', $id, ['rating' => $row['result_rating'] ?? null]);
        $this->ok($row);
    }

    public function uploadAttachment(string $id): void
    {
        $user = $this->requirePermission('complaints', 'upload');
        if (!$this->complaints->find((int)$id)) $this->fail('Không tìm thấy phản ánh', 404);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) $this->fail('Vui lòng chọn file đính kèm', 422);
        $storage = new FileStorageService();
        $info = $storage->inspectUpload($file, $this->fileType($file), 'complaint');
        if (!$this->allowedComplaintMime($info['mime'])) throw new \RuntimeException('Chỉ hỗ trợ ảnh, video MP4/WEBM hoặc PDF');
        $stored = $storage->storeUpload($file, 'complaint', $this->categoryForMime($info['mime']), $info['extension']);
        $stored['mime'] = $info['mime'];
        $row = $this->complaints->addAttachment((int)$id, $stored, $file, (int)$user['id'], (int)($this->input('history_id', $this->input('historyId', 0))) ?: null);
        $this->audit($user, 'complaints', 'upload', 'Đính kèm file phản ánh', $id, ['file' => $row]);
        $this->ok($row);
    }

    public function previewAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, true); }
    public function downloadAttachment(string $id, string $fileId): void { $this->streamAttachment((int)$id, (int)$fileId, false); }

    public function deleteAttachment(string $id, string $fileId): void
    {
        $user = $this->requirePermission('complaints', 'delete');
        $before = $this->complaints->attachment((int)$id, (int)$fileId);
        if (!$before) $this->fail('Không tìm thấy file đính kèm', 404);
        $this->complaints->deleteAttachment((int)$id, (int)$fileId, (int)$user['id']);
        $this->audit($user, 'complaints', 'delete_attachment', 'Xóa file đính kèm phản ánh', $id, ['file' => $before]);
        $this->ok(['id' => (int)$fileId]);
    }

    public function report(): void
    {
        $this->requirePermission('complaints', 'read');
        $this->ok($this->complaints->report($this->filters()));
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('complaints', 'export');
        $report = $this->complaints->report($this->filters());
        $this->audit($user, 'complaints', 'export', 'Xuất Excel báo cáo phản ánh', null, ['totalRows' => $report['totalRows']]);
        $fileName = 'bao-cao-phan-anh-' . date('Ymd_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
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
        $user = $this->requirePermission('complaints', 'export');
        $report = $this->complaints->report($this->filters());
        $this->audit($user, 'complaints', 'export', 'Xuất PDF báo cáo phản ánh', null, ['totalRows' => $report['totalRows']]);
        $pdf = new SimplePdf();
        $pdf->addPrintHeader('Thôn 09', $report['title']);
        $pdf->addMeta('Thời gian xuất: ' . date('d/m/Y H:i:s'));
        foreach ($report['summary'] as $label => $value) $pdf->addMeta($label . ': ' . $value);
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('Trưởng thôn');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bao-cao-phan-anh-' . date('Ymd_His') . '.pdf"');
        echo $pdf->output();
        exit;
    }

    private function streamAttachment(int $id, int $fileId, bool $preview): void
    {
        $this->requirePermission('complaints', 'read');
        $file = $this->complaints->attachment($id, $fileId);
        if (!$file) $this->fail('Không tìm thấy file đính kèm', 404);
        $storage = new FileStorageService();
        $path = $storage->safeFilePath((string)$file['stored_path']);
        if (!$path || !is_file($path)) $this->fail('File không còn tồn tại', 404);
        $mime = mime_content_type($path) ?: (string)$file['mime_type'];
        if (!$this->allowedComplaintMime($mime)) $this->fail('Định dạng file không được hỗ trợ', 415);
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
            'receiver_user_id' => $this->query('receiver_user_id', $this->query('receiverUserId', '')),
            'household_id' => $this->query('household_id', $this->query('householdId', '')),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'date_from' => $this->query('date_from', $this->query('dateFrom', '')),
            'date_to' => $this->query('date_to', $this->query('dateTo', '')),
            'overdue' => $this->query('overdue', ''),
            'located' => $this->query('located', ''),
            'sort' => $this->query('sort', 'received_at'),
            'direction' => $this->query('direction', 'DESC'),
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

    private function allowedComplaintMime(string $mime): bool
    {
        return in_array($mime, ['image/jpeg','image/png','image/webp','video/mp4','video/webm','application/pdf'], true);
    }
}
