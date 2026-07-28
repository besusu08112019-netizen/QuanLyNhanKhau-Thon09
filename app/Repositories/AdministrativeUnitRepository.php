<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AdministrativeUnitRepository
{
    private ?PDO $db = null;

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
        $stmt = $this->db()->prepare(
            'INSERT INTO villages (code, name, unit_name, commune_name, domain, subdomain, logo_url, status)
             VALUES (:code, :name, :unit_name, :commune_name, :domain, :subdomain, :logo_url, :status)'
        );
        $stmt->execute([
            'code' => $data['code'],
            'name' => $data['name'],
            'unit_name' => $data['unit_name'] ?? null,
            'commune_name' => $data['commune_name'] ?? null,
            'domain' => $data['domain'] ?? null,
            'subdomain' => $data['subdomain'] ?? null,
            'logo_url' => $data['logo'] ?? null,
            'status' => $data['status'] ?? 'ACTIVE',
        ]);

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
        $stmt = $this->db()->prepare('UPDATE villages SET status = :status WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
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
            $where[] = '(v.code LIKE :search_code OR v.name LIKE :search_name OR v.domain LIKE :search_domain OR v.subdomain LIKE :search_subdomain)';
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
            'logo' => (string) ($row['logo_url'] ?? ''),
            'status' => $status,
            'manager' => 'Chua gan',
            'version' => defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1',
            'healthStatus' => $status === 'ACTIVE' ? 'OK' : 'LOCKED',
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
}
