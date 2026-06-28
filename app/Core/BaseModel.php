<?php

namespace App\Core;

use PDO;

abstract class BaseModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->db->lastInsertId();
    }

    protected function page(int $page, int $pageSize): array
    {
        $page = max($page, 1);
        $pageSize = min(max($pageSize, 5), 100);
        return [$page, $pageSize, ($page - 1) * $pageSize];
    }
}
