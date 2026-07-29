<?php

namespace App\Services;

use App\Core\BaseModel;
use App\Policies\HouseholdRelationPolicy;

final class HouseholdRelationshipInferenceService extends BaseModel
{
    public function inferForHousehold(int $householdId, ?string $headNameOverride = null): int
    {
        $household = $this->fetchOne(
            'SELECT id, head_citizen_name FROM households WHERE id=:id AND status <> "DELETED" AND ' . $this->tenantWhere('households'),
            $this->withTenant(['id' => $householdId])
        );
        if (!$household) return 0;

        $headName = trim((string) ($headNameOverride ?: ($household['head_citizen_name'] ?? '')));
        if ($headName === '') return 0;

        $members = $this->fetchAll(
            'SELECT id, full_name, gender, father_name, mother_name, relationship
             FROM citizens
             WHERE household_id=:household_id AND status <> "DELETED" AND ' . $this->tenantWhere('citizens'),
            $this->withTenant(['household_id' => $householdId])
        );
        if (!$members) return 0;

        $existingHeads = array_values(array_filter(
            $members,
            fn($member) => HouseholdRelationPolicy::normalizeRelationship($member['relationship'] ?? '') === HouseholdRelationPolicy::HEAD
        ));
        if (count($existingHeads) > 1) return 0;
        if (count($existingHeads) === 1) $headName = (string) ($existingHeads[0]['full_name'] ?? $headName);

        $relations = HouseholdRelationPolicy::inferHouseholdRelationships($members, $headName);
        if (!$relations) return 0;

        $updated = 0;
        foreach ($members as $member) {
            $id = (int) $member['id'];
            if (!HouseholdRelationPolicy::isInferableEmpty($member['relationship'] ?? '')) continue;
            $relationship = $relations[$id] ?? '';
            if ($relationship === '' || $relationship === trim((string) ($member['relationship'] ?? ''))) continue;
            $this->execute(
                'UPDATE citizens SET relationship=:relationship WHERE id=:id AND household_id=:household_id AND ' . $this->tenantWhere('citizens'),
                $this->withTenant(['relationship' => $relationship, 'id' => $id, 'household_id' => $householdId])
            );
            $updated++;
        }

        if ($updated > 0) $this->syncHouseholdHead($householdId);
        return $updated;
    }

    private function syncHouseholdHead(int $householdId): void
    {
        $head = $this->fetchOne(
            'SELECT id, full_name FROM citizens WHERE household_id=:household_id AND relationship="' . HouseholdRelationPolicy::HEAD . '" AND status <> "DELETED" AND ' . $this->tenantWhere('citizens') . ' ORDER BY id LIMIT 1',
            $this->withTenant(['household_id' => $householdId])
        );
        $this->execute(
            'UPDATE households SET head_citizen_id=:head_id, head_citizen_name=:head_name WHERE id=:household_id AND ' . $this->tenantWhere('households'),
            $this->withTenant(['household_id' => $householdId, 'head_id' => $head['id'] ?? null, 'head_name' => $head['full_name'] ?? null])
        );
    }
}
