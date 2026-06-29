<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Citizen;

final class PersonController extends BaseController
{
    private Citizen $citizens;

    private const FLAG_FILTERS = [
        'party_member' => ['partyMember'],
        'youth_union_member' => ['youthUnionMember'],
        'women_union_member' => ['womenUnionMember', 'women_member', 'womenMember'],
        'farmers_union_member' => ['farmersUnionMember', 'farmer_member', 'farmerMember', 'farmers_member'],
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

    public function __construct($request) { parent::__construct($request); $this->citizens = new Citizen(); }

    public function index(): void
    {
        $this->requirePermission('citizen', 'read');
        $this->ok($this->citizens->paginate($this->personFilters()));
    }

    public function temporaryResidence(): void
    {
        $this->requirePermission('citizen', 'read');
        $filters = $this->personFilters();
        $filters['residencyStatus'] = 'TEMPORARY';
        $this->ok($this->citizens->paginate($filters));
    }

    public function temporaryAbsence(): void
    {
        $this->requirePermission('citizen', 'read');
        $filters = $this->personFilters();
        $filters['presenceStatus'] = 'AWAY';
        $this->ok($this->citizens->paginate($filters));
    }

    public function show(string $id): void { $this->requirePermission('citizen', 'read'); $row = $this->citizens->find((int) $id); $row ? $this->ok($row) : $this->fail('Không tìm thấy nhân khẩu', 404); }
    public function store(): void { $user = $this->requirePermission('citizen', 'create'); $row = $this->citizens->create($this->input(), (int) $user['id']); $this->audit($user, 'citizen', 'create', 'Tạo nhân khẩu', $row['id']); $this->ok($row); }
    public function update(string $id): void { $user = $this->requirePermission('citizen', 'update'); $row = $this->citizens->update((int) $id, $this->input(), (int) $user['id']); $this->audit($user, 'citizen', 'update', 'Cập nhật nhân khẩu', $id); $this->ok($row); }
    public function destroy(string $id): void { $user = $this->requirePermission('citizen', 'delete'); $this->citizens->softDelete((int) $id, (int) $user['id']); $this->audit($user, 'citizen', 'delete', 'Xóa mềm nhân khẩu', $id); $this->ok(['id' => (int) $id]); }
    public function restore(string $id): void { $user = $this->requirePermission('citizen', 'update'); $this->citizens->restore((int) $id, (int) $user['id']); $this->audit($user, 'citizen', 'update', 'Khôi phục nhân khẩu', $id); $this->ok(['id' => (int) $id]); }
    public function bulkDelete(): void { $user = $this->requirePermission('citizen', 'delete'); $ids = (array) $this->input('ids', []); $success = 0; $errors = []; foreach ($ids as $id) { try { $this->citizens->softDelete((int) $id, (int) $user['id']); $success++; } catch (\Throwable $e) { $errors[] = ['id' => $id, 'message' => $e->getMessage()]; } } $this->audit($user, 'citizen', 'delete', 'Xóa nhiều nhân khẩu', null, ['ids' => $ids, 'success' => $success, 'errors' => $errors]); $this->ok(['success' => $success, 'errors' => $errors]); }

    private function personFilters(): array
    {
        $filters = [
            'page' => $this->query('page', 1),
            'pageSize' => $this->query('pageSize', 20),
            'search' => $this->query('search', $this->query('q', '')),
            'status' => $this->query('status', ''),
            'presenceStatus' => $this->query('presenceStatus', $this->query('presence_status', '')),
            'residencyStatus' => $this->query('residencyStatus', $this->query('residency_status', '')),
            'householdId' => $this->query('householdId', $this->query('householdCode', '')),
            'household_type' => $this->queryAny('household_type', ['householdType', 'category']),
            'gender' => $this->query('gender', ''),
            'ageFrom' => $this->query('ageFrom', $this->query('age_from', '')),
            'ageTo' => $this->query('ageTo', $this->query('age_to', '')),
            'ethnicity' => $this->query('ethnicity', ''),
            'religion' => $this->query('religion', ''),
            'occupation' => $this->query('occupation', ''),
        ];

        foreach (self::FLAG_FILTERS as $field => $aliases) {
            $filters[$field] = $this->queryAny($field, $aliases);
        }

        return $filters;
    }

    private function queryAny(string $primary, array $aliases): string
    {
        $value = $this->query($primary, null);
        if ($value !== null) return trim((string) $value);
        foreach ($aliases as $alias) {
            $value = $this->query($alias, null);
            if ($value !== null) return trim((string) $value);
        }
        return '';
    }
}
