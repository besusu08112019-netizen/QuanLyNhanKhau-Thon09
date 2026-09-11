<?php

namespace App\Models;

use App\Core\BaseModel;

final class WorkTask extends BaseModel
{
    private const REQUIRED_SCHEMA = [
        'work_task_categories' => ['id','village_id','code','name','sort_order','is_active','created_at','updated_at'],
        'work_task_priorities' => ['id','village_id','code','name','sort_order','is_active','created_at','updated_at'],
        'work_task_statuses' => ['id','village_id','code','name','progress_percent','is_terminal','sort_order','is_active','created_at','updated_at'],
        'work_tasks' => ['id','village_id','task_code','title','description','category_id','priority_id','status_id','assigned_user_id','assigned_name','start_at','due_at','completed_at','progress_percent','related_module','related_id','area_code','note','soft_status','created_at','updated_at','created_by','updated_by','deleted_at','deleted_by'],
        'work_task_logs' => ['id','village_id','task_id','actor_user_id','actor_name','content','status_id','progress_percent','created_at'],
        'work_task_attachments' => ['id','village_id','task_id','log_id','original_name','stored_path','mime_type','file_size','file_kind','created_at','created_by','deleted_at','deleted_by'],
    ];

    private const REQUIRED_INDEXES = [
        'work_task_categories' => ['PRIMARY','idx_work_task_categories_active','idx_work_task_categories_village'],
        'work_task_priorities' => ['PRIMARY','idx_work_task_priorities_active','idx_work_task_priorities_village'],
        'work_task_statuses' => ['PRIMARY','idx_work_task_statuses_active','idx_work_task_statuses_terminal','idx_work_task_statuses_village'],
        'work_tasks' => ['PRIMARY','idx_work_tasks_search','idx_work_tasks_category','idx_work_tasks_priority','idx_work_tasks_status','idx_work_tasks_assigned','idx_work_tasks_due','idx_work_tasks_area','idx_work_tasks_related','idx_work_tasks_soft_status','idx_work_tasks_village'],
        'work_task_logs' => ['PRIMARY','idx_work_task_logs_task','idx_work_task_logs_status','idx_work_task_logs_village'],
        'work_task_attachments' => ['PRIMARY','idx_work_task_attachments_task','idx_work_task_attachments_log','idx_work_task_attachments_kind','idx_work_task_attachments_village'],
    ];

    private const REQUIRED_FOREIGN_KEYS = [
        'work_tasks' => [
            ['fk_work_tasks_category','category_id','work_task_categories','id'],
            ['fk_work_tasks_priority','priority_id','work_task_priorities','id'],
            ['fk_work_tasks_status','status_id','work_task_statuses','id'],
        ],
        'work_task_logs' => [
            ['fk_work_task_logs_task','task_id','work_tasks','id'],
            ['fk_work_task_logs_status','status_id','work_task_statuses','id'],
        ],
        'work_task_attachments' => [
            ['fk_work_task_attachments_task','task_id','work_tasks','id'],
            ['fk_work_task_attachments_log','log_id','work_task_logs','id'],
        ],
    ];

    private const REQUIRED_CATEGORIES = [
        ['fund_collection', 'Thu quỹ', 10],
        ['household_check', 'Kiểm tra hộ', 20],
        ['gift_distribution', 'Phát quà', 30],
        ['environment_cleanup', 'Vệ sinh môi trường', 40],
        ['patrol', 'Tuần tra', 50],
        ['public_asset_check', 'Kiểm tra công trình', 60],
        ['production_check', 'Kiểm tra sản xuất', 70],
        ['other', 'Khác', 80],
    ];

    private const REQUIRED_PRIORITIES = [
        ['URGENT','Khẩn cấp',10],
        ['HIGH','Cao',20],
        ['NORMAL','Bình thường',30],
        ['LOW','Thấp',40],
    ];

    private const REQUIRED_STATUSES = [
        ['NEW','Mới tạo',0,0,10],
        ['ASSIGNED','Đã giao',10,0,20],
        ['IN_PROGRESS','Đang thực hiện',50,0,30],
        ['WAITING','Tạm dừng/chờ xử lý',50,0,40],
        ['DONE','Đã hoàn thành',100,1,50],
        ['CANCELLED','Đã hủy',0,1,60],
    ];

    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
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
                ['value' => 'risk_warning', 'label' => 'Cảnh báo vận hành'],
                ['value' => 'data_quality', 'label' => 'Chất lượng dữ liệu'],
                ['value' => 'executive_dashboard', 'label' => 'Dashboard điều hành'],
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
        $row = $this->fetchOne($this->selectSql() . ' ' . $this->fromSql() . ' WHERE t.id=:id AND t.soft_status <> "DELETED" AND ' . $this->tenantWhere('t', 'work_tasks'), ['id' => $id]);
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
            $this->execute('UPDATE work_tasks SET title=:title, description=:description, category_id=:category_id, priority_id=:priority_id, status_id=:status_id, assigned_user_id=:assigned_user_id, assigned_name=:assigned_name, start_at=:start_at, due_at=:due_at, completed_at=:completed_at, progress_percent=:progress_percent, related_module=:related_module, related_id=:related_id, area_code=:area_code, note=:note, updated_by=:updated_by WHERE id=:id AND soft_status <> "DELETED" AND ' . $this->tenantWhere('work_tasks'), $params);
            return $this->find($id);
        }
        $params['task_code'] = $this->nextCode();
        $columns = ['task_code', 'title', 'description', 'category_id', 'priority_id', 'status_id', 'assigned_user_id', 'assigned_name', 'start_at', 'due_at', 'completed_at', 'progress_percent', 'related_module', 'related_id', 'area_code', 'note', 'created_by', 'updated_by'];
        $this->addTenantInsert('work_tasks', $columns, $params);
        $newId = $this->insert('INSERT INTO work_tasks (' . implode(',', $columns) . ') VALUES (:' . implode(', :', $columns) . ')', $params);
        $this->addLog($newId, ['content' => 'Tạo công việc', 'status_id' => $params['status_id'], 'progress_percent' => $params['progress_percent']], $userId, $userName);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy công việc');
        $this->execute('UPDATE work_tasks SET soft_status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('work_tasks'), ['id' => $id, 'user' => $userId]);
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
        $this->execute('UPDATE work_tasks SET ' . implode(', ', $sets) . ' WHERE id=:id AND ' . $this->tenantWhere('work_tasks'), $params);
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
        $where = ['t.soft_status <> "DELETED"', $this->tenantWhere('t', 'work_tasks')];
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
        $relatedModule = $this->targetType((string)($filters['related_module'] ?? $filters['relatedModule'] ?? ''));
        if ($relatedModule !== null) {
            $where[] = 't.related_module = :related_module';
            $params['related_module'] = $relatedModule;
        }
        $relatedId = $this->nullableInt($filters['related_id'] ?? $filters['relatedId'] ?? null);
        if ($relatedId !== null) {
            $where[] = 't.related_id = :related_id';
            $params['related_id'] = $relatedId;
        }
        $sourceRef = trim((string)($filters['source_ref'] ?? $filters['sourceRef'] ?? ''));
        if ($sourceRef !== '') {
            $sourceRef = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $sourceRef);
            $where[] = 't.note LIKE :source_ref';
            $params['source_ref'] = '%SOURCE_REF:' . $sourceRef . '%';
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
        return ['WHERE ' . implode(' AND ', $where), $this->withTenant($params)];
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

    private function catalog(string $table): array
    {
        $rows = $this->uniqueCatalogRows($this->fetchAll("SELECT id, code, name FROM $table WHERE is_active=1 ORDER BY sort_order ASC, id ASC"));
        return array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name']], $rows);
    }

    private function statusCatalog(): array
    {
        $rows = $this->uniqueCatalogRows($this->fetchAll('SELECT id, code, name, progress_percent, is_terminal FROM work_task_statuses WHERE is_active=1 ORDER BY sort_order ASC, id ASC'));
        return array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name'], 'progress_percent' => (int)$r['progress_percent'], 'is_terminal' => (bool)$r['is_terminal']], $rows);
    }

    private function uniqueCatalogRows(array $rows): array
    {
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim((string)($row['code'] ?? '')));
            if ($key === '') $key = (string)($row['id'] ?? count($unique));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $row;
        }
        return $unique;
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

    private function assertSchemaReady(): void { foreach (self::REQUIRED_SCHEMA as $table => $columns) { if (!$this->tableExists($table)) throw new \RuntimeException('Work Task schema is not provisioned: missing table ' . $table); foreach ($columns as $column) { if (!$this->columnExists($table, $column)) throw new \RuntimeException('Work Task schema is not provisioned: missing column ' . $table . '.' . $column); } foreach (self::REQUIRED_INDEXES[$table] ?? [] as $index) { if (!$this->indexExists($table, $index)) throw new \RuntimeException('Work Task schema is not provisioned: missing index ' . $table . '.' . $index); } } foreach (self::REQUIRED_FOREIGN_KEYS as $table => $foreignKeys) { foreach ($foreignKeys as [$constraint, $column, $referencedTable, $referencedColumn]) { if (!$this->foreignKeyExists($table, $constraint, $column, $referencedTable, $referencedColumn)) throw new \RuntimeException('Work Task schema is not provisioned: missing foreign key ' . $constraint); } } $drift = array_merge($this->simpleCatalogDrift('work_task_categories', self::REQUIRED_CATEGORIES), $this->simpleCatalogDrift('work_task_priorities', self::REQUIRED_PRIORITIES), $this->statusCatalogDrift()); if ($drift !== []) throw new \RuntimeException('Work Task catalogs are not provisioned: ' . implode('; ', $drift)); }
    private function simpleCatalogDrift(string $table, array $items): array { $drift = []; foreach ($items as [$code]) { $compatible = $this->fetchOne("SELECT id FROM $table WHERE code=:code AND is_active=1", ['code' => $code]); if ($compatible) continue; $existing = $this->fetchOne("SELECT id FROM $table WHERE code=:code", ['code' => $code]); $drift[] = $existing ? 'conflicting ' . $table . ' ' . $code : 'missing ' . $table . ' ' . $code; } return $drift; }
    private function statusCatalogDrift(): array { $drift = []; foreach (self::REQUIRED_STATUSES as [$code, $name, $progress, $terminal]) { $compatible = $this->fetchOne('SELECT id FROM work_task_statuses WHERE code=:code AND progress_percent=:progress AND is_terminal=:terminal AND is_active=1', ['code' => $code, 'progress' => $progress, 'terminal' => $terminal]); if ($compatible) continue; $existing = $this->fetchOne('SELECT id FROM work_task_statuses WHERE code=:code', ['code' => $code]); $drift[] = $existing ? 'conflicting work_task_statuses ' . $code : 'missing work_task_statuses ' . $code; } return $drift; }
    private function tableExists(string $table): bool { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table', ['table' => $table]); return (int)($row['total'] ?? 0) > 0; }
    private function indexExists(string $table, string $index): bool { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND INDEX_NAME=:index', ['table' => $table, 'index' => $index]); return (int)($row['total'] ?? 0) > 0; }
    private function foreignKeyExists(string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): bool { $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND CONSTRAINT_NAME=:constraint_name AND COLUMN_NAME=:column_name AND REFERENCED_TABLE_NAME=:referenced_table AND REFERENCED_COLUMN_NAME=:referenced_column', ['table' => $table, 'constraint_name' => $constraint, 'column_name' => $column, 'referenced_table' => $referencedTable, 'referenced_column' => $referencedColumn]); return (int)($row['total'] ?? 0) > 0; }
    private function defaultStatusId(): int { return (int)(($this->fetchOne('SELECT id FROM work_task_statuses WHERE code="NEW"') ?: [])['id'] ?? 0); }
    private function defaultPriorityId(): int { return (int)(($this->fetchOne('SELECT id FROM work_task_priorities WHERE code="NORMAL"') ?: [])['id'] ?? 0); }
    private function statusTerminal(int $id): bool { return (bool)(($this->fetchOne('SELECT is_terminal FROM work_task_statuses WHERE id=:id', ['id' => $id]) ?: [])['is_terminal'] ?? false); }
    private function defaultProgress(int $statusId): int { return (int)(($this->fetchOne('SELECT progress_percent FROM work_task_statuses WHERE id=:id', ['id' => $statusId]) ?: [])['progress_percent'] ?? 0); }
    private function nextCode(): string { $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM work_tasks WHERE ' . $this->tenantWhere('work_tasks')); return 'CV-' . date('Y') . '-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : $value; }
    private function nullableInt(mixed $value): ?int { $value = trim((string)($value ?? '')); if ($value === '') return null; $id = (int)$value; return $id > 0 ? $id : null; }
    private function progress(mixed $value, bool $nullable): ?int { $value = trim((string)($value ?? '')); if ($value === '') return $nullable ? null : null; return max(0, min(100, (int)$value)); }
    private function targetType(string $type): ?string { $type = preg_replace('/[^a-z_]/', '', strtolower(trim($type))); return $type === '' ? null : match ($type) { 'person', 'persons', 'citizens' => 'citizen', 'publicassets', 'public_assets' => 'public_asset', 'household_business', 'household_businesses', 'business_household' => 'business', 'agri' => 'agriculture', default => $type }; }
    private function dateTime(mixed $value, bool $required, string $message): ?string { $value = trim((string)($value ?? '')); if ($value === '') { if ($required) throw new \RuntimeException($message); return null; } if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value . ' 00:00:00'; if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value)) throw new \RuntimeException($message); return str_replace('T', ' ', strlen($value) === 16 ? $value . ':00' : $value); }
    private function camel(string $value): string { return preg_replace_callback('/_([a-z])/', fn($m) => strtoupper($m[1]), $value); }
}
