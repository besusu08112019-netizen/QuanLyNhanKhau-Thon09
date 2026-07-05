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
        $this->ok($this->reports->build($this->reportType(), $this->filters()));
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
        $type = $this->reportType();
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'export', 'Xuất Excel báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->downloadExcel($report);
    }

    public function print(): void
    {
        $user = $this->requirePermission('report', 'read');
        $type = $this->reportType();
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'print', 'In báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->ok($report);
    }

    public function exportPdf(): void
    {
        $user = $this->requirePermission('report', 'export');
        $type = $this->reportType();
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'export', 'Xuất PDF báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->downloadPdf($report);
    }

    private function reportType(): string
    {
        $type = trim((string) $this->query('type', ''));
        if ($type === '') {
            $type = trim((string) $this->query('report_type', ''));
        }
        return $type === '' ? 'summary' : $type;
    }
    private function filters(): array
    {
        $filters = [
            'dateFrom' => $this->nullableQuery('dateFrom'),
            'dateTo' => $this->nullableQuery('dateTo'),
            'householdStatus' => $this->nullableQuery('householdStatus'),
            'householdType' => $this->nullableQueryAny('householdType', ['household_type', 'category']),
            'household_type' => $this->nullableQueryAny('household_type', ['householdType', 'category']),
            'category' => $this->nullableQueryAny('category', ['household_type', 'householdType']),
            'residencyStatus' => $this->nullableQueryAny('residencyStatus', ['residency_status']),
            'presenceStatus' => $this->nullableQueryAny('presenceStatus', ['presence_status']),
            'lifeStatus' => $this->nullableQueryAny('lifeStatus', ['life_status']),
            'gender' => $this->nullableQuery('gender'),
            'ageFrom' => $this->nullableQueryAny('ageFrom', ['age_from']),
            'ageTo' => $this->nullableQueryAny('ageTo', ['age_to']),
            'ethnicity' => $this->nullableQuery('ethnicity'),
            'religion' => $this->nullableQuery('religion'),
            'occupation' => $this->nullableQuery('occupation'),
        ];

        foreach ($this->flagFilterAliases() as $field => $aliases) {
            $filters[$field] = $this->nullableQueryAny($field, $aliases);
        }

        return $filters;
    }

    private function nullableQuery(string $name): ?string
    {
        $value = trim((string) $this->query($name, ''));
        return $value === '' ? null : $value;
    }

    private function nullableQueryAny(string $primary, array $aliases): ?string
    {
        $value = $this->query($primary, null);
        if ($value !== null) {
            $value = trim((string) $value);
            return $value === '' ? null : $value;
        }
        foreach ($aliases as $alias) {
            $value = $this->query($alias, null);
            if ($value !== null) {
                $value = trim((string) $value);
                return $value === '' ? null : $value;
            }
        }
        return null;
    }

    private function flagFilterAliases(): array
    {
        return [
            'party_member' => ['partyMember'],
            'youth_union_member' => ['youthUnionMember'],
            'women_union_member' => ['womenUnionMember', 'women_member', 'womenMember'],
            'farmers_union_member' => ['farmersUnionMember', 'farmer_member', 'farmerMember'],
            'veterans_union_member' => ['veteransUnionMember', 'veteran_member', 'veteranMember'],
            'elderly_union_member' => ['elderlyUnionMember', 'elderly_member', 'elderlyMember'],
            'meritorious_person' => ['meritoriousPerson'],
            'martyr_relative' => ['martyrRelative'],
            'wounded_soldier' => ['woundedSoldier'],
            'sick_soldier' => ['sickSoldier'],
            'disabled_person' => ['disabledPerson', 'disabled'],
            'social_assistance' => ['socialAssistance'],
            'employed' => ['employed'],
            'unemployed' => ['unemployed'],
            'freelance_labor' => ['freelanceLabor'],
            'out_province_labor' => ['outProvinceLabor'],
            'foreign_labor' => ['foreignLabor'],
            'pupil' => ['pupil'],
            'student' => ['student'],
            'retired' => ['retired'],
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
