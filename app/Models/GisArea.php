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
        $stats = $this->areaStats();
        $items = [];
        foreach ($areas as $area) {
            $code = (string) ($area['area_code'] ?? '');
            $item = $this->normalizeArea($area);
            $item['stats'] = $this->enrichStats($stats[$code] ?? $this->emptyStats($code), $item);
            $items[] = $item;
        }
        return [
            'areas' => $items,
            'unassigned' => $this->unassignedStats(),
            'summary' => $this->summary($stats, $items),
        ];
    }

    public function save(array $data, int $userId): array
    {
        $this->ensureSchema();
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $areaCode = trim((string) ($data['area_code'] ?? $data['areaCode'] ?? ''));
        $polygon = $data['polygon'] ?? $data['geometry'] ?? [];
        if ($name === '') throw new \RuntimeException('Tên khu vực là bắt buộc');
        if ($areaCode === '') throw new \RuntimeException('Mã khu vực là bắt buộc');
        if (!$this->validGeometry($polygon)) throw new \RuntimeException('Ranh giới khu vực chưa hợp lệ');

        $polygonJson = json_encode($polygon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params = [
            'name' => $name,
            'area_code' => $areaCode,
            'polygon' => $polygonJson,
            'geometry_json' => $polygonJson,
            'color' => trim((string) ($data['color'] ?? '#0f8a4b')) ?: '#0f8a4b',
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'updated_by' => $userId,
        ];

        try {
            if ($id > 0 && $this->fetchOne('SELECT id FROM gis_areas WHERE id=:id AND status <> "DELETED"', ['id' => $id])) {
                $params['id'] = $id;
                $sql = 'UPDATE gis_areas SET name=:name, area_code=:area_code, polygon=:polygon, geometry_json=:geometry_json, color=:color, note=:note, updated_by=:updated_by, updated_at=NOW() WHERE id=:id';
                $this->trackedExecute($sql, $params);
            } else {
                $params['created_by'] = $userId;
                $sql = 'INSERT INTO gis_areas (name, area_code, polygon, geometry_json, color, note, created_by, updated_by) VALUES (:name,:area_code,:polygon,:geometry_json,:color,:note,:created_by,:updated_by)';
                $id = $this->trackedInsert($sql, $params);
            }
        } catch (\Throwable $e) {
            $this->logSqlFailure('save', $data, $e);
            throw $e;
        }

        return $this->find($id) ?: [];
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM gis_areas WHERE id=:id AND status <> "DELETED"', ['id' => $id]);
        if (!$row) return null;
        $stats = $this->areaStats();
        $item = $this->normalizeArea($row);
        $item['stats'] = $this->enrichStats($stats[$item['area_code']] ?? $this->emptyStats($item['area_code']), $item);
        return $item;
    }

    public function delete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy khu vực bản đồ');
        $sql = 'UPDATE gis_areas SET status="DELETED", deleted_by=:deleted_by, deleted_at=NOW() WHERE id=:id';
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
            polygon LONGTEXT NULL,
            geometry_json LONGTEXT NULL,
            color VARCHAR(20) DEFAULT "#0f8a4b",
            note TEXT NULL,
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

    private function areaStats(): array
    {
        $rows = $this->fetchAll('SELECT COALESCE(NULLIF(h.area_code,""), "Chưa phân khu") AS area_code,
            COUNT(DISTINCT h.id) AS households,
            COUNT(c.id) AS citizens,
            COALESCE(SUM(CASE WHEN c.residency_status="TEMPORARY" THEN 1 ELSE 0 END),0) AS temporary,
            COALESCE(SUM(CASE WHEN c.presence_status="AWAY" THEN 1 ELSE 0 END),0) AS away,
            COALESCE(SUM(CASE WHEN c.party_member=1 THEN 1 ELSE 0 END),0) AS party_members,
            COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 60 THEN 1 ELSE 0 END),0) AS elderly,
            COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 5 THEN 1 ELSE 0 END),0) AS children,
            COALESCE(SUM(CASE WHEN h.latitude IS NOT NULL AND h.longitude IS NOT NULL THEN 1 ELSE 0 END),0) AS located,
            COALESCE(SUM(CASE WHEN h.latitude IS NULL OR h.longitude IS NULL THEN 1 ELSE 0 END),0) AS unlocated,
            COALESCE(SUM(CASE WHEN h.poor_household=1 THEN 1 ELSE 0 END),0) AS poor_households,
            COALESCE(SUM(CASE WHEN h.near_poor_household=1 THEN 1 ELSE 0 END),0) AS near_poor_households
            FROM households h LEFT JOIN citizens c ON c.household_id=h.id AND c.status <> "DELETED"
            WHERE h.status <> "DELETED" GROUP BY area_code ORDER BY area_code');
        $stats = [];
        foreach ($rows as $row) $stats[(string) $row['area_code']] = $this->castStats($row);
        return $stats;
    }

    private function unassignedStats(): array
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS households FROM households h WHERE h.status <> "DELETED" AND NOT EXISTS (SELECT 1 FROM gis_areas g WHERE g.status <> "DELETED" AND g.area_code = COALESCE(NULLIF(h.area_code,""), "Chưa phân khu"))') ?: ['households' => 0];
        return ['households' => (int) ($row['households'] ?? 0)];
    }

    private function summary(array $stats, array $areas): array
    {
        $summary = ['areas' => count($areas), 'households' => 0, 'citizens' => 0, 'located' => 0, 'unlocated' => 0, 'poor_households' => 0, 'near_poor_households' => 0, 'temporary' => 0, 'away' => 0, 'area_m2' => 0];
        foreach ($stats as $row) {
            foreach (['households','citizens','located','unlocated','poor_households','near_poor_households','temporary','away'] as $key) $summary[$key] += (int) ($row[$key] ?? 0);
        }
        foreach ($areas as $area) $summary['area_m2'] += (float) ($area['area_m2'] ?? 0);
        $summary['density'] = $summary['area_m2'] > 0 ? round($summary['citizens'] / ($summary['area_m2'] / 1000000), 2) : 0;
        return $summary;
    }

    private function castStats(array $row): array
    {
        return [
            'area_code' => (string) ($row['area_code'] ?? ''),
            'households' => (int) ($row['households'] ?? 0),
            'citizens' => (int) ($row['citizens'] ?? 0),
            'temporary' => (int) ($row['temporary'] ?? 0),
            'away' => (int) ($row['away'] ?? 0),
            'party_members' => (int) ($row['party_members'] ?? 0),
            'elderly' => (int) ($row['elderly'] ?? 0),
            'children' => (int) ($row['children'] ?? 0),
            'located' => (int) ($row['located'] ?? 0),
            'unlocated' => (int) ($row['unlocated'] ?? 0),
            'poor_households' => (int) ($row['poor_households'] ?? 0),
            'near_poor_households' => (int) ($row['near_poor_households'] ?? 0),
        ];
    }

    private function enrichStats(array $stats, array $area): array
    {
        $areaM2 = $this->polygonAreaM2($area['polygon'] ?? $area['geometry'] ?? []);
        $stats['area_m2'] = round($areaM2, 2);
        $stats['area_ha'] = round($areaM2 / 10000, 2);
        $stats['density'] = $areaM2 > 0 ? round(((int) ($stats['citizens'] ?? 0)) / ($areaM2 / 1000000), 2) : 0;
        return $stats;
    }

    private function emptyStats(string $areaCode): array
    {
        return ['area_code' => $areaCode, 'households' => 0, 'citizens' => 0, 'temporary' => 0, 'away' => 0, 'party_members' => 0, 'elderly' => 0, 'children' => 0, 'located' => 0, 'unlocated' => 0, 'poor_households' => 0, 'near_poor_households' => 0, 'area_m2' => 0, 'area_ha' => 0, 'density' => 0];
    }

    private function normalizeArea(array $row): array
    {
        $polygonRaw = $row['polygon'] ?? $row['geometry_json'] ?? '[]';
        $polygon = json_decode((string) $polygonRaw, true);
        $polygon = is_array($polygon) ? $polygon : [];
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'area_code' => (string) $row['area_code'],
            'polygon' => $polygon,
            'geometry' => $polygon,
            'color' => (string) ($row['color'] ?? '#0f8a4b'),
            'note' => (string) ($row['note'] ?? ''),
            'updated_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
        ];
    }

    private function validGeometry(mixed $geometry): bool
    {
        if (!is_array($geometry) || count($geometry) < 3) return false;
        foreach ($geometry as $point) {
            if (!is_array($point) || !isset($point['lat'], $point['lng'])) return false;
            $lat = (float) $point['lat']; $lng = (float) $point['lng'];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return false;
        }
        return true;
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
        $this->lastSql = $sql; $this->lastParams = $params;
        return $this->execute($sql, $params);
    }

    private function trackedInsert(string $sql, array $params): int
    {
        $this->lastSql = $sql; $this->lastParams = $params;
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
