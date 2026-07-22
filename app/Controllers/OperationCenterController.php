<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\OperationCenter;

final class OperationCenterController extends BaseController
{
    private OperationCenter $operation;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->operation = new OperationCenter();
    }

    public function notifications(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requireOperationalSourcePermissions();
        $this->ok($this->operation->notifications($this->query()));
    }

    public function tasks(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requireOperationalSourcePermissions();
        $this->ok($this->operation->tasks($this->query()));
    }

    public function search(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requirePermission('household', 'read');
        $this->requirePermission('citizen', 'read');
        $this->ok($this->operation->search(trim((string) $this->query('q', $this->query('search', ''))), (int) $this->query('limit', 20)));
    }

    public function quickProfile(): void
    {
        $this->requirePermission('dashboard', 'read');
        $type = (string) $this->query('type', 'household');
        if ($type === 'citizen') {
            $this->requirePermission('citizen', 'read');
            $this->requirePermission('household', 'read');
        } else {
            $this->requirePermission('household', 'read');
            $this->requirePermission('citizen', 'read');
            $this->requirePermission('gis', 'read');
        }
        $this->requirePermission('file', 'read');
        $this->ok($this->operation->quickProfile($type, (int) $this->query('id', 0)));
    }

    public function timeline(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requirePermission('logs', 'read');
        $this->requirePermission('movement', 'read');
        $this->ok($this->operation->timeline($this->query()));
    }

    public function areaDashboard(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requirePermission('household', 'read');
        $this->requirePermission('citizen', 'read');
        $this->requirePermission('gis', 'read');
        $this->ok($this->operation->areaDashboard($this->query()));
    }

    public function progress(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requireOperationalSourcePermissions();
        $this->ok($this->operation->progress($this->query()));
    }

    public function systemLogs(): void
    {
        $this->requirePermission('logs', 'read');
        $this->ok($this->operation->systemLogs($this->query()));
    }

    public function exportReport(): void
    {
        $user = $this->requirePermission('report', 'export');
        $this->requireOperationalSourcePermissions();
        $format = strtolower(trim((string) $this->query('format', 'pdf')));
        $report = $this->operation->executiveReport($this->query());
        $this->audit($user, 'operation_center', 'export', 'Xuất báo cáo điều hành', null, ['format' => $format]);
        if ($format === 'excel' || $format === 'xls') $this->downloadTable($report, 'xls', 'application/vnd.ms-excel');
        if ($format === 'word' || $format === 'doc') $this->downloadTable($report, 'doc', 'application/msword');
        $this->downloadPdf($report);
    }

    public function exportLogs(): void
    {
        $user = $this->requirePermission('logs', 'export');
        $data = $this->operation->systemLogs(array_merge($this->query(), ['pageSize' => 100]));
        $rows = [];
        foreach (($data['data']['items'] ?? []) as $item) {
            $rows[] = [$item['created_at'] ?? '', $item['user_email'] ?? '', $item['module'] ?? '', $item['action'] ?? '', $item['message'] ?? '', $item['ip_address'] ?? ''];
        }
        $report = ['title' => 'Nhật ký hệ thống', 'headers' => ['Thời gian', 'Người thao tác', 'Module', 'Hành động', 'Nội dung', 'IP'], 'rows' => $rows];
        $this->audit($user, 'operation_center', 'export_logs', 'Xuất Excel nhật ký hệ thống');
        $this->downloadTable($report, 'xls', 'application/vnd.ms-excel');
    }

    private function downloadTable(array $report, string $extension, string $contentType): void
    {
        $fileName = $this->slug($report['title'] ?? 'bao_cao') . '_' . date('Ymd_His') . '.' . $extension;
        header('Content-Type: ' . $contentType . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse}td,th{border:1px solid #999;padding:6px}th{background:#eef2f7}</style></head><body>';
        echo '<h2>' . htmlspecialchars((string) ($report['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h2><table><thead><tr>';
        foreach (($report['headers'] ?? []) as $header) echo '<th>' . htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') . '</th>';
        echo '</tr></thead><tbody>';
        foreach (($report['rows'] ?? []) as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    private function downloadPdf(array $report): void
    {
        $fileName = $this->slug($report['title'] ?? 'bao_cao') . '_' . date('Ymd_His') . '.pdf';
        $pdf = new SimplePdf();
        $pdf->addTitle((string) ($report['title'] ?? 'Báo cáo điều hành'));
        $pdf->addMeta('Thời gian xuất: ' . date('d/m/Y H:i:s'));
        $pdf->addTable($report['headers'] ?? [], $report['rows'] ?? []);
        $pdf->addSignatureBlock('Truong thon');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $pdf->output();
        exit;
    }

    private function slug(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $text));
        return trim($text, '_') ?: 'bao_cao';
    }

    private function requireOperationalSourcePermissions(): void
    {
        foreach (['household', 'citizen', 'movement', 'gis'] as $module) {
            $this->requirePermission($module, 'read');
        }
    }
}
