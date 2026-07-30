<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TenantRegistryRepository
{
    private ?PDO $db = null;
    private array $columnCache = [];

    public function paginate(array $filters = []): array
    {
        [$where, $params] = $this->filters($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($filters['per_page'] ?? $filters['perPage'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $sort = $this->sortSql((string) ($filters['sort'] ?? ''), (string) ($filters['direction'] ?? ''));

        $total = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM villages v WHERE ' . implode(' AND ', $where), $params)['total'] ?? 0);
        $items = $this->fetchAll(
            $this->selectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sort . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return [
            'items' => array_map(fn(array $row): array => $this->normalize($row), $items),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $perPage)),
            ],
            'filters' => $this->normalizedFilters($filters),
        ];
    }

    public function find(int $id): ?array
    {
        $where = ['v.id = :id'];
        $params = ['id' => $id];
        $row = $this->fetchOne($this->selectSql() . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1', $params);
        return $row ? $this->normalize($row) : null;
    }

    public function create(array $data, array $actor): array
    {
        $columns = ['code', 'name', 'unit_name', 'commune_name', 'domain', 'subdomain', 'status'];
        $params = [
            'code' => $data['code'],
            'name' => $data['name'],
            'unit_name' => $data['unit_name'] ?? null,
            'commune_name' => $data['commune_name'] ?? null,
            'domain' => $data['domain'] ?? null,
            'subdomain' => $data['subdomain'] ?? null,
            'status' => $data['status'] ?? 'READY',
        ];

        foreach ($this->optionalColumnMap() as $input => $column) {
            if (!$this->hasColumn($column) || !array_key_exists($input, $data)) {
                continue;
            }
            $columns[] = $column;
            $params[$column] = $data[$input];
        }

        if ($this->hasColumn('last_status_changed_at')) {
            $columns[] = 'last_status_changed_at';
            $params['last_status_changed_at'] = date('Y-m-d H:i:s');
        }
        if ($this->hasColumn('last_status_changed_by')) {
            $columns[] = 'last_status_changed_by';
            $params['last_status_changed_by'] = $actor['id'] ?? null;
        }

        $stmt = $this->db()->prepare('INSERT INTO villages (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')');
        $stmt->execute($params);

        return $this->find((int) $this->db()->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data, array $actor): array
    {
        $sets = [];
        $params = ['id' => $id];
        $map = [
            'name' => 'name',
            'unit_name' => 'unit_name',
            'commune_name' => 'commune_name',
            'domain' => 'domain',
            'subdomain' => 'subdomain',
            'status' => 'status',
        ];
        foreach ($this->optionalColumnMap() as $input => $column) {
            if ($this->hasColumn($column)) {
                $map[$input] = $column;
            }
        }

        foreach ($map as $input => $column) {
            if (!array_key_exists($input, $data)) {
                continue;
            }
            $sets[] = $column . ' = :' . $input;
            $params[$input] = $data[$input];
        }

        if (array_key_exists('status', $data)) {
            if ($this->hasColumn('last_status_changed_at')) {
                $sets[] = 'last_status_changed_at = NOW()';
            }
            if ($this->hasColumn('last_status_changed_by')) {
                $sets[] = 'last_status_changed_by = :last_status_changed_by';
                $params['last_status_changed_by'] = $actor['id'] ?? null;
            }
        }

        if ($sets) {
            $stmt = $this->db()->prepare('UPDATE villages SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $stmt->execute($params);
        }

        return $this->find($id) ?? [];
    }

    public function lock(int $id, string $reason, array $actor): array
    {
        $sets = ['status = :status'];
        $params = [
            'id' => $id,
            'status' => 'DISABLED',
        ];
        foreach (['connection_status', 'website_status', 'database_status'] as $column) {
            if ($this->hasColumn($column)) {
                $sets[] = $column . ' = :locked_' . $column;
                $params['locked_' . $column] = 'LOCKED';
            }
        }
        if ($this->hasColumn('locked_at')) {
            $sets[] = 'locked_at = NOW()';
        }
        if ($this->hasColumn('locked_by')) {
            $sets[] = 'locked_by = :locked_by';
            $params['locked_by'] = $actor['id'] ?? null;
        }
        if ($this->hasColumn('lock_reason')) {
            $sets[] = 'lock_reason = :lock_reason';
            $params['lock_reason'] = $reason;
        }
        if ($this->hasColumn('last_status_changed_at')) {
            $sets[] = 'last_status_changed_at = NOW()';
        }
        if ($this->hasColumn('last_status_changed_by')) {
            $sets[] = 'last_status_changed_by = :last_status_changed_by';
            $params['last_status_changed_by'] = $actor['id'] ?? null;
        }

        $stmt = $this->db()->prepare('UPDATE villages SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $this->find($id) ?? [];
    }

    public function unlock(int $id, string $targetStatus, array $actor): array
    {
        $sets = ['status = :status'];
        $params = [
            'id' => $id,
            'status' => $targetStatus,
        ];
        foreach (['connection_status', 'website_status', 'database_status'] as $column) {
            if ($this->hasColumn($column)) {
                $sets[] = $column . ' = :unknown_' . $column;
                $params['unknown_' . $column] = 'UNKNOWN';
            }
        }
        foreach (['locked_at', 'locked_by', 'lock_reason'] as $column) {
            if ($this->hasColumn($column)) {
                $sets[] = $column . ' = NULL';
            }
        }
        if ($this->hasColumn('last_status_changed_at')) {
            $sets[] = 'last_status_changed_at = NOW()';
        }
        if ($this->hasColumn('last_status_changed_by')) {
            $sets[] = 'last_status_changed_by = :last_status_changed_by';
            $params['last_status_changed_by'] = $actor['id'] ?? null;
        }

        $stmt = $this->db()->prepare('UPDATE villages SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $this->find($id) ?? [];
    }

    public function softDelete(int $id, array $actor): array
    {
        $sets = ['status = :status'];
        $params = [
            'id' => $id,
            'status' => 'DISABLED',
        ];
        if ($this->hasColumn('deleted_at')) {
            $sets[] = 'deleted_at = NOW()';
        }
        if ($this->hasColumn('last_status_changed_at')) {
            $sets[] = 'last_status_changed_at = NOW()';
        }
        if ($this->hasColumn('last_status_changed_by')) {
            $sets[] = 'last_status_changed_by = :last_status_changed_by';
            $params['last_status_changed_by'] = $actor['id'] ?? null;
        }

        $stmt = $this->db()->prepare('UPDATE villages SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $this->find($id) ?? [];
    }

    public function activity(int $tenantId, array $filters = []): array
    {
        $where = ['a.village_id = :tenant_id'];
        $params = ['tenant_id' => $tenantId];

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $where[] = 'a.action = :action';
            $params['action'] = $action;
        }
        $severity = strtoupper(trim((string) ($filters['severity'] ?? '')));
        if (in_array($severity, ['INFO', 'WARN', 'ERROR'], true)) {
            $where[] = 'a.level = :severity';
            $params['severity'] = $severity;
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $where[] = 'a.created_at ' . $operator . ' :' . $key;
                $params[$key] = $value;
            }
        }

        $rows = $this->fetchAll(
            'SELECT a.id, a.created_at, a.actor_email, a.module, a.action, a.level, a.message, a.metadata
             FROM audit_logs a
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 100',
            $params
        );

        return ['items' => array_map(static function (array $row): array {
            $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
            return [
                'id' => (int) $row['id'],
                'createdAt' => $row['created_at'] ?? null,
                'actor' => (string) ($row['actor_email'] ?: 'System'),
                'module' => (string) ($row['module'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'severity' => (string) ($row['level'] ?? 'INFO'),
                'message' => (string) ($row['message'] ?? ''),
                'metadata' => is_array($metadata) ? $metadata : [],
            ];
        }, $rows)];
    }

    public function existsByCode(string $code, ?int $ignoreId = null): bool
    {
        return $this->exists('code', $code, $ignoreId);
    }

    public function existsByDomain(string $domain, ?int $ignoreId = null): bool
    {
        return $this->exists('domain', $domain, $ignoreId);
    }

    public function existsBySubdomain(string $subdomain, ?int $ignoreId = null): bool
    {
        return $this->exists('subdomain', $subdomain, $ignoreId);
    }

    private function exists(string $column, string $value, ?int $ignoreId): bool
    {
        if ($value === '') {
            return false;
        }
        $params = ['value' => $value];
        $sql = 'SELECT id FROM villages WHERE ' . $column . ' = :value';
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        return $this->fetchOne($sql, $params) !== null;
    }

    private function filters(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!$this->truthy($filters['include_deleted'] ?? false) && $this->hasColumn('deleted_at')) {
            $where[] = 'v.deleted_at IS NULL';
        }

        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status === 'LOCKED' && $this->hasColumn('locked_at')) {
            $where[] = 'v.locked_at IS NOT NULL';
        } elseif ($status === 'DELETED' && $this->hasColumn('deleted_at')) {
            $where[] = 'v.deleted_at IS NOT NULL';
        } elseif (in_array($status, ['ACTIVE', 'READY', 'DISABLED', 'CREATING', 'FAILED', 'MAINTENANCE', 'INACTIVE'], true)) {
            $where[] = 'v.status = :status';
            $params['status'] = $status === 'INACTIVE' ? 'DISABLED' : $status;
        }

        foreach ([
            'website_status' => ['ONLINE', 'OFFLINE', 'UNKNOWN', 'LOCKED'],
            'database_status' => ['CONNECTED', 'DISCONNECTED', 'UNKNOWN', 'LOCKED'],
            'ssl_status' => ['VALID', 'INVALID', 'UNKNOWN', 'NOT_APPLICABLE'],
        ] as $key => $allowed) {
            $value = strtoupper(trim((string) ($filters[$key] ?? '')));
            if ($value !== '' && in_array($value, $allowed, true) && $this->hasColumn($key)) {
                $where[] = 'v.' . $key . ' = :' . $key;
                $params[$key] = $value;
            }
        }

        $version = trim((string) ($filters['version'] ?? ''));
        if ($version !== '' && $this->hasColumn('app_version')) {
            $where[] = 'v.app_version = :version';
            $params['version'] = $version;
        }

        $search = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));
        if ($search !== '') {
            $parts = ['v.code LIKE :search', 'v.name LIKE :search', 'v.domain LIKE :search', 'v.subdomain LIKE :search'];
            if ($this->hasColumn('manager_name')) {
                $parts[] = 'v.manager_name LIKE :search';
            }
            if ($this->hasColumn('database_name')) {
                $parts[] = 'v.database_name LIKE :search';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
            $params['search'] = '%' . $search . '%';
        }

        return [$where, $params];
    }

    private function normalizedFilters(array $filters): array
    {
        return [
            'q' => (string) ($filters['q'] ?? $filters['search'] ?? ''),
            'status' => strtoupper(trim((string) ($filters['status'] ?? ''))),
            'websiteStatus' => strtoupper(trim((string) ($filters['website_status'] ?? ''))),
            'databaseStatus' => strtoupper(trim((string) ($filters['database_status'] ?? ''))),
            'sslStatus' => strtoupper(trim((string) ($filters['ssl_status'] ?? ''))),
            'version' => (string) ($filters['version'] ?? ''),
        ];
    }

    private function sortSql(string $sort, string $direction): string
    {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $map = [
            'name' => 'v.name',
            'status' => 'v.status',
            'updated' => 'v.updated_at',
            'updated_at' => 'v.updated_at',
            'last_checked' => $this->hasColumn('last_checked_at') ? 'v.last_checked_at' : 'v.updated_at',
            'storage' => $this->hasColumn('storage_usage_bytes') ? 'v.storage_usage_bytes' : 'v.id',
            'code' => 'v.code',
        ];
        $column = $map[$sort] ?? 'v.updated_at';
        return $column . ' ' . $direction . ', v.code ASC';
    }

    private function selectSql(): string
    {
        $columns = [
            'v.id',
            'v.code',
            'v.name',
            $this->columnSql('unit_name'),
            $this->columnSql('commune_name'),
            'v.domain',
            'v.subdomain',
            'v.status',
            $this->columnSql('database_host'),
            $this->columnSql('database_name'),
            $this->columnSql('database_charset'),
            $this->columnSql('app_version'),
            $this->columnSql('build_version'),
            $this->columnSql('schema_version'),
            $this->columnSql('connection_status'),
            $this->columnSql('website_status'),
            $this->columnSql('database_status'),
            $this->columnSql('ssl_status'),
            $this->columnSql('storage_usage_bytes'),
            $this->columnSql('storage_quota_bytes'),
            $this->columnSql('last_checked_at'),
            $this->columnSql('last_website_checked_at'),
            $this->columnSql('last_database_checked_at'),
            $this->columnSql('last_backup_at'),
            $this->columnSql('last_error'),
            $this->columnSql('manager_name'),
            $this->columnSql('notes'),
            $this->columnSql('deleted_at'),
            $this->columnSql('locked_at'),
            $this->columnSql('locked_by'),
            $this->columnSql('lock_reason'),
            $this->columnSql('last_status_changed_at'),
            $this->columnSql('last_status_changed_by'),
            'v.created_at',
            'v.updated_at',
        ];
        return 'SELECT ' . implode(', ', $columns) . ' FROM villages v';
    }

    private function columnSql(string $column): string
    {
        return $this->hasColumn($column) ? 'v.' . $column : 'NULL AS ' . $column;
    }

    private function normalize(array $row): array
    {
        $storedStatus = (string) ($row['status'] ?? 'ACTIVE');
        $deletedAt = $row['deleted_at'] ?? null;
        $lockedAt = $row['locked_at'] ?? null;
        $effectiveStatus = $deletedAt ? 'DELETED' : ($lockedAt ? 'LOCKED' : $storedStatus);

        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'unitName' => (string) ($row['unit_name'] ?? ''),
            'communeName' => (string) ($row['commune_name'] ?? ''),
            'domain' => (string) ($row['domain'] ?? ''),
            'subdomain' => (string) ($row['subdomain'] ?? ''),
            'status' => $effectiveStatus,
            'storedStatus' => $storedStatus,
            'runtimeAllowed' => !$deletedAt && !$lockedAt && in_array($storedStatus, ['READY', 'ACTIVE'], true),
            'databaseHost' => (string) ($row['database_host'] ?? ''),
            'databaseName' => (string) ($row['database_name'] ?? ''),
            'databaseCharset' => (string) ($row['database_charset'] ?: 'utf8mb4'),
            'appVersion' => (string) ($row['app_version'] ?? ''),
            'buildVersion' => (string) ($row['build_version'] ?? ''),
            'schemaVersion' => (string) ($row['schema_version'] ?? ''),
            'websiteStatus' => (string) ($row['website_status'] ?: ($effectiveStatus === 'LOCKED' ? 'LOCKED' : 'UNKNOWN')),
            'databaseStatus' => (string) ($row['database_status'] ?: ($row['connection_status'] ?: ($effectiveStatus === 'LOCKED' ? 'LOCKED' : 'UNKNOWN'))),
            'connectionStatus' => (string) ($row['connection_status'] ?: ($effectiveStatus === 'LOCKED' ? 'LOCKED' : 'UNKNOWN')),
            'sslStatus' => (string) ($row['ssl_status'] ?: 'UNKNOWN'),
            'storageUsageBytes' => $row['storage_usage_bytes'] !== null ? (int) $row['storage_usage_bytes'] : null,
            'storageQuotaBytes' => $row['storage_quota_bytes'] !== null ? (int) $row['storage_quota_bytes'] : null,
            'lastCheckedAt' => $row['last_checked_at'] ?? null,
            'lastWebsiteCheckedAt' => $row['last_website_checked_at'] ?? null,
            'lastDatabaseCheckedAt' => $row['last_database_checked_at'] ?? null,
            'lastBackupAt' => $row['last_backup_at'] ?? null,
            'lastError' => (string) ($row['last_error'] ?? ''),
            'managerName' => (string) ($row['manager_name'] ?? ''),
            'notes' => (string) ($row['notes'] ?? ''),
            'deletedAt' => $deletedAt,
            'lockedAt' => $lockedAt,
            'lockedBy' => $row['locked_by'] !== null ? (int) $row['locked_by'] : null,
            'lockReason' => (string) ($row['lock_reason'] ?? ''),
            'lastStatusChangedAt' => $row['last_status_changed_at'] ?? null,
            'lastStatusChangedBy' => $row['last_status_changed_by'] !== null ? (int) $row['last_status_changed_by'] : null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private function optionalColumnMap(): array
    {
        return [
            'database_host' => 'database_host',
            'database_name' => 'database_name',
            'database_charset' => 'database_charset',
            'app_version' => 'app_version',
            'build_version' => 'build_version',
            'schema_version' => 'schema_version',
            'connection_status' => 'connection_status',
            'website_status' => 'website_status',
            'database_status' => 'database_status',
            'ssl_status' => 'ssl_status',
            'storage_usage_bytes' => 'storage_usage_bytes',
            'storage_quota_bytes' => 'storage_quota_bytes',
            'last_backup_at' => 'last_backup_at',
            'manager_name' => 'manager_name',
            'notes' => 'notes',
        ];
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

    private function truthy(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function hasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }
        $stmt = $this->db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "villages" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        $row = $stmt->fetch();
        return $this->columnCache[$column] = ((int) ($row['total'] ?? 0) > 0);
    }

    private function db(): PDO
    {
        return $this->db ??= Database::pdo();
    }
}
