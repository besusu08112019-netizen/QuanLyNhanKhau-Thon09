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
            'location_source' => "ENUM('MANUAL','GPS') NULL DEFAULT NULL",
            'location_updated_at' => 'DATETIME NULL',
            'location_updated_by' => 'BIGINT NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('households', $column)) {
                $this->execute('ALTER TABLE households ADD COLUMN ' . $column . ' ' . $definition);
            } elseif (in_array($column, ['latitude', 'longitude', 'location_source', 'location_updated_by'], true)) {
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
                h.status,
                h.poor_household, h.near_poor_household, h.meritorious_family, h.disabled_household,
                COALESCE(v.total_members, 0) AS total_members,
                COALESCE(v.at_home_count, 0) AS at_home_count,
                COALESCE(v.away_count, 0) AS away_count,
                COALESCE(cm.party_members, 0) AS party_members,
                COALESCE(cm.children_count, 0) AS children_count,
                COALESCE(cm.elderly_count, 0) AS elderly_count,
                COALESCE(cm.working_age_count, 0) AS working_age_count,
                COALESCE(cm.permanent_count, 0) AS permanent_count,
                COALESCE(cm.temporary_count, 0) AS temporary_count,
                COALESCE(cm.labor_count, 0) AS labor_count,
                (SELECT f.id FROM file_attachments f WHERE COALESCE(f.entity_type, f.module) = "household" AND f.entity_id = h.id AND (f.status IS NULL OR f.status <> "DELETED") AND (f.file_type IN ("PHOTO","IMAGE") OR f.mime_type LIKE "image/%") ORDER BY CASE COALESCE(f.profile_section, f.category, "") WHEN "front_house" THEN 0 WHEN "inside_house" THEN 1 ELSE 2 END, f.id DESC LIMIT 1) AS thumbnail_file_id,
                (SELECT COUNT(1) FROM file_attachments f WHERE COALESCE(f.entity_type, f.module) = "household" AND f.entity_id = h.id AND (f.status IS NULL OR f.status <> "DELETED") AND (f.file_type IN ("PHOTO","IMAGE") OR f.mime_type LIKE "image/%")) AS gallery_count
             FROM households h
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             LEFT JOIN (
                SELECT c.household_id,
                    SUM(CASE WHEN c.party_member = 1 THEN 1 ELSE 0 END) AS party_members,
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) < 16 THEN 1 ELSE 0 END) AS children_count,
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) >= 60 THEN 1 ELSE 0 END) AS elderly_count,
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) BETWEEN 16 AND 59 THEN 1 ELSE 0 END) AS working_age_count,
                    SUM(CASE WHEN c.residency_status = "PERMANENT" THEN 1 ELSE 0 END) AS permanent_count,
                    SUM(CASE WHEN c.residency_status = "TEMPORARY" THEN 1 ELSE 0 END) AS temporary_count,
                    SUM(CASE WHEN c.employed = 1 OR c.freelance_labor = 1 OR c.out_province_labor = 1 OR c.foreign_labor = 1 THEN 1 ELSE 0 END) AS labor_count
                FROM citizens c
                WHERE c.status <> "DELETED"
                GROUP BY c.household_id
             ) cm ON cm.household_id = h.id
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

    public function clearLocation(int $householdId, int $userId): array
    {
        $this->ensureSchema();
        $updated = $this->execute(
            'UPDATE households
             SET latitude = NULL,
                 longitude = NULL,
                 location_accuracy = NULL,
                 location_source = NULL,
                 location_updated_at = NOW(),
                 location_updated_by = :location_updated_by,
                 area_code = NULL,
                 updated_at = NOW(),
                 updated_by = :updated_by
             WHERE id = :id AND status <> "DELETED"',
            ['location_updated_by' => $userId, 'updated_by' => $userId, 'id' => $householdId]
        );
        if ($updated < 1) throw new \RuntimeException('Không tìm thấy hộ gia đình cần xóa vị trí');

        return [
            'id' => $householdId,
            'latitude' => null,
            'longitude' => null,
            'location_accuracy' => null,
            'location_source' => null,
            'location_updated_at' => date('Y-m-d H:i:s'),
            'location_updated_by' => $userId,
            'area_code' => null,
            'removed' => true,
        ];
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
            $where .= ' AND (h.household_code LIKE :search OR h.head_citizen_name LIKE :search OR h.address LIKE :search OR h.phone LIKE :search OR EXISTS (SELECT 1 FROM citizens cs WHERE cs.household_id = h.id AND cs.status <> "DELETED" AND (cs.full_name LIKE :search OR cs.identity_number LIKE :search)))';
            $params['search'] = '%' . $search . '%';
        }
        $areaCode = trim((string) ($filters['area_code'] ?? ''));
        if ($areaCode !== '') {
            $where .= ' AND h.area_code = :area_code';
            $params['area_code'] = $areaCode;
        }
        foreach (['party' => 'cm.party_members', 'children' => 'cm.children_count', 'elderly' => 'cm.elderly_count', 'labor' => 'cm.labor_count', 'permanent' => 'cm.permanent_count', 'temporary' => 'cm.temporary_count'] as $filterKey => $column) {
            if ($this->enabledFilter($filters[$filterKey] ?? null)) $where .= ' AND ' . $column . ' > 0';
        }
        if ($this->enabledFilter($filters['poor'] ?? null)) $where .= ' AND h.poor_household = 1';
        if ($this->enabledFilter($filters['near_poor'] ?? null)) $where .= ' AND h.near_poor_household = 1';
        foreach (['north', 'south', 'east', 'west'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') $params[$key] = (float) $filters[$key];
        }
        if (isset($params['north'], $params['south'], $params['east'], $params['west'])) {
            $where .= ' AND h.latitude BETWEEN :south AND :north AND h.longitude BETWEEN :west AND :east';
        }
        return [$where, $params];
    }

    private function enabledFilter(mixed $value): bool
    {
        if ($value === null || $value === '') return false;
        return !in_array(strtolower((string) $value), ['0', 'false', 'no', 'off'], true);
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
            'residency_status' => $this->residencyStatus($row),
            'status' => (string) ($row['status'] ?? ''),
            'household_type' => $this->householdType($row),
            'poor_household' => (int) ($row['poor_household'] ?? 0),
            'near_poor_household' => (int) ($row['near_poor_household'] ?? 0),
            'thumbnail_file_id' => $row['thumbnail_file_id'] !== null ? (int) $row['thumbnail_file_id'] : null,
            'thumbnail_url' => $row['thumbnail_file_id'] !== null ? '/api/files/' . (int) $row['thumbnail_file_id'] . '/preview' : null,
            'gallery_count' => (int) ($row['gallery_count'] ?? 0),
            'party_members' => (int) ($row['party_members'] ?? 0),
            'children_count' => (int) ($row['children_count'] ?? 0),
            'elderly_count' => (int) ($row['elderly_count'] ?? 0),
            'working_age_count' => (int) ($row['working_age_count'] ?? 0),
            'permanent_count' => (int) ($row['permanent_count'] ?? 0),
            'temporary_count' => (int) ($row['temporary_count'] ?? 0),
            'labor_count' => (int) ($row['labor_count'] ?? 0),
            'gps' => trim((string) ($row['latitude'] ?? '')) . ', ' . trim((string) ($row['longitude'] ?? '')),
        ];
    }

    private function residencyStatus(array $row): string
    {
        $atHome = (int) ($row['at_home_count'] ?? 0);
        $away = (int) ($row['away_count'] ?? 0);
        if ($atHome > 0 && $away > 0) return 'Có người đi vắng';
        if ($away > 0) return 'Tạm vắng';
        return 'Thường trú';
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
