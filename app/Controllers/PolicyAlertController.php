<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\PolicyAlert;

final class PolicyAlertController extends BaseController
{
    private PolicyAlert $alerts;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->alerts = new PolicyAlert();
    }

    public function summary(): void
    {
        $this->requirePermission('citizen', 'read');
        $this->ok($this->alerts->summary());
    }

    public function index(): void
    {
        $this->requirePermission('citizen', 'read');
        $this->ok($this->alerts->paginate($this->query()));
    }

    public function mark(string $citizenId): void
    {
        $user = $this->requirePermission('citizen', 'update');
        $input = (array) $this->input();
        $row = $this->alerts->mark((int) $citizenId, (string) ($input['alert_key'] ?? $input['type'] ?? ''), (string) ($input['status'] ?? ''), (int) $user['id'], (string) ($input['note'] ?? ''));
        $this->audit($user, 'citizen', 'policy_alert', 'Cập nhật cảnh báo chính sách', $citizenId, ['after' => $row]);
        $this->ok($row);
    }

    public function report(): void
    {
        $this->requirePermission('citizen', 'read');
        $this->ok($this->alerts->report($this->query()));
    }

    public function print(): void
    {
        $this->report();
    }

    public function exportExcel(): void
    {
        $this->requirePermission('citizen', 'export');
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="canh-bao-chinh-sach-' . date('Ymd_His') . '.xls"');
        echo $this->alerts->excel($this->query());
        exit;
    }

    public function exportPdf(): void
    {
        $this->requirePermission('citizen', 'export');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="canh-bao-chinh-sach-' . date('Ymd_His') . '.pdf"');
        echo $this->alerts->pdf($this->query());
        exit;
    }
}
