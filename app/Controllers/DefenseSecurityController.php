<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\DefenseSecurity;
use Throwable;

final class DefenseSecurityController extends BaseController
{
    private DefenseSecurity $defense;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->defense = new DefenseSecurity();
    }

    public function catalogs(): void { $this->requirePermission('defense_security', 'read'); $this->ok($this->defense->catalogs()); }
    public function dashboard(): void { $this->requirePermission('defense_security', 'read'); $this->ok($this->defense->dashboard($this->filters())); }
    public function citizenSearch(): void { $this->requirePermission('defense_security', 'read'); $this->ok(['items' => $this->defense->searchCitizens((string) $this->query('q', $this->query('search', '')))]); }
    public function citizenSummary(string $citizenId): void { $this->requirePermission('defense_security', 'read'); $this->ok($this->defense->citizenSummary((int) $citizenId)); }

    public function nvqsIndex(): void { $this->requirePermission('defense_security', 'read'); $this->ok($this->defense->paginateNvqs($this->filters())); }
    public function nvqsShow(string $id): void { $this->showRecord($this->defense->findNvqs((int) $id), 'KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ NVQS'); }
    public function nvqsStore(): void { $this->saveRecord('nvqs', null); }
    public function nvqsUpdate(string $id): void { $this->saveRecord('nvqs', (int) $id); }
    public function nvqsDelete(string $id): void { $this->deleteRecord('nvqs', (int) $id); }

    public function militiaIndex(): void { $this->requirePermission('defense_security', 'read'); $this->ok($this->defense->paginateMilitia($this->filters())); }
    public function militiaShow(string $id): void { $this->showRecord($this->defense->findMilitia((int) $id), 'KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ dÃ¢n quÃ¢n'); }
    public function militiaStore(): void { $this->saveRecord('militia', null); }
    public function militiaUpdate(string $id): void { $this->saveRecord('militia', (int) $id); }
    public function militiaDelete(string $id): void { $this->deleteRecord('militia', (int) $id); }

    public function securityForceIndex(): void { $this->requirePermission('defense_security', 'read'); $this->ok($this->defense->paginateSecurityForce($this->filters())); }
    public function securityForceShow(string $id): void { $this->showRecord($this->defense->findSecurityForce((int) $id), 'KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ ANTT cÆ¡ sá»Ÿ'); }
    public function securityForceStore(): void { $this->saveRecord('security_force', null); }
    public function securityForceUpdate(string $id): void { $this->saveRecord('security_force', (int) $id); }
    public function securityForceDelete(string $id): void { $this->deleteRecord('security_force', (int) $id); }

    private function showRecord(?array $row, string $message): void
    {
        $this->requirePermission('defense_security', 'read');
        if (!$row) $this->fail($message, 404);
        $this->ok($row);
    }

    private function saveRecord(string $type, ?int $id): void
    {
        $user = $this->requirePermission('defense_security', $id ? 'update' : 'create');
        $input = (array) $this->input();
        $this->requireInputFields($input, ['citizen_id' => 'NhÃ¢n kháº©u']);
        try {
            $before = null;
            if ($type === 'nvqs') { $before = $id ? $this->defense->findNvqs($id) : null; $row = $this->defense->saveNvqs($input, (int) $user['id'], $id); }
            elseif ($type === 'militia') { $before = $id ? $this->defense->findMilitia($id) : null; $row = $this->defense->saveMilitia($input, (int) $user['id'], $id); }
            else { $before = $id ? $this->defense->findSecurityForce($id) : null; $row = $this->defense->saveSecurityForce($input, (int) $user['id'], $id); }
            $action = $id ? 'update' : 'create';
            $this->audit($user, 'defense_security', $action, ($id ? 'Cáº­p nháº­t ' : 'ThÃªm ') . $type, $row['id'] ?? null, ['before' => $before, 'after' => $row]);
            $this->ok($row);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage($e->getMessage(), $e), 422);
        }
    }

    private function deleteRecord(string $type, int $id): void
    {
        $user = $this->requirePermission('defense_security', 'delete');
        try {
            if ($type === 'nvqs') { $before = $this->defense->findNvqs($id); $this->defense->deleteNvqs($id, (int) $user['id']); }
            elseif ($type === 'militia') { $before = $this->defense->findMilitia($id); $this->defense->deleteMilitia($id, (int) $user['id']); }
            else { $before = $this->defense->findSecurityForce($id); $this->defense->deleteSecurityForce($id, (int) $user['id']); }
            $this->audit($user, 'defense_security', 'delete', 'XÃ³a ' . $type, $id, ['before' => $before, 'after' => null]);
            $this->ok(['id' => $id]);
        } catch (Throwable $e) {
            $this->fail($this->safeExceptionMessage($e->getMessage(), $e), 422);
        }
    }

    private function filters(): array
    {
        return [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'year' => $this->query('year', $this->query('report_year', date('Y'))),
            'metric' => $this->query('metric', ''),
            'registered_status' => $this->query('registered_status', ''),
            'preliminary_status' => $this->query('preliminary_status', ''),
            'medical_exam_status' => $this->query('medical_exam_status', ''),
            'eligibility_status' => $this->query('eligibility_status', ''),
            'selection_status' => $this->query('selection_status', ''),
            'participation_status' => $this->query('participation_status', $this->query('status', '')),
            'sort' => $this->query('sort', ''),
            'direction' => $this->query('direction', 'ASC'),
        ];
    }
}

