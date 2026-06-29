<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\Report;

final class ReportController extends BaseController
{
    private Report $reports;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->reports = new Report();
    }

    public function summary(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->reports->build((string) $this->query('type', 'summary'), $this->filters()));
    }

    public function population(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->reports->populationReport($this->filters()));
    }

    public function household(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->reports->householdReport($this->filters()));
    }

    public function temporaryResidence(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->reports->temporaryResidenceReport($this->filters()));
    }

    public function temporaryAbsence(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->reports->temporaryAbsenceReport($this->filters()));
    }

    public function births(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->reports->birthReport($this->filters()));
    }

    public function deaths(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->reports->deathReport($this->filters()));
    }

    public function migration(): void
    {
        $this->requirePermission('report', 'read');
        $this->ok($this->reports->migrationReport($this->filters()));
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('report', 'export');
        $type = (string) $this->query('type', 'summary');
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'export', 'Xuất Excel báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->downloadExcel($report);
    }

    public function print(): void
    {
        $user = $this->requirePermission('report', 'read');
        $type = (string) $this->query('type', 'summary');
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'print', 'In báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->ok($report);
    }

    public function exportPdf(): void
    {
        $user = $this->requirePermission('report', 'export');
        $type = (string) $this->query('type', 'summary');
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'export', 'Xuất PDF báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->downloadPdf($report);
    }

    private function filters(): array
    {
        return [
            'dateFrom' => trim((string) $this->query('dateFrom', '')) ?: null,
            'dateTo' => trim((string) $this->query('dateTo', '')) ?: null,
            'householdStatus' => trim((string) $this->query('householdStatus', '')) ?: null,
            'householdType' => trim((string) $this->query('householdType', $this->query('household_type', $this->query('category', '')))) ?: null,
            'household_type' => trim((string) $this->query('household_type', $this->query('householdType', $this->query('category', '')))) ?: null,
            'category' => trim((string) $this->query('category', $this->query('household_type', $this->query('householdType', '')))) ?: null,
            'residencyStatus' => trim((string) $this->query('residencyStatus', '')) ?: null,
            'presenceStatus' => trim((string) $this->query('presenceStatus', '')) ?: null,
            'lifeStatus' => trim((string) $this->query('lifeStatus', '')) ?: null,
            'gender' => trim((string) $this->query('gender', '')) ?: null,
            'ageFrom' => trim((string) $this->query('ageFrom', '')) ?: null,
            'ageTo' => trim((string) $this->query('ageTo', '')) ?: null,
            'ethnicity' => trim((string) $this->query('ethnicity', '')) ?: null,
            'religion' => trim((string) $this->query('religion', '')) ?: null,
            'occupation' => trim((string) $this->query('occupation', '')) ?: null,
            'party_member' => trim((string) $this->query('party_member', $this->query('partyMember', ''))) ?: null,
            'youth_union_member' => trim((string) $this->query('youth_union_member', $this->query('youthUnionMember', ''))) ?: null,
            'meritorious_person' => trim((string) $this->query('meritorious_person', $this->query('meritoriousPerson', ''))) ?: null,
            'disabled_person' => trim((string) $this->query('disabled_person', $this->query('disabledPerson', ''))) ?: null,
        ];
    }

    private function downloadExcel(array $report): void
    {
        $fileName = $this->slug($report['title']) . '_' . date('Ymd_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse}td,th{border:1px solid #999;padding:6px}th{font-weight:bold;background:#eef2f7}</style></head><body>';
        echo '<h2>' . htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
        echo '<p>Thời gian xuất: ' . date('d/m/Y H:i:s') . '</p>';
        echo '<table><thead><tr>';
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

    private function downloadPdf(array $report): void
    {
        $fileName = $this->slug($report['title']) . '_' . date('Ymd_His') . '.pdf';
        $pdf = new SimplePdf();
        $pdf->addTitle($report['title']);
        $pdf->addMeta('Quan Ly Nhan Khau Thon 09 xa Hong Phong');
        $pdf->addMeta('Thoi gian xuat: ' . date('d/m/Y H:i:s'));
        $pdf->addMeta('Tong so dong: ' . (int) $report['totalRows']);
        $pdf->addTable($report['headers'], $report['rows']);
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
}
