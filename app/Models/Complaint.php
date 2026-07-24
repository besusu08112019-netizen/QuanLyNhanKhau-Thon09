<?php

namespace App\Models;

use App\Core\BaseModel;

final class Complaint extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS complaint_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_complaint_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS complaint_priorities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_complaint_priorities_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS complaint_statuses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  marker_color VARCHAR(20) NOT NULL DEFAULT 'red',
  is_terminal TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_complaint_statuses_active (is_active),
  KEY idx_complaint_statuses_terminal (is_terminal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS complaints (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  detail TEXT NOT NULL,
  received_at DATETIME NOT NULL,
  receiver_user_id BIGINT UNSIGNED NULL,
  receiver_name VARCHAR(255) NULL,
  reporter_name VARCHAR(255) NOT NULL,
  reporter_phone VARCHAR(40) NULL,
  household_id BIGINT UNSIGNED NULL,
  citizen_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  priority_id BIGINT UNSIGNED NULL,
  status_id BIGINT UNSIGNED NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  assigned_name VARCHAR(255) NULL,
  due_at DATETIME NULL,
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  gps_accuracy DECIMAL(10,2) NULL,
  result_rating ENUM('SATISFIED','NEEDS_MORE','DISAGREE') NULL,
  result_note TEXT NULL,
  closed_at DATETIME NULL,
  soft_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_complaints_search (complaint_code, title),
  KEY idx_complaints_category (category_id),
  KEY idx_complaints_priority (priority_id),
  KEY idx_complaints_status (status_id),
  KEY idx_complaints_assigned (assigned_user_id),
  KEY idx_complaints_receiver (receiver_user_id),
  KEY idx_complaints_household (household_id),
  KEY idx_complaints_citizen (citizen_id),
  KEY idx_complaints_received (received_at),
  KEY idx_complaints_due (due_at),
  KEY idx_complaints_location (latitude, longitude),
  KEY idx_complaints_soft_status (soft_status),
  CONSTRAINT fk_complaints_category FOREIGN KEY (category_id) REFERENCES complaint_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_complaints_priority FOREIGN KEY (priority_id) REFERENCES complaint_priorities(id) ON DELETE SET NULL,
  CONSTRAINT fk_complaints_status FOREIGN KEY (status_id) REFERENCES complaint_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS complaint_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT UNSIGNED NOT NULL,
  target_type VARCHAR(60) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  label VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_complaint_links_target (complaint_id, target_type, target_id, label),
  KEY idx_complaint_links_target (target_type, target_id),
  CONSTRAINT fk_complaint_links_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureComplaintLinkIndex();
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS complaint_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT UNSIGNED NOT NULL,
  history_id BIGINT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('IMAGE','VIDEO','PDF','OTHER') NOT NULL DEFAULT 'OTHER',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_complaint_attachments_complaint (complaint_id),
  KEY idx_complaint_attachments_history (history_id),
  KEY idx_complaint_attachments_kind (file_kind),
  CONSTRAINT fk_complaint_attachments_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS complaint_histories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(255) NULL,
  content TEXT NOT NULL,
  status_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_complaint_histories_complaint (complaint_id),
  KEY idx_complaint_histories_status (status_id),
  CONSTRAINT fk_complaint_histories_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_complaint_histories_status FOREIGN KEY (status_id) REFERENCES complaint_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS complaint_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT UNSIGNED NOT NULL,
  assignee_user_id BIGINT UNSIGNED NULL,
  assignee_name VARCHAR(255) NOT NULL,
  assigned_at DATETIME NOT NULL,
  due_at DATETIME NULL,
  note TEXT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_complaint_assignments_complaint (complaint_id),
  KEY idx_complaint_assignments_assignee (assignee_user_id),
  KEY idx_complaint_assignments_due (due_at),
  CONSTRAINT fk_complaint_assignments_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->seedCatalogs();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'categories' => $this->catalog('complaint_categories'),
            'priorities' => $this->catalog('complaint_priorities'),
            'statuses' => $this->statusCatalog(),
            'ratings' => [
                ['value' => 'SATISFIED', 'label' => 'Người dân hài lòng'],
                ['value' => 'NEEDS_MORE', 'label' => 'Cần xử lý thêm'],
                ['value' => 'DISAGREE', 'label' => 'Không đồng ý'],
            ],
            'linkTypes' => $this->linkTypes(),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters);
        $order = $this->listOrder($filters, [
            'complaint_code' => 'c.complaint_code',
            'title' => 'c.title',
            'reporter_name' => 'c.reporter_name',
            'category' => 'cc.name',
            'priority' => 'cp.sort_order',
            'status' => 'cs.sort_order',
            'assigned' => 'c.assigned_name',
            'received_at' => 'c.received_at',
            'due_at' => 'c.due_at',
            'overdue' => 'is_overdue',
        ], 'received_at', 'DESC', ['c.id DESC']);
        $from = $this->fromSql();
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " $from $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne($this->selectSql() . ' ' . $this->fromSql() . ' WHERE c.id=:id AND c.soft_status <> "DELETED"', ['id' => $id]);
        if (!$row) return null;
        $item = $this->normalize($row);
        $item['links'] = $this->links($id);
        $item['attachments'] = $this->attachments($id);
        $item['histories'] = $this->histories($id);
        $item['assignments'] = $this->assignments($id);
        return $item;
    }

    public function upsert(array $data, int $userId, string $userName, ?int $id = null): array
    {
        $this->ensureSchema();
        $existing = $id ? $this->find($id) : null;
        if ($id && !$existing) throw new \RuntimeException('Không tìm thấy phản ánh');
        $params = $this->params($data, $userId, $userName, $existing);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE complaints SET title=:title, detail=:detail, received_at=:received_at, receiver_user_id=:receiver_user_id, receiver_name=:receiver_name, reporter_name=:reporter_name, reporter_phone=:reporter_phone, household_id=:household_id, citizen_id=:citizen_id, category_id=:category_id, priority_id=:priority_id, status_id=:status_id, assigned_user_id=:assigned_user_id, assigned_name=:assigned_name, due_at=:due_at, latitude=:latitude, longitude=:longitude, gps_accuracy=:gps_accuracy, result_rating=:result_rating, result_note=:result_note, closed_at=:closed_at, updated_by=:updated_by WHERE id=:id AND soft_status <> "DELETED"', $params);
            $this->syncLinks($id, $data, $userId);
            return $this->find($id);
        }
        $params['complaint_code'] = $this->nextCode();
        $newId = $this->insert('INSERT INTO complaints (complaint_code, title, detail, received_at, receiver_user_id, receiver_name, reporter_name, reporter_phone, household_id, citizen_id, category_id, priority_id, status_id, assigned_user_id, assigned_name, due_at, latitude, longitude, gps_accuracy, result_rating, result_note, closed_at, created_by, updated_by) VALUES (:complaint_code, :title, :detail, :received_at, :receiver_user_id, :receiver_name, :reporter_name, :reporter_phone, :household_id, :citizen_id, :category_id, :priority_id, :status_id, :assigned_user_id, :assigned_name, :due_at, :latitude, :longitude, :gps_accuracy, :result_rating, :result_note, :closed_at, :created_by, :updated_by)', $params);
        $this->syncLinks($newId, $data, $userId);
        $this->addHistory($newId, ['content' => 'Tiếp nhận phản ánh', 'status_id' => $params['status_id']], $userId, $userName);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy phản ánh');
        $this->execute('UPDATE complaints SET soft_status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    public function addHistory(int $id, array $data, int $userId, string $userName): array
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy phản ánh');
        $content = trim((string)($data['content'] ?? $data['note'] ?? ''));
        if ($content === '') throw new \RuntimeException('Nội dung xử lý là bắt buộc');
        $statusId = $this->validId('complaint_statuses', $data['status_id'] ?? $data['statusId'] ?? null, true);
        $historyId = $this->insert('INSERT INTO complaint_histories (complaint_id, actor_user_id, actor_name, content, status_id) VALUES (:id,:user,:name,:content,:status)', ['id' => $id, 'user' => $userId, 'name' => $userName, 'content' => $content, 'status' => $statusId]);
        if ($statusId) {
            $closed = $this->statusTerminal($statusId) ? ', closed_at=COALESCE(closed_at,NOW())' : ', closed_at=NULL';
            $this->execute('UPDATE complaints SET status_id=:status, updated_by=:user' . $closed . ' WHERE id=:id', ['id' => $id, 'status' => $statusId, 'user' => $userId]);
        }
        return $this->histories($id)[0] ?? ['id' => $historyId];
    }

    public function assign(int $id, array $data, int $userId): array
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy phản ánh');
        $assigneeId = $this->nullableInt($data['assignee_user_id'] ?? $data['assigneeUserId'] ?? null);
        $assigneeName = trim((string)($data['assignee_name'] ?? $data['assigneeName'] ?? ''));
        if ($assigneeName === '' && $assigneeId) $assigneeName = $this->userName($assigneeId);
        if ($assigneeName === '') throw new \RuntimeException('Người xử lý là bắt buộc');
        $assignedAt = $this->dateTime($data['assigned_at'] ?? $data['assignedAt'] ?? date('Y-m-d H:i:s'), true, 'Ngày giao không hợp lệ');
        $dueAt = $this->dateTime($data['due_at'] ?? $data['dueAt'] ?? null, false, 'Hạn hoàn thành không hợp lệ');
        $note = $this->nullable($data['note'] ?? '');
        $assignmentId = $this->insert('INSERT INTO complaint_assignments (complaint_id, assignee_user_id, assignee_name, assigned_at, due_at, note, assigned_by) VALUES (:id,:assignee_id,:assignee_name,:assigned_at,:due_at,:note,:user)', ['id' => $id, 'assignee_id' => $assigneeId, 'assignee_name' => $assigneeName, 'assigned_at' => $assignedAt, 'due_at' => $dueAt, 'note' => $note, 'user' => $userId]);
        $this->execute('UPDATE complaints SET assigned_user_id=:assignee_id, assigned_name=:assignee_name, due_at=:due_at, updated_by=:user WHERE id=:id', ['id' => $id, 'assignee_id' => $assigneeId, 'assignee_name' => $assigneeName, 'due_at' => $dueAt, 'user' => $userId]);
        return $this->assignments($id)[0] ?? ['id' => $assignmentId];
    }

    public function evaluate(int $id, array $data, int $userId): array
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy phản ánh');
        $rating = strtoupper(trim((string)($data['result_rating'] ?? $data['resultRating'] ?? '')));
        if (!in_array($rating, ['SATISFIED','NEEDS_MORE','DISAGREE'], true)) throw new \RuntimeException('Đánh giá kết quả không hợp lệ');
        $this->execute('UPDATE complaints SET result_rating=:rating, result_note=:note, updated_by=:user WHERE id=:id', ['id' => $id, 'rating' => $rating, 'note' => $this->nullable($data['result_note'] ?? $data['resultNote'] ?? ''), 'user' => $userId]);
        return $this->find($id);
    }

    public function addAttachment(int $id, array $stored, array $file, int $userId, ?int $historyId = null): array
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy phản ánh');
        $mime = (string)$stored['mime'];
        $kind = str_starts_with($mime, 'image/') ? 'IMAGE' : (str_starts_with($mime, 'video/') ? 'VIDEO' : ($mime === 'application/pdf' ? 'PDF' : 'OTHER'));
        $attachmentId = $this->insert('INSERT INTO complaint_attachments (complaint_id, history_id, original_name, stored_path, mime_type, file_size, file_kind, created_by) VALUES (:id,:history_id,:name,:path,:mime,:size,:kind,:user)', ['id' => $id, 'history_id' => $historyId, 'name' => basename((string)($file['name'] ?? 'attachment')), 'path' => $stored['file_path'], 'mime' => $mime, 'size' => (int)($file['size'] ?? 0), 'kind' => $kind, 'user' => $userId]);
        return $this->attachment($id, $attachmentId) ?? ['id' => $attachmentId];
    }

    public function attachment(int $complaintId, int $fileId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM complaint_attachments WHERE complaint_id=:complaint_id AND id=:id AND deleted_at IS NULL', ['complaint_id' => $complaintId, 'id' => $fileId]);
        return $row ? $this->normalizeAttachment($row) : null;
    }

    public function deleteAttachment(int $complaintId, int $fileId, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->attachment($complaintId, $fileId)) throw new \RuntimeException('Không tìm thấy file đính kèm');
        $this->execute('UPDATE complaint_attachments SET deleted_at=NOW(), deleted_by=:user WHERE complaint_id=:complaint_id AND id=:id', ['complaint_id' => $complaintId, 'id' => $fileId, 'user' => $userId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $from = $this->fromSql();
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(cs.code='NEW'),0) AS new_count, COALESCE(SUM(cs.code IN ('VERIFYING','PROCESSING')),0) AS processing_count, COALESCE(SUM(cs.code='DONE'),0) AS done_count, COALESCE(SUM(cs.code='ESCALATED'),0) AS escalated_count, COALESCE(SUM(c.due_at IS NOT NULL AND c.due_at < NOW() AND COALESCE(cs.is_terminal,0)=0),0) AS overdue_count $from $where", $params) ?: [];
        return [
            'metrics' => array_map('intval', $metrics),
            'charts' => [
                'by_month' => $this->fetchAll("SELECT DATE_FORMAT(c.received_at, '%Y-%m') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY label DESC LIMIT 12", $params),
                'by_quarter' => $this->fetchAll("SELECT CONCAT(YEAR(c.received_at), '-Q', QUARTER(c.received_at)) AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY YEAR(c.received_at) DESC, QUARTER(c.received_at) DESC LIMIT 8", $params),
                'by_year' => $this->fetchAll("SELECT YEAR(c.received_at) AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY label DESC LIMIT 6", $params),
                'by_category' => $this->fetchAll("SELECT COALESCE(cc.name, 'Khác') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY value DESC", $params),
                'by_status' => $this->fetchAll("SELECT COALESCE(cs.name, 'Chưa cập nhật') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY value DESC", $params),
            ],
        ];
    }

    public function gisFeatures(array $filters = []): array
    {
        $filters['located'] = '1';
        $filters['page'] = 1;
        $filters['pageSize'] = 2000;
        return $this->paginate($filters)['items'];
    }

    public function report(array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['pageSize'] = 1000;
        $rows = $this->paginate($filters)['items'];
        $dashboard = $this->dashboard($filters)['metrics'];
        return [
            'title' => 'Báo cáo phản ánh - kiến nghị',
            'headers' => ['Mã', 'Tiêu đề', 'Người phản ánh', 'Hộ liên quan', 'Loại', 'Ưu tiên', 'Trạng thái', 'Phụ trách', 'Ngày tiếp nhận', 'Hạn xử lý', 'Quá hạn'],
            'rows' => array_map(fn($row) => [$row['complaint_code'], $row['title'], $row['reporter_name'], $row['household_code'], $row['category_name'], $row['priority_name'], $row['status_name'], $row['assigned_name'], $row['received_at'], $row['due_at'], $row['is_overdue'] ? 'Có' : 'Không'], $rows),
            'totalRows' => count($rows),
            'filters' => $filters,
            'summary' => [
                'Tổng tiếp nhận' => (int)($dashboard['total'] ?? 0),
                'Đã xử lý' => (int)($dashboard['done_count'] ?? 0),
                'Đang xử lý' => (int)($dashboard['processing_count'] ?? 0),
                'Quá hạn' => (int)($dashboard['overdue_count'] ?? 0),
                'Tỷ lệ hoàn thành' => ((int)($dashboard['total'] ?? 0) > 0 ? round(((int)($dashboard['done_count'] ?? 0) / (int)$dashboard['total']) * 100, 2) : 0) . '%',
            ],
            'generatedAt' => date('c'),
        ];
    }

    public function householdSearch(string $query): array
    {
        $q = '%' . trim($query) . '%';
        return array_map(fn($r) => ['id' => (int)$r['id'], 'label' => trim((string)$r['household_code'] . ' - ' . (string)$r['head_citizen_name']), 'code' => (string)$r['household_code'], 'head' => (string)$r['head_citizen_name'], 'phone' => (string)($r['phone'] ?? ''), 'address' => (string)($r['address'] ?? ''), 'area_code' => (string)($r['area_code'] ?? '')], $this->fetchAll('SELECT id, household_code, head_citizen_name, phone, address, area_code FROM households WHERE status <> "DELETED" AND (household_code LIKE :q OR head_citizen_name LIKE :q OR phone LIKE :q OR address LIKE :q) ORDER BY household_code ASC LIMIT 20', ['q' => $q]));
    }

    public function citizenSearch(string $query, ?int $householdId = null): array
    {
        $params = ['q' => '%' . trim($query) . '%'];
        $where = 'c.status <> "DELETED" AND (c.citizen_code LIKE :q OR c.full_name LIKE :q OR c.phone LIKE :q OR c.identity_number LIKE :q)';
        if ($householdId) {
            $where .= ' AND c.household_id=:household_id';
            $params['household_id'] = $householdId;
        }
        return array_map(fn($r) => ['id' => (int)$r['id'], 'label' => trim((string)$r['citizen_code'] . ' - ' . (string)$r['full_name']), 'name' => (string)$r['full_name'], 'phone' => (string)($r['phone'] ?? ''), 'household_id' => (int)$r['household_id'], 'household_code' => (string)$r['household_code'], 'address' => (string)($r['household_address'] ?? '')], $this->fetchAll('SELECT c.id, c.citizen_code, c.full_name, c.phone, c.household_id, h.household_code, h.address AS household_address FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE ' . $where . ' ORDER BY c.full_name ASC LIMIT 20', $params));
    }

    public function relatedSearch(string $targetType, string $query): array
    {
        $this->ensureSchema();
        $targetType = $this->targetType($targetType);
        $query = trim($query);
        if ($targetType === '' || mb_strlen($query, 'UTF-8') < 2) return [];
        if ($targetType === 'other') return [['target_type' => 'other', 'target_id' => 0, 'label' => $query, 'meta' => 'Khác']];
        $q = '%' . $query . '%';
        try {
            return match ($targetType) {
                'household' => array_map(fn($r) => $this->relatedRow('household', (int)$r['id'], trim($r['household_code'] . ' - ' . $r['head_citizen_name']), $r['address'] ?? ''), $this->fetchAll('SELECT id, household_code, head_citizen_name, address, phone FROM households WHERE status <> "DELETED" AND (household_code LIKE :q OR head_citizen_name LIKE :q OR address LIKE :q OR phone LIKE :q) ORDER BY household_code ASC LIMIT 20', ['q' => $q])),
                'citizen' => array_map(fn($r) => $this->relatedRow('citizen', (int)$r['id'], trim($r['citizen_code'] . ' - ' . $r['full_name']), trim(($r['household_code'] ?? '') . ' ' . ($r['identity_number'] ?? ''))), $this->fetchAll('SELECT c.id, c.citizen_code, c.full_name, c.identity_number, c.phone, h.household_code FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.status <> "DELETED" AND (c.citizen_code LIKE :q OR c.full_name LIKE :q OR c.identity_number LIKE :q OR c.phone LIKE :q OR h.household_code LIKE :q) ORDER BY c.full_name ASC LIMIT 20', ['q' => $q])),
                'public_asset' => array_map(fn($r) => $this->relatedRow('public_asset', (int)$r['id'], trim($r['asset_code'] . ' - ' . $r['asset_name']), trim(($r['type_name'] ?? '') . ' ' . ($r['address'] ?? ''))), $this->fetchAll('SELECT id, asset_code, asset_name, type_name, address FROM public_assets WHERE status <> "DELETED" AND (asset_code LIKE :q OR asset_name LIKE :q OR type_name LIKE :q OR address LIKE :q) ORDER BY asset_code ASC LIMIT 20', ['q' => $q])),
                'house' => array_map(fn($r) => $this->relatedRow('house', (int)$r['id'], trim($r['house_code'] . ' - ' . ($r['house_name'] ?: $r['house_type'] ?: 'Nhà ở')), trim(($r['household_code'] ?? '') . ' ' . ($r['head_citizen_name'] ?? '') . ' ' . ($r['address'] ?? ''))), $this->fetchAll('SELECT hs.id, hs.house_code, hs.house_name, hs.house_type, hs.address, h.household_code, h.head_citizen_name FROM houses hs INNER JOIN households h ON h.id=hs.household_id WHERE hs.status <> "DELETED" AND (hs.house_code LIKE :q OR hs.house_name LIKE :q OR hs.house_type LIKE :q OR hs.address LIKE :q OR h.household_code LIKE :q OR h.head_citizen_name LIKE :q) ORDER BY hs.house_code ASC LIMIT 20', ['q' => $q])),
                'business' => array_map(fn($r) => $this->relatedRow('business', (int)$r['id'], trim(($r['business_name'] ?: $r['household_code']) . ' - ' . ($r['owner_name'] ?: $r['head_citizen_name'])), trim(($r['business_sector'] ?? '') . ' ' . ($r['production_sector'] ?? '') . ' ' . ($r['tax_code'] ?? ''))), $this->fetchAll('SELECT hb.id, hb.business_name, hb.owner_name, hb.business_sector, hb.production_sector, hb.tax_code, h.household_code, h.head_citizen_name, h.address FROM household_business hb INNER JOIN households h ON h.id=hb.household_id WHERE hb.status <> "DELETED" AND (hb.business_name LIKE :q OR hb.owner_name LIKE :q OR hb.business_sector LIKE :q OR hb.production_sector LIKE :q OR hb.tax_code LIKE :q OR h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR h.address LIKE :q) ORDER BY h.household_code ASC, hb.id DESC LIMIT 20', ['q' => $q])),
                'agriculture' => array_map(fn($r) => $this->relatedRow('agriculture', (int)$r['id'], trim($r['parcel_code'] . ' - ' . ($r['field_area'] ?: $r['field_name'] ?: 'Sản xuất nông nghiệp')), trim(($r['owner_name'] ?? '') . ' ' . ($r['producer_name'] ?? '') . ' ' . ($r['current_crop'] ?? ''))), $this->fetchAll('SELECT p.id, p.parcel_code, p.field_area, p.field_name, o.name AS owner_name, pr.name AS producer_name, cs.crop AS current_crop FROM agri_land_parcels p INNER JOIN agri_stakeholders o ON o.id=p.owner_id INNER JOIN agri_stakeholders pr ON pr.id=p.producer_id LEFT JOIN (SELECT pp.parcel_id, s.crop FROM agri_crop_seasons s INNER JOIN agri_production_plots pp ON pp.id=s.plot_id WHERE s.status <> "DELETED" AND pp.status <> "DELETED" GROUP BY pp.parcel_id, s.crop) cs ON cs.parcel_id=p.id WHERE p.status <> "DELETED" AND (p.parcel_code LIKE :q OR p.field_area LIKE :q OR p.field_name LIKE :q OR o.name LIKE :q OR pr.name LIKE :q OR cs.crop LIKE :q) ORDER BY p.parcel_code ASC LIMIT 20', ['q' => $q])),
                'livestock' => array_map(fn($r) => $this->relatedRow('livestock', (int)$r['id'], trim($r['animal_type'] . ' - Hộ ' . $r['household_code']), trim(($r['head_citizen_name'] ?? '') . ' ' . ($r['breed'] ?? '') . ' SL: ' . (string)($r['quantity'] ?? 0))), $this->fetchAll('SELECT l.id, l.animal_type, l.breed, l.quantity, h.household_code, h.head_citizen_name, h.address FROM livestock l INNER JOIN households h ON h.id=l.household_id WHERE l.status <> "DELETED" AND (l.animal_type LIKE :q OR l.breed LIKE :q OR h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR h.address LIKE :q) ORDER BY h.household_code ASC, l.animal_type ASC LIMIT 20', ['q' => $q])),
                'gis' => array_map(fn($r) => $this->relatedRow('gis', (int)$r['id'], trim($r['area_code'] . ' - ' . $r['name']), $r['note'] ?? ''), $this->fetchAll('SELECT id, area_code, name, note FROM gis_areas WHERE status <> "DELETED" AND (area_code LIKE :q OR name LIKE :q OR note LIKE :q) ORDER BY area_code ASC LIMIT 20', ['q' => $q])),
                default => [],
            };
        } catch (\Throwable) {
            return [];
        }
    }

    private function fromSql(): string
    {
        return 'FROM complaints c LEFT JOIN complaint_categories cc ON cc.id=c.category_id LEFT JOIN complaint_priorities cp ON cp.id=c.priority_id LEFT JOIN complaint_statuses cs ON cs.id=c.status_id LEFT JOIN households h ON h.id=c.household_id LEFT JOIN citizens ct ON ct.id=c.citizen_id';
    }

    private function selectSql(): string
    {
        return 'SELECT c.*, cc.name AS category_name, cp.name AS priority_name, cp.code AS priority_code, cs.name AS status_name, cs.code AS status_code, cs.marker_color, cs.is_terminal, h.household_code, h.head_citizen_name, h.address AS household_address, h.area_code, ct.full_name AS citizen_name, (c.due_at IS NOT NULL AND c.due_at < NOW() AND COALESCE(cs.is_terminal,0)=0) AS is_overdue';
    }

    private function where(array $filters, bool $withOrderFilters = true): array
    {
        $where = ['c.soft_status <> "DELETED"'];
        $params = [];
        foreach (['category_id' => 'c.category_id', 'priority_id' => 'c.priority_id', 'status_id' => 'c.status_id', 'assigned_user_id' => 'c.assigned_user_id', 'receiver_user_id' => 'c.receiver_user_id', 'household_id' => 'c.household_id'] as $key => $column) {
            $value = $filters[$key] ?? $filters[$this->camel($key)] ?? '';
            if ($value !== '' && $value !== null) {
                $where[] = "$column=:$key";
                $params[$key] = (int)$value;
            }
        }
        $area = trim((string)($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') {
            $where[] = 'h.area_code LIKE :area_code';
            $params['area_code'] = '%' . $area . '%';
        }
        if (($filters['overdue'] ?? '') !== '') {
            $where[] = ((string)$filters['overdue'] === '1') ? '(c.due_at IS NOT NULL AND c.due_at < NOW() AND COALESCE(cs.is_terminal,0)=0)' : '(c.due_at IS NULL OR c.due_at >= NOW() OR COALESCE(cs.is_terminal,0)=1)';
        }
        if (($filters['located'] ?? '') !== '') {
            $where[] = ((string)$filters['located'] === '1') ? '(c.latitude IS NOT NULL AND c.longitude IS NOT NULL)' : '(c.latitude IS NULL OR c.longitude IS NULL)';
        }
        $from = trim((string)($filters['date_from'] ?? $filters['dateFrom'] ?? ''));
        $to = trim((string)($filters['date_to'] ?? $filters['dateTo'] ?? ''));
        if ($from !== '') { $where[] = 'DATE(c.received_at) >= :date_from'; $params['date_from'] = $from; }
        if ($to !== '') { $where[] = 'DATE(c.received_at) <= :date_to'; $params['date_to'] = $to; }
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(c.complaint_code LIKE :q OR c.title LIKE :q OR c.detail LIKE :q OR c.reporter_name LIKE :q OR c.reporter_phone LIKE :q OR c.assigned_name LIKE :q OR h.household_code LIKE :q OR h.head_citizen_name LIKE :q OR ct.full_name LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function params(array $data, int $userId, string $userName, ?array $existing): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $detail = trim((string)($data['detail'] ?? $data['content'] ?? ''));
        $reporter = trim((string)($data['reporter_name'] ?? $data['reporterName'] ?? ''));
        if ($title === '') throw new \RuntimeException('Tiêu đề là bắt buộc');
        if ($detail === '') throw new \RuntimeException('Nội dung chi tiết là bắt buộc');
        if ($reporter === '') throw new \RuntimeException('Người phản ánh là bắt buộc');
        $statusId = $this->validId('complaint_statuses', $data['status_id'] ?? $data['statusId'] ?? $existing['status_id'] ?? null, true) ?: $this->defaultStatusId();
        return [
            'title' => $title,
            'detail' => $detail,
            'received_at' => $this->dateTime($data['received_at'] ?? $data['receivedAt'] ?? $existing['received_at'] ?? date('Y-m-d H:i:s'), true, 'Ngày tiếp nhận không hợp lệ'),
            'receiver_user_id' => $this->nullableInt($data['receiver_user_id'] ?? $data['receiverUserId'] ?? $existing['receiver_user_id'] ?? $userId),
            'receiver_name' => $this->nullable($data['receiver_name'] ?? $data['receiverName'] ?? $existing['receiver_name'] ?? $userName),
            'reporter_name' => $reporter,
            'reporter_phone' => $this->nullable($data['reporter_phone'] ?? $data['reporterPhone'] ?? ''),
            'household_id' => $this->nullableInt($data['household_id'] ?? $data['householdId'] ?? null),
            'citizen_id' => $this->nullableInt($data['citizen_id'] ?? $data['citizenId'] ?? null),
            'category_id' => $this->validId('complaint_categories', $data['category_id'] ?? $data['categoryId'] ?? null, false),
            'priority_id' => $this->validId('complaint_priorities', $data['priority_id'] ?? $data['priorityId'] ?? null, false) ?: $this->defaultPriorityId(),
            'status_id' => $statusId,
            'assigned_user_id' => $this->nullableInt($data['assigned_user_id'] ?? $data['assignedUserId'] ?? $existing['assigned_user_id'] ?? null),
            'assigned_name' => $this->nullable($data['assigned_name'] ?? $data['assignedName'] ?? $existing['assigned_name'] ?? ''),
            'due_at' => $this->dateTime($data['due_at'] ?? $data['dueAt'] ?? null, false, 'Hạn xử lý không hợp lệ'),
            'latitude' => $this->coord($data['latitude'] ?? null),
            'longitude' => $this->coord($data['longitude'] ?? null),
            'gps_accuracy' => $this->nullableNumber($data['gps_accuracy'] ?? $data['gpsAccuracy'] ?? null),
            'result_rating' => $this->rating($data['result_rating'] ?? $data['resultRating'] ?? $existing['result_rating'] ?? null),
            'result_note' => $this->nullable($data['result_note'] ?? $data['resultNote'] ?? $existing['result_note'] ?? ''),
            'closed_at' => $this->statusTerminal($statusId) ? ($existing['closed_at'] ?? date('Y-m-d H:i:s')) : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int)$row['id'];
        foreach (['category_id','priority_id','status_id','assigned_user_id','receiver_user_id','household_id','citizen_id'] as $key) $row[$key] = $row[$key] !== null ? (int)$row[$key] : null;
        foreach (['latitude','longitude','gps_accuracy'] as $key) $row[$key] = $row[$key] !== null ? (float)$row[$key] : null;
        $row['is_overdue'] = !empty($row['is_overdue']);
        $row['marker_color'] = (string)($row['marker_color'] ?: 'red');
        return $row;
    }

    private function histories(int $id): array
    {
        return $this->fetchAll('SELECT ch.*, cs.name AS status_name FROM complaint_histories ch LEFT JOIN complaint_statuses cs ON cs.id=ch.status_id WHERE ch.complaint_id=:id ORDER BY ch.created_at DESC, ch.id DESC', ['id' => $id]);
    }

    private function assignments(int $id): array
    {
        return $this->fetchAll('SELECT * FROM complaint_assignments WHERE complaint_id=:id ORDER BY assigned_at DESC, id DESC', ['id' => $id]);
    }

    private function attachments(int $id): array
    {
        return array_map(fn($row) => $this->normalizeAttachment($row), $this->fetchAll('SELECT * FROM complaint_attachments WHERE complaint_id=:id AND deleted_at IS NULL ORDER BY created_at DESC, id DESC', ['id' => $id]));
    }

    private function normalizeAttachment(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['complaint_id'] = (int)$row['complaint_id'];
        $row['file_size'] = (int)$row['file_size'];
        $row['preview_url'] = '/api/complaints/' . $row['complaint_id'] . '/attachments/' . $row['id'] . '/preview';
        $row['download_url'] = '/api/complaints/' . $row['complaint_id'] . '/attachments/' . $row['id'] . '/download';
        return $row;
    }

    private function links(int $id): array
    {
        return array_map(fn($row) => $this->normalizeLink($row), $this->fetchAll('SELECT * FROM complaint_links WHERE complaint_id=:id ORDER BY target_type ASC, id ASC', ['id' => $id]));
    }

    private function syncLinks(int $id, array $data, int $userId): void
    {
        $this->execute('DELETE FROM complaint_links WHERE complaint_id=:id', ['id' => $id]);
        $links = $data['related_links'] ?? $data['relatedLinks'] ?? $data['links'] ?? [];
        if (is_string($links)) $links = json_decode($links, true) ?: [];
        if (!is_array($links)) $links = [];
        $householdId = $this->nullableInt($data['household_id'] ?? $data['householdId'] ?? null);
        $citizenId = $this->nullableInt($data['citizen_id'] ?? $data['citizenId'] ?? null);
        if ($householdId && !$this->hasLink($links, 'household', $householdId)) $links[] = ['target_type' => 'household', 'target_id' => $householdId, 'label' => 'Hộ gia đình'];
        if ($citizenId && !$this->hasLink($links, 'citizen', $citizenId)) $links[] = ['target_type' => 'citizen', 'target_id' => $citizenId, 'label' => 'Nhân khẩu'];
        $allowed = array_column($this->linkTypes(), 'value');
        foreach ($links as $link) {
            $type = $this->targetType((string)($link['target_type'] ?? $link['type'] ?? ''));
            $targetId = (int)($link['target_id'] ?? $link['id'] ?? 0);
            $label = $this->nullable($link['label'] ?? '');
            if (!in_array($type, $allowed, true)) continue;
            if ($type !== 'other' && $targetId <= 0) continue;
            if ($type === 'other' && $label === null) continue;
            $this->execute('INSERT IGNORE INTO complaint_links (complaint_id, target_type, target_id, label, created_by) VALUES (:id,:type,:target,:label,:user)', ['id' => $id, 'type' => $type, 'target' => $targetId, 'label' => $this->nullable($link['label'] ?? ''), 'user' => $userId]);
        }
    }

    private function seedCatalogs(): void
    {
        $categories = [['security','An ninh trật tự'],['environment','Vệ sinh môi trường'],['electricity','Điện'],['water','Nước'],['traffic','Giao thông'],['land','Đất đai'],['construction','Xây dựng'],['noise','Tiếng ồn'],['pets','Vật nuôi'],['policy','Chính sách'],['poor_household','Hộ nghèo'],['other','Khác']];
        $order = 10;
        foreach ($categories as [$code, $name]) { $this->execute('INSERT INTO complaint_categories (code,name,sort_order) VALUES (:code,:name,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'sort_order' => $order]); $order += 10; }
        $priorities = [['URGENT','Khẩn cấp'],['HIGH','Cao'],['NORMAL','Bình thường'],['LOW','Thấp']];
        $order = 10;
        foreach ($priorities as [$code, $name]) { $this->execute('INSERT INTO complaint_priorities (code,name,sort_order) VALUES (:code,:name,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'sort_order' => $order]); $order += 10; }
        $statuses = [['NEW','Mới tiếp nhận','red',0],['VERIFYING','Đang xác minh','yellow',0],['PROCESSING','Đang xử lý','yellow',0],['DONE','Đã hoàn thành','green',1],['ESCALATED','Đã chuyển cấp trên','yellow',1],['REJECTED','Không đủ điều kiện xử lý','red',1]];
        $order = 10;
        foreach ($statuses as [$code, $name, $color, $terminal]) { $this->execute('INSERT INTO complaint_statuses (code,name,marker_color,is_terminal,sort_order) VALUES (:code,:name,:color,:terminal,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), marker_color=VALUES(marker_color), is_terminal=VALUES(is_terminal), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'color' => $color, 'terminal' => $terminal, 'sort_order' => $order]); $order += 10; }
    }

    private function ensureComplaintLinkIndex(): void
    {
        $rows = $this->fetchAll('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="complaint_links" AND INDEX_NAME="uq_complaint_links_target" ORDER BY SEQ_IN_INDEX');
        $columns = array_map(fn($row) => (string)$row['COLUMN_NAME'], $rows);
        if ($columns === ['complaint_id', 'target_type', 'target_id', 'label']) return;
        try { $this->execute('ALTER TABLE complaint_links DROP INDEX uq_complaint_links_target'); } catch (\Throwable) {}
        try { $this->execute('ALTER TABLE complaint_links ADD UNIQUE KEY uq_complaint_links_target (complaint_id, target_type, target_id, label)'); } catch (\Throwable) {}
    }

    private function catalog(string $table): array { return array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name']], $this->fetchAll("SELECT id, code, name FROM $table WHERE is_active=1 ORDER BY sort_order ASC, name ASC")); }
    private function statusCatalog(): array { return array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name'], 'marker_color' => (string)$r['marker_color'], 'is_terminal' => (bool)$r['is_terminal']], $this->fetchAll('SELECT id, code, name, marker_color, is_terminal FROM complaint_statuses WHERE is_active=1 ORDER BY sort_order ASC, name ASC')); }
    private function validId(string $table, mixed $value, bool $allowNull): ?int { $id = $this->nullableInt($value); if (!$id) { if ($allowNull) return null; return null; } $row = $this->fetchOne("SELECT id FROM $table WHERE id=:id AND is_active=1", ['id' => $id]); if (!$row) throw new \RuntimeException('Danh mục không hợp lệ'); return $id; }
    private function defaultStatusId(): int { return (int)(($this->fetchOne('SELECT id FROM complaint_statuses WHERE code="NEW"') ?: [])['id'] ?? 0); }
    private function defaultPriorityId(): int { return (int)(($this->fetchOne('SELECT id FROM complaint_priorities WHERE code="NORMAL"') ?: [])['id'] ?? 0); }
    private function statusTerminal(int $id): bool { return (bool)(($this->fetchOne('SELECT is_terminal FROM complaint_statuses WHERE id=:id', ['id' => $id]) ?: [])['is_terminal'] ?? false); }
    private function nextCode(): string { $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM complaints'); return 'PAKN-' . date('Y') . '-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT); }
    private function relatedRow(string $type, int $id, string $label, string $meta = ''): array { return ['target_type' => $type, 'target_id' => $id, 'label' => trim($label), 'meta' => trim($meta), 'type_label' => $this->linkTypeLabel($type)]; }
    private function normalizeLink(array $row): array { $type = (string)$row['target_type']; return ['id' => (int)$row['id'], 'complaint_id' => (int)$row['complaint_id'], 'target_type' => $type, 'target_id' => (int)$row['target_id'], 'label' => (string)($row['label'] ?: $this->linkTypeLabel($type) . ' #' . (int)$row['target_id']), 'type_label' => $this->linkTypeLabel($type), 'created_at' => $row['created_at'] ?? null]; }
    private function linkTypes(): array { return [['value'=>'household','label'=>'Hộ gia đình'],['value'=>'citizen','label'=>'Nhân khẩu'],['value'=>'public_asset','label'=>'Công trình công cộng'],['value'=>'house','label'=>'Nhà ở'],['value'=>'business','label'=>'Hộ sản xuất kinh doanh'],['value'=>'agriculture','label'=>'Sản xuất nông nghiệp'],['value'=>'livestock','label'=>'Vật nuôi'],['value'=>'gis','label'=>'GIS'],['value'=>'other','label'=>'Khác']]; }
    private function linkTypeLabel(string $type): string { foreach ($this->linkTypes() as $item) if ($item['value'] === $type) return $item['label']; return $type; }
    private function targetType(string $type): string { $type = preg_replace('/[^a-z_]/', '', strtolower(trim($type))); return match ($type) { 'person', 'persons', 'citizens' => 'citizen', 'publicassets', 'public_assets' => 'public_asset', 'household_business', 'household_businesses', 'business_household' => 'business', 'agri' => 'agriculture', default => $type }; }
    private function hasLink(array $links, string $type, int $targetId): bool { foreach ($links as $link) if ($this->targetType((string)($link['target_type'] ?? $link['type'] ?? '')) === $type && (int)($link['target_id'] ?? $link['id'] ?? 0) === $targetId) return true; return false; }
    private function userName(int $id): string { $row = $this->fetchOne('SELECT COALESCE(NULLIF(display_name,""), email) AS name FROM users WHERE id=:id', ['id' => $id]); return (string)($row['name'] ?? ''); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : $value; }
    private function nullableInt(mixed $value): ?int { $value = trim((string)($value ?? '')); if ($value === '') return null; $id = (int)$value; return $id > 0 ? $id : null; }
    private function nullableNumber(mixed $value): ?float { $value = trim((string)($value ?? '')); return $value === '' ? null : (float)str_replace(',', '.', $value); }
    private function coord(mixed $value): ?float { $number = $this->nullableNumber($value); return $number === null ? null : max(-180, min(180, $number)); }
    private function dateTime(mixed $value, bool $required, string $message): ?string { $value = trim((string)($value ?? '')); if ($value === '') { if ($required) throw new \RuntimeException($message); return null; } if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value . ' 00:00:00'; if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value)) throw new \RuntimeException($message); return str_replace('T', ' ', strlen($value) === 16 ? $value . ':00' : $value); }
    private function rating(mixed $value): ?string { $value = strtoupper(trim((string)($value ?? ''))); return in_array($value, ['SATISFIED','NEEDS_MORE','DISAGREE'], true) ? $value : null; }
    private function camel(string $value): string { return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $value); }
}
