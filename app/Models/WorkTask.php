<?php

namespace App\Models;

use App\Core\BaseModel;

final class WorkTask extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS work_task_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_work_task_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS work_task_priorities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_work_task_priorities_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS work_task_statuses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_terminal TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_work_task_statuses_active (is_active),
  KEY idx_work_task_statuses_terminal (is_terminal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS work_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category_id BIGINT UNSIGNED NULL,
  priority_id BIGINT UNSIGNED NULL,
  status_id BIGINT UNSIGNED NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  assigned_name VARCHAR(255) NULL,
  start_at DATETIME NULL,
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  related_module VARCHAR(80) NULL,
  related_id BIGINT UNSIGNED NULL,
  area_code VARCHAR(80) NULL,
  note TEXT NULL,
  soft_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_work_tasks_search (task_code, title),
  KEY idx_work_tasks_category (category_id),
  KEY idx_work_tasks_priority (priority_id),
  KEY idx_work_tasks_status (status_id),
  KEY idx_work_tasks_assigned (assigned_user_id),
  KEY idx_work_tasks_due (due_at),
  KEY idx_work_tasks_area (area_code),
  KEY idx_work_tasks_related (related_module, related_id),
  KEY idx_work_tasks_soft_status (soft_status),
  CONSTRAINT fk_work_tasks_category FOREIGN KEY (category_id) REFERENCES work_task_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_work_tasks_priority FOREIGN KEY (priority_id) REFERENCES work_task_priorities(id) ON DELETE SET NULL,
  CONSTRAINT fk_work_tasks_status FOREIGN KEY (status_id) REFERENCES work_task_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS work_task_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(255) NULL,
  content TEXT NOT NULL,
  status_id BIGINT UNSIGNED NULL,
  progress_percent TINYINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_work_task_logs_task (task_id),
  KEY idx_work_task_logs_status (status_id),
  CONSTRAINT fk_work_task_logs_task FOREIGN KEY (task_id) REFERENCES work_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_work_task_logs_status FOREIGN KEY (status_id) REFERENCES work_task_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS work_task_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT UNSIGNED NOT NULL,
  log_id BIGINT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('IMAGE','VIDEO','PDF','DOCUMENT','OTHER') NOT NULL DEFAULT 'OTHER',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_work_task_attachments_task (task_id),
  KEY idx_work_task_attachments_log (log_id),
  KEY idx_work_task_attachments_kind (file_kind),
  CONSTRAINT fk_work_task_attachments_task FOREIGN KEY (task_id) REFERENCES work_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_work_task_attachments_log FOREIGN KEY (log_id) REFERENCES work_task_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->seedCatalogs();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'categories' => $this->catalog('work_task_categories'),
            'priorities' => $this->catalog('work_task_priorities'),
            'statuses' => $this->statusCatalog(),
            'related_modules' => [
                ['value' => '', 'label' => 'Không liên kết'],
                ['value' => 'household', 'label' => 'Hộ gia đình'],
                ['value' => 'citizen', 'label' => 'Nhân khẩu'],
                ['value' => 'public_asset', 'label' => 'Công trình công cộng'],
                ['value' => 'house', 'label' => 'Nhà ở'],
                ['value' => 'business', 'label' => 'Hộ sản xuất kinh doanh'],
                ['value' => 'agriculture', 'label' => 'Sản xuất nông nghiệp'],
                ['value' => 'livestock', 'label' => 'Vật nuôi'],
                ['value' => 'gis', 'label' => 'GIS'],
            ],
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters);
        $order = $this->listOrder($filters, [
            'task_code' => 't.task_code',
            'title' => 't.title',
            'category' => 'c.name',
            'priority' => 'p.sort_order',
            'status' => 's.sort_order',
            'assigned' => 't.assigned_name',
            'progress' => 't.progress_percent',
            'start_at' => 't.start_at',
            'due_at' => 't.due_at',
            'overdue' => 'is_overdue',
        ], 'due_at', 'ASC', ['t.id DESC']);
        $from = $this->fromSql();
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " $from $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalize($row), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne($this->selectSql() . ' ' . $this->fromSql() . ' WHERE t.id=:id AND t.soft_status <> "DELETED"', ['id' => $id]);
        if (!$row) return null;
        $item = $this->normalize($row);
        $item['logs'] = $this->logs($id);
        $item['attachments'] = $this->attachments($id);
        return $item;
    }

    public function upsert(array $data, int $userId, string $userName, ?int $id = null): array
    {
        $this->ensureSchema();
        $existing = $id ? $this->find($id) : null;
        if ($id && !$existing) throw new \RuntimeException('Không tìm thấy công việc');
        $params = $this->params($data, $userId, $existing);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE work_tasks SET title=:title, description=:description, category_id=:category_id, priority_id=:priority_id, status_id=:status_id, assigned_user_id=:assigned_user_id, assigned_name=:assigned_name, start_at=:start_at, due_at=:due_at, completed_at=:completed_at, progress_percent=:progress_percent, related_module=:related_module, related_id=:related_id, area_code=:area_code, note=:note, updated_by=:updated_by WHERE id=:id AND soft_status <> "DELETED"', $params);
            return $this->find($id);
        }
        $params['task_code'] = $this->nextCode();
        $newId = $this->insert('INSERT INTO work_tasks (task_code, title, description, category_id, priority_id, status_id, assigned_user_id, assigned_name, start_at, due_at, completed_at, progress_percent, related_module, related_id, area_code, note, created_by, updated_by) VALUES (:task_code, :title, :description, :category_id, :priority_id, :status_id, :assigned_user_id, :assigned_name, :start_at, :due_at, :completed_at, :progress_percent, :related_module, :related_id, :area_code, :note, :created_by, :updated_by)', $params);
        $this->addLog($newId, ['content' => 'Tạo công việc', 'status_id' => $params['status_id'], 'progress_percent' => $params['progress_percent']], $userId, $userName);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy công việc');
        $this->execute('UPDATE work_tasks SET soft_status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    public function addLog(int $id, array $data, int $userId, string $userName): array
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy công việc');
        $content = trim((string)($data['content'] ?? $data['note'] ?? ''));
        if ($content === '') throw new \RuntimeException('Nội dung nhật ký là bắt buộc');
        $statusId = $this->validId('work_task_statuses', $data['status_id'] ?? $data['statusId'] ?? null, true);
        $progress = $this->progress($data['progress_percent'] ?? $data['progressPercent'] ?? null, true);
        $logId = $this->insert('INSERT INTO work_task_logs (task_id, actor_user_id, actor_name, content, status_id, progress_percent) VALUES (:id,:user,:name,:content,:status,:progress)', ['id' => $id, 'user' => $userId, 'name' => $userName, 'content' => $content, 'status' => $statusId, 'progress' => $progress]);
        $sets = ['updated_by=:user'];
        $params = ['id' => $id, 'user' => $userId];
        if ($statusId) {
            $sets[] = 'status_id=:status';
            $sets[] = $this->statusTerminal($statusId) ? 'completed_at=COALESCE(completed_at,NOW())' : 'completed_at=NULL';
            $params['status'] = $statusId;
        }
        if ($progress !== null) {
            $sets[] = 'progress_percent=:progress';
            $params['progress'] = $progress;
        }
        $this->execute('UPDATE work_tasks SET ' . implode(', ', $sets) . ' WHERE id=:id', $params);
        return $this->logs($id)[0] ?? ['id' => $logId];
    }

    public function addAttachment(int $id, array $stored, array $file, int $userId, ?int $logId = null): array
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy công việc');
        $mime = (string)$stored['mime'];
        $kind = str_starts_with($mime, 'image/') ? 'IMAGE' : (str_starts_with($mime, 'video/') ? 'VIDEO' : ($mime === 'application/pdf' ? 'PDF' : 'DOCUMENT'));
        $attachmentId = $this->insert('INSERT INTO work_task_attachments (task_id, log_id, original_name, stored_path, mime_type, file_size, file_kind, created_by) VALUES (:id,:log_id,:name,:path,:mime,:size,:kind,:user)', ['id' => $id, 'log_id' => $logId, 'name' => basename((string)($file['name'] ?? 'attachment')), 'path' => $stored['file_path'], 'mime' => $mime, 'size' => (int)($file['size'] ?? 0), 'kind' => $kind, 'user' => $userId]);
        return $this->attachment($id, $attachmentId) ?? ['id' => $attachmentId];
    }

    public function attachment(int $taskId, int $fileId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM work_task_attachments WHERE task_id=:task_id AND id=:id AND deleted_at IS NULL', ['task_id' => $taskId, 'id' => $fileId]);
        return $row ? $this->normalizeAttachment($row) : null;
    }

    public function deleteAttachment(int $taskId, int $fileId, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->attachment($taskId, $fileId)) throw new \RuntimeException('Không tìm thấy file đính kèm');
        $this->execute('UPDATE work_task_attachments SET deleted_at=NOW(), deleted_by=:user WHERE task_id=:task_id AND id=:id', ['task_id' => $taskId, 'id' => $fileId, 'user' => $userId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters);
        $from = $this->fromSql();
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(s.code='NEW'),0) AS new_count, COALESCE(SUM(s.code IN ('ASSIGNED','IN_PROGRESS')),0) AS processing_count, COALESCE(SUM(s.code='DONE'),0) AS done_count, COALESCE(SUM(t.due_at IS NOT NULL AND t.due_at < NOW() AND COALESCE(s.is_terminal,0)=0),0) AS overdue_count, COALESCE(ROUND(AVG(t.progress_percent)),0) AS avg_progress $from $where", $params) ?: [];
        return [
            'metrics' => array_map('intval', $metrics),
            'charts' => [
                'by_month' => $this->fetchAll("SELECT DATE_FORMAT(COALESCE(t.start_at,t.created_at), '%Y-%m') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY label DESC LIMIT 12", $params),
                'by_category' => $this->fetchAll("SELECT COALESCE(c.name, 'Khác') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY value DESC", $params),
                'by_status' => $this->fetchAll("SELECT COALESCE(s.name, 'Chưa cập nhật') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY value DESC", $params),
            ],
        ];
    }

    public function report(array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['pageSize'] = 100;
        $data = $this->paginate($filters);
        $rows = array_map(fn($r) => [
            $r['task_code'],
            $r['title'],
            $r['category_name'] ?? '',
            $r['priority_name'] ?? '',
            $r['status_name'] ?? '',
            $r['assigned_name'] ?? '',
            $r['progress_percent'] . '%',
            $r['due_at'] ?? '',
            $r['is_overdue'] ? 'Quá hạn' : '',
        ], $data['items']);
        return [
            'title' => 'Báo cáo công việc',
            'headers' => ['Mã', 'Tiêu đề', 'Loại', 'Ưu tiên', 'Trạng thái', 'Phụ trách', 'Tiến độ', 'Hạn', 'Quá hạn'],
            'rows' => $rows,
            'totalRows' => $data['total'],
            'summary' => $this->dashboard($filters)['metrics'] ?? [],
        ];
    }

    private function fromSql(): string
    {
        return 'FROM work_tasks t LEFT JOIN work_task_categories c ON c.id=t.category_id LEFT JOIN work_task_priorities p ON p.id=t.priority_id LEFT JOIN work_task_statuses s ON s.id=t.status_id';
    }

    private function selectSql(): string
    {
        return 'SELECT t.*, c.name AS category_name, c.code AS category_code, p.name AS priority_name, p.code AS priority_code, s.name AS status_name, s.code AS status_code, s.is_terminal, (t.due_at IS NOT NULL AND t.due_at < NOW() AND COALESCE(s.is_terminal,0)=0) AS is_overdue';
    }

    private function where(array $filters): array
    {
        $where = ['t.soft_status <> "DELETED"'];
        $params = [];
        foreach (['category_id' => 't.category_id', 'priority_id' => 't.priority_id', 'status_id' => 't.status_id', 'assigned_user_id' => 't.assigned_user_id'] as $key => $column) {
            $value = $filters[$key] ?? $filters[$this->camel($key)] ?? '';
            if ($value !== '' && $value !== null) {
                $where[] = "$column=:$key";
                $params[$key] = (int)$value;
            }
        }
        $area = trim((string)($filters['area_code'] ?? $filters['areaCode'] ?? ''));
        if ($area !== '') {
            $where[] = 't.area_code LIKE :area_code';
            $params['area_code'] = '%' . $area . '%';
        }
        $from = trim((string)($filters['date_from'] ?? $filters['dateFrom'] ?? ''));
        $to = trim((string)($filters['date_to'] ?? $filters['dateTo'] ?? ''));
        if ($from !== '') { $where[] = 'DATE(COALESCE(t.start_at,t.created_at)) >= :date_from'; $params['date_from'] = $from; }
        if ($to !== '') { $where[] = 'DATE(COALESCE(t.start_at,t.created_at)) <= :date_to'; $params['date_to'] = $to; }
        if (($filters['overdue'] ?? '') !== '') {
            $where[] = ((string)$filters['overdue'] === '1') ? '(t.due_at IS NOT NULL AND t.due_at < NOW() AND COALESCE(s.is_terminal,0)=0)' : '(t.due_at IS NULL OR t.due_at >= NOW() OR COALESCE(s.is_terminal,0)=1)';
        }
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(t.task_code LIKE :q OR t.title LIKE :q OR t.description LIKE :q OR t.assigned_name LIKE :q OR t.area_code LIKE :q OR t.note LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function params(array $data, int $userId, ?array $existing): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') throw new \RuntimeException('Tiêu đề công việc là bắt buộc');
        $statusId = $this->validId('work_task_statuses', $data['status_id'] ?? $data['statusId'] ?? $existing['status_id'] ?? null, true) ?: $this->defaultStatusId();
        $progress = $this->progress($data['progress_percent'] ?? $data['progressPercent'] ?? $existing['progress_percent'] ?? null, false);
        if ($progress === null) $progress = $this->defaultProgress($statusId);
        return [
            'title' => $title,
            'description' => $this->nullable($data['description'] ?? ''),
            'category_id' => $this->validId('work_task_categories', $data['category_id'] ?? $data['categoryId'] ?? null, false),
            'priority_id' => $this->validId('work_task_priorities', $data['priority_id'] ?? $data['priorityId'] ?? null, false) ?: $this->defaultPriorityId(),
            'status_id' => $statusId,
            'assigned_user_id' => $this->nullableInt($data['assigned_user_id'] ?? $data['assignedUserId'] ?? null),
            'assigned_name' => $this->nullable($data['assigned_name'] ?? $data['assignedName'] ?? ''),
            'start_at' => $this->dateTime($data['start_at'] ?? $data['startAt'] ?? null, false, 'Ngày bắt đầu không hợp lệ'),
            'due_at' => $this->dateTime($data['due_at'] ?? $data['dueAt'] ?? null, false, 'Hạn hoàn thành không hợp lệ'),
            'completed_at' => $this->statusTerminal($statusId) ? ($existing['completed_at'] ?? date('Y-m-d H:i:s')) : null,
            'progress_percent' => $progress,
            'related_module' => $this->targetType((string)($data['related_module'] ?? $data['relatedModule'] ?? '')),
            'related_id' => $this->nullableInt($data['related_id'] ?? $data['relatedId'] ?? null),
            'area_code' => $this->nullable($data['area_code'] ?? $data['areaCode'] ?? ''),
            'note' => $this->nullable($data['note'] ?? ''),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function seedCatalogs(): void
    {
        $categories = [
            ['fund_collection', 'Thu quỹ'],
            ['household_check', 'Kiểm tra hộ'],
            ['gift_distribution', 'Phát quà'],
            ['environment_cleanup', 'Vệ sinh môi trường'],
            ['patrol', 'Tuần tra'],
            ['public_asset_check', 'Kiểm tra công trình'],
            ['production_check', 'Kiểm tra sản xuất'],
            ['other', 'Khác'],
        ];
        $order = 10;
        foreach ($categories as [$code, $name]) {
            $this->execute('INSERT INTO work_task_categories (code,name,sort_order) VALUES (:code,:name,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'sort_order' => $order]);
            $order += 10;
        }
        $priorities = [['URGENT','Khẩn cấp'],['HIGH','Cao'],['NORMAL','Bình thường'],['LOW','Thấp']];
        $order = 10;
        foreach ($priorities as [$code, $name]) {
            $this->execute('INSERT INTO work_task_priorities (code,name,sort_order) VALUES (:code,:name,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'sort_order' => $order]);
            $order += 10;
        }
        $statuses = [['NEW','Mới tạo',0,0],['ASSIGNED','Đã giao',10,0],['IN_PROGRESS','Đang thực hiện',50,0],['WAITING','Tạm dừng/chờ xử lý',50,0],['DONE','Đã hoàn thành',100,1],['CANCELLED','Đã hủy',0,1]];
        $order = 10;
        foreach ($statuses as [$code, $name, $progress, $terminal]) {
            $this->execute('INSERT INTO work_task_statuses (code,name,progress_percent,is_terminal,sort_order) VALUES (:code,:name,:progress,:terminal,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), progress_percent=VALUES(progress_percent), is_terminal=VALUES(is_terminal), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'progress' => $progress, 'terminal' => $terminal, 'sort_order' => $order]);
            $order += 10;
        }
    }

    private function catalog(string $table): array
    {
        return array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name']], $this->fetchAll("SELECT id, code, name FROM $table WHERE is_active=1 ORDER BY sort_order ASC, name ASC"));
    }

    private function statusCatalog(): array
    {
        return array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name'], 'progress_percent' => (int)$r['progress_percent'], 'is_terminal' => (bool)$r['is_terminal']], $this->fetchAll('SELECT id, code, name, progress_percent, is_terminal FROM work_task_statuses WHERE is_active=1 ORDER BY sort_order ASC, name ASC'));
    }

    private function logs(int $id): array
    {
        return $this->fetchAll('SELECT l.*, s.name AS status_name FROM work_task_logs l LEFT JOIN work_task_statuses s ON s.id=l.status_id WHERE l.task_id=:id ORDER BY l.created_at DESC, l.id DESC', ['id' => $id]);
    }

    private function attachments(int $id): array
    {
        return array_map(fn($row) => $this->normalizeAttachment($row), $this->fetchAll('SELECT * FROM work_task_attachments WHERE task_id=:id AND deleted_at IS NULL ORDER BY created_at DESC, id DESC', ['id' => $id]));
    }

    private function normalize(array $row): array
    {
        $row['id'] = (int)$row['id'];
        foreach (['category_id','priority_id','status_id','assigned_user_id','related_id'] as $key) $row[$key] = $row[$key] !== null ? (int)$row[$key] : null;
        $row['progress_percent'] = (int)$row['progress_percent'];
        $row['is_overdue'] = !empty($row['is_overdue']);
        return $row;
    }

    private function normalizeAttachment(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['task_id'] = (int)$row['task_id'];
        $row['file_size'] = (int)$row['file_size'];
        $row['preview_url'] = '/api/work-tasks/' . $row['task_id'] . '/attachments/' . $row['id'] . '/preview';
        $row['download_url'] = '/api/work-tasks/' . $row['task_id'] . '/attachments/' . $row['id'] . '/download';
        return $row;
    }

    private function validId(string $table, mixed $value, bool $allowNull): ?int
    {
        $id = $this->nullableInt($value);
        if (!$id) return null;
        $row = $this->fetchOne("SELECT id FROM $table WHERE id=:id AND is_active=1", ['id' => $id]);
        if (!$row) throw new \RuntimeException('Danh mục không hợp lệ');
        return $id;
    }

    private function defaultStatusId(): int { return (int)(($this->fetchOne('SELECT id FROM work_task_statuses WHERE code="NEW"') ?: [])['id'] ?? 0); }
    private function defaultPriorityId(): int { return (int)(($this->fetchOne('SELECT id FROM work_task_priorities WHERE code="NORMAL"') ?: [])['id'] ?? 0); }
    private function statusTerminal(int $id): bool { return (bool)(($this->fetchOne('SELECT is_terminal FROM work_task_statuses WHERE id=:id', ['id' => $id]) ?: [])['is_terminal'] ?? false); }
    private function defaultProgress(int $statusId): int { return (int)(($this->fetchOne('SELECT progress_percent FROM work_task_statuses WHERE id=:id', ['id' => $statusId]) ?: [])['progress_percent'] ?? 0); }
    private function nextCode(): string { $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM work_tasks'); return 'CV-' . date('Y') . '-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : $value; }
    private function nullableInt(mixed $value): ?int { $value = trim((string)($value ?? '')); if ($value === '') return null; $id = (int)$value; return $id > 0 ? $id : null; }
    private function progress(mixed $value, bool $nullable): ?int { $value = trim((string)($value ?? '')); if ($value === '') return $nullable ? null : null; return max(0, min(100, (int)$value)); }
    private function targetType(string $type): ?string { $type = preg_replace('/[^a-z_]/', '', strtolower(trim($type))); return $type === '' ? null : match ($type) { 'person', 'persons', 'citizens' => 'citizen', 'publicassets', 'public_assets' => 'public_asset', 'household_business', 'household_businesses', 'business_household' => 'business', 'agri' => 'agriculture', default => $type }; }
    private function dateTime(mixed $value, bool $required, string $message): ?string { $value = trim((string)($value ?? '')); if ($value === '') { if ($required) throw new \RuntimeException($message); return null; } if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value . ' 00:00:00'; if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value)) throw new \RuntimeException($message); return str_replace('T', ' ', strlen($value) === 16 ? $value . ':00' : $value); }
    private function camel(string $value): string { return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $value); }
}
