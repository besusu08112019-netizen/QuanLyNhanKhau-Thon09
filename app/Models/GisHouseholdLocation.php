<?php

namespace App\Models;

use App\Core\BaseModel;

final class GisHouseholdLocation extends BaseModel
{
    private ?PopulationStatistics $statistics = null;

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

    public function lightMarkers(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->markerConditions($filters);
        $limit = $this->markerLimit($filters, 2000);
        $photoSql = $this->householdPhotoSql();
        $rows = $this->fetchAll(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.latitude, h.longitude, h.location_accuracy, h.location_source, h.location_updated_at,
                ' . $photoSql['thumbnail'] . ' AS thumbnail_file_id,
                ' . $photoSql['count'] . ' AS gallery_count
             FROM households h
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
                WHERE ' . $this->statistics()->citizenCondition('c') . '
                GROUP BY c.household_id
             ) cm ON cm.household_id = h.id
             WHERE ' . $this->statistics()->householdCondition('h') . $where . '
               AND h.latitude IS NOT NULL AND h.latitude <> "" AND h.longitude IS NOT NULL AND h.longitude <> ""
             ORDER BY h.id ASC
             LIMIT ' . $limit,
            $params
        );
        return [
            'items' => array_map(fn(array $row) => [
                'id' => (int) $row['id'],
                'household_code' => (string) ($row['household_code'] ?? ''),
                'head_citizen_name' => (string) ($row['head_citizen_name'] ?? ''),
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'located' => true,
                'location_accuracy' => $row['location_accuracy'] !== null ? (int) $row['location_accuracy'] : null,
                'location_source' => (string) ($row['location_source'] ?? ''),
                'location_updated_at' => $row['location_updated_at'] ?? null,
                'thumbnail_file_id' => $row['thumbnail_file_id'] !== null ? (int) $row['thumbnail_file_id'] : null,
                'photo_file_id' => $row['thumbnail_file_id'] !== null ? (int) $row['thumbnail_file_id'] : null,
                'thumbnail_url' => $row['thumbnail_file_id'] !== null ? '/api/files/' . (int) $row['thumbnail_file_id'] . '/preview' : null,
                'photo_url' => $row['thumbnail_file_id'] !== null ? '/api/files/' . (int) $row['thumbnail_file_id'] . '/preview' : null,
                'household_photo_url' => $row['thumbnail_file_id'] !== null ? '/api/files/' . (int) $row['thumbnail_file_id'] . '/preview' : null,
                'gallery_count' => (int) ($row['gallery_count'] ?? 0),
            ], $rows),
            'total' => count($rows),
            'summary' => $this->summary($filters),
            'generatedAt' => date('c'),
        ];
    }

    public function detail(int $householdId): array
    {
        $this->ensureSchema();
        $photoSql = $this->householdPhotoSql();
        $row = $this->fetchOne(
            'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code, h.latitude, h.longitude, h.location_accuracy, h.location_source, h.location_updated_at,
                    COALESCE(v.total_members,0) AS total_members, COALESCE(v.at_home_count,0) AS at_home_count, COALESCE(v.away_count,0) AS away_count,
                    COALESCE(cm.temporary_count,0) AS temporary_count,
                    ' . $photoSql['thumbnail'] . ' AS thumbnail_file_id,
                    ' . $photoSql['count'] . ' AS gallery_count
             FROM households h
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             LEFT JOIN (
                SELECT c.household_id,
                    SUM(CASE WHEN c.residency_status = "TEMPORARY" THEN 1 ELSE 0 END) AS temporary_count
                FROM citizens c
                WHERE ' . $this->statistics()->citizenCondition('c') . '
                GROUP BY c.household_id
             ) cm ON cm.household_id = h.id
             WHERE h.id = :id AND ' . $this->statistics()->householdCondition('h'),
            ['id' => $householdId]
        );
        if (!$row) throw new \RuntimeException('Không tìm thấy hộ gia đình');
        $members = $this->fetchAll('SELECT id, citizen_code, full_name, relationship, phone, residency_status, presence_status FROM citizens WHERE household_id = :id AND status <> "DELETED" ORDER BY CASE WHEN relationship = "Chủ hộ" THEN 0 ELSE 1 END, full_name LIMIT 200', ['id' => $householdId]);
        $business = [];
        if ($this->tableExists('household_business')) {
            $business = $this->fetchAll('SELECT id, business_name, business_type, economic_type, production_sector, business_sector, business_scale, worker_count, status FROM household_business WHERE household_id = :id AND status <> "DELETED" ORDER BY id ASC', ['id' => $householdId]);
        }
        $livestock = [];
        if ($this->tableExists('livestock')) {
            $livestock = $this->fetchAll('SELECT id, animal_type, breed, quantity, vaccinated, status FROM livestock WHERE household_id = :id AND status <> "DELETED" ORDER BY animal_type ASC, id ASC', ['id' => $householdId]);
        }
        return [
            'household' => [
                'id' => (int) $row['id'],
                'household_code' => (string) $row['household_code'],
                'head_citizen_name' => (string) $row['head_citizen_name'],
                'address' => (string) ($row['address'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'area_code' => (string) ($row['area_code'] ?? ''),
                'latitude' => $row['latitude'] !== null && $row['latitude'] !== '' ? (float) $row['latitude'] : null,
                'longitude' => $row['longitude'] !== null && $row['longitude'] !== '' ? (float) $row['longitude'] : null,
                'location_accuracy' => $row['location_accuracy'] !== null ? (int) $row['location_accuracy'] : null,
                'location_source' => (string) ($row['location_source'] ?? ''),
                'location_updated_at' => $row['location_updated_at'] ?? null,
                'total_members' => (int) ($row['total_members'] ?? 0),
                'at_home_count' => (int) ($row['at_home_count'] ?? 0),
                'away_count' => (int) ($row['away_count'] ?? 0),
                'temporary_count' => (int) ($row['temporary_count'] ?? 0),
                'thumbnail_file_id' => $row['thumbnail_file_id'] !== null ? (int) $row['thumbnail_file_id'] : null,
                'photo_file_id' => $row['thumbnail_file_id'] !== null ? (int) $row['thumbnail_file_id'] : null,
                'thumbnail_url' => $row['thumbnail_file_id'] !== null ? '/api/files/' . (int) $row['thumbnail_file_id'] . '/preview' : null,
                'photo_url' => $row['thumbnail_file_id'] !== null ? '/api/files/' . (int) $row['thumbnail_file_id'] . '/preview' : null,
                'household_photo_url' => $row['thumbnail_file_id'] !== null ? '/api/files/' . (int) $row['thumbnail_file_id'] . '/preview' : null,
                'gallery_count' => (int) ($row['gallery_count'] ?? 0),
            ],
            'members' => array_map(fn($m) => [
                'id' => (int) $m['id'], 'citizen_code' => (string) ($m['citizen_code'] ?? ''), 'full_name' => (string) ($m['full_name'] ?? ''), 'relationship' => (string) ($m['relationship'] ?? ''), 'phone' => (string) ($m['phone'] ?? ''), 'residency_status' => (string) ($m['residency_status'] ?? ''), 'presence_status' => (string) ($m['presence_status'] ?? ''),
            ], $members),
            'business' => array_map(fn($b) => [
                'id' => (int) $b['id'], 'business_name' => (string) ($b['business_name'] ?: $b['economic_type'] ?: 'Hoạt động kinh tế'), 'business_type' => (string) ($b['business_type'] ?? ''), 'economic_type' => (string) ($b['economic_type'] ?? ''), 'sector' => (string) (($b['production_sector'] ?? '') ?: ($b['business_sector'] ?? '')), 'business_scale' => (string) ($b['business_scale'] ?? ''), 'worker_count' => (int) ($b['worker_count'] ?? 0), 'status' => (string) ($b['status'] ?? ''),
            ], $business),
            'livestock' => array_map(fn($l) => [
                'id' => (int) $l['id'], 'animal_type' => (string) ($l['animal_type'] ?? ''), 'breed' => (string) ($l['breed'] ?? ''), 'quantity' => (int) ($l['quantity'] ?? 0), 'vaccinated' => (int) ($l['vaccinated'] ?? 0) === 1, 'status' => (string) ($l['status'] ?? ''),
            ], $livestock),
            'vehicles' => [],
            'contributions' => [],
            'timeline' => [],
            'generatedAt' => date('c'),
        ];
    }

    public function markers(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->markerConditions($filters);
        $limit = $this->markerLimit($filters, 1000);
        $photoSql = $this->householdPhotoSql();
        $hasBusinessTable = $this->tableExists('household_business');
        $businessJoin = $hasBusinessTable ? ' LEFT JOIN (
                SELECT x.household_id,
                       GROUP_CONCAT(COALESCE(NULLIF(x.business_name,""),"Chưa đặt tên") ORDER BY x.id SEPARATOR ", ") AS business_names,
                       GROUP_CONCAT(CONCAT_WS(CHAR(31),
                           x.id,
                           COALESCE(REPLACE(REPLACE(x.business_name, CHAR(30), " "), CHAR(31), " "), ""),
                           COALESCE(x.business_type,""),
                           CASE x.business_type WHEN "PRODUCTION" THEN "Ho san xuat" WHEN "BUSINESS" THEN "Ho kinh doanh" WHEN "BOTH" THEN "Ho san xuat va kinh doanh" ELSE "Ho dan" END,
                           COALESCE(NULLIF(x.production_sector,""), NULLIF(x.business_sector,""), ""),
                           COALESCE(REPLACE(REPLACE(x.owner_name, CHAR(30), " "), CHAR(31), " "), ""),
                           COALESCE(REPLACE(REPLACE(x.phone, CHAR(30), " "), CHAR(31), " "), "")
                       ) ORDER BY x.id SEPARATOR "|~|") AS business_activities_json,
                       CASE
                           WHEN SUM(x.business_type = "BOTH") > 0 THEN "BOTH"
                           WHEN SUM(x.business_type = "PRODUCTION") > 0 AND SUM(x.business_type = "BUSINESS") > 0 THEN "BOTH"
                           WHEN SUM(x.business_type = "PRODUCTION") > 0 THEN "PRODUCTION"
                           WHEN SUM(x.business_type = "BUSINESS") > 0 THEN "BUSINESS"
                           ELSE "RESIDENT"
                       END AS business_marker_type
                FROM household_business x
                WHERE x.status <> "DELETED"
                GROUP BY x.household_id
             ) hb ON hb.household_id = h.id' : '';
        $businessSelect = $hasBusinessTable ? ', hb.business_names AS business_name, hb.business_marker_type, hb.business_activities_json' : ', NULL AS business_name, NULL AS business_marker_type, NULL AS business_activities_json';
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
                ' . $photoSql['thumbnail'] . ' AS thumbnail_file_id,
                ' . $photoSql['count'] . ' AS gallery_count
                ' . $businessSelect . '
             FROM households h
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             ' . $businessJoin . '
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
                WHERE ' . $this->statistics()->citizenCondition('c') . '
                GROUP BY c.household_id
             ) cm ON cm.household_id = h.id
             WHERE ' . $this->statistics()->householdCondition('h') . $where . '
             ORDER BY h.household_code ASC
             LIMIT ' . $limit,
            $params
        );

        return [
            'items' => array_map(fn(array $row) => $this->normalizeMarker($row), $rows),
            'total' => count($rows),
            'summary' => $this->summary($filters),
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


    private function summary(array $filters): array
    {
        $this->ensureSchema();
        $summaryFilters = $filters;
        unset($summaryFilters['north'], $summaryFilters['south'], $summaryFilters['east'], $summaryFilters['west']);
        [$where, $params] = $this->markerConditions(array_merge($summaryFilters, ['located' => '']));
        $locatedExpr = "h.latitude IS NOT NULL AND h.latitude <> '' AND h.longitude IS NOT NULL AND h.longitude <> ''";
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS households,
                COALESCE(SUM(CASE WHEN $locatedExpr THEN 1 ELSE 0 END), 0) AS located
             FROM households h
             LEFT JOIN v_household_member_counts v ON v.household_id = h.id
             LEFT JOIN (
                SELECT c.household_id,
                    SUM(CASE WHEN c.party_member = 1 THEN 1 ELSE 0 END) AS party_members,
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) < 16 THEN 1 ELSE 0 END) AS children_count,
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) >= 60 THEN 1 ELSE 0 END) AS elderly_count,
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) BETWEEN 16 AND 59 THEN 1 ELSE 0 END) AS working_age_count,
                    SUM(CASE WHEN c.residency_status = \"PERMANENT\" THEN 1 ELSE 0 END) AS permanent_count,
                    SUM(CASE WHEN c.residency_status = \"TEMPORARY\" THEN 1 ELSE 0 END) AS temporary_count,
                    SUM(CASE WHEN c.employed = 1 OR c.freelance_labor = 1 OR c.out_province_labor = 1 OR c.foreign_labor = 1 THEN 1 ELSE 0 END) AS labor_count
                FROM citizens c
                WHERE " . $this->statistics()->citizenCondition('c') . "
                GROUP BY c.household_id
             ) cm ON cm.household_id = h.id
             WHERE " . $this->statistics()->householdCondition('h') . $where,
            $params
        ) ?: [];
        $households = (int) ($row['households'] ?? 0);
        $located = (int) ($row['located'] ?? 0);
        return [
            'households' => $households,
            'located' => $located,
            'unlocated' => max(0, $households - $located),
        ];
    }
    private function householdPhotoSql(): array
    {
        if (!$this->tableExists('file_attachments')) {
            return [
                'thumbnail' => 'NULL',
                'count' => '0',
            ];
        }

        $columns = $this->existingColumns('file_attachments', [
            'id', 'module', 'entity_type', 'entity_id', 'status', 'file_type', 'mime_type', 'profile_section', 'category',
        ]);
        if (!in_array('id', $columns, true) || !in_array('entity_id', $columns, true)) {
            return [
                'thumbnail' => 'NULL',
                'count' => '0',
            ];
        }

        $where = ['f.entity_id = h.id'];
        if (in_array('entity_type', $columns, true) && in_array('module', $columns, true)) {
            $where[] = 'COALESCE(f.entity_type, f.module) = "household"';
        } elseif (in_array('entity_type', $columns, true)) {
            $where[] = 'f.entity_type = "household"';
        } elseif (in_array('module', $columns, true)) {
            $where[] = 'f.module = "household"';
        }
        if (in_array('status', $columns, true)) {
            $where[] = '(f.status IS NULL OR f.status <> "DELETED")';
        }

        $imageParts = [];
        if (in_array('file_type', $columns, true)) {
            $imageParts[] = 'f.file_type IN ("PHOTO","IMAGE")';
        }
        if (in_array('mime_type', $columns, true)) {
            $imageParts[] = 'f.mime_type LIKE "image/%"';
        }
        if ($imageParts) {
            $where[] = '(' . implode(' OR ', $imageParts) . ')';
        }

        $sectionExpr = $this->photoSectionExpression($columns);
        $whereSql = implode(' AND ', $where);
        return [
            'thumbnail' => '(SELECT f.id FROM file_attachments f WHERE ' . $whereSql . ' ORDER BY CASE ' . $sectionExpr . ' WHEN "front_house" THEN 0 WHEN "inside_house" THEN 1 ELSE 2 END, f.id DESC LIMIT 1)',
            'count' => '(SELECT COUNT(1) FROM file_attachments f WHERE ' . $whereSql . ')',
        ];
    }

    private function photoSectionExpression(array $columns): string
    {
        $parts = [];
        foreach (['profile_section', 'category'] as $column) {
            if (in_array($column, $columns, true)) {
                $parts[] = 'f.' . $column;
            }
        }
        return $parts ? 'COALESCE(' . implode(', ', $parts) . ', "")' : '""';
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }

    private function statistics(): PopulationStatistics
    {
        return $this->statistics ??= new PopulationStatistics();
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
        $located = (string) ($filters['located'] ?? '');
        if ($located === '1') {
            $where .= " AND h.latitude IS NOT NULL AND h.latitude <> '' AND h.longitude IS NOT NULL AND h.longitude <> ''";
        } elseif ($located === '0') {
            $where .= " AND (h.latitude IS NULL OR h.latitude = '' OR h.longitude IS NULL OR h.longitude = '')";
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $businessSearch = $this->tableExists('household_business') ? ' OR EXISTS (SELECT 1 FROM household_business hbq WHERE hbq.household_id = h.id AND hbq.status <> "DELETED" AND (hbq.business_name LIKE :search OR hbq.economic_type LIKE :search OR hbq.production_sector LIKE :search OR hbq.business_sector LIKE :search OR hbq.owner_name LIKE :search OR hbq.phone LIKE :search))' : '';
            $where .= ' AND (h.household_code LIKE :search OR h.head_citizen_name LIKE :search OR h.address LIKE :search OR h.phone LIKE :search OR EXISTS (SELECT 1 FROM citizens cs WHERE cs.household_id = h.id AND cs.status <> "DELETED" AND (cs.full_name LIKE :search OR cs.identity_number LIKE :search))' . $businessSearch . ')';
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

    private function markerLimit(array $filters, int $default): int
    {
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : $default;
        return min(max($limit, 1), $default);
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
            'latitude' => ($row['latitude'] !== null && $row['latitude'] !== '') ? (float) $row['latitude'] : null,
            'longitude' => ($row['longitude'] !== null && $row['longitude'] !== '') ? (float) $row['longitude'] : null,
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
            'gps' => ($row['latitude'] !== null && $row['latitude'] !== '' && $row['longitude'] !== null && $row['longitude'] !== '') ? trim((string) $row['latitude']) . ', ' . trim((string) $row['longitude']) : '',
            'business_name' => (string) ($row['business_name'] ?? ''),
            'business_type_code' => (string) ($row['business_marker_type'] ?? ''),
            'business_type_label' => $this->businessTypeLabel($row['business_marker_type'] ?? ''),
            'business_sector' => trim((string) ($row['production_sector'] ?? '')) ?: trim((string) ($row['business_sector'] ?? '')),
            'business_owner_name' => (string) ($row['business_owner_name'] ?? ''),
            'business_phone' => (string) ($row['business_phone'] ?? ''),
            'business_marker' => $this->businessMarkerKey($row['business_marker_type'] ?? ''),
        ];
    }


    private function normalizeBusinessActivities(mixed $encoded): array
    {
        $text = (string) ($encoded ?? '');
        if ($text === '') return [];
        $items = [];
        foreach (explode(chr(30), $text) as $row) {
            $parts = explode(chr(31), $row);
            if (count($parts) < 7) continue;
            $type = strtoupper((string) ($parts[2] ?? ''));
            $items[] = [
                'id' => (int) ($parts[0] ?? 0),
                'business_name' => (string) ($parts[1] ?? ''),
                'business_type' => $type,
                'business_type_label' => (string) ($parts[3] ?? $this->businessTypeLabel($type)),
                'sector' => (string) ($parts[4] ?? ''),
                'owner_name' => (string) ($parts[5] ?? ''),
                'phone' => (string) ($parts[6] ?? ''),
            ];
        }
        return $items;
    }

    private function businessTypeLabel(mixed $value): string
    {
        return ['RESIDENT' => 'Hộ dân', 'PRODUCTION' => 'Hộ sản xuất', 'BUSINESS' => 'Hộ kinh doanh', 'BOTH' => 'Hộ sản xuất và kinh doanh'][strtoupper((string) $value)] ?? '';
    }

    private function businessMarkerKey(mixed $value): string
    {
        return ['PRODUCTION' => 'production', 'BUSINESS' => 'business', 'BOTH' => 'production_business'][strtoupper((string) $value)] ?? 'household';
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
        return 'Hộ thường';
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
