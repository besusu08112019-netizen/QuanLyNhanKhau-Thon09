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
        if (!$row) $this->fail('KhÃ´ng tÃ¬m tháº¥y giai Ä‘oáº¡n', 404);
        $this->ok($row);
    }

    public function storePeriod(): void
    {
        $user = $this->requirePermission('poverty', 'create');
        try {
            $row = $this->poverty->savePeriod((array) $this->input(), (int) $user['id']);
            $this->safeAudit($user, 'poverty', 'create', 'ThÃªm giai Ä‘oáº¡n há»™ nghÃ¨o/cáº­n nghÃ¨o', $row['id'] ?? null, ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('KhÃ´ng lÆ°u Ä‘Æ°á»£c giai Ä‘oáº¡n', $e), 422);
        }
    }

    public function updatePeriod(string $id): void
    {
        $user = $this->requirePermission('poverty', 'update');
        $before = $this->poverty->findPeriod((int) $id);
        if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y giai Ä‘oáº¡n', 404);
        try {
            $row = $this->poverty->savePeriod((array) $this->input(), (int) $user['id'], (int) $id);
            $this->safeAudit($user, 'poverty', 'update', 'Cáº­p nháº­t giai Ä‘oáº¡n há»™ nghÃ¨o/cáº­n nghÃ¨o', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('KhÃ´ng cáº­p nháº­t Ä‘Æ°á»£c giai Ä‘oáº¡n', $e), 422);
        }
    }

    public function deletePeriod(string $id): void
    {
        $user = $this->requirePermission('poverty', 'delete');
        $before = $this->poverty->findPeriod((int) $id);
        if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y giai Ä‘oáº¡n', 404);
        try {
            $this->poverty->deletePeriod((int) $id, (int) $user['id']);
            $this->safeAudit($user, 'poverty', 'delete', 'XÃ³a giai Ä‘oáº¡n há»™ nghÃ¨o/cáº­n nghÃ¨o', $id, ['before' => $before]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('KhÃ´ng xÃ³a Ä‘Æ°á»£c giai Ä‘oáº¡n', $e), 422);
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
        if (!$row) $this->fail('KhÃ´ng tÃ¬m tháº¥y báº£n ghi há»™ nghÃ¨o/cáº­n nghÃ¨o', 404);
        $this->ok($row);
    }

    public function store(): void
    {
        $user = $this->requirePermission('poverty', 'create');
        try {
            $row = $this->poverty->createRecord((array) $this->input(), (int) $user['id'], $this->requestMeta());
            $this->safeAudit($user, 'poverty', 'create', 'ThÃªm tráº¡ng thÃ¡i há»™ nghÃ¨o/cáº­n nghÃ¨o', $row['id'] ?? null, ['after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('KhÃ´ng lÆ°u Ä‘Æ°á»£c tráº¡ng thÃ¡i há»™', $e), 422);
        }
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('poverty', 'update');
        $before = $this->poverty->findRecord((int) $id);
        if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y báº£n ghi há»™ nghÃ¨o/cáº­n nghÃ¨o', 404);
        try {
            $row = $this->poverty->updateRecord((int) $id, (array) $this->input(), (int) $user['id'], $this->requestMeta());
            $this->safeAudit($user, 'poverty', 'update', 'Cáº­p nháº­t ghi chÃº/quyáº¿t Ä‘á»‹nh há»™ nghÃ¨o/cáº­n nghÃ¨o', $id, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('KhÃ´ng cáº­p nháº­t Ä‘Æ°á»£c tráº¡ng thÃ¡i há»™', $e), 422);
        }
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('poverty', 'delete');
        $before = $this->poverty->findRecord((int) $id);
        if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y báº£n ghi há»™ nghÃ¨o/cáº­n nghÃ¨o', 404);
        try {
            $this->poverty->deleteRecord((int) $id, (int) $user['id'], $this->requestMeta());
            $this->safeAudit($user, 'poverty', 'delete', 'XÃ³a má»m báº£n ghi há»™ nghÃ¨o/cáº­n nghÃ¨o', $id, ['before' => $before]);
            $this->ok(['id' => (int) $id]);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage('KhÃ´ng xÃ³a Ä‘Æ°á»£c báº£n ghi há»™', $e), 422);
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
            $this->fail($this->safeExceptionMessage('KhÃ´ng táº£i Ä‘Æ°á»£c lá»‹ch sá»­ há»™ nghÃ¨o/cáº­n nghÃ¨o', $e), 422);
        }
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('poverty', 'export');
        $report = $this->poverty->report($this->filters());
        $this->safeAudit($user, 'poverty', 'export', 'Xuáº¥t Excel bÃ¡o cÃ¡o há»™ nghÃ¨o/cáº­n nghÃ¨o', null, ['totalRows' => $report['totalRows']]);
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
        $this->safeAudit($user, 'poverty', 'export', 'Xuáº¥t PDF bÃ¡o cÃ¡o há»™ nghÃ¨o/cáº­n nghÃ¨o', null, ['totalRows' => $report['totalRows']]);
        $pdf = new SimplePdf();
        $pdf->addPrintHeader(TenantConfig::unitName(), $report['title']);
        $pdf->addMeta('Thá»i gian xuáº¥t: ' . date('d/m/Y H:i:s'));
        foreach ($report['summary'] as $label => $value) $pdf->addMeta($label . ': ' . $value);
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock('TrÆ°á»Ÿng thÃ´n');
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

    private function safeAudit(?array $user, string $module, string $action, string $message, mixed $entityId = null, array $metadata = [], string $level = 'INFO'): void
    {
        try {
            $this->audit($user, $module, $action, $message, $entityId, $metadata, $level);
        } catch (Throwable $e) {
            error_log('[POVERTY_AUDIT_ERROR] ' . $e->getMessage());
        }
    }
}
