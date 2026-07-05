<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Citizen;
use App\Services\PopulationMovementService;

final class PersonController extends BaseController
{
    private Citizen $citizens;
    private PopulationMovementService $movementService;

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

    public function __construct($request)
    {
        parent::__construct($request);
        $this->citizens = new Citizen();
        $this->movementService = new PopulationMovementService();
    }

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

    public function show(string $id): void
    {
        $this->requirePermission('citizen', 'read');
        $row = $this->citizens->find((int) $id);
        $row ? $this->ok($row) : $this->fail('Không tìm thấy nhân khẩu', 404);
    }

    public function store(): void
    {
        $user = $this->requirePermission('citizen', 'create');
        $input = $this->input();
        $this->requireInputFields((array) $input, ['householdCode' => 'Mã hộ', 'fullName' => 'Họ và tên', 'dateOfBirth' => 'Ngày sinh']);
        $row = $this->citizens->create($input, (int) $user['id']);
        $this->movementService->afterCitizenCreated($row, $input, (int) $user['id']);
        $row = $this->citizens->find((int) $row['id']) ?: $row;
        $this->audit($user, 'citizen', 'create', 'Tạo nhân khẩu và ghi biến động dân cư', $row['id']);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('citizen', 'update');
        $before = $this->citizens->find((int) $id);
        if (!$before) $this->fail('Không tìm thấy nhân khẩu', 404);
        $input = $this->input();
        $row = $this->citizens->update((int) $id, $input, (int) $user['id']);
        $this->movementService->afterCitizenUpdated($before, $row, $input, (int) $user['id']);
        $row = $this->citizens->find((int) $id) ?: $row;
        $this->audit($user, 'citizen', 'update', 'Cập nhật nhân khẩu và ghi biến động dân cư', $id);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('citizen', 'delete');
        $this->movementService->markCitizenMovedOut((int) $id, $this->input(), (int) $user['id']);
        $this->audit($user, 'citizen', 'delete', 'Chuyển nhân khẩu khỏi dân cư hiện tại', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function restore(string $id): void
    {
        $user = $this->requirePermission('citizen', 'update');
        $this->citizens->restore((int) $id, (int) $user['id']);
        $this->audit($user, 'citizen', 'update', 'Khôi phục nhân khẩu', $id);
        $this->ok(['id' => (int) $id]);
    }

    public function bulkDelete(): void
    {
        $user = $this->requirePermission('citizen', 'delete');
        $ids = (array) $this->input('ids', []);
        $deleted = $this->movementService->markCitizensMovedOut($ids, $this->input(), (int) $user['id']);
        $this->audit($user, 'citizen', 'delete', 'Chuyển hàng loạt nhân khẩu khỏi dân cư hiện tại', null, ['ids' => array_values(array_map('intval', $ids)), 'deleted' => $deleted]);
        $this->ok(['success' => $deleted, 'errors' => []]);
    }

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
            'maritalStatus' => $this->query('maritalStatus', $this->query('marital_status', '')),
            'educationLevel' => $this->query('educationLevel', $this->query('education_level', '')),
            'workplace' => $this->query('workplace', ''),
            'nationality' => $this->query('nationality', ''),
            'bloodType' => $this->query('bloodType', $this->query('blood_type', '')),
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
