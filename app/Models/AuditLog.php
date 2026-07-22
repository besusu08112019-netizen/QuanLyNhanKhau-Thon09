<?php

namespace App\Models;

use App\Core\BaseModel;

final class AuditLog extends BaseModel
{
    public function paginate(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 50));
        $where = ['1=1']; $params = [];
        if (!empty($filters['module'])) { $where[] = 'module = :module'; $params['module'] = $filters['module']; }
        if (!empty($filters['action'])) { $where[] = 'action = :action'; $params['action'] = $filters['action']; }
        if (!empty($filters['search'])) {
            $q = '%' . $filters['search'] . '%';
            $where[] = '(actor_email LIKE :q_actor OR message LIKE :q_message OR entity_id LIKE :q_entity)';
            $params['q_actor'] = $q;
            $params['q_message'] = $q;
            $params['q_entity'] = $q;
        }
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->fetchOne("SELECT COUNT(*) AS total FROM audit_logs $sqlWhere", $params)['total'];
        $items = $this->fetchAll("SELECT audit_logs.*, actor_email AS user_email, action AS action_name, module AS module_name, COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.ip')), '') AS ip_address FROM audit_logs $sqlWhere ORDER BY created_at DESC, id DESC LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated($items, $page, $pageSize, $total);
    }

    public function write(?int $userId, ?string $email, string $module, string $action, string $message, ?string $entityId = null, array $metadata = [], string $level = 'INFO'): void
    {
        $metadata = $this->redact($metadata);
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

    private function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return is_string($value) && preg_match('/Bearer\s+[a-f0-9]{32,}/i', $value) ? '[REDACTED]' : $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $key));
            if (preg_match('/(password|passwd|pwd|token|csrf|cookie|session|authorization|identity|cccd|phone|email|login)/', $normalized)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = $this->redact($item);
        }
        return $redacted;
    }
}
