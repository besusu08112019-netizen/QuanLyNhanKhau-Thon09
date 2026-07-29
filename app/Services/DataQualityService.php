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
                'critical' => $this->severityTotal($issues, 'CRITICAL'),
                'high' => $this->severityTotal($issues, 'HIGH'),
                'medium' => $this->severityTotal($issues, 'MEDIUM'),
                'low' => $this->severityTotal($issues, 'LOW'),
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
            throw new \RuntimeException('Khong tim thay ma loi du lieu');
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
                'name' => 'Thieu CCCD',
                'group' => 'identity',
                'groupLabel' => 'Du lieu dinh danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhan khau chua co so CCCD/CMND.',
                'impact' => 'Anh huong doi soat, bao cao va chinh sach.',
                'suggestion' => 'Mo danh sach nhan khau va bo sung so CCCD/CMND.',
            ],
            [
                'code' => 'citizen.missing_date_of_birth',
                'name' => 'Thieu ngay sinh',
                'group' => 'citizen',
                'groupLabel' => 'Nhan khau',
                'severity' => 'CRITICAL',
                'description' => 'Nhan khau thieu ngay sinh nen khong the tinh tuoi.',
                'impact' => 'Lam sai thong ke tuoi, chinh sach BHYT va bao tro.',
                'suggestion' => 'Bo sung ngay sinh trong ho so nhan khau.',
            ],
            [
                'code' => 'citizen.missing_gender',
                'name' => 'Thieu gioi tinh',
                'group' => 'citizen',
                'groupLabel' => 'Nhan khau',
                'severity' => 'HIGH',
                'description' => 'Nhan khau chua co thong tin gioi tinh.',
                'impact' => 'Lam sai co cau dan so va mot so suy luan quan he.',
                'suggestion' => 'Cap nhat gioi tinh trong ho so nhan khau.',
            ],
            [
                'code' => 'citizen.missing_relationship',
                'name' => 'Thieu quan he',
                'group' => 'household_relation',
                'groupLabel' => 'Quan he ho',
                'severity' => 'HIGH',
                'description' => 'Nhan khau chua co quan he voi chu ho.',
                'impact' => 'Lam giam chat luong ho so ho gia dinh va bao cao quan he.',
                'suggestion' => 'Cap nhat quan he theo HouseholdRelationPolicy.',
            ],
            [
                'code' => 'citizen.invalid_relationship',
                'name' => 'Quan he khong hop le',
                'group' => 'household_relation',
                'groupLabel' => 'Quan he ho',
                'severity' => 'CRITICAL',
                'description' => 'Quan he khong nam trong danh sach quan he chuan.',
                'impact' => 'Lam sai suy luan ho gia dinh va cac canh bao du lieu.',
                'suggestion' => 'Chon lai quan he theo danh muc quan he chuan.',
            ],
            [
                'code' => 'citizen.missing_occupation',
                'name' => 'Thieu nghe nghiep',
                'group' => 'employment',
                'groupLabel' => 'Lao dong',
                'severity' => 'HIGH',
                'description' => 'Nhan khau chua co thong tin nghe nghiep.',
                'impact' => 'Anh huong thong ke lao dong va bao cao viec lam.',
                'suggestion' => 'Cap nhat nghe nghiep hoac trang thai lao dong.',
            ],
            [
                'code' => 'citizen.missing_health_insurance',
                'name' => 'Thieu BHYT',
                'group' => 'policy',
                'groupLabel' => 'Chinh sach',
                'severity' => 'HIGH',
                'description' => 'Nhan khau chua co thong tin BHYT theo InsurancePolicy.',
                'impact' => 'Anh huong thong ke BHYT va ra soat chinh sach.',
                'suggestion' => 'Cap nhat tinh trang BHYT hoac ly do chua co BHYT.',
            ],
            [
                'code' => 'citizen.missing_phone',
                'name' => 'Thieu so dien thoai',
                'group' => 'citizen',
                'groupLabel' => 'Nhan khau',
                'severity' => 'MEDIUM',
                'description' => 'Nhan khau chua co so dien thoai lien he.',
                'impact' => 'Kho lien he khi ra soat ho so va chinh sach.',
                'suggestion' => 'Bo sung so dien thoai neu co.',
            ],
            [
                'code' => 'household.missing_code',
                'name' => 'Thieu ma ho',
                'group' => 'household',
                'groupLabel' => 'Ho gia dinh',
                'severity' => 'CRITICAL',
                'description' => 'Ho gia dinh chua co ma ho.',
                'impact' => 'Anh huong dinh danh ho, import/export va bao cao.',
                'suggestion' => 'Bo sung ma ho duy nhat.',
            ],
            [
                'code' => 'household.missing_address',
                'name' => 'Ho khong co dia chi',
                'group' => 'household',
                'groupLabel' => 'Ho gia dinh',
                'severity' => 'HIGH',
                'description' => 'Ho gia dinh chua co dia chi.',
                'impact' => 'Anh huong quan ly dia ban, GIS va lien he.',
                'suggestion' => 'Cap nhat dia chi ho gia dinh.',
            ],
            [
                'code' => 'household.no_head',
                'name' => 'Khong co chu ho',
                'group' => 'household',
                'groupLabel' => 'Ho gia dinh',
                'severity' => 'CRITICAL',
                'description' => 'Ho gia dinh co thanh vien nhung khong co chu ho.',
                'impact' => 'Lam sai quan he ho va thong ke ho.',
                'suggestion' => 'Gan dung mot thanh vien lam chu ho.',
            ],
            [
                'code' => 'household.multiple_heads',
                'name' => 'Co nhieu chu ho',
                'group' => 'household',
                'groupLabel' => 'Ho gia dinh',
                'severity' => 'CRITICAL',
                'description' => 'Ho gia dinh co hon mot thanh vien la chu ho.',
                'impact' => 'Lam sai cau truc ho gia dinh.',
                'suggestion' => 'Giu lai mot chu ho va cap nhat quan he cac thanh vien con lai.',
            ],
            [
                'code' => 'household.duplicate_members',
                'name' => 'Thanh vien trung trong ho',
                'group' => 'household',
                'groupLabel' => 'Ho gia dinh',
                'severity' => 'HIGH',
                'description' => 'Trong cung mot ho co thanh vien trung ho ten va ngay sinh.',
                'impact' => 'Co nguy co nhap trung nhan khau.',
                'suggestion' => 'Ra soat va gop/xoa ban ghi trung neu dung nghiep vu.',
            ],
            [
                'code' => 'identity.duplicate_identity',
                'name' => 'Trung CCCD',
                'group' => 'identity',
                'groupLabel' => 'Du lieu dinh danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhieu nhan khau dung chung mot so CCCD/CMND.',
                'impact' => 'Anh huong nghiem trong den dinh danh va bao cao.',
                'suggestion' => 'Mo danh sach trung va chinh sua ban ghi sai.',
            ],
            [
                'code' => 'identity.duplicate_phone',
                'name' => 'Trung so dien thoai',
                'group' => 'identity',
                'groupLabel' => 'Du lieu dinh danh',
                'severity' => 'MEDIUM',
                'description' => 'Nhieu nhan khau dung chung so dien thoai.',
                'impact' => 'Co the dung chung so gia dinh, can ra soat khi lien he.',
                'suggestion' => 'Kiem tra va cap nhat so lien he rieng neu co.',
            ],
            [
                'code' => 'identity.duplicate_citizen_code',
                'name' => 'Trung ma nhan khau',
                'group' => 'identity',
                'groupLabel' => 'Du lieu dinh danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhieu nhan khau dung chung ma nhan khau.',
                'impact' => 'Anh huong import/export va dong bo du lieu.',
                'suggestion' => 'Cap nhat ma nhan khau duy nhat.',
            ],
            [
                'code' => 'identity.duplicate_household_code',
                'name' => 'Trung ma ho',
                'group' => 'identity',
                'groupLabel' => 'Du lieu dinh danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhieu ho dung chung ma ho.',
                'impact' => 'Anh huong quan ly ho, bao cao va import/export.',
                'suggestion' => 'Cap nhat ma ho duy nhat.',
            ],
            [
                'code' => 'data.orphan_citizen',
                'name' => 'Ho so nhan khau mo coi',
                'group' => 'data',
                'groupLabel' => 'Du lieu',
                'severity' => 'CRITICAL',
                'description' => 'Nhan khau khong lien ket duoc voi ho gia dinh hop le.',
                'impact' => 'Nhan khau co the khong xuat hien trong bao cao ho.',
                'suggestion' => 'Gan lai ho gia dinh hoac ra soat du lieu import.',
            ],
            [
                'code' => 'policy.eligible_health_insurance_missing',
                'name' => 'Du dieu kien BHYT nhung chua cap nhat',
                'group' => 'policy',
                'groupLabel' => 'Chinh sach',
                'severity' => 'HIGH',
                'description' => 'RiskWarningEngine phat hien ho so du dieu kien BHYT nhung chua cap nhat.',
                'impact' => 'Anh huong den theo doi chinh sach va ho tro nguoi dan.',
                'suggestion' => 'Ra soat ho so BHYT trong danh sach canh bao.',
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
            'label' => $score >= 90 ? 'Tot' : ($score >= 75 ? 'Can ra soat' : 'Can xu ly gap'),
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

    private function severityTotal(array $issues, string $severity): int
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
