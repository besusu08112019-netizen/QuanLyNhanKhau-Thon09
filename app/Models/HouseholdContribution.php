<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Core\TenantConfig;
use App\Services\ContributionRuleEngine;

final class HouseholdContribution extends BaseModel
{
    public const CATEGORY_STATUS = ['ACTIVE' => 'Dang ap dung', 'INACTIVE' => 'Tam dung', 'DELETED' => 'Da xoa'];
    public const COLLECTION_CYCLES = ['MONTHLY' => 'Thang', 'QUARTERLY' => 'Quy', 'YEARLY' => 'Nam', 'CUSTOM' => 'Tuy chinh'];
    public const CATEGORIES = ['Quỹ vệ sinh', 'Quỹ rác thải', 'Quỹ an ninh', 'Quỹ khuyến học', 'Đóng góp làm đường', 'Điện chiếu sáng', 'Nghĩa trang', 'Nhà văn hóa', 'Đóng góp khác'];
    public const CAMPAIGN_STATUS = ['ACTIVE' => 'Đang thu', 'CLOSED' => 'Đã kết thúc', 'INACTIVE' => 'Tạm dừng', 'DELETED' => 'Đã xóa'];
    public const PAYMENT_STATUS = ['UNPAID' => 'Chưa thu', 'PAID' => 'Đã thu', 'PARTIAL' => 'Thu một phần', 'EXEMPT' => 'Được miễn', 'REDUCED' => 'Miễn một phần'];

    private const REQUIRED_SCHEMA = [
        'contribution_categories' => ['id','village_id','code','name','contribution_type','unit_type','amount','unit','collection_cycle','custom_cycle','target_config_json','exemption_config_json','is_required','status','note','created_at','updated_at','created_by','updated_by','deleted_at','deleted_by'],
        'contribution_campaigns' => ['id','village_id','category_id','contribution_name','contribution_type','year','period_name','amount','unit','unit_type','start_date','due_date','target_config_json','exemption_config_json','note','status','created_at','updated_at','created_by','updated_by','deleted_at','deleted_by'],
        'contribution_rate_rules' => ['id','village_id','campaign_id','rule_name','unit_type','amount','target_config_json','effective_from','effective_to','status','created_at','updated_at'],
        'contribution_rule_templates' => ['id','village_id','template_code','category_name','contribution_name','unit_type','amount','unit','target_config_json','exemption_config_json','status','created_at','updated_at'],
        'contribution_exemption_policies' => ['id','village_id','campaign_id','policy_code','policy_name','policy_type','exemption_config_json','amount','percent','status','approved_by','approved_at','note','created_at','updated_at'],
        'household_contributions' => ['id','village_id','campaign_id','household_id','payment_status','expected_amount','gross_amount','exempt_amount','discount_amount','paid_amount','debt_amount','amount','eligible_count','exempt_count','chargeable_count','paid_at','collector_name','payment_method','receipt_number','calculation_note','note','status','created_at','updated_at','created_by','updated_by','deleted_at','deleted_by'],
        'contribution_receipts' => ['id','village_id','contribution_id','campaign_id','household_id','receipt_number','amount','paid_at','collector_name','payment_method','note','created_at','created_by'],
        'contribution_payment_history' => ['id','village_id','contribution_id','campaign_id','household_id','action','amount','payment_status','paid_at','collector_name','receipt_number','note','created_at','created_by'],
        'contribution_adjustment_history' => ['id','village_id','contribution_id','campaign_id','household_id','before_json','after_json','reason','created_at','created_by'],
    ];
    private const REQUIRED_INDEXES = [
        'contribution_categories' => ['idx_contribution_categories_village'],
        'contribution_campaigns' => ['idx_contribution_campaigns_village','idx_contribution_campaign_year','idx_contribution_campaign_status','idx_contribution_campaign_category'],
        'contribution_rate_rules' => ['idx_contribution_rate_rules_village','idx_contribution_rate_campaign'],
        'contribution_rule_templates' => ['idx_contribution_rule_templates_village'],
        'contribution_exemption_policies' => ['idx_contribution_exemption_policies_village','idx_contribution_policy_campaign'],
        'household_contributions' => ['idx_household_contributions_village','idx_household_contributions_household','idx_household_contributions_status','idx_household_contributions_campaign','uniq_household_contribution'],
        'contribution_receipts' => ['idx_contribution_receipts_village','idx_contribution_receipts_contribution','idx_contribution_receipts_campaign','idx_contribution_receipts_household'],
        'contribution_payment_history' => ['idx_contribution_payment_history_village','idx_contribution_history_contribution','idx_contribution_history_campaign','idx_contribution_history_household'],
        'contribution_adjustment_history' => ['idx_contribution_adjustment_history_village','idx_contribution_adjustment_campaign','idx_contribution_adjustment_contribution'],
    ];
    private const REQUIRED_CATEGORY_CODES = ['CAT01','CAT02','CAT03','CAT04','CAT05','CAT06','CAT07','CAT08','CAT09'];
    private const REQUIRED_TEMPLATE_CODE = 'WASTE_COLLECTION';
    private const ACTIVE_HOUSEHOLD = 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")';
    private const ACTIVE_CITIZEN = 'c.status <> "DELETED" AND COALESCE(c.life_status,"ALIVE") <> "DECEASED" AND COALESCE(c.residency_status,"PERMANENT") <> "TRANSFERRED_OUT"';

    private ContributionRuleEngine $rules;

    public function __construct()
    {
        parent::__construct();
        $this->rules = new ContributionRuleEngine();
    }

    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
    }

    public function assertSchemaReady(): void
    {
        foreach (self::REQUIRED_SCHEMA as $table => $columns) {
            $this->assertTableReady($table);
            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    throw new \RuntimeException('Household Contributions schema/catalog is not provisioned: missing column ' . $table . '.' . $column);
                }
            }
            $this->assertTenantScopeReady($table);
            foreach (self::REQUIRED_INDEXES[$table] ?? [] as $index) {
                $this->assertIndexReady($table, $index);
            }
        }

        $this->assertPaymentStatusContract();
        $this->assertCanonicalCategoriesReady();
        $this->assertRuleTemplateReady();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'categories' => $this->categoryOptions(),
            'category_statuses' => $this->options(self::CATEGORY_STATUS),
            'collection_cycles' => $this->options(self::COLLECTION_CYCLES),
            'campaign_statuses' => $this->options(self::CAMPAIGN_STATUS),
            'payment_statuses' => $this->options(self::PAYMENT_STATUS),
            'unit_types' => $this->options(ContributionRuleEngine::UNIT_TYPES),
            'target_options' => $this->options(ContributionRuleEngine::TARGET_OPTIONS),
            'exemption_options' => $this->options(ContributionRuleEngine::EXEMPTION_OPTIONS),
            'payment_methods' => $this->options(['CASH' => 'Tiền mặt', 'TRANSFER' => 'Chuyển khoản', 'OTHER' => 'Khác']),
        ];
    }

    public function categories(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->categoryWhere($filters);
        $rows = $this->fetchAll(
            "SELECT cc.*,
                COALESCE(COUNT(c.id),0) AS campaign_count,
                COALESCE(SUM(CASE WHEN c.status='ACTIVE' THEN 1 ELSE 0 END),0) AS active_campaign_count
             FROM contribution_categories cc
             LEFT JOIN contribution_campaigns c ON c.category_id=cc.id AND c.status <> 'DELETED' AND " . $this->tenantWhere('c', 'contribution_campaigns') . "
             $where
             GROUP BY cc.id
             ORDER BY cc.status ASC, cc.name ASC",
            $params
        );
        return ['items' => array_map(fn($row) => $this->normalizeCategory($row), $rows), 'total' => count($rows)];
    }

    public function findCategory(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne(
            "SELECT cc.*,
                COALESCE(COUNT(c.id),0) AS campaign_count,
                COALESCE(SUM(CASE WHEN c.status='ACTIVE' THEN 1 ELSE 0 END),0) AS active_campaign_count
             FROM contribution_categories cc
             LEFT JOIN contribution_campaigns c ON c.category_id=cc.id AND c.status <> 'DELETED' AND " . $this->tenantWhere('c', 'contribution_campaigns') . "
             WHERE cc.id=:id AND cc.status <> 'DELETED' AND " . $this->tenantWhere('cc', 'contribution_categories') . "
             GROUP BY cc.id",
            $this->withTenant(['id' => $id])
        );
        return $row ? $this->normalizeCategory($row) : null;
    }

    public function upsertCategory(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->categoryParams($data, $userId, $id);
        if ($id) {
            if (!$this->findCategory($id)) throw new \RuntimeException('Không tìm thấy khoản thu');
            $params['id'] = $id;
            $this->execute('UPDATE contribution_categories SET code=:code, name=:name, contribution_type=:contribution_type, unit_type=:unit_type, amount=:amount, unit=:unit, collection_cycle=:collection_cycle, custom_cycle=:custom_cycle, target_config_json=:target_config_json, exemption_config_json=:exemption_config_json, status=:status, note=:note, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('contribution_categories'), $this->withTenant($params));
            $this->refreshCampaignsFromCategory($id);
            return $this->findCategory($id);
        }
        $insertParams = $params + ['created_by' => $userId, 'updated_by' => $userId];
        unset($insertParams['user']);
        $columns = ['code', 'name', 'contribution_type', 'unit_type', 'amount', 'unit', 'collection_cycle', 'custom_cycle', 'target_config_json', 'exemption_config_json', 'status', 'note', 'created_by', 'updated_by'];
        $this->addTenantInsert('contribution_categories', $columns, $insertParams);
        $newId = $this->insert('INSERT INTO contribution_categories (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $insertParams);
        return $this->findCategory($newId);
    }

    public function deleteCategory(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->findCategory($id)) throw new \RuntimeException('Không tìm thấy khoản thu');
        $active = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM contribution_campaigns WHERE category_id=:id AND status <> "DELETED" AND ' . $this->tenantWhere('contribution_campaigns'), $this->withTenant(['id' => $id])) ?: [])['total'] ?? 0);
        if ($active > 0) throw new \RuntimeException('Khoản thu đang có đợt thu, không thể xóa');
        $this->execute('UPDATE contribution_categories SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('contribution_categories'), $this->withTenant(['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]));
    }

    public function campaigns(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->campaignWhere($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM contribution_campaigns c LEFT JOIN contribution_categories cc ON cc.id=c.category_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT c.*, cc.code AS category_code, cc.name AS category_name, cc.collection_cycle AS category_cycle,
                COALESCE(SUM(CASE WHEN hc.payment_status IN ('PAID','REDUCED') AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS paid_households,
                COALESCE(SUM(CASE WHEN hc.payment_status='PARTIAL' AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS partial_households,
                COALESCE(SUM(CASE WHEN hc.payment_status='UNPAID' AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS unpaid_households,
                COALESCE(SUM(CASE WHEN hc.payment_status='EXEMPT' AND hc.status='ACTIVE' THEN 1 ELSE 0 END),0) AS exempt_households,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.gross_amount ELSE 0 END),0) AS gross_total,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.exempt_amount + hc.discount_amount ELSE 0 END),0) AS exempt_total,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.expected_amount ELSE 0 END),0) AS expected_total,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.paid_amount ELSE 0 END),0) AS collected_amount,
                COALESCE(SUM(CASE WHEN hc.status='ACTIVE' THEN hc.debt_amount ELSE 0 END),0) AS debt_amount
             FROM contribution_campaigns c
             LEFT JOIN contribution_categories cc ON cc.id=c.category_id
             LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE' AND " . $this->tenantWhere('hc', 'household_contributions') . "
             $where
             GROUP BY c.id
             $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return $this->paginated(array_map(fn($row) => $this->normalizeCampaign($row), $rows), $page, $pageSize, $total);
    }

    public function findCampaign(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT c.*, cc.code AS category_code, cc.name AS category_name, cc.collection_cycle AS category_cycle FROM contribution_campaigns c LEFT JOIN contribution_categories cc ON cc.id=c.category_id WHERE c.id=:id AND c.status <> "DELETED" AND ' . $this->tenantWhere('c', 'contribution_campaigns') . ' AND (cc.id IS NULL OR ' . $this->tenantWhere('cc', 'contribution_categories') . ')', $this->withTenant(['id' => $id]));
        return $row ? $this->normalizeCampaign($row) : null;
    }

    public function upsertCampaign(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->campaignParamsV2($data, $userId);
        $before = $id ? $this->findCampaign($id) : null;
        if (!$id && empty($params['category_id'])) throw new \RuntimeException('Vui long tao/chon khoan thu truoc khi tao dot thu');
        if ($id && !$before) throw new \RuntimeException('Không tìm thấy đợt thu');
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE contribution_campaigns SET category_id=:category_id, contribution_name=:contribution_name, contribution_type=:contribution_type, year=:year, period_name=:period_name, amount=:amount, unit=:unit, unit_type=:unit_type, start_date=:start_date, due_date=:due_date, target_config_json=:target_config_json, exemption_config_json=:exemption_config_json, note=:note, status=:status, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('contribution_campaigns'), $this->withTenant($params));
            $this->writeAdjustment($id, null, $before, $params, $userId, 'Cập nhật quy định đợt thu');
            $this->syncCampaign($id);
            return $this->findCampaign($id);
        }
        $insertParams = $params + ['created_by' => $userId, 'updated_by' => $userId];
        unset($insertParams['user']);
        $columns = ['category_id', 'contribution_name', 'contribution_type', 'year', 'period_name', 'amount', 'unit', 'unit_type', 'start_date', 'due_date', 'target_config_json', 'exemption_config_json', 'note', 'status', 'created_by', 'updated_by'];
        $this->addTenantInsert('contribution_campaigns', $columns, $insertParams);
        $newId = $this->insert('INSERT INTO contribution_campaigns (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $insertParams);
        $this->upsertRateRule($newId, $params);
        $this->syncCampaign($newId);
        return $this->findCampaign($newId);
    }

    public function deleteCampaign(int $id, int $userId): void
    {
        $this->ensureSchema();
        $before = $this->findCampaign($id);
        if (!$before) throw new \RuntimeException('Không tìm thấy đợt thu');
        $this->execute('UPDATE contribution_campaigns SET status="DELETED", deleted_at=NOW(), deleted_by=:deleted_by, updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('contribution_campaigns'), $this->withTenant(['id' => $id, 'deleted_by' => $userId, 'updated_by' => $userId]));
        $this->writeAdjustment($id, null, $before, ['status' => 'DELETED'], $userId, 'Xóa đợt thu');
    }

    public function tracking(int $campaignId, array $filters): array
    {
        $this->ensureSchema();
        if (!$this->findCampaign($campaignId)) throw new \RuntimeException('Không tìm thấy đợt thu');
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->trackingWhere($campaignId, $filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM household_contributions hc INNER JOIN households h ON h.id=hc.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll(
            "SELECT h.id AS household_id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code,
                c.contribution_name, c.amount AS unit_amount, c.unit, c.due_date,
                hc.*
             FROM household_contributions hc
             INNER JOIN households h ON h.id=hc.household_id
             INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id
             $where $order LIMIT $pageSize OFFSET $offset",
            $params
        );
        return $this->paginated(array_map(fn($row) => $this->normalizeTracking($row), $rows), $page, $pageSize, $total);
    }

    public function upsertTracking(int $campaignId, int $householdId, array $data, int $userId): array
    {
        $this->ensureSchema();
        $campaign = $this->findCampaign($campaignId);
        if (!$campaign) throw new \RuntimeException('Không tìm thấy đợt thu');
        if (!$this->fetchOne('SELECT id FROM households h WHERE h.id=:id AND ' . $this->tenantWhere('h', 'households') . ' AND ' . self::ACTIVE_HOUSEHOLD, $this->withTenant(['id' => $householdId]))) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $this->syncCampaign($campaignId);
        $before = $this->tracking($campaignId, ['household_id' => $householdId, 'pageSize' => 1])['items'][0] ?? null;
        $status = strtoupper(trim((string) ($data['payment_status'] ?? $data['paymentStatus'] ?? 'UNPAID')));
        if (!isset(self::PAYMENT_STATUS[$status])) $status = 'UNPAID';
        $paid = max(0, (float) ($data['paid_amount'] ?? $data['amount'] ?? 0));
        $discount = max(0, (float) ($data['discount_amount'] ?? 0));
        $expected = max(0, (float) ($before['expected_amount'] ?? 0));
        $debt = $status === 'EXEMPT' ? 0 : max(0, $expected - $paid - $discount);
        if ($status !== 'EXEMPT') {
            if ($debt <= 0 && $expected > 0) $status = $discount > 0 ? 'REDUCED' : 'PAID';
            if ($paid > 0 && $debt > 0) $status = 'PARTIAL';
            if ($paid <= 0 && $debt > 0) $status = 'UNPAID';
        }
        $params = [
            'campaign_id' => $campaignId,
            'household_id' => $householdId,
            'payment_status' => $status,
            'paid_amount' => $paid,
            'amount' => $paid,
            'discount_amount' => $discount,
            'debt_amount' => $debt,
            'paid_at' => trim((string) ($data['paid_at'] ?? $data['paidAt'] ?? '')) ?: null,
            'collector_name' => trim((string) ($data['collector_name'] ?? $data['collectorName'] ?? '')) ?: null,
            'payment_method' => strtoupper(trim((string) ($data['payment_method'] ?? $data['paymentMethod'] ?? 'CASH'))) ?: 'CASH',
            'receipt_number' => trim((string) ($data['receipt_number'] ?? $data['receiptNumber'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'user' => $userId,
        ];
        if (!in_array($params['payment_method'], ['CASH', 'TRANSFER', 'OTHER'], true)) $params['payment_method'] = 'CASH';
        $this->execute(
            'UPDATE household_contributions SET payment_status=:payment_status, paid_amount=:paid_amount, amount=:amount, discount_amount=:discount_amount, debt_amount=:debt_amount, paid_at=:paid_at, collector_name=:collector_name, payment_method=:payment_method, receipt_number=:receipt_number, note=:note, status="ACTIVE", updated_by=:user, deleted_at=NULL, deleted_by=NULL WHERE campaign_id=:campaign_id AND household_id=:household_id AND ' . $this->tenantWhere('household_contributions'),
            $this->withTenant($params)
        );
        $row = $this->tracking($campaignId, ['household_id' => $householdId, 'pageSize' => 1])['items'][0] ?? [];
        $this->writePaymentHistory((int) ($row['id'] ?? 0), $params, $userId);
        if ($paid > 0) $this->writeReceipt((int) ($row['id'] ?? 0), $params, $userId);
        $this->writeAdjustment($campaignId, $householdId, $before, $row, $userId, 'Cập nhật thu tiền');
        return $row;
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        return $this->summary($filters);
    }

    public function charts(array $filters = []): array
    {
        $summary = $this->summary($filters);
        return [
            'by_status' => [
                ['label' => 'Đã nộp', 'value' => $summary['paid_households']],
                ['label' => 'Nộp một phần', 'value' => $summary['partial_households']],
                ['label' => 'Chưa nộp', 'value' => $summary['unpaid_households']],
                ['label' => 'Được miễn', 'value' => $summary['exempt_households']],
            ],
            'by_population_status' => [
                ['label' => 'Đã hoàn thành', 'value' => $summary['completed_population']],
                ['label' => 'Chưa hoàn thành', 'value' => $summary['incomplete_population']],
                ['label' => 'Được miễn', 'value' => $summary['exempt_population']],
            ],
            'financial' => [
                ['label' => 'Tổng mức thu', 'value' => $summary['gross_total']],
                ['label' => 'Được miễn', 'value' => $summary['exempt_total']],
                ['label' => 'Đã thu', 'value' => $summary['collected_amount']],
                ['label' => 'Còn phải thu', 'value' => $summary['debt_amount']],
            ],
            'by_year' => $this->fetchAll("SELECT c.year AS label, COALESCE(SUM(hc.paid_amount),0) AS value FROM contribution_campaigns c LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE' AND " . $this->tenantWhere('hc', 'household_contributions') . " WHERE c.status <> 'DELETED' AND " . $this->tenantWhere('c', 'contribution_campaigns') . " GROUP BY c.year ORDER BY c.year DESC LIMIT 10", $this->withTenant()),
            'by_campaign' => $this->fetchAll("SELECT c.contribution_name AS label, COALESCE(SUM(hc.paid_amount),0) AS value FROM contribution_campaigns c LEFT JOIN household_contributions hc ON hc.campaign_id=c.id AND hc.status='ACTIVE' AND " . $this->tenantWhere('hc', 'household_contributions') . " WHERE c.status <> 'DELETED' AND " . $this->tenantWhere('c', 'contribution_campaigns') . " GROUP BY c.id, c.contribution_name ORDER BY value DESC LIMIT 10", $this->withTenant()),
        ];
    }

    public function searchHouseholds(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll('SELECT id, household_code, head_citizen_name, address, phone FROM households h WHERE ' . $this->tenantWhere('h', 'households') . ' AND ' . self::ACTIVE_HOUSEHOLD . ' AND (LOWER(h.household_code) LIKE :code OR LOWER(h.head_citizen_name) LIKE :head OR LOWER(h.address) LIKE :address) ORDER BY h.household_code LIMIT ' . max(1, min(20, $limit)), $this->withTenant(['code' => $keyword, 'head' => $keyword, 'address' => $keyword]));
        return array_map(fn($r) => ['id' => (int) $r['id'], 'household_code' => (string) $r['household_code'], 'head_citizen_name' => (string) $r['head_citizen_name'], 'address' => (string) ($r['address'] ?? ''), 'phone' => (string) ($r['phone'] ?? '')], $rows);
    }

    public function report(string $mode, array $filters = []): array
    {
        $mode = strtolower($mode);
        if (in_array($mode, ['summary', 'household', 'households', 'list', 'collection', 'collection-list', 'finance', 'financial', 'population', 'exempt', 'exemptions', 'detail', 'campaign-detail', 'by-contribution', 'by_contribution', 'partial', 'unpaid-list', 'signature', 'signatures', 'year-summary'], true)) {
            return match ($mode) {
                'household', 'households', 'list' => $this->householdContributionReport($filters),
                'collection', 'collection-list', 'signature', 'signatures' => $this->collectionReport($filters),
                'finance', 'financial' => $this->financialReport($filters),
                'population' => $this->populationReport($filters),
                'exempt', 'exemptions' => $this->exemptionReport($filters),
                'detail', 'campaign-detail' => $this->campaignDetailReport($filters),
                'by-contribution', 'by_contribution' => $this->byContributionReport($filters),
                'partial' => $this->partialReport($filters),
                'unpaid-list' => $this->unpaidReport($filters),
                'year-summary' => $this->yearSummaryReport($filters),
                default => $this->summaryReport($filters),
            };
        }
        if ($mode === 'paid') $filters['payment_status'] = 'PAID';
        if ($mode === 'unpaid' || $mode === 'debt') $filters['payment_status'] = 'UNPAID';
        $campaignId = (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0);
        if ($campaignId > 0) {
            $rows = $this->tracking($campaignId, $filters + ['pageSize' => 100]);
            return $this->table('Báo cáo đóng góp hộ', ['Mã hộ','Chủ hộ','Địa chỉ','Trạng thái','Phải thu','Đã thu','Còn nợ','Số khẩu thu','Số khẩu miễn','Ngày thu','Người thu','Biên lai','Ghi chú'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['payment_status_label'], $r['expected_amount'], $r['paid_amount'], $r['debt_amount'], $r['chargeable_count'], $r['exempt_count'], $r['paid_at'], $r['collector_name'], $r['receipt_number'], $r['note']], $rows['items']), $filters);
        }
        $rows = $this->campaigns($filters + ['pageSize' => 100])['items'];
        return $this->table('Danh sách đợt thu', ['Khoản thu','Năm','Đợt','Mức thu','Đơn vị','Hạn đóng','Đã nộp','Nộp một phần','Chưa nộp','Được miễn','Tổng phải thu','Đã thu','Còn nợ','Trạng thái'], array_map(fn($r) => [$r['contribution_name'], $r['year'], $r['period_name'], $r['amount'], $r['unit'], $r['due_date'], $r['paid_households'], $r['partial_households'], $r['unpaid_households'], $r['exempt_households'], $r['expected_total'], $r['collected_amount'], $r['debt_amount'], $r['status_label']], $rows), $filters);
    }

    private function householdContributionReport(array $filters): array
    {
        $campaignId = (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0);
        if ($campaignId <= 0) $campaignId = $this->latestCampaignId($filters);
        if ($campaignId <= 0) return $this->reportTable('Danh sách hộ đóng góp', ['STT','Mã hộ','Chủ hộ','Địa chỉ/Tổ dân cư','Số khẩu','Khẩu phải đóng góp','Khẩu được miễn','Mức thu','Số tiền được miễn','Số tiền phải thu','Đã thu','Còn phải thu','Trạng thái','Ghi chú'], [], $filters);
        $rows = $this->contributionRows($campaignId, $filters);
        return $this->reportTable('Danh sách hộ đóng góp', ['STT','Mã hộ','Chủ hộ','Địa chỉ/Tổ dân cư','Số khẩu','Khẩu phải đóng góp','Khẩu được miễn','Mức thu','Số tiền được miễn','Số tiền phải thu','Đã thu','Còn phải thu','Trạng thái','Ghi chú'], array_map(function ($r, $i) {
            return [$i + 1, $r['household_code'], $r['head_citizen_name'], $r['address'] ?: $r['area_code'], $r['eligible_count'], $r['chargeable_count'], $r['exempt_count'], $r['gross_amount'], $r['exempt_amount'] + $r['discount_amount'], $r['expected_amount'], $r['paid_amount'], $r['debt_amount'], $r['payment_status_label'], $r['note']];
        }, $rows, array_keys($rows)), $filters + ['campaign_id' => $campaignId], $campaignId);
    }

    private function campaignDetailReport(array $filters): array
    {
        return $this->householdContributionReport($filters);
    }

    private function collectionReport(array $filters): array
    {
        $campaignId = (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0);
        if ($campaignId <= 0) $campaignId = $this->latestCampaignId($filters);
        $rows = $campaignId > 0 ? $this->contributionRows($campaignId, $filters) : [];
        return $this->reportTable('Danh sách thu tiền đóng góp', ['STT','Chủ hộ','Số tiền phải thu','Đã thu','Còn phải thu','Người thu','Ngày thu','Hình thức thanh toán','Ký nhận'], array_map(function ($r, $i) {
            return [$i + 1, $r['head_citizen_name'], $r['expected_amount'], $r['paid_amount'], $r['debt_amount'], $r['collector_name'], $r['paid_at'], $r['payment_method_label'], ''];
        }, $rows, array_keys($rows)), $filters + ['campaign_id' => $campaignId], $campaignId);
    }

    private function unpaidReport(array $filters): array
    {
        $filters['payment_status'] = 'UNPAID';
        $campaignId = (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0);
        if ($campaignId <= 0) $campaignId = $this->latestCampaignId($filters);
        $rows = $campaignId > 0 ? $this->contributionRows($campaignId, $filters) : [];
        return $this->reportTable('Danh sách hộ chưa nộp đóng góp', ['STT','Chủ hộ','Địa chỉ','Số khẩu','Số tiền còn phải thu','Ghi chú'], array_map(function ($r, $i) {
            return [$i + 1, $r['head_citizen_name'], $r['address'], $r['eligible_count'], $r['debt_amount'], $r['note']];
        }, $rows, array_keys($rows)), $filters + ['campaign_id' => $campaignId], $campaignId);
    }

    private function partialReport(array $filters): array
    {
        $filters['payment_status'] = 'PARTIAL';
        $campaignId = (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0);
        if ($campaignId <= 0) $campaignId = $this->latestCampaignId($filters);
        $rows = $campaignId > 0 ? $this->contributionRows($campaignId, $filters) : [];
        return $this->reportTable('Danh sách hộ nộp một phần', ['STT','Chủ hộ','Phải thu','Đã thu','Còn thiếu','Tỷ lệ hoàn thành'], array_map(function ($r, $i) {
            $rate = (float) $r['expected_amount'] > 0 ? round((float) $r['paid_amount'] * 100 / (float) $r['expected_amount'], 2) . '%' : '0%';
            return [$i + 1, $r['head_citizen_name'], $r['expected_amount'], $r['paid_amount'], $r['debt_amount'], $rate];
        }, $rows, array_keys($rows)), $filters + ['campaign_id' => $campaignId], $campaignId);
    }

    private function byContributionReport(array $filters): array
    {
        $rows = $this->campaigns($filters + ['pageSize' => 100])['items'];
        return $this->reportTable('Báo cáo theo từng khoản đóng góp', ['Khoản đóng góp','Năm','Đợt','Tổng hộ','Hộ phải đóng','Hộ miễn','Đã nộp','Nộp một phần','Chưa nộp','Tổng mức thu','Tổng miễn','Phải thu thực tế','Đã thu','Còn phải thu'], array_map(function ($r) {
            $totalHouseholds = (int) $r['paid_households'] + (int) $r['partial_households'] + (int) $r['unpaid_households'] + (int) $r['exempt_households'];
            $dueHouseholds = (int) $r['paid_households'] + (int) $r['partial_households'] + (int) $r['unpaid_households'];
            return [$r['contribution_name'], $r['year'], $r['period_name'], $totalHouseholds, $dueHouseholds, $r['exempt_households'], $r['paid_households'], $r['partial_households'], $r['unpaid_households'], $r['gross_total'], $r['exempt_total'], $r['expected_total'], $r['collected_amount'], $r['debt_amount']];
        }, $rows), $filters);
    }

    private function yearSummaryReport(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        return $this->summaryReport($filters + ['year' => $year]);
    }

    private function summary(array $filters): array
    {
        [$where, $params] = $this->summaryWhere($filters);
        $row = $this->fetchOne(
            "SELECT
                COUNT(DISTINCT hc.household_id) AS total_households,
                COALESCE(SUM(CASE WHEN hc.payment_status='EXEMPT' THEN 1 ELSE 0 END),0) AS exempt_households,
                COALESCE(SUM(CASE WHEN hc.payment_status IN ('PAID','REDUCED') THEN 1 ELSE 0 END),0) AS paid_households,
                COALESCE(SUM(CASE WHEN hc.payment_status='PARTIAL' THEN 1 ELSE 0 END),0) AS partial_households,
                COALESCE(SUM(CASE WHEN hc.payment_status='UNPAID' THEN 1 ELSE 0 END),0) AS unpaid_households,
                COALESCE(SUM(CASE WHEN c.due_date IS NOT NULL AND c.due_date < CURDATE() AND hc.payment_status IN ('UNPAID','PARTIAL') THEN 1 ELSE 0 END),0) AS overdue_households,
                COALESCE(SUM(hc.eligible_count),0) AS total_population,
                COALESCE(SUM(hc.exempt_count),0) AS exempt_population,
                COALESCE(SUM(hc.chargeable_count),0) AS chargeable_population,
                COALESCE(SUM(CASE WHEN GREATEST(hc.gross_amount - hc.exempt_amount - hc.discount_amount, 0) > 0 AND hc.paid_amount >= GREATEST(hc.gross_amount - hc.exempt_amount - hc.discount_amount, 0) THEN hc.chargeable_count ELSE 0 END),0) AS completed_population,
                COALESCE(SUM(hc.gross_amount),0) AS gross_total,
                COALESCE(SUM(hc.exempt_amount + hc.discount_amount),0) AS exempt_total,
                COALESCE(SUM(GREATEST(hc.gross_amount - hc.exempt_amount - hc.discount_amount, 0)),0) AS expected_total,
                COALESCE(SUM(hc.paid_amount),0) AS collected_amount,
                COALESCE(SUM(GREATEST(hc.gross_amount - hc.exempt_amount - hc.discount_amount - hc.paid_amount, 0)),0) AS debt_amount
             FROM household_contributions hc
             INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id
             LEFT JOIN contribution_categories cc ON cc.id=c.category_id
             INNER JOIN households h ON h.id=hc.household_id
             $where",
            $params
        ) ?: [];
        $totalHouseholds = $this->activeHouseholdCount();
        $summary = [
            'total_households' => $totalHouseholds,
            'due_households' => max(0, (int) ($row['total_households'] ?? 0) - (int) ($row['exempt_households'] ?? 0)),
            'exempt_households' => (int) ($row['exempt_households'] ?? 0),
            'paid_households' => (int) ($row['paid_households'] ?? 0),
            'partial_households' => (int) ($row['partial_households'] ?? 0),
            'unpaid_households' => max(0, $totalHouseholds - (int) ($row['paid_households'] ?? 0) - (int) ($row['partial_households'] ?? 0) - (int) ($row['exempt_households'] ?? 0)),
            'overdue_households' => (int) ($row['overdue_households'] ?? 0),
            'total_population' => (int) ($row['total_population'] ?? 0),
            'eligible_population' => (int) ($row['chargeable_population'] ?? 0),
            'exempt_population' => (int) ($row['exempt_population'] ?? 0),
            'completed_population' => (int) ($row['completed_population'] ?? 0),
            'incomplete_population' => max(0, (int) ($row['chargeable_population'] ?? 0) - (int) ($row['completed_population'] ?? 0)),
            'gross_total' => (float) ($row['gross_total'] ?? 0),
            'exempt_total' => (float) ($row['exempt_total'] ?? 0),
            'expected_total' => (float) ($row['expected_total'] ?? 0),
            'collected_amount' => (float) ($row['collected_amount'] ?? 0),
            'debt_amount' => (float) ($row['debt_amount'] ?? 0),
        ];
        $summary['completion_rate'] = $summary['expected_total'] > 0 ? round($summary['collected_amount'] * 100 / $summary['expected_total'], 2) : 0.0;
        return $summary;
    }

    private function summaryReport(array $filters): array
    {
        $s = $this->summary($filters);
        return $this->reportTable('Báo cáo tổng hợp đóng góp hộ', ['Chỉ tiêu','Giá trị'], [
            ['Tổng số hộ', $s['total_households']],
            ['Hộ phải đóng', $s['due_households']],
            ['Hộ được miễn', $s['exempt_households']],
            ['Hộ đã nộp đủ', $s['paid_households']],
            ['Hộ nộp một phần', $s['partial_households']],
            ['Hộ chưa nộp', $s['unpaid_households']],
            ['Hộ quá hạn', $s['overdue_households']],
        ], $filters, (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0) ?: null);
    }

    private function populationReport(array $filters): array
    {
        $s = $this->summary($filters);
        return $this->reportTable('Báo cáo theo nhân khẩu', ['Chỉ tiêu','Giá trị'], [
            ['Tổng số nhân khẩu', $s['total_population']],
            ['Khẩu phải thu', $s['eligible_population']],
            ['Khẩu được miễn', $s['exempt_population']],
            ['Khẩu đã hoàn thành nghĩa vụ', $s['completed_population']],
            ['Khẩu chưa hoàn thành', $s['incomplete_population']],
        ], $filters, (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0) ?: null);
    }

    private function financialReport(array $filters): array
    {
        $s = $this->summary($filters);
        return $this->reportTable('Báo cáo tài chính đóng góp hộ', ['Chỉ tiêu','Giá trị'], [
            ['Tổng số tiền theo quy định', $s['gross_total']],
            ['Tổng số tiền được miễn', $s['exempt_total']],
            ['Tổng phải thu thực tế', $s['expected_total']],
            ['Tổng số tiền đã thu', $s['collected_amount']],
            ['Tổng số tiền còn phải thu', $s['debt_amount']],
            ['Tỷ lệ hoàn thành (%)', $s['completion_rate']],
        ], $filters, (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0) ?: null);
    }

    private function exemptionReport(array $filters): array
    {
        [$where, $params] = $this->summaryWhere($filters);
        $rows = $this->fetchAll(
            "SELECT h.household_code, h.head_citizen_name, hc.eligible_count, hc.exempt_count, hc.exempt_amount, hc.calculation_note, hc.note, hc.updated_at
             FROM household_contributions hc
             INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id
             LEFT JOIN contribution_categories cc ON cc.id=c.category_id
             INNER JOIN households h ON h.id=hc.household_id
             $where AND (hc.payment_status='EXEMPT' OR hc.exempt_count > 0 OR hc.exempt_amount > 0)
             ORDER BY h.household_code ASC",
            $params
        );
        return $this->reportTable('Danh sách hộ được miễn đóng góp', ['STT','Chủ hộ','Số khẩu','Số khẩu miễn','Lý do miễn','Đối tượng miễn','Số tiền miễn','Người phê duyệt','Ngày phê duyệt'], array_map(function ($r, $i) {
            $note = json_decode((string) ($r['calculation_note'] ?? ''), true);
            $subjects = is_array($note['exempt_subjects'] ?? null) ? $note['exempt_subjects'] : [];
            return [
                $i + 1,
                $r['head_citizen_name'],
                (int) ($r['eligible_count'] ?? 0),
                (int) ($r['exempt_count'] ?? 0),
                implode(', ', array_unique(array_filter(array_map(fn($s) => $s['reason'] ?? '', $subjects)))) ?: 'Theo cấu hình miễn giảm',
                implode(', ', array_filter(array_map(fn($s) => $s['full_name'] ?? '', $subjects))),
                (float) ($r['exempt_amount'] ?? 0),
                '',
                $r['updated_at'] ?? '',
            ];
        }, $rows, array_keys($rows)), $filters, (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0) ?: null);
    }

    private function syncCampaign(int $campaignId): void
    {
        $campaign = $this->fetchOne('SELECT * FROM contribution_campaigns WHERE id=:id AND status <> "DELETED" AND ' . $this->tenantWhere('contribution_campaigns'), $this->withTenant(['id' => $campaignId]));
        if (!$campaign) return;
        $households = $this->fetchAll('SELECT h.* FROM households h WHERE ' . $this->tenantWhere('h', 'households') . ' AND ' . self::ACTIVE_HOUSEHOLD . ' ORDER BY h.household_code', $this->withTenant());
        foreach ($households as $household) {
            $members = $this->householdMembers((int) $household['id']);
            $calc = $this->rules->calculateHousehold($campaign, $household, $members);
            $existing = $this->fetchOne('SELECT * FROM household_contributions WHERE campaign_id=:campaign_id AND household_id=:household_id AND status="ACTIVE" AND ' . $this->tenantWhere('household_contributions'), $this->withTenant(['campaign_id' => $campaignId, 'household_id' => (int) $household['id']]));
            $paid = (float) ($existing['paid_amount'] ?? $existing['amount'] ?? 0);
            $hasAutoDiscount = !empty($calc['has_auto_discount']);
            $discount = $hasAutoDiscount ? (float) ($calc['discount_amount'] ?? 0) : (float) ($existing['discount_amount'] ?? 0);
            $expected = max(0, (float) $calc['expected_amount'] - ($hasAutoDiscount ? 0 : $discount));
            $status = (string) ($existing['payment_status'] ?? 'UNPAID');
            $debt = max(0, $expected - $paid);
            if ($expected <= 0) $status = 'EXEMPT';
            elseif ($paid <= 0 && $discount <= 0) $status = 'UNPAID';
            elseif ($debt > 0) $status = 'PARTIAL';
            elseif ($discount > 0) $status = 'REDUCED';
            else $status = 'PAID';
            $params = [
                'campaign_id' => $campaignId,
                'household_id' => (int) $household['id'],
                'payment_status' => $status,
                'expected_amount' => $expected,
                'gross_amount' => (float) $calc['gross_amount'],
                'exempt_amount' => (float) $calc['exempt_amount'],
                'discount_amount' => $discount,
                'paid_amount' => $paid,
                'amount' => $paid,
                'debt_amount' => $status === 'EXEMPT' ? 0 : $debt,
                'eligible_count' => (int) $calc['eligible_count'],
                'exempt_count' => (int) $calc['exempt_count'],
                'chargeable_count' => (int) $calc['chargeable_count'],
                'calculation_note' => json_encode(['engine' => $calc['note'], 'exempt_subjects' => $calc['exempt_subjects'], 'discount_percent' => (float) ($calc['discount_percent'] ?? 0), 'discount_reason' => (string) ($calc['discount_reason'] ?? '')], JSON_UNESCAPED_UNICODE),
            ];
            $this->execute(
                'INSERT INTO household_contributions (village_id, campaign_id, household_id, payment_status, expected_amount, gross_amount, exempt_amount, discount_amount, paid_amount, amount, debt_amount, eligible_count, exempt_count, chargeable_count, calculation_note)
                 VALUES (:village_id,:campaign_id,:household_id,:payment_status,:expected_amount,:gross_amount,:exempt_amount,:discount_amount,:paid_amount,:amount,:debt_amount,:eligible_count,:exempt_count,:chargeable_count,:calculation_note)
                 ON DUPLICATE KEY UPDATE payment_status=VALUES(payment_status), expected_amount=VALUES(expected_amount), gross_amount=VALUES(gross_amount), exempt_amount=VALUES(exempt_amount), discount_amount=VALUES(discount_amount), paid_amount=VALUES(paid_amount), amount=VALUES(amount), debt_amount=VALUES(debt_amount), eligible_count=VALUES(eligible_count), exempt_count=VALUES(exempt_count), chargeable_count=VALUES(chargeable_count), calculation_note=VALUES(calculation_note), status="ACTIVE", updated_at=CURRENT_TIMESTAMP',
                $this->withTenant($params)
            );
        }
    }

    private function householdMembers(int $householdId): array
    {
        return $this->fetchAll('SELECT c.* FROM citizens c WHERE c.household_id=:household_id AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . self::ACTIVE_CITIZEN . ' ORDER BY CASE WHEN c.relationship="Chủ hộ" THEN 0 ELSE 1 END, c.full_name', $this->withTenant(['household_id' => $householdId]));
    }

    private function campaignWhere(array $filters, bool $withOrder = true): array
    {
        $where = ['c.status <> "DELETED"', $this->tenantWhere('c', 'contribution_campaigns'), '(cc.id IS NULL OR ' . $this->tenantWhere('cc', 'contribution_categories') . ')'];
        $params = $this->withTenant();
        $campaignId = (int) ($filters['campaign_id'] ?? $filters['campaignId'] ?? 0);
        if ($campaignId > 0) { $where[] = 'c.id = :campaign_id'; $params['campaign_id'] = $campaignId; }
        $categoryId = (int) ($filters['category_id'] ?? $filters['categoryId'] ?? 0);
        if ($categoryId > 0) { $where[] = 'c.category_id = :category_id'; $params['category_id'] = $categoryId; }
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(LOWER(c.contribution_name) LIKE :search OR LOWER(c.period_name) LIKE :search OR LOWER(c.note) LIKE :search OR LOWER(cc.name) LIKE :search OR LOWER(cc.code) LIKE :search)'; $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%'; }
        $contributionName = trim((string) ($filters['contribution_name'] ?? $filters['contributionName'] ?? ''));
        if ($contributionName !== '') { $where[] = 'LOWER(c.contribution_name) LIKE :contribution_name'; $params['contribution_name'] = '%' . mb_strtolower($contributionName, 'UTF-8') . '%'; }
        $year = (int) ($filters['year'] ?? 0);
        if ($year > 0) { $where[] = 'c.year = :year'; $params['year'] = $year; }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && isset(self::CAMPAIGN_STATUS[$status])) { $where[] = 'c.status = :status'; $params['status'] = $status; }
        return ['WHERE ' . implode(' AND ', $where), $params, $withOrder ? $this->listOrder($filters, ['year' => 'c.year', 'contribution_name' => 'c.contribution_name', 'category_name' => 'cc.name', 'status' => 'c.status'], 'year', 'DESC', ['c.id DESC']) : ''];
    }

    private function trackingWhere(int $campaignId, array $filters): array
    {
        $where = ['hc.campaign_id=:campaign_id', 'hc.status="ACTIVE"', $this->tenantWhere('hc', 'household_contributions'), $this->tenantWhere('h', 'households'), self::ACTIVE_HOUSEHOLD];
        $params = $this->withTenant(['campaign_id' => $campaignId]);
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(LOWER(h.household_code) LIKE :search OR LOWER(h.head_citizen_name) LIKE :search OR LOWER(h.address) LIKE :search OR LOWER(hc.receipt_number) LIKE :search)'; $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%'; }
        $householdId = (int) ($filters['household_id'] ?? $filters['householdId'] ?? 0);
        if ($householdId > 0) { $where[] = 'h.id = :household_id'; $params['household_id'] = $householdId; }
        $payment = strtoupper(trim((string) ($filters['payment_status'] ?? $filters['paymentStatus'] ?? '')));
        if ($payment !== '' && isset(self::PAYMENT_STATUS[$payment])) { $where[] = 'hc.payment_status = :payment_status'; $params['payment_status'] = $payment; }
        $area = trim((string) ($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') { $where[] = 'h.area_code = :area_code'; $params['area_code'] = $area; }
        return ['WHERE ' . implode(' AND ', $where), $params, $this->listOrder($filters, ['household_code' => 'h.household_code', 'head_citizen_name' => 'h.head_citizen_name', 'area_code' => 'h.area_code', 'payment_status' => 'hc.payment_status', 'paid_amount' => 'hc.paid_amount', 'debt_amount' => 'hc.debt_amount'], 'household_code', 'ASC', ['h.household_code ASC', 'hc.id ASC'])];
    }

    private function summaryWhere(array $filters): array
    {
        if (empty($filters['campaign_id']) && empty($filters['campaignId']) && empty($filters['year']) && empty($filters['status'])) {
            $latest = $this->fetchOne('SELECT id FROM contribution_campaigns WHERE status="ACTIVE" AND ' . $this->tenantWhere('contribution_campaigns') . ' ORDER BY year DESC, id DESC LIMIT 1', $this->withTenant());
            if ($latest) $filters['campaign_id'] = (int) $latest['id'];
        }
        [$campaignWhere, $params] = $this->campaignWhere($filters, false);
        return [$campaignWhere . ' AND hc.status="ACTIVE" AND ' . $this->tenantWhere('hc', 'household_contributions') . ' AND ' . $this->tenantWhere('h', 'households') . ' AND ' . self::ACTIVE_HOUSEHOLD, $params];
    }

    private function campaignParams(array $data, int $userId): array
    {
        $name = trim((string) ($data['contribution_name'] ?? $data['contributionName'] ?? ''));
        if ($name === '') throw new \RuntimeException('Tên khoản thu là bắt buộc');
        $year = (int) ($data['year'] ?? date('Y'));
        if ($year < 2000 || $year > (int) date('Y') + 5) throw new \RuntimeException('Năm thu không hợp lệ');
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::CAMPAIGN_STATUS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        $unitType = strtoupper(trim((string) ($data['unit_type'] ?? $data['unitType'] ?? 'HOUSEHOLD')));
        if (!isset(ContributionRuleEngine::UNIT_TYPES[$unitType])) $unitType = 'HOUSEHOLD';
        $params = [
            'contribution_name' => $name,
            'contribution_type' => trim((string) ($data['contribution_type'] ?? $data['contributionType'] ?? '')) ?: null,
            'year' => $year,
            'period_name' => trim((string) ($data['period_name'] ?? $data['periodName'] ?? '')) ?: null,
            'amount' => max(0, (float) ($data['amount'] ?? 0)),
            'unit' => trim((string) ($data['unit'] ?? 'VNĐ/hộ')) ?: 'VNĐ/hộ',
            'unit_type' => $unitType,
            'start_date' => trim((string) ($data['start_date'] ?? $data['startDate'] ?? '')) ?: null,
            'due_date' => trim((string) ($data['due_date'] ?? $data['dueDate'] ?? '')) ?: null,
            'target_config_json' => $this->configJson($data, 'target'),
            'exemption_config_json' => $this->configJson($data, 'exemption'),
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'status' => $status,
            'user' => $userId,
        ];
        return $this->applyRuleTemplate($params, $data);
    }

    private function configJson(array $data, string $prefix): string
    {
        $raw = $data[$prefix . '_config_json'] ?? $data[$prefix . 'ConfigJson'] ?? $data[$prefix . '_config'] ?? $data[$prefix . 'Config'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }
        $conditions = $data[$prefix . '_conditions'] ?? $data[$prefix . 'Conditions'] ?? [];
        if (is_string($conditions)) $conditions = array_filter(array_map('trim', explode(',', $conditions)));
        if (!is_array($conditions)) $conditions = [];
        if ($conditions === [] && $prefix === 'target') $conditions = ['ALL_HOUSEHOLDS'];
        return json_encode(['conditions' => array_values($conditions), 'age_from' => (int) ($data['age_from'] ?? $data['ageFrom'] ?? 0), 'age_to' => (int) ($data['age_to'] ?? $data['ageTo'] ?? 0)], JSON_UNESCAPED_UNICODE);
    }

    private function campaignParamsV2(array $data, int $userId): array
    {
        $categoryId = (int) ($data['category_id'] ?? $data['categoryId'] ?? 0);
        $category = $categoryId > 0 ? $this->findCategory($categoryId) : null;
        if ($categoryId > 0 && !$category) throw new \RuntimeException('Khong tim thay khoan thu');
        $name = trim((string) ($data['contribution_name'] ?? $data['contributionName'] ?? ($category['name'] ?? '')));
        if ($name === '') throw new \RuntimeException('Vui long chon khoan thu truoc khi tao dot thu');
        $year = (int) ($data['year'] ?? date('Y'));
        if ($year < 2000 || $year > (int) date('Y') + 5) throw new \RuntimeException('Nam thu khong hop le');
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::CAMPAIGN_STATUS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        $unitType = strtoupper(trim((string) ($data['unit_type'] ?? $data['unitType'] ?? ($category['unit_type'] ?? 'HOUSEHOLD'))));
        if (!isset(ContributionRuleEngine::UNIT_TYPES[$unitType])) $unitType = 'HOUSEHOLD';
        $params = [
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'contribution_name' => $name,
            'contribution_type' => trim((string) ($data['contribution_type'] ?? $data['contributionType'] ?? ($category['contribution_type'] ?? ''))) ?: null,
            'year' => $year,
            'period_name' => trim((string) ($data['period_name'] ?? $data['periodName'] ?? '')) ?: null,
            'amount' => max(0, (float) ($data['amount'] ?? ($category['amount'] ?? 0))),
            'unit' => trim((string) ($data['unit'] ?? ($category['unit'] ?? 'VNĐ/hộ'))) ?: 'VNĐ/hộ',
            'unit_type' => $unitType,
            'start_date' => trim((string) ($data['start_date'] ?? $data['startDate'] ?? '')) ?: null,
            'due_date' => trim((string) ($data['due_date'] ?? $data['dueDate'] ?? '')) ?: null,
            'target_config_json' => $this->configJson($data, 'target'),
            'exemption_config_json' => $this->configJson($data, 'exemption'),
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'status' => $status,
            'user' => $userId,
        ];
        if ($category) $params = $this->inheritCategoryDefaults($params, $data, $category);
        return $this->applyRuleTemplate($params, $data);
    }

    private function categoryParams(array $data, int $userId, ?int $id = null): array
    {
        $name = trim((string) ($data['name'] ?? $data['contribution_name'] ?? $data['contributionName'] ?? ''));
        if ($name === '') throw new \RuntimeException('Ten khoan thu la bat buoc');
        $code = strtoupper(trim((string) ($data['code'] ?? $data['category_code'] ?? $data['categoryCode'] ?? '')));
        if ($code === '') $code = $this->categoryCodeFromName($name);
        $duplicateSql = 'SELECT id FROM contribution_categories WHERE code=:code AND status <> "DELETED" AND ' . $this->tenantWhere('contribution_categories');
        $duplicateParams = $this->withTenant(['code' => $code]);
        if ($id) {
            $duplicateSql .= ' AND id <> :id';
            $duplicateParams['id'] = $id;
        }
        $duplicate = $this->fetchOne($duplicateSql, $duplicateParams);
        if ($duplicate) throw new \RuntimeException('Ma khoan thu da ton tai');
        $unitType = strtoupper(trim((string) ($data['unit_type'] ?? $data['unitType'] ?? 'HOUSEHOLD')));
        if (!isset(ContributionRuleEngine::UNIT_TYPES[$unitType])) $unitType = 'HOUSEHOLD';
        $cycle = strtoupper(trim((string) ($data['collection_cycle'] ?? $data['collectionCycle'] ?? 'YEARLY')));
        if (!isset(self::COLLECTION_CYCLES[$cycle])) $cycle = 'YEARLY';
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!isset(self::CATEGORY_STATUS[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        return [
            'code' => $code,
            'name' => $name,
            'contribution_type' => trim((string) ($data['contribution_type'] ?? $data['contributionType'] ?? $name)) ?: $name,
            'unit_type' => $unitType,
            'amount' => max(0, (float) ($data['amount'] ?? 0)),
            'unit' => trim((string) ($data['unit'] ?? 'VNĐ/hộ')) ?: 'VNĐ/hộ',
            'collection_cycle' => $cycle,
            'custom_cycle' => trim((string) ($data['custom_cycle'] ?? $data['customCycle'] ?? '')) ?: null,
            'target_config_json' => $this->configJson($data, 'target'),
            'exemption_config_json' => $this->configJson($data, 'exemption'),
            'status' => $status,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'user' => $userId,
        ];
    }

    private function inheritCategoryDefaults(array $params, array $data, array $category): array
    {
        if (!$this->hasConfigInput($data, 'target')) $params['target_config_json'] = (string) ($category['target_config_json'] ?? $params['target_config_json']);
        if (!$this->hasConfigInput($data, 'exemption')) $params['exemption_config_json'] = (string) ($category['exemption_config_json'] ?? $params['exemption_config_json']);
        if (!array_key_exists('amount', $data)) $params['amount'] = (float) ($category['amount'] ?? $params['amount']);
        if (!array_key_exists('unit', $data)) $params['unit'] = (string) ($category['unit'] ?? $params['unit']);
        if (!array_key_exists('unit_type', $data) && !array_key_exists('unitType', $data)) $params['unit_type'] = (string) ($category['unit_type'] ?? $params['unit_type']);
        return $params;
    }

    private function categoryWhere(array $filters): array
    {
        $where = ['cc.status <> "DELETED"', $this->tenantWhere('cc', 'contribution_categories')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(LOWER(cc.name) LIKE :search OR LOWER(cc.code) LIKE :search OR LOWER(cc.note) LIKE :search)';
            $params['search'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && isset(self::CATEGORY_STATUS[$status])) {
            $where[] = 'cc.status = :status';
            $params['status'] = $status;
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function categoryOptions(): array
    {
        return array_map(fn($row) => $this->normalizeCategoryOption($row), $this->fetchAll('SELECT * FROM contribution_categories WHERE status="ACTIVE" AND ' . $this->tenantWhere('contribution_categories') . ' ORDER BY name ASC', $this->withTenant()));
    }

    private function normalizeCategoryOption(array $row): array
    {
        $category = $this->normalizeCategory($row);
        return $category + ['value' => $category['id'], 'label' => $category['name']];
    }

    private function normalizeCategory(array $row): array
    {
        $status = (string) ($row['status'] ?? 'ACTIVE');
        $cycle = (string) ($row['collection_cycle'] ?? 'YEARLY');
        $unitType = (string) ($row['unit_type'] ?? 'HOUSEHOLD');
        return [
            'id' => (int) $row['id'],
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'contribution_name' => (string) ($row['name'] ?? ''),
            'contribution_type' => (string) ($row['contribution_type'] ?? ''),
            'unit_type' => $unitType,
            'unit_type_label' => ContributionRuleEngine::UNIT_TYPES[$unitType] ?? $unitType,
            'amount' => (float) ($row['amount'] ?? 0),
            'unit' => (string) ($row['unit'] ?? 'VNĐ/hộ'),
            'collection_cycle' => $cycle,
            'collection_cycle_label' => self::COLLECTION_CYCLES[$cycle] ?? $cycle,
            'custom_cycle' => (string) ($row['custom_cycle'] ?? ''),
            'target_config_json' => $row['target_config_json'] ?? null,
            'exemption_config_json' => $row['exemption_config_json'] ?? null,
            'status' => $status,
            'status_label' => self::CATEGORY_STATUS[$status] ?? $status,
            'note' => (string) ($row['note'] ?? ''),
            'campaign_count' => (int) ($row['campaign_count'] ?? 0),
            'active_campaign_count' => (int) ($row['active_campaign_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function refreshCampaignsFromCategory(int $categoryId): void
    {
        foreach ($this->fetchAll('SELECT id FROM contribution_campaigns WHERE category_id=:id AND status="ACTIVE" AND ' . $this->tenantWhere('contribution_campaigns'), $this->withTenant(['id' => $categoryId])) as $row) {
            $this->syncCampaign((int) $row['id']);
        }
    }

    private function categoryCodeFromName(string $name): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $this->asciiText($name)) ?: 'KHOAN_THU');
        $base = trim($base, '_') ?: 'KHOAN_THU';
        $code = substr($base, 0, 32);
        $suffix = 1;
        while ($this->fetchOne('SELECT id FROM contribution_categories WHERE code=:code AND ' . $this->tenantWhere('contribution_categories'), $this->withTenant(['code' => $code]))) {
            $tail = '_' . (++$suffix);
            $code = substr($base, 0, 40 - strlen($tail)) . $tail;
        }
        return $code;
    }

    private function asciiText(string $value): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return is_string($converted) && $converted !== '' ? $converted : $value;
    }

    private function normalizeCampaign(array $row): array
    {
        $status = (string) ($row['status'] ?? 'ACTIVE');
        return [
            'id' => (int) $row['id'],
            'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
            'category_code' => (string) ($row['category_code'] ?? ''),
            'category_name' => (string) ($row['category_name'] ?? ''),
            'category_cycle' => (string) ($row['category_cycle'] ?? ''),
            'contribution_name' => (string) $row['contribution_name'],
            'contribution_type' => (string) ($row['contribution_type'] ?? ''),
            'year' => (int) $row['year'],
            'period_name' => (string) ($row['period_name'] ?? ''),
            'amount' => (float) ($row['amount'] ?? 0),
            'unit' => (string) ($row['unit'] ?? 'VNĐ/hộ'),
            'unit_type' => (string) ($row['unit_type'] ?? 'HOUSEHOLD'),
            'unit_type_label' => ContributionRuleEngine::UNIT_TYPES[(string) ($row['unit_type'] ?? 'HOUSEHOLD')] ?? (string) ($row['unit_type'] ?? ''),
            'start_date' => $row['start_date'] ?? null,
            'due_date' => $row['due_date'] ?? null,
            'target_config_json' => $row['target_config_json'] ?? null,
            'exemption_config_json' => $row['exemption_config_json'] ?? null,
            'note' => (string) ($row['note'] ?? ''),
            'status' => $status,
            'status_label' => self::CAMPAIGN_STATUS[$status] ?? $status,
            'paid_households' => (int) ($row['paid_households'] ?? 0),
            'partial_households' => (int) ($row['partial_households'] ?? 0),
            'unpaid_households' => (int) ($row['unpaid_households'] ?? 0),
            'exempt_households' => (int) ($row['exempt_households'] ?? 0),
            'gross_total' => (float) ($row['gross_total'] ?? 0),
            'exempt_total' => (float) ($row['exempt_total'] ?? 0),
            'expected_total' => (float) ($row['expected_total'] ?? 0),
            'collected_amount' => (float) ($row['collected_amount'] ?? 0),
            'debt_amount' => (float) ($row['debt_amount'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function normalizeTracking(array $row): array
    {
        $payment = (string) ($row['payment_status'] ?? 'UNPAID');
        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'campaign_id' => isset($row['campaign_id']) ? (int) $row['campaign_id'] : null,
            'contribution_name' => (string) ($row['contribution_name'] ?? ''),
            'due_date' => $row['due_date'] ?? null,
            'unit_amount' => (float) ($row['unit_amount'] ?? 0),
            'unit' => (string) ($row['unit'] ?? ''),
            'household_id' => (int) $row['household_id'],
            'household_code' => (string) $row['household_code'],
            'head_citizen_name' => (string) $row['head_citizen_name'],
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
            'payment_status' => $payment,
            'payment_status_label' => self::PAYMENT_STATUS[$payment] ?? $payment,
            'expected_amount' => (float) ($row['expected_amount'] ?? 0),
            'gross_amount' => (float) ($row['gross_amount'] ?? 0),
            'exempt_amount' => (float) ($row['exempt_amount'] ?? 0),
            'discount_amount' => (float) ($row['discount_amount'] ?? 0),
            'paid_amount' => (float) ($row['paid_amount'] ?? $row['amount'] ?? 0),
            'debt_amount' => (float) ($row['debt_amount'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
            'eligible_count' => (int) ($row['eligible_count'] ?? 0),
            'exempt_count' => (int) ($row['exempt_count'] ?? 0),
            'chargeable_count' => (int) ($row['chargeable_count'] ?? 0),
            'paid_at' => $row['paid_at'] ?? null,
            'collector_name' => (string) ($row['collector_name'] ?? ''),
            'payment_method' => (string) ($row['payment_method'] ?? 'CASH'),
            'payment_method_label' => ['CASH' => 'Tiền mặt', 'TRANSFER' => 'Chuyển khoản', 'OTHER' => 'Khác'][(string) ($row['payment_method'] ?? 'CASH')] ?? (string) ($row['payment_method'] ?? ''),
            'receipt_number' => (string) ($row['receipt_number'] ?? ''),
            'calculation_note' => $row['calculation_note'] ?? null,
            'note' => (string) ($row['note'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function assertTableReady(string $table): void
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table]
        );
        if ((int) ($row['total'] ?? 0) <= 0) {
            throw new \RuntimeException('Household Contributions schema/catalog is not provisioned: missing table ' . $table);
        }
    }

    private function assertTenantScopeReady(string $table): void
    {
        $row = $this->fetchOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = "village_id"',
            ['table' => $table]
        );
        $type = strtolower((string) ($row['COLUMN_TYPE'] ?? ''));
        if (!str_contains($type, 'bigint') || !str_contains($type, 'unsigned') || (string) ($row['IS_NULLABLE'] ?? '') !== 'NO') {
            throw new \RuntimeException('Household Contributions schema/catalog is not provisioned: invalid tenant scope ' . $table . '.village_id');
        }
    }

    private function assertIndexReady(string $table, string $index): void
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index',
            ['table' => $table, 'index' => $index]
        );
        if ((int) ($row['total'] ?? 0) <= 0) {
            throw new \RuntimeException('Household Contributions schema/catalog is not provisioned: missing index ' . $table . '.' . $index);
        }
    }

    private function assertPaymentStatusContract(): void
    {
        $row = $this->fetchOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "household_contributions" AND COLUMN_NAME = "payment_status"'
        );
        $type = strtolower((string) ($row['COLUMN_TYPE'] ?? ''));
        foreach (['unpaid', 'paid', 'partial', 'exempt', 'reduced'] as $status) {
            if (!str_contains($type, "'" . $status . "'")) {
                throw new \RuntimeException('Household Contributions schema/catalog is not provisioned: invalid household_contributions.payment_status enum');
            }
        }
        if ((string) ($row['IS_NULLABLE'] ?? '') !== 'NO' || trim((string) ($row['COLUMN_DEFAULT'] ?? ''), "'") !== 'UNPAID') {
            throw new \RuntimeException('Household Contributions schema/catalog is not provisioned: invalid household_contributions.payment_status default');
        }
    }

    private function assertCanonicalCategoriesReady(): void
    {
        foreach (self::REQUIRED_CATEGORY_CODES as $code) {
            $row = $this->fetchOne(
                'SELECT COUNT(*) AS total FROM contribution_categories WHERE code = :code AND status = "ACTIVE" AND ' . $this->tenantWhere('contribution_categories'),
                $this->withTenant(['code' => $code])
            );
            if ((int) ($row['total'] ?? 0) <= 0) {
                throw new \RuntimeException('Household Contributions schema/catalog is not provisioned: missing active category ' . $code);
            }
        }
    }

    private function assertRuleTemplateReady(): void
    {
        $rows = $this->fetchAll(
            'SELECT * FROM contribution_rule_templates WHERE template_code = :code AND status = "ACTIVE" AND ' . $this->tenantWhere('contribution_rule_templates'),
            $this->withTenant(['code' => self::REQUIRED_TEMPLATE_CODE])
        );
        foreach ($rows as $row) {
            $target = json_decode((string) ($row['target_config_json'] ?? ''), true);
            $conditions = is_array($target) && isset($target['conditions']) && is_array($target['conditions']) ? $target['conditions'] : [];
            if ((string) ($row['unit_type'] ?? '') === 'PERSON'
                && (float) ($row['amount'] ?? 0) === 0.0
                && in_array('ALL_PEOPLE', array_map('strval', $conditions), true)) {
                return;
            }
        }
        throw new \RuntimeException('Household Contributions schema/catalog is not provisioned: missing compatible WASTE_COLLECTION template');
    }

    private function applyRuleTemplate(array $params, array $data): array
    {
        $template = $this->findRuleTemplate((string) $params['contribution_name'], (string) ($params['contribution_type'] ?? ''));
        if (!$template) return $params;
        if (!$this->hasConfigInput($data, 'target')) $params['target_config_json'] = (string) ($template['target_config_json'] ?? $params['target_config_json']);
        if (!$this->hasConfigInput($data, 'exemption')) $params['exemption_config_json'] = (string) ($template['exemption_config_json'] ?? $params['exemption_config_json']);
        $submittedUnitType = strtoupper(trim((string) ($data['unit_type'] ?? $data['unitType'] ?? '')));
        if ($submittedUnitType === '' || $submittedUnitType === 'HOUSEHOLD') $params['unit_type'] = (string) ($template['unit_type'] ?? $params['unit_type']);
        $submittedUnit = trim((string) ($data['unit'] ?? ''));
        if ($submittedUnit === '' || $submittedUnit === 'VNĐ/hộ') $params['unit'] = (string) ($template['unit'] ?? $params['unit']);
        return $params;
    }

    private function findRuleTemplate(string $name, string $type): ?array
    {
        $normalized = $this->normalizeText($name . ' ' . $type);
        foreach ($this->fetchAll('SELECT * FROM contribution_rule_templates WHERE status="ACTIVE"') as $template) {
            $needle = $this->normalizeText((string) ($template['contribution_name'] ?? $template['category_name'] ?? ''));
            if ($needle !== '' && str_contains($normalized, $needle)) return $template;
        }
        return null;
    }

    private function hasConfigInput(array $data, string $prefix): bool
    {
        foreach ([$prefix . '_config_json', $prefix . 'ConfigJson', $prefix . '_config', $prefix . 'Config', $prefix . '_conditions', $prefix . 'Conditions'] as $key) {
            if (!array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === []) continue;
            if ($prefix === 'target' && in_array($key, [$prefix . '_conditions', $prefix . 'Conditions'], true)) {
                $values = is_array($data[$key]) ? $data[$key] : array_filter(array_map('trim', explode(',', (string) $data[$key])));
                if ($values === ['ALL_HOUSEHOLDS']) continue;
            }
            return true;
        }
        return false;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $from = ['ỹ','á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ','đ','é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ','í','ì','ỉ','ĩ','ị','ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ','ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự','ý','ỳ','ỷ','ỹ','ỵ'];
        $to = ['y','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','d','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y'];
        return str_replace($from, $to, $value);
    }

    private function upsertRateRule(int $campaignId, array $params): void
    {
        $this->execute('INSERT INTO contribution_rate_rules (village_id, campaign_id, rule_name, unit_type, amount, target_config_json, effective_from, effective_to) VALUES (:village_id,:campaign_id,:rule_name,:unit_type,:amount,:target_config_json,:effective_from,:effective_to)', $this->withTenant(['campaign_id' => $campaignId, 'rule_name' => $params['contribution_name'], 'unit_type' => $params['unit_type'], 'amount' => $params['amount'], 'target_config_json' => $params['target_config_json'], 'effective_from' => $params['start_date'], 'effective_to' => $params['due_date']]));
    }

    private function writePaymentHistory(int $contributionId, array $params, int $userId): void
    {
        if ($contributionId <= 0) return;
        $this->execute('INSERT INTO contribution_payment_history (village_id, contribution_id, campaign_id, household_id, action, amount, payment_status, paid_at, collector_name, receipt_number, note, created_by) VALUES (:village_id,:contribution_id,:campaign_id,:household_id,"PAYMENT",:amount,:payment_status,:paid_at,:collector_name,:receipt_number,:note,:created_by)', $this->withTenant(['contribution_id' => $contributionId, 'campaign_id' => $params['campaign_id'], 'household_id' => $params['household_id'], 'amount' => $params['paid_amount'], 'payment_status' => $params['payment_status'], 'paid_at' => $params['paid_at'], 'collector_name' => $params['collector_name'], 'receipt_number' => $params['receipt_number'], 'note' => $params['note'], 'created_by' => $userId]));
    }

    private function writeReceipt(int $contributionId, array $params, int $userId): void
    {
        if ($contributionId <= 0) return;
        $this->execute('INSERT INTO contribution_receipts (village_id, contribution_id, campaign_id, household_id, receipt_number, amount, paid_at, collector_name, payment_method, note, created_by) VALUES (:village_id,:contribution_id,:campaign_id,:household_id,:receipt_number,:amount,:paid_at,:collector_name,:payment_method,:note,:created_by)', $this->withTenant(['contribution_id' => $contributionId, 'campaign_id' => $params['campaign_id'], 'household_id' => $params['household_id'], 'receipt_number' => $params['receipt_number'], 'amount' => $params['paid_amount'], 'paid_at' => $params['paid_at'], 'collector_name' => $params['collector_name'], 'payment_method' => $params['payment_method'] ?? 'CASH', 'note' => $params['note'], 'created_by' => $userId]));
    }

    private function writeAdjustment(int $campaignId, ?int $householdId, mixed $before, mixed $after, int $userId, string $reason): void
    {
        $this->execute('INSERT INTO contribution_adjustment_history (village_id, campaign_id, household_id, before_json, after_json, reason, created_by) VALUES (:village_id,:campaign_id,:household_id,:before_json,:after_json,:reason,:created_by)', $this->withTenant(['campaign_id' => $campaignId, 'household_id' => $householdId, 'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE), 'after_json' => json_encode($after, JSON_UNESCAPED_UNICODE), 'reason' => $reason, 'created_by' => $userId]));
    }

    private function activeHouseholdCount(): int
    {
        return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->tenantWhere('h', 'households') . ' AND ' . self::ACTIVE_HOUSEHOLD, $this->withTenant()) ?: [])['total'] ?? 0);
    }

    private function activeCitizenCount(): int
    {
        return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households') . ' AND ' . self::ACTIVE_CITIZEN . ' AND ' . self::ACTIVE_HOUSEHOLD, $this->withTenant()) ?: [])['total'] ?? 0);
    }

    private function contributionRows(int $campaignId, array $filters): array
    {
        [$where, $params, $order] = $this->trackingWhere($campaignId, $filters);
        $rows = $this->fetchAll(
            "SELECT h.id AS household_id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code,
                c.contribution_name, c.year, c.period_name, c.amount AS campaign_amount, c.unit, c.due_date,
                hc.*
             FROM household_contributions hc
             INNER JOIN households h ON h.id=hc.household_id
             INNER JOIN contribution_campaigns c ON c.id=hc.campaign_id
             $where $order",
            $params
        );
        return array_map(fn($row) => $this->normalizeTracking($row), $rows);
    }

    private function latestCampaignId(array $filters = []): int
    {
        [$where, $params] = $this->campaignWhere($filters, false);
        $row = $this->fetchOne("SELECT c.id FROM contribution_campaigns c LEFT JOIN contribution_categories cc ON cc.id=c.category_id $where ORDER BY c.year DESC, c.id DESC LIMIT 1", $params);
        return (int) ($row['id'] ?? 0);
    }

    private function options(array $map): array
    {
        return array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys($map), array_values($map));
    }

    private function reportTable(string $title, array $headers, array $rows, array $filters, ?int $campaignId = null): array
    {
        $report = $this->table($title, $headers, $rows, $filters);
        $campaign = $campaignId ? $this->findCampaign($campaignId) : null;
        $period = $campaign
            ? trim(($campaign['contribution_name'] ?? '') . ' - ' . ($campaign['period_name'] ?? '') . ' - Năm ' . ($campaign['year'] ?? ''))
            : ('Năm ' . (string) ($filters['year'] ?? date('Y')));
        $report['meta'] = [
            'national_header' => 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM - Độc lập - Tự do - Hạnh phúc',
            'unit_name' => TenantConfig::unitName(),
            'period_label' => 'Thời gian thống kê: ' . $period,
            'prepared_by' => 'Người lập biểu: ................................',
            'approved_by' => 'Trưởng thôn ký xác nhận: ................................',
            'report_date' => 'Ngày lập báo cáo: ' . date('d/m/Y'),
            'page_footer' => 'Trang {PAGE}/{PAGES}',
        ];
        return $report;
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')];
    }
}
