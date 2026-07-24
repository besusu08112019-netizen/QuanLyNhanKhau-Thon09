<?php

namespace App\Models;

use App\Core\BaseModel;

final class WorkCalendar extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS calendar_event_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT '#0d6efd',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_calendar_event_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS calendar_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category_id BIGINT UNSIGNED NULL,
  location VARCHAR(255) NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  reminder_at DATETIME NULL,
  host_user_id BIGINT UNSIGNED NULL,
  host_name VARCHAR(255) NULL,
  area_code VARCHAR(80) NULL,
  status ENUM('SCHEDULED','DONE','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  note TEXT NULL,
  soft_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_calendar_events_search (event_code, title),
  KEY idx_calendar_events_category (category_id),
  KEY idx_calendar_events_time (start_at, end_at),
  KEY idx_calendar_events_reminder (reminder_at),
  KEY idx_calendar_events_host (host_user_id),
  KEY idx_calendar_events_area (area_code),
  KEY idx_calendar_events_status (status),
  KEY idx_calendar_events_soft_status (soft_status),
  CONSTRAINT fk_calendar_events_category FOREIGN KEY (category_id) REFERENCES calendar_event_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS calendar_event_attendees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  attendee_name VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NULL,
  role_name VARCHAR(120) NULL,
  attendance_status ENUM('INVITED','ATTENDED','ABSENT','EXCUSED') NOT NULL DEFAULT 'INVITED',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_calendar_event_attendees_event (event_id),
  KEY idx_calendar_event_attendees_status (attendance_status),
  CONSTRAINT fk_calendar_event_attendees_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS calendar_event_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('IMAGE','VIDEO','PDF','DOCUMENT','OTHER') NOT NULL DEFAULT 'OTHER',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_calendar_event_attachments_event (event_id),
  CONSTRAINT fk_calendar_event_attachments_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->seedCatalogs();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'categories' => array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name'], 'color' => (string)$r['color']], $this->fetchAll('SELECT id, code, name, color FROM calendar_event_categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC')),
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
        $row = $this->fetchOne($this->selectSql() . ' ' . $this->fromSql() . ' WHERE e.id=:id AND e.soft_status <> "DELETED"', ['id' => $id]);
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
            $this->execute('UPDATE calendar_events SET title=:title, description=:description, category_id=:category_id, location=:location, start_at=:start_at, end_at=:end_at, reminder_at=:reminder_at, host_user_id=:host_user_id, host_name=:host_name, area_code=:area_code, status=:status, note=:note, updated_by=:updated_by WHERE id=:id AND soft_status <> "DELETED"', $params);
            $this->syncAttendees($id, $data['attendees'] ?? [], $userId);
            return $this->find($id);
        }
        $params['event_code'] = $this->nextCode();
        $newId = $this->insert('INSERT INTO calendar_events (event_code, title, description, category_id, location, start_at, end_at, reminder_at, host_user_id, host_name, area_code, status, note, created_by, updated_by) VALUES (:event_code, :title, :description, :category_id, :location, :start_at, :end_at, :reminder_at, :host_user_id, :host_name, :area_code, :status, :note, :created_by, :updated_by)', $params);
        $this->syncAttendees($newId, $data['attendees'] ?? [], $userId);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy lịch công tác');
        $this->execute('UPDATE calendar_events SET soft_status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
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

    private function seedCatalogs(): void
    {
        $items = [
            ['meeting', 'Họp', '#0d6efd'],
            ['conference', 'Hội nghị', '#6610f2'],
            ['duty', 'Trực', '#198754'],
            ['vaccination', 'Tiêm chủng', '#20c997'],
            ['gift_distribution', 'Phát quà', '#fd7e14'],
            ['party_meeting', 'Sinh hoạt Chi bộ', '#dc3545'],
            ['union_activity', 'Sinh hoạt đoàn thể', '#6f42c1'],
            ['other', 'Khác', '#6c757d'],
        ];
        $order = 10;
        foreach ($items as [$code, $name, $color]) {
            $this->execute('INSERT INTO calendar_event_categories (code,name,color,sort_order) VALUES (:code,:name,:color,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'color' => $color, 'sort_order' => $order]);
            $order += 10;
        }
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
        $where = ['e.soft_status <> "DELETED"'];
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
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function fromSql(): string { return 'FROM calendar_events e LEFT JOIN calendar_event_categories c ON c.id=e.category_id'; }
    private function selectSql(): string { return 'SELECT e.*, c.name AS category_name, c.code AS category_code, c.color AS category_color'; }
    private function attendees(int $id): array { return $this->fetchAll('SELECT * FROM calendar_event_attendees WHERE event_id=:id ORDER BY id ASC', ['id' => $id]); }
    private function attachments(int $id): array { return array_map(fn($row) => $this->normalizeAttachment($row), $this->fetchAll('SELECT * FROM calendar_event_attachments WHERE event_id=:id AND deleted_at IS NULL ORDER BY created_at DESC, id DESC', ['id' => $id])); }
    private function validCategory(mixed $value): ?int { $id = $this->nullableInt($value); if (!$id) return null; $row = $this->fetchOne('SELECT id FROM calendar_event_categories WHERE id=:id AND is_active=1', ['id' => $id]); if (!$row) throw new \RuntimeException('Loại lịch công tác không hợp lệ'); return $id; }
    private function normalize(array $row): array { $row['id'] = (int)$row['id']; $row['category_id'] = $row['category_id'] !== null ? (int)$row['category_id'] : null; $row['host_user_id'] = $row['host_user_id'] !== null ? (int)$row['host_user_id'] : null; $row['status_label'] = $this->statusLabel((string)$row['status']); return $row; }
    private function normalizeAttachment(array $row): array { $row['id'] = (int)$row['id']; $row['event_id'] = (int)$row['event_id']; $row['file_size'] = (int)$row['file_size']; $row['preview_url'] = '/api/work-calendar/' . $row['event_id'] . '/attachments/' . $row['id'] . '/preview'; $row['download_url'] = '/api/work-calendar/' . $row['event_id'] . '/attachments/' . $row['id'] . '/download'; return $row; }
    private function statusLabel(string $value): string { return ['SCHEDULED' => 'Đã lên lịch', 'DONE' => 'Đã hoàn thành', 'CANCELLED' => 'Đã hủy'][$value] ?? $value; }
    private function nextCode(): string { $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM calendar_events'); return 'LCT-' . date('Y') . '-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : $value; }
    private function nullableInt(mixed $value): ?int { $value = trim((string)($value ?? '')); if ($value === '') return null; $id = (int)$value; return $id > 0 ? $id : null; }
    private function dateTime(mixed $value, bool $required, string $message): ?string { $value = trim((string)($value ?? '')); if ($value === '') { if ($required) throw new \RuntimeException($message); return null; } if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value . ' 00:00:00'; if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value)) throw new \RuntimeException($message); return str_replace('T', ' ', strlen($value) === 16 ? $value . ':00' : $value); }
}
