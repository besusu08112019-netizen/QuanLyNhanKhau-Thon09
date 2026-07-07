<?php

namespace App\Models;

use App\Core\BaseModel;

final class FileAttachment extends BaseModel
{
    public function create(array $data, int $userId): array
    {
        $params = $data;
        $params['module'] = $params['module'] ?? ($params['entity_type'] ?? '');
        $params['entity_type'] = $params['entity_type'] ?? $params['module'];
        $params['category'] = $params['category'] ?? ($params['profile_section'] ?? ($params['file_type'] ?? 'OTHER'));
        $params['file_name'] = $params['file_name'] ?? ($params['original_name'] ?? '');
        $params['original_name'] = $params['original_name'] ?? $params['file_name'];
        $params['created_by'] = $userId;

        $columns = ['module', 'entity_id', 'file_type', 'original_name', 'stored_name', 'file_path', 'mime_type', 'file_size', 'status', 'created_by'];
        $values = [':module', ':entity_id', ':file_type', ':original_name', ':stored_name', ':file_path', ':mime_type', ':file_size', '"ACTIVE"', ':created_by'];

        foreach (['entity_type', 'category', 'file_name', 'description', 'profile_section'] as $column) {
            if ($this->columnExists('file_attachments', $column)) {
                $columns[] = $column;
                $values[] = ':' . $column;
                $params[$column] = $params[$column] ?? null;
            }
        }

        if ($this->columnExists('file_attachments', 'updated_by')) {
            $columns[] = 'updated_by';
            $values[] = ':updated_by';
            $params['updated_by'] = $userId;
        }

        $sql = 'INSERT INTO file_attachments (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')';
        $bindParams = $this->prepareSqlParams($sql, $params);
        $this->logSqlBindingDebug($sql, $bindParams);
        $id = $this->insert($sql, $bindParams);
        return $this->find($id) ?: ['id' => $id] + $data;
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM file_attachments WHERE id=:id AND status="ACTIVE"', ['id' => $id]);
    }

    public function byEntity(string $entityType, int $entityId): array
    {
        if (!$this->tableExists('file_attachments')) {
            throw new \RuntimeException('Bảng file_attachments chưa tồn tại. Cần chạy migration 2026_06_28_admin_panel.sql trước khi dùng Hồ sơ số.');
        }

        $missing = array_diff(['id', 'module', 'entity_id'], $this->existingColumns('file_attachments', ['id', 'module', 'entity_id']));
        if ($missing) {
            throw new \RuntimeException('Bảng file_attachments thiếu cột bắt buộc: ' . implode(', ', $missing));
        }

        $where = ['f.entity_id = :entity_id'];
        $params = ['entity_type' => $entityType, 'entity_id' => $entityId];
        if ($this->columnExists('file_attachments', 'status')) {
            $where[] = 'f.status = "ACTIVE"';
        }
        if ($this->columnExists('file_attachments', 'entity_type')) {
            $where[] = 'COALESCE(f.entity_type, f.module) = :entity_type';
        } else {
            $where[] = 'f.module = :entity_type';
        }

        $select = 'f.*';
        $join = '';
        if ($this->tableExists('users') && $this->columnExists('file_attachments', 'created_by')) {
            $select .= ', uc.display_name AS created_by_name, uc.email AS created_by_email';
            $join = ' LEFT JOIN users uc ON uc.id = f.created_by';
            if ($this->columnExists('file_attachments', 'updated_by')) {
                $select .= ', uu.display_name AS updated_by_name, uu.email AS updated_by_email';
                $join .= ' LEFT JOIN users uu ON uu.id = f.updated_by';
            }
        }

        $orderBy = $this->columnExists('file_attachments', 'created_at') ? 'f.created_at DESC, f.id DESC' : 'f.id DESC';
        return $this->fetchAll('SELECT ' . $select . ' FROM file_attachments f' . $join . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy, $params);
    }

    public function searchByEntity(string $entityType, int $entityId, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($filters['pageSize'] ?? $filters['page_size'] ?? 24)));
        $offset = ($page - 1) * $pageSize;

        [$where, $params] = $this->entityWhere($entityType, $entityId);
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $searchParts = ['f.original_name LIKE :search_original'];
            $params['search_original'] = '%' . $search . '%';
            foreach (['file_name' => 'search_file', 'description' => 'search_description', 'profile_section' => 'search_section', 'category' => 'search_category'] as $column => $param) {
                if (!$this->columnExists('file_attachments', $column)) continue;
                $searchParts[] = 'f.' . $column . ' LIKE :' . $param;
                $params[$param] = '%' . $search . '%';
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        $category = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($filters['category'] ?? $filters['profileSection'] ?? '')));
        if ($category !== '') {
            $categoryParts = [];
            if ($this->columnExists('file_attachments', 'profile_section')) $categoryParts[] = 'f.profile_section = :category';
            if ($this->columnExists('file_attachments', 'category')) $categoryParts[] = 'f.category = :category';
            if (!$categoryParts) $categoryParts[] = 'f.file_type = :category';
            $where[] = '(' . implode(' OR ', $categoryParts) . ')';
            $params['category'] = $category;
        }

        $fileType = preg_replace('/[^A-Z_]/', '', strtoupper((string) ($filters['fileType'] ?? $filters['file_type'] ?? '')));
        if ($fileType !== '') {
            $where[] = 'f.file_type = :file_type';
            $params['file_type'] = $fileType;
        }

        $select = $this->fileSelectAndJoin();
        $whereSql = implode(' AND ', $where);
        $orderBy = $this->columnExists('file_attachments', 'created_at') ? 'f.created_at DESC, f.id DESC' : 'f.id DESC';
        $totalRow = $this->fetchOne('SELECT COUNT(*) AS total FROM file_attachments f WHERE ' . $whereSql, $params);
        $items = $this->fetchAll('SELECT ' . $select['select'] . ' FROM file_attachments f' . $select['join'] . ' WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . $pageSize . ' OFFSET ' . $offset, $params);

        return [
            'items' => array_map(fn(array $row): array => $this->normalizeRow($row), $items),
            'total' => (int) ($totalRow['total'] ?? 0),
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function softDelete(int $id, int $userId): void
    {
        $sets = ['status="DELETED"', 'deleted_at=NOW()', 'deleted_by=:deleted_by'];
        $params = ['id' => $id, 'deleted_by' => $userId];
        if ($this->columnExists('file_attachments', 'updated_by')) {
            $sets[] = 'updated_by=:updated_by';
            $params['updated_by'] = $userId;
        }
        if ($this->columnExists('file_attachments', 'updated_at')) $sets[] = 'updated_at=NOW()';
        $this->execute('UPDATE file_attachments SET ' . implode(',', $sets) . ' WHERE id=:id', $params);
    }
    public function updateMetadata(int $id, array $data, int $userId): ?array
    {
        $file = $this->find($id);
        if (!$file) return null;

        $sets = [];
        $params = ['id' => $id, 'user' => $userId];

        if (array_key_exists('file_name', $data) && $this->columnExists('file_attachments', 'file_name')) {
            $name = trim((string) $data['file_name']);
            if ($name === '') {
                throw new \RuntimeException('T?n file kh?ng ???c r?ng');
            }
            $sets[] = 'file_name=:file_name';
            $params['file_name'] = mb_substr($name, 0, 255);
        }

        if (array_key_exists('original_name', $data)) {
            $name = trim((string) $data['original_name']);
            if ($name !== '') {
                $sets[] = 'original_name=:original_name';
                $params['original_name'] = mb_substr($name, 0, 255);
            }
        }

        if (array_key_exists('description', $data) && $this->columnExists('file_attachments', 'description')) {
            $description = trim((string) $data['description']);
            $sets[] = 'description=:description';
            $params['description'] = $description !== '' ? mb_substr($description, 0, 500) : null;
        }

        if (array_key_exists('category', $data) && $this->columnExists('file_attachments', 'category')) {
            $category = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $data['category']));
            if ($category !== '') {
                $sets[] = 'category=:category';
                $params['category'] = $category;
            }
        }

        if (array_key_exists('profile_section', $data) && $this->columnExists('file_attachments', 'profile_section')) {
            $section = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $data['profile_section']));
            if ($section !== '') {
                $sets[] = 'profile_section=:profile_section';
                $params['profile_section'] = $section;
            }
        }

        if (array_key_exists('file_type', $data)) {
            $fileType = preg_replace('/[^A-Z_]/', '', strtoupper((string) $data['file_type']));
            if ($fileType !== '') {
                $sets[] = 'file_type=:file_type';
                $params['file_type'] = $fileType;
            }
        }

        if (!$sets) return $file;
        if ($this->columnExists('file_attachments', 'updated_by')) $sets[] = 'updated_by=:user';
        if ($this->columnExists('file_attachments', 'updated_at')) $sets[] = 'updated_at=NOW()';
        $this->execute('UPDATE file_attachments SET ' . implode(',', $sets) . ' WHERE id=:id AND status="ACTIVE"', $params);
        return $this->find($id);
    }

    public function normalizeRow(array $row): array
    {
        $row['entity_type'] = $row['entity_type'] ?? ($row['module'] ?? '');
        $row['category'] = $row['category'] ?? ($row['profile_section'] ?? ($row['file_type'] ?? ''));
        $row['file_name'] = $row['file_name'] ?? ($row['original_name'] ?? '');
        $row['display_name'] = $row['file_name'] ?: ($row['original_name'] ?? '');
        $row['version'] = $row['version'] ?? null;
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $row['preview_url'] = '/api/files/' . $id . '/preview';
            $row['download_url'] = '/api/files/' . $id . '/download';
        }
        return $row;
    }

    private function entityWhere(string $entityType, int $entityId): array
    {
        $where = ['f.entity_id = :entity_id'];
        $params = ['entity_type' => $entityType, 'entity_id' => $entityId];
        if ($this->columnExists('file_attachments', 'status')) {
            $where[] = 'f.status = "ACTIVE"';
        }
        if ($this->columnExists('file_attachments', 'entity_type')) {
            $where[] = 'COALESCE(f.entity_type, f.module) = :entity_type';
        } else {
            $where[] = 'f.module = :entity_type';
        }
        return [$where, $params];
    }

    private function fileSelectAndJoin(): array
    {
        $select = 'f.*';
        $join = '';
        if ($this->tableExists('users') && $this->columnExists('file_attachments', 'created_by')) {
            $select .= ', uc.display_name AS created_by_name, uc.email AS created_by_email';
            $join = ' LEFT JOIN users uc ON uc.id = f.created_by';
            if ($this->columnExists('file_attachments', 'updated_by')) {
                $select .= ', uu.display_name AS updated_by_name, uu.email AS updated_by_email';
                $join .= ' LEFT JOIN users uu ON uu.id = f.updated_by';
            }
        }
        return ['select' => $select, 'join' => $join];
    }

    private function prepareSqlParams(string $sql, array $params): array
    {
        $placeholders = $this->sqlPlaceholders($sql);
        $missing = array_values(array_diff($placeholders, array_keys($params)));
        if ($missing) {
            throw new \RuntimeException('SQL binding missing parameters: ' . implode(', ', $missing));
        }
        return array_intersect_key($params, array_flip($placeholders));
    }

    private function sqlPlaceholders(string $sql): array
    {
        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $sql, $matches);
        return array_values(array_unique(array_map(static fn(string $placeholder): string => substr($placeholder, 1), $matches[0] ?? [])));
    }

    private function logSqlBindingDebug(string $sql, array $params): void
    {
        error_log('[FileAttachment] SQL=' . $sql . ' placeholders=' . json_encode($this->sqlPlaceholders($sql)) . ' params=' . json_encode(array_keys($params)));
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
}
