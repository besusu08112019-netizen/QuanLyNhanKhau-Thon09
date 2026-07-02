<?php

namespace App\Models;

use App\Core\BaseModel;

final class GisHouseholdLocation extends BaseModel
{
    public function ensureSchema(): void
    {
        $columns = [
            'latitude' => 'DECIMAL(10,8) NULL',
            'longitude' => 'DECIMAL(11,8) NULL',
            'location_accuracy' => 'INT NULL',
            'location_source' => "ENUM('MANUAL','GPS') NOT NULL DEFAULT 'MANUAL'",
            'location_updated_at' => 'DATETIME NULL',
            'location_updated_by' => 'BIGINT NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('households', $column)) {
                $this->execute('ALTER TABLE households ADD COLUMN ' . $column . ' ' . $definition);
            } elseif (in_array($column, ['latitude', 'longitude', 'location_updated_by'], true)) {
                $this->execute('ALTER TABLE households MODIFY COLUMN ' . $column . ' ' . $definition);
            }
        }

        $this->createIndexIfMissing('households', 'idx_households_location', 'latitude, longitude');
        $this->createIndexIfMissing('households', 'idx_households_area_location', 'area_code, latitude, longitude');
    }

    public function markers(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->markerConditions($filters);
        $rows = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code,
                h.latitude, h.longitude, h.location_accuracy, h.location_source, h.location_updated_at,
                h.poor_household, h.near_poor_household, h.meritorious_family, h.disabled_household,
                COALESCE(v.total_members, 0) AS total_members,
                COALESCE(v.at_home_count, 0) AS at_home_count,
                COALESCE(v.away_count, 0) AS away_count
             FROM households h
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             WHERE h.status NOT IN ("DELETED", "ENDED", "MERGED", "TRANSFERRED_OUT", "MOVED_OUT", "INACTIVE")
                AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL' . $where . '
             ORDER BY h.household_code ASC
             LIMIT 1000',
            $params
        );

        return [
            'items' => array_map(fn(array $row) => $this->normalizeMarker($row), $rows),
            'total' => count($rows),
        ];
    }

    public function saveLocation(int $householdId, array $data, int $userId): array
    {
        $this->ensureSchema();
        $lat = $this->coordinate($data['latitude'] ?? $data['lat'] ?? null, -90, 90, 'Latitude');
        $lng = $this->coordinate($data['longitude'] ?? $data['lng'] ?? null, -180, 180, 'Longitude');
        $accuracy = isset($data['accuracy']) && $data['accuracy'] !== '' ? max(0, (int) $data['accuracy']) : null;
        $source = strtoupper(trim((string) ($data['source'] ?? $data['location_source'] ?? 'MANUAL')));
        if (!in_array($source, ['MANUAL', 'GPS'], true)) $source = 'MANUAL';
        $areaCode = $this->areaCodeForPoint($lat, $lng);

        $this->db->beginTransaction();
        try {
            $updated = $this->execute(
                'UPDATE households
                 SET latitude = :latitude,
                     longitude = :longitude,
                     location_accuracy = :location_accuracy,
                     location_source = :location_source,
                     location_updated_at = NOW(),
                     location_updated_by = :location_updated_by,
                     area_code = :area_code,
                     updated_at = NOW(),
                     updated_by = :updated_by
                 WHERE id = :id AND status <> "DELETED"',
                [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'location_accuracy' => $accuracy,
                    'location_source' => $source,
                    'location_updated_by' => $userId,
                    'area_code' => $areaCode,
                    'updated_by' => $userId,
                    'id' => $householdId,
                ]
            );
            if ($updated < 1) throw new \RuntimeException('Không tìm thấy hộ gia đình cần định vị');
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        return $this->findMarker($householdId) ?? ['id' => $householdId, 'area_code' => $areaCode];
    }

    public function clearLocation(int $householdId, int $userId): void
    {
        $this->ensureSchema();
        $updated = $this->execute(
            'UPDATE households
             SET latitude = NULL,
                 longitude = NULL,
                 location_accuracy = NULL,
                 location_source = "MANUAL",
                 location_updated_at = NOW(),
                 location_updated_by = :location_updated_by,
                 area_code = NULL,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id AND status <> "DELETED"',
            ['location_updated_by' => $userId, 'updated_by' => $userId, 'id' => $householdId]
        );
        if ($updated < 1) throw new \RuntimeException('Không tìm thấy hộ gia đình cần xóa vị trí');
    }

    public function recalculateAreaCodes(): int
    {
        $this->ensureSchema();
        $areas = $this->activeAreas();
        $rows = $this->fetchAll('SELECT id, latitude, longitude, area_code FROM households WHERE status <> "DELETED" AND latitude IS NOT NULL AND longitude IS NOT NULL');
        $changed = 0;
        foreach ($rows as $row) {
            $newCode = $this->areaCodeForPoint((float) $row['latitude'], (float) $row['longitude'], $areas);
            $oldCode = ($row['area_code'] ?? '') !== '' ? (string) $row['area_code'] : null;
            if ($newCode === $oldCode) continue;
            $this->execute('UPDATE households SET area_code = :area_code, updated_at = NOW() WHERE id = :id', ['area_code' => $newCode, 'id' => (int) $row['id']]);
            $changed++;
        }
        return $changed;
    }

    private function findMarker(int $householdId): ?array
    {
        $rows = $this->markers(['id' => $householdId]);
        return $rows['items'][0] ?? null;
    }

    private function markerConditions(array $filters): array
    {
        $where = '';
        $params = [];
        if (!empty($filters['id'])) {
            $where .= ' AND h.id = :id';
            $params['id'] = (int) $filters['id'];
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where .= ' AND (h.household_code LIKE :search OR h.head_citizen_name LIKE :search OR h.address LIKE :search OR h.phone LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        foreach (['north', 'south', 'east', 'west'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') $params[$key] = (float) $filters[$key];
        }
        if (isset($params['north'], $params['south'], $params['east'], $params['west'])) {
            $where .= ' AND h.latitude BETWEEN :south AND :north AND h.longitude BETWEEN :west AND :east';
        }
        return [$where, $params];
    }

    private function normalizeMarker(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'household_code' => (string) ($row['household_code'] ?? ''),
            'head_citizen_name' => (string) ($row['head_citizen_name'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'area_code' => ($row['area_code'] ?? '') !== '' ? (string) $row['area_code'] : null,
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'location_accuracy' => $row['location_accuracy'] !== null ? (int) $row['location_accuracy'] : null,
            'location_source' => (string) ($row['location_source'] ?? 'MANUAL'),
            'location_updated_at' => $row['location_updated_at'] ?? null,
            'total_members' => (int) ($row['total_members'] ?? 0),
            'at_home_count' => (int) ($row['at_home_count'] ?? 0),
            'away_count' => (int) ($row['away_count'] ?? 0),
            'household_type' => $this->householdType($row),
        ];
    }

    private function householdType(array $row): string
    {
        if ((int) ($row['poor_household'] ?? 0) === 1) return 'Hộ nghèo';
        if ((int) ($row['near_poor_household'] ?? 0) === 1) return 'Hộ cận nghèo';
        if ((int) ($row['meritorious_family'] ?? 0) === 1) return 'Hộ có công';
        if ((int) ($row['disabled_household'] ?? 0) === 1) return 'Hộ có người khuyết tật';
        return 'Hộ bình thường';
    }

    private function coordinate(mixed $value, float $min, float $max, string $label): float
    {
        if ($value === null || $value === '' || !is_numeric($value)) throw new \RuntimeException($label . ' không hợp lệ');
        $number = (float) $value;
        if ($number < $min || $number > $max) throw new \RuntimeException($label . ' nằm ngoài phạm vi cho phép');
        return round($number, 8);
    }

    private function areaCodeForPoint(float $lat, float $lng, ?array $areas = null): ?string
    {
        foreach ($areas ?? $this->activeAreas() as $area) {
            if ($this->pointInPolygon($lat, $lng, $area['polygon'])) return $area['area_code'];
        }
        return null;
    }

    private function activeAreas(): array
    {
        $rows = $this->fetchAll('SELECT area_code, polygon, geometry_json FROM gis_areas WHERE status <> "DELETED" ORDER BY sort_order, name');
        $areas = [];
        foreach ($rows as $row) {
            $polygon = $this->normalizePolygon(json_decode((string) ($row['polygon'] ?? $row['geometry_json'] ?? '[]'), true));
            if (count($polygon) >= 3) $areas[] = ['area_code' => (string) $row['area_code'], 'polygon' => $polygon];
        }
        return $areas;
    }

    private function normalizePolygon(mixed $input): array
    {
        if (!is_array($input)) return [];
        if (($input['type'] ?? '') === 'Feature') $input = $input['geometry'] ?? [];
        if (($input['type'] ?? '') === 'Polygon') {
            $input = $input['coordinates'][0] ?? [];
            return array_values(array_filter(array_map(fn($point) => is_array($point) && count($point) >= 2 ? ['lat' => (float) $point[1], 'lng' => (float) $point[0]] : null, $input)));
        }
        return array_values(array_filter(array_map(function ($point) {
            if (!is_array($point)) return null;
            if (isset($point['lat'], $point['lng'])) return ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']];
            if (isset($point[0], $point[1])) return ['lat' => (float) $point[1], 'lng' => (float) $point[0]];
            return null;
        }, $input)));
    }

    private function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);
        if ($count < 3) return false;
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $yi = (float) $polygon[$i]['lat'];
            $xi = (float) $polygon[$i]['lng'];
            $yj = (float) $polygon[$j]['lat'];
            $xj = (float) $polygon[$j]['lng'];
            $intersects = (($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 0.0000000001) + $xi);
            if ($intersects) $inside = !$inside;
        }
        return $inside;
    }

    private function createIndexIfMissing(string $table, string $index, string $columns): void
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index', ['table' => $table, 'index' => $index]);
        if ((int) ($row['total'] ?? 0) > 0) return;
        try {
            $this->execute('ALTER TABLE ' . $table . ' ADD INDEX ' . $index . ' (' . $columns . ')');
        } catch (\Throwable $e) {
            error_log('[GIS_LOCATION_INDEX_WARNING] ' . $e->getMessage());
        }
    }
}
