<?php

namespace App\Models;

use App\Core\BaseModel;

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
  document_number VARCHAR(120) NOT NULL,
  title VARCHAR(255) NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  signer_name VARCHAR(255) NULL,
  issued_date DATE NOT NULL,
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
  KEY idx_village_documents_area (area_code),
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
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('PDF','DOCUMENT','IMAGE','OTHER') NOT NULL DEFAULT 'PDF',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_village_document_attachments_document (document_id),
  CONSTRAINT fk_village_document_attachments_document FOREIGN KEY (document_id) REFERENCES village_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->seedCategories();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'categories' => array_map(fn($r) => ['value' => (string)$r['id'], 'code' => (string)$r['code'], 'label' => (string)$r['name']], $this->fetchAll('SELECT id, code, name FROM document_categories WHERE is_active=1 ORDER BY sort_order ASC, name ASC')),
            'statuses' => [['value' => 'ACTIVE', 'label' => 'Đang hiệu lực'], ['value' => 'ARCHIVED', 'label' => 'Lưu trữ']],
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 20));
        [$where, $params] = $this->where($filters);
        $order = $this->listOrder($filters, ['document_code' => 'd.document_code', 'document_number' => 'd.document_number', 'title' => 'd.title', 'category' => 'c.name', 'issued_date' => 'd.issued_date', 'signer_name' => 'd.signer_name', 'status' => 'd.status'], 'issued_date', 'DESC', ['d.id DESC']);
        $from = $this->fromSql();
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
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
        if ($id && !$this->find($id)) throw new \RuntimeException('Không tìm thấy văn bản');
        $params = $this->params($data, $userId);
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE village_documents SET document_number=:document_number, title=:title, category_id=:category_id, signer_name=:signer_name, issued_date=:issued_date, effective_date=:effective_date, area_code=:area_code, summary=:summary, status=:status, updated_by=:updated_by WHERE id=:id AND status <> "DELETED"', $params);
            return $this->find($id);
        }
        $params['document_code'] = $this->nextCode();
        $newId = $this->insert('INSERT INTO village_documents (document_code, document_number, title, category_id, signer_name, issued_date, effective_date, area_code, summary, status, created_by, updated_by) VALUES (:document_code,:document_number,:title,:category_id,:signer_name,:issued_date,:effective_date,:area_code,:summary,:status,:created_by,:updated_by)', $params);
        return $this->find($newId);
    }

    public function softDelete(int $id, int $userId): void
    {
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy văn bản');
        $this->execute('UPDATE village_documents SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    public function addAttachment(int $id, array $stored, array $file, int $userId): array
    {
        if (!$this->find($id)) throw new \RuntimeException('Không tìm thấy văn bản');
        $mime = (string)$stored['mime'];
        $kind = $mime === 'application/pdf' ? 'PDF' : (str_starts_with($mime, 'image/') ? 'IMAGE' : 'DOCUMENT');
        $fileId = $this->insert('INSERT INTO village_document_attachments (document_id, original_name, stored_path, mime_type, file_size, file_kind, created_by) VALUES (:id,:name,:path,:mime,:size,:kind,:user)', ['id' => $id, 'name' => basename((string)($file['name'] ?? 'attachment')), 'path' => $stored['file_path'], 'mime' => $mime, 'size' => (int)($file['size'] ?? 0), 'kind' => $kind, 'user' => $userId]);
        return $this->attachment($id, $fileId) ?? ['id' => $fileId];
    }

    public function attachment(int $documentId, int $fileId): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM village_document_attachments WHERE document_id=:document_id AND id=:id AND deleted_at IS NULL', ['document_id' => $documentId, 'id' => $fileId]);
        return $row ? $this->normalizeAttachment($row) : null;
    }

    public function deleteAttachment(int $documentId, int $fileId, int $userId): void
    {
        if (!$this->attachment($documentId, $fileId)) throw new \RuntimeException('Không tìm thấy file đính kèm');
        $this->execute('UPDATE village_document_attachments SET deleted_at=NOW(), deleted_by=:user WHERE document_id=:document_id AND id=:id', ['document_id' => $documentId, 'id' => $fileId, 'user' => $userId]);
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters);
        $from = $this->fromSql();
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(d.status='ACTIVE'),0) AS active_count, COALESCE(SUM(d.status='ARCHIVED'),0) AS archived_count, COALESCE(SUM(d.issued_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)),0) AS recent_count $from $where", $params) ?: [];
        return ['metrics' => array_map('intval', $metrics), 'by_category' => $this->fetchAll("SELECT COALESCE(c.name,'Khác') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY value DESC LIMIT 10", $params), 'by_month' => $this->fetchAll("SELECT DATE_FORMAT(d.issued_date, '%Y-%m') AS label, COUNT(*) AS value $from $where GROUP BY label ORDER BY label DESC LIMIT 12", $params)];
    }

    public function report(array $filters = []): array
    {
        $filters['page'] = 1;
        $filters['pageSize'] = 500;
        $data = $this->paginate($filters);
        return ['title' => 'Báo cáo văn bản', 'headers' => ['Mã', 'Số văn bản', 'Tiêu đề', 'Loại', 'Người ký', 'Ngày ban hành', 'Trạng thái'], 'rows' => array_map(fn($r) => [$r['document_code'], $r['document_number'], $r['title'], $r['category_name'], $r['signer_name'], $r['issued_date'], $r['status_label']], $data['items']), 'totalRows' => $data['total']];
    }

    private function attachments(int $id): array
    {
        return array_map(fn($r) => $this->normalizeAttachment($r), $this->fetchAll('SELECT * FROM village_document_attachments WHERE document_id=:id AND deleted_at IS NULL ORDER BY id DESC', ['id' => $id]));
    }

    private function params(array $data, int $userId): array
    {
        $number = trim((string)($data['document_number'] ?? $data['documentNumber'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        if ($number === '') throw new \RuntimeException('Số văn bản là bắt buộc');
        if ($title === '') throw new \RuntimeException('Tiêu đề văn bản là bắt buộc');
        $issued = $this->dateRequired($data['issued_date'] ?? $data['issuedDate'] ?? '', 'Ngày ban hành không hợp lệ');
        $status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
        if (!in_array($status, ['ACTIVE','ARCHIVED'], true)) $status = 'ACTIVE';
        return ['document_number' => mb_substr($number, 0, 120), 'title' => mb_substr($title, 0, 255), 'category_id' => ((int)($data['category_id'] ?? $data['categoryId'] ?? 0)) ?: null, 'signer_name' => $this->nullable($data['signer_name'] ?? $data['signerName'] ?? ''), 'issued_date' => $issued, 'effective_date' => $this->dateOrNull($data['effective_date'] ?? $data['effectiveDate'] ?? ''), 'area_code' => $this->nullable($data['area_code'] ?? $data['areaCode'] ?? ''), 'summary' => $this->nullable($data['summary'] ?? ''), 'status' => $status, 'created_by' => $userId, 'updated_by' => $userId];
    }

    private function where(array $filters): array
    {
        $where = ['d.status <> "DELETED"'];
        $params = [];
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(LOWER(d.document_code) LIKE :q OR LOWER(d.document_number) LIKE :q OR LOWER(d.title) LIKE :q OR LOWER(d.signer_name) LIKE :q OR LOWER(d.summary) LIKE :q)';
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        }
        foreach (['category_id' => 'd.category_id', 'status' => 'd.status', 'area_code' => 'd.area_code'] as $key => $column) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { $where[] = "d.issued_date $op :$key"; $params[$key] = $value; }
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function selectSql(): string { return 'SELECT d.*, c.name AS category_name, c.code AS category_code, (SELECT COUNT(*) FROM village_document_attachments a WHERE a.document_id=d.id AND a.deleted_at IS NULL) AS attachment_count'; }
    private function fromSql(): string { return 'FROM village_documents d LEFT JOIN document_categories c ON c.id=d.category_id'; }
    private function normalize(array $row): array { $status = (string)($row['status'] ?? 'ACTIVE'); return ['id' => (int)$row['id'], 'document_code' => (string)$row['document_code'], 'document_number' => (string)$row['document_number'], 'title' => (string)$row['title'], 'category_id' => $row['category_id'] !== null ? (int)$row['category_id'] : null, 'category_name' => (string)($row['category_name'] ?? ''), 'signer_name' => (string)($row['signer_name'] ?? ''), 'issued_date' => $row['issued_date'] ?? null, 'effective_date' => $row['effective_date'] ?? null, 'area_code' => (string)($row['area_code'] ?? ''), 'summary' => (string)($row['summary'] ?? ''), 'status' => $status, 'status_label' => $status === 'ARCHIVED' ? 'Lưu trữ' : 'Đang hiệu lực', 'attachment_count' => (int)($row['attachment_count'] ?? 0), 'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null]; }
    private function normalizeAttachment(array $row): array { $id = (int)$row['id']; return ['id' => $id, 'document_id' => (int)$row['document_id'], 'original_name' => (string)$row['original_name'], 'stored_path' => (string)$row['stored_path'], 'mime_type' => (string)$row['mime_type'], 'file_size' => (int)$row['file_size'], 'file_kind' => (string)$row['file_kind'], 'preview_url' => '/api/documents/' . (int)$row['document_id'] . '/attachments/' . $id . '/preview', 'download_url' => '/api/documents/' . (int)$row['document_id'] . '/attachments/' . $id . '/download']; }
    private function seedCategories(): void { $items = [['notice','Thông báo'], ['decision','Quyết định'], ['official_dispatch','Công văn'], ['plan','Kế hoạch'], ['report','Báo cáo'], ['minutes','Biên bản']]; $order = 10; foreach ($items as [$code, $name]) { $this->execute('INSERT INTO document_categories (code,name,sort_order) VALUES (:code,:name,:sort_order) ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1', ['code' => $code, 'name' => $name, 'sort_order' => $order]); $order += 10; } }
    private function nextCode(): string { $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM village_documents'); return 'VB09-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : mb_substr($value, 0, 500); }
    private function dateRequired(mixed $value, string $message): string { $value = trim((string)($value ?? '')); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) throw new \RuntimeException($message); return $value; }
    private function dateOrNull(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null; }
}
