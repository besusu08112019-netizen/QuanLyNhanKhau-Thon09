<?php

namespace App\Models;

use App\Core\BaseModel;
use PDO;

final class Backup extends BaseModel
{
    public function createSqlDump(int $userId): array
    {
        $tables = $this->fetchAll('SHOW TABLES');
        $sql = "-- Quan Ly Nhan Khau Thon 09 backup\n-- Created at: " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($tables as $row) {
            $table = array_values($row)[0];
            $create = $this->fetchOne('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
            $sql .= "DROP TABLE IF EXISTS `$table`;\n" . ($create['Create Table'] ?? array_values($create)[1]) . ";\n\n";
            $records = $this->fetchAll('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
            foreach ($records as $record) {
                $columns = array_map(fn($col) => '`' . str_replace('`', '``', $col) . '`', array_keys($record));
                $values = array_map(fn($value) => $value === null ? 'NULL' : $this->db->quote((string) $value, PDO::PARAM_STR), array_values($record));
                $sql .= 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $fileName = 'backup_thon09_' . date('Ymd_His') . '.sql';
        $checksum = hash('sha256', $sql);
        $this->insert('INSERT INTO backups (file_name, file_path, file_size, checksum, status, created_by) VALUES (:file_name,:file_path,:file_size,:checksum,"SUCCESS",:user)', ['file_name' => $fileName, 'file_path' => 'download://' . $fileName, 'file_size' => strlen($sql), 'checksum' => $checksum, 'user' => $userId]);
        return ['fileName' => $fileName, 'content' => $sql, 'size' => strlen($sql), 'checksum' => $checksum];
    }

    public function page(array $filters = []): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $total = (int) $this->fetchOne('SELECT COUNT(*) AS total FROM backups')['total'];
        $items = $this->fetchAll("SELECT b.*, u.email AS created_by_email FROM backups b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.created_at DESC LIMIT $pageSize OFFSET $offset");
        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'totalPages' => max(1, (int) ceil($total / $pageSize))];
    }
}
