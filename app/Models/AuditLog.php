<?php

namespace App\Models;

use App\Core\BaseModel;

final class AuditLog extends BaseModel
{
    public function page(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 50));
        $where = ['1=1']; $params = [];
        if (!empty($filters['module'])) { $where[] = 'module = :module'; $params['module'] = $filters['module']; }
        if (!empty($filters['action'])) { $where[] = 'action = :action'; $params['action'] = $filters['action']; }
        if (!empty($filters['search'])) { $where[] = '(actor_email LIKE :q OR message LIKE :q OR entity_id LIKE :q)'; $params['q'] = '%' . $filters['search'] . '%'; }
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM audit_logs $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT * FROM audit_logs $sqlWhere ORDER BY created_at DESC, id DESC LIMIT $pageSize OFFSET $offset", $params);
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }

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
