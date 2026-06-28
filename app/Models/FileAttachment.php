<?php

namespace App\Models;

use App\Core\BaseModel;

final class FileAttachment extends BaseModel
{
    public function create(array $data, int $userId): array
    {
        $id = $this->insert('INSERT INTO file_attachments (module, entity_id, file_type, original_name, stored_name, file_path, mime_type, file_size, status, created_by) VALUES (:module,:entity_id,:file_type,:original_name,:stored_name,:file_path,:mime_type,:file_size,"ACTIVE",:user)', $data + ['user' => $userId]);
        return $this->find($id);
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM file_attachments WHERE id=:id AND status="ACTIVE"', ['id' => $id]);
    }

    public function byEntity(string $module, int $entityId): array
    {
        return $this->fetchAll('SELECT * FROM file_attachments WHERE module=:module AND entity_id=:entity_id AND status="ACTIVE" ORDER BY created_at DESC', ['module' => $module, 'entity_id' => $entityId]);
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->execute('UPDATE file_attachments SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
    }
}
