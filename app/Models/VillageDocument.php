<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;

final class VillageDocument extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS document_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_document_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS village_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_code VARCHAR(40) NOT NULL UNIQUE,
  document_number VARCHAR(120) NULL,
  title VARCHAR(255) NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  issuing_unit VARCHAR(255) NULL,
  signer_name VARCHAR(255) NULL,
  issued_date DATE NULL,
  effective_date DATE NULL,
  area_code VARCHAR(80) NULL,
  summary TEXT NULL,
  status ENUM('ACTIVE','ARCHIVED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_village_documents_number (document_number),
  KEY idx_village_documents_title (title),
  KEY idx_village_documents_category (category_id),
  KEY idx_village_documents_issued (issued_date),
  KEY idx_village_documents_created (created_at),
  KEY idx_village_documents_status (status),
  CONSTRAINT fk_village_documents_category FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS village_document_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(160) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('PDF','WORD','EXCEL','POWERPOINT','ARCHIVE','DOCUMENT','IMAGE','OTHER') NOT NULL DEFAULT 'DOCUMENT',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_village_document_attachments_document (document_id),
  CONSTRAINT fk_village_document_attachments_document FOREIGN KEY (document_id) REFERENCES village_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->migrateSchema();
        $this->seedCategories();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'categories' => array_map(fn($r) => ['value' => (string) $r['id'], 'code' => (string) $r['code'], 'label' => (string) $r['name']], $this->fetchAll('SELECT id, code, name FROM document_categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC')),
            'statuses' => [['value' => 'ACTIVE', 'label' => 'Dang hieu luc'], ['value' => 'ARCHIVED', 'label' => 'Luu tru']],
            'years' => array_map(fn($r) => ['value' => (string) $r['year'], 'label' => (string) $r['year']], $this->fetchAll('SELECT DISTINCT YEAR(COALESCE(issued_date, created_at)) AS year FROM village_documents WHERE status <> "DELETED" ORDER BY year DESC')),
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
        $row = $this->fetchOne($this->selectSql() . ' ' . $this->fromSql() . ' WHERE d.id=:id AND d.status <> "DELETED"', ['id' => $id]);
        if (!$row) return null;
        $item = $this->normalize($row);
        $item['attachments'] = $this->attachments($id);
        return $item;
    }

    public function upsert(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        if ($id && !$this->find($id)) throw new RuntimeException('Khong tim thay van ban');
        $params = $this->params($data, $userId);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE village_documents SET document_number=:document_number,title=:title,category_id=:category_id,issuing_unit=:issuing_unit,signer_name=:signer_name,issued_date=:issued_date,effective_date=:effective_date,area_code=:area_code,summary=:summary,status=:status,updated_by=:updated_by WHERE id=:id AND status <> "DELETED"', $params);
            return $this->find($id);
        }
        $params['document_code'] = $this->nextCode();
        $newId = $this->insert('INSERT INTO village_documents (document_code,document_number,title,category_id,issuing_unit,signer_name,issued_date,effective_date,area_code,summary,status,created_by,updated_by) VALUES (:document_code,:document_number,:title,:category_id,:issuing_unit,:signer_name,:issued_date,:effective_date,:area_code,:summary,:status,:created_by,:updated_by)', $params);
        return $this->find($newId);
    }

    public function deletePermanently(int $id): array
    {
        $row = $this->find($id);
        if (!$row) throw new RuntimeException('Khong tim thay van ban');
        $files = $this->attachments($id);
        $this->execute('DELETE FROM village_document_attachments WHERE document_id=:id', ['id' => $id]);
        $this->execute('DELETE FROM village_documents WHERE id=:id', ['id' => $id]);
        return ['document' => $row, 'files' => $files];
    }

    public function addAttachment(int $id, array $stored, array $file, int $userId): array
    {
        if (!$this->find($id)) throw new RuntimeException('Khong tim thay van ban');
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
        if (!$file) throw new RuntimeException('Khong tim thay file dinh kem');
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
            'title' => 'Bao cao van ban',
            'headers' => ['Ma', 'So van ban', 'Tieu de', 'Loai', 'Don vi ban hanh', 'Nguoi ky', 'Ngay ban hanh', 'Nguoi tai len', 'Tao luc'],
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
        if ($title === '') throw new RuntimeException('Tieu de van ban la bat buoc');
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
        $where = ['d.status <> "DELETED"'];
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
        return ['WHERE ' . implode(' AND ', $where), $params];
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
            'status_label' => $status === 'ARCHIVED' ? 'Luu tru' : 'Dang hieu luc',
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

    private function migrateSchema(): void
    {
        $columns = [
            'issuing_unit' => 'VARCHAR(255) NULL AFTER category_id',
        ];
        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('village_documents', $column)) {
                $this->execute('ALTER TABLE village_documents ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        try { $this->execute('ALTER TABLE village_documents MODIFY document_number VARCHAR(120) NULL'); } catch (\Throwable) {}
        try { $this->execute('ALTER TABLE village_documents MODIFY issued_date DATE NULL'); } catch (\Throwable) {}
        try { $this->execute("ALTER TABLE village_document_attachments MODIFY file_kind ENUM('PDF','WORD','EXCEL','POWERPOINT','ARCHIVE','DOCUMENT','IMAGE','OTHER') NOT NULL DEFAULT 'DOCUMENT'"); } catch (\Throwable) {}
    }

    private function seedCategories(): void
    {
        $items = [['notice', 'Thong bao'], ['decision', 'Quyet dinh'], ['official_dispatch', 'Cong van'], ['plan', 'Ke hoach'], ['report', 'Bao cao'], ['minutes', 'Bien ban'], ['other', 'Khac']];
        $order = 10;
        foreach ($items as [$code, $name]) {
            $this->execute('INSERT INTO document_categories (code,name,sort_order) VALUES (:code,:name,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'sort_order' => $order]);
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
        $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM village_documents');
        return 'VB09-' . str_pad((string) (((int) ($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT);
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
