<?php

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require BASE_PATH . '/config/database.php';
        self::validateConfig($config);

        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'] ?? 3306, $config['database'], $charset);

        try {
            self::$pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return self::$pdo;
        } catch (PDOException $e) {
            error_log('[DATABASE_CONNECTION_ERROR] ' . json_encode([
                'time' => date('c'),
                'host' => $config['host'] ?? null,
                'port' => $config['port'] ?? null,
                'database' => $config['database'] ?? null,
                'username' => $config['username'] ?? null,
                'exception' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            throw new DatabaseException('Không kết nối được cơ sở dữ liệu. Vui lòng kiểm tra cấu hình DB trên Hosting.', 0, $e);
        }
    }

    private static function validateConfig(array $config): void
    {
        $missing = [];
        foreach (['host', 'database', 'username'] as $key) {
            if (!isset($config[$key]) || trim((string) $config[$key]) === '') $missing[] = $key;
        }
        if ($missing) {
            error_log('[DATABASE_CONFIG_ERROR] Missing keys: ' . implode(', ', $missing));
            throw new DatabaseException('Thiếu cấu hình cơ sở dữ liệu: ' . implode(', ', $missing));
        }
        if (($config['username'] ?? '') === 'root' && (string) ($config['password'] ?? '') === '') {
            error_log('[DATABASE_CONFIG_ERROR] Refusing unsafe root database fallback');
            throw new DatabaseException('Cấu hình DB không hợp lệ: không được dùng root không mật khẩu trên Hosting.');
        }
    }
}
