<?php

namespace App\Services;

use App\Core\BaseModel;
use App\Models\PolicyAlert;
use App\Models\PopulationStatistics;
use App\Policies\AgePolicy;
use App\Policies\HouseholdRelationPolicy;
use App\Policies\InsurancePolicy;
use App\PolicyEngine\BusinessRuleCenter;

final class RiskWarningEngine extends BaseModel
{
    public const VERSION = '1.0.0';
    private const DEFAULT_LIMIT_PER_RULE = 25;

    private PopulationStatistics $statistics;
    private CitizenInsightEngine $citizenInsightEngine;
    private BusinessRuleCenter $businessRuleCenter;

    public function __construct(
        ?PopulationStatistics $statistics = null,
        ?CitizenInsightEngine $citizenInsightEngine = null,
        ?BusinessRuleCenter $businessRuleCenter = null,
    ) {
        parent::__construct();
        $this->statistics = $statistics ?? new PopulationStatistics();
        $this->citizenInsightEngine = $citizenInsightEngine ?? new CitizenInsightEngine($this->statistics);
        $this->businessRuleCenter = $businessRuleCenter ?? new BusinessRuleCenter();
    }

    public function warnings(array $filters = []): array
    {
        $limit = min(max((int) ($filters['limitPerRule'] ?? self::DEFAULT_LIMIT_PER_RULE), 1), 100);
        $items = array_merge(
            $this->policyWarnings($limit),
            $this->dataWarnings($limit),
            $this->householdWarnings($limit),
            $this->laborWarnings($limit),
            $this->studentWarnings($limit),
        );

        return [
            'engine' => [
                'name' => 'RiskWarningEngine',
                'version' => self::VERSION,
                'generatedAt' => date('c'),
                'source' => [
                    'policyCenter' => $this->businessRuleCenter->health(),
                    'citizenInsight' => $this->citizenInsightEngine->summary($filters)['engine'] ?? [],
                ],
            ],
            'summary' => $this->summary($items),
            'warnings' => $items,
        ];
    }

    private function policyWarnings(int $limit): array
    {
        return array_merge(
            $this->citizenRule(
                'policy.upcoming_70',
                'policy',
                'medium',
                'Sap du 70 tuoi',
                PolicyAlert::filterCondition('upcoming_70', 'c') ?? '0=1',
                'Ra soat BHYT cho nguoi sap den nguong chinh sach.',
                $limit
            ),
            $this->citizenRule(
                'policy.upcoming_75',
                'policy',
                'medium',
                'Sap du 75 tuoi',
                PolicyAlert::filterCondition('upcoming_75', 'c') ?? '0=1',
                'Ra soat bao tro xa hoi cho nguoi sap den nguong chinh sach.',
                $limit
            ),
            $this->citizenRule(
                'policy.health_insurance_eligible_missing',
                'policy',
                'high',
                'Du dieu kien BHYT nhung chua co BHYT',
                '(' . AgePolicy::ageSql('c') . ' >= ' . InsurancePolicy::DEFAULT_AGE . ' OR ' . StudentStatusService::studentSql('c') . ') AND ' . InsurancePolicy::missingConditionSql('c', $this->columnExists('citizens', 'has_health_insurance')),
                'Cap nhat thong tin BHYT hoac ra soat dien chinh sach.',
                $limit
            ),
            $this->citizenRule(
                'policy.social_support_eligible',
                'policy',
                'high',
                'Du dieu kien bao tro xa hoi',
                AgePolicy::ageSql('c') . ' >= ' . AgePolicy::SOCIAL_ALLOWANCE_DEFAULT_AGE . ' AND ' . $this->missingFlag('c.social_assistance'),
                'Ra soat ho so bao tro xa hoi.',
                $limit
            )
        );
    }

    private function dataWarnings(int $limit): array
    {
        return array_merge(
            $this->citizenRule('data.missing_identity', 'data', 'medium', 'Thieu CCCD', $this->missing('c.identity_number'), 'Bo sung CCCD.', $limit),
            $this->citizenRule('data.missing_phone', 'data', 'low', 'Thieu so dien thoai', $this->missing('c.phone'), 'Bo sung so dien thoai lien he.', $limit),
            $this->citizenRule('data.missing_date_of_birth', 'data', 'high', 'Thieu ngay sinh', 'c.date_of_birth IS NULL', 'Bo sung ngay sinh de tinh chinh sach chinh xac.', $limit),
            $this->citizenRule('data.missing_gender', 'data', 'medium', 'Thieu gioi tinh', $this->missing('c.gender'), 'Bo sung gioi tinh.', $limit),
            $this->citizenRule('data.missing_relationship', 'data', 'medium', 'Thieu quan he voi chu ho', $this->missing('c.relationship'), 'Cap nhat quan he trong ho.', $limit)
        );
    }

    private function householdWarnings(int $limit): array
    {
        $age = AgePolicy::ageSql('c');
        $citizenCondition = $this->statistics->citizenCondition('c');

        $aggregate = "SELECT h.id, h.household_code, h.head_citizen_name, h.address,
            COUNT(c.id) AS member_count,
            COALESCE(SUM(CASE WHEN " . AgePolicy::statisticalElderlyConditionSql('c') . " THEN 1 ELSE 0 END),0) AS elderly_count,
            COALESCE(SUM(CASE WHEN " . AgePolicy::childConditionSql('c') . " THEN 1 ELSE 0 END),0) AS child_count,
            COALESCE(SUM(CASE WHEN c.relationship = :head_relation THEN 1 ELSE 0 END),0) AS head_count,
            COALESCE(SUM(CASE WHEN " . $this->missing('c.relationship') . " THEN 1 ELSE 0 END),0) AS missing_relationship_count,
            MIN(CASE WHEN c.date_of_birth IS NOT NULL THEN $age ELSE NULL END) AS min_age,
            MAX(CASE WHEN c.date_of_birth IS NOT NULL THEN $age ELSE NULL END) AS max_age
        FROM households h
        LEFT JOIN citizens c ON c.household_id = h.id AND $citizenCondition
        WHERE " . $this->statistics->householdCondition('h') . "
        GROUP BY h.id, h.household_code, h.head_citizen_name, h.address";

        return array_merge(
            $this->householdRule(
                'household.elderly_only',
                'household',
                'medium',
                'Ho chi co nguoi cao tuoi',
                "member_count > 0 AND elderly_count = member_count",
                'Ra soat ho tro ho nguoi cao tuoi.',
                $aggregate,
                $limit
            ),
            $this->householdRule(
                'household.children_only',
                'household',
                'high',
                'Ho chi co tre em',
                "member_count > 0 AND child_count = member_count",
                'Kiem tra nguoi giam ho va quan he ho.',
                $aggregate,
                $limit
            ),
            $this->householdRule(
                'household.relationship_anomaly',
                'household',
                'high',
                'Quan he ho bat thuong',
                "head_count <> 1 OR missing_relationship_count > 0",
                'Ra soat chu ho va quan he thanh vien.',
                $aggregate,
                $limit
            )
        );
    }

    private function laborWarnings(int $limit): array
    {
        return array_merge(
            $this->citizenRule(
                'labor.working_age_missing_occupation',
                'labor',
                'medium',
                'Trong do tuoi lao dong nhung chua co nghe nghiep',
                AgePolicy::workingAgeConditionSql('c') . ' AND ' . $this->missing('c.occupation'),
                'Cap nhat nghe nghiep hoac trang thai lao dong.',
                $limit
            ),
            $this->citizenRule(
                'labor.occupation_missing_contact',
                'labor',
                'low',
                'Co nghe nghiep nhung thieu thong tin lien quan',
                'NOT ' . $this->missing('c.occupation') . ' AND ' . $this->missing('c.phone'),
                'Bo sung thong tin lien he de phuc vu quan ly lao dong.',
                $limit
            )
        );
    }

    private function studentWarnings(int $limit): array
    {
        $student = StudentStatusService::studentSql('c');
        return array_merge(
            $this->citizenRule(
                'student.school_age_not_updated',
                'student',
                'medium',
                'Den tuoi di hoc nhung chua cap nhat',
                $student . ' AND ' . $this->missingFlag('c.pupil') . ' AND ' . $this->missingFlag('c.student'),
                'Cap nhat trang thai hoc sinh/sinh vien.',
                $limit
            ),
            $this->citizenRule(
                'student.incomplete_student_data',
                'student',
                'low',
                'Du lieu hoc sinh chua day du',
                $student . ' AND (' . $this->missing('c.education_level') . ' OR ' . $this->missing('c.occupation') . ')',
                'Bo sung lop/trinh do hoac nghe nghiep hoc sinh.',
                $limit
            )
        );
    }

    private function citizenRule(string $code, string $group, string $severity, string $title, string $condition, string $action, int $limit): array
    {
        $where = [
            $this->statistics->citizenCondition('c'),
            $this->statistics->householdCondition('h'),
            $condition,
        ];
        $rows = $this->fetchAll(
            "SELECT c.id, c.citizen_code, c.full_name, c.date_of_birth, c.identity_number, c.phone, c.relationship,
                h.household_code, h.head_citizen_name, h.address,
                " . AgePolicy::ageSql('c') . " AS age
             FROM citizens c
             INNER JOIN households h ON h.id = c.household_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY h.household_code ASC, c.full_name ASC
             LIMIT $limit"
        );

        return array_map(fn(array $row): array => $this->warning($code, $group, $severity, $title, 'citizen', $row, $action), $rows);
    }

    private function householdRule(string $code, string $group, string $severity, string $title, string $condition, string $action, string $aggregateSql, int $limit): array
    {
        $rows = $this->fetchAll(
            "SELECT x.* FROM ($aggregateSql) x WHERE $condition ORDER BY x.household_code ASC LIMIT $limit",
            ['head_relation' => HouseholdRelationPolicy::HEAD]
        );

        return array_map(fn(array $row): array => $this->warning($code, $group, $severity, $title, 'household', $row, $action), $rows);
    }

    private function warning(string $code, string $group, string $severity, string $title, string $entityType, array $row, string $action): array
    {
        $entityId = (int) ($row['id'] ?? 0);
        $label = $entityType === 'household'
            ? trim((string) ($row['household_code'] ?? ''))
            : trim((string) ($row['full_name'] ?? ''));

        return [
            'id' => $code . ':' . $entityType . ':' . $entityId,
            'code' => $code,
            'group' => $group,
            'severity' => $severity,
            'title' => $title,
            'message' => $label !== '' ? $title . ': ' . $label : $title,
            'entity' => [
                'type' => $entityType,
                'id' => $entityId,
                'label' => $label,
                'householdCode' => $row['household_code'] ?? null,
            ],
            'action' => [
                'label' => $action,
                'target' => $entityType,
            ],
            'evidence' => array_filter([
                'age' => isset($row['age']) ? (int) $row['age'] : null,
                'dateOfBirth' => $row['date_of_birth'] ?? null,
                'identityNumber' => $row['identity_number'] ?? null,
                'phone' => $row['phone'] ?? null,
                'relationship' => $row['relationship'] ?? null,
                'headCitizenName' => $row['head_citizen_name'] ?? null,
                'address' => $row['address'] ?? null,
                'memberCount' => isset($row['member_count']) ? (int) $row['member_count'] : null,
            ], static fn($value) => $value !== null && $value !== ''),
            'source' => [
                'businessRuleCenter' => true,
                'citizenInsightEngine' => true,
                'policyEngine' => true,
            ],
        ];
    }

    private function summary(array $warnings): array
    {
        $byGroup = [];
        $bySeverity = [];
        foreach ($warnings as $warning) {
            $byGroup[$warning['group']] = ($byGroup[$warning['group']] ?? 0) + 1;
            $bySeverity[$warning['severity']] = ($bySeverity[$warning['severity']] ?? 0) + 1;
        }
        ksort($byGroup);
        ksort($bySeverity);

        return [
            'total' => count($warnings),
            'byGroup' => $byGroup,
            'bySeverity' => $bySeverity,
        ];
    }

    private function missing(string $field): string
    {
        return '(' . $field . ' IS NULL OR TRIM(' . $field . ') = "")';
    }

    private function missingFlag(string $field): string
    {
        [$tableAlias, $column] = explode('.', $field, 2) + ['', ''];
        unset($tableAlias);
        if ($column === '' || !$this->columnExists('citizens', $column)) {
            return '1=1';
        }
        return 'COALESCE(' . $field . ',0)=0';
    }
}
