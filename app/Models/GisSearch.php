<?php

namespace App\Models;

use App\Core\BaseModel;

class GisSearch extends BaseModel
{
    public function households(string $query, int $limit = 10): array
    {
        $this->ensureIndexes();

        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $limit = min(max($limit, 1), 10);
        $like = '%' . $query . '%';
        $prefix = $query . '%';

        $sql = "SELECT
                    h.id,
                    h.household_code,
                    h.head_citizen_name,
                    h.address,
                    h.phone,
                    h.area_code,
                    h.latitude,
                    h.longitude,
                    h.location_accuracy,
                    h.location_source,
                    h.location_updated_at,
                    h.poor_household,
                    h.near_poor_household,
                    h.meritorious_family,
                    h.disabled_household,
                    COALESCE(v.total_members, 0) AS total_members,
                    COALESCE(v.at_home_count, 0) AS at_home_count,
                    COALESCE(v.away_count, 0) AS away_count,
                    CASE
                        WHEN h.household_code LIKE :rank_code THEN 0
                        WHEN h.head_citizen_name LIKE :rank_head THEN 1
                        ELSE 2
                    END AS match_rank
                FROM households h
                LEFT JOIN v_household_member_counts v ON v.household_id = h.id
                WHERE (h.status IS NULL OR h.status NOT IN ('DELETED', 'ARCHIVED'))
                  AND (
                    h.household_code LIKE :household_code
                    OR h.head_citizen_name LIKE :head_name
                    OR EXISTS (
                        SELECT 1
                        FROM citizens c
                        WHERE c.household_id = h.id
                          AND (c.status IS NULL OR c.status <> 'DELETED')
                          AND c.full_name LIKE :citizen_name
                    )
                  )
                ORDER BY match_rank ASC, h.household_code ASC, h.head_citizen_name ASC
                LIMIT " . (int) $limit;

        $rows = $this->fetchAll($sql, [
            'rank_code' => $prefix,
            'rank_head' => $prefix,
            'household_code' => $like,
            'head_name' => $like,
            'citizen_name' => $like,
        ]);

        return array_map(fn(array $row): array => $this->normalize($row), $rows);
    }

    private function normalize(array $row): array
    {
        $hasLocation = $row['latitude'] !== null && $row['latitude'] !== ''
            && $row['longitude'] !== null && $row['longitude'] !== '';

        return [
            'id' => (int) $row['id'],
            'household_code' => (string) ($row['household_code'] ?? ''),
            'head_name' => (string) ($row['head_citizen_name'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'area_code' => $row['area_code'] ?: null,
            'latitude' => $hasLocation ? (float) $row['latitude'] : null,
            'longitude' => $hasLocation ? (float) $row['longitude'] : null,
            'has_location' => $hasLocation,
            'location_accuracy' => $row['location_accuracy'] !== null ? (int) $row['location_accuracy'] : null,
            'location_source' => $row['location_source'] ?: null,
            'location_updated_at' => $row['location_updated_at'] ?? null,
            'total_members' => (int) ($row['total_members'] ?? 0),
            'at_home_count' => (int) ($row['at_home_count'] ?? 0),
            'away_count' => (int) ($row['away_count'] ?? 0),
            'household_type' => $this->householdType($row),
        ];
    }

    private function householdType(array $row): string
    {
        if ((int) ($row['poor_household'] ?? 0) === 1) {
            return 'Hộ nghèo';
        }
        if ((int) ($row['near_poor_household'] ?? 0) === 1) {
            return 'Hộ cận nghèo';
        }
        if ((int) ($row['meritorious_family'] ?? 0) === 1) {
            return 'Gia đình có công';
        }
        if ((int) ($row['disabled_household'] ?? 0) === 1) {
            return 'Hộ có người khuyết tật';
        }
        return 'Không ưu tiên';
    }

    private function ensureIndexes(): void
    {
        $this->createIndexIfMissing('households', 'idx_households_household_code', 'household_code');
        $this->createIndexIfMissing('households', 'idx_households_head_name', 'head_citizen_name');
        $this->createIndexIfMissing('citizens', 'idx_citizens_full_name', 'full_name');
    }

    private function createIndexIfMissing(string $table, string $name, string $columns): void
    {
        $exists = $this->fetchOne(
            'SELECT COUNT(1) AS total
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND INDEX_NAME = :name',
            ['table' => $table, 'name' => $name]
        );

        if ((int) ($exists['total'] ?? 0) === 0) {
            $this->execute("CREATE INDEX {$name} ON {$table} ({$columns})");
        }
    }
}
