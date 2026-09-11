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
        self::POOR => 'Hộ nghèo',
        self::NEAR_POOR => 'Hộ cận nghèo',
        self::MEDIUM => 'Hộ trung bình',
        self::POLICY => 'Hộ chính sách',
        self::NORMAL => 'Hộ bình thường',
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
        $poor = $this->condition(self::POOR, $householdAlias);
        $nearPoor = $this->condition(self::NEAR_POOR, $householdAlias);
        $medium = $this->condition(self::MEDIUM, $householdAlias);
        $policy = $this->condition(self::POLICY, $householdAlias);
        $normal = $this->condition(self::NORMAL, $householdAlias);
        return "COALESCE(SUM(CASE WHEN $poor THEN 1 ELSE 0 END),0) AS poor_households, "
            . "COALESCE(SUM(CASE WHEN $nearPoor THEN 1 ELSE 0 END),0) AS near_poor_households, "
            . "COALESCE(SUM(CASE WHEN $medium THEN 1 ELSE 0 END),0) AS medium_households, "
            . "COALESCE(SUM(CASE WHEN $policy THEN 1 ELSE 0 END),0) AS policy_households, "
            . "COALESCE(SUM(CASE WHEN $normal THEN 1 ELSE 0 END),0) AS normal_households";
    }

    public function condition(string $category, string $householdAlias = 'h'): string
    {
        $key = self::normalizeKey($category);
        if ($key === '') {
            return '';
        }

        $alias = $this->alias($householdAlias);
        return match ($key) {
            self::POOR => $this->povertyExists($alias, 'POOR'),
            self::NEAR_POOR => $this->povertyExists($alias, 'NEAR_POOR'),
            self::MEDIUM => $this->povertyExists($alias, 'MEDIUM'),
            self::POLICY => $this->policyExists($alias),
            self::NORMAL => 'NOT (' . $this->povertyExists($alias, 'POOR') . ')'
                . ' AND NOT (' . $this->povertyExists($alias, 'NEAR_POOR') . ')'
                . ' AND NOT (' . $this->povertyExists($alias, 'MEDIUM') . ')'
                . ' AND NOT (' . $this->policyExists($alias) . ')',
            default => '',
        };
    }

    public function selectExpressions(string $householdAlias = 'h'): string
    {
        return $this->keyExpression($householdAlias) . ' AS canonical_household_type_key, '
            . $this->labelExpression($householdAlias) . ' AS canonical_household_type, '
            . $this->povertyTypeExpression($householdAlias) . ' AS poverty_type, '
            . 'CASE WHEN ' . $this->condition(self::POOR, $householdAlias) . ' THEN 1 ELSE 0 END AS household_is_poor, '
            . 'CASE WHEN ' . $this->condition(self::NEAR_POOR, $householdAlias) . ' THEN 1 ELSE 0 END AS household_is_near_poor, '
            . 'CASE WHEN ' . $this->condition(self::MEDIUM, $householdAlias) . ' THEN 1 ELSE 0 END AS household_is_medium, '
            . 'CASE WHEN ' . $this->condition(self::POLICY, $householdAlias) . ' THEN 1 ELSE 0 END AS household_is_policy';
    }

    private function povertyTypeExpression(string $householdAlias = 'h'): string
    {
        $alias = $this->alias($householdAlias);
        if (!$this->tableExists('household_poverty_records')) return 'NULL';
        $conditions = [
            'hpr.household_id=' . $alias . '.id',
            "hpr.status='ACTIVE'",
            $this->tenantLiteral('household_poverty_records', 'hpr'),
        ];
        if ($this->columnExists('household_poverty_records', 'deleted_at')) $conditions[] = 'hpr.deleted_at IS NULL';
        if ($this->columnExists('household_poverty_records', 'effective_from')) $conditions[] = 'hpr.effective_from <= CURDATE()';
        if ($this->columnExists('household_poverty_records', 'effective_to')) $conditions[] = '(hpr.effective_to IS NULL OR hpr.effective_to >= CURDATE())';
        return '(SELECT hpr.poverty_type FROM household_poverty_records hpr WHERE ' . implode(' AND ', $conditions) . ' ORDER BY hpr.effective_from DESC, hpr.id DESC LIMIT 1)';
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
            str_contains($text, 'trung binh') || str_contains($text, 'medium') || str_contains($text, 'average') || $text === self::MEDIUM => self::MEDIUM,
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

        $conditions = [
            "hpr.household_id={$householdAlias}.id",
            "hpr.status='ACTIVE'",
            "hpr.poverty_type='$type'",
            $this->tenantLiteral('household_poverty_records', 'hpr'),
        ];
        if ($this->columnExists('household_poverty_records', 'deleted_at')) {
            $conditions[] = 'hpr.deleted_at IS NULL';
        }
        if ($this->columnExists('household_poverty_records', 'effective_from')) {
            $conditions[] = 'hpr.effective_from <= CURDATE()';
        }
        if ($this->columnExists('household_poverty_records', 'effective_to')) {
            $conditions[] = '(hpr.effective_to IS NULL OR hpr.effective_to >= CURDATE())';
        }

        return 'EXISTS (SELECT 1 FROM household_poverty_records hpr WHERE ' . implode(' AND ', $conditions) . ')';
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
            "cpr.status IN ('ACTIVE','PAUSED')",
            'cpr.deleted_at IS NULL',
            'pst.deleted_at IS NULL',
            'COALESCE(pst.is_active,1)=1',
            $this->policyEffectiveCondition('cpr'),
            $this->citizenActiveCondition('citizens', 'pc'),
            $this->tenantLiteral('citizen_policy_records', 'cpr'),
            $this->tenantLiteral('policy_subject_types', 'pst'),
            $this->tenantLiteral('citizens', 'pc'),
        ];

        return 'EXISTS (SELECT 1 FROM citizen_policy_records cpr INNER JOIN policy_subject_types pst ON pst.id=cpr.policy_type_id INNER JOIN citizens pc ON pc.id=cpr.citizen_id WHERE ' . implode(' AND ', $where) . ')';
    }

    private function policyEffectiveCondition(string $alias): string
    {
        $conditions = [];
        if ($this->columnExists('citizen_policy_records', 'benefit_start_date')) {
            $conditions[] = $alias . '.benefit_start_date <= CURDATE()';
        }
        if ($this->columnExists('citizen_policy_records', 'benefit_end_date')) {
            $conditions[] = '(' . $alias . '.benefit_end_date IS NULL OR ' . $alias . '.benefit_end_date >= CURDATE())';
        }
        return $conditions ? implode(' AND ', $conditions) : '1=1';
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
        if ($this->columnExists($table, 'presence_status')) {
            $conditions[] = "COALESCE(" . $alias . ".presence_status,'AT_HOME') <> 'MOVED_OUT'";
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
