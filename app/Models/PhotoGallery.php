<?php

namespace App\Models;

use App\Core\BaseModel;

final class PhotoGallery extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS photo_gallery_albums (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  cover_item_id BIGINT UNSIGNED NULL,
  status ENUM('ACTIVE','ARCHIVED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_photo_gallery_albums_status (status),
  KEY idx_photo_gallery_albums_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS photo_gallery_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  taken_at DATETIME NULL,
  event_date DATE NULL,
  area_code VARCHAR(80) NULL,
  source_module VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  tags_text VARCHAR(500) NULL,
  status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_photo_gallery_items_album (album_id),
  KEY idx_photo_gallery_items_status (status),
  KEY idx_photo_gallery_items_event_date (event_date),
  KEY idx_photo_gallery_items_area (area_code),
  KEY idx_photo_gallery_items_source (source_module, source_id),
  FULLTEXT KEY ft_photo_gallery_items_search (title, description, original_name, tags_text),
  CONSTRAINT fk_photo_gallery_items_album FOREIGN KEY (album_id) REFERENCES photo_gallery_albums(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        $albums = $this->fetchAll('SELECT id, album_code, name FROM photo_gallery_albums WHERE status <> "DELETED" ORDER BY name ASC');
        $tags = $this->fetchAll('SELECT DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(tags_text, ",", n.n), ",", -1)) AS tag FROM photo_gallery_items JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) n WHERE status <> "DELETED" AND tags_text IS NOT NULL AND tags_text <> "" HAVING tag <> "" ORDER BY tag ASC LIMIT 100');
        return [
            'albums' => array_map(fn($r) => ['value' => (string)$r['id'], 'label' => (string)$r['name'], 'code' => (string)$r['album_code']], $albums),
            'tags' => array_map(fn($r) => ['value' => (string)$r['tag'], 'label' => (string)$r['tag']], $tags),
            'sources' => array_map(fn($v) => ['value' => $v[0], 'label' => $v[1]], [
                ['meeting', 'Hội nghị'], ['public_asset', 'Công trình'], ['work_task', 'Công tác'], ['construction', 'Thi công'], ['union', 'Hoạt động đoàn thể'], ['other', 'Khác'],
            ]),
        ];
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->where($filters, false);
        $metrics = $this->fetchOne("SELECT COUNT(*) AS total_photos, COALESCE(SUM(pgi.album_id IS NULL),0) AS unclassified_photos, COALESCE(SUM(pgi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)),0) AS recent_photos, COALESCE(SUM(pgi.file_size),0) AS total_size FROM photo_gallery_items pgi LEFT JOIN photo_gallery_albums pga ON pga.id=pgi.album_id $where", $params) ?: [];
        return [
            'metrics' => array_map('floatval', $metrics),
            'by_album' => $this->fetchAll("SELECT COALESCE(pga.name, :unknown) AS label, COUNT(*) AS value FROM photo_gallery_items pgi LEFT JOIN photo_gallery_albums pga ON pga.id=pgi.album_id $where GROUP BY label ORDER BY value DESC LIMIT 8", $params + ['unknown' => $this->u('Ch\u01b0a c\u00f3 album')]),
            'by_source' => $this->fetchAll("SELECT COALESCE(NULLIF(pgi.source_module,''), :unknown) AS label, COUNT(*) AS value FROM photo_gallery_items pgi LEFT JOIN photo_gallery_albums pga ON pga.id=pgi.album_id $where GROUP BY label ORDER BY value DESC LIMIT 8", $params + ['unknown' => $this->u('Kh\u00e1c')]),
        ];
    }

    public function paginate(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int)($filters['page'] ?? 1), (int)($filters['pageSize'] ?? 24));
        [$where, $params, $order] = $this->where($filters);
        $total = (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM photo_gallery_items pgi LEFT JOIN photo_gallery_albums pga ON pga.id=pgi.album_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll("SELECT pgi.*, pga.name AS album_name, pga.album_code FROM photo_gallery_items pgi LEFT JOIN photo_gallery_albums pga ON pga.id=pgi.album_id $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($r) => $this->normalizeItem($r), $rows), $page, $pageSize, $total);
    }

    public function albums(): array
    {
        $this->ensureSchema();
        $rows = $this->fetchAll('SELECT pga.*, COUNT(pgi.id) AS photo_count FROM photo_gallery_albums pga LEFT JOIN photo_gallery_items pgi ON pgi.album_id=pga.id AND pgi.status <> "DELETED" WHERE pga.status <> "DELETED" GROUP BY pga.id ORDER BY pga.name ASC');
        return ['items' => array_map(fn($r) => $this->normalizeAlbum($r), $rows)];
    }

    public function createAlbum(array $data, int $userId): array
    {
        $this->ensureSchema();
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') throw new \RuntimeException($this->u('T\u00ean album l\u00e0 b\u1eaft bu\u1ed9c'));
        $id = $this->insert('INSERT INTO photo_gallery_albums (album_code, name, description, created_by, updated_by) VALUES (:album_code,:name,:description,:created_by,:updated_by)', [
            'album_code' => $this->nextAlbumCode(),
            'name' => mb_substr($name, 0, 255),
            'description' => $this->nullable($data['description'] ?? ''),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        return $this->findAlbum($id);
    }

    public function findAlbum(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT pga.*, COUNT(pgi.id) AS photo_count FROM photo_gallery_albums pga LEFT JOIN photo_gallery_items pgi ON pgi.album_id=pga.id AND pgi.status <> "DELETED" WHERE pga.id=:id AND pga.status <> "DELETED" GROUP BY pga.id', ['id' => $id]);
        return $row ? $this->normalizeAlbum($row) : null;
    }

    public function createItem(array $data, array $stored, array $inspection, int $userId): array
    {
        $this->ensureSchema();
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') $title = basename((string)($data['original_name'] ?? 'Ảnh'));
        $albumId = (int)($data['album_id'] ?? 0);
        if ($albumId > 0 && !$this->findAlbum($albumId)) throw new \RuntimeException($this->u('Album kh\u00f4ng h\u1ee3p l\u1ec7'));
        $id = $this->insert('INSERT INTO photo_gallery_items (album_id, title, description, original_name, stored_name, file_path, mime_type, file_size, event_date, area_code, source_module, source_id, tags_text, created_by, updated_by) VALUES (:album_id,:title,:description,:original_name,:stored_name,:file_path,:mime_type,:file_size,:event_date,:area_code,:source_module,:source_id,:tags_text,:created_by,:updated_by)', [
            'album_id' => $albumId > 0 ? $albumId : null,
            'title' => mb_substr($title, 0, 255),
            'description' => $this->nullable($data['description'] ?? ''),
            'original_name' => mb_substr(basename((string)$data['original_name']), 0, 255),
            'stored_name' => $stored['stored_name'],
            'file_path' => $stored['file_path'],
            'mime_type' => $inspection['mime'],
            'file_size' => (int)($data['file_size'] ?? 0),
            'event_date' => $this->dateOrNull($data['event_date'] ?? ''),
            'area_code' => $this->nullable($data['area_code'] ?? ''),
            'source_module' => $this->source($data['source_module'] ?? ''),
            'source_id' => ((int)($data['source_id'] ?? 0)) > 0 ? (int)$data['source_id'] : null,
            'tags_text' => $this->tags($data['tags'] ?? $data['tags_text'] ?? ''),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        return $this->findItem($id);
    }

    public function findItem(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT pgi.*, pga.name AS album_name, pga.album_code FROM photo_gallery_items pgi LEFT JOIN photo_gallery_albums pga ON pga.id=pgi.album_id WHERE pgi.id=:id AND pgi.status <> "DELETED"', ['id' => $id]);
        return $row ? $this->normalizeItem($row) : null;
    }

    public function updateItem(int $id, array $data, int $userId): ?array
    {
        $existing = $this->findItem($id);
        if (!$existing) return null;
        $albumId = (int)($data['album_id'] ?? $data['albumId'] ?? 0);
        if ($albumId > 0 && !$this->findAlbum($albumId)) throw new \RuntimeException($this->u('Album kh\u00f4ng h\u1ee3p l\u1ec7'));
        $this->execute('UPDATE photo_gallery_items SET album_id=:album_id, title=:title, description=:description, event_date=:event_date, area_code=:area_code, source_module=:source_module, source_id=:source_id, tags_text=:tags_text, updated_by=:updated_by WHERE id=:id AND status <> "DELETED"', [
            'id' => $id,
            'album_id' => $albumId > 0 ? $albumId : null,
            'title' => mb_substr(trim((string)($data['title'] ?? $existing['title'])), 0, 255),
            'description' => $this->nullable($data['description'] ?? ''),
            'event_date' => $this->dateOrNull($data['event_date'] ?? ''),
            'area_code' => $this->nullable($data['area_code'] ?? ''),
            'source_module' => $this->source($data['source_module'] ?? ''),
            'source_id' => ((int)($data['source_id'] ?? 0)) > 0 ? (int)$data['source_id'] : null,
            'tags_text' => $this->tags($data['tags'] ?? $data['tags_text'] ?? ''),
            'updated_by' => $userId,
        ]);
        return $this->findItem($id);
    }

    public function softDeleteItem(int $id, int $userId): void
    {
        $this->execute('UPDATE photo_gallery_items SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    public function itemPath(int $id): ?string
    {
        $row = $this->fetchOne('SELECT file_path FROM photo_gallery_items WHERE id=:id AND status <> "DELETED"', ['id' => $id]);
        return $row ? (string)$row['file_path'] : null;
    }

    private function where(array $filters, bool $withOrder = true): array
    {
        $where = ['pgi.status <> "DELETED"'];
        $params = [];
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(LOWER(pgi.title) LIKE :q OR LOWER(pgi.description) LIKE :q OR LOWER(pgi.original_name) LIKE :q OR LOWER(pgi.tags_text) LIKE :q OR LOWER(pga.name) LIKE :q)';
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        }
        foreach (['album_id' => 'pgi.album_id', 'area_code' => 'pgi.area_code', 'source_module' => 'pgi.source_module'] as $key => $column) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') { $where[] = "$column = :$key"; $params[$key] = $value; }
        }
        $tag = trim((string)($filters['tag'] ?? ''));
        if ($tag !== '') { $where[] = 'LOWER(pgi.tags_text) LIKE :tag'; $params['tag'] = '%' . mb_strtolower($tag, 'UTF-8') . '%'; }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { $where[] = "pgi.event_date $op :$key"; $params[$key] = $value; }
        }
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = $this->listOrder($filters, ['created_at' => 'pgi.created_at', 'event_date' => 'pgi.event_date', 'title' => 'pgi.title', 'album' => 'pga.name'], 'created_at', 'DESC', ['pgi.id DESC']);
        return $result;
    }

    private function normalizeItem(array $row): array
    {
        $id = (int)$row['id'];
        $tags = array_values(array_filter(array_map('trim', explode(',', (string)($row['tags_text'] ?? '')))));
        return [
            'id' => $id,
            'album_id' => $row['album_id'] !== null ? (int)$row['album_id'] : null,
            'album_name' => (string)($row['album_name'] ?? ''),
            'album_code' => (string)($row['album_code'] ?? ''),
            'title' => (string)$row['title'],
            'description' => (string)($row['description'] ?? ''),
            'original_name' => (string)$row['original_name'],
            'mime_type' => (string)$row['mime_type'],
            'file_size' => (int)($row['file_size'] ?? 0),
            'event_date' => $row['event_date'] ?? null,
            'area_code' => (string)($row['area_code'] ?? ''),
            'source_module' => (string)($row['source_module'] ?? ''),
            'source_id' => $row['source_id'] !== null ? (int)$row['source_id'] : null,
            'tags' => $tags,
            'tags_text' => implode(', ', $tags),
            'preview_url' => '/api/photo-gallery/' . $id . '/preview',
            'download_url' => '/api/photo-gallery/' . $id . '/download',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function normalizeAlbum(array $row): array
    {
        return ['id' => (int)$row['id'], 'album_code' => (string)$row['album_code'], 'name' => (string)$row['name'], 'description' => (string)($row['description'] ?? ''), 'photo_count' => (int)($row['photo_count'] ?? 0)];
    }

    private function nextAlbumCode(): string { $row = $this->fetchOne('SELECT MAX(id) AS max_id FROM photo_gallery_albums'); return 'ALB09-' . str_pad((string)(((int)($row['max_id'] ?? 0)) + 1), 4, '0', STR_PAD_LEFT); }
    private function nullable(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value === '' ? null : mb_substr($value, 0, 500); }
    private function dateOrNull(mixed $value): ?string { $value = trim((string)($value ?? '')); return $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null; }
    private function source(mixed $value): ?string { $value = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$value)); return $value !== '' ? $value : null; }
    private function tags(mixed $value): ?string
    {
        $raw = is_array($value) ? implode(',', $value) : (string)$value;
        $tags = array_slice(array_unique(array_filter(array_map(fn($v) => trim(mb_substr((string)$v, 0, 40)), preg_split('/[,;#]+/', $raw) ?: []))), 0, 10);
        return $tags ? implode(', ', $tags) : null;
    }
    private function u(string $text): string { return json_decode('"' . $text . '"') ?: $text; }
}
