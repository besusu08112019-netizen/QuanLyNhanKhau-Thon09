<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\SimplePdf;
use App\Models\Report;
use App\Models\SystemSetting;
use Throwable;

final class ReportController extends BaseController
{
    private Report $reports;
    private ?SystemSetting $settings = null;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->reports = new Report();
    }

    public function summary(): void
    {
        $this->requirePermission('report', 'read');
        $type = $this->reportType();
        $this->requireReportSourcePermissions($type);
        $this->ok($this->reports->build($type, $this->filters()));
    }

    public function population(): void
    {
        $this->requirePermission('report', 'read');
        $this->requireReportSourcePermissions('population');
        $this->ok($this->reports->populationReport($this->filters()));
    }

    public function household(): void
    {
        $this->requirePermission('report', 'read');
        $this->requireReportSourcePermissions('household');
        $this->ok($this->reports->householdReport($this->filters()));
    }

    public function temporaryResidence(): void
    {
        $this->requirePermission('report', 'read');
        $this->requireReportSourcePermissions('temporary-residence');
        $this->ok($this->reports->temporaryResidenceReport($this->filters()));
    }

    public function temporaryAbsence(): void
    {
        $this->requirePermission('report', 'read');
        $this->requireReportSourcePermissions('temporary-absence');
        $this->ok($this->reports->temporaryAbsenceReport($this->filters()));
    }

    public function births(): void
    {
        $this->requirePermission('report', 'read');
        $this->requireReportSourcePermissions('births');
        $this->ok($this->reports->birthReport($this->filters()));
    }

    public function deaths(): void
    {
        $this->requirePermission('report', 'read');
        $this->requireReportSourcePermissions('deaths');
        $this->ok($this->reports->deathReport($this->filters()));
    }

    public function migration(): void
    {
        $this->requirePermission('report', 'read');
        $this->requireReportSourcePermissions('migration');
        $this->ok($this->reports->migrationReport($this->filters()));
    }

    public function exportExcel(): void
    {
        $user = $this->requirePermission('report', 'export');
        $type = $this->reportType();
        $this->requireReportSourcePermissions($type);
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'export', 'Xuất Excel báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->downloadExcel($report);
    }

    public function print(): void
    {
        $user = $this->requirePermission('report', 'print');
        $type = $this->reportType();
        $this->requireReportSourcePermissions($type);
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'print', 'In báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->ok($report);
    }

    public function exportPdf(): void
    {
        $user = $this->requirePermission('report', 'export');
        $type = $this->reportType();
        $this->requireReportSourcePermissions($type);
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'export', 'Xuất PDF báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->downloadPdf($report);
    }


    public function exportWord(): void
    {
        $user = $this->requirePermission('report', 'export');
        $type = $this->reportType();
        $this->requireReportSourcePermissions($type);
        $report = $this->reports->build($type, $this->filters());
        $this->audit($user, 'report', 'export', 'Xuất Word báo cáo ' . $type, null, ['type' => $type, 'totalRows' => $report['totalRows']]);
        $this->downloadWord($report);
    }

    public function center(): void
    {
        $this->safeJson('center', fn() => $this->reports->center());
    }

    public function bi(): void
    {
        $this->safeJson('bi', fn() => $this->reports->biDashboard($this->filters()));
    }

    public function templates(): void
    {
        $user = $this->requirePermission('report', 'read');
        $this->ok(['ok' => true, 'widget' => 'templates', 'data' => $this->reports->templates((int) ($user['id'] ?? 0)), 'generatedAt' => date('c')]);
    }

    public function saveTemplate(): void
    {
        $user = $this->requirePermission('report', 'update');
        $input = is_array($this->input()) ? $this->input() : [];
        $template = $this->reports->saveTemplate((int) ($user['id'] ?? 0), $input);
        $this->audit($user, 'report', 'save_template', 'Lưu mẫu báo cáo', $template['id'] ?? null, ['type' => $template['type'] ?? null]);
        $this->ok($template);
    }

    public function deleteTemplate(string $id): void
    {
        $user = $this->requirePermission('report', 'delete');
        $this->reports->deleteTemplate((int) ($user['id'] ?? 0), (int) $id);
        $this->audit($user, 'report', 'delete_template', 'Xóa mẫu báo cáo', $id);
        $this->ok(['deleted' => true, 'id' => (int) $id]);
    }

    public function defaultTemplate(string $id): void
    {
        $user = $this->requirePermission('report', 'update');
        $this->reports->setDefaultTemplate((int) ($user['id'] ?? 0), (int) $id);
        $this->audit($user, 'report', 'default_template', 'Đặt mẫu báo cáo mặc định', $id);
        $this->ok(['default' => true, 'id' => (int) $id]);
    }

    private function reportType(): string
    {
        $type = trim((string) $this->query('type', ''));
        if ($type === '') {
            $type = trim((string) $this->query('report_type', ''));
        }
        return $type === '' ? 'summary' : $type;
    }

    private function requireReportSourcePermissions(string $type): void
    {
        foreach ($this->sourceModulesForReportType($type) as $module) {
            $this->requirePermission($module, 'read');
        }
    }

    private function sourceModulesForReportType(string $type): array
    {
        $type = strtolower(str_replace('_', '-', trim($type)));
        return match (true) {
            $type === '' || $type === 'summary' => ['household', 'citizen'],
            str_starts_with($type, 'household-business') || str_starts_with($type, 'business-') => ['household_business'],
            str_starts_with($type, 'livestock') => ['livestock'],
            str_starts_with($type, 'vehicle') || str_starts_with($type, 'vehicles') => ['vehicles'],
            str_starts_with($type, 'contribution') || str_starts_with($type, 'household-contribution') => ['contributions'],
            str_starts_with($type, 'agriculture') => ['agriculture'],
            str_starts_with($type, 'house-') || str_starts_with($type, 'houses') => ['houses'],
            str_starts_with($type, 'public-asset') || str_starts_with($type, 'public-assets') => ['public_assets'],
            str_starts_with($type, 'gis') => ['gis', 'household'],
            str_starts_with($type, 'digital-profile') || str_starts_with($type, 'profile-') => ['household', 'citizen', 'file'],
            in_array($type, ['population', 'citizen', 'citizens', 'gender', 'age', 'residency', 'health-insurance', 'health-insurance-missing', 'health-insurance-expiring', 'health-insurance-expired', 'health-insurance-household', 'health-insurance-area', 'party-members', 'party-member', 'party', 'youth-union', 'youth-union-member', 'meritorious-people', 'meritorious', 'meritorious-person', 'disabled-people', 'disabled', 'disabled-person', 'labor', 'labour', 'elderly', 'children'], true) => ['citizen'],
            in_array($type, ['household', 'households', 'poor-households', 'near-poor-households', 'special'], true) => ['household'],
            in_array($type, ['temporary-residence', 'temporary', 'temporary-absence', 'absence', 'births', 'birth', 'deaths', 'death', 'migration', 'movement', 'movement-summary'], true) => ['citizen', 'movement'],
            default => ['household', 'citizen'],
        };
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
            'search' => $this->nullableQueryAny('search', ['q']),
            'land_type' => $this->nullableQueryAny('land_type', ['landType']),
            'usage_form' => $this->nullableQueryAny('usage_form', ['usageForm']),
            'crop' => $this->nullableQuery('crop'),
            'season' => $this->nullableQuery('season'),
            'status' => $this->nullableQuery('status'),
            'year' => $this->nullableQuery('year'),
            'campaign_id' => $this->nullableQueryAny('campaign_id', ['campaignId']),
            'campaignId' => $this->nullableQueryAny('campaignId', ['campaign_id']),
            'payment_status' => $this->nullableQueryAny('payment_status', ['paymentStatus']),
            'paymentStatus' => $this->nullableQueryAny('paymentStatus', ['payment_status']),
            'area_code' => $this->nullableQueryAny('area_code', ['areaCode', 'area']),
            'areaCode' => $this->nullableQueryAny('areaCode', ['area_code', 'area']),
            'contribution_name' => $this->nullableQueryAny('contribution_name', ['contributionName']),
            'contributionName' => $this->nullableQueryAny('contributionName', ['contribution_name']),
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
            'has_health_insurance' => ['hasHealthInsurance', 'health_insurance', 'healthInsurance'],
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
        echo '<html><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;color:#111}.report-print-masthead{display:grid;grid-template-columns:1fr 1.35fr 1fr;gap:8mm;align-items:start;margin-bottom:12mm}.report-print-agency{text-align:left}.report-print-agency-primary{font-weight:700;text-transform:uppercase;font-size:13px}.report-print-agency-secondary{font-size:11px;margin-top:2px}.report-print-national{text-align:center}.report-print-national-title{font-weight:700;text-transform:uppercase;font-size:13px}.report-print-national-subtitle{display:inline-block;border-bottom:1px solid #111;font-weight:700;font-size:12px;padding-bottom:2px}.report-print-title{text-align:center;text-transform:uppercase;font-size:20px;font-weight:700;margin:0 0 10mm}.report-print-meta{margin:8px 0 12px;line-height:1.45}table{border-collapse:collapse;table-layout:fixed}td,th{border:1px solid #999;padding:6px;word-break:break-word}th{font-weight:bold;background:#eef2f7}</style></head><body>';
        $this->echoReportHeaderHtml($report);
        $this->echoReportMetaHtml($report);
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
        $pdf->addPrintHeader($this->reportUnitName($report), $report['title']);
        foreach ($this->reportMetaLines($report) as $line) $pdf->addMeta($line);
        $pdf->addMeta('Thoi gian xuat: ' . date('d/m/Y H:i:s'));
        $pdf->addMeta('Tong so dong: ' . (int) $report['totalRows']);
        $pdf->addTable($report['headers'], $report['rows']);
        $pdf->addSignatureBlock((string) ($report['meta']['approved_by'] ?? 'Truong thon'));
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $pdf->output();
        exit;
    }


    private function downloadWord(array $report): void
    {
        $fileName = $this->slug($report['title']) . '_' . date('Ymd_His') . '.doc';
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"><style>@page{size:A4;margin:16mm 14mm 20mm}body{font-family:Arial,sans-serif;color:#111}.report-print-masthead{display:grid;grid-template-columns:1fr 1.35fr 1fr;gap:8mm;align-items:start;margin-bottom:12mm}.report-print-agency{text-align:left}.report-print-agency-primary{font-weight:700;text-transform:uppercase;font-size:13px}.report-print-agency-secondary{font-size:11px;margin-top:2px}.report-print-national{text-align:center}.report-print-national-title{font-weight:700;text-transform:uppercase;font-size:13px}.report-print-national-subtitle{display:inline-block;border-bottom:1px solid #111;font-weight:700;font-size:12px;padding-bottom:2px}.report-print-title{text-align:center;text-transform:uppercase;font-size:20px;font-weight:700;margin:0 0 10mm}.report-print-meta{margin:8px 0 12px;line-height:1.45}table{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}td,th{border:1px solid #555;padding:6px;vertical-align:top;word-break:break-word}th{background:#eef2f7}</style></head><body>';
        $this->echoReportHeaderHtml($report);
        $this->echoReportMetaHtml($report);
        echo '<p>Thời gian xuất: ' . date('d/m/Y H:i:s') . '</p><table><thead><tr>';
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

    private function safeJson(string $widget, callable $callback): void
    {
        try {
            $this->requirePermission('report', 'read');
            $this->ok(['ok' => true, 'widget' => $widget, 'data' => $callback(), 'generatedAt' => date('c')]);
        } catch (Throwable $exception) {
            error_log('[SMART_REPORTING_API_ERROR] ' . json_encode(['widget' => $widget, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $message = $this->debugEnabled() ? $exception->getMessage() : json_decode('"Kh\u00f4ng t\u1ea3i \u0111\u01b0\u1ee3c d\u1eef li\u1ec7u b\u00e1o c\u00e1o"', true);
            $this->ok(['ok' => true, 'widget' => $widget, 'data' => [], 'error' => ['message' => $message], 'generatedAt' => date('c')]);
        }
    }

    private function slug(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $text));
        return trim($text, '_') ?: 'bao_cao';
    }
    private function reportMetaLines(array $report): array
    {
        $meta = is_array($report['meta'] ?? null) ? $report['meta'] : [];
        $lines = [];
        foreach (['period_label', 'prepared_by', 'approved_by', 'report_date'] as $key) {
            $value = trim((string) ($meta[$key] ?? ''));
            if ($value !== '') $lines[] = $value;
        }
        return $lines;
    }

    private function reportUnitName(array $report): string
    {
        $meta = is_array($report['meta'] ?? null) ? $report['meta'] : [];
        $unit = trim((string) ($meta['unit_name'] ?? ''));
        if ($unit !== '') return $unit;

        try {
            $settings = ($this->settings ??= new SystemSetting())->all();
            $configured = trim((string) ($settings['unitName'] ?? ''));
            if ($configured !== '') return $configured;
            $hamlet = trim((string) ($settings['hamletName'] ?? ''));
            $commune = trim((string) ($settings['communeName'] ?? ''));
            $combined = trim($hamlet . ($hamlet !== '' && $commune !== '' ? ' - ' : '') . $commune);
            if ($combined !== '') return $combined;
        } catch (Throwable) {
        }

        return 'Thôn 09 - Xã Hồng Phong';
    }

    private function echoReportHeaderHtml(array $report): void
    {
        echo '<div class="report-print-masthead">';
        echo '<div class="report-print-agency"><div class="report-print-agency-primary">T&#7880;NH NINH B&#204;NH</div><div class="report-print-agency-secondary">Th&#244;n 09, x&#227; H&#7891;ng Phong</div></div>';
        echo '<div class="report-print-national"><div class="report-print-national-title">C&#7896;NG H&#210;A X&#195; H&#7896;I CH&#7910; NGH&#296;A VI&#7878;T NAM</div><div class="report-print-national-subtitle">&#272;&#7897;c l&#7853;p - T&#7921; do - H&#7841;nh ph&#250;c</div></div>';
        echo '<div></div></div>';
        echo '<div class="report-print-title">' . htmlspecialchars((string) ($report['title'] ?? 'Báo cáo'), ENT_QUOTES, 'UTF-8') . '</div>';
    }

    private function echoReportMetaHtml(array $report): void
    {
        $lines = $this->reportMetaLines($report);
        if (!$lines) return;
        echo '<div class="report-print-meta">';
        foreach ($lines as $line) echo '<div>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';
    }
}
