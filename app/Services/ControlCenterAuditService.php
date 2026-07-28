<?php

namespace App\Services;

use App\Core\Database;
use Throwable;

final class ControlCenterAuditService
{
    public function write(?array $actor, string $action, ?int $administrativeUnitId, string $message, array $metadata = [], string $level = 'INFO'): void
    {
        $metadata = $this->redact($metadata + [
            'portal' => 'CONTROL_CENTER',
            'actor_platform_role' => $actor['platformRole'] ?? null,
            'actor_source_role' => $actor['sourceRole'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'time' => date('c'),
        ]);

        try {
            $villageId = $administrativeUnitId ?: (int) ($actor['village_id'] ?? 0);
            if ($villageId <= 0) {
                $this->fallbackLog($actor, $action, $message, $metadata, $level);
                return;
            }

            $stmt = Database::pdo()->prepare(
                'INSERT INTO audit_logs (village_id, actor_user_id, actor_email, module, action, entity_id, level, message, metadata)
                 VALUES (:village_id, :actor_user_id, :actor_email, :module, :action, :entity_id, :level, :message, :metadata)'
            );
            $stmt->execute([
                'village_id' => $villageId,
                'actor_user_id' => $actor['id'] ?? null,
                'actor_email' => $actor['email'] ?? null,
                'module' => 'control_center_unit',
                'action' => $action,
                'entity_id' => $administrativeUnitId !== null ? (string) $administrativeUnitId : null,
                'level' => $level,
                'message' => $message,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            $this->fallbackLog($actor, $action, $message, $metadata + ['audit_error' => $e->getMessage()], 'WARN');
        }
    }

    private function fallbackLog(?array $actor, string $action, string $message, array $metadata, string $level): void
    {
        error_log('[CONTROL_CENTER_AUDIT] ' . json_encode([
            'level' => $level,
            'actor' => $actor['id'] ?? null,
            'action' => $action,
            'message' => $message,
            'metadata' => $metadata,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return is_string($value) && preg_match('/Bearer\s+[a-f0-9]{32,}/i', $value) ? '[REDACTED]' : $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $key));
            if (preg_match('/(password|passwd|pwd|token|csrf|cookie|session|authorization|secret)/', $normalized)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            $redacted[$key] = $this->redact($item);
        }
        return $redacted;
    }
}
