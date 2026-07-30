<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ControlCenterPermissionRepository
{
    private ?PDO $db = null;

    public function overrides(): array
    {
        $rows = $this->fetchAll(
            'SELECT role, module, action, allowed
             FROM permissions
             WHERE module LIKE "control_center.%"'
        );

        $overrides = [];
        foreach ($rows as $row) {
            $key = $this->platformRole((string) $row['role']) . '|' . (string) $row['module'] . '.' . (string) $row['action'];
            $overrides[$key] = (bool) $row['allowed'];
        }
        return $overrides;
    }

    public function set(string $platformRole, string $permission, bool $allowed, int $actorId): void
    {
        $sourceRole = $this->sourceRole($platformRole);
        [$module, $action] = $this->splitPermission($permission);
        $stmt = $this->db()->prepare(
            'INSERT INTO permissions (role, module, action, allowed, updated_by)
             VALUES (:role, :module, :action, :allowed, :actor)
             ON DUPLICATE KEY UPDATE allowed = VALUES(allowed), updated_by = VALUES(updated_by)'
        );
        $stmt->execute([
            'role' => $sourceRole,
            'module' => $module,
            'action' => $action,
            'allowed' => $allowed ? 1 : 0,
            'actor' => $actorId,
        ]);
    }

    public function reset(string $platformRole, string $permission): void
    {
        $sourceRole = $this->sourceRole($platformRole);
        [$module, $action] = $this->splitPermission($permission);
        $stmt = $this->db()->prepare('DELETE FROM permissions WHERE role = :role AND module = :module AND action = :action');
        $stmt->execute(['role' => $sourceRole, 'module' => $module, 'action' => $action]);
    }

    public function findUserByToken(string $token): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT u.id, u.village_id, u.email, u.display_name, u.role, u.status
             FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = :hash
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()
               AND u.status = "ACTIVE"
             LIMIT 1'
        );
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function platformRole(string $sourceRole): string
    {
        return [
            'SUPER_ADMIN' => 'SYSTEM_ADMIN',
            'ADMIN' => 'VILLAGE_ADMIN',
            'OFFICER' => 'STAFF',
            'VIEWER' => 'VIEWER',
        ][$sourceRole] ?? $sourceRole;
    }

    public function sourceRole(string $platformRole): string
    {
        $source = [
            'SYSTEM_ADMIN' => 'SUPER_ADMIN',
            'VILLAGE_ADMIN' => 'ADMIN',
            'STAFF' => 'OFFICER',
            'VIEWER' => 'VIEWER',
            'SUPER_ADMIN' => 'SUPER_ADMIN',
            'ADMIN' => 'ADMIN',
            'OFFICER' => 'OFFICER',
        ][$platformRole] ?? '';
        if ($source === '') {
            throw new \InvalidArgumentException('Vai trò không hợp lệ');
        }
        return $source;
    }

    private function splitPermission(string $permission): array
    {
        $parts = explode('.', $permission);
        if (count($parts) < 3) {
            throw new \InvalidArgumentException('Quyền không hợp lệ');
        }
        $action = array_pop($parts);
        return [implode('.', $parts), $action];
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    private function db(): PDO
    {
        return $this->db ??= Database::pdo();
    }
}
