<?php

namespace App\Core;

use PDO;

abstract class BaseModel
{
    protected PDO $db;
    private static ?array $lastQuery = null;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        self::rememberQuery($sql, $params);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        self::rememberQuery($sql, $params);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function execute(string $sql, array $params = []): int
    {
        self::rememberQuery($sql, $params);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->db->lastInsertId();
    }

    protected function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column', ['table' => $table, 'column' => $column]);
        return $cache[$key] = ((int) ($row['total'] ?? 0) > 0);
    }

    protected function existingColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, fn($column) => $this->columnExists($table, $column)));
    }

    public static function lastQuery(): ?array
    {
        return self::$lastQuery;
    }

    private static function rememberQuery(string $sql, array $params): void
    {
        self::$lastQuery = ['sql' => $sql, 'params' => $params];
    }
    protected function page(int $page, int $pageSize): array
    {
        $page = max($page, 1);
        $pageSize = min(max($pageSize, 5), 100);
        return [$page, $pageSize, ($page - 1) * $pageSize];
    }
}
