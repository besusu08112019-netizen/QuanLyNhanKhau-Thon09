<?php

namespace App\Models;

use App\Core\BaseModel;

final class GisArea extends BaseModel
{
    private ?string $lastSql = null;
    private array $lastParams = [];

    public function all(): array
    {
        $this->ensureSchema();
        $areas = $this->fetchAll('SELECT * FROM gis_areas WHERE status <> "DELETED" ORDER BY sort_order, name');
        $households = $this->householdsForMapStats();
        $items = [];

        foreach ($areas as $area) {
            $item = $this->normalizeArea($area);
            $item['stats'] = $this->statsForPolygon($item['polygon'], $households);
            $item['stats'] = $this->enrichStats($item['stats'], $item);
            $items[] = $item;
        }

        return [
            'areas' => $items,
            'unassigned' => $this->unassignedStats($items, $households),
            'summary' => $this->summary($items, $households),
        ];
    }

    public function save(array $data, int $userId): array
    {
        $this->ensureSchema();
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $areaCode = trim((string) ($data['area_code'] ?? $data['areaCode'] ?? ''));
        $polygon = $this->normalizePolygonInput($data['polygon'] ?? $data['geometry'] ?? null);

        if ($name === '') throw new \RuntimeException('Tên khu vực là bắt buộc');
        if ($areaCode === '') throw new \RuntimeException('Mã khu vực là bắt buộc');
        if (!$this->validGeometry($polygon)) throw new \RuntimeException('Ranh giới khu vực chưa hợp lệ');

        $polygonGeoJson = $this->toGeoJson($polygon);
        $polygonJson = json_encode($polygonGeoJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($polygonJson === false) throw new \RuntimeException('Không mã hóa được dữ liệu polygon');

        $params = [
            'name' => $name,
            'area_code' => $areaCode,
            'color' => trim((string) ($data['color'] ?? '#0f8a4b')) ?: '#0f8a4b',
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'polygon' => $polygonJson,
            'geometry_json' => $polygonJson,
            'updated_by' => $userId,
        ];

        try {
            $this->db->beginTransaction();
            if ($id > 0 && $this->fetchOne('SELECT id FROM gis_areas WHERE id = :id AND status <> "DELETED"', ['id' => $id])) {
                $params['id'] = $id;
                $sql = 'UPDATE gis_areas SET name = :name, area_code = :area_code, color = :color, note = :note, polygon = :polygon, geometry_json = :geometry_json, updated_by = :updated_by, updated_at = NOW() WHERE id = :id';
                $this->trackedExecute($sql, $params);
            } else {
                $params['created_by'] = $userId;
                $sql = 'INSERT INTO gis_areas (name, area_code, color, note, polygon, geometry_json, created_by, updated_by) VALUES (:name, :area_code, :color, :note, :polygon, :geometry_json, :created_by, :updated_by)';
                $id = $this->trackedInsert($sql, $params);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logSqlFailure('save', $data, $e);
            throw $e;
        }

        return $this->find($id) ?: [];
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM gis_areas WHERE id = :id AND status <> "DELETED"', ['id' => $id]);
        if (!$row) return null;
        $item = $this->normalizeArea($row);
        $item['stats'] = $this->enrichStats($this->statsForPolygon($item['polygon'], $this->householdsForMapStats()), $item);
        return $item;
    }

    public function delete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy khu vực bản đồ');
        $sql = 'UPDATE gis_areas SET status = "DELETED", deleted_by = :deleted_by, deleted_at = NOW() WHERE id = :id';
        try {
            $this->trackedExecute($sql, ['id' => $id, 'deleted_by' => $userId]);
        } catch (\Throwable $e) {
            $this->logSqlFailure('delete', ['id' => $id], $e);
            throw $e;
        }
    }

    public function pdfRows(): array
    {
        $data = $this->all();
        return array_map(fn($area) => [
            'name' => $area['name'],
            'area_code' => $area['area_code'],
            'households' => (int) ($area['stats']['households'] ?? 0),
            'citizens' => (int) ($area['stats']['citizens'] ?? 0),
            'temporary' => (int) ($area['stats']['temporary'] ?? 0),
            'away' => (int) ($area['stats']['away'] ?? 0),
        ], $data['areas']);
    }

    public function ensureSchema(): void
    {
        $this->execute('CREATE TABLE IF NOT EXISTS gis_areas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            area_code VARCHAR(100) NOT NULL,
            color VARCHAR(20) DEFAULT "#0f8a4b",
            note TEXT NULL,
            polygon LONGTEXT NULL,
            geometry_json LONGTEXT NULL,
            sort_order INT DEFAULT 0,
            status VARCHAR(20) DEFAULT "ACTIVE",
            created_by INT NULL,
            updated_by INT NULL,
            deleted_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_gis_area_code (area_code),
            INDEX idx_gis_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $areaColumns = ['polygon' => 'LONGTEXT NULL', 'geometry_json' => 'LONGTEXT NULL'];
        foreach ($areaColumns as $column => $definition) {
            if (!$this->columnExists('gis_areas', $column)) $this->execute('ALTER TABLE gis_areas ADD COLUMN ' . $column . ' ' . $definition);
        }

        $householdColumns = [
            'latitude' => 'DECIMAL(10,7) NULL',
            'longitude' => 'DECIMAL(10,7) NULL',
            'google_map_url' => 'VARCHAR(255) NULL',
            'location_note' => 'TEXT NULL',
            'location_updated_at' => 'DATETIME NULL',
            'location_updated_by' => 'INT NULL',
        ];
        foreach ($householdColumns as $column => $definition) {
            if (!$this->columnExists('households', $column)) $this->execute('ALTER TABLE households ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    private function householdsForMapStats(): array
    {
        return $this->fetchAll('SELECT h.id, h.household_code, h.latitude, h.longitude, h.poor_household, h.near_poor_household, h.meritorious_family, h.disabled_household,
            COALESCE(v.total_members, 0) AS citizens,
            COALESCE(v.at_home_count, 0) AS at_home,
            COALESCE(v.away_count, 0) AS away,
            COALESCE(SUM(CASE WHEN c.residency_status = "TEMPORARY" AND c.status <> "DELETED" AND COALESCE(c.life_status, "ALIVE") <> "DECEASED" AND COALESCE(c.residency_status, "PERMANENT") <> "TRANSFERRED_OUT" THEN 1 ELSE 0 END), 0) AS temporary,
            COALESCE(SUM(CASE WHEN c.party_member = 1 AND c.status <> "DELETED" AND COALESCE(c.life_status, "ALIVE") <> "DECEASED" AND COALESCE(c.residency_status, "PERMANENT") <> "TRANSFERRED_OUT" THEN 1 ELSE 0 END), 0) AS party_members
            FROM households h
            LEFT JOIN v_household_member_counts v ON v.household_id = h.id
            LEFT JOIN citizens c ON c.household_id = h.id
            WHERE h.status NOT IN ("DELETED", "ENDED", "MERGED", "TRANSFERRED_OUT", "MOVED_OUT", "INACTIVE")
            GROUP BY h.id, h.household_code, h.latitude, h.longitude, h.poor_household, h.near_poor_household, h.meritorious_family, h.disabled_household, v.total_members, v.at_home_count, v.away_count');
    }

    private function statsForPolygon(array $polygon, array $households): array
    {
        $stats = $this->emptyStats('');
        foreach ($households as $household) {
            $lat = $household['latitude'] ?? null;
            $lng = $household['longitude'] ?? null;
            if ($lat === null || $lng === null || $lat === '' || $lng === '') continue;
            if (!$this->pointInPolygon((float) $lat, (float) $lng, $polygon)) continue;
            $stats['households']++;
            $stats['citizens'] += (int) ($household['citizens'] ?? 0);
            $stats['temporary'] += (int) ($household['temporary'] ?? 0);
            $stats['away'] += (int) ($household['away'] ?? 0);
            $stats['party_members'] += (int) ($household['party_members'] ?? 0);
            $stats['located']++;
            $stats['poor_households'] += (int) (($household['poor_household'] ?? 0) == 1);
            $stats['near_poor_households'] += (int) (($household['near_poor_household'] ?? 0) == 1);
        }
        return $stats;
    }

    private function unassignedStats(array $areas, array $households): array
    {
        $total = 0;
        foreach ($households as $household) {
            $lat = $household['latitude'] ?? null;
            $lng = $household['longitude'] ?? null;
            if ($lat === null || $lng === null || $lat === '' || $lng === '') { $total++; continue; }
            $assigned = false;
            foreach ($areas as $area) {
                if ($this->pointInPolygon((float) $lat, (float) $lng, $area['polygon'] ?? [])) { $assigned = true; break; }
            }
            if (!$assigned) $total++;
        }
        return ['households' => $total];
    }

    private function summary(array $areas, array $households): array
    {
        $summary = ['areas' => count($areas), 'households' => count($households), 'citizens' => 0, 'located' => 0, 'unlocated' => 0, 'poor_households' => 0, 'near_poor_households' => 0, 'temporary' => 0, 'away' => 0, 'area_m2' => 0];
        foreach ($households as $household) {
            $summary['citizens'] += (int) ($household['citizens'] ?? 0);
            $summary['temporary'] += (int) ($household['temporary'] ?? 0);
            $summary['away'] += (int) ($household['away'] ?? 0);
            $summary['poor_households'] += (int) (($household['poor_household'] ?? 0) == 1);
            $summary['near_poor_households'] += (int) (($household['near_poor_household'] ?? 0) == 1);
            $hasGps = ($household['latitude'] ?? null) !== null && ($household['longitude'] ?? null) !== null && $household['latitude'] !== '' && $household['longitude'] !== '';
            $summary[$hasGps ? 'located' : 'unlocated']++;
        }
        foreach ($areas as $area) $summary['area_m2'] += (float) ($area['stats']['area_m2'] ?? 0);
        $summary['density'] = $summary['area_m2'] > 0 ? round($summary['citizens'] / ($summary['area_m2'] / 1000000), 2) : 0;
        return $summary;
    }

    private function emptyStats(string $areaCode): array
    {
        return ['area_code' => $areaCode, 'households' => 0, 'citizens' => 0, 'temporary' => 0, 'away' => 0, 'party_members' => 0, 'elderly' => 0, 'children' => 0, 'located' => 0, 'unlocated' => 0, 'poor_households' => 0, 'near_poor_households' => 0, 'area_m2' => 0, 'area_ha' => 0, 'density' => 0];
    }

    private function enrichStats(array $stats, array $area): array
    {
        $areaM2 = $this->polygonAreaM2($area['polygon'] ?? []);
        $stats['area_m2'] = round($areaM2, 2);
        $stats['area_ha'] = round($areaM2 / 10000, 2);
        $stats['density'] = $areaM2 > 0 ? round(((int) ($stats['citizens'] ?? 0)) / ($areaM2 / 1000000), 2) : 0;
        return $stats;
    }

    private function normalizeArea(array $row): array
    {
        $polygonRaw = $row['polygon'] ?? $row['geometry_json'] ?? '[]';
        $polygon = $this->normalizePolygonInput(json_decode((string) $polygonRaw, true));
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'area_code' => (string) $row['area_code'],
            'polygon' => $polygon,
            'geometry' => $polygon,
            'geojson' => $this->toGeoJson($polygon),
            'color' => (string) ($row['color'] ?? '#0f8a4b'),
            'note' => (string) ($row['note'] ?? ''),
            'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
        ];
    }

    private function normalizePolygonInput(mixed $input): array
    {
        if (is_string($input)) $input = json_decode($input, true);
        if (!is_array($input)) return [];
        if (($input['type'] ?? '') === 'Feature') $input = $input['geometry'] ?? [];
        if (($input['type'] ?? '') === 'Polygon') {
            $ring = $input['coordinates'][0] ?? [];
            return array_values(array_filter(array_map(fn($point) => is_array($point) && count($point) >= 2 ? ['lat' => (float) $point[1], 'lng' => (float) $point[0]] : null, $ring)));
        }
        return array_values(array_filter(array_map(function ($point) {
            if (!is_array($point)) return null;
            if (isset($point['lat'], $point['lng'])) return ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']];
            if (isset($point[0], $point[1])) return ['lat' => (float) $point[1], 'lng' => (float) $point[0]];
            return null;
        }, $input)));
    }

    private function toGeoJson(array $polygon): array
    {
        $ring = array_map(fn($point) => [(float) $point['lng'], (float) $point['lat']], $polygon);
        if ($ring && $ring[0] !== $ring[count($ring) - 1]) $ring[] = $ring[0];
        return ['type' => 'Polygon', 'coordinates' => [$ring]];
    }

    private function validGeometry(array $geometry): bool
    {
        if (count($geometry) < 3) return false;
        foreach ($geometry as $point) {
            if (!isset($point['lat'], $point['lng'])) return false;
            $lat = (float) $point['lat']; $lng = (float) $point['lng'];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return false;
        }
        return true;
    }

    private function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);
        if ($count < 3) return false;
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $yi = (float) $polygon[$i]['lat']; $xi = (float) $polygon[$i]['lng'];
            $yj = (float) $polygon[$j]['lat']; $xj = (float) $polygon[$j]['lng'];
            $intersects = (($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 0.0000000001) + $xi);
            if ($intersects) $inside = !$inside;
        }
        return $inside;
    }

    private function polygonAreaM2(array $points): float
    {
        if (count($points) < 3) return 0.0;
        $earthRadius = 6378137.0;
        $area = 0.0;
        $count = count($points);
        for ($i = 0; $i < $count; $i++) {
            $p1 = $points[$i]; $p2 = $points[($i + 1) % $count];
            $lat1 = deg2rad((float) ($p1['lat'] ?? 0));
            $lat2 = deg2rad((float) ($p2['lat'] ?? 0));
            $lng1 = deg2rad((float) ($p1['lng'] ?? 0));
            $lng2 = deg2rad((float) ($p2['lng'] ?? 0));
            $area += ($lng2 - $lng1) * (2 + sin($lat1) + sin($lat2));
        }
        return abs($area * $earthRadius * $earthRadius / 2.0);
    }

    private function trackedExecute(string $sql, array $params): int
    {
        $this->lastSql = $sql;
        $this->lastParams = $params;
        return $this->execute($sql, $params);
    }

    private function trackedInsert(string $sql, array $params): int
    {
        $this->lastSql = $sql;
        $this->lastParams = $params;
        return $this->insert($sql, $params);
    }

    private function logSqlFailure(string $action, array $request, \Throwable $e): void
    {
        $payload = [
            'time' => date('c'),
            'action' => $action,
            'sql' => $this->lastSql,
            'params' => array_keys($this->lastParams),
            'request_keys' => array_keys($request),
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ];
        error_log('[GIS_SQL_ERROR] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
