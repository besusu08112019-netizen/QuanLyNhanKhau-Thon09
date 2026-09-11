<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;

final class VillageDocument extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
    }

    public function assertSchemaReady(): void
    {
        $this->assertColumns('document_categories', [
            'id','village_id','code','name','sort_order','is_active','created_at','updated_at',
        ]);
        $this->assertColumns('village_documents', [
            'id','village_id','document_code','document_number','title','category_id','issuing_unit','signer_name','issued_date','effective_date','area_code','summary','status','created_at','updated_at','created_by','updated_by','deleted_at','deleted_by',
        ]);
        $this->assertColumns('village_document_attachments', [
            'id','village_id','document_id','original_name','stored_path','mime_type','file_size','file_kind','created_at','created_by','deleted_at','deleted_by',
        ]);
        foreach (['document_categories','village_documents','village_document_attachments'] as $table) {
            $this->assertTenantScope($table);
        }

        foreach ([
            'idx_document_categories_active','idx_document_categories_village',
            'idx_village_documents_number','idx_village_documents_title','idx_village_documents_category','idx_village_documents_issued','idx_village_documents_created','idx_village_documents_status','idx_village_documents_village',
            'idx_village_document_attachments_document','idx_village_document_attachments_village',
        ] as $index) {
            $this->assertIndexExists($index);
        }

        $this->assertForeignKey('fk_village_documents_category', 'village_documents', 'category_id', 'document_categories', 'id', 'SET NULL');
        $this->assertForeignKey('fk_village_document_attachments_document', 'village_document_attachments', 'document_id', 'village_documents', 'id', 'CASCADE');
        $this->assertCatalogReady();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'categories' => array_map(fn($r) => ['value' => (string) $r['id'], 'code' => (string) $r['code'], 'label' => (string) $r['name']], $this->fetchAll('SELECT id, code, name FROM document_categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC')),
            'statuses' => [['value' => 'ACTIVE', 'label' => 'Đang hiệu lực'], ['value' => 'ARCHIVED', 'label' => 'Lưu trữ']],
            'years' => array_map(fn($r) => ['value' => (string) $r['year'], 'label' => (string) $r['year']], $this->fetchAll('SELECT DISTINCT YEAR(COALESCE(issued_date, created_at)) AS year FROM village_documents WHERE status <> "DELETED" AND ' . $this->tenantWhere('village_documents') . ' ORDER BY year DESC')),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters);
        $order = $this->listOrder($filters, [
            'document_code' => 'd.document_code',
            'document_number' => 'd.document_number',
            'title' => 'd.title',
            'category' => 'c.name',
            'issued_date' => 'd.issued_date',
            'created_at' => 'd.created_at',
            'uploader' => 'u.display_name',
            'status' => 'd.status',
        ], 'created_at', 'DESC', ['d.id DESC']);
        $from = $this->fromSql();
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->selectSql() . " $from $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($r) => $this->normalize($r), $rows), $page, $pageSize, $total);
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne($this->selectSql() . ' ' . $this->fromSql() . ' WHERE d.id=:id AND d.status <> "DELETED" AND ' . $this->tenantWhere('d', 'village_documents'), ['id' => $id]);
        if (!$row) return null;
        $item = $this->normalize($row);
        $item['attachments'] = $this->attachments($id);
        return $item;
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->find($id)) throw new RuntimeException('Không tìm thấy văn bản');
        $params = $this->params($data, $userId);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE village_documents SET document_number=:document_number,title=:title,category_id=:category_id,issuing_unit=:issuing_unit,signer_name=:signer_name,issued_date=:issued_date,effective_date=:effective_date,area_code=:area_code,summary=:summary,status=:status,updated_by=:updated_by WHERE id=:id AND status <> "DELETED" AND ' . $this->tenantWhere('village_documents'), $params);
            return $this->find($id);
        }
        $params['document_code'] = $this->nextCode();
        $columns = ['document_code','document_number','title','category_id','issuing_unit','signer_name','issued_date','effective_date','area_code','summary','status','created_by','updated_by'];
        $this->addTenantInsert('village_documents', $columns, $params);
        $newId = $this->insert('INSERT INTO village_documents (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->find($newId);
    }

    public function deletePermanently(int $id): array
    {
        $row = $this->find($id);
        if (!$row) throw new RuntimeException('Không tìm thấy văn bản');
        $files = $this->attachments($id);
        $this->execute('DELETE FROM village_document_attachments WHERE document_id=:id', ['id' => $id]);
        $this->execute('DELETE FROM village_documents WHERE id=:id AND ' . $this->tenantWhere('village_documents'), ['id' => $id]);
        return ['document' => $row, 'files' => $files];
    }

    public function addAttachment(int $id, array $stored, array $file, int $userId): array
    {
        if (!$this->find($id)) throw new RuntimeException('Không tìm thấy văn bản');
        $mime = (string) $stored['mime'];
        $extension = strtolower((string) ($stored['extension'] ?? pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)));
        $kind = $this->kindForExtension($extension, $mime);
        $fileId = $this->insert('INSERT INTO village_document_attachments (document_id, original_name, stored_path, mime_type, file_size, file_kind, created_by) VALUES (:id,:name,:path,:mime,:size,:kind,:user)', [
            'id' => $id,
            'name' => basename((string) ($file['name'] ?? 'attachment')),
            'path' => $stored['file_path'],
            'mime' => $mime,
            'size' => (int) ($file['size'] ?? 0),
            'kind' => $kind,
            'user' => $userId,
        ]);
        return $this->attachment($id, $fileId) ?? ['id' => $fileId];
    }

    public function attachment(int $documentId, int $fileId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM village_document_attachments WHERE document_id=:document_id AND id=:id AND deleted_at IS NULL', ['document_id' => $documentId, 'id' => $fileId]);
        return $row ? $this->normalizeAttachment($row) : null;
    }

    public function primaryAttachment(int $documentId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM village_document_attachments WHERE document_id=:id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1', ['id' => $documentId]);
        return $row ? $this->normalizeAttachment($row) : null;
    }

    public function deleteAttachment(int $documentId, int $fileId, int $userId): ?array
    {
        $file = $this->attachment($documentId, $fileId);
        if (!$file) throw new RuntimeException('Không tìm thấy file đính kèm');
        $this->execute('DELETE FROM village_document_attachments WHERE document_id=:document_id AND id=:id', ['document_id' => $documentId, 'id' => $fileId]);
        return $file;
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters);
        $from = $this->fromSql();
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(d.status='ACTIVE'),0) AS active_count, COALESCE(SUM(d.status='ARCHIVED'),0) AS archived_count, COALESCE(SUM(d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)),0) AS recent_count $from $where", $params) ?: [];
        return ['metrics' => array_map('intval', $metrics)];
    }

    public function report(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters);
        $rows = array_map(fn($r) => $this->normalize($r), $this->fetchAll($this->selectSql() . ' ' . $this->fromSql() . " $where ORDER BY d.created_at DESC, d.id DESC", $params));
        return [
            'title' => 'Báo cáo văn bản',
            'headers' => ['Ma', 'Số văn bản', 'Tiêu đề', 'Loại', 'Đơn vị ban hành', 'Người ký', 'Ngày ban hanh', 'Người tải lên', 'Tạo lúc'],
            'rows' => array_map(fn($r) => [$r['document_code'], $r['document_number'], $r['title'], $r['category_name'], $r['issuing_unit'], $r['signer_name'], $r['issued_date'], $r['created_by_name'], $r['created_at']], $rows),
            'totalRows' => count($rows),
        ];
    }

    private function attachments(int $id): array
    {
        return array_map(fn($r) => $this->normalizeAttachment($r), $this->fetchAll('SELECT * FROM village_document_attachments WHERE document_id=:id AND deleted_at IS NULL ORDER BY id DESC', ['id' => $id]));
    }

    private function params(array $data, int $userId): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') throw new RuntimeException('Tiêu đề van ban la bat buoc');
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));
        if (!in_array($status, ['ACTIVE', 'ARCHIVED'], true)) $status = 'ACTIVE';
        return [
            'document_number' => $this->nullable($data['document_number'] ?? $data['documentNumber'] ?? '', 120),
            'title' => mb_substr($title, 0, 255),
            'category_id' => ((int) ($data['category_id'] ?? $data['categoryId'] ?? 0)) ?: null,
            'issuing_unit' => $this->nullable($data['issuing_unit'] ?? $data['issuingUnit'] ?? '', 255),
            'signer_name' => $this->nullable($data['signer_name'] ?? $data['signerName'] ?? '', 255),
            'issued_date' => $this->dateOrNull($data['issued_date'] ?? $data['issuedDate'] ?? ''),
            'effective_date' => $this->dateOrNull($data['effective_date'] ?? $data['effectiveDate'] ?? ''),
            'area_code' => $this->nullable($data['area_code'] ?? $data['areaCode'] ?? '', 80),
            'summary' => $this->nullable($data['summary'] ?? $data['description'] ?? '', 5000),
            'status' => $status,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function where(array $filters): array
    {
        $where = ['d.status <> "DELETED"', $this->tenantWhere('d', 'village_documents')];
        $params = [];
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(LOWER(d.document_code) LIKE :q OR LOWER(d.document_number) LIKE :q OR LOWER(d.title) LIKE :q OR LOWER(d.issuing_unit) LIKE :q OR LOWER(d.signer_name) LIKE :q OR LOWER(d.summary) LIKE :q)';
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        }
        foreach (['category_id' => 'd.category_id', 'status' => 'd.status'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        $year = trim((string) ($filters['year'] ?? ''));
        if ($year !== '' && preg_match('/^\d{4}$/', $year)) { $where[] = 'YEAR(COALESCE(d.issued_date, d.created_at)) = :year'; $params['year'] = $year; }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { $where[] = "COALESCE(d.issued_date, DATE(d.created_at)) $op :$key"; $params[$key] = $value; }
        }
        return ['WHERE ' . implode(' AND ', $where), $this->withTenant($params)];
    }

    private function selectSql(): string
    {
        return 'SELECT d.*, c.name AS category_name, c.code AS category_code, u.display_name AS created_by_name, u.email AS created_by_email, (SELECT COUNT(*) FROM village_document_attachments a WHERE a.document_id=d.id AND a.deleted_at IS NULL) AS attachment_count';
    }

    private function fromSql(): string
    {
        return 'FROM village_documents d LEFT JOIN document_categories c ON c.id=d.category_id LEFT JOIN users u ON u.id=d.created_by';
    }

    private function normalize(array $row): array
    {
        $status = (string) ($row['status'] ?? 'ACTIVE');
        return [
            'id' => (int) $row['id'],
            'document_code' => (string) $row['document_code'],
            'document_number' => (string) ($row['document_number'] ?? ''),
            'title' => (string) $row['title'],
            'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
            'category_name' => (string) ($row['category_name'] ?? ''),
            'issuing_unit' => (string) ($row['issuing_unit'] ?? ''),
            'signer_name' => (string) ($row['signer_name'] ?? ''),
            'issued_date' => $row['issued_date'] ?? null,
            'effective_date' => $row['effective_date'] ?? null,
            'area_code' => (string) ($row['area_code'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'status' => $status,
            'status_label' => $status === 'ARCHIVED' ? 'Lưu trữ' : 'Đang hiệu lực',
            'attachment_count' => (int) ($row['attachment_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'created_by' => $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'created_by_name' => (string) ($row['created_by_name'] ?? $row['created_by_email'] ?? ''),
            'created_by_email' => (string) ($row['created_by_email'] ?? ''),
        ];
    }

    private function normalizeAttachment(array $row): array
    {
        $id = (int) $row['id'];
        return [
            'id' => $id,
            'document_id' => (int) $row['document_id'],
            'original_name' => (string) $row['original_name'],
            'stored_path' => (string) $row['stored_path'],
            'mime_type' => (string) $row['mime_type'],
            'file_size' => (int) $row['file_size'],
            'file_kind' => (string) $row['file_kind'],
            'created_at' => $row['created_at'] ?? null,
            'created_by' => $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'preview_url' => '/api/documents/' . (int) $row['document_id'] . '/attachments/' . $id . '/preview',
            'download_url' => '/api/documents/' . (int) $row['document_id'] . '/attachments/' . $id . '/download',
        ];
    }

    private function assertColumns(string $table, array $columns): void
    {
        $existing = $this->fetchAll('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table', ['table' => $table]);
        $existing = array_flip(array_map(fn($row) => (string) $row['COLUMN_NAME'], $existing));
        foreach ($columns as $column) {
            if (!isset($existing[$column])) {
                throw new RuntimeException("Village document schema is not ready: missing $table.$column");
            }
        }
    }

    private function assertIndexExists(string $index): void
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME=:index_name', ['index_name' => $index]);
        if ((int) ($row['total'] ?? 0) === 0) {
            throw new RuntimeException("Village document schema is not ready: missing index $index");
        }
    }

    private function assertTenantScope(string $table): void
    {
        $row = $this->fetchOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME="village_id"',
            ['table' => $table]
        );
        if (!$row
            || !str_contains(strtolower((string) $row['COLUMN_TYPE']), 'bigint')
            || !str_contains(strtolower((string) $row['COLUMN_TYPE']), 'unsigned')
            || strtoupper((string) $row['IS_NULLABLE']) !== 'NO') {
            throw new RuntimeException("Village document schema is not ready: invalid tenant scope $table.village_id");
        }
    }

    private function assertForeignKey(string $name, string $table, string $column, string $parentTable, string $parentColumn, string $deleteRule): void
    {
        $row = $this->fetchOne(
            'SELECT rc.DELETE_RULE, rc.UPDATE_RULE, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_SCHEMA=rc.CONSTRAINT_SCHEMA
              AND kcu.CONSTRAINT_NAME=rc.CONSTRAINT_NAME
              AND kcu.TABLE_NAME=rc.TABLE_NAME
             WHERE rc.CONSTRAINT_SCHEMA=DATABASE()
               AND rc.CONSTRAINT_NAME=:name
               AND rc.TABLE_NAME=:table',
            ['name' => $name, 'table' => $table]
        );
        if (!$row
            || (string) $row['COLUMN_NAME'] !== $column
            || (string) $row['REFERENCED_TABLE_NAME'] !== $parentTable
            || (string) $row['REFERENCED_COLUMN_NAME'] !== $parentColumn
            || strtoupper((string) $row['DELETE_RULE']) !== $deleteRule
            || strtoupper((string) $row['UPDATE_RULE']) !== 'RESTRICT') {
            throw new RuntimeException("Village document schema is not ready: invalid foreign key $name");
        }
    }

    private function assertCatalogReady(): void
    {
        foreach (['notice','decision','official_dispatch','plan','report','minutes','other'] as $code) {
            $row = $this->fetchOne(
                'SELECT COUNT(*) AS total FROM document_categories WHERE code=:code AND is_active=1 AND ' . $this->tenantWhere('', 'document_categories'),
                $this->withTenant(['code' => $code])
            );
            if ((int) ($row['total'] ?? 0) === 0) {
                throw new RuntimeException("Village document catalog is not ready: missing active document_categories.$code");
            }
        }
    }

    private function migrateSchema(): void
    {
        throw new RuntimeException('Village document schema migration is not allowed from runtime model reads');
    }

    private function seedCategories(): void
    {
        $items = [['notice', 'Thông báo'], ['decision', 'Quyết định'], ['official_dispatch', 'Công văn'], ['plan', 'Kế hoạch'], ['report', 'Báo cáo'], ['minutes', 'Biên bản'], ['other', 'Khác']];
        $order = 10;
        foreach ($items as [$code, $name]) {
            throw new RuntimeException('Village document catalog seeding is not allowed from runtime model reads');
            $order += 10;
        }
    }

    private function kindForExtension(string $extension, string $mime): string
    {
        return match ($extension) {
            'pdf' => 'PDF',
            'doc', 'docx' => 'WORD',
            'xls', 'xlsx' => 'EXCEL',
            'ppt', 'pptx' => 'POWERPOINT',
            'zip' => 'ARCHIVE',
            default => $mime === 'application/pdf' ? 'PDF' : 'DOCUMENT',
        };
    }

    private function nextCode(): string
    {
        $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM village_documents WHERE ' . $this->tenantWhere('village_documents'));
        return 'VB-' . str_pad((string) (((int) ($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}

