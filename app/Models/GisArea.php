<?php

namespace App\Models;

use App\Core\BaseModel;

final class GisArea extends BaseModel
{
    public function all(): array
    {
        $this->ensureSchema();
        $areas = $this->fetchAll('SELECT * FROM gis_areas WHERE status <> "DELETED" ORDER BY sort_order, name');
        $stats = $this->areaStats();
        $items = [];
        foreach ($areas as $area) {
            $code = (string) ($area['area_code'] ?? '');
            $item = $this->normalizeArea($area);
            $item['stats'] = $stats[$code] ?? $this->emptyStats($code);
            $items[] = $item;
        }
        return [
            'areas' => $items,
            'unassigned' => $this->unassignedStats(),
            'summary' => $this->summary($stats),
        ];
    }

    public function save(array $data, int $userId): array
    {
        $this->ensureSchema();
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $areaCode = trim((string) ($data['area_code'] ?? $data['areaCode'] ?? ''));
        $geometry = $data['geometry'] ?? [];
        if ($name === '') throw new \RuntimeException('Tên khu vực là bắt buộc');
        if ($areaCode === '') $areaCode = $this->slugAreaCode($name);
        if (!$this->validGeometry($geometry)) throw new \RuntimeException('Ranh giới khu vực chưa hợp lệ');
        $params = [
            'name' => $name,
            'area_code' => $areaCode,
            'geometry_json' => json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'color' => trim((string) ($data['color'] ?? '#0f8a4b')) ?: '#0f8a4b',
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'user' => $userId,
        ];
        if ($id > 0 && $this->fetchOne('SELECT id FROM gis_areas WHERE id=:id AND status <> "DELETED"', ['id' => $id])) {
            $params['id'] = $id;
            $this->execute('UPDATE gis_areas SET name=:name, area_code=:area_code, geometry_json=:geometry_json, color=:color, note=:note, updated_by=:user, updated_at=NOW() WHERE id=:id', $params);
        } else {
            $id = $this->insert('INSERT INTO gis_areas (name, area_code, geometry_json, color, note, created_by, updated_by) VALUES (:name,:area_code,:geometry_json,:color,:note,:user,:user)', $params);
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
        $item['stats'] = $stats[$item['area_code']] ?? $this->emptyStats($item['area_code']);
        return $item;
    }

    public function delete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy khu vực bản đồ');
        $this->execute('UPDATE gis_areas SET status="DELETED", deleted_by=:user, deleted_at=NOW() WHERE id=:id', ['id' => $id, 'user' => $userId]);
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
            geometry_json LONGTEXT NOT NULL,
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

        $columns = [
            'latitude' => 'DECIMAL(10,7) NULL',
            'longitude' => 'DECIMAL(10,7) NULL',
            'google_map_url' => 'VARCHAR(255) NULL',
            'location_note' => 'TEXT NULL',
            'location_updated_at' => 'DATETIME NULL',
            'location_updated_by' => 'INT NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('households', $column)) {
                $this->execute('ALTER TABLE households ADD COLUMN ' . $column . ' ' . $definition);
            }
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
            COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 5 THEN 1 ELSE 0 END),0) AS children
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

    private function summary(array $stats): array
    {
        $households = 0; $citizens = 0;
        foreach ($stats as $row) { $households += (int) $row['households']; $citizens += (int) $row['citizens']; }
        return ['areas' => count($stats), 'households' => $households, 'citizens' => $citizens];
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
        ];
    }

    private function emptyStats(string $areaCode): array
    {
        return ['area_code' => $areaCode, 'households' => 0, 'citizens' => 0, 'temporary' => 0, 'away' => 0, 'party_members' => 0, 'elderly' => 0, 'children' => 0];
    }

    private function normalizeArea(array $row): array
    {
        $geometry = json_decode((string) ($row['geometry_json'] ?? '[]'), true);
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'area_code' => (string) $row['area_code'],
            'geometry' => is_array($geometry) ? $geometry : [],
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

    private function slugAreaCode(string $name): string
    {
        $text = mb_strtolower(trim($name));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) $text = $converted;
        $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
        return strtoupper($text ?: 'AREA-' . date('His'));
    }
}
