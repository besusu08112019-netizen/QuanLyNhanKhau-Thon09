<?php

namespace App\Services;

use App\Core\BaseModel;
use App\Models\PopulationStatistics;
use App\Policies\HouseholdRelationPolicy;
use App\Policies\InsurancePolicy;

final class DataQualityService extends BaseModel
{
    public const VERSION = '1.0.0';

    private PopulationStatistics $statistics;
    private RiskWarningEngine $riskWarningEngine;

    public function __construct(?PopulationStatistics $statistics = null, ?RiskWarningEngine $riskWarningEngine = null)
    {
        parent::__construct();
        $this->statistics = $statistics ?? new PopulationStatistics();
        $this->riskWarningEngine = $riskWarningEngine ?? new RiskWarningEngine();
    }

    public function summary(array $filters = []): array
    {
        $issues = $this->issues($filters);
        $score = $this->score($issues);
        $completeness = $this->completeness();

        return [
            'engine' => [
                'name' => 'DataQualityService',
                'version' => self::VERSION,
                'generatedAt' => date('c'),
                'mode' => 'read_only',
            ],
            'score' => $score,
            'completeness' => $completeness,
            'totals' => [
                'issues' => array_sum(array_column($issues, 'count')),
                'warnings' => array_sum(array_map(static fn(array $issue): int => $issue['severity'] === 'CRITICAL' ? 0 : (int) $issue['count'], $issues)),
                'critical' => $this->severityTốtal($issues, 'CRITICAL'),
                'high' => $this->severityTốtal($issues, 'HIGH'),
                'medium' => $this->severityTốtal($issues, 'MEDIUM'),
                'low' => $this->severityTốtal($issues, 'LOW'),
            ],
            'groups' => $this->groups($issues),
            'issues' => $issues,
        ];
    }

    public function issueList(array $filters = []): array
    {
        $issues = $this->issues($filters);
        $severity = strtoupper(trim((string) ($filters['severity'] ?? '')));
        $group = trim((string) ($filters['group'] ?? ''));

        return [
            'items' => array_values(array_filter($issues, static function (array $issue) use ($severity, $group): bool {
                if ($severity !== '' && $issue['severity'] !== $severity) return false;
                if ($group !== '' && $issue['group'] !== $group) return false;
                return true;
            })),
            'generatedAt' => date('c'),
        ];
    }

    public function issueDetail(string $code, array $filters = []): array
    {
        $definition = $this->definition($code);
        if (!$definition) {
            throw new \RuntimeException('Không tìm thấy mã lỗi dữ liệu');
        }

        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $rows = $this->detailRows($code, $pageSize, $offset);
        $total = $this->countFor($code);

        return $this->paginated($rows, $page, $pageSize, $total, [
            'issue' => $this->issueRecord($definition, $total),
            'generatedAt' => date('c'),
        ]);
    }

    private function issues(array $filters): array
    {
        $records = [];
        foreach ($this->definitions() as $definition) {
            $count = $this->countFor($definition['code']);
            if (!empty($filters['includeEmpty']) || $count > 0) {
                $records[] = $this->issueRecord($definition, $count);
            }
        }

        usort($records, static function (array $left, array $right): int {
            $rank = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
            return [$rank[$left['severity']] ?? 9, -$left['count'], $left['code']]
                <=> [$rank[$right['severity']] ?? 9, -$right['count'], $right['code']];
        });
        return $records;
    }

    private function issueRecord(array $definition, int $count): array
    {
        return $definition + [
            'count' => $count,
            'quickLink' => '/data-quality?issue=' . rawurlencode($definition['code']),
            'readOnly' => true,
        ];
    }

    private function definitions(): array
    {
        return [
            [
                'code' => 'citizen.missing_identity',
                'name' => 'Thiếu CCCD',
                'group' => 'identity',
                'groupLabel' => 'Dữ liệu định danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhân khẩu chưa có số CCCD/CMND.',
                'impact' => 'Ảnh hưởng đối soát, báo cáo và chính sách.',
                'suggestion' => 'Mở danh sách nhân khẩu và bổ sung số CCCD/CMND.',
            ],
            [
                'code' => 'citizen.missing_date_of_birth',
                'name' => 'Thiếu ngày sinh',
                'group' => 'citizen',
                'groupLabel' => 'Nhân khẩu',
                'severity' => 'CRITICAL',
                'description' => 'Nhân khẩu thiếu ngày sinh nên không thể tính tuổi.',
                'impact' => 'Làm sai thống kê tuổi, chính sách BHYT và bảo trợ.',
                'suggestion' => 'Bổ sung ngày sinh trong hồ sơ nhân khẩu.',
            ],
            [
                'code' => 'citizen.missing_gender',
                'name' => 'Thiếu giới tính',
                'group' => 'citizen',
                'groupLabel' => 'Nhân khẩu',
                'severity' => 'HIGH',
                'description' => 'Nhân khẩu chưa có thông tin giới tính.',
                'impact' => 'Làm sai cơ cấu dân số và một số suy luận quan hệ.',
                'suggestion' => 'Cập nhật giới tính trong hồ sơ nhân khẩu.',
            ],
            [
                'code' => 'citizen.missing_relationship',
                'name' => 'Thiếu quan hệ',
                'group' => 'household_relation',
                'groupLabel' => 'Quan hệ hộ',
                'severity' => 'HIGH',
                'description' => 'Nhân khẩu chưa có quan hệ với chủ hộ.',
                'impact' => 'Làm giảm chất lượng hồ sơ hộ gia đình và báo cáo quan hệ.',
                'suggestion' => 'Cập nhật quan hệ theo HouseholdRelationPolicy.',
            ],
            [
                'code' => 'citizen.invalid_relationship',
                'name' => 'Quan hệ không hợp lệ',
                'group' => 'household_relation',
                'groupLabel' => 'Quan hệ hộ',
                'severity' => 'CRITICAL',
                'description' => 'Quan hệ không nằm trong danh sách quan hệ chuẩn.',
                'impact' => 'Làm sai suy luận hộ gia đình và các cảnh báo dữ liệu.',
                'suggestion' => 'Chọn lại quan hệ theo danh mục quan hệ chuẩn.',
            ],
            [
                'code' => 'citizen.missing_occupation',
                'name' => 'Thiếu nghề nghiệp',
                'group' => 'employment',
                'groupLabel' => 'Lao động',
                'severity' => 'HIGH',
                'description' => 'Nhân khẩu chưa có thông tin nghề nghiệp.',
                'impact' => 'Ảnh hưởng thống kê lao động và báo cáo việc làm.',
                'suggestion' => 'Cập nhật nghề nghiệp hoặc trạng thái lao động.',
            ],
            [
                'code' => 'citizen.missing_health_insurance',
                'name' => 'Thiếu BHYT',
                'group' => 'policy',
                'groupLabel' => 'Chính sách',
                'severity' => 'HIGH',
                'description' => 'Nhân khẩu chưa có thông tin BHYT theo InsurancePolicy.',
                'impact' => 'Ảnh hưởng thống kê BHYT và rà soát chính sách.',
                'suggestion' => 'Cập nhật tình trạng BHYT hoặc lý do chưa có BHYT.',
            ],
            [
                'code' => 'citizen.missing_phone',
                'name' => 'Thiếu số điện thoại',
                'group' => 'citizen',
                'groupLabel' => 'Nhân khẩu',
                'severity' => 'MEDIUM',
                'description' => 'Nhân khẩu chưa có số điện thoại liên hệ.',
                'impact' => 'Khó liên hệ khi rà soát hồ sơ và chính sách.',
                'suggestion' => 'Bổ sung số điện thoại nếu có.',
            ],
            [
                'code' => 'household.missing_code',
                'name' => 'Thiếu mã hộ',
                'group' => 'household',
                'groupLabel' => 'Hộ gia đình',
                'severity' => 'CRITICAL',
                'description' => 'Hộ gia đình chưa có mã hộ.',
                'impact' => 'Ảnh hưởng định danh hộ, import/export và báo cáo.',
                'suggestion' => 'Bổ sung mã hộ duy nhất.',
            ],
            [
                'code' => 'household.missing_address',
                'name' => 'Hộ không có địa chỉ',
                'group' => 'household',
                'groupLabel' => 'Hộ gia đình',
                'severity' => 'HIGH',
                'description' => 'Hộ gia đình chưa có địa chỉ.',
                'impact' => 'Ảnh hưởng quản lý địa bàn, GIS và liên hệ.',
                'suggestion' => 'Cập nhật địa chỉ hộ gia đình.',
            ],
            [
                'code' => 'household.no_head',
                'name' => 'Không có chủ hộ',
                'group' => 'household',
                'groupLabel' => 'Hộ gia đình',
                'severity' => 'CRITICAL',
                'description' => 'Hộ gia đình có thành viên nhưng không có chủ hộ.',
                'impact' => 'Làm sai quan hệ hộ và thống kê hộ.',
                'suggestion' => 'Gán đúng một thành viên làm chủ hộ.',
            ],
            [
                'code' => 'household.multiple_heads',
                'name' => 'Có nhiều chủ hộ',
                'group' => 'household',
                'groupLabel' => 'Hộ gia đình',
                'severity' => 'CRITICAL',
                'description' => 'Hộ gia đình có hơn một thành viên là chủ hộ.',
                'impact' => 'Làm sai cấu trúc hộ gia đình.',
                'suggestion' => 'Giữ lại một chủ hộ và cập nhật quan hệ các thành viên còn lại.',
            ],
            [
                'code' => 'household.duplicate_members',
                'name' => 'Thành viên trùng trong hộ',
                'group' => 'household',
                'groupLabel' => 'Hộ gia đình',
                'severity' => 'HIGH',
                'description' => 'Trong cùng một hộ có thành viên trùng họ tên và ngày sinh.',
                'impact' => 'Có nguy cơ nhập trùng nhân khẩu.',
                'suggestion' => 'Rà soát và gộp/xóa bản ghi trùng nếu đúng nghiệp vụ.',
            ],
            [
                'code' => 'identity.duplicate_identity',
                'name' => 'Trung CCCD',
                'group' => 'identity',
                'groupLabel' => 'Dữ liệu định danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhiều nhân khẩu dùng chung một số CCCD/CMND.',
                'impact' => 'Ảnh hưởng nghiêm trọng đến định danh và báo cáo.',
                'suggestion' => 'Mở danh sách trùng và chỉnh sửa bản ghi sai.',
            ],
            [
                'code' => 'identity.duplicate_phone',
                'name' => 'Trùng số điện thoại',
                'group' => 'identity',
                'groupLabel' => 'Dữ liệu định danh',
                'severity' => 'MEDIUM',
                'description' => 'Nhiều nhân khẩu dùng chung số điện thoại.',
                'impact' => 'Có thể dùng chung số gia đình, cần rà soát khi liên hệ.',
                'suggestion' => 'Kiểm tra và cập nhật số liên hệ riêng nếu có.',
            ],
            [
                'code' => 'identity.duplicate_citizen_code',
                'name' => 'Trùng mã nhân khẩu',
                'group' => 'identity',
                'groupLabel' => 'Dữ liệu định danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhiều nhân khẩu dùng chung mã nhân khẩu.',
                'impact' => 'Ảnh hưởng import/export và đồng bộ dữ liệu.',
                'suggestion' => 'Cập nhật mã nhân khẩu duy nhất.',
            ],
            [
                'code' => 'identity.duplicate_household_code',
                'name' => 'Trùng mã hộ',
                'group' => 'identity',
                'groupLabel' => 'Dữ liệu định danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhiều hộ dùng chung mã hộ.',
                'impact' => 'Ảnh hưởng quản lý hộ, báo cáo và import/export.',
                'suggestion' => 'Cập nhật mã hộ duy nhất.',
            ],
            [
                'code' => 'data.orphan_citizen',
                'name' => 'Hồ sơ nhân khẩu mồ côi',
                'group' => 'data',
                'groupLabel' => 'Dữ liệu',
                'severity' => 'CRITICAL',
                'description' => 'Nhân khẩu không liên kết được với hộ gia đình hợp lệ.',
                'impact' => 'Nhân khẩu có thể không xuất hiện trong báo cáo hộ.',
                'suggestion' => 'Gán lại hộ gia đình hoặc rà soát dữ liệu import.',
            ],
            [
                'code' => 'policy.eligible_health_insurance_missing',
                'name' => 'Đủ điều kiện BHYT nhưng chưa cập nhật',
                'group' => 'policy',
                'groupLabel' => 'Chính sách',
                'severity' => 'HIGH',
                'description' => 'RiskWarningEngine phát hiện hồ sơ đủ điều kiện BHYT nhưng chưa cập nhật.',
                'impact' => 'Ảnh hưởng đến theo dõi chính sách và hỗ trợ người dân.',
                'suggestion' => 'Rà soát hồ sơ BHYT trong danh sách cảnh báo.',
            ],
        ];
    }

    private function countFor(string $code): int
    {
        return match ($code) {
            'citizen.missing_identity' => $this->citizenCount($this->missing('c.identity_number')),
            'citizen.missing_date_of_birth' => $this->citizenCount('c.date_of_birth IS NULL'),
            'citizen.missing_gender' => $this->citizenCount($this->missing('c.gender')),
            'citizen.missing_relationship' => $this->citizenCount($this->missing('c.relationship')),
            'citizen.invalid_relationship' => $this->citizenCount($this->invalidRelationshipCondition()),
            'citizen.missing_occupation' => $this->citizenCount($this->missing('c.occupation')),
            'citizen.missing_health_insurance' => $this->citizenCount($this->healthInsuranceMissingCondition()),
            'citizen.missing_phone' => $this->citizenCount($this->missing('c.phone')),
            'household.missing_code' => $this->householdCount($this->missing('h.household_code')),
            'household.missing_address' => $this->householdCount($this->missing('h.address')),
            'household.no_head' => $this->householdAggregateCount('head_count = 0 AND member_count > 0'),
            'household.multiple_heads' => $this->householdAggregateCount('head_count > 1'),
            'household.duplicate_members' => $this->duplicateMemberCount(),
            'identity.duplicate_identity' => $this->duplicateCitizenColumnCount('identity_number'),
            'identity.duplicate_phone' => $this->duplicateCitizenColumnCount('phone'),
            'identity.duplicate_citizen_code' => $this->duplicateCitizenColumnCount('citizen_code'),
            'identity.duplicate_household_code' => $this->duplicateHouseholdColumnCount('household_code'),
            'data.orphan_citizen' => $this->orphanCitizenCount(),
            'policy.eligible_health_insurance_missing' => $this->riskWarningCount('policy.health_insurance_eligible_missing'),
            default => 0,
        };
    }

    private function detailRows(string $code, int $limit, int $offset): array
    {
        return match ($code) {
            'citizen.missing_identity' => $this->citizenRows($this->missing('c.identity_number'), $limit, $offset),
            'citizen.missing_date_of_birth' => $this->citizenRows('c.date_of_birth IS NULL', $limit, $offset),
            'citizen.missing_gender' => $this->citizenRows($this->missing('c.gender'), $limit, $offset),
            'citizen.missing_relationship' => $this->citizenRows($this->missing('c.relationship'), $limit, $offset),
            'citizen.invalid_relationship' => $this->citizenRows($this->invalidRelationshipCondition(), $limit, $offset),
            'citizen.missing_occupation' => $this->citizenRows($this->missing('c.occupation'), $limit, $offset),
            'citizen.missing_health_insurance' => $this->citizenRows($this->healthInsuranceMissingCondition(), $limit, $offset),
            'citizen.missing_phone' => $this->citizenRows($this->missing('c.phone'), $limit, $offset),
            'household.missing_code' => $this->householdRows($this->missing('h.household_code'), $limit, $offset),
            'household.missing_address' => $this->householdRows($this->missing('h.address'), $limit, $offset),
            'household.no_head' => $this->householdAggregateRows('head_count = 0 AND member_count > 0', $limit, $offset),
            'household.multiple_heads' => $this->householdAggregateRows('head_count > 1', $limit, $offset),
            'household.duplicate_members' => $this->duplicateMemberRows($limit, $offset),
            'identity.duplicate_identity' => $this->duplicateCitizenColumnRows('identity_number', $limit, $offset),
            'identity.duplicate_phone' => $this->duplicateCitizenColumnRows('phone', $limit, $offset),
            'identity.duplicate_citizen_code' => $this->duplicateCitizenColumnRows('citizen_code', $limit, $offset),
            'identity.duplicate_household_code' => $this->duplicateHouseholdColumnRows('household_code', $limit, $offset),
            'data.orphan_citizen' => $this->orphanCitizenRows($limit, $offset),
            'policy.eligible_health_insurance_missing' => $this->riskWarningRows('policy.health_insurance_eligible_missing', $limit, $offset),
            default => [],
        };
    }

    private function score(array $issues): array
    {
        $weights = ['CRITICAL' => 8, 'HIGH' => 5, 'MEDIUM' => 2, 'LOW' => 1];
        $penalty = 0;
        foreach ($issues as $issue) {
            $penalty += (int) $issue['count'] * ($weights[$issue['severity']] ?? 1);
        }
        $records = max(1, $this->totalCitizens() + $this->totalHouseholds());
        $score = max(0, round(100 - min(100, ($penalty / $records) * 10), 1));
        return [
            'value' => $score,
            'label' => $score >= 90 ? 'Tốt' : ($score >= 75 ? 'Cần rà soát' : 'Cần xử lý gấp'),
        ];
    }

    private function completeness(): array
    {
        $totalCitizens = $this->totalCitizens();
        $totalHouseholds = $this->totalHouseholds();
        $citizenChecks = max(0, $totalCitizens * 7);
        $householdChecks = max(0, $totalHouseholds * 2);
        $totalChecks = $citizenChecks + $householdChecks;
        $missing = $this->countFor('citizen.missing_identity')
            + $this->countFor('citizen.missing_date_of_birth')
            + $this->countFor('citizen.missing_gender')
            + $this->countFor('citizen.missing_relationship')
            + $this->countFor('citizen.missing_occupation')
            + $this->countFor('citizen.missing_health_insurance')
            + $this->countFor('citizen.missing_phone')
            + $this->countFor('household.missing_code')
            + $this->countFor('household.missing_address');
        $complete = max(0, $totalChecks - $missing);
        $percent = $totalChecks > 0 ? round(($complete / $totalChecks) * 100, 1) : 100.0;

        return [
            'completeRecords' => $complete,
            'missingRecords' => $missing,
            'completePercent' => $percent,
            'missingPercent' => round(100 - $percent, 1),
        ];
    }

    private function groups(array $issues): array
    {
        $groups = [];
        foreach ($issues as $issue) {
            $key = $issue['group'];
            if (!isset($groups[$key])) {
                $groups[$key] = ['key' => $key, 'label' => $issue['groupLabel'], 'count' => 0, 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
            }
            $groups[$key]['count'] += (int) $issue['count'];
            $groups[$key][strtolower($issue['severity'])] += (int) $issue['count'];
        }
        return array_values($groups);
    }

    private function severityTốtal(array $issues, string $severity): int
    {
        return array_sum(array_map(static fn(array $issue): int => $issue['severity'] === $severity ? (int) $issue['count'] : 0, $issues));
    }

    private function definition(string $code): ?array
    {
        foreach ($this->definitions() as $definition) {
            if ($definition['code'] === $code) return $definition;
        }
        return null;
    }

    private function citizenCount(string $condition): int
    {
        $where = $this->citizenWhere($condition);
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE $where") ?: [])['total'] ?? 0);
    }

    private function citizenRows(string $condition, int $limit, int $offset): array
    {
        $where = $this->citizenWhere($condition);
        return $this->fetchAll("SELECT 'citizen' AS entity_type, c.id AS entity_id, c.citizen_code, c.full_name AS title, h.household_code, h.address, c.identity_number, c.phone, c.relationship, c.date_of_birth, c.gender, c.occupation FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE $where ORDER BY h.household_code, c.full_name LIMIT $limit OFFSET $offset");
    }

    private function householdCount(string $condition): int
    {
        $where = $this->householdWhere($condition);
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM households h WHERE $where") ?: [])['total'] ?? 0);
    }

    private function householdRows(string $condition, int $limit, int $offset): array
    {
        $where = $this->householdWhere($condition);
        return $this->fetchAll("SELECT 'household' AS entity_type, h.id AS entity_id, h.household_code, h.head_citizen_name AS title, h.address, h.phone, h.status FROM households h WHERE $where ORDER BY h.household_code LIMIT $limit OFFSET $offset");
    }

    private function householdAggregateCount(string $condition): int
    {
        $aggregate = $this->householdAggregateSql();
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM ($aggregate) x WHERE $condition", ['head_relation' => HouseholdRelationPolicy::HEAD]) ?: [])['total'] ?? 0);
    }

    private function householdAggregateRows(string $condition, int $limit, int $offset): array
    {
        $aggregate = $this->householdAggregateSql();
        return $this->fetchAll("SELECT 'household' AS entity_type, x.id AS entity_id, x.household_code, x.head_citizen_name AS title, x.address, x.member_count, x.head_count FROM ($aggregate) x WHERE $condition ORDER BY x.household_code LIMIT $limit OFFSET $offset", ['head_relation' => HouseholdRelationPolicy::HEAD]);
    }

    private function householdAggregateSql(): string
    {
        $citizenCondition = $this->statistics->citizenCondition('c');
        $householdCondition = $this->statistics->householdCondition('h');
        return "SELECT h.id, h.household_code, h.head_citizen_name, h.address, COUNT(c.id) AS member_count, COALESCE(SUM(CASE WHEN c.relationship = :head_relation THEN 1 ELSE 0 END),0) AS head_count FROM households h LEFT JOIN citizens c ON c.household_id = h.id AND $citizenCondition WHERE $householdCondition GROUP BY h.id, h.household_code, h.head_citizen_name, h.address";
    }

    private function duplicateMemberCount(): int
    {
        $condition = $this->statistics->citizenCondition('c') . ' AND ' . $this->statistics->householdCondition('h') . ' AND NOT ' . $this->missing('c.full_name') . ' AND c.date_of_birth IS NOT NULL';
        $row = $this->fetchOne("SELECT COALESCE(SUM(x.total),0) AS total FROM (SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE $condition GROUP BY c.household_id, c.full_name, c.date_of_birth HAVING COUNT(*) > 1) x") ?: [];
        return (int) ($row['total'] ?? 0);
    }

    private function duplicateMemberRows(int $limit, int $offset): array
    {
        $condition = $this->statistics->citizenCondition('c') . ' AND ' . $this->statistics->householdCondition('h') . ' AND NOT ' . $this->missing('c.full_name') . ' AND c.date_of_birth IS NOT NULL';
        return $this->fetchAll("SELECT 'citizen' AS entity_type, c.id AS entity_id, c.citizen_code, c.full_name AS title, h.household_code, h.address, c.date_of_birth, c.identity_number FROM citizens c INNER JOIN households h ON h.id = c.household_id INNER JOIN (SELECT household_id, full_name, date_of_birth FROM citizens c WHERE " . $this->statistics->citizenCondition('c') . " GROUP BY household_id, full_name, date_of_birth HAVING COUNT(*) > 1) d ON d.household_id = c.household_id AND d.full_name = c.full_name AND d.date_of_birth = c.date_of_birth WHERE $condition ORDER BY h.household_code, c.full_name LIMIT $limit OFFSET $offset");
    }

    private function duplicateCitizenColumnCount(string $column): int
    {
        if (!$this->columnExists('citizens', $column)) return 0;
        $condition = $this->statistics->citizenCondition('c') . ' AND ' . $this->statistics->householdCondition('h') . ' AND NOT ' . $this->missing('c.' . $column);
        $row = $this->fetchOne("SELECT COALESCE(SUM(x.total),0) AS total FROM (SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE $condition GROUP BY c.$column HAVING COUNT(*) > 1) x") ?: [];
        return (int) ($row['total'] ?? 0);
    }

    private function duplicateCitizenColumnRows(string $column, int $limit, int $offset): array
    {
        if (!$this->columnExists('citizens', $column)) return [];
        $condition = $this->statistics->citizenCondition('c') . ' AND ' . $this->statistics->householdCondition('h') . ' AND NOT ' . $this->missing('c.' . $column);
        return $this->fetchAll("SELECT 'citizen' AS entity_type, c.id AS entity_id, c.citizen_code, c.full_name AS title, h.household_code, h.address, c.$column AS duplicate_value, c.identity_number, c.phone FROM citizens c INNER JOIN households h ON h.id = c.household_id INNER JOIN (SELECT c.$column FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE $condition GROUP BY c.$column HAVING COUNT(*) > 1) d ON d.$column = c.$column WHERE $condition ORDER BY c.$column, h.household_code LIMIT $limit OFFSET $offset");
    }

    private function duplicateHouseholdColumnCount(string $column): int
    {
        if (!$this->columnExists('households', $column)) return 0;
        $condition = $this->statistics->householdCondition('h') . ' AND NOT ' . $this->missing('h.' . $column);
        $row = $this->fetchOne("SELECT COALESCE(SUM(x.total),0) AS total FROM (SELECT COUNT(*) AS total FROM households h WHERE $condition GROUP BY h.$column HAVING COUNT(*) > 1) x") ?: [];
        return (int) ($row['total'] ?? 0);
    }

    private function duplicateHouseholdColumnRows(string $column, int $limit, int $offset): array
    {
        if (!$this->columnExists('households', $column)) return [];
        $condition = $this->statistics->householdCondition('h') . ' AND NOT ' . $this->missing('h.' . $column);
        return $this->fetchAll("SELECT 'household' AS entity_type, h.id AS entity_id, h.household_code, h.head_citizen_name AS title, h.address, h.$column AS duplicate_value FROM households h INNER JOIN (SELECT h.$column FROM households h WHERE $condition GROUP BY h.$column HAVING COUNT(*) > 1) d ON d.$column = h.$column WHERE $condition ORDER BY h.$column LIMIT $limit OFFSET $offset");
    }

    private function orphanCitizenCount(): int
    {
        $where = $this->statistics->citizenCondition('c') . ' AND h.id IS NULL';
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c LEFT JOIN households h ON h.id = c.household_id WHERE $where") ?: [])['total'] ?? 0);
    }

    private function orphanCitizenRows(int $limit, int $offset): array
    {
        $where = $this->statistics->citizenCondition('c') . ' AND h.id IS NULL';
        return $this->fetchAll("SELECT 'citizen' AS entity_type, c.id AS entity_id, c.citizen_code, c.full_name AS title, c.household_id, c.identity_number, c.phone FROM citizens c LEFT JOIN households h ON h.id = c.household_id WHERE $where ORDER BY c.full_name LIMIT $limit OFFSET $offset");
    }

    private function riskWarningCount(string $code): int
    {
        $warnings = $this->riskWarningEngine->warnings(['limitPerRule' => 100]);
        return count(array_filter($warnings['warnings'] ?? [], static fn(array $item): bool => ($item['code'] ?? '') === $code));
    }

    private function riskWarningRows(string $code, int $limit, int $offset): array
    {
        $warnings = array_values(array_filter($this->riskWarningEngine->warnings(['limitPerRule' => 100])['warnings'] ?? [], static fn(array $item): bool => ($item['code'] ?? '') === $code));
        return array_map(static function (array $item): array {
            return [
                'entity_type' => $item['entity']['type'] ?? '',
                'entity_id' => $item['entity']['id'] ?? 0,
                'title' => $item['entity']['label'] ?? $item['title'] ?? '',
                'household_code' => $item['entity']['householdCode'] ?? '',
                'message' => $item['message'] ?? '',
            ];
        }, array_slice($warnings, $offset, $limit));
    }

    private function citizenWhere(string $condition): string
    {
        return $this->statistics->citizenCondition('c') . ' AND ' . $this->statistics->householdCondition('h') . ' AND ' . $condition;
    }

    private function householdWhere(string $condition): string
    {
        return $this->statistics->householdCondition('h') . ' AND ' . $condition;
    }

    private function invalidRelationshipCondition(): string
    {
        $relationships = array_map(static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'", HouseholdRelationPolicy::standardRelationships());
        return 'NOT ' . $this->missing('c.relationship') . ' AND c.relationship NOT IN (' . implode(',', $relationships) . ')';
    }

    private function healthInsuranceMissingCondition(): string
    {
        return InsurancePolicy::missingConditionSql('c', $this->columnExists('citizens', 'has_health_insurance'));
    }

    private function totalCitizens(): int
    {
        return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE ' . $this->statistics->citizenCondition('c') . ' AND ' . $this->statistics->householdCondition('h')) ?: [])['total'] ?? 0);
    }

    private function totalHouseholds(): int
    {
        return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->statistics->householdCondition('h')) ?: [])['total'] ?? 0);
    }

    private function missing(string $field): string
    {
        return '(' . $field . ' IS NULL OR TRIM(' . $field . ') = "")';
    }
}
