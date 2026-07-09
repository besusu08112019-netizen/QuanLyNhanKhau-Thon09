<?php

namespace App\Models;

use App\Core\BaseModel;

final class OperationCenter extends BaseModel
{
    public function notifications(array $filters = []): array
    {
        return $this->safePayload('notifications', function () use ($filters) {
            $now = date('c');
            $items = [
                $this->notice('citizen_missing_photo', 'Hồ sơ công dân chưa có ảnh', $this->missingCitizenPhotoCount($filters), 'high', 'persons', 'Mở nhân khẩu', $now),
                $this->notice('citizen_missing_identity', 'Hồ sơ công dân thiếu CCCD', $this->missingCitizenFieldCount($filters, 'identity_number'), 'high', 'persons', 'Mở nhân khẩu', $now),
                $this->notice('citizen_missing_birth', 'Hồ sơ thiếu ngày sinh', $this->missingCitizenFieldCount($filters, 'date_of_birth'), 'medium', 'persons', 'Mở nhân khẩu', $now),
                $this->notice('digital_profile_incomplete', 'Hồ sơ số chưa hoàn thiện', $this->incompleteDigitalProfileCount($filters), 'medium', 'households', 'Mở hồ sơ số', $now),
                $this->notice('household_missing_gps', 'Hộ chưa định vị GPS', $this->missingGpsCount($filters), 'high', 'gis', 'Mở GIS', $now),
                $this->notice('household_missing_photo', 'Hộ chưa có ảnh', $this->missingHouseholdPhotoCount($filters), 'medium', 'households', 'Mở hộ', $now),
                $this->notice('movement_new', 'Có biến động dân cư mới', $this->recentMovementCount(''), 'low', 'movements', 'Mở biến động', $now),
                $this->notice('citizen_new', 'Có nhân khẩu mới', $this->recentCitizenCount(), 'low', 'persons', 'Mở nhân khẩu', $now),
                $this->notice('household_new', 'Có hộ mới', $this->recentHouseholdCount(), 'low', 'households', 'Mở hộ', $now),
                $this->notice('temporary_residence_new', 'Có tạm trú mới', $this->recentMovementCount('TEMPORARY_RESIDENCE'), 'medium', 'temporaryResidence', 'Mở tạm trú', $now),
                $this->notice('temporary_absence_new', 'Có tạm vắng mới', $this->recentMovementCount('TEMPORARY_ABSENCE'), 'medium', 'temporaryAbsence', 'Mở tạm vắng', $now),
            ];
            return [
                'items' => array_values(array_filter($items, fn($item) => (int) $item['count'] > 0)),
                'generatedAt' => $now,
            ];
        }, ['items' => [], 'generatedAt' => date('c')]);
    }

    public function tasks(array $filters = []): array
    {
        return $this->safePayload('tasks', function () use ($filters) {
            $items = [
                $this->task('missing_gps', 'Định vị GPS còn thiếu', $this->missingGpsCount($filters), 'high', 'gis'),
                $this->task('missing_photo', 'Hồ sơ thiếu ảnh', $this->missingCitizenPhotoCount($filters) + $this->missingHouseholdPhotoCount($filters), 'high', 'persons'),
                $this->task('missing_documents', 'Hồ sơ thiếu giấy tờ', $this->missingCitizenDocumentsCount($filters) + $this->missingHouseholdDocumentsCount($filters), 'medium', 'households'),
                $this->task('needs_update', 'Hồ sơ cần cập nhật', $this->missingCitizenFieldCount($filters, 'date_of_birth') + $this->missingCitizenFieldCount($filters, 'identity_number'), 'medium', 'persons'),
                $this->task('pending_movements', 'Biến động chưa xác nhận', $this->pendingMovementCount(), 'medium', 'movements'),
            ];
            return ['items' => $items, 'generatedAt' => date('c')];
        }, ['items' => [], 'generatedAt' => date('c')]);
    }

    public function search(string $query, int $limit = 20): array
    {
        return $this->safePayload('search', function () use ($query, $limit) {
            $query = trim($query);
            if ($query === '') return ['items' => [], 'total' => 0];
            $limit = min(max($limit, 5), 40);
            $q = '%' . $query . '%';
            $items = [];
            $hasLat = $this->columnExists('households', 'latitude');
            $hasLng = $this->columnExists('households', 'longitude');
            $gpsSelect = $hasLat ? ', h.latitude' : ', NULL AS latitude';
            $gpsSelect .= $hasLng ? ', h.longitude' : ', NULL AS longitude';
            $gpsWhere = ($hasLat && $hasLng) ? ' OR h.latitude LIKE :q OR h.longitude LIKE :q' : '';
            $households = $this->fetchAll(
                'SELECT h.id, h.household_code, h.head_citizen_name, h.address, h.phone, h.area_code' . $gpsSelect . '
                 FROM households h
                 WHERE ' . $this->activeHouseholdCondition('h') . ' AND (h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR h.address LIKE :q OR h.phone LIKE :q OR h.area_code LIKE :q' . $gpsWhere . ')
                 ORDER BY h.household_code ASC LIMIT ' . $limit,
                ['q' => $q]
            );
            foreach ($households as $row) {
                $items[] = [
                    'type' => 'household',
                    'id' => (int) $row['id'],
                    'title' => $row['household_code'] ?: 'Hộ gia đình',
                    'subtitle' => trim(($row['head_citizen_name'] ?? '') . ' - ' . ($row['address'] ?? ''), ' -'),
                    'meta' => $row['phone'] ?? '',
                    'screen' => 'households',
                ];
            }
            $citizens = $this->fetchAll(
                'SELECT c.id, c.full_name, c.citizen_code, c.identity_number, c.phone, c.current_address, c.date_of_birth, h.household_code, h.head_citizen_name
                 FROM citizens c INNER JOIN households h ON h.id = c.household_id
                 WHERE ' . $this->activeCitizenCondition('c') . ' AND ' . $this->activeHouseholdCondition('h') . ' AND (c.full_name LIKE :q OR c.identity_number LIKE :q OR c.citizen_code LIKE :q OR c.phone LIKE :q OR c.current_address LIKE :q OR h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR h.address LIKE :q)
                 ORDER BY c.full_name ASC LIMIT ' . $limit,
                ['q' => $q]
            );
            foreach ($citizens as $row) {
                $items[] = [
                    'type' => 'citizen',
                    'id' => (int) $row['id'],
                    'title' => $row['full_name'] ?: 'Nhân khẩu',
                    'subtitle' => trim(($row['identity_number'] ?? '') . ' - ' . ($row['household_code'] ?? ''), ' -'),
                    'meta' => $row['phone'] ?: ($row['current_address'] ?? ''),
                    'screen' => 'persons',
                ];
            }
            return ['items' => array_slice($items, 0, $limit), 'total' => count($items)];
        }, ['items' => [], 'total' => 0]);
    }

    public function quickProfile(string $type, int $id): array
    {
        return $this->safePayload('quickProfile', function () use ($type, $id) {
            if ($type === 'citizen') return $this->citizenQuickProfile($id);
            return $this->householdQuickProfile($id);
        }, ['type' => $type, 'id' => $id, 'profile' => null, 'members' => [], 'files' => [], 'gps' => null, 'timeline' => []]);
    }

    public function timeline(array $filters = []): array
    {
        return $this->safePayload('timeline', function () use ($filters) {
            $limit = min(max((int) ($filters['limit'] ?? 80), 20), 200);
            $items = [];
            if ($this->tableExists('audit_logs')) {
                [$where, $params] = $this->logWhere($filters);
                $logs = $this->fetchAll("SELECT id, created_at, actor_email, module, action, entity_id, message, level FROM audit_logs $where ORDER BY created_at DESC, id DESC LIMIT $limit", $params);
                foreach ($logs as $row) {
                    $items[] = [
                        'type' => 'audit', 'time' => $row['created_at'], 'module' => $row['module'], 'action' => $row['action'],
                        'actor' => $row['actor_email'], 'title' => $row['message'], 'level' => $row['level'] ?: 'INFO', 'entityId' => $row['entity_id'],
                    ];
                }
            }
            if ($this->tableExists('movements')) {
                $rows = $this->fetchAll("SELECT id, created_at, effective_date, type, status, note FROM movements WHERE status <> 'DELETED' ORDER BY COALESCE(effective_date, created_at) DESC, id DESC LIMIT " . min($limit, 50));
                foreach ($rows as $row) {
                    $items[] = [
                        'type' => 'movement', 'time' => $row['effective_date'] ?: $row['created_at'], 'module' => 'movements', 'action' => $row['type'],
                        'actor' => '', 'title' => $this->movementLabel((string) $row['type']) . ' - ' . ($row['note'] ?: $row['status']), 'level' => 'INFO', 'entityId' => $row['id'],
                    ];
                }
            }
            usort($items, fn($a, $b) => strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? '')));
            return ['items' => array_slice($items, 0, $limit), 'generatedAt' => date('c')];
        }, ['items' => [], 'generatedAt' => date('c')]);
    }

    public function areaDashboard(array $filters = []): array
    {
        return $this->safePayload('areaDashboard', function () use ($filters) {
            $area = trim((string) ($filters['area'] ?? $filters['area_code'] ?? ''));
            $whereArea = $area !== '' ? ' AND h.area_code = :area' : '';
            $params = $area !== '' ? ['area' => $area] : [];
            $households = $this->fetchOne('SELECT COUNT(*) AS total_households, COALESCE(SUM(h.poor_household=1),0) AS poor_households, COALESCE(SUM(h.near_poor_household=1),0) AS near_poor_households FROM households h WHERE ' . $this->activeHouseholdCondition('h') . $whereArea, $params) ?: [];
            $citizens = $this->fetchOne('SELECT COUNT(c.id) AS total_citizens, COALESCE(SUM(c.gender="Nam"),0) AS male_count, COALESCE(SUM(c.gender="Nữ"),0) AS female_count, COALESCE(SUM(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 16),0) AS children_count, COALESCE(SUM(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 60),0) AS elderly_count, COALESCE(SUM(' . ($this->columnExists('citizens', 'party_member') ? 'c.party_member=1' : '0') . '),0) AS party_member_count FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE ' . $this->activeCitizenCondition('c') . ' AND ' . $this->activeHouseholdCondition('h') . $whereArea, $params) ?: [];
            $areas = $this->fetchAll('SELECT COALESCE(NULLIF(area_code,""),"Chưa phân khu") AS area_code, COUNT(*) AS total FROM households h WHERE ' . $this->activeHouseholdCondition('h') . ' GROUP BY area_code ORDER BY area_code');
            $gps = $this->gpsProgress($params, $whereArea);
            $profile = $this->profileProgress($params, $whereArea);
            return [
                'area' => $area,
                'areas' => $areas,
                'metrics' => array_merge($households, $citizens),
                'gpsProgress' => $gps,
                'profileProgress' => $profile,
                'generatedAt' => date('c'),
            ];
        }, ['area' => '', 'areas' => [], 'metrics' => [], 'gpsProgress' => $this->emptyProgress(), 'profileProgress' => $this->emptyProgress(), 'generatedAt' => date('c')]);
    }

    public function progress(array $filters = []): array
    {
        return $this->safePayload('progress', function () use ($filters) {
            return ['items' => [
                ['key' => 'gps', 'label' => 'GPS', 'progress' => $this->gpsProgress()],
                ['key' => 'digital_profile', 'label' => 'Hồ sơ số', 'progress' => $this->profileProgress()],
                ['key' => 'household_photo', 'label' => 'Ảnh hộ', 'progress' => $this->householdPhotoProgress()],
                ['key' => 'identity', 'label' => 'CCCD', 'progress' => $this->identityProgress()],
            ], 'generatedAt' => date('c')];
        }, ['items' => [], 'generatedAt' => date('c')]);
    }

    public function systemLogs(array $filters = []): array
    {
        return $this->safePayload('systemLogs', function () use ($filters) {
            if (!$this->tableExists('audit_logs')) return ['items' => [], 'total' => 0, 'page' => 1, 'pageSize' => 50];
            [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 50));
            [$where, $params] = $this->logWhere($filters);
            $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM audit_logs $where", $params) ?: [])['total'] ?? 0);
            $items = $this->fetchAll("SELECT id, actor_email AS user_email, created_at, module, action, message, entity_id, COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.ip')), '') AS ip_address FROM audit_logs $where ORDER BY created_at DESC, id DESC LIMIT $pageSize OFFSET $offset", $params);
            return ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
        }, ['items' => [], 'total' => 0, 'page' => 1, 'pageSize' => 50, 'totalPages' => 1]);
    }

    public function executiveReport(array $filters = []): array
    {
        $notifications = $this->notifications($filters)['data']['items'] ?? [];
        $tasks = $this->tasks($filters)['data']['items'] ?? [];
        $area = $this->areaDashboard($filters)['data'] ?? [];
        $progress = $this->progress($filters)['data']['items'] ?? [];
        return [
            'title' => 'Báo cáo điều hành',
            'generatedAt' => date('d/m/Y H:i:s'),
            'headers' => ['Nhóm', 'Chỉ tiêu', 'Giá trị', 'Mức độ'],
            'rows' => array_merge(
                array_map(fn($item) => ['Thông báo', $item['label'], $item['count'], $item['priority']], $notifications),
                array_map(fn($item) => ['Công việc', $item['label'], $item['count'], $item['priority']], $tasks),
                array_map(fn($item) => ['Tiến độ', $item['label'], ($item['progress']['percent'] ?? 0) . '%', ''], $progress),
                [['Khu vực', 'Tổng số hộ', $area['metrics']['total_households'] ?? 0, $area['area'] ?? 'Tất cả'], ['Khu vực', 'Tổng nhân khẩu', $area['metrics']['total_citizens'] ?? 0, $area['area'] ?? 'Tất cả']]
            ),
        ];
    }

    private function householdQuickProfile(int $id): array
    {
        $profile = $this->fetchOne('SELECT * FROM households WHERE id = :id', ['id' => $id]);
        if (!$profile) return ['type' => 'household', 'id' => $id, 'profile' => null, 'members' => [], 'files' => [], 'gps' => null, 'timeline' => []];
        $members = $this->fetchAll('SELECT id, full_name, relationship, gender, date_of_birth, identity_number FROM citizens WHERE household_id = :id AND status <> "DELETED" ORDER BY relationship="Chủ hộ" DESC, full_name ASC LIMIT 20', ['id' => $id]);
        return ['type' => 'household', 'id' => $id, 'profile' => $profile, 'members' => $members, 'files' => $this->entityFiles('household', $id), 'gps' => ['latitude' => $profile['latitude'] ?? null, 'longitude' => $profile['longitude'] ?? null], 'timeline' => $this->entityTimeline('household', $id)];
    }

    private function citizenQuickProfile(int $id): array
    {
        $profile = $this->fetchOne('SELECT c.*, h.household_code, h.head_citizen_name, h.address AS household_address FROM citizens c LEFT JOIN households h ON h.id = c.household_id WHERE c.id = :id', ['id' => $id]);
        if (!$profile) return ['type' => 'citizen', 'id' => $id, 'profile' => null, 'members' => [], 'files' => [], 'gps' => null, 'timeline' => []];
        $members = $profile['household_id'] ? $this->fetchAll('SELECT id, full_name, relationship, gender, date_of_birth FROM citizens WHERE household_id = :id AND status <> "DELETED" ORDER BY relationship="Chủ hộ" DESC, full_name ASC LIMIT 20', ['id' => $profile['household_id']]) : [];
        return ['type' => 'citizen', 'id' => $id, 'profile' => $profile, 'members' => $members, 'files' => $this->entityFiles('citizen', $id), 'gps' => null, 'timeline' => $this->entityTimeline('citizen', $id)];
    }

    private function entityFiles(string $module, int $id): array
    {
        if (!$this->tableExists('file_attachments')) return [];
        $columns = $this->existingColumns('file_attachments', ['id', 'module', 'entity_type', 'entity_id', 'original_name', 'file_name', 'file_type', 'profile_section', 'created_at', 'status']);
        if (!in_array('id', $columns, true) || !in_array('entity_id', $columns, true)) return [];
        $select = ['id'];
        foreach (['original_name', 'file_name', 'file_type', 'profile_section', 'created_at'] as $column) $select[] = in_array($column, $columns, true) ? $column : "'' AS $column";
        $where = ['entity_id = :id'];
        if (in_array('entity_type', $columns, true) && in_array('module', $columns, true)) $where[] = '(module = :module OR entity_type = :module)';
        elseif (in_array('entity_type', $columns, true)) $where[] = 'entity_type = :module';
        elseif (in_array('module', $columns, true)) $where[] = 'module = :module';
        if (in_array('status', $columns, true)) $where[] = 'status = "ACTIVE"';
        $order = in_array('created_at', $columns, true) ? 'created_at DESC, id DESC' : 'id DESC';
        return $this->fetchAll('SELECT ' . implode(', ', $select) . ' FROM file_attachments WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT 8', ['id' => $id, 'module' => $module]);
    }

    private function entityTimeline(string $module, int $id): array
    {
        if (!$this->tableExists('audit_logs')) return [];
        return $this->fetchAll('SELECT created_at, actor_email, module, action, message FROM audit_logs WHERE entity_id = :id AND module LIKE :module ORDER BY created_at DESC LIMIT 8', ['id' => (string) $id, 'module' => '%' . $module . '%']);
    }

    private function notice(string $key, string $label, int $count, string $priority, string $screen, string $action, string $time): array
    {
        return ['key' => $key, 'label' => $label, 'count' => $count, 'priority' => $priority, 'screen' => $screen, 'action' => $action, 'status' => 'new', 'createdAt' => $time];
    }

    private function task(string $key, string $label, int $count, string $priority, string $screen): array
    {
        return ['key' => $key, 'label' => $label, 'count' => $count, 'priority' => $priority, 'screen' => $screen, 'status' => 'open', 'createdAt' => date('c')];
    }

    private function safePayload(string $widget, callable $callback, array $fallback): array
    {
        try {
            return ['ok' => true, 'data' => $callback(), 'widget' => $widget];
        } catch (\Throwable $exception) {
            $lastQuery = self::lastQuery();
            error_log('[OPERATION_CENTER_WIDGET_ERROR] ' . json_encode(['widget' => $widget, 'message' => $exception->getMessage(), 'sql' => $lastQuery['sql'] ?? null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $message = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN) ? $exception->getMessage() : json_decode('"Kh\u00f4ng t\u1ea3i \u0111\u01b0\u1ee3c d\u1eef li\u1ec7u \u0111i\u1ec1u h\u00e0nh"', true);
            return ['ok' => true, 'data' => $fallback, 'widget' => $widget, 'error' => ['message' => $message]];
        }
    }

    private function missingCitizenFieldCount(array $filters, string $column): int
    {
        if (!$this->columnExists('citizens', $column)) return 0;
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE " . $this->activeCitizenCondition('c') . " AND " . $this->activeHouseholdCondition('h') . " AND (c.$column IS NULL OR c.$column = '' OR c.$column = '0')") ?: [])['total'] ?? 0);
    }

    private function missingCitizenPhotoCount(array $filters): int { return $this->missingEntityFileCount('citizen', 'citizens', 'c', 'c.id', true); }
    private function missingHouseholdPhotoCount(array $filters): int { return $this->missingEntityFileCount('household', 'households', 'h', 'h.id', true); }
    private function missingCitizenDocumentsCount(array $filters): int { return $this->missingEntityFileCount('citizen', 'citizens', 'c', 'c.id', false); }
    private function missingHouseholdDocumentsCount(array $filters): int { return $this->missingEntityFileCount('household', 'households', 'h', 'h.id', false); }

    private function missingEntityFileCount(string $module, string $table, string $alias, string $idExpr, bool $imageOnly): int
    {
        $baseCondition = $table === 'citizens' ? $this->activeCitizenCondition($alias) : $this->activeHouseholdCondition($alias);
        $join = $table === 'citizens' ? ' INNER JOIN households h ON h.id = c.household_id' : '';
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM $table $alias $join WHERE $baseCondition") ?: [])['total'] ?? 0);
        if (!$this->tableExists('file_attachments')) return $total;
        $columns = $this->existingColumns('file_attachments', ['entity_id', 'module', 'entity_type', 'status', 'file_type', 'mime_type']);
        if (!in_array('entity_id', $columns, true)) return $total;
        $fileWhere = ["f.entity_id = $idExpr"];
        if (in_array('entity_type', $columns, true) && in_array('module', $columns, true)) $fileWhere[] = '(f.module = :module OR f.entity_type = :module)';
        elseif (in_array('entity_type', $columns, true)) $fileWhere[] = 'f.entity_type = :module';
        elseif (in_array('module', $columns, true)) $fileWhere[] = 'f.module = :module';
        if (in_array('status', $columns, true)) $fileWhere[] = 'f.status = "ACTIVE"';
        if ($imageOnly) {
            $imageParts = [];
            if (in_array('file_type', $columns, true)) $imageParts[] = "f.file_type IN ('PHOTO','IMAGE')";
            if (in_array('mime_type', $columns, true)) $imageParts[] = "f.mime_type LIKE 'image/%'";
            if ($imageParts) $fileWhere[] = '(' . implode(' OR ', $imageParts) . ')';
        }
        $with = (int) (($this->fetchOne("SELECT COUNT(DISTINCT $idExpr) AS total FROM $table $alias $join WHERE $baseCondition AND EXISTS (SELECT 1 FROM file_attachments f WHERE " . implode(' AND ', $fileWhere) . ')', ['module' => $module]) ?: [])['total'] ?? 0);
        return max(0, $total - $with);
    }

    private function incompleteDigitalProfileCount(array $filters): int
    {
        return $this->missingCitizenDocumentsCount($filters) + $this->missingHouseholdDocumentsCount($filters);
    }

    private function missingGpsCount(array $filters): int
    {
        if (!$this->columnExists('households', 'latitude') || !$this->columnExists('households', 'longitude')) {
            return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->activeHouseholdCondition('h')) ?: [])['total'] ?? 0);
        }
        return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->activeHouseholdCondition('h') . ' AND (h.latitude IS NULL OR h.latitude = "" OR h.longitude IS NULL OR h.longitude = "")') ?: [])['total'] ?? 0);
    }

    private function recentMovementCount(string $type): int
    {
        if (!$this->tableExists('movements')) return 0;
        $where = 'status <> "DELETED" AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
        $params = [];
        if ($type !== '') { $where .= ' AND type = :type'; $params['type'] = $type; }
        return (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM movements WHERE $where", $params) ?: [])['total'] ?? 0);
    }

    private function recentCitizenCount(): int { return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM citizens c WHERE ' . $this->activeCitizenCondition('c') . ' AND DATE(c.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)') ?: [])['total'] ?? 0); }
    private function recentHouseholdCount(): int { return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->activeHouseholdCondition('h') . ' AND DATE(h.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)') ?: [])['total'] ?? 0); }
    private function pendingMovementCount(): int { return $this->tableExists('movements') ? (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM movements WHERE status IN ('PENDING','DRAFT')") ?: [])['total'] ?? 0) : 0; }

    private function gpsProgress(array $params = [], string $whereArea = ''): array
    {
        $total = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->activeHouseholdCondition('h') . $whereArea, $params) ?: [])['total'] ?? 0);
        $done = 0;
        if ($this->columnExists('households', 'latitude') && $this->columnExists('households', 'longitude')) {
            $done = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->activeHouseholdCondition('h') . $whereArea . ' AND h.latitude IS NOT NULL AND h.latitude <> "" AND h.longitude IS NOT NULL AND h.longitude <> ""', $params) ?: [])['total'] ?? 0);
        }
        return $this->progressValue($done, $total);
    }

    private function profileProgress(array $params = [], string $whereArea = ''): array
    {
        $total = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->activeHouseholdCondition('h') . $whereArea, $params) ?: [])['total'] ?? 0);
        $missing = $this->missingHouseholdDocumentsCount([]);
        return $this->progressValue(max(0, $total - min($missing, $total)), $total);
    }

    private function householdPhotoProgress(): array
    {
        $total = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM households h WHERE ' . $this->activeHouseholdCondition('h')) ?: [])['total'] ?? 0);
        return $this->progressValue(max(0, $total - $this->missingHouseholdPhotoCount([])), $total);
    }

    private function identityProgress(): array
    {
        $total = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM citizens c WHERE ' . $this->activeCitizenCondition('c')) ?: [])['total'] ?? 0);
        return $this->progressValue(max(0, $total - $this->missingCitizenFieldCount([], 'identity_number')), $total);
    }

    private function progressValue(int $done, int $total): array { return ['done' => $done, 'total' => $total, 'percent' => $total > 0 ? round($done * 100 / $total, 1) : 0]; }
    private function emptyProgress(): array { return ['done' => 0, 'total' => 0, 'percent' => 0]; }

    private function logWhere(array $filters): array
    {
        $where = ['1=1']; $params = [];
        foreach (['module', 'action'] as $key) if (!empty($filters[$key])) { $where[] = "$key = :$key"; $params[$key] = $filters[$key]; }
        if (!empty($filters['search'])) { $where[] = '(actor_email LIKE :q OR message LIKE :q OR entity_id LIKE :q)'; $params['q'] = '%' . $filters['search'] . '%'; }
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function activeHouseholdCondition(string $alias): string { return $alias . ".status NOT IN ('DELETED','ENDED','MERGED','TRANSFERRED_OUT','MOVED_OUT','INACTIVE')"; }
    private function activeCitizenCondition(string $alias): string { return $alias . ".status <> 'DELETED' AND COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED' AND COALESCE(" . $alias . ".residency_status,'PERMANENT') <> 'TRANSFERRED_OUT'"; }
    private function movementLabel(string $type): string { return ['BIRTH' => 'Thêm nhân khẩu', 'MOVE_IN' => 'Chuyển đến', 'MOVE_OUT' => 'Chuyển đi', 'TEMPORARY_RESIDENCE' => 'Tạm trú', 'TEMPORARY_ABSENCE' => 'Tạm vắng', 'DEATH' => 'Qua đời'][$type] ?? $type; }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
}
