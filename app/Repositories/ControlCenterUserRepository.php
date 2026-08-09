<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ControlCenterUserRepository
{
    private const DIRECTORY_ID_FACTOR = 1000000;

    private ?PDO $db = null;
    private array $columnCache = [];

    public function paginate(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(5, (int) ($filters['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $items = $this->directoryItems($filters);
        $total = count($items);
        $items = array_slice($items, $offset, $pageSize);

        return [
            'items' => $items,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    private function directoryItems(array $filters): array
    {
        $items = [];
        foreach ($this->registryTenants() as $tenant) {
            foreach ($this->tenantUsers($tenant) as $row) {
                $items[] = $this->normalizeDirectoryRow($tenant, $row);
            }
        }

        $items = array_values(array_filter($items, fn(array $item): bool => $this->matchesDirectoryFilters($item, $filters)));
        usort($items, static fn(array $a, array $b): int => strcasecmp($a['unitName'] . ' ' . $a['displayName'], $b['unitName'] . ' ' . $b['displayName']));
        return $items;
    }

    public function find(int $id): ?array
    {
        [$tenantId, $userId] = $this->decodeDirectoryId($id);
        $tenant = $this->tenantById($tenantId);
        if (!$tenant) {
            return null;
        }
        $row = $this->tenantUser($tenant, $userId);
        return $row ? $this->normalizeDirectoryRow($tenant, $row) : null;
    }

    public function create(array $data): array
    {
        $tenant = $this->tenantById((int) $data['unit_id']);
        if (!$tenant) {
            throw new \RuntimeException('Không tìm thấy đơn vị');
        }
        $pdo = $this->tenantPdo($tenant);
        $this->ensureTenantUserCompatibility($pdo);
        $tenantColumns = $this->tenantUserColumns($pdo);
        $columns = ['village_id', 'email', 'display_name', 'password_hash', 'role', 'status', 'created_by'];
        $params = [
            'village_id' => $this->tenantLocalVillageId($pdo, $tenant),
            'email' => $data['email'],
            'display_name' => $data['display_name'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => $data['source_role'],
            'status' => $data['status'],
            'created_by' => $this->actorIdForTenant((int) $data['unit_id'], (int) $data['actor_id']),
        ];

        if (in_array('username', $tenantColumns, true)) {
            $columns[] = 'username';
            $params['username'] = $data['username'];
        }
        if (in_array('phone', $tenantColumns, true)) {
            $columns[] = 'phone';
            $params['phone'] = $data['phone'] ?? null;
        }
        if (in_array('position', $tenantColumns, true)) {
            $columns[] = 'position';
            $params['position'] = $data['position'] ?? null;
        }

        $stmt = $pdo->prepare('INSERT INTO users (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')');
        $stmt->execute($params);
        return $this->find($this->encodeDirectoryId((int) $tenant['id'], (int) $pdo->lastInsertId())) ?? [];
    }

    public function update(int $id, array $data): array
    {
        [$tenantId, $userId] = $this->decodeDirectoryId($id);
        if ($tenantId !== (int) $data['unit_id']) {
            throw new \RuntimeException('Không hỗ trợ chuyển người dùng giữa các đơn vị');
        }
        $tenant = $this->tenantById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException('Không tìm thấy đơn vị');
        }
        $pdo = $this->tenantPdo($tenant);
        $this->ensureTenantUserCompatibility($pdo);
        $tenantColumns = $this->tenantUserColumns($pdo);
        $sets = ['email = :email', 'display_name = :display_name', 'role = :role', 'status = :status', 'updated_by = :updated_by'];
        $params = [
            'id' => $userId,
            'email' => $data['email'],
            'display_name' => $data['display_name'],
            'role' => $data['source_role'],
            'status' => $data['status'],
            'updated_by' => $this->actorIdForTenant($tenantId, (int) $data['actor_id']),
        ];

        if (in_array('username', $tenantColumns, true)) {
            $sets[] = 'username = :username';
            $params['username'] = $data['username'];
        }
        if (in_array('phone', $tenantColumns, true)) {
            $sets[] = 'phone = :phone';
            $params['phone'] = $data['phone'] ?? null;
        }
        if (in_array('position', $tenantColumns, true)) {
            $sets[] = 'position = :position';
            $params['position'] = $data['position'] ?? null;
        }

        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id AND status <> "DELETED"');
        $stmt->execute($params);
        return $this->find($id) ?? [];
    }

    public function setStatus(int $id, string $status, int $actorId): array
    {
        [$tenantId, $userId] = $this->decodeDirectoryId($id);
        $tenant = $this->tenantById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException('Không tìm thấy đơn vị');
        }
        $pdo = $this->tenantPdo($tenant);
        $stmt = $pdo->prepare('UPDATE users SET status = :status, updated_by = :actor WHERE id = :id AND status <> "DELETED"');
        $stmt->execute(['id' => $userId, 'status' => $status, 'actor' => $this->actorIdForTenant($tenantId, $actorId)]);
        if ($status === 'INACTIVE') {
            $this->revokeSessions($id);
        }
        return $this->find($id) ?? [];
    }

    public function resetPassword(int $id, string $password, int $actorId): array
    {
        [$tenantId, $userId] = $this->decodeDirectoryId($id);
        $tenant = $this->tenantById($tenantId);
        if (!$tenant) {
            throw new \RuntimeException('Không tìm thấy đơn vị');
        }
        $pdo = $this->tenantPdo($tenant);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_by = :actor WHERE id = :id AND status <> "DELETED"');
        $stmt->execute(['id' => $userId, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'actor' => $this->actorIdForTenant($tenantId, $actorId)]);
        $this->revokeSessions($id);
        return $this->find($id) ?? [];
    }

    public function revokeSessions(int $id): void
    {
        [$tenantId, $userId] = $this->decodeDirectoryId($id);
        $tenant = $this->tenantById($tenantId);
        if (!$tenant) {
            return;
        }
        $pdo = $this->tenantPdo($tenant);
        $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = :id AND revoked_at IS NULL');
        $stmt->execute(['id' => $userId]);
    }

    public function existsByEmail(string $email, int $unitId, ?int $ignoreId = null): bool
    {
        return $this->exists('email', $email, $unitId, $ignoreId);
    }

    public function existsByUsername(string $username, int $unitId, ?int $ignoreId = null): bool
    {
        return $this->exists('username', $username, $unitId, $ignoreId);
    }

    public function unitExists(int $id): bool
    {
        return $this->fetchOne('SELECT id FROM villages WHERE id = :id LIMIT 1', ['id' => $id]) !== null;
    }

    public function activeSystemAdminCount(?int $ignoreId = null): int
    {
        $count = 0;
        foreach ($this->directoryItems([]) as $item) {
            if ((string) $item['sourceRole'] !== 'SUPER_ADMIN' || (string) $item['status'] !== 'ACTIVE') {
                continue;
            }
            if ($ignoreId !== null && (int) $item['id'] === $ignoreId) {
                continue;
            }
            $count++;
        }
        return $count;
    }

    private function exists(string $column, string $value, int $unitId, ?int $ignoreId): bool
    {
        $tenant = $this->tenantById($unitId);
        if (!$tenant) {
            return false;
        }
        $pdo = $this->tenantPdo($tenant);
        $this->ensureTenantUserCompatibility($pdo);
        [, $localIgnoreId] = $ignoreId !== null ? $this->decodeDirectoryId($ignoreId) : [0, 0];
        $params = ['value' => $value, 'unit_id' => $this->tenantLocalVillageId($pdo, $tenant)];
        $sql = 'SELECT id FROM users WHERE ' . $column . ' = :value AND village_id = :unit_id AND status <> "DELETED"';
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $localIgnoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
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

    private function registryTenants(): array
    {
        $stmt = $this->db()->query(
            "SELECT id, code, name, domain, database_name, database_host, database_charset, status
             FROM villages
             WHERE status IN ('ACTIVE','READY','MAINTENANCE')
             ORDER BY id ASC"
        );
        return $stmt->fetchAll() ?: [];
    }

    private function tenantUsers(array $tenant): array
    {
        try {
            $pdo = $this->tenantPdo($tenant);
            $this->ensureTenantUserCompatibility($pdo);
            $columns = $this->tenantUserColumns($pdo);
            $username = in_array('username', $columns, true) ? 'username' : 'NULL AS username';
            $phone = in_array('phone', $columns, true) ? 'phone' : 'NULL AS phone';
            $position = in_array('position', $columns, true) ? 'position' : 'NULL AS position';
            $createdByJoin = in_array('created_by', $columns, true)
                ? 'LEFT JOIN users creator ON creator.id = u.created_by'
                : '';
            $createdBySelect = in_array('created_by', $columns, true) ? 'u.created_by' : 'NULL AS created_by';
            $createdByNameSelect = in_array('created_by', $columns, true) ? 'creator.display_name AS created_by_name' : 'NULL AS created_by_name';
            $lastLogin = in_array('last_login_at', $columns, true) ? 'u.last_login_at' : 'NULL AS last_login_at';
            $createdAt = in_array('created_at', $columns, true) ? 'u.created_at' : 'NULL AS created_at';

            $sql = "
                SELECT
                    u.id,
                    $username,
                    u.email,
                    u.display_name,
                    $phone,
                    $position,
                    u.role,
                    u.status,
                    $lastLogin,
                    $createdAt,
                    $createdBySelect,
                    $createdByNameSelect
                FROM users u
                $createdByJoin
                WHERE u.status <> 'DELETED'
                ORDER BY u.display_name ASC, u.id ASC
            ";
            return $pdo->query($sql)->fetchAll() ?: [];
        } catch (\Throwable $e) {
            error_log('[CONTROL_CENTER_USER_DIRECTORY_TENANT_ERROR] ' . json_encode([
                'tenant' => $tenant['code'] ?? null,
                'domain' => $tenant['domain'] ?? null,
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return [];
        }
    }


    private function tenantLocalVillageId(PDO $pdo, array $tenant): int
    {
        $centralId = (int) ($tenant['id'] ?? 0);
        $code = trim((string) ($tenant['code'] ?? ''));
        $domain = trim((string) ($tenant['domain'] ?? ''));
        $name = trim((string) ($tenant['name'] ?? ''));

        $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'villages'")->fetchColumn();
        if (!$tableExists) {
            throw new \RuntimeException('Tenant database thi?u b?ng villages');
        }

        $columns = $pdo->query('SHOW COLUMNS FROM villages')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $conditions = [];
        $params = [];
        if (in_array('code', $columns, true) && $code !== '') {
            $conditions[] = 'code = :code';
            $params['code'] = $code;
        }
        if (in_array('domain', $columns, true) && $domain !== '') {
            $conditions[] = 'domain = :domain';
            $params['domain'] = $domain;
        }
        if (in_array('name', $columns, true) && $name !== '') {
            $conditions[] = 'name = :name';
            $params['name'] = $name;
        }
        if ($conditions !== []) {
            $stmt = $pdo->prepare('SELECT id FROM villages WHERE ' . implode(' OR ', $conditions) . ' ORDER BY id ASC LIMIT 1');
            $stmt->execute($params);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) return $id;
        }

        $count = (int) $pdo->query('SELECT COUNT(*) FROM villages')->fetchColumn();
        if ($count === 1) {
            return (int) $pdo->query('SELECT id FROM villages ORDER BY id ASC LIMIT 1')->fetchColumn();
        }

        if (in_array('id', $columns, true) && $centralId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM villages WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $centralId]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) return $id;
        }

        $insertColumns = [];
        $insertParams = [];
        foreach ([
            'id' => $centralId > 0 ? $centralId : null,
            'code' => $code !== '' ? $code : ('tenant-' . ($centralId ?: time())),
            'name' => $name !== '' ? $name : ($code !== '' ? $code : 'Tenant'),
            'domain' => $domain !== '' ? $domain : null,
            'status' => 'ACTIVE',
            'database_name' => (string) ($tenant['database_name'] ?? ''),
            'database_host' => (string) ($tenant['database_host'] ?? ''),
            'database_charset' => (string) ($tenant['database_charset'] ?? ''),
        ] as $column => $value) {
            if ($value !== null && in_array($column, $columns, true)) {
                $insertColumns[] = $column;
                $insertParams[$column] = $value;
            }
        }
        if (!in_array('name', $insertColumns, true) || !in_array('code', $insertColumns, true)) {
            throw new \RuntimeException('Kh?ng x?c ??nh ???c village local c?a tenant');
        }

        $sql = 'INSERT INTO villages (' . implode(',', $insertColumns) . ') VALUES (:' . implode(',:', $insertColumns) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertParams);
        return (int) ($insertParams['id'] ?? $pdo->lastInsertId());
    }

    private function tenantPdo(array $tenant): PDO
    {
        $config = $this->tenantDatabaseConfig((string) ($tenant['domain'] ?? ''));
        $database = $config['database'] ?? (string) ($tenant['database_name'] ?? '');
        $host = $config['host'] ?? (string) ($tenant['database_host'] ?: 'localhost');
        $port = (int) ($config['port'] ?? 3306);
        $username = trim((string) ($config['username'] ?? '')) !== ''
            ? (string) $config['username']
            : (string) env(['TENANT_REGISTRY_DB_USERNAME', 'DB_USERNAME', 'DB_USER']);
        $password = array_key_exists('password', $config) && (string) $config['password'] !== ''
            ? (string) $config['password']
            : (string) env(['TENANT_REGISTRY_DB_PASSWORD', 'DB_PASSWORD', 'DB_PASS'], '');
        $charset = (string) ($config['charset'] ?? ($tenant['database_charset'] ?: 'utf8mb4'));

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Chưa cấu hình cơ sở dữ liệu của đơn vị');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
    }

    private function tenantDatabaseConfig(string $domain): array
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/:\d+$/', '', $domain) ?? $domain;
        $domain = preg_replace('/[^a-z0-9.-]/', '', $domain) ?? '';
        $path = $domain !== '' ? BASE_PATH . '/.env.' . $domain : '';
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
            $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return [
            'host' => $values['DB_HOST'] ?? 'localhost',
            'port' => (int) ($values['DB_PORT'] ?? 3306),
            'database' => $values['DB_DATABASE'] ?? $values['DB_NAME'] ?? '',
            'username' => $values['DB_USERNAME'] ?? $values['DB_USER'] ?? '',
            'password' => $values['DB_PASSWORD'] ?? $values['DB_PASS'] ?? '',
            'charset' => $values['DB_CHARSET'] ?? 'utf8mb4',
        ];
    }


    private function ensureTenantUserCompatibility(PDO $pdo): void
    {
        $columns = $this->tenantUserColumns($pdo);
        if (!in_array('username', $columns, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(60) NULL AFTER id');
            $columns[] = 'username';
        }
        if (in_array('username', $columns, true)) {
            $pdo->exec("UPDATE users SET username = LOWER(SUBSTRING_INDEX(email, '@', 1)) WHERE username IS NULL OR username = ''");
        }
    }

    private function tenantUserColumns(PDO $pdo): array
    {
        return $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function normalizeDirectoryRow(array $tenant, array $row): array
    {
        $tenantId = (int) $tenant['id'];
        $userId = (int) $row['id'];
        $unitName = $this->directoryUnitName($tenant, $row);
        $lastLogin = $row['last_login_at'] ?? null;

        return [
            'id' => $this->encodeDirectoryId($tenantId, $userId),
            'localUserId' => $userId,
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) $row['email'],
            'displayName' => (string) $row['display_name'],
            'display_name' => (string) $row['display_name'],
            'phone' => (string) ($row['phone'] ?? ''),
            'position' => (string) ($row['position'] ?? ''),
            'role' => $this->platformRole((string) $row['role']),
            'sourceRole' => (string) $row['role'],
            'status' => (string) $row['status'],
            'unitId' => $tenantId,
            'unitName' => $unitName,
            'unitCode' => (string) ($tenant['code'] ?? ''),
            'tenantDomain' => (string) ($tenant['domain'] ?? ''),
            'lastLoginAt' => $lastLogin,
            'lastLoginLabel' => $lastLogin ? (string) $lastLogin : 'Chưa đăng nhập',
            'lastIp' => null,
            'lastDevice' => null,
            'createdAt' => $row['created_at'] ?? null,
            'createdBy' => (string) ($row['created_by_name'] ?? ''),
            'createdById' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'readOnly' => true,
        ];
    }

    private function tenantById(int $id): ?array
    {
        $row = $this->fetchOne(
            'SELECT id, code, name, domain, database_name, database_host, database_charset, status FROM villages WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    private function tenantUser(array $tenant, int $userId): ?array
    {
        $rows = $this->tenantUsers($tenant);
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $userId) {
                return $row;
            }
        }
        return null;
    }

    private function encodeDirectoryId(int $tenantId, int $userId): int
    {
        return ($tenantId * self::DIRECTORY_ID_FACTOR) + $userId;
    }

    private function decodeDirectoryId(int $id): array
    {
        if ($id >= self::DIRECTORY_ID_FACTOR) {
            return [intdiv($id, self::DIRECTORY_ID_FACTOR), $id % self::DIRECTORY_ID_FACTOR];
        }
        return [1, $id];
    }

    private function actorIdForTenant(int $tenantId, int $actorId): ?int
    {
        [$actorTenantId, $actorLocalId] = $this->decodeDirectoryId($actorId);
        return $actorTenantId === $tenantId ? $actorLocalId : null;
    }

    private function directoryUnitName(array $tenant, array $row): string
    {
        $email = strtolower((string) ($row['email'] ?? ''));
        $name = strtolower((string) ($row['display_name'] ?? ''));
        if ($email === 'admin@hongphongnb.com' || str_contains($name, 'community control center')) {
            return 'CCC';
        }
        return (string) ($tenant['name'] ?: $tenant['code'] ?: $tenant['domain'] ?: 'Tenant');
    }

    private function matchesDirectoryFilters(array $item, array $filters): bool
    {
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && (string) $item['status'] !== $status) {
            return false;
        }

        $role = strtoupper(trim((string) ($filters['role'] ?? '')));
        if ($role !== '' && (string) $item['role'] !== $role) {
            return false;
        }

        $unitId = (int) ($filters['unit_id'] ?? 0);
        if ($unitId > 0 && (int) $item['unitId'] !== $unitId) {
            return false;
        }

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')), 'UTF-8');
        if ($search === '') {
            return true;
        }

        $haystack = mb_strtolower(implode(' ', [
            $item['username'],
            $item['email'],
            $item['displayName'],
            $item['role'],
            $item['sourceRole'],
            $item['status'],
            $item['unitName'],
            $item['unitCode'],
            $item['tenantDomain'],
            $item['createdBy'],
        ]), 'UTF-8');

        return str_contains($haystack, $search);
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
            'lastLoginLabel' => $lastLogin ? (string) $lastLogin : 'Chưa đăng nhập',
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
