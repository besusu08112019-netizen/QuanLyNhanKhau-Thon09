<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\ExportEncoding;
use App\Core\SimplePdf;
use App\Core\TenantConfig;
use App\Models\PolicySubject;
use Throwable;

final class PolicySubjectController extends BaseController
{
    private PolicySubject $policySubjects;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->policySubjects = new PolicySubject();
    }

    public function catalogs(): void
    {
        $this->requirePermission('policy_subjects', 'read');
        $this->ok($this->policySubjects->catalogs());
    }

    public function types(): void
    {
        $this->requirePermission('policy_subjects', 'read');
        $this->ok($this->policySubjects->typeList($this->filters()));
    }

    public function storeType(): void
    {
        $user = $this->requirePermission('policy_subjects', 'create');
        try {
            $row = $this->policySubjects->saveType((array) $this->input(), (int) $user['id']);
            $this->safeAudit($user, 'policy_subjects', 'create', 'Thêm loại đối tượng chính sách', $row['id'] ?? null, ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->logPolicyException('type.store', $e, (array) $this->input());
            $this->fail($this->safeExceptionMessage('Không lưu được loại đối tượng', $e), 422);
        }
    }

    public function updateType(string $id): void
    {
        $user = $this->requirePermission('policy_subjects', 'update');
        $before = $this->policySubjects->findType((int) $id);
        if (!$before) $this->fail('Không tìm thấy loại đối tượng', 404);
        try {
            $row = $this->policySubjects->saveType((array) $this->input(), (int) $user['id'], (int) $id);
            $this->safeAudit($user, 'policy_subjects', 'update', 'Cập nhật loại đối tượng chính sách', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->logPolicyException('type.update', $e, ['id' => $id] + (array) $this->input());
            $this->fail($this->safeExceptionMessage('Không cập nhật được loại đối tượng', $e), 422);
        }
    }

    public function deleteType(string $id): void
    {
        $user = $this->requirePermission('policy_subjects', 'delete');
        $before = $this->policySubjects->findType((int) $id);
        if (!$before) $this->fail('Không tìm thấy loại đối tượng', 404);
        try {
            $this->policySubjects->deleteType((int) $id, (int) $user['id']);
            $this->safeAudit($user, 'policy_subjects', 'delete', 'Xóa loại đối tượng chính sách', $id, ['before' => $before]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $e) {
            $this->logPolicyException('type.delete', $e, ['id' => $id]);
            $this->fail($this->safeExceptionMessage('Không xóa được loại đối tượng', $e), 422);
        }
    }

    public function index(): void
    {
        $this->requirePermission('policy_subjects', 'read');
        $this->ok($this->policySubjects->recordList($this->filters()));
    }

    public function show(string $id): void
    {
        $this->requirePermission('policy_subjects', 'read');
        $row = $this->policySubjects->findRecord((int) $id);
        if (!$row) $this->fail('Không tìm thấy hồ sơ chính sách', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('policy_subjects', 'create');
        try {
            $row = $this->policySubjects->createRecord((array) $this->input(), (int) $user['id'], $this->requestMeta());
            $this->safeAudit($user, 'policy_subjects', 'create', 'Thêm hồ sơ đối tượng chính sách', $row['id'] ?? null, ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->logPolicyException('record.store', $e, (array) $this->input());
            $this->fail($this->safeExceptionMessage('Không lưu được hồ sơ chính sách', $e), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('policy_subjects', 'update');
        $before = $this->policySubjects->findRecord((int) $id);
        if (!$before) $this->fail('Không tìm thấy hồ sơ chính sách', 404);
        try {
            $row = $this->policySubjects->updateRecord((int) $id, (array) $this->input(), (int) $user['id'], $this->requestMeta());
            $this->safeAudit($user, 'policy_subjects', 'update', 'Cập nhật hồ sơ đối tượng chính sách', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->logPolicyException('record.update', $e, ['id' => $id] + (array) $this->input());
            $this->fail($this->safeExceptionMessage('Không cập nhật được hồ sơ chính sách', $e), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('policy_subjects', 'delete');
        $before = $this->policySubjects->findRecord((int) $id);
        if (!$before) $this->fail('Không tìm thấy hồ sơ chính sách', 404);
        try {
            $this->policySubjects->deleteRecord((int) $id, (int) $user['id'], $this->requestMeta());
            $this->safeAudit($user, 'policy_subjects', 'delete', 'Xóa mềm hồ sơ đối tượng chính sách', $id, ['before' => $before]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $e) {
            $this->logPolicyException('record.delete', $e, ['id' => $id]);
            $this->fail($this->safeExceptionMessage('Không xóa được hồ sơ chính sách', $e), 422);
        }
    }

    public function uploadAttachment(string $recordId): void
    {
        $user = $this->requirePermission('policy_subjects', 'upload');
        if (empty($_FILES['file'])) $this->fail('Vui lòng chọn file', 422);
        try {
            $row = $this->policySubjects->addAttachment((int) $recordId, $_FILES['file'], (string) ($_POST['file_type'] ?? $_POST['fileType'] ?? 'OTHER'), (int) $user['id'], $this->requestMeta());
            $this->safeAudit($user, 'policy_subjects', 'upload', 'Upload hồ sơ đính kèm đối tượng chính sách', $recordId, ['attachment' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->logPolicyException('attachment.upload', $e, ['recordId' => $recordId]);
            $this->fail($this->safeExceptionMessage('Không upload được hồ sơ đính kèm', $e), 422);
        }
    }

    public function deleteAttachment(string $id): void
    {
        $user = $this->requirePermission('policy_subjects', 'delete');
        try {
            $this->policySubjects->deleteAttachment((int) $id, (int) $user['id'], $this->requestMeta());
            $this->safeAudit($user, 'policy_subjects', 'delete', 'Xóa hồ sơ đính kèm đối tượng chính sách', $id);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $e) {
            $this->logPolicyException('attachment.delete', $e, ['id' => $id]);
            $this->fail($this->safeExceptionMessage('Không xóa được hồ sơ đính kèm', $e), 422);
        }
    }

    public function attachments(string $recordId): void
    {
        $this->requirePermission('policy_subjects', 'read');
        $this->ok(['items' => $this->policySubjects->attachments((int) $recordId)]);
    }

    public function dashboard(): void
    {
        $this->requirePermission('policy_subjects', 'read');
        $this->ok($this->policySubjects->dashboard($this->filters()) + ['generatedAt' => date('c')]);
    }

    public function report(): void
    {
        $this->requirePermission('policy_subjects', 'read');
        $this->ok(ExportEncoding::cleanArray($this->policySubjects->report($this->filters())));
    }

    public function citizenSearch(): void
    {
        $this->requirePermission('policy_subjects', 'read');
        $this->ok(['items' => $this->policySubjects->searchCitizens((string) $this->query('q', $this->query('search', '')))]);
    }

    public function citizenSummary(string $citizenId): void
    {
        $this->requirePermission('policy_subjects', 'read');
        try {
            $this->ok(['items' => $this->policySubjects->citizenSummary((int) $citizenId)]);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('Không tải được tóm tắt đối tượng chính sách', $e), 422);
        }
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('policy_subjects', 'export');
        $report = ExportEncoding::cleanArray($this->policySubjects->report($this->filters()));
        $this->safeAudit($user, 'policy_subjects', 'export', 'Xuất Excel báo cáo đối tượng chính sách', null, ['totalRows' => $report['totalRows']]);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="bao-cao-doi-tuong-chinh-sach-' . date('Ymd_His') . '.xls"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"></head><body><h1>' . ExportEncoding::html($report['title']) . '</h1>';
        foreach ($report['summary'] as $label => $value) echo '<p>' . ExportEncoding::html($label) . ': ' . ExportEncoding::html($value) . '</p>';
        echo '<table border="1"><thead><tr>';
        foreach ($report['headers'] as $header) echo '<th>' . ExportEncoding::html($header) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($report['rows'] as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo '<td>' . ExportEncoding::html($cell) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    public function exportPdf(): void
    {
        $user = $this->requirePermission('policy_subjects', 'export');
        $report = ExportEncoding::cleanArray($this->policySubjects->report($this->filters()));
        $this->safeAudit($user, 'policy_subjects', 'export', 'Xuất PDF báo cáo đối tượng chính sách', null, ['totalRows' => $report['totalRows']]);
        $pdf = new SimplePdf();
        if (count($report['headers'] ?? []) > 7) $pdf->useLandscape();
        $pdf->addPrintHeader(TenantConfig::unitName(), $report['title']);
        $pdf->addMeta('Thời gian xuất: ' . date('d/m/Y H:i:s'));
        foreach ($report['summary'] as $label => $value) $pdf->addMeta($label . ': ' . $value);
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('Trưởng thôn');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bao-cao-doi-tuong-chinh-sach-' . date('Ymd_His') . '.pdf"');
        echo $pdf->output();
        exit;
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'policy_type_id' => $this->query('policy_type_id', $this->query('policyTypeId', $this->query('type', ''))),
            'record_status' => $this->query('record_status', $this->query('recordStatus', $this->query('status', ''))),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'household_id' => $this->query('household_id', $this->query('householdId', '')),
            'gender' => $this->query('gender', ''),
            'age_from' => $this->query('age_from', $this->query('ageFrom', '')),
            'age_to' => $this->query('age_to', $this->query('ageTo', '')),
            'active' => $this->query('active', ''),
            'sort' => $this->query('sort', 'start_date'),
            'direction' => $this->query('direction', 'DESC'),
        ];
    }

    private function requestMeta(): array
    {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
    }

    private function safeAudit(?array $user, string $module, string $action, string $message, mixed $entityId = null, array $metadata = [], string $level = 'INFO'): void
    {
        try {
            $this->audit($user, $module, $action, $message, $entityId, $metadata, $level);
        } catch (Throwable $e) {
            error_log('[POLICY_SUBJECT_AUDIT_ERROR] ' . $e->getMessage());
        }
    }

    private function logPolicyException(string $context, Throwable $e, array $payload = []): void
    {
        foreach (['password', 'token', 'secret', 'connection_string', 'db_password', 'database_password'] as $key) unset($payload[$key]);
        error_log('[POLICY_SUBJECT_ERROR] ' . json_encode([
            'context' => $context,
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'payload' => $payload,
            'trace' => $e->getTraceAsString(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
