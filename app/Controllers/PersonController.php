<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Citizen;
use App\Models\ProfileSummary;
use App\Services\PopulationMovementService;

final class PersonController extends BaseController
{
    private Citizen $citizens;
    private ProfileSummary $summary;
    private PopulationMovementService $movementService;

    private const FLAG_FILTERS = [
        'party_member' => ['partyMember'],
        'youth_union_member' => ['youthUnionMember'],
        'women_union_member' => ['womenUnionMember', 'women_member', 'womenMember'],
        'farmers_union_member' => ['farmersUnionMember', 'farmer_member', 'farmerMember', 'farmers_member'],
        'veterans_union_member' => ['veteransUnionMember', 'veteran_member', 'veteranMember'],
        'elderly_union_member' => ['elderlyUnionMember', 'elderly_member', 'elderlyMember'],
        'martyr_relative' => ['martyrRelative'],
        'wounded_soldier' => ['woundedSoldier'],
        'sick_soldier' => ['sickSoldier'],
        'chemical_warfare_victim' => ['chemicalWarfareVictim'],
        'imprisoned_resistance_activist' => ['imprisonedResistanceActivist'],
        'youth_volunteer' => ['youthVolunteer'],
        'resistance_hero' => ['resistanceHero'],
        'revolutionary_activist' => ['revolutionaryActivist'],
        'meritorious_person' => ['meritoriousPerson'],
        'disabled_person' => ['disabledPerson', 'disabled'],
        'social_assistance' => ['socialAssistance'],
        'has_health_insurance' => ['hasHealthInsurance', 'health_insurance', 'healthInsurance'],
        'employed' => ['employed'],
        'unemployed' => ['unemployed'],
        'freelance_labor' => ['freelanceLabor'],
        'out_province_labor' => ['outProvinceLabor'],
        'foreign_labor' => ['foreignLabor'],
        'not_attending_school' => ['notAttendingSchool', 'not_school', 'notSchool'],
        'pupil' => ['pupil'],
        'student' => ['student'],
        'retired' => ['retired'],
    ];

    public function __construct($request)
    {
        parent::__construct($request);
        $this->citizens = new Citizen();
        $this->summary = new ProfileSummary();
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
        if ($row) {
            $row['related_summary'] = $this->summary->person((int) $id);
        }
        $row ? $this->ok($row) : $this->fail('KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u', 404);
    }

    public function store(): void
    {
        $user = $this->requirePermission('citizen', 'create');
        $input = $this->input();
        $householdKey = trim((string) ($input['householdId'] ?? $input['householdCode'] ?? $input['household_id'] ?? $input['household_code'] ?? ''));
        if ($householdKey === '') $this->fail('Vui lÃ²ng chá»n há»™ gia Ä‘Ã¬nh tá»« danh sÃ¡ch', 422);
        $this->requireInputFields((array) $input, ['fullName' => 'Há» vÃ  tÃªn', 'dateOfBirth' => 'NgÃ y sinh']);
        $row = $this->citizens->create($input, (int) $user['id']);
        $this->movementService->afterCitizenCreated($row, $input, (int) $user['id']);
        $row = $this->citizens->find((int) $row['id']) ?: $row;
        $this->audit($user, 'citizen', 'create', 'Táº¡o nhÃ¢n kháº©u vÃ  ghi biáº¿n Ä‘á»™ng dÃ¢n cÆ°', $row['id'], ['before' => null, 'after' => $row]);
        $this->ok($row);
    }

    public function update(string $id): void
    {
        $user = $this->requirePermission('citizen', 'update');
        $before = $this->citizens->find((int) $id);
        if (!$before) $this->fail('KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u', 404);
        $input = $this->input();
        $row = $this->citizens->update((int) $id, $input, (int) $user['id']);
        $this->movementService->afterCitizenUpdated($before, $row, $input, (int) $user['id']);
        $row = $this->citizens->find((int) $id) ?: $row;
        $this->audit($user, 'citizen', 'update', 'Cáº­p nháº­t nhÃ¢n kháº©u vÃ  ghi biáº¿n Ä‘á»™ng dÃ¢n cÆ°', $id, ['before' => $before, 'after' => $row]);
        $this->ok($row);
    }

    public function destroy(string $id): void
    {
        $user = $this->requirePermission('citizen', 'delete');
        $before = $this->citizens->find((int) $id);
        $this->movementService->markCitizenMovedOut((int) $id, $this->input(), (int) $user['id']);
        $after = $this->citizens->find((int) $id);
        $this->audit($user, 'citizen', 'delete', 'Chuyá»ƒn nhÃ¢n kháº©u khá»i dÃ¢n cÆ° hiá»‡n táº¡i', $id, ['before' => $before, 'after' => $after]);
        $this->ok(['id' => (int) $id]);
    }

    public function restore(string $id): void
    {
        $user = $this->requirePermission('citizen', 'update');
        $before = $this->citizens->find((int) $id);
        $this->citizens->restore((int) $id, (int) $user['id']);
        $after = $this->citizens->find((int) $id);
        $this->audit($user, 'citizen', 'update', 'KhÃ´i phá»¥c nhÃ¢n kháº©u', $id, ['before' => $before, 'after' => $after]);
        $this->ok(['id' => (int) $id]);
    }

    public function bulkDelete(): void
    {
        $user = $this->requirePermission('citizen', 'delete');
        $ids = (array) $this->input('ids', []);
        $deleted = $this->movementService->markCitizensMovedOut($ids, $this->input(), (int) $user['id']);
        $this->audit($user, 'citizen', 'delete', 'Chuyá»ƒn hÃ ng loáº¡t nhÃ¢n kháº©u khá»i dÃ¢n cÆ° hiá»‡n táº¡i', null, ['ids' => array_values(array_map('intval', $ids)), 'deleted' => $deleted, 'before' => 'bulk_citizens']);
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
            'policyAlert' => $this->query('policyAlert', $this->query('policy_alert', '')),
            'ethnicity' => $this->query('ethnicity', ''),
            'religion' => $this->query('religion', ''),
            'occupation' => $this->query('occupation', ''),
            'maritalStatus' => $this->query('maritalStatus', $this->query('marital_status', '')),
            'educationLevel' => $this->query('educationLevel', $this->query('education_level', '')),
            'workplace' => $this->query('workplace', ''),
            'nationality' => $this->query('nationality', ''),
            'bloodType' => $this->query('bloodType', $this->query('blood_type', '')),
            'includeMetrics' => $this->query('includeMetrics', '0'),
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
