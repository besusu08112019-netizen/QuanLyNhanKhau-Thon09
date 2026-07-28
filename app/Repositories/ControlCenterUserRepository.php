<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ControlCenterUserRepository
{
    private ?PDO $db = null;
    private array $columnCache = [];

    public function paginate(array $filters = []): array
    {
        [$where, $params] = $this->filters($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(5, (int) ($filters['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $total = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM users u LEFT JOIN villages v ON v.id = u.village_id WHERE ' . implode(' AND ', $where), $params)['total'] ?? 0);
        $items = $this->fetchAll(
            $this->selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY u.display_name ASC, u.role ASC, v.name ASC LIMIT ' . $pageSize . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items' => array_map(fn(array $row): array => $this->normalize($row), $items),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    public function find(int $id): ?array
    {
        $row = $this->fetchOne($this->selectSql() . ' WHERE u.id = :id AND u.status <> "DELETED" LIMIT 1', ['id' => $id]);
        return $row ? $this->normalize($row) : null;
    }

    public function create(array $data): array
    {
        $columns = ['village_id', 'email', 'display_name', 'password_hash', 'role', 'status', 'created_by'];
        $params = [
            'village_id' => $data['unit_id'],
            'email' => $data['email'],
            'display_name' => $data['display_name'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['source_role'],
            'status' => $data['status'],
            'created_by' => $data['actor_id'],
        ];

        if ($this->hasColumn('username')) {
            $columns[] = 'username';
            $params['username'] = $data['username'];
        }
        if ($this->hasColumn('phone')) {
            $columns[] = 'phone';
            $params['phone'] = $data['phone'] ?? null;
        }
        if ($this->hasColumn('position')) {
            $columns[] = 'position';
            $params['position'] = $data['position'] ?? null;
        }

        $stmt = $this->db()->prepare('INSERT INTO users (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')');
        $stmt->execute($params);
        return $this->find((int) $this->db()->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): array
    {
        $sets = ['email = :email', 'display_name = :display_name', 'role = :role', 'status = :status', 'village_id = :village_id', 'updated_by = :updated_by'];
        $params = [
            'id' => $id,
            'email' => $data['email'],
            'display_name' => $data['display_name'],
            'role' => $data['source_role'],
            'status' => $data['status'],
            'village_id' => $data['unit_id'],
            'updated_by' => $data['actor_id'],
        ];

        if ($this->hasColumn('username')) {
            $sets[] = 'username = :username';
            $params['username'] = $data['username'];
        }
        if ($this->hasColumn('phone')) {
            $sets[] = 'phone = :phone';
            $params['phone'] = $data['phone'] ?? null;
        }
        if ($this->hasColumn('position')) {
            $sets[] = 'position = :position';
            $params['position'] = $data['position'] ?? null;
        }

        $stmt = $this->db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id AND status <> "DELETED"');
        $stmt->execute($params);
        return $this->find($id) ?? [];
    }

    public function setStatus(int $id, string $status, int $actorId): array
    {
        $stmt = $this->db()->prepare('UPDATE users SET status = :status, updated_by = :actor WHERE id = :id AND status <> "DELETED"');
        $stmt->execute(['id' => $id, 'status' => $status, 'actor' => $actorId]);
        if ($status === 'INACTIVE') {
            $this->revokeSessions($id);
        }
        return $this->find($id) ?? [];
    }

    public function resetPassword(int $id, string $password, int $actorId): array
    {
        $stmt = $this->db()->prepare('UPDATE users SET password_hash = :hash, updated_by = :actor WHERE id = :id AND status <> "DELETED"');
        $stmt->execute(['id' => $id, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'actor' => $actorId]);
        $this->revokeSessions($id);
        return $this->find($id) ?? [];
    }

    public function revokeSessions(int $id): void
    {
        $stmt = $this->db()->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = :id AND revoked_at IS NULL');
        $stmt->execute(['id' => $id]);
    }

    public function existsByEmail(string $email, int $unitId, ?int $ignoreId = null): bool
    {
        return $this->exists('email', $email, $unitId, $ignoreId);
    }

    public function existsByUsername(string $username, int $unitId, ?int $ignoreId = null): bool
    {
        if (!$this->hasColumn('username')) {
            return false;
        }
        return $this->exists('username', $username, $unitId, $ignoreId);
    }

    public function unitExists(int $id): bool
    {
        return $this->fetchOne('SELECT id FROM villages WHERE id = :id LIMIT 1', ['id' => $id]) !== null;
    }

    public function activeSystemAdminCount(?int $ignoreId = null): int
    {
        $params = [];
        $where = 'role = "SUPER_ADMIN" AND status = "ACTIVE"';
        if ($ignoreId !== null) {
            $where .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        return (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM users WHERE ' . $where, $params)['total'] ?? 0);
    }

    private function exists(string $column, string $value, int $unitId, ?int $ignoreId): bool
    {
        $params = ['value' => $value, 'unit_id' => $unitId];
        $sql = 'SELECT id FROM users WHERE ' . $column . ' = :value AND village_id = :unit_id AND status <> "DELETED"';
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        return $this->fetchOne($sql, $params) !== null;
    }

    private function filters(array $filters): array
    {
        $where = ['u.status <> "DELETED"'];
        $params = [];

        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }

        $role = strtoupper(trim((string) ($filters['role'] ?? '')));
        $sourceRole = $this->sourceRole($role);
        if ($sourceRole !== null) {
            $where[] = 'u.role = :role';
            $params['role'] = $sourceRole;
        }

        $unitId = (int) ($filters['unit_id'] ?? 0);
        if ($unitId > 0) {
            $where[] = 'u.village_id = :unit_id';
            $params['unit_id'] = $unitId;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $parts = ['u.email LIKE :q_email', 'u.display_name LIKE :q_name', 'u.role LIKE :q_role', 'u.status LIKE :q_status', 'v.name LIKE :q_unit', 'v.code LIKE :q_unit_code'];
            $labelRole = $this->sourceRoleFromSearch($search);
            if ($labelRole !== null) {
                $parts[] = 'u.role = :q_label_role';
                $params['q_label_role'] = $labelRole;
            }
            if ($this->hasColumn('username')) {
                $parts[] = 'u.username LIKE :q_username';
                $params['q_username'] = '%' . $search . '%';
            }
            $like = '%' . $search . '%';
            $params += [
                'q_email' => $like,
                'q_name' => $like,
                'q_role' => $like,
                'q_status' => $like,
                'q_unit' => $like,
                'q_unit_code' => $like,
            ];
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        return [$where, $params];
    }

    private function selectSql(): string
    {
        $username = $this->hasColumn('username') ? 'u.username' : 'NULL AS username';
        $phone = $this->hasColumn('phone') ? 'u.phone' : 'NULL AS phone';
        $position = $this->hasColumn('position') ? 'u.position' : 'NULL AS position';

        return "
            SELECT
                u.id,
                $username,
                u.email,
                u.display_name,
                $phone,
                $position,
                u.role,
                u.status,
                u.village_id,
                v.name AS unit_name,
                v.code AS unit_code,
                u.last_login_at,
                u.created_at,
                u.created_by,
                creator.display_name AS created_by_name,
                (
                    SELECT s.ip_address
                    FROM user_sessions s
                    WHERE s.user_id = u.id
                    ORDER BY s.created_at DESC
                    LIMIT 1
                ) AS last_ip,
                (
                    SELECT s.user_agent
                    FROM user_sessions s
                    WHERE s.user_id = u.id
                    ORDER BY s.created_at DESC
                    LIMIT 1
                ) AS last_device
            FROM users u
            LEFT JOIN villages v ON v.id = u.village_id
            LEFT JOIN users creator ON creator.id = u.created_by
        ";
    }

    private function normalize(array $row): array
    {
        $lastLogin = $row['last_login_at'] ?? null;
        return [
            'id' => (int) $row['id'],
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) $row['email'],
            'displayName' => (string) $row['display_name'],
            'display_name' => (string) $row['display_name'],
            'phone' => (string) ($row['phone'] ?? ''),
            'position' => (string) ($row['position'] ?? ''),
            'role' => $this->platformRole((string) $row['role']),
            'sourceRole' => (string) $row['role'],
            'status' => (string) $row['status'],
            'unitId' => (int) $row['village_id'],
            'unitName' => (string) ($row['unit_name'] ?? ''),
            'unitCode' => (string) ($row['unit_code'] ?? ''),
            'lastLoginAt' => $lastLogin,
            'lastLoginLabel' => $lastLogin ? (string) $lastLogin : 'Chua dang nhap',
            'lastIp' => $row['last_ip'] ?? null,
            'lastDevice' => $this->deviceLabel((string) ($row['last_device'] ?? '')),
            'createdAt' => $row['created_at'] ?? null,
            'createdBy' => (string) ($row['created_by_name'] ?? ''),
            'createdById' => $row['created_by'] !== null ? (int) $row['created_by'] : null,
        ];
    }

    private function sourceRole(string $platformRole): ?string
    {
        return [
            'SYSTEM_ADMIN' => 'SUPER_ADMIN',
            'VILLAGE_ADMIN' => 'ADMIN',
            'STAFF' => 'OFFICER',
            'VIEWER' => 'VIEWER',
            'SUPER_ADMIN' => 'SUPER_ADMIN',
            'ADMIN' => 'ADMIN',
            'OFFICER' => 'OFFICER',
        ][$platformRole] ?? null;
    }

    private function platformRole(string $sourceRole): string
    {
        return [
            'SUPER_ADMIN' => 'SYSTEM_ADMIN',
            'ADMIN' => 'VILLAGE_ADMIN',
            'OFFICER' => 'STAFF',
            'VIEWER' => 'VIEWER',
        ][$sourceRole] ?? $sourceRole;
    }

    private function sourceRoleFromSearch(string $search): ?string
    {
        $normalized = strtolower(trim($search));
        foreach ([
            'quan tri he thong' => 'SUPER_ADMIN',
            'system_admin' => 'SUPER_ADMIN',
            'quan tri thon' => 'ADMIN',
            'village_admin' => 'ADMIN',
            'can bo nhap lieu' => 'OFFICER',
            'staff' => 'OFFICER',
            'chi xem' => 'VIEWER',
            'viewer' => 'VIEWER',
        ] as $label => $role) {
            if (str_contains($label, $normalized) || str_contains($normalized, $label)) {
                return $role;
            }
        }
        return null;
    }

    private function deviceLabel(string $userAgent): ?string
    {
        $agent = trim($userAgent);
        if ($agent === '') {
            return null;
        }
        return mb_strlen($agent, 'UTF-8') > 80 ? mb_substr($agent, 0, 77, 'UTF-8') . '...' : $agent;
    }

    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }
        $stmt = $this->db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "users" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        $row = $stmt->fetch();
        return $this->columnCache[$column] = ((int) ($row['total'] ?? 0) > 0);
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
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
