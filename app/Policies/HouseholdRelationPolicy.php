<?php

namespace App\Policies;

final class HouseholdRelationPolicy
{
    public const HEAD = 'Chá»§ há»™';
    public const WIFE = 'Vá»£';
    public const HUSBAND = 'Chá»“ng';
    public const SON = 'Con trai';
    public const DAUGHTER = 'Con gÃ¡i';
    public const DAUGHTER_IN_LAW = 'Con dÃ¢u';
    public const SON_IN_LAW = 'Con rá»ƒ';
    public const GRANDCHILD = 'ChÃ¡u';
    public const GREAT_GRANDCHILD = 'Cháº¯t';
    public const PATERNAL_GRANDCHILD = 'ChÃ¡u ná»™i';
    public const MATERNAL_GRANDCHILD = 'ChÃ¡u ngoáº¡i';
    public const FATHER = 'Bá»‘';
    public const MOTHER = 'Máº¹';
    public const GRANDFATHER = 'Ã”ng';
    public const GRANDMOTHER = 'BÃ ';
    public const OLDER_BROTHER = 'Anh';
    public const OLDER_SISTER = 'Chá»‹';
    public const YOUNGER_SIBLING = 'Em';
    public const OTHER_RELATIVE = 'NgÆ°á»i thÃ¢n khÃ¡c';
    public const UNKNOWN = 'ChÆ°a xÃ¡c Ä‘á»‹nh';

    /** @return list<string> */
    public static function standardRelationships(): array
    {
        return [
            self::HEAD,
            self::WIFE,
            self::HUSBAND,
            self::SON,
            self::DAUGHTER,
            self::DAUGHTER_IN_LAW,
            self::SON_IN_LAW,
            self::GRANDCHILD,
            self::PATERNAL_GRANDCHILD,
            self::MATERNAL_GRANDCHILD,
            self::FATHER,
            self::MOTHER,
            self::GRANDFATHER,
            self::GRANDMOTHER,
            self::OLDER_BROTHER,
            self::OLDER_SISTER,
            self::YOUNGER_SIBLING,
            self::OTHER_RELATIVE,
        ];
    }

    public static function normalizeRelationship(mixed $value, mixed $gender = null): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return self::OTHER_RELATIVE;
        if ($text === self::UNKNOWN) return self::UNKNOWN;

        $key = self::normalizeText($text);
        $map = [
            'chu ho' => self::HEAD,
            'vo' => self::WIFE,
            'chong' => self::HUSBAND,
            'con trai' => self::SON,
            'con gai' => self::DAUGHTER,
            'con dau' => self::DAUGHTER_IN_LAW,
            'con re' => self::SON_IN_LAW,
            'chau' => self::GRANDCHILD,
            'chau noi' => self::PATERNAL_GRANDCHILD,
            'chau ngoai' => self::MATERNAL_GRANDCHILD,
            'bo' => self::FATHER,
            'cha' => self::FATHER,
            'me' => self::MOTHER,
            'ong' => self::GRANDFATHER,
            'ba' => self::GRANDMOTHER,
            'anh' => self::OLDER_BROTHER,
            'chi' => self::OLDER_SISTER,
            'em' => self::YOUNGER_SIBLING,
            'nguoi than khac' => self::OTHER_RELATIVE,
            'khac' => self::OTHER_RELATIVE,
            'chua xac dinh' => self::UNKNOWN,
        ];
        if ($key === 'con') return self::isFemale($gender) ? self::DAUGHTER : self::SON;
        return $map[$key] ?? $text;
    }

    public static function isInferableEmpty(mixed $relationship): bool
    {
        $text = trim((string) ($relationship ?? ''));
        return $text === '' || self::normalizeRelationship($text) === self::UNKNOWN;
    }

    /**
     * @param list<array<string,mixed>> $members
     * @return array<int,string>
     */
    public static function inferHouseholdRelationships(array $members, string $headName): array
    {
        $headName = trim($headName);
        if ($headName === '') return [];

        $byName = self::membersByNormalizedName($members);
        if (!self::singleMemberByName($headName, $byName)) return [];

        $relations = [];
        $locked = [];
        foreach ($members as $member) {
            $id = (int) ($member['id'] ?? 0);
            if ($id <= 0) continue;
            $relationship = trim((string) ($member['relationship'] ?? ''));
            $relations[$id] = $relationship;
            $locked[$id] = !self::isInferableEmpty($relationship);
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($members as $member) {
                $id = (int) ($member['id'] ?? 0);
                if ($id <= 0 || ($locked[$id] ?? false)) continue;
                $inferred = self::inferMemberRelationship($member, $headName, $byName, $relations);
                if ($inferred !== null && $inferred !== ($relations[$id] ?? '')) {
                    $relations[$id] = $inferred;
                    $changed = true;
                }
            }
        }

        return $relations;
    }

    public static function normalizeName(string $value): string
    {
        return self::normalizeText($value);
    }

    private static function inferMemberRelationship(array $member, string $headName, array $byName, array $relations): ?string
    {
        if (self::sameName((string) ($member['full_name'] ?? ''), $headName)) return self::HEAD;
        if (self::sameName((string) ($member['father_name'] ?? ''), $headName) || self::sameName((string) ($member['mother_name'] ?? ''), $headName)) {
            return self::childRelation($member['gender'] ?? null);
        }

        $father = self::singleMemberByName((string) ($member['father_name'] ?? ''), $byName);
        $mother = self::singleMemberByName((string) ($member['mother_name'] ?? ''), $byName);
        $fatherRelation = $father ? self::normalizeRelationship($relations[(int) $father['id']] ?? '') : '';
        $motherRelation = $mother ? self::normalizeRelationship($relations[(int) $mother['id']] ?? '') : '';

        if (in_array($fatherRelation, [self::SON, self::DAUGHTER], true) || in_array($motherRelation, [self::SON, self::DAUGHTER], true)) return self::GRANDCHILD;
        if ($fatherRelation === self::GRANDCHILD || $motherRelation === self::GRANDCHILD) return self::GREAT_GRANDCHILD;
        if (self::parentOfChildHasRelation($member, $byName, $relations, 'mother_name', [self::SON, self::DAUGHTER], self::GRANDCHILD)) return self::DAUGHTER_IN_LAW;
        if (self::parentOfChildHasRelation($member, $byName, $relations, 'father_name', [self::SON, self::DAUGHTER], self::GRANDCHILD)) return self::SON_IN_LAW;

        return null;
    }

    private static function childRelation(mixed $gender): string
    {
        return self::isFemale($gender) ? self::DAUGHTER : self::SON;
    }

    private static function isFemale(mixed $gender): bool
    {
        return in_array(self::normalizeText((string) ($gender ?? '')), ['nu', 'female'], true);
    }

    private static function membersByNormalizedName(array $members): array
    {
        $byName = [];
        foreach ($members as $member) {
            $key = self::normalizeName((string) ($member['full_name'] ?? ''));
            if ($key === '') continue;
            $byName[$key][] = $member;
        }
        return $byName;
    }

    private static function singleMemberByName(string $name, array $byName): ?array
    {
        $key = self::normalizeName($name);
        if ($key === '' || count($byName[$key] ?? []) !== 1) return null;
        return $byName[$key][0];
    }

    private static function parentOfChildHasRelation(array $member, array $byName, array $relations, string $childParentField, array $parentRelationships, string $childRelationship): bool
    {
        $name = self::normalizeName((string) ($member['full_name'] ?? ''));
        if ($name === '') return false;

        foreach ($byName as $candidates) {
            foreach ($candidates as $candidate) {
                if (self::normalizeRelationship($relations[(int) $candidate['id']] ?? '') !== $childRelationship) continue;
                if (!self::sameName((string) ($candidate[$childParentField] ?? ''), $name)) continue;
                $otherField = $childParentField === 'father_name' ? 'mother_name' : 'father_name';
                $otherParent = self::singleMemberByName((string) ($candidate[$otherField] ?? ''), $byName);
                if ($otherParent && in_array(self::normalizeRelationship($relations[(int) $otherParent['id']] ?? ''), $parentRelationships, true)) return true;
            }
        }
        return false;
    }

    private static function sameName(string $left, string $right): bool
    {
        $left = self::normalizeName($left);
        $right = self::normalizeName($right);
        return $left !== '' && $left === $right;
    }

    private static function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $from = ['Ã ','Ã¡','áº¡','áº£','Ã£','Ã¢','áº§','áº¥','áº­','áº©','áº«','Äƒ','áº±','áº¯','áº·','áº³','áºµ','Ã¨','Ã©','áº¹','áº»','áº½','Ãª','á»','áº¿','á»‡','á»ƒ','á»…','Ã¬','Ã­','á»‹','á»‰','Ä©','Ã²','Ã³','á»','á»','Ãµ','Ã´','á»“','á»‘','á»™','á»•','á»—','Æ¡','á»','á»›','á»£','á»Ÿ','á»¡','Ã¹','Ãº','á»¥','á»§','Å©','Æ°','á»«','á»©','á»±','á»­','á»¯','á»³','Ã½','á»µ','á»·','á»¹','Ä‘'];
        $to = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
        $value = str_replace($from, $to, $value);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value);
    }
}
