<?php

namespace App\Services;

use App\Core\BaseModel;

final class HouseholdRelationshipInferenceService extends BaseModel
{
    private const UNKNOWN_RELATIONSHIP = 'Chưa xác định';
    private const INFERABLE_EMPTY_VALUES = ['', self::UNKNOWN_RELATIONSHIP];

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
            'SELECT id, full_name, father_name, mother_name, relationship
             FROM citizens
             WHERE household_id=:household_id AND status <> "DELETED" AND ' . $this->tenantWhere('citizens'),
            $this->withTenant(['household_id' => $householdId])
        );
        if (!$members) return 0;

        $existingHeads = array_values(array_filter($members, fn($member) => trim((string) ($member['relationship'] ?? '')) === 'Chủ hộ'));
        if (count($existingHeads) > 1) return 0;
        if (count($existingHeads) === 1) $headName = (string) ($existingHeads[0]['full_name'] ?? $headName);

        $byName = $this->membersByNormalizedName($members);
        if (!$this->singleMemberByName($headName, $byName)) return 0;
        $relations = [];
        $locked = [];
        foreach ($members as $member) {
            $id = (int) $member['id'];
            $relationship = trim((string) ($member['relationship'] ?? ''));
            $relations[$id] = $relationship;
            $locked[$id] = !in_array($relationship, self::INFERABLE_EMPTY_VALUES, true);
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($members as $member) {
                $id = (int) $member['id'];
                if ($locked[$id]) continue;
                $inferred = $this->inferMemberRelationship($member, $headName, $byName, $relations);
                if ($inferred !== null && $inferred !== ($relations[$id] ?? '')) {
                    $relations[$id] = $inferred;
                    $changed = true;
                }
            }
        }

        $updated = 0;
        foreach ($members as $member) {
            $id = (int) $member['id'];
            if ($locked[$id]) continue;
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

    private function inferMemberRelationship(array $member, string $headName, array $byName, array $relations): ?string
    {
        if ($this->sameName((string) ($member['full_name'] ?? ''), $headName)) return 'Chủ hộ';
        if ($this->sameName((string) ($member['father_name'] ?? ''), $headName) || $this->sameName((string) ($member['mother_name'] ?? ''), $headName)) return 'Con';

        $father = $this->singleMemberByName((string) ($member['father_name'] ?? ''), $byName);
        $mother = $this->singleMemberByName((string) ($member['mother_name'] ?? ''), $byName);
        $fatherRelation = $father ? ($relations[(int) $father['id']] ?? '') : '';
        $motherRelation = $mother ? ($relations[(int) $mother['id']] ?? '') : '';

        if ($fatherRelation === 'Con' || $motherRelation === 'Con') return 'Cháu';
        if ($fatherRelation === 'Cháu' || $motherRelation === 'Cháu') return 'Chắt';

        if ($this->parentOfChildHasRelation($member, $byName, $relations, 'mother_name', 'Con', 'Cháu')) return 'Con dâu';
        if ($this->parentOfChildHasRelation($member, $byName, $relations, 'father_name', 'Con', 'Cháu')) return 'Con rể';

        return null;
    }

    private function membersByNormalizedName(array $members): array
    {
        $byName = [];
        foreach ($members as $member) {
            $key = $this->normalizeName((string) ($member['full_name'] ?? ''));
            if ($key === '') continue;
            $byName[$key][] = $member;
        }
        return $byName;
    }

    private function singleMemberByName(string $name, array $byName): ?array
    {
        $key = $this->normalizeName($name);
        if ($key === '' || count($byName[$key] ?? []) !== 1) return null;
        return $byName[$key][0];
    }

    private function parentOfChildHasRelation(array $member, array $byName, array $relations, string $childParentField, string $relationship, string $childRelationship): bool
    {
        $name = $this->normalizeName((string) ($member['full_name'] ?? ''));
        if ($name === '') return false;
        foreach ($byName as $candidates) {
            foreach ($candidates as $candidate) {
                if (($relations[(int) $candidate['id']] ?? '') !== $childRelationship) continue;
                if (!$this->sameName((string) ($candidate[$childParentField] ?? ''), $name)) continue;
                $otherField = $childParentField === 'father_name' ? 'mother_name' : 'father_name';
                $otherParent = $this->singleMemberByName((string) ($candidate[$otherField] ?? ''), $byName);
                if ($otherParent && ($relations[(int) $otherParent['id']] ?? '') === $relationship) return true;
            }
        }
        return false;
    }

    private function syncHouseholdHead(int $householdId): void
    {
        $head = $this->fetchOne(
            'SELECT id, full_name FROM citizens WHERE household_id=:household_id AND relationship="Chủ hộ" AND status <> "DELETED" AND ' . $this->tenantWhere('citizens') . ' ORDER BY id LIMIT 1',
            $this->withTenant(['household_id' => $householdId])
        );
        $this->execute(
            'UPDATE households SET head_citizen_id=:head_id, head_citizen_name=:head_name WHERE id=:household_id AND ' . $this->tenantWhere('households'),
            $this->withTenant(['household_id' => $householdId, 'head_id' => $head['id'] ?? null, 'head_name' => $head['full_name'] ?? null])
        );
    }

    private function sameName(string $left, string $right): bool
    {
        $left = $this->normalizeName($left);
        $right = $this->normalizeName($right);
        return $left !== '' && $left === $right;
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = $this->removeVietnameseMarks($value);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value);
    }

    private function removeVietnameseMarks(string $value): string
    {
        $groups = [
            'a' => '&#224;&#225;&#7843;&#227;&#7841;&#259;&#7857;&#7855;&#7859;&#7861;&#7863;&#226;&#7847;&#7845;&#7849;&#7851;&#7853;',
            'd' => '&#273;',
            'e' => '&#232;&#233;&#7867;&#7869;&#7865;&#234;&#7873;&#7871;&#7875;&#7877;&#7879;',
            'i' => '&#236;&#237;&#7881;&#297;&#7883;',
            'o' => '&#242;&#243;&#7887;&#245;&#7885;&#244;&#7891;&#7889;&#7893;&#7895;&#7897;&#417;&#7901;&#7899;&#7903;&#7905;&#7907;',
            'u' => '&#249;&#250;&#7911;&#361;&#7909;&#432;&#7915;&#7913;&#7917;&#7919;&#7921;',
            'y' => '&#7923;&#253;&#7927;&#7929;&#7925;',
        ];

        foreach ($groups as $ascii => $entities) {
            $chars = preg_split('//u', html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($chars) $value = str_replace($chars, $ascii, $value);
        }
        return $value;
    }
}
