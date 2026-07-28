<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AdministrativeUnitRepository
{
    private ?PDO $db = null;
    private array $columnCache = [];

    public function paginate(array $filters = []): array
    {
        [$where, $params] = $this->filters($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(100, max(5, (int) ($filters['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $total = (int) $this->fetchOne('SELECT COUNT(*) AS total FROM villages v WHERE ' . implode(' AND ', $where), $params)['total'];
        $items = $this->fetchAll(
            $this->selectSql() . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY v.id ORDER BY v.status DESC, v.code ASC LIMIT ' . $pageSize . ' OFFSET ' . $offset,
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
        $row = $this->fetchOne($this->selectSql() . ' WHERE v.id = :id GROUP BY v.id LIMIT 1', ['id' => $id]);
        return $row ? $this->normalize($row) : null;
    }

    public function create(array $data): array
    {
        $columns = ['code', 'name', 'unit_name', 'commune_name', 'domain', 'subdomain', 'logo_url', 'status'];
        $params = [
            'code' => $data['code'],
            'name' => $data['name'],
            'unit_name' => $data['unit_name'] ?? null,
            'commune_name' => $data['commune_name'] ?? null,
            'domain' => $data['domain'] ?? null,
            'subdomain' => $data['subdomain'] ?? null,
            'logo_url' => $data['logo'] ?? null,
            'status' => $data['status'] ?? 'ACTIVE',
        ];
        foreach ($this->optionalColumnMap() as $input => $column) {
            if ($this->hasColumn($column)) {
                $columns[] = $column;
                $params[$column] = $data[$input] ?? null;
            }
        }

        $stmt = $this->db()->prepare('INSERT INTO villages (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')');
        $stmt->execute($params);

        return $this->find((int) $this->db()->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): array
    {
        $sets = [];
        $params = ['id' => $id];
        $map = [
            'name' => 'name',
            'unit_name' => 'unit_name',
            'commune_name' => 'commune_name',
            'domain' => 'domain',
            'subdomain' => 'subdomain',
            'logo' => 'logo_url',
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

        if ($sets) {
            $stmt = $this->db()->prepare('UPDATE villages SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $stmt->execute($params);
        }

        return $this->find($id) ?? [];
    }

    public function setStatus(int $id, string $status): array
    {
        $sets = ['status = :status'];
        if ($this->hasColumn('connection_status')) {
            $sets[] = 'connection_status = :connection_status';
        }
        $stmt = $this->db()->prepare('UPDATE villages SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $params = ['id' => $id, 'status' => $status];
        if ($this->hasColumn('connection_status')) {
            $params['connection_status'] = $status === 'ACTIVE' ? 'UNKNOWN' : 'LOCKED';
        }
        $stmt->execute($params);
        return $this->find($id) ?? [];
    }

    public function updateHealth(int $id, string $connectionStatus, ?string $error = null): array
    {
        $sets = [];
        $params = ['id' => $id];
        if ($this->hasColumn('connection_status')) {
            $sets[] = 'connection_status = :connection_status';
            $params['connection_status'] = $connectionStatus;
        }
        if ($this->hasColumn('last_checked_at')) {
            $sets[] = 'last_checked_at = NOW()';
        }
        if ($this->hasColumn('last_error')) {
            $sets[] = 'last_error = :last_error';
            $params['last_error'] = $error;
        }
        if ($sets) {
            $stmt = $this->db()->prepare('UPDATE villages SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $stmt->execute($params);
        }
        return $this->find($id) ?? [];
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

        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $where[] = 'v.status = :status';
            $params['status'] = $status;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $parts = ['v.code LIKE :search_code', 'v.name LIKE :search_name', 'v.domain LIKE :search_domain', 'v.subdomain LIKE :search_subdomain'];
            if ($this->hasColumn('database_name')) {
                $parts[] = 'v.database_name LIKE :search_database';
                $params['search_database'] = '%' . $search . '%';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
            $like = '%' . $search . '%';
            $params['search_code'] = $like;
            $params['search_name'] = $like;
            $params['search_domain'] = $like;
            $params['search_subdomain'] = $like;
        }

        return [$where, $params];
    }

    private function selectSql(): string
    {
        $databaseName = $this->hasColumn('database_name') ? 'v.database_name' : 'NULL AS database_name';
        $databaseHost = $this->hasColumn('database_host') ? 'v.database_host' : 'NULL AS database_host';
        $version = $this->hasColumn('version') ? 'v.version AS registry_version' : 'NULL AS registry_version';
        $connectionStatus = $this->hasColumn('connection_status') ? 'v.connection_status' : 'NULL AS connection_status';
        $lastCheckedAt = $this->hasColumn('last_checked_at') ? 'v.last_checked_at' : 'NULL AS last_checked_at';
        $lastError = $this->hasColumn('last_error') ? 'v.last_error' : 'NULL AS last_error';
        return "
            SELECT
                v.id,
                v.code,
                v.name,
                v.unit_name,
                v.commune_name,
                v.domain,
                v.subdomain,
                v.logo_url,
                v.status,
                $databaseName,
                $databaseHost,
                $version,
                $connectionStatus,
                $lastCheckedAt,
                $lastError,
                v.created_at,
                v.updated_at,
                COUNT(DISTINCT h.id) AS household_count,
                COUNT(DISTINCT c.id) AS citizen_count
            FROM villages v
            LEFT JOIN households h ON h.village_id = v.id AND h.status <> 'DELETED'
            LEFT JOIN citizens c ON c.village_id = v.id AND c.status <> 'DELETED' AND c.life_status = 'ALIVE'
        ";
    }

    private function normalize(array $row): array
    {
        $status = (string) ($row['status'] ?? 'ACTIVE');
        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'type' => 'VILLAGE',
            'unitName' => (string) ($row['unit_name'] ?? ''),
            'communeName' => (string) ($row['commune_name'] ?? ''),
            'domain' => (string) ($row['domain'] ?: $row['subdomain'] ?: ''),
            'subdomain' => (string) ($row['subdomain'] ?? ''),
            'databaseName' => (string) ($row['database_name'] ?? ''),
            'databaseHost' => (string) ($row['database_host'] ?? ''),
            'logo' => (string) ($row['logo_url'] ?? ''),
            'status' => $status,
            'manager' => 'Chua gan',
            'version' => (string) ($row['registry_version'] ?: (defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1')),
            'healthStatus' => (string) ($row['connection_status'] ?: ($status === 'ACTIVE' ? 'UNKNOWN' : 'LOCKED')),
            'lastCheckedAt' => $row['last_checked_at'] ?? null,
            'lastError' => (string) ($row['last_error'] ?? ''),
            'households' => (int) ($row['household_count'] ?? 0),
            'citizens' => (int) ($row['citizen_count'] ?? 0),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
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

    private function db(): PDO
    {
        return $this->db ??= Database::pdo();
    }

    private function optionalColumnMap(): array
    {
        return [
            'database_name' => 'database_name',
            'database_host' => 'database_host',
            'version' => 'version',
            'connection_status' => 'connection_status',
        ];
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
}
