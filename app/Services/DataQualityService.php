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
                'critical' => $this->severityTá»‘tal($issues, 'CRITICAL'),
                'high' => $this->severityTá»‘tal($issues, 'HIGH'),
                'medium' => $this->severityTá»‘tal($issues, 'MEDIUM'),
                'low' => $this->severityTá»‘tal($issues, 'LOW'),
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
            throw new \RuntimeException('KhÃ´ng tÃ¬m tháº¥y mÃ£ lá»—i dá»¯ liá»‡u');
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
                'name' => 'Thiáº¿u CCCD',
                'group' => 'identity',
                'groupLabel' => 'Dá»¯ liá»‡u Ä‘á»‹nh danh',
                'severity' => 'CRITICAL',
                'description' => 'NhÃ¢n kháº©u chÆ°a cÃ³ sá»‘ CCCD/CMND.',
                'impact' => 'áº¢nh hÆ°á»Ÿng Ä‘á»‘i soÃ¡t, bÃ¡o cÃ¡o vÃ  chÃ­nh sÃ¡ch.',
                'suggestion' => 'Má»Ÿ danh sÃ¡ch nhÃ¢n kháº©u vÃ  bá»• sung sá»‘ CCCD/CMND.',
            ],
            [
                'code' => 'citizen.missing_date_of_birth',
                'name' => 'Thiáº¿u ngÃ y sinh',
                'group' => 'citizen',
                'groupLabel' => 'NhÃ¢n kháº©u',
                'severity' => 'CRITICAL',
                'description' => 'NhÃ¢n kháº©u thiáº¿u ngÃ y sinh nÃªn khÃ´ng thá»ƒ tÃ­nh tuá»•i.',
                'impact' => 'LÃ m sai thá»‘ng kÃª tuá»•i, chÃ­nh sÃ¡ch BHYT vÃ  báº£o trá»£.',
                'suggestion' => 'Bá»• sung ngÃ y sinh trong há»“ sÆ¡ nhÃ¢n kháº©u.',
            ],
            [
                'code' => 'citizen.missing_gender',
                'name' => 'Thiáº¿u giá»›i tÃ­nh',
                'group' => 'citizen',
                'groupLabel' => 'NhÃ¢n kháº©u',
                'severity' => 'HIGH',
                'description' => 'NhÃ¢n kháº©u chÆ°a cÃ³ thÃ´ng tin giá»›i tÃ­nh.',
                'impact' => 'LÃ m sai cÆ¡ cáº¥u dÃ¢n sá»‘ vÃ  má»™t sá»‘ suy luáº­n quan há»‡.',
                'suggestion' => 'Cáº­p nháº­t giá»›i tÃ­nh trong há»“ sÆ¡ nhÃ¢n kháº©u.',
            ],
            [
                'code' => 'citizen.missing_relationship',
                'name' => 'Thiáº¿u quan há»‡',
                'group' => 'household_relation',
                'groupLabel' => 'Quan há»‡ há»™',
                'severity' => 'HIGH',
                'description' => 'NhÃ¢n kháº©u chÆ°a cÃ³ quan há»‡ vá»›i chá»§ há»™.',
                'impact' => 'LÃ m giáº£m cháº¥t lÆ°á»£ng há»“ sÆ¡ há»™ gia Ä‘Ã¬nh vÃ  bÃ¡o cÃ¡o quan há»‡.',
                'suggestion' => 'Cáº­p nháº­t quan há»‡ theo HouseholdRelationPolicy.',
            ],
            [
                'code' => 'citizen.invalid_relationship',
                'name' => 'Quan há»‡ khÃ´ng há»£p lá»‡',
                'group' => 'household_relation',
                'groupLabel' => 'Quan há»‡ há»™',
                'severity' => 'CRITICAL',
                'description' => 'Quan há»‡ khÃ´ng náº±m trong danh sÃ¡ch quan há»‡ chuáº©n.',
                'impact' => 'LÃ m sai suy luáº­n há»™ gia Ä‘Ã¬nh vÃ  cÃ¡c cáº£nh bÃ¡o dá»¯ liá»‡u.',
                'suggestion' => 'Chá»n láº¡i quan há»‡ theo danh má»¥c quan há»‡ chuáº©n.',
            ],
            [
                'code' => 'citizen.missing_occupation',
                'name' => 'Thiáº¿u nghá» nghiá»‡p',
                'group' => 'employment',
                'groupLabel' => 'Lao Ä‘á»™ng',
                'severity' => 'HIGH',
                'description' => 'NhÃ¢n kháº©u chÆ°a cÃ³ thÃ´ng tin nghá» nghiá»‡p.',
                'impact' => 'áº¢nh hÆ°á»Ÿng thá»‘ng kÃª lao Ä‘á»™ng vÃ  bÃ¡o cÃ¡o viá»‡c lÃ m.',
                'suggestion' => 'Cáº­p nháº­t nghá» nghiá»‡p hoáº·c tráº¡ng thÃ¡i lao Ä‘á»™ng.',
            ],
            [
                'code' => 'citizen.missing_health_insurance',
                'name' => 'Thiáº¿u BHYT',
                'group' => 'policy',
                'groupLabel' => 'ChÃ­nh sÃ¡ch',
                'severity' => 'HIGH',
                'description' => 'NhÃ¢n kháº©u chÆ°a cÃ³ thÃ´ng tin BHYT theo InsurancePolicy.',
                'impact' => 'áº¢nh hÆ°á»Ÿng thá»‘ng kÃª BHYT vÃ  rÃ  soÃ¡t chÃ­nh sÃ¡ch.',
                'suggestion' => 'Cáº­p nháº­t tÃ¬nh tráº¡ng BHYT hoáº·c lÃ½ do chÆ°a cÃ³ BHYT.',
            ],
            [
                'code' => 'citizen.missing_phone',
                'name' => 'Thiáº¿u sá»‘ Ä‘iá»‡n thoáº¡i',
                'group' => 'citizen',
                'groupLabel' => 'NhÃ¢n kháº©u',
                'severity' => 'MEDIUM',
                'description' => 'NhÃ¢n kháº©u chÆ°a cÃ³ sá»‘ Ä‘iá»‡n thoáº¡i liÃªn há»‡.',
                'impact' => 'KhÃ³ liÃªn há»‡ khi rÃ  soÃ¡t há»“ sÆ¡ vÃ  chÃ­nh sÃ¡ch.',
                'suggestion' => 'Bá»• sung sá»‘ Ä‘iá»‡n thoáº¡i náº¿u cÃ³.',
            ],
            [
                'code' => 'household.missing_code',
                'name' => 'Thiáº¿u mÃ£ há»™',
                'group' => 'household',
                'groupLabel' => 'Há»™ gia Ä‘Ã¬nh',
                'severity' => 'CRITICAL',
                'description' => 'Há»™ gia Ä‘Ã¬nh chÆ°a cÃ³ mÃ£ há»™.',
                'impact' => 'áº¢nh hÆ°á»Ÿng Ä‘á»‹nh danh há»™, import/export vÃ  bÃ¡o cÃ¡o.',
                'suggestion' => 'Bá»• sung mÃ£ há»™ duy nháº¥t.',
            ],
            [
                'code' => 'household.missing_address',
                'name' => 'Há»™ khÃ´ng cÃ³ Ä‘á»‹a chá»‰',
                'group' => 'household',
                'groupLabel' => 'Há»™ gia Ä‘Ã¬nh',
                'severity' => 'HIGH',
                'description' => 'Há»™ gia Ä‘Ã¬nh chÆ°a cÃ³ Ä‘á»‹a chá»‰.',
                'impact' => 'áº¢nh hÆ°á»Ÿng quáº£n lÃ½ Ä‘á»‹a bÃ n, GIS vÃ  liÃªn há»‡.',
                'suggestion' => 'Cáº­p nháº­t Ä‘á»‹a chá»‰ há»™ gia Ä‘Ã¬nh.',
            ],
            [
                'code' => 'household.no_head',
                'name' => 'KhÃ´ng cÃ³ chá»§ há»™',
                'group' => 'household',
                'groupLabel' => 'Há»™ gia Ä‘Ã¬nh',
                'severity' => 'CRITICAL',
                'description' => 'Há»™ gia Ä‘Ã¬nh cÃ³ thÃ nh viÃªn nhÆ°ng khÃ´ng cÃ³ chá»§ há»™.',
                'impact' => 'LÃ m sai quan há»‡ há»™ vÃ  thá»‘ng kÃª há»™.',
                'suggestion' => 'GÃ¡n Ä‘Ãºng má»™t thÃ nh viÃªn lÃ m chá»§ há»™.',
            ],
            [
                'code' => 'household.multiple_heads',
                'name' => 'CÃ³ nhiá»u chá»§ há»™',
                'group' => 'household',
                'groupLabel' => 'Há»™ gia Ä‘Ã¬nh',
                'severity' => 'CRITICAL',
                'description' => 'Há»™ gia Ä‘Ã¬nh cÃ³ hÆ¡n má»™t thÃ nh viÃªn lÃ  chá»§ há»™.',
                'impact' => 'LÃ m sai cáº¥u trÃºc há»™ gia Ä‘Ã¬nh.',
                'suggestion' => 'Giá»¯ láº¡i má»™t chá»§ há»™ vÃ  cáº­p nháº­t quan há»‡ cÃ¡c thÃ nh viÃªn cÃ²n láº¡i.',
            ],
            [
                'code' => 'household.duplicate_members',
                'name' => 'ThÃ nh viÃªn trÃ¹ng trong há»™',
                'group' => 'household',
                'groupLabel' => 'Há»™ gia Ä‘Ã¬nh',
                'severity' => 'HIGH',
                'description' => 'Trong cÃ¹ng má»™t há»™ cÃ³ thÃ nh viÃªn trÃ¹ng há» tÃªn vÃ  ngÃ y sinh.',
                'impact' => 'CÃ³ nguy cÆ¡ nháº­p trÃ¹ng nhÃ¢n kháº©u.',
                'suggestion' => 'RÃ  soÃ¡t vÃ  gá»™p/xÃ³a báº£n ghi trÃ¹ng náº¿u Ä‘Ãºng nghiá»‡p vá»¥.',
            ],
            [
                'code' => 'identity.duplicate_identity',
                'name' => 'Trung CCCD',
                'group' => 'identity',
                'groupLabel' => 'Dá»¯ liá»‡u Ä‘á»‹nh danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhiá»u nhÃ¢n kháº©u dÃ¹ng chung má»™t sá»‘ CCCD/CMND.',
                'impact' => 'áº¢nh hÆ°á»Ÿng nghiÃªm trá»ng Ä‘áº¿n Ä‘á»‹nh danh vÃ  bÃ¡o cÃ¡o.',
                'suggestion' => 'Má»Ÿ danh sÃ¡ch trÃ¹ng vÃ  chá»‰nh sá»­a báº£n ghi sai.',
            ],
            [
                'code' => 'identity.duplicate_phone',
                'name' => 'TrÃ¹ng sá»‘ Ä‘iá»‡n thoáº¡i',
                'group' => 'identity',
                'groupLabel' => 'Dá»¯ liá»‡u Ä‘á»‹nh danh',
                'severity' => 'MEDIUM',
                'description' => 'Nhiá»u nhÃ¢n kháº©u dÃ¹ng chung sá»‘ Ä‘iá»‡n thoáº¡i.',
                'impact' => 'CÃ³ thá»ƒ dÃ¹ng chung sá»‘ gia Ä‘Ã¬nh, cáº§n rÃ  soÃ¡t khi liÃªn há»‡.',
                'suggestion' => 'Kiá»ƒm tra vÃ  cáº­p nháº­t sá»‘ liÃªn há»‡ riÃªng náº¿u cÃ³.',
            ],
            [
                'code' => 'identity.duplicate_citizen_code',
                'name' => 'TrÃ¹ng mÃ£ nhÃ¢n kháº©u',
                'group' => 'identity',
                'groupLabel' => 'Dá»¯ liá»‡u Ä‘á»‹nh danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhiá»u nhÃ¢n kháº©u dÃ¹ng chung mÃ£ nhÃ¢n kháº©u.',
                'impact' => 'áº¢nh hÆ°á»Ÿng import/export vÃ  Ä‘á»“ng bá»™ dá»¯ liá»‡u.',
                'suggestion' => 'Cáº­p nháº­t mÃ£ nhÃ¢n kháº©u duy nháº¥t.',
            ],
            [
                'code' => 'identity.duplicate_household_code',
                'name' => 'TrÃ¹ng mÃ£ há»™',
                'group' => 'identity',
                'groupLabel' => 'Dá»¯ liá»‡u Ä‘á»‹nh danh',
                'severity' => 'CRITICAL',
                'description' => 'Nhiá»u há»™ dÃ¹ng chung mÃ£ há»™.',
                'impact' => 'áº¢nh hÆ°á»Ÿng quáº£n lÃ½ há»™, bÃ¡o cÃ¡o vÃ  import/export.',
                'suggestion' => 'Cáº­p nháº­t mÃ£ há»™ duy nháº¥t.',
            ],
            [
                'code' => 'data.orphan_citizen',
                'name' => 'Há»“ sÆ¡ nhÃ¢n kháº©u má»“ cÃ´i',
                'group' => 'data',
                'groupLabel' => 'Dá»¯ liá»‡u',
                'severity' => 'CRITICAL',
                'description' => 'NhÃ¢n kháº©u khÃ´ng liÃªn káº¿t Ä‘Æ°á»£c vá»›i há»™ gia Ä‘Ã¬nh há»£p lá»‡.',
                'impact' => 'NhÃ¢n kháº©u cÃ³ thá»ƒ khÃ´ng xuáº¥t hiá»‡n trong bÃ¡o cÃ¡o há»™.',
                'suggestion' => 'GÃ¡n láº¡i há»™ gia Ä‘Ã¬nh hoáº·c rÃ  soÃ¡t dá»¯ liá»‡u import.',
            ],
            [
                'code' => 'policy.eligible_health_insurance_missing',
                'name' => 'Äá»§ Ä‘iá»u kiá»‡n BHYT nhÆ°ng chÆ°a cáº­p nháº­t',
                'group' => 'policy',
                'groupLabel' => 'ChÃ­nh sÃ¡ch',
                'severity' => 'HIGH',
                'description' => 'RiskWarningEngine phÃ¡t hiá»‡n há»“ sÆ¡ Ä‘á»§ Ä‘iá»u kiá»‡n BHYT nhÆ°ng chÆ°a cáº­p nháº­t.',
                'impact' => 'áº¢nh hÆ°á»Ÿng Ä‘áº¿n theo dÃµi chÃ­nh sÃ¡ch vÃ  há»— trá»£ ngÆ°á»i dÃ¢n.',
                'suggestion' => 'RÃ  soÃ¡t há»“ sÆ¡ BHYT trong danh sÃ¡ch cáº£nh bÃ¡o.',
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
            'label' => $score >= 90 ? 'Tá»‘t' : ($score >= 75 ? 'Cáº§n rÃ  soÃ¡t' : 'Cáº§n xá»­ lÃ½ gáº¥p'),
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

    private function severityTá»‘tal(array $issues, string $severity): int
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
