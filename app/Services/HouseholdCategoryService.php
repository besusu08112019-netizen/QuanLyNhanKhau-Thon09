<?php

namespace App\Services;

use App\Core\BaseModel;

final class HouseholdCategoryService extends BaseModel
{
    public const POOR = 'poor';
    public const NEAR_POOR = 'near_poor';
    public const MEDIUM = 'medium';
    public const POLICY = 'policy';
    public const NORMAL = 'normal';

    public const LABELS = [
        self::POOR => 'H? ngh?o',
        self::NEAR_POOR => 'H? c?n ngh?o',
        self::MEDIUM => 'H? trung b?nh',
        self::POLICY => 'H? ch?nh s?ch',
        self::NORMAL => 'H? b?nh th??ng',
    ];


    private static array $tableCache = [];

    public function keyExpression(string $householdAlias = 'h'): string
    {
        $alias = $this->alias($householdAlias);
        return 'CASE'
            . ' WHEN ' . $this->povertyExists($alias, 'POOR') . " THEN '" . self::POOR . "'"
            . ' WHEN ' . $this->povertyExists($alias, 'NEAR_POOR') . " THEN '" . self::NEAR_POOR . "'"
            . ' WHEN ' . $this->povertyExists($alias, 'MEDIUM') . " THEN '" . self::MEDIUM . "'"
            . ' WHEN ' . $this->policyExists($alias) . " THEN '" . self::POLICY . "'"
            . " ELSE '" . self::NORMAL . "' END";
    }

    public function labelExpression(string $householdAlias = 'h'): string
    {
        $alias = $this->alias($householdAlias);
        return 'CASE'
            . ' WHEN ' . $this->povertyExists($alias, 'POOR') . " THEN '" . self::LABELS[self::POOR] . "'"
            . ' WHEN ' . $this->povertyExists($alias, 'NEAR_POOR') . " THEN '" . self::LABELS[self::NEAR_POOR] . "'"
            . ' WHEN ' . $this->povertyExists($alias, 'MEDIUM') . " THEN '" . self::LABELS[self::MEDIUM] . "'"
            . ' WHEN ' . $this->policyExists($alias) . " THEN '" . self::LABELS[self::POLICY] . "'"
            . " ELSE '" . self::LABELS[self::NORMAL] . "' END";
    }

    public function countsSelect(string $householdAlias = 'h'): string
    {
        $keyExpr = $this->keyExpression($householdAlias);
        return "COALESCE(SUM(CASE WHEN $keyExpr='" . self::POOR . "' THEN 1 ELSE 0 END),0) AS poor_households, "
            . "COALESCE(SUM(CASE WHEN $keyExpr='" . self::NEAR_POOR . "' THEN 1 ELSE 0 END),0) AS near_poor_households, "
            . "COALESCE(SUM(CASE WHEN $keyExpr='" . self::MEDIUM . "' THEN 1 ELSE 0 END),0) AS medium_households, "
            . "COALESCE(SUM(CASE WHEN $keyExpr='" . self::POLICY . "' THEN 1 ELSE 0 END),0) AS policy_households, "
            . "COALESCE(SUM(CASE WHEN $keyExpr='" . self::NORMAL . "' THEN 1 ELSE 0 END),0) AS normal_households";
    }

    public function condition(string $category, string $householdAlias = 'h'): string
    {
        $key = self::normalizeKey($category);
        if ($key === '') {
            return '';
        }

        return $this->keyExpression($householdAlias) . "='" . $key . "'";
    }

    public function labelForRow(array $row): string
    {
        $key = self::normalizeKey($row['household_type_key'] ?? $row['household_type'] ?? '');
        if ($key !== '') {
            return self::LABELS[$key];
        }

        if ((int) ($row['poor_household'] ?? 0) === 1) return self::LABELS[self::POOR];
        if ((int) ($row['near_poor_household'] ?? 0) === 1) return self::LABELS[self::NEAR_POOR];
        if ((int) ($row['policy_household'] ?? 0) === 1 || (int) ($row['meritorious_policy'] ?? 0) === 1 || (int) ($row['disabled_policy'] ?? 0) === 1) return self::LABELS[self::POLICY];
        return self::LABELS[self::NORMAL];
    }

    public static function normalizeKey(mixed $value): string
    {
        $text = self::normalize((string) $value);
        if ($text === '') return '';
        return match (true) {
            str_contains($text, 'can ngheo') || str_contains($text, 'near poor') || $text === self::NEAR_POOR => self::NEAR_POOR,
            str_contains($text, 'trung binh') || str_contains($text, 'medium') || $text === self::MEDIUM => self::MEDIUM,
            str_contains($text, 'chinh sach') || str_contains($text, 'co cong') || str_contains($text, 'khuyet tat') || str_contains($text, 'bao tro') || str_contains($text, 'policy') || str_contains($text, 'meritorious') || str_contains($text, 'other') || $text === self::POLICY => self::POLICY,
            str_contains($text, 'binh thuong') || str_contains($text, 'normal') || $text === 'khong' || $text === self::NORMAL => self::NORMAL,
            str_contains($text, 'ngheo') || str_contains($text, 'poor') || $text === self::POOR => self::POOR,
            default => '',
        };
    }

    private function povertyExists(string $householdAlias, string $type): string
    {
        if (!$this->tableExists('household_poverty_records')) {
            return '0=1';
        }

        return "EXISTS (SELECT 1 FROM household_poverty_records hpr WHERE hpr.household_id={$householdAlias}.id AND hpr.status='ACTIVE' AND hpr.poverty_type='$type' AND " . $this->tenantLiteral('household_poverty_records', 'hpr') . ')';
    }

    private function policyExists(string $householdAlias): string
    {
        return $this->citizenPolicyRecordExists($householdAlias);
    }

    private function citizenPolicyRecordExists(string $householdAlias): string
    {
        if (!$this->tableExists('citizen_policy_records') || !$this->tableExists('policy_subject_types')) {
            return '0=1';
        }

        $where = [
            'pc.household_id=' . $householdAlias . '.id',
            "cpr.status='ACTIVE'",
            'cpr.deleted_at IS NULL',
            'pst.deleted_at IS NULL',
            'COALESCE(pst.is_active,1)=1',
            $this->citizenActiveCondition('citizens', 'pc'),
            $this->tenantLiteral('citizen_policy_records', 'cpr'),
            $this->tenantLiteral('policy_subject_types', 'pst'),
            $this->tenantLiteral('citizens', 'pc'),
        ];

        return 'EXISTS (SELECT 1 FROM citizen_policy_records cpr INNER JOIN policy_subject_types pst ON pst.id=cpr.policy_type_id INNER JOIN citizens pc ON pc.id=cpr.citizen_id WHERE ' . implode(' AND ', $where) . ')';
    }

    private function citizenActiveCondition(string $table, string $alias): string
    {
        $conditions = [$this->tenantLiteral($table, $alias)];
        if ($this->columnExists($table, 'status')) {
            $conditions[] = '(' . $alias . ".status IS NULL OR " . $alias . ".status <> 'DELETED')";
        }
        if ($this->columnExists($table, 'deleted_at')) {
            $conditions[] = $alias . '.deleted_at IS NULL';
        }
        if ($this->columnExists($table, 'life_status')) {
            $conditions[] = "COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED'";
        }
        if ($this->columnExists($table, 'residency_status')) {
            $conditions[] = "COALESCE(" . $alias . ".residency_status,'PERMANENT') <> 'TRANSFERRED_OUT'";
        }
        return implode(' AND ', $conditions);
    }

    private function tenantLiteral(string $table, string $alias = ''): string
    {
        if (!$this->tenantColumnExists($table)) return '1=1';
        return ($alias !== '' ? $alias . '.' : '') . 'village_id = ' . $this->tenantId();
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableCache)) {
            return self::$tableCache[$table];
        }
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return self::$tableCache[$table] = ((int) ($row['total'] ?? 0) > 0);
    }

    private function alias(string $alias): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'h';
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim(preg_replace('/[^a-z0-9_]+/', ' ', $value));
    }
}
