<?php

namespace App\Services;

use App\Core\BaseModel;
use App\Policies\AgePolicy;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use Throwable;

final class HealthInsuranceEligibilityService extends BaseModel
{
    public const TYPE_STUDENT = 'STUDENT';
    public const TYPE_MEDIUM_HOUSEHOLD = 'MEDIUM_HOUSEHOLD';
    public const TYPE_POLICY = 'POLICY';
    public const TYPE_OTHER = 'OTHER';

    public const SOURCE_AGE_RULE = 'AGE_RULE';
    public const SOURCE_HOUSEHOLD_MEDIUM = 'HOUSEHOLD_MEDIUM';
    public const SOURCE_POLICY_RECORD = 'POLICY_RECORD';
    public const SOURCE_MANUAL = 'MANUAL';

    private const ACTIVE = 'ACTIVE';
    private const ENDED = 'ENDED';
    private ?bool $schemaAvailable = null;

    public static function classify(?string $dateOfBirth, bool $householdMedium, ?DateTimeInterface $referenceDate = null): ?array
    {
        $referenceDate ??= new DateTimeImmutable('today');
        $age = AgePolicy::ageFromDate($dateOfBirth, $referenceDate);
        if ($age === null) return null;

        if ($age < 18) {
            return ['type' => self::TYPE_STUDENT, 'source' => self::SOURCE_AGE_RULE, 'age' => $age];
        }

        if ($age >= 70) {
            return ['type' => self::TYPE_POLICY, 'source' => self::SOURCE_AGE_RULE, 'age' => $age];
        }

        if ($householdMedium) {
            return ['type' => self::TYPE_MEDIUM_HOUSEHOLD, 'source' => self::SOURCE_HOUSEHOLD_MEDIUM, 'age' => $age];
        }

        return ['type' => null, 'source' => null, 'age' => $age];
    }

    public static function legacyGroupForType(?string $type): ?string
    {
        return match ($type) {
            self::TYPE_STUDENT => 'Học sinh',
            self::TYPE_MEDIUM_HOUSEHOLD => 'Hộ trung bình',
            self::TYPE_POLICY => 'Chính sách',
            self::TYPE_OTHER => 'Đối tượng khác',
            default => null,
        };
    }

    public function synchronizeCitizen(int $citizenId, int $userId, ?DateTimeInterface $referenceDate = null, array $requestMeta = []): void
    {
        if (!$this->schemaAvailable()) {
            $this->logMissingSchema('synchronizeCitizen', $citizenId);
            return;
        }
        $citizen = $this->fetchOne(
            'SELECT c.id, c.household_id, c.date_of_birth, h.status AS household_status
             FROM citizens c INNER JOIN households h ON h.id=c.household_id
             WHERE c.id=:id AND COALESCE(c.status,"ACTIVE") <> "DELETED"
               AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households'),
            $this->withTenant(['id' => $citizenId])
        );
        if (!$citizen) return;

        $referenceDate ??= new DateTimeImmutable('today');
        $year = (int) $referenceDate->format('Y');
        $householdId = (int) $citizen['household_id'];
        $isMedium = $this->householdHasMediumForYear($householdId, $year);
        $target = self::classify((string) $citizen['date_of_birth'], $isMedium, $referenceDate);

        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) $this->db->beginTransaction();
        try {
            $changed = $this->endSupersededSystemRecords($citizenId, $householdId, $year, $target['type'] ?? null, $userId, $requestMeta) > 0;
            $legacy = $this->legacyHealthInsuranceFields($citizenId);
            if (($target['type'] ?? null) !== null && !$this->hasPriorityRecord($citizenId, $year) && !$this->hasLegacyPriorityInsurance($legacy)) {
                $changed = $this->ensureActiveRecord($citizenId, $householdId, $year, $target['type'], $target['source'], $this->yearStart($year), null, $userId, $requestMeta) || $changed;
            }
            if ($changed || $this->effectiveRecordForCitizen($citizenId)) $this->recalculateLegacyFields($citizenId, $userId);
            if ($startedTransaction) $this->db->commit();
        } catch (Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function synchronizeHousehold(int $householdId, int $userId, ?DateTimeInterface $referenceDate = null, array $requestMeta = []): void
    {
        if (!$this->schemaAvailable()) {
            $this->logMissingSchema('synchronizeHousehold', $householdId);
            return;
        }
        $referenceDate ??= new DateTimeImmutable('today');
        $citizens = $this->fetchAll(
            'SELECT c.id FROM citizens c INNER JOIN households h ON h.id=c.household_id
             WHERE c.household_id=:household_id
               AND COALESCE(c.status,"ACTIVE") <> "DELETED"
               AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households'),
            $this->withTenant(['household_id' => $householdId])
        );
        foreach ($citizens as $citizen) {
            $this->synchronizeCitizen((int) $citizen['id'], $userId, $referenceDate, $requestMeta);
        }
    }

    public function endHouseholdMedium(int $householdId, int $year, int $userId, array $requestMeta = []): int
    {
        if (!$this->schemaAvailable()) {
            $this->logMissingSchema('endHouseholdMedium', $householdId);
            return 0;
        }
        $rows = $this->fetchAll(
            'SELECT * FROM citizen_health_insurance_records
             WHERE household_id=:household_id AND insurance_year=:insurance_year
               AND insurance_source=:source AND status=:status AND deleted_at IS NULL
               AND ' . $this->tenantWhere('citizen_health_insurance_records'),
            $this->withTenant([
                'household_id' => $householdId,
                'insurance_year' => $year,
                'source' => self::SOURCE_HOUSEHOLD_MEDIUM,
                'status' => self::ACTIVE,
            ])
        );

        $ended = 0;
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $this->endRecord($row, $this->yearEnd($year), $userId, $requestMeta);
                $this->recalculateLegacyFields((int) $row['citizen_id'], $userId);
                $ended++;
            }
            if ($startedTransaction) $this->db->commit();
        } catch (Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $ended;
    }

    public function reconcileYear(int $year, int $userId, array $requestMeta = []): array
    {
        if (!$this->schemaAvailable()) {
            $this->logMissingSchema('reconcileYear', $year);
            return ['households' => 0, 'year' => $year, 'skipped' => 'canonical_schema_missing'];
        }
        $households = $this->fetchAll(
            'SELECT DISTINCT h.id FROM households h
             WHERE h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND ' . $this->tenantWhere('h', 'households'),
            $this->withTenant()
        );
        foreach ($households as $household) {
            $this->synchronizeHousehold((int) $household['id'], $userId, new DateTimeImmutable($year . '-01-01'), $requestMeta);
        }
        return ['households' => count($households), 'year' => $year];
    }

    private function householdHasMediumForYear(int $householdId, int $year): bool
    {
        $row = $this->fetchOne(
            'SELECT id FROM household_poverty_records
             WHERE household_id=:household_id AND poverty_type="MEDIUM" AND status="ACTIVE" AND deleted_at IS NULL
               AND effective_from <= :year_end AND (effective_to IS NULL OR effective_to >= :year_start)
               AND ' . $this->tenantWhere('household_poverty_records') . ' LIMIT 1',
            $this->withTenant(['household_id' => $householdId, 'year_start' => $this->yearStart($year), 'year_end' => $this->yearEnd($year)])
        );
        return (bool) $row;
    }

    private function schemaAvailable(): bool
    {
        if ($this->schemaAvailable !== null) return $this->schemaAvailable;
        $required = [
            'citizen_health_insurance_records' => ['village_id','citizen_id','household_id','insurance_type','insurance_source','insurance_year','effective_from','effective_to','status','legacy_snapshot_json','deleted_at'],
            'citizen_health_insurance_change_logs' => ['village_id','record_id','citizen_id','household_id','action','before_json','after_json','actor_user_id','ip_address','user_agent'],
        ];
        foreach ($required as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->columnExists($table, $column)) {
                    return $this->schemaAvailable = false;
                }
            }
        }
        return $this->schemaAvailable = true;
    }

    private function logMissingSchema(string $operation, int $id): void
    {
        error_log('[BHYT_CANONICAL_SCHEMA_MISSING] operation=' . $operation . ' id=' . $id);
    }

    private function hasPriorityRecord(int $citizenId, int $year): bool
    {
        $row = $this->fetchOne(
            'SELECT id FROM citizen_health_insurance_records
             WHERE citizen_id=:citizen_id AND insurance_year=:insurance_year AND status=:status AND deleted_at IS NULL
               AND insurance_type IN (:policy,:other)
               AND insurance_source IN (:policy_source,:manual)
               AND ' . $this->tenantWhere('citizen_health_insurance_records') . ' LIMIT 1',
            $this->withTenant([
                'citizen_id' => $citizenId,
                'insurance_year' => $year,
                'status' => self::ACTIVE,
                'policy' => self::TYPE_POLICY,
                'other' => self::TYPE_OTHER,
                'policy_source' => self::SOURCE_POLICY_RECORD,
                'manual' => self::SOURCE_MANUAL,
            ])
        );
        return (bool) $row;
    }

    private function endSupersededSystemRecords(int $citizenId, int $householdId, int $year, ?string $targetType, int $userId, array $requestMeta): int
    {
        $rows = $this->fetchAll(
            'SELECT * FROM citizen_health_insurance_records
             WHERE citizen_id=:citizen_id AND insurance_year=:insurance_year AND status=:status AND deleted_at IS NULL
               AND insurance_source IN (:age_source,:medium_source)
               AND ' . $this->tenantWhere('citizen_health_insurance_records'),
            $this->withTenant([
                'citizen_id' => $citizenId,
                'insurance_year' => $year,
                'status' => self::ACTIVE,
                'age_source' => self::SOURCE_AGE_RULE,
                'medium_source' => self::SOURCE_HOUSEHOLD_MEDIUM,
            ])
        );
        $ended = 0;
        foreach ($rows as $row) {
            $keep = $row['insurance_type'] === $targetType
                && (int) $row['household_id'] === $householdId
                && ($targetType !== self::TYPE_MEDIUM_HOUSEHOLD || $row['insurance_source'] === self::SOURCE_HOUSEHOLD_MEDIUM)
                && ($targetType === self::TYPE_MEDIUM_HOUSEHOLD || $row['insurance_source'] === self::SOURCE_AGE_RULE);
            if (!$keep) {
                $this->endRecord($row, $this->yearEnd($year), $userId, $requestMeta);
                $ended++;
            }
        }
        return $ended;
    }

    private function ensureActiveRecord(int $citizenId, int $householdId, int $year, string $type, string $source, string $from, ?string $to, int $userId, array $requestMeta): bool
    {
        $existing = $this->fetchOne(
            'SELECT * FROM citizen_health_insurance_records
             WHERE citizen_id=:citizen_id AND household_id=:household_id AND insurance_year=:insurance_year
               AND insurance_type=:insurance_type AND insurance_source=:insurance_source
               AND status=:status AND deleted_at IS NULL AND ' . $this->tenantWhere('citizen_health_insurance_records') . ' LIMIT 1',
            $this->withTenant([
                'citizen_id' => $citizenId,
                'household_id' => $householdId,
                'insurance_year' => $year,
                'insurance_type' => $type,
                'insurance_source' => $source,
                'status' => self::ACTIVE,
            ])
        );
        if ($existing) return false;

        $columns = ['citizen_id','household_id','insurance_type','insurance_source','insurance_year','effective_from','effective_to','status','legacy_snapshot_json','note','created_by','updated_by'];
        $params = [
            'citizen_id' => $citizenId,
            'household_id' => $householdId,
            'insurance_type' => $type,
            'insurance_source' => $source,
            'insurance_year' => $year,
            'effective_from' => $from,
            'effective_to' => $to,
            'status' => self::ACTIVE,
            'legacy_snapshot_json' => json_encode($this->legacyHealthInsuranceFields($citizenId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'note' => $source === self::SOURCE_HOUSEHOLD_MEDIUM ? 'Auto from active medium household year' : 'Auto from age rule',
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
        $this->addTenantInsert('citizen_health_insurance_records', $columns, $params);
        $id = $this->insert('INSERT INTO citizen_health_insurance_records (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        $after = $this->fetchOne('SELECT * FROM citizen_health_insurance_records WHERE id=:id AND ' . $this->tenantWhere('citizen_health_insurance_records'), $this->withTenant(['id' => $id]));
        $this->writeChangeLog('create', $after, null, $after, $userId, $requestMeta);
        return true;
    }

    private function endRecord(array $row, string $effectiveTo, int $userId, array $requestMeta): void
    {
        $before = $row;
        $this->execute(
            'UPDATE citizen_health_insurance_records
             SET status=:ended, effective_to=:effective_to, updated_by=:user
             WHERE id=:id AND status=:active AND ' . $this->tenantWhere('citizen_health_insurance_records'),
            $this->withTenant(['id' => (int) $row['id'], 'ended' => self::ENDED, 'active' => self::ACTIVE, 'effective_to' => $effectiveTo, 'user' => $userId])
        );
        $after = $this->fetchOne('SELECT * FROM citizen_health_insurance_records WHERE id=:id AND ' . $this->tenantWhere('citizen_health_insurance_records'), $this->withTenant(['id' => (int) $row['id']]));
        $this->writeChangeLog('end', $after, $before, $after, $userId, $requestMeta);
    }

    private function recalculateLegacyFields(int $citizenId, int $userId): void
    {
        $record = $this->effectiveRecordForCitizen($citizenId);
        $legacy = $this->legacyHealthInsuranceFields($citizenId);
        if (!$record && $this->mustPreserveUnknownLegacy($citizenId, $legacy)) return;

        $hasInsurance = $record ? 1 : 0;
        $shouldWriteCompatibilityDetails = $record && !$this->legacyCurrentlyInsured($legacy);
        $shouldPreserveLegacyDetails = $record && $this->legacyCurrentlyInsured($legacy);
        $this->execute(
            'UPDATE citizens
             SET has_health_insurance=:has_health_insurance,
                 health_insurance_group=CASE WHEN :has_health_insurance_for_group=1 THEN COALESCE(:group_name, health_insurance_group) WHEN :preserve_group=1 THEN health_insurance_group ELSE NULL END,
                 health_insurance_start_date=CASE WHEN :has_health_insurance_for_start=1 THEN COALESCE(:start_date, health_insurance_start_date) WHEN :preserve_start=1 THEN health_insurance_start_date ELSE NULL END,
                 health_insurance_end_date=:end_date,
                 updated_by=:user
             WHERE id=:id AND ' . $this->tenantWhere('citizens'),
            $this->withTenant([
                'id' => $citizenId,
                'has_health_insurance' => $hasInsurance,
                'has_health_insurance_for_group' => $shouldWriteCompatibilityDetails ? 1 : 0,
                'has_health_insurance_for_start' => $shouldWriteCompatibilityDetails ? 1 : 0,
                'preserve_group' => $shouldPreserveLegacyDetails ? 1 : 0,
                'preserve_start' => $shouldPreserveLegacyDetails ? 1 : 0,
                'group_name' => $record ? self::legacyGroupForType((string) $record['insurance_type']) : null,
                'start_date' => $record['effective_from'] ?? null,
                'end_date' => $shouldWriteCompatibilityDetails ? ($record['effective_to'] ?? null) : ($record ? $legacy['health_insurance_end_date'] : null),
                'user' => $userId,
            ])
        );
    }

    private function legacyHealthInsuranceFields(int $citizenId): array
    {
        $row = $this->fetchOne(
            'SELECT has_health_insurance, health_insurance_number, health_insurance_group,
                    health_insurance_start_date, health_insurance_end_date, health_insurance_facility
             FROM citizens WHERE id=:id AND ' . $this->tenantWhere('citizens'),
            $this->withTenant(['id' => $citizenId])
        ) ?: [];
        return [
            'has_health_insurance' => (int) ($row['has_health_insurance'] ?? 0),
            'health_insurance_number' => $row['health_insurance_number'] ?? null,
            'health_insurance_group' => $row['health_insurance_group'] ?? null,
            'health_insurance_start_date' => $row['health_insurance_start_date'] ?? null,
            'health_insurance_end_date' => $row['health_insurance_end_date'] ?? null,
            'health_insurance_facility' => $row['health_insurance_facility'] ?? null,
        ];
    }

    private function legacyCurrentlyInsured(array $legacy): bool
    {
        return (int) ($legacy['has_health_insurance'] ?? 0) === 1;
    }

    private function hasLegacyPriorityInsurance(array $legacy): bool
    {
        if (!$this->legacyCurrentlyInsured($legacy)) return false;
        $group = mb_strtolower(trim((string) ($legacy['health_insurance_group'] ?? '')));
        if ($group === '') return false;
        foreach (['hộ trung bình', 'ho trung binh', 'hộ gia đình', 'ho gia dinh', 'học sinh', 'hoc sinh'] as $nonPriority) {
            if ($group === $nonPriority) return false;
        }
        return true;
    }

    private function mustPreserveUnknownLegacy(int $citizenId, array $legacy): bool
    {
        if (!$this->legacyCurrentlyInsured($legacy)) return false;
        $rows = $this->fetchAll(
            'SELECT legacy_snapshot_json FROM citizen_health_insurance_records
             WHERE citizen_id=:citizen_id AND deleted_at IS NULL
               AND ' . $this->tenantWhere('citizen_health_insurance_records') . '
             ORDER BY id ASC',
            $this->withTenant(['citizen_id' => $citizenId])
        );
        if (!$rows) return true;
        foreach ($rows as $row) {
            $snapshot = json_decode((string) ($row['legacy_snapshot_json'] ?? ''), true);
            if (!is_array($snapshot)) return true;
            if ((int) ($snapshot['has_health_insurance'] ?? 0) === 1) return true;
        }
        return false;
    }

    private function effectiveRecordForCitizen(int $citizenId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM citizen_health_insurance_records
             WHERE citizen_id=:citizen_id AND status=:status AND deleted_at IS NULL
               AND effective_from <= CURDATE() AND (effective_to IS NULL OR effective_to >= CURDATE())
               AND ' . $this->tenantWhere('citizen_health_insurance_records') . '
             ORDER BY CASE
                WHEN insurance_type IN ("POLICY","OTHER") AND insurance_source IN ("POLICY_RECORD","MANUAL") THEN 1
                WHEN insurance_type="POLICY" THEN 2
                WHEN insurance_type="STUDENT" THEN 3
                WHEN insurance_type="MEDIUM_HOUSEHOLD" THEN 4
                ELSE 5 END, id DESC LIMIT 1',
            $this->withTenant(['citizen_id' => $citizenId, 'status' => self::ACTIVE])
        );
    }

    private function writeChangeLog(string $action, ?array $record, ?array $before, ?array $after, int $userId, array $requestMeta): void
    {
        $columns = ['record_id','citizen_id','household_id','action','before_json','after_json','actor_user_id','ip_address','user_agent'];
        $params = [
            'record_id' => $record['id'] ?? $before['id'] ?? $after['id'] ?? null,
            'citizen_id' => $record['citizen_id'] ?? $before['citizen_id'] ?? $after['citizen_id'] ?? null,
            'household_id' => $record['household_id'] ?? $before['household_id'] ?? $after['household_id'] ?? null,
            'action' => $action,
            'before_json' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'actor_user_id' => $userId,
            'ip_address' => $this->nullable($requestMeta['ip'] ?? null),
            'user_agent' => $this->nullable(mb_substr((string) ($requestMeta['user_agent'] ?? ''), 0, 255)),
        ];
        $this->addTenantInsert('citizen_health_insurance_change_logs', $columns, $params);
        $this->insert('INSERT INTO citizen_health_insurance_change_logs (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
    }

    private function yearStart(int $year): string
    {
        if ($year < 1900 || $year > 2200) throw new RuntimeException('Invalid insurance year');
        return $year . '-01-01';
    }

    private function yearEnd(int $year): string
    {
        if ($year < 1900 || $year > 2200) throw new RuntimeException('Invalid insurance year');
        return $year . '-12-31';
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }
}
