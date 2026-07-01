<?php

namespace App\Models;

use App\Core\BaseModel;

final class SystemInsight extends BaseModel
{
    public function globalSearch(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') return ['households' => [], 'citizens' => []];
        $limit = min(max($limit, 5), 50);
        $q = '%' . $query . '%';

        $households = $this->fetchAll(
            "SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code, h.poor_household, h.near_poor_household, h.meritorious_family, h.status,
                    COALESCE(v.total_members,0) AS member_count_real,
                    'household' AS result_type
             FROM households h
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             WHERE h.status <> 'DELETED'
               AND (h.household_code LIKE :q_code OR h.head_citizen_name LIKE :q_head OR h.address LIKE :q_address OR h.phone LIKE :q_phone OR h.area_code LIKE :q_area OR h.note LIKE :q_note)
             ORDER BY h.household_code
             LIMIT $limit",
            ['q_code' => $q, 'q_head' => $q, 'q_address' => $q, 'q_phone' => $q, 'q_area' => $q, 'q_note' => $q]
        );

        $citizens = $this->fetchAll(
            "SELECT c.id, c.citizen_code, c.full_name, c.identity_number, c.phone, c.gender, c.date_of_birth, c.relationship, c.residency_status, c.presence_status, c.life_status,
                    h.id AS household_id, h.household_code, h.address AS household_address,
                    'citizen' AS result_type
             FROM citizens c
             INNER JOIN households h ON h.id = c.household_id
             WHERE c.status <> 'DELETED'
               AND (c.citizen_code LIKE :q_code OR c.full_name LIKE :q_name OR c.identity_number LIKE :q_identity OR c.phone LIKE :q_phone OR h.household_code LIKE :q_household OR h.address LIKE :q_address)
             ORDER BY c.full_name
             LIMIT $limit",
            ['q_code' => $q, 'q_name' => $q, 'q_identity' => $q, 'q_phone' => $q, 'q_household' => $q, 'q_address' => $q]
        );

        foreach ($citizens as &$row) {
            if (!empty($row['identity_number'])) $row['identity_masked'] = $this->maskIdentity((string) $row['identity_number']);
            $row['computed_age'] = $this->age($row['date_of_birth'] ?? null);
        }

        return ['households' => $households, 'citizens' => $citizens];
    }

    public function smartAlerts(): array
    {
        return [
            'missing_identity' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND (identity_number IS NULL OR identity_number = '')"),
            'missing_phone' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND (phone IS NULL OR phone = '')"),
            'invalid_identity' => $this->countOne("SELECT COUNT(*) AS total FROM citizens WHERE status <> 'DELETED' AND identity_number IS NOT NULL AND identity_number <> '' AND identity_number NOT REGEXP '^[0-9]{9,12}$'"),
            'duplicate_identity' => $this->countOne("SELECT COUNT(*) AS total FROM (SELECT identity_number FROM citizens WHERE status <> 'DELETED' AND identity_number IS NOT NULL AND identity_number <> '' GROUP BY identity_number HAVING COUNT(*) > 1) d"),
            'households_without_members' => $this->countOne("SELECT COUNT(*) AS total FROM households h LEFT JOIN citizens c ON c.household_id = h.id AND c.status <> 'DELETED' WHERE h.status <> 'DELETED' GROUP BY h.id HAVING COUNT(c.id) = 0", true),
            'missing_area_code' => $this->countOne("SELECT COUNT(*) AS total FROM households WHERE status <> 'DELETED' AND (area_code IS NULL OR area_code = '')"),
        ];
    }

    private function countOne(string $sql, bool $countRows = false): int
    {
        if ($countRows) return count($this->fetchAll($sql));
        return (int) ($this->fetchOne($sql)['total'] ?? 0);
    }

    private function age(mixed $date): ?int
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $date)) return null;
        try { return (int) (new \DateTimeImmutable((string) $date))->diff(new \DateTimeImmutable('today'))->y; } catch (\Throwable) { return null; }
    }

    private function maskIdentity(string $identity): string
    {
        $identity = trim($identity);
        if (mb_strlen($identity) <= 8) return $identity;
        return mb_substr($identity, 0, 4) . '••••' . mb_substr($identity, -4);
    }
}
