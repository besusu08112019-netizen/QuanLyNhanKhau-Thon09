<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Services\HouseholdCategoryService;

final class ProfileSummary extends BaseModel
{
    private ?HouseholdCategoryService $categoryService = null;

    public function person(int $citizenId): array
    {
        return [
            'party_member' => $this->partyMember($citizenId),
            'organizations' => $this->organizationMemberships($citizenId),
            'defense_security' => $this->defenseSecurity($citizenId),
            'policy_subjects' => $this->policySubjects($citizenId),
            'health_insurance' => $this->healthInsurance($citizenId),
        ];
    }

    public function household(int $householdId): array
    {
        return [
            'members' => $this->safeSummary('members', fn() => $this->householdMembers($householdId), ['total' => 0, 'at_home' => 0, 'away' => 0, 'source' => 'v_household_member_counts']),
            'water' => $this->safeSummary('water', fn() => $this->water($householdId), null),
            'livestock' => $this->safeSummary('livestock', fn() => $this->livestock($householdId), ['records' => 0, 'quantity' => 0, 'types' => [], 'source' => 'livestock']),
            'agriculture' => $this->safeSummary('agriculture', fn() => $this->agriculture($householdId), ['parcels' => 0, 'area' => 0, 'source' => 'agri_land_parcels']),
            'houses' => $this->safeSummary('houses', fn() => $this->houses($householdId), ['total' => 0, 'source' => 'houses']),
            'vehicles' => $this->safeSummary('vehicles', fn() => $this->vehicles($householdId), ['total' => 0, 'types' => [], 'source' => 'vehicles']),
            'business' => $this->safeSummary('business', fn() => $this->business($householdId), ['total' => 0, 'source' => 'household_business']),
            'contributions' => $this->safeSummary('contributions', fn() => $this->contributions($householdId), ['total' => 0, 'source' => 'household_contributions']),
            'poverty' => $this->safeSummary('poverty', fn() => $this->poverty($householdId), null),
            'household_category' => $this->safeSummary('household_category', fn() => $this->householdCategory($householdId), null),
        ];
    }

    private function safeSummary(string $key, callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            error_log('[PROFILE_SUMMARY] household ' . $key . ' failed: ' . get_class($e) . ' ' . $e->getMessage());
            return $fallback;
        }
    }
    private function partyMember(int $citizenId): ?array
    {
        if (!$this->tableExists('party_members')) return null;
        $row = $this->fetchOne(
            'SELECT pm.id, pm.party_member_code, pm.branch_name, pm.party_position, pm.member_type, pm.activity_status, pm.party_status, pm.joined_party_date, pm.official_party_date
             FROM party_members pm
             WHERE pm.status <> "DELETED" AND pm.citizen_id = :citizen_id AND ' . $this->tenantWhere('pm', 'party_members') . '
             ORDER BY pm.id DESC LIMIT 1',
            $this->withTenant(['citizen_id' => $citizenId])
        );
        if (!$row) return null;
        return [
            'id' => (int) $row['id'],
            'status' => (string) ($row['party_status'] ?: $row['activity_status'] ?: ''),
            'title' => '??ng vi?n',
            'summary' => trim(implode(' | ', array_filter([
                $row['branch_name'] ?? '',
                $row['party_position'] ?? '',
                $row['party_member_code'] ? 'M?: ' . $row['party_member_code'] : '',
            ]))),
            'joined_date' => $row['joined_party_date'] ?? null,
            'source' => 'party_members',
            'detail' => ['action' => 'partyMembers.detail', 'id' => (int) $row['id']],
        ];
    }

    private function organizationMemberships(int $citizenId): array
    {
        if (!$this->tableExists('organization_members')) return [];
        $rows = $this->fetchAll(
            'SELECT om.id, om.status, om.joined_date, om.ended_date, o.code AS organization_code, o.name AS organization_name, p.name AS position_name
             FROM organization_members om
             INNER JOIN organizations o ON o.id = om.organization_id AND o.village_id = om.village_id
             LEFT JOIN organization_positions p ON p.id = om.position_id AND p.village_id = om.village_id
             WHERE om.status <> "DELETED" AND om.citizen_id = :citizen_id AND ' . $this->tenantWhere('om', 'organization_members') . '
             ORDER BY o.sort_order ASC, om.joined_date DESC, om.id DESC',
            $this->withTenant(['citizen_id' => $citizenId])
        );
        return array_map(fn(array $row) => [
            'id' => (int) $row['id'],
            'organization_code' => (string) $row['organization_code'],
            'organization_name' => (string) $row['organization_name'],
            'position_name' => (string) ($row['position_name'] ?? ''),
            'status' => (string) $row['status'],
            'joined_date' => $row['joined_date'] ?? null,
            'ended_date' => $row['ended_date'] ?? null,
            'source' => 'organization_members',
            'detail' => ['action' => 'communityOrganizations.detail', 'id' => (int) $row['id']],
        ], $rows);
    }

    private function defenseSecurity(int $citizenId): array
    {
        return [
            'nvqs' => $this->latestDefenseRow('defense_nvqs_records', $citizenId, ['recruitment_year','registered_status','preliminary_status','medical_exam_status','eligibility_status','selection_status']),
            'militia' => $this->latestDefenseRow('defense_militia_records', $citizenId, ['militia_type','position_name','unit_name','participation_status','joined_date','ended_date']),
            'security_force' => $this->latestDefenseRow('defense_security_force_records', $citizenId, ['team_name','position_code','participation_status','joined_date','ended_date']),
        ];
    }

    private function latestDefenseRow(string $table, int $citizenId, array $columns): ?array
    {
        if (!$this->tableExists($table)) return null;
        $select = ['id', 'citizen_id'];
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) $select[] = $column;
        }
        $row = $this->fetchOne(
            'SELECT ' . implode(', ', $select) . ' FROM ' . $table . ' WHERE status <> "DELETED" AND citizen_id = :citizen_id AND ' . $this->tenantWhere($table) . ' ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 1',
            $this->withTenant(['citizen_id' => $citizenId])
        );
        if (!$row) return null;
        $row['source'] = $table;
        $row['detail'] = ['action' => 'defenseSecurity.edit', 'id' => (int) $row['id']];
        return $row;
    }

    private function policySubjects(int $citizenId): array
    {
        if (!$this->tableExists('citizen_policy_records') || !$this->tableExists('policy_subject_types')) return [];
        $rows = $this->fetchAll(
            'SELECT cpr.id, pst.name AS policy_name, cpr.status, cpr.benefit_start_date, cpr.benefit_end_date
             FROM citizen_policy_records cpr
             INNER JOIN policy_subject_types pst ON pst.id = cpr.policy_type_id AND pst.village_id = cpr.village_id
             WHERE cpr.status <> "DELETED" AND cpr.deleted_at IS NULL AND pst.deleted_at IS NULL AND cpr.citizen_id = :citizen_id
               AND ' . $this->tenantWhere('cpr', 'citizen_policy_records') . ' AND ' . $this->tenantWhere('pst', 'policy_subject_types') . '
             ORDER BY pst.display_order ASC, cpr.benefit_start_date DESC, cpr.id DESC LIMIT 10',
            $this->withTenant(['citizen_id' => $citizenId])
        );
        return array_map(fn(array $row) => [
            'id' => (int) $row['id'],
            'policy_name' => (string) ($row['policy_name'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'benefit_start_date' => $row['benefit_start_date'] ?? null,
            'benefit_end_date' => $row['benefit_end_date'] ?? null,
            'source' => 'citizen_policy_records',
            'detail' => ['action' => 'policySubjects.detail', 'id' => (int) $row['id']],
        ], $rows);
    }

    private function healthInsurance(int $citizenId): ?array
    {
        if (!$this->tableExists('citizens') || !$this->columnExists('citizens', 'has_health_insurance')) return null;
        $columns = ['id', 'has_health_insurance'];
        foreach (['health_insurance_number', 'health_insurance_group', 'health_insurance_start_date', 'health_insurance_end_date', 'health_insurance_facility'] as $column) {
            if ($this->columnExists('citizens', $column)) $columns[] = $column;
        }
        $row = $this->fetchOne(
            'SELECT ' . implode(', ', $columns) . ' FROM citizens WHERE id = :citizen_id AND status <> "DELETED" AND ' . $this->tenantWhere('citizens'),
            $this->withTenant(['citizen_id' => $citizenId])
        );
        if (!$row) return null;
        return [
            'has_health_insurance' => (int) ($row['has_health_insurance'] ?? 0),
            'number' => $row['health_insurance_number'] ?? null,
            'group' => $row['health_insurance_group'] ?? null,
            'start_date' => $row['health_insurance_start_date'] ?? null,
            'end_date' => $row['health_insurance_end_date'] ?? null,
            'facility' => $row['health_insurance_facility'] ?? null,
            'source' => 'citizens',
        ];
    }

    private function householdMembers(int $householdId): array
    {
        $household = (new Household())->find($householdId);
        if ($household) {
            return [
                'total' => (int) ($household['member_count_real'] ?? 0),
                'at_home' => (int) ($household['at_home_count'] ?? 0),
                'away' => (int) ($household['away_count'] ?? 0),
                'source' => 'v_household_member_counts',
            ];
        }
        return ['total' => 0, 'at_home' => 0, 'away' => 0, 'source' => 'v_household_member_counts'];
    }

    private function water(int $householdId): ?array
    {
        if (!$this->tableExists('rural_clean_water')) return null;
        $row = $this->fetchOne(
            'SELECT w.id, w.household_id, w.connection_type, w.water_supply_form, w.provider_name, w.water_source, w.clean_water_status, w.hygienic_water_status, w.status, w.updated_at
             FROM rural_clean_water w
             INNER JOIN households h ON h.id = w.household_id AND ' . $this->tenantWhere('h', 'households') . '
             WHERE w.household_id = :household_id AND w.status <> "DELETED" AND ' . $this->tenantWhere('w', 'rural_clean_water') . '
             ORDER BY w.id DESC LIMIT 1',
            $this->withTenant(['household_id' => $householdId])
        );
        if (!$row) return null;
        $row['source'] = 'rural_clean_water';
        $row['detail'] = ['action' => 'ruralCleanWater.edit', 'id' => (int) $row['id'], 'household_id' => (int) $householdId];
        return $row;
    }

    private function livestock(int $householdId): array
    {
        if (!$this->tableExists('livestock')) return ['records' => 0, 'quantity' => 0, 'types' => [], 'source' => 'livestock'];
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS records, COALESCE(SUM(quantity),0) AS quantity, COUNT(DISTINCT animal_type) AS type_count, MAX(id) AS latest_id
             FROM livestock
             WHERE household_id = :household_id AND status <> "DELETED" AND ' . $this->tenantWhere('livestock'),
            $this->withTenant(['household_id' => $householdId])
        ) ?: [];
        $types = $this->fetchAll(
            'SELECT animal_type, COALESCE(SUM(quantity),0) AS quantity FROM livestock WHERE household_id=:household_id AND status <> "DELETED" AND ' . $this->tenantWhere('livestock') . ' GROUP BY animal_type ORDER BY quantity DESC LIMIT 5',
            $this->withTenant(['household_id' => $householdId])
        );
        return [
            'records' => (int) ($row['records'] ?? 0),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'type_count' => (int) ($row['type_count'] ?? 0),
            'types' => $types,
            'source' => 'livestock',
            'detail' => ['action' => 'livestock.household', 'id' => $householdId, 'household_id' => $householdId],
        ];
    }

    private function agriculture(int $householdId): array
    {
        if (!$this->tableExists('agri_stakeholders') || !$this->tableExists('agri_land_parcels')) return ['parcels' => 0, 'area' => 0, 'source' => 'agri_land_parcels'];
        $row = $this->fetchOne(
            'SELECT COUNT(DISTINCT p.id) AS parcels, COALESCE(SUM(p.actual_area),0) AS area, MAX(p.id) AS latest_id
             FROM agri_land_parcels p
             INNER JOIN agri_stakeholders o ON o.id = p.owner_id AND o.village_id = p.village_id
             INNER JOIN agri_stakeholders pr ON pr.id = p.producer_id AND pr.village_id = p.village_id
             WHERE p.status <> "DELETED" AND (o.household_id = :household_id OR pr.household_id = :household_id)
               AND ' . $this->tenantWhere('p', 'agri_land_parcels') . ' AND ' . $this->tenantWhere('o', 'agri_stakeholders') . ' AND ' . $this->tenantWhere('pr', 'agri_stakeholders'),
            $this->withTenant(['household_id' => $householdId])
        ) ?: [];
        return [
            'parcels' => (int) ($row['parcels'] ?? 0),
            'area' => (float) ($row['area'] ?? 0),
            'latest_id' => $row['latest_id'] ?? null,
            'source' => 'agri_land_parcels',
            'detail' => !empty($row['latest_id']) ? ['action' => 'agriculture.detail', 'id' => (int) $row['latest_id']] : null,
        ];
    }

    private function houses(int $householdId): array
    {
        if (!$this->tableExists('houses')) return ['total' => 0, 'source' => 'houses'];
        $areaColumn = $this->columnExists('houses', 'floor_area') ? 'floor_area' : ($this->columnExists('houses', 'building_area') ? 'building_area' : '0');
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total, COALESCE(SUM(' . $areaColumn . '),0) AS floor_area, MAX(house_type) AS latest_type, MAX(id) AS latest_id
             FROM houses
             WHERE household_id = :household_id AND status <> "DELETED" AND ' . $this->tenantWhere('houses'),
            $this->withTenant(['household_id' => $householdId])
        ) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'floor_area' => (float) ($row['floor_area'] ?? 0),
            'latest_type' => (string) ($row['latest_type'] ?? ''),
            'latest_id' => $row['latest_id'] ?? null,
            'source' => 'houses',
            'detail' => !empty($row['latest_id']) ? ['action' => 'houses.detail', 'id' => (int) $row['latest_id']] : null,
        ];
    }

    private function vehicles(int $householdId): array
    {
        if (!$this->tableExists('vehicles')) return ['total' => 0, 'types' => [], 'source' => 'vehicles'];
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total, MAX(id) AS latest_id FROM vehicles WHERE household_id = :household_id AND status <> "DELETED" AND ' . $this->tenantWhere('vehicles'),
            $this->withTenant(['household_id' => $householdId])
        ) ?: [];
        $types = $this->fetchAll(
            'SELECT vehicle_type, COUNT(*) AS total FROM vehicles WHERE household_id=:household_id AND status <> "DELETED" AND ' . $this->tenantWhere('vehicles') . ' GROUP BY vehicle_type ORDER BY total DESC LIMIT 5',
            $this->withTenant(['household_id' => $householdId])
        );
        return [
            'total' => (int) ($row['total'] ?? 0),
            'types' => $types,
            'latest_id' => $row['latest_id'] ?? null,
            'source' => 'vehicles',
            'detail' => ((int) ($row['total'] ?? 0) > 0) ? ['action' => 'vehicles.household', 'id' => $householdId, 'household_id' => $householdId] : null,
        ];
    }

    private function business(int $householdId): array
    {
        if (!$this->tableExists('household_business')) return ['total' => 0, 'source' => 'household_business'];
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total, COALESCE(SUM(worker_count),0) AS workers, MAX(COALESCE(NULLIF(business_name,""), NULLIF(production_sector,""), NULLIF(business_sector,""))) AS latest_name, MAX(id) AS latest_id
             FROM household_business
             WHERE household_id = :household_id AND status <> "DELETED" AND ' . $this->tenantWhere('household_business'),
            $this->withTenant(['household_id' => $householdId])
        ) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'workers' => (int) ($row['workers'] ?? 0),
            'latest_name' => (string) ($row['latest_name'] ?? ''),
            'latest_id' => $row['latest_id'] ?? null,
            'source' => 'household_business',
            'detail' => ((int) ($row['total'] ?? 0) > 0) ? ['action' => 'businessHouseholds.detail', 'id' => $householdId, 'household_id' => $householdId] : null,
        ];
    }

    private function contributions(int $householdId): array
    {
        if (!$this->tableExists('household_contributions')) return ['total' => 0, 'source' => 'household_contributions'];
        $amountColumn = $this->columnExists('household_contributions', 'amount') ? 'amount' : ($this->columnExists('household_contributions', 'expected_amount') ? 'expected_amount' : '0');
        $paidColumn = $this->columnExists('household_contributions', 'paid_amount') ? 'paid_amount' : '0';
        $debtColumn = $this->columnExists('household_contributions', 'debt_amount') ? 'debt_amount' : '0';
        $statusWhere = $this->columnExists('household_contributions', 'status') ? ' AND status <> "DELETED"' : '';
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total, COALESCE(SUM(' . $amountColumn . '),0) AS amount, COALESCE(SUM(' . $paidColumn . '),0) AS paid, COALESCE(SUM(' . $debtColumn . '),0) AS debt, MAX(id) AS latest_id
             FROM household_contributions
             WHERE household_id = :household_id' . $statusWhere . ' AND ' . $this->tenantWhere('household_contributions'),
            $this->withTenant(['household_id' => $householdId])
        ) ?: [];
        $latest = $this->fetchOne(
            'SELECT campaign_id FROM household_contributions WHERE household_id = :household_id' . $statusWhere . ' AND ' . $this->tenantWhere('household_contributions') . ' ORDER BY id DESC LIMIT 1',
            $this->withTenant(['household_id' => $householdId])
        ) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
            'paid' => (float) ($row['paid'] ?? 0),
            'debt' => (float) ($row['debt'] ?? 0),
            'latest_id' => $row['latest_id'] ?? null,
            'latest_campaign_id' => $latest['campaign_id'] ?? null,
            'source' => 'household_contributions',
            'detail' => ((int) ($row['total'] ?? 0) > 0 && !empty($latest['campaign_id'])) ? ['action' => 'contributions.household', 'id' => $householdId, 'household_id' => $householdId, 'campaign_id' => (int) $latest['campaign_id']] : null,
        ];
    }

    private function poverty(int $householdId): ?array
    {
        if (!$this->tableExists('household_poverty_records')) return null;
        $row = $this->fetchOne(
            'SELECT hpr.id, hpr.poverty_type, hpr.status, hpr.start_date, hpr.end_date
             FROM household_poverty_records hpr
             WHERE hpr.household_id = :household_id AND hpr.status <> "DELETED" AND ' . $this->tenantWhere('hpr', 'household_poverty_records') . '
             ORDER BY COALESCE(hpr.end_date, "9999-12-31") DESC, hpr.id DESC LIMIT 1',
            $this->withTenant(['household_id' => $householdId])
        );
        if (!$row) return null;
        $row['source'] = 'household_poverty_records';
        $row['detail'] = ['action' => 'poverty.detail', 'id' => (int) $row['id']];
        return $row;
    }

    private function householdCategory(int $householdId): ?array
    {
        $household = (new Household())->find($householdId);
        if (!$household) return null;
        return [
            'household_type_key' => (string) ($household['household_type_key'] ?? ''),
            'household_type' => (string) ($household['household_type'] ?? ''),
            'source' => 'HouseholdCategoryService',
        ];
    }

    private function categoryService(): HouseholdCategoryService
    {
        return $this->categoryService ??= new HouseholdCategoryService();
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
}
