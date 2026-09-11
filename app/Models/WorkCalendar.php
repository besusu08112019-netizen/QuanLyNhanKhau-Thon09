<?php

namespace App\Models;

use App\Core\BaseModel;

final class WorkCalendar extends BaseModel
{
    private const REQUIRED_SCHEMA = [
        'calendar_event_categories' => ['id','village_id','code','name','color','sort_order','is_active','created_at','updated_at'],
        'calendar_events' => ['id','village_id','event_code','title','description','category_id','location','start_at','end_at','reminder_at','host_user_id','host_name','area_code','status','note','soft_status','created_at','updated_at','created_by','updated_by','deleted_at','deleted_by'],
        'calendar_event_attendees' => ['id','village_id','event_id','attendee_name','phone','role_name','attendance_status','note','created_at','updated_at'],
        'calendar_event_attachments' => ['id','village_id','event_id','original_name','stored_path','mime_type','file_size','file_kind','created_at','created_by','deleted_at','deleted_by'],
    ];

    private const REQUIRED_INDEXES = [
        'calendar_event_categories' => ['PRIMARY','idx_calendar_event_categories_active','idx_calendar_event_categories_village'],
        'calendar_events' => ['PRIMARY','idx_calendar_events_search','idx_calendar_events_category','idx_calendar_events_time','idx_calendar_events_reminder','idx_calendar_events_host','idx_calendar_events_area','idx_calendar_events_status','idx_calendar_events_soft_status','idx_calendar_events_village'],
        'calendar_event_attendees' => ['PRIMARY','idx_calendar_event_attendees_event','idx_calendar_event_attendees_status','idx_calendar_event_attendees_village'],
        'calendar_event_attachments' => ['PRIMARY','idx_calendar_event_attachments_event','idx_calendar_event_attachments_village'],
    ];

    private const REQUIRED_FOREIGN_KEYS = [
        'calendar_events' => [['fk_calendar_events_category','category_id','calendar_event_categories','id']],
        'calendar_event_attendees' => [['fk_calendar_event_attendees_event','event_id','calendar_events','id']],
        'calendar_event_attachments' => [['fk_calendar_event_attachments_event','event_id','calendar_events','id']],
    ];

    private const REQUIRED_CATEGORIES = [
        ['meeting', 'Họp', '#0d6efd', 10],
        ['conference', 'Hội nghị', '#6610f2', 20],
        ['duty', 'Trực', '#198754', 30],
        ['vaccination', 'Tiêm chủng', '#20c997', 40],
        ['gift_distribution', 'Phát quà', '#fd7e14', 50],
        ['party_meeting', 'Sinh hoạt Chi bộ', '#dc3545', 60],
        ['union_activity', 'Sinh hoạt đoàn thể', '#6f42c1', 70],
        ['other', 'Khác', '#6c757d', 80],
    ];

    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        $categories = $this->uniqueCatalogRows($this->fetchAll('SELECT id, code, name, color FROM calendar_event_categories WHERE is_active=1 ORDER BY sort_order ASC, id ASC'));
        return [
            'categories' => array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name'], 'color' => (string)$r['color']], $categories),
            'statuses' => [
                ['value' => 'SCHEDULED', 'label' => 'Đã lên lịch'],
                ['value' => 'DONE', 'label' => 'Đã hoàn thành'],
                ['value' => 'CANCELLED', 'label' => 'Đã hủy'],
            ],
            'attendance_statuses' => [
                ['value' => 'INVITED', 'label' => 'Đã mời'],
                ['value' => 'ATTENDED', 'label' => 'Có mặt'],
                ['value' => 'ABSENT', 'label' => 'Vắng'],
                ['value' => 'EXCUSED', 'label' => 'Có lý do'],
            ],
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters);
        $order = $this->listOrder($filters, [
            'event_code' => 'e.event_code',
            'title' => 'e.title',
            'category' => 'c.name',
            'start_at' => 'e.start_at',
            'status' => 'e.status',
            'host' => 'e.host_name',
        ], 'start_at', 'ASC', ['e.id DESC']);
        $from = $this->fromSql();
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " $from $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne($this->selectSql() . ' ' . $this->fromSql() . ' WHERE e.id=:id AND e.soft_status <> "DELETED" AND ' . $this->tenantWhere('e', 'calendar_events'), ['id' => $id]);
        if (!$row) return null;
        $item = $this->normalize($row);
        $item['attendees'] = $this->attendees($id);
        $item['attachments'] = $this->attachments($id);
        return $item;
    }

    public function upsert(array $data, int $userId, string $userName, ?int $id = null): array
    {
        $this->ensureSchema();
        $existing = $id ? $this->find($id) : null;
        if ($id && !$existing) throw new \RuntimeException('Không tìm thấy lịch công tác');
        $params = $this->params($data, $userId, $userName);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE calendar_events SET title=:title, description=:description, category_id=:category_id, location=:location, start_at=:start_at, end_at=:end_at, reminder_at=:reminder_at, host_user_id=:host_user_id, host_name=:host_name, area_code=:area_code, status=:status, note=:note, updated_by=:updated_by WHERE id=:id AND soft_status <> "DELETED" AND ' . $this->tenantWhere('calendar_events'), $params);
            $this->syncAttendees($id, $data['attendees'] ?? [], $userId);
            return $this->find($id);
        }
        $params['event_code'] = $this->nextCode();
        $columns = ['event_code', 'title', 'description', 'category_id', 'location', 'start_at', 'end_at', 'reminder_at', 'host_user_id', 'host_name', 'area_code', 'status', 'note', 'created_by', 'updated_by'];
        $this->addTenantInsert('calendar_events', $columns, $params);
        $newId = $this->insert('INSERT INTO calendar_events (' . implode(',', $columns) . ') VALUES (:' . implode(', :', $columns) . ')', $params);
        $this->syncAttendees($newId, $data['attendees'] ?? [], $userId);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy lịch công tác');
        $this->execute('UPDATE calendar_events SET soft_status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('calendar_events'), ['id' => $id, 'user' => $userId]);
    }

    public function addAttachment(int $id, array $stored, array $file, int $userId): array
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy lịch công tác');
        $mime = (string)$stored['mime'];
        $kind = str_starts_with($mime, 'image/') ? 'IMAGE' : (str_starts_with($mime, 'video/') ? 'VIDEO' : ($mime === 'application/pdf' ? 'PDF' : 'DOCUMENT'));
        $attachmentId = $this->insert('INSERT INTO calendar_event_attachments (event_id, original_name, stored_path, mime_type, file_size, file_kind, created_by) VALUES (:id,:name,:path,:mime,:size,:kind,:user)', ['id' => $id, 'name' => basename((string)($file['name'] ?? 'attachment')), 'path' => $stored['file_path'], 'mime' => $mime, 'size' => (int)($file['size'] ?? 0), 'kind' => $kind, 'user' => $userId]);
        return $this->attachment($id, $attachmentId) ?? ['id' => $attachmentId];
    }

    public function attachment(int $eventId, int $fileId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM calendar_event_attachments WHERE event_id=:event_id AND id=:id AND deleted_at IS NULL', ['event_id' => $eventId, 'id' => $fileId]);
        return $row ? $this->normalizeAttachment($row) : null;
    }

    public function deleteAttachment(int $eventId, int $fileId, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->attachment($eventId, $fileId)) throw new \RuntimeException('Không tìm thấy file đính kèm');
        $this->execute('UPDATE calendar_event_attachments SET deleted_at=NOW(), deleted_by=:user WHERE event_id=:event_id AND id=:id', ['event_id' => $eventId, 'id' => $fileId, 'user' => $userId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters);
        $from = $this->fromSql();
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(DATE(e.start_at)=CURDATE() AND e.status='SCHEDULED'),0) AS today_count, COALESCE(SUM(e.start_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND e.status='SCHEDULED'),0) AS week_count, COALESCE(SUM(e.status='DONE'),0) AS done_count, COALESCE(SUM(e.status='CANCELLED'),0) AS cancelled_count $from $where", $params) ?: [];
        return [
            'metrics' => array_map('intval', $metrics),
            'charts' => [
                'by_month' => $this->fetchAll("SELECT DATE_FORMAT(e.start_at, '%Y-%m') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY label DESC LIMIT 12", $params),
                'by_category' => $this->fetchAll("SELECT COALESCE(c.name, 'Khác') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY value DESC", $params),
                'by_status' => $this->fetchAll("SELECT e.status AS label, COUNT(*) AS value $from $where GROUP BY e.status ORDER BY value DESC", $params),
            ],
        ];
    }

    public function report(array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['pageSize'] = 100;
        $data = $this->paginate($filters);
        return [
            'title' => 'Báo cáo lịch công tác',
            'headers' => ['Mã', 'Tiêu đề', 'Loại', 'Thời gian', 'Địa điểm', 'Chủ trì', 'Trạng thái'],
            'rows' => array_map(fn($r) => [$r['event_code'], $r['title'], $r['category_name'] ?? '', $r['start_at'], $r['location'] ?? '', $r['host_name'] ?? '', $this->statusLabel($r['status'])], $data['items']),
            'totalRows' => $data['total'],
        ];
    }

    private function syncAttendees(int $eventId, mixed $items, int $userId): void
    {
        if (is_string($items)) $items = json_decode($items, true) ?: [];
        if (!is_array($items)) $items = [];
        $this->execute('DELETE FROM calendar_event_attendees WHERE event_id=:id', ['id' => $eventId]);
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $name = trim((string)($item['attendee_name'] ?? $item['name'] ?? ''));
            if ($name === '') continue;
            $status = strtoupper(trim((string)($item['attendance_status'] ?? $item['status'] ?? 'INVITED')));
            if (!in_array($status, ['INVITED','ATTENDED','ABSENT','EXCUSED'], true)) $status = 'INVITED';
            $this->execute('INSERT INTO calendar_event_attendees (event_id, attendee_name, phone, role_name, attendance_status, note) VALUES (:event_id,:name,:phone,:role,:status,:note)', ['event_id' => $eventId, 'name' => $name, 'phone' => $this->nullable($item['phone'] ?? ''), 'role' => $this->nullable($item['role_name'] ?? $item['role'] ?? ''), 'status' => $status, 'note' => $this->nullable($item['note'] ?? '')]);
        }
    }

    private function uniqueCatalogRows(array $rows): array
    {
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string)($row['code'] ?? '')));
            if ($key === '') $key = (string)($row['id'] ?? count($unique));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $row;
        }
        return $unique;
    }

    private function params(array $data, int $userId, string $userName): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') throw new \RuntimeException('Tiêu đề lịch công tác là bắt buộc');
        $status = strtoupper(trim((string)($data['status'] ?? 'SCHEDULED')));
        if (!in_array($status, ['SCHEDULED','DONE','CANCELLED'], true)) throw new \RuntimeException('Trạng thái lịch không hợp lệ');
        return [
            'title' => $title,
            'description' => $this->nullable($data['description'] ?? ''),
            'category_id' => $this->validCategory($data['category_id'] ?? $data['categoryId'] ?? null),
            'location' => $this->nullable($data['location'] ?? ''),
            'start_at' => $this->dateTime($data['start_at'] ?? $data['startAt'] ?? null, true, 'Thời gian bắt đầu không hợp lệ'),
            'end_at' => $this->dateTime($data['end_at'] ?? $data['endAt'] ?? null, false, 'Thời gian kết thúc không hợp lệ'),
            'reminder_at' => $this->dateTime($data['reminder_at'] ?? $data['reminderAt'] ?? null, false, 'Thời gian nhắc việc không hợp lệ'),
            'host_user_id' => $this->nullableInt($data['host_user_id'] ?? $data['hostUserId'] ?? null),
            'host_name' => $this->nullable($data['host_name'] ?? $data['hostName'] ?? $userName),
            'area_code' => $this->nullable($data['area_code'] ?? $data['areaCode'] ?? ''),
            'status' => $status,
            'note' => $this->nullable($data['note'] ?? ''),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function where(array $filters): array
    {
        $where = ['e.soft_status <> "DELETED"', $this->tenantWhere('e', 'calendar_events')];
        $params = [];
        $category = $this->nullableInt($filters['category_id'] ?? $filters['categoryId'] ?? null);
        if ($category) { $where[] = 'e.category_id=:category_id'; $params['category_id'] = $category; }
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        if ($status !== '') { $where[] = 'e.status=:status'; $params['status'] = $status; }
        $area = trim((string)($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') { $where[] = 'e.area_code LIKE :area_code'; $params['area_code'] = '%' . $area . '%'; }
        $from = trim((string)($filters['date_from'] ?? $filters['dateFrom'] ?? ''));
        $to = trim((string)($filters['date_to'] ?? $filters['dateTo'] ?? ''));
        if ($from !== '') { $where[] = 'DATE(e.start_at) >= :date_from'; $params['date_from'] = $from; }
        if ($to !== '') { $where[] = 'DATE(e.start_at) <= :date_to'; $params['date_to'] = $to; }
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(e.event_code LIKE :q OR e.title LIKE :q OR e.description LIKE :q OR e.location LIKE :q OR e.host_name LIKE :q OR e.area_code LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        return ['WHERE ' . implode(' AND ', $where), $this->withTenant($params)];
    }

    private function fromSql(): string { return 'FROM calendar_events e LEFT JOIN calendar_event_categories c ON c.id=e.category_id'; }
    private function selectSql(): string { return 'SELECT e.*, c.name AS category_name, c.code AS category_code, c.color AS category_color'; }
    private function attendees(int $id): array { return $this->fetchAll('SELECT * FROM calendar_event_attendees WHERE event_id=:id ORDER BY id ASC', ['id' => $id]); }
    private function attachments(int $id): array { return array_map(fn($row) => $this->normalizeAttachment($row), $this->fetchAll('SELECT * FROM calendar_event_attachments WHERE event_id=:id AND deleted_at IS NULL ORDER BY created_at DESC, id DESC', ['id' => $id])); }
    private function validCategory(mixed $value): ?int { $id = $this->nullableInt($value); if (!$id) return null; $row = $this->fetchOne('SELECT id FROM calendar_event_categories WHERE id=:id AND is_active=1', ['id' => $id]); if (!$row) throw new \RuntimeException('Loại lịch công tác không hợp lệ'); return $id; }
    private function normalize(array $row): array { $row['id'] = (int)$row['id']; $row['category_id'] = $row['category_id'] !== null ? (int)$row['category_id'] : null; $row['host_user_id'] = $row['host_user_id'] !== null ? (int)$row['host_user_id'] : null; $row['status_label'] = $this->statusLabel((string)$row['status']); return $row; }
    private function normalizeAttachment(array $row): array { $row['id'] = (int)$row['id']; $row['event_id'] = (int)$row['event_id']; $row['file_size'] = (int)$row['file_size']; $row['preview_url'] = '/api/work-calendar/' . $row['event_id'] . '/attachments/' . $row['id'] . '/preview'; $row['download_url'] = '/api/work-calendar/' . $row['event_id'] . '/attachments/' . $row['id'] . '/download'; return $row; }
    private function statusLabel(string $value): string { return ['SCHEDULED' => 'Đã lên lịch', 'DONE' => 'Đã hoàn thành', 'CANCELLED' => 'Đã hủy'][$value] ?? $value; }
    private function assertSchemaReady(): void { foreach (self::REQUIRED_SCHEMA as $table => $columns) { if (!$this->tableExists($table)) throw new \RuntimeException('Work Calendar schema is not provisioned: missing table ' . $table); foreach ($columns as $column) { if (!$this->columnExists($table, $column)) throw new \RuntimeException('Work Calendar schema is not provisioned: missing column ' . $table . '.' . $column); } foreach (self::REQUIRED_INDEXES[$table] ?? [] as $index) { if (!$this->indexExists($table, $index)) throw new \RuntimeException('Work Calendar schema is not provisioned: missing index ' . $table . '.' . $index); } } foreach (self::REQUIRED_FOREIGN_KEYS as $table => $foreignKeys) { foreach ($foreignKeys as [$constraint, $column, $referencedTable, $referencedColumn]) { if (!$this->foreignKeyExists($table, $constraint, $column, $referencedTable, $referencedColumn)) throw new \RuntimeException('Work Calendar schema is not provisioned: missing foreign key ' . $constraint); } } $drift = $this->catalogReadinessDrift(); if ($drift !== []) throw new \RuntimeException('Work Calendar catalogs are not provisioned: ' . implode('; ', $drift)); }
    private function catalogReadinessDrift(): array { $drift = []; foreach (self::REQUIRED_CATEGORIES as [$code]) { $compatible = $this->fetchOne('SELECT id FROM calendar_event_categories WHERE code=:code AND is_active=1', ['code' => $code]); if ($compatible) continue; $existing = $this->fetchOne('SELECT id FROM calendar_event_categories WHERE code=:code', ['code' => $code]); $drift[] = $existing ? 'conflicting category ' . $code : 'missing category ' . $code; } return $drift; }
    private function tableExists(string $table): bool { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table', ['table' => $table]); return (int)($row['total'] ?? 0) > 0; }
    private function indexExists(string $table, string $index): bool { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND INDEX_NAME=:index', ['table' => $table, 'index' => $index]); return (int)($row['total'] ?? 0) > 0; }
    private function foreignKeyExists(string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): bool { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND CONSTRAINT_NAME=:constraint_name AND COLUMN_NAME=:column_name AND REFERENCED_TABLE_NAME=:referenced_table AND REFERENCED_COLUMN_NAME=:referenced_column', ['table' => $table, 'constraint_name' => $constraint, 'column_name' => $column, 'referenced_table' => $referencedTable, 'referenced_column' => $referencedColumn]); return (int)($row['total'] ?? 0) > 0; }
    private function nextCode(): string { $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM calendar_events WHERE ' . $this->tenantWhere('calendar_events')); return 'LCT-' . date('Y') . '-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : $value; }
    private function nullableInt(mixed $value): ?int { $value = trim((string)($value ?? '')); if ($value === '') return null; $id = (int)$value; return $id > 0 ? $id : null; }
    private function dateTime(mixed $value, bool $required, string $message): ?string { $value = trim((string)($value ?? '')); if ($value === '') { if ($required) throw new \RuntimeException($message); return null; } if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value . ' 00:00:00'; if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value)) throw new \RuntimeException($message); return str_replace('T', ' ', strlen($value) === 16 ? $value . ':00' : $value); }
}
