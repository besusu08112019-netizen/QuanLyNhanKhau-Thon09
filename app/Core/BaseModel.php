<?php

namespace App\Core;

use PDO;

abstract class BaseModel
{
    protected PDO $db;
    private static ?array $lastQuery = null;
    private static array $columnCache = [];

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        self::rememberQuery($sql, $params);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(self::paramsForSql($sql, $params));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        self::rememberQuery($sql, $params);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(self::paramsForSql($sql, $params));
        return $stmt->fetchAll();
    }

    protected function execute(string $sql, array $params = []): int
    {
        self::rememberQuery($sql, $params);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(self::paramsForSql($sql, $params));
        return $stmt->rowCount();
    }

    protected function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->db->lastInsertId();
    }

    protected function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) return self::$columnCache[$key];
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column', ['table' => $table, 'column' => $column]);
        return self::$columnCache[$key] = ((int) ($row['total'] ?? 0) > 0);
    }

    protected function existingColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, fn($column) => $this->columnExists($table, $column)));
    }

    protected function tenantId(): int
    {
        return TenantContext::id();
    }

    protected function tenantColumnExists(string $table): bool
    {
        return $this->columnExists($table, 'village_id');
    }

    protected function ensureTenantColumn(string $table): void
    {
        if ($this->tenantColumnExists($table)) {
            return;
        }

        $this->execute('ALTER TABLE ' . $table . ' ADD COLUMN village_id BIGINT UNSIGNED NULL AFTER id');
        unset(self::$columnCache[$table . '.village_id']);
        $this->execute('UPDATE ' . $table . ' SET village_id = :village_id WHERE village_id IS NULL', $this->withTenant());
        $this->execute('ALTER TABLE ' . $table . ' MODIFY COLUMN village_id BIGINT UNSIGNED NOT NULL');
        unset(self::$columnCache[$table . '.village_id']);
        try {
            $this->execute('ALTER TABLE ' . $table . ' ADD INDEX idx_' . $table . '_village (village_id)');
        } catch (\Throwable) {
        }
    }

    protected function tenantWhere(string $tableAlias = '', string $table = ''): string
    {
        $tableName = $table !== '' ? $table : $tableAlias;
        if ($tableName === '' || !$this->tenantColumnExists($tableName)) {
            return '1=1';
        }

        $prefix = $tableAlias !== '' ? rtrim($tableAlias, '.') . '.' : '';
        return $prefix . 'village_id = :village_id';
    }

    protected function withTenant(array $params = []): array
    {
        $params['village_id'] = $this->tenantId();
        return $params;
    }

    protected function addTenantInsert(string $table, array &$columns, array &$params): void
    {
        if (!$this->tenantColumnExists($table) || in_array('village_id', $columns, true)) {
            return;
        }

        $columns[] = 'village_id';
        $params['village_id'] = $this->tenantId();
    }

    public static function lastQuery(): ?array
    {
        return self::$lastQuery;
    }

    private static function rememberQuery(string $sql, array $params): void
    {
        self::$lastQuery = ['sql' => $sql, 'params' => $params];
    }

    private static function paramsForSql(string $sql, array $params): array
    {
        if ($params === [] || array_is_list($params)) {
            return $params;
        }

        preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        if (empty($matches[1])) {
            return [];
        }

        return array_intersect_key($params, array_flip(array_unique($matches[1])));
    }

    protected function page(int $page, int $pageSize): array
    {
        $page = max($page, 1);
        $pageSize = min(max($pageSize, 5), 100);
        return [$page, $pageSize, ($page - 1) * $pageSize];
    }

    protected function paginated(array $items, int $page, int $pageSize, int $total, array $extra = []): array
    {
        return [
            'items' => $items,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / max(1, $pageSize))),
        ] + $extra;
    }

    protected function listOrder(array $filters, array $sortMap, string $defaultSort, string $defaultDirection = 'ASC', array $tieBreakers = []): string
    {
        $sort = preg_replace('/[^a-z_]/', '', (string) ($filters['sort'] ?? $defaultSort));
        if ($sort === '' || !isset($sortMap[$sort])) {
            $sort = $defaultSort;
        }

        $direction = strtoupper((string) ($filters['direction'] ?? $defaultDirection)) === 'DESC' ? 'DESC' : 'ASC';
        $parts = [$sortMap[$sort] . ' ' . $direction];
        foreach ($tieBreakers as $tieBreaker) {
            $tieBreaker = trim((string) $tieBreaker);
            if ($tieBreaker !== '' && !in_array($tieBreaker, $parts, true)) {
                $parts[] = $tieBreaker;
            }
        }

        return 'ORDER BY ' . implode(', ', $parts);
    }
}
