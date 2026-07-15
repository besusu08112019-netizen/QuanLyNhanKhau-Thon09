<?php

namespace App\Services;

final class ContributionRuleEngine
{
    private const LABOR_AGE = [
        'min_years' => 15,
        'male_start_year' => 2021,
        'male_start_months' => 723,
        'male_step_months' => 3,
        'male_max_months' => 744,
        'female_start_year' => 2021,
        'female_start_months' => 664,
        'female_step_months' => 4,
        'female_max_months' => 720,
    ];

    public const UNIT_TYPES = [
        'HOUSEHOLD' => 'Theo hộ',
        'PERSON' => 'Theo nhân khẩu',
        'AREA' => 'Theo diện tích',
        'VEHICLE' => 'Theo phương tiện',
        'ONCE' => 'Theo lần',
        'OTHER' => 'Khác',
    ];

    public const TARGET_OPTIONS = [
        'ALL_HOUSEHOLDS' => 'Thu toàn bộ hộ',
        'ALL_PEOPLE' => 'Thu toàn bộ nhân khẩu',
        'LABOR_AGE' => 'Chỉ thu người trong độ tuổi lao động',
        'NON_LABOR_AGE' => 'Chỉ thu người ngoài độ tuổi lao động',
        'AGE_RANGE' => 'Chỉ thu người từ ... tuổi đến ... tuổi',
        'AGE_FROM' => 'Chỉ thu người từ ... tuổi trở lên',
        'CHILDREN' => 'Chỉ thu trẻ em',
        'ELDERLY' => 'Chỉ thu người cao tuổi',
        'OTHER' => 'Khác',
    ];

    public const EXEMPTION_OPTIONS = [
        'PRESENCE_AWAY' => 'Người đi vắng',
        'AGE_FROM_CONFIG' => 'Người từ độ tuổi cấu hình trở lên',
        'NON_LABOR_AGE' => 'Người ngoài độ tuổi lao động',
        'CHILDREN' => 'Trẻ em',
        'ELDERLY' => 'Người cao tuổi',
        'DISABLED' => 'Người khuyết tật',
        'MERITORIOUS' => 'Người có công',
        'ACTIVE_SOLDIER' => 'Bộ đội đang phục vụ',
        'POOR_HOUSEHOLD' => 'Hộ nghèo',
        'NEAR_POOR_HOUSEHOLD' => 'Hộ cận nghèo',
        'SOCIAL_ASSISTANCE' => 'Người thuộc diện bảo trợ xã hội',
        'POLICY' => 'Đối tượng chính sách',
        'COMMUNE_DECISION' => 'Miễn theo quyết định của UBND xã',
        'HAMLET_DECISION' => 'Miễn theo quyết định của Trưởng thôn',
        'OTHER' => 'Khác',
    ];

    public function calculateHousehold(array $campaign, array $household, array $members): array
    {
        $target = $this->decodeConfig($campaign['target_config_json'] ?? null);
        $exemption = $this->decodeConfig($campaign['exemption_config_json'] ?? null);
        $unitType = strtoupper((string) ($campaign['unit_type'] ?? 'HOUSEHOLD'));
        $amount = max(0.0, (float) ($campaign['amount'] ?? 0));

        $totalMembers = count($members);
        $targetMembers = [];
        foreach ($members as $member) {
            if ($this->matchesTarget($member, $target)) {
                $targetMembers[] = $member;
            }
        }

        if ($unitType === 'HOUSEHOLD' && $targetMembers === [] && $this->householdMatchesTarget($target)) {
            $targetMembers = $members ?: [['id' => null, 'full_name' => (string) ($household['head_citizen_name'] ?? 'Chủ hộ')]];
            $totalMembers = max(1, $totalMembers);
        }

        $exemptMembers = [];
        foreach ($targetMembers as $member) {
            if ($this->matchesExemption($member, $household, $exemption)) {
                $exemptMembers[] = $member;
            }
        }

        $householdExempt = $this->householdExempt($household, $exemption);
        $targetCount = count($targetMembers);
        $policyExemptCount = $householdExempt ? $targetCount : count($exemptMembers);
        $chargeableCount = max(0, $targetCount - $policyExemptCount);
        $exemptCount = max(0, $totalMembers - $chargeableCount);

        $grossAmount = match ($unitType) {
            'PERSON' => $totalMembers * $amount,
            default => $totalMembers > 0 ? $amount : 0.0,
        };
        $exemptAmount = $householdExempt
            ? $grossAmount
            : (match ($unitType) {
                'PERSON' => $exemptCount * $amount,
                default => $chargeableCount <= 0 && $totalMembers > 0 ? $amount : 0.0,
            });
        $discount = $this->householdDiscount($household, $exemption, max(0.0, $grossAmount - $exemptAmount));
        $expectedAmount = max(0.0, $grossAmount - $exemptAmount - $discount['amount']);

        return [
            'eligible_count' => $totalMembers,
            'exempt_count' => $exemptCount,
            'chargeable_count' => $chargeableCount,
            'gross_amount' => $grossAmount,
            'exempt_amount' => $exemptAmount,
            'discount_amount' => $discount['amount'],
            'discount_percent' => $discount['percent'],
            'discount_reason' => $discount['reason'],
            'has_auto_discount' => $discount['amount'] > 0,
            'expected_amount' => $expectedAmount,
            'exempt_subjects' => array_map(fn($m) => [
                'citizen_id' => $m['id'] ?? null,
                'full_name' => (string) ($m['full_name'] ?? ''),
                'reason' => (string) ($m['_exemption_reason'] ?? $this->exemptionReason($m, $household, $exemption)),
            ], $this->exemptSubjects($members, $targetMembers, $householdExempt ? $targetMembers : $exemptMembers, $household, $exemption)),
            'note' => json_encode([
                'unit_type' => $unitType,
                'target' => $target,
                'exemption' => $exemption,
                'household_exempt' => $householdExempt,
                'target_count' => $targetCount,
                'policy_exempt_count' => $policyExemptCount,
                'unit_price' => $amount,
                'discount' => $discount,
            ], JSON_UNESCAPED_UNICODE),
        ];
    }

    private function exemptSubjects(array $members, array $targetMembers, array $policyExemptMembers, array $household, array $exemption): array
    {
        $targetIds = array_flip(array_map(fn($m) => (string) ($m['id'] ?? spl_object_id((object) $m)), $targetMembers));
        $policyIds = array_flip(array_map(fn($m) => (string) ($m['id'] ?? spl_object_id((object) $m)), $policyExemptMembers));
        $subjects = [];
        foreach ($members as $member) {
            $key = (string) ($member['id'] ?? spl_object_id((object) $member));
            if (isset($policyIds[$key])) {
                $subjects[] = $member + ['_exemption_reason' => $this->exemptionReason($member, $household, $exemption)];
                continue;
            }
            if (!isset($targetIds[$key])) {
                $subjects[] = $member + ['_exemption_reason' => 'Không thuộc đối tượng phải đóng góp'];
            }
        }
        return $subjects;
    }

    private function householdMatchesTarget(array $config): bool
    {
        $conditions = $this->conditions($config, ['ALL_HOUSEHOLDS']);
        return $conditions === [] || in_array('ALL_HOUSEHOLDS', $conditions, true);
    }

    private function matchesTarget(array $member, array $config): bool
    {
        $conditions = $this->conditions($config, ['ALL_HOUSEHOLDS']);
        if ($conditions === [] || in_array('ALL_HOUSEHOLDS', $conditions, true) || in_array('ALL_PEOPLE', $conditions, true)) return true;
        $age = $this->age($member['date_of_birth'] ?? null);
        foreach ($conditions as $condition) {
            if ($condition === 'LABOR_AGE' && $this->isLaborAge($member)) return true;
            if ($condition === 'NON_LABOR_AGE' && !$this->isLaborAge($member)) return true;
            if ($condition === 'CHILDREN' && $age !== null && $age < 16) return true;
            if ($condition === 'ELDERLY' && $age !== null && $age >= 60) return true;
            if ($condition === 'AGE_FROM' && $age !== null && $age >= (int) ($config['age_from'] ?? 0)) return true;
            if ($condition === 'AGE_RANGE' && $age !== null && $age >= (int) ($config['age_from'] ?? 0) && $age <= (int) ($config['age_to'] ?? 200)) return true;
            if ($condition === 'OTHER') return true;
        }
        return false;
    }

    private function matchesExemption(array $member, array $household, array $config): bool
    {
        foreach ($this->personRules($config) as $rule) {
            if ($this->evaluateRule($member, $household, $rule)) return true;
        }
        $conditions = $this->conditions($config, []);
        foreach ($conditions as $condition) {
            if ($condition === 'PRESENCE_AWAY' && $this->evaluateRule($member, $household, ['rule' => 'FIELD_EQUALS', 'scope' => 'person', 'field' => 'presence_status', 'value' => 'AWAY'])) return true;
            if ($condition === 'AGE_FROM_CONFIG' && $this->evaluateRule($member, $household, ['rule' => 'AGE_GTE', 'value' => (int) ($config['age_from'] ?? 0)])) return true;
            if ($condition === 'NON_LABOR_AGE' && !$this->isLaborAge($member)) return true;
            if ($condition === 'CHILDREN' && (($this->age($member['date_of_birth'] ?? null) ?? 999) < 16)) return true;
            if ($condition === 'ELDERLY' && (($this->age($member['date_of_birth'] ?? null) ?? 0) >= 60)) return true;
            if ($condition === 'DISABLED' && (int) ($member['disabled_person'] ?? 0) === 1) return true;
            if ($condition === 'MERITORIOUS' && ((int) ($member['meritorious_person'] ?? 0) === 1 || (int) ($household['meritorious_family'] ?? 0) === 1)) return true;
            if ($condition === 'ACTIVE_SOLDIER' && $this->textHas($member, ['bộ đội', 'bo doi', 'quân nhân', 'quan nhan', 'military', 'soldier'])) return true;
            if ($condition === 'SOCIAL_ASSISTANCE' && (int) ($member['social_assistance'] ?? 0) === 1) return true;
            if ($condition === 'POOR_HOUSEHOLD' && (int) ($household['poor_household'] ?? 0) === 1) return true;
            if ($condition === 'NEAR_POOR_HOUSEHOLD' && (int) ($household['near_poor_household'] ?? 0) === 1) return true;
            if ($condition === 'POLICY' && $this->noteHas($household, ['chính sách', 'chinh sach', 'policy'])) return true;
            if (in_array($condition, ['COMMUNE_DECISION', 'HAMLET_DECISION', 'OTHER'], true) && $this->noteHas($household, ['miễn', 'mien', 'quyết định', 'quyet dinh'])) return true;
        }
        return false;
    }

    private function householdDiscount(array $household, array $config, float $baseAmount): array
    {
        foreach ($this->householdDiscountRules($config) as $rule) {
            if (!$this->evaluateRule([], $household, $rule + ['scope' => 'household'])) continue;
            $percent = max(0.0, min(100.0, (float) ($rule['percent'] ?? 0)));
            $amount = max(0.0, (float) ($rule['amount'] ?? 0));
            if ($percent > 0) $amount = round($baseAmount * $percent / 100, 2);
            if ($amount <= 0) continue;
            return [
                'amount' => min($baseAmount, $amount),
                'percent' => $percent,
                'reason' => (string) ($rule['label'] ?? $rule['rule'] ?? 'Giảm theo cấu hình khoản thu'),
            ];
        }
        return ['amount' => 0.0, 'percent' => 0.0, 'reason' => ''];
    }

    private function householdExempt(array $household, array $config): bool
    {
        $conditions = $this->conditions($config, []);
        foreach ($conditions as $condition) {
            if ($condition === 'POOR_HOUSEHOLD' && (int) ($household['poor_household'] ?? 0) === 1) return true;
            if ($condition === 'NEAR_POOR_HOUSEHOLD' && (int) ($household['near_poor_household'] ?? 0) === 1) return true;
            if ($condition === 'POLICY' && $this->noteHas($household, ['chính sách', 'chinh sach', 'policy'])) return true;
            if (in_array($condition, ['COMMUNE_DECISION', 'HAMLET_DECISION', 'OTHER'], true) && $this->noteHas($household, ['miễn toàn bộ', 'mien toan bo'])) return true;
        }
        return false;
    }

    private function exemptionReason(array $member, array $household, array $config): string
    {
        foreach ($this->personRules($config) as $rule) {
            if ($this->evaluateRule($member, $household, $rule)) return (string) ($rule['label'] ?? 'Miễn theo cấu hình khoản thu');
        }
        foreach ($this->conditions($config, []) as $condition) {
            if ($this->matchesExemption($member, $household, ['conditions' => [$condition]])) {
                return self::EXEMPTION_OPTIONS[$condition] ?? $condition;
            }
        }
        return 'Miễn theo cấu hình khoản thu';
    }

    private function personRules(array $config): array
    {
        return $this->rulesFrom($config, ['person_exemptions', 'personRules', 'person_rules']);
    }

    private function householdDiscountRules(array $config): array
    {
        return $this->rulesFrom($config, ['household_discounts', 'householdDiscounts', 'discount_rules', 'discountRules']);
    }

    private function rulesFrom(array $config, array $keys): array
    {
        foreach ($keys as $key) {
            $rules = $config[$key] ?? null;
            if (is_string($rules)) $rules = json_decode($rules, true);
            if (is_array($rules)) return array_values(array_filter($rules, 'is_array'));
        }
        return [];
    }

    private function evaluateRule(array $member, array $household, array $rule): bool
    {
        $name = strtoupper(trim((string) ($rule['rule'] ?? $rule['type'] ?? '')));
        $scope = strtolower(trim((string) ($rule['scope'] ?? 'person')));
        $source = $scope === 'household' ? $household : $member;
        $field = (string) ($rule['field'] ?? '');
        $actual = $field !== '' ? ($source[$field] ?? null) : null;
        return match ($name) {
            'AGE_GTE' => (($this->age($member['date_of_birth'] ?? null) ?? -1) >= (int) ($rule['value'] ?? $rule['age'] ?? 0)),
            'AGE_LT' => (($this->age($member['date_of_birth'] ?? null) ?? 999) < (int) ($rule['value'] ?? $rule['age'] ?? 0)),
            'BOOLEAN_TRUE' => $field !== '' && (int) ($actual ?? 0) === 1,
            'FIELD_EQUALS' => $field !== '' && strtoupper((string) $actual) === strtoupper((string) ($rule['value'] ?? '')),
            'TEXT_CONTAINS' => $field !== '' && $this->containsAny((string) ($actual ?? ''), (array) ($rule['values'] ?? [$rule['value'] ?? ''])),
            'TEXT_ANY_CONTAINS' => $this->textHas($source, (array) ($rule['values'] ?? [$rule['value'] ?? ''])),
            default => false,
        };
    }

    private function isLaborAge(array $member): bool
    {
        $ageMonths = $this->ageMonths($member['date_of_birth'] ?? null);
        if ($ageMonths === null || $ageMonths < self::LABOR_AGE['min_years'] * 12) return false;
        $gender = (string) ($member['gender'] ?? '');
        $year = (int) date('Y');
        if ($gender === 'Nữ') {
            $retirement = min(
                self::LABOR_AGE['female_max_months'],
                self::LABOR_AGE['female_start_months'] + max(0, $year - self::LABOR_AGE['female_start_year']) * self::LABOR_AGE['female_step_months']
            );
            return $ageMonths < $retirement;
        }
        $retirement = min(
            self::LABOR_AGE['male_max_months'],
            self::LABOR_AGE['male_start_months'] + max(0, $year - self::LABOR_AGE['male_start_year']) * self::LABOR_AGE['male_step_months']
        );
        return $ageMonths < $retirement;
    }

    private function age(mixed $date): ?int
    {
        if (!$date) return null;
        try {
            $dob = new \DateTimeImmutable((string) $date);
            return (int) $dob->diff(new \DateTimeImmutable('today'))->y;
        } catch (\Throwable) {
            return null;
        }
    }

    private function ageMonths(mixed $date): ?int
    {
        if (!$date) return null;
        try {
            $dob = new \DateTimeImmutable((string) $date);
            $diff = $dob->diff(new \DateTimeImmutable('today'));
            return $diff->y * 12 + $diff->m;
        } catch (\Throwable) {
            return null;
        }
    }

    private function decodeConfig(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function conditions(array $config, array $default): array
    {
        $raw = $config['conditions'] ?? $config['targets'] ?? $config['exemptions'] ?? $default;
        if (is_string($raw)) $raw = array_filter(array_map('trim', explode(',', $raw)));
        if (!is_array($raw)) return $default;
        return array_values(array_unique(array_map(fn($v) => strtoupper(trim((string) $v)), $raw)));
    }

    private function noteHas(array $household, array $needles): bool
    {
        return $this->containsAny((string) ($household['note'] ?? ''), $needles);
    }

    private function textHas(array $row, array $needles): bool
    {
        return $this->containsAny(implode(' ', array_map(fn($value) => is_scalar($value) ? (string) $value : '', $row)), $needles);
    }

    private function containsAny(string $text, array $needles): bool
    {
        $text = mb_strtolower($text, 'UTF-8');
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, mb_strtolower((string) $needle, 'UTF-8'))) return true;
        }
        return false;
    }
}
