<?php

namespace App\Models;

use App\Core\BaseModel;

final class AuditLog extends BaseModel
{
    public function write(?int $userId, ?string $email, string $module, string $action, string $message, ?string $entityId = null, array $metadata = [], string $level = 'INFO'): void
    {
        $this->insert('INSERT INTO audit_logs (actor_user_id, actor_email, module, action, entity_id, level, message, metadata) VALUES (:actor_user_id, :actor_email, :module, :action, :entity_id, :level, :message, :metadata)', [
            'actor_user_id' => $userId,
            'actor_email' => $email,
            'module' => $module,
            'action' => $action,
            'entity_id' => $entityId,
            'level' => $level,
            'message' => $message,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
