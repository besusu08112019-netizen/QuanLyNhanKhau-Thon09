<?php

namespace App\Models;

use App\Core\BaseModel;

final class FileAttachment extends BaseModel
{
    public function create(array $data, int $userId): array
    {
        $params = $data + ['user' => $userId];
        $params['module'] = $params['module'] ?? ($params['entity_type'] ?? '');
        $params['entity_type'] = $params['entity_type'] ?? $params['module'];
        $params['category'] = $params['category'] ?? ($params['profile_section'] ?? ($params['file_type'] ?? 'OTHER'));
        $params['file_name'] = $params['file_name'] ?? ($params['original_name'] ?? '');
        $params['original_name'] = $params['original_name'] ?? $params['file_name'];

        $columns = ['module', 'entity_id', 'file_type', 'original_name', 'stored_name', 'file_path', 'mime_type', 'file_size', 'status', 'created_by'];
        $values = [':module', ':entity_id', ':file_type', ':original_name', ':stored_name', ':file_path', ':mime_type', ':file_size', '"ACTIVE"', ':user'];

        foreach (['entity_type', 'category', 'file_name', 'description', 'profile_section', 'updated_by'] as $column) {
            if ($this->columnExists('file_attachments', $column)) {
                $columns[] = $column;
                $values[] = $column === 'updated_by' ? ':user' : ':' . $column;
                $params[$column] = $params[$column] ?? null;
            }
        }

        $id = $this->insert('INSERT INTO file_attachments (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')', $params);
        return $this->find($id) ?: ['id' => $id] + $data;
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM file_attachments WHERE id=:id AND status="ACTIVE"', ['id' => $id]);
    }

    public function byEntity(string $entityType, int $entityId): array
    {
        $where = ['f.entity_id = :entity_id', 'f.status = "ACTIVE"'];
        $params = ['entity_type' => $entityType, 'entity_id' => $entityId];
        if ($this->columnExists('file_attachments', 'entity_type')) {
            $where[] = '(f.entity_type = :entity_type OR f.module = :entity_type)';
        } else {
            $where[] = 'f.module = :entity_type';
        }
        $select = 'f.*';
        $join = '';
        if ($this->tableExists('users')) {
            $select .= ', uc.display_name AS created_by_name, uc.email AS created_by_email';
            $join = ' LEFT JOIN users uc ON uc.id = f.created_by';
            if ($this->columnExists('file_attachments', 'updated_by')) {
                $select .= ', uu.display_name AS updated_by_name, uu.email AS updated_by_email';
                $join .= ' LEFT JOIN users uu ON uu.id = f.updated_by';
            }
        }
        return $this->fetchAll('SELECT ' . $select . ' FROM file_attachments f' . $join . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY f.created_at DESC, f.id DESC', $params);
    }

    public function softDelete(int $id, int $userId): void
    {
        $sets = ['status="DELETED"', 'deleted_at=NOW()', 'deleted_by=:user'];
        if ($this->columnExists('file_attachments', 'updated_by')) $sets[] = 'updated_by=:user';
        if ($this->columnExists('file_attachments', 'updated_at')) $sets[] = 'updated_at=NOW()';
        $this->execute('UPDATE file_attachments SET ' . implode(',', $sets) . ' WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }

    public function normalizeRow(array $row): array
    {
        $row['entity_type'] = $row['entity_type'] ?? ($row['module'] ?? '');
        $row['category'] = $row['category'] ?? ($row['profile_section'] ?? ($row['file_type'] ?? ''));
        $row['file_name'] = $row['file_name'] ?? ($row['original_name'] ?? '');
        return $row;
    }
    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
}
