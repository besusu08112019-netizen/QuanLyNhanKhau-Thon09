<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Core\TenantConfig;
use App\Models\HouseholdPoverty;
use Throwable;

final class HouseholdPovertyController extends BaseController
{
    private HouseholdPoverty $poverty;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->poverty = new HouseholdPoverty();
    }

    public function catalogs(): void
    {
        $this->requirePermission('poverty', 'read');
        $this->ok($this->poverty->catalogs());
    }

    public function periods(): void
    {
        $this->requirePermission('poverty', 'read');
        $this->ok($this->poverty->periodList($this->filters()));
    }

    public function showPeriod(string $id): void
    {
        $this->requirePermission('poverty', 'read');
        $row = $this->poverty->findPeriod((int) $id);
        if (!$row) $this->fail('Không tìm thấy giai đoạn', 404);
        $this->ok($row);
    }

    public function storePeriod(): void
    {
        $user = $this->requirePermission('poverty', 'create');
        try {
            $row = $this->poverty->savePeriod((array) $this->input(), (int) $user['id']);
            $this->audit($user, 'poverty', 'create', 'Thêm giai đoạn hộ nghèo/cận nghèo', $row['id'] ?? null, ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('Không lưu được giai đoạn', $e), 422);
        }
    }

    public function updatePeriod(string $id): void
    {
        $user = $this->requirePermission('poverty', 'update');
        $before = $this->poverty->findPeriod((int) $id);
        if (!$before) $this->fail('Không tìm thấy giai đoạn', 404);
        try {
            $row = $this->poverty->savePeriod((array) $this->input(), (int) $user['id'], (int) $id);
            $this->audit($user, 'poverty', 'update', 'Cập nhật giai đoạn hộ nghèo/cận nghèo', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('Không cập nhật được giai đoạn', $e), 422);
        }
    }

    public function deletePeriod(string $id): void
    {
        $user = $this->requirePermission('poverty', 'delete');
        $before = $this->poverty->findPeriod((int) $id);
        if (!$before) $this->fail('Không tìm thấy giai đoạn', 404);
        try {
            $this->poverty->deletePeriod((int) $id, (int) $user['id']);
            $this->audit($user, 'poverty', 'delete', 'Xóa giai đoạn hộ nghèo/cận nghèo', $id, ['before' => $before]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('Không xóa được giai đoạn', $e), 422);
        }
    }

    public function index(): void
    {
        $this->requirePermission('poverty', 'read');
        $this->ok($this->poverty->recordList($this->filters()));
    }

    public function show(string $id): void
    {
        $this->requirePermission('poverty', 'read');
        $row = $this->poverty->findRecord((int) $id);
        if (!$row) $this->fail('Không tìm thấy bản ghi hộ nghèo/cận nghèo', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('poverty', 'create');
        try {
            $row = $this->poverty->createRecord((array) $this->input(), (int) $user['id'], $this->requestMeta());
            $this->audit($user, 'poverty', 'create', 'Thêm trạng thái hộ nghèo/cận nghèo', $row['id'] ?? null, ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('Không lưu được trạng thái hộ', $e), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('poverty', 'update');
        $before = $this->poverty->findRecord((int) $id);
        if (!$before) $this->fail('Không tìm thấy bản ghi hộ nghèo/cận nghèo', 404);
        try {
            $row = $this->poverty->updateRecord((int) $id, (array) $this->input(), (int) $user['id'], $this->requestMeta());
            $this->audit($user, 'poverty', 'update', 'Cập nhật ghi chú/quyết định hộ nghèo/cận nghèo', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('Không cập nhật được trạng thái hộ', $e), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('poverty', 'delete');
        $before = $this->poverty->findRecord((int) $id);
        if (!$before) $this->fail('Không tìm thấy bản ghi hộ nghèo/cận nghèo', 404);
        try {
            $this->poverty->deleteRecord((int) $id, (int) $user['id'], $this->requestMeta());
            $this->audit($user, 'poverty', 'delete', 'Xóa mềm bản ghi hộ nghèo/cận nghèo', $id, ['before' => $before]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('Không xóa được bản ghi hộ', $e), 422);
        }
    }

    public function dashboard(): void
    {
        $this->requirePermission('poverty', 'read');
        $this->ok($this->poverty->dashboard($this->filters()) + ['generatedAt' => date('c')]);
    }

    public function report(): void
    {
        $this->requirePermission('poverty', 'read');
        $this->ok($this->poverty->report($this->filters()));
    }

    public function householdSearch(): void
    {
        $this->requirePermission('poverty', 'read');
        $this->ok(['items' => $this->poverty->searchHouseholds((string) $this->query('q', $this->query('search', '')))]);
    }

    public function householdHistory(string $householdId): void
    {
        $this->requirePermission('poverty', 'read');
        try {
            $this->ok(['items' => $this->poverty->householdHistory((int) $householdId)]);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('Không tải được lịch sử hộ nghèo/cận nghèo', $e), 422);
        }
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('poverty', 'export');
        $report = $this->poverty->report($this->filters());
        $this->audit($user, 'poverty', 'export', 'Xuất Excel báo cáo hộ nghèo/cận nghèo', null, ['totalRows' => $report['totalRows']]);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="bao-cao-ho-ngheo-can-ngheo-' . date('Ymd_His') . '.xls"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"></head><body><h1>' . htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') . '</h1>';
        foreach ($report['summary'] as $label => $value) echo '<p>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<table border="1"><thead><tr>';
        foreach ($report['headers'] as $header) echo '<th>' . htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($report['rows'] as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    public function exportPdf(): void
    {
        $user = $this->requirePermission('poverty', 'export');
        $report = $this->poverty->report($this->filters());
        $this->audit($user, 'poverty', 'export', 'Xuất PDF báo cáo hộ nghèo/cận nghèo', null, ['totalRows' => $report['totalRows']]);
        $pdf = new SimplePdf();
        $pdf->addPrintHeader(TenantConfig::unitName(), $report['title']);
        $pdf->addMeta('Thời gian xuất: ' . date('d/m/Y H:i:s'));
        foreach ($report['summary'] as $label => $value) $pdf->addMeta($label . ': ' . $value);
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('Trưởng thôn');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bao-cao-ho-ngheo-can-ngheo-' . date('Ymd_His') . '.pdf"');
        echo $pdf->output();
        exit;
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'period_id' => $this->query('period_id', $this->query('periodId', '')),
            'poverty_type' => $this->query('poverty_type', $this->query('povertyType', $this->query('type', ''))),
            'record_status' => $this->query('record_status', $this->query('recordStatus', $this->query('status', ''))),
            'area_code' => $this->query('area_code', $this->query('areaCode', '')),
            'year' => $this->query('year', ''),
            'list' => $this->query('list', ''),
            'sort' => $this->query('sort', 'effective_from'),
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
}
