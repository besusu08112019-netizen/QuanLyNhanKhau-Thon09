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

        $attempts = self::connectionAttempts($config);
        $lastException = null;

        foreach ($attempts as $attempt) {
            try {
                self::$pdo = new PDO($attempt['dsn'], (string) $config['username'], (string) $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                return self::$pdo;
            } catch (PDOException $e) {
                $lastException = $e;
                self::logConfig('DATABASE_CONNECTION_ATTEMPT_FAILED', $config, $e->getMessage(), $attempt['label']);
            }
        }

        self::logConfig('DATABASE_CONNECTION_ERROR', $config, $lastException?->getMessage() ?? 'Unknown PDO error');
        throw new DatabaseException('Không kết nối được cơ sở dữ liệu. Vui lòng kiểm tra cấu hình DB trên Hosting.', 0, $lastException);
    }

    private static function connectionAttempts(array $config): array
    {
        $charset = $config['charset'] ?? 'utf8mb4';
        $database = $config['database'];
        $attempts = [];

        $socket = trim((string) ($config['socket'] ?? ''));
        if ($socket !== '') {
            $attempts[] = [
                'label' => 'socket:' . $socket,
                'dsn' => sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $database, $charset),
            ];
        }

        $host = (string) ($config['host'] ?? 'localhost');
        $port = (int) ($config['port'] ?? 3306);
        $attempts[] = [
            'label' => 'host:' . $host . ':' . $port,
            'dsn' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset),
        ];

        $fallbackHost = null;
        if ($host === 'localhost') {
            $fallbackHost = '127.0.0.1';
        } elseif ($host === '127.0.0.1') {
            $fallbackHost = 'localhost';
        }

        if ($fallbackHost !== null) {
            $attempts[] = [
                'label' => 'host:' . $fallbackHost . ':' . $port,
                'dsn' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $fallbackHost, $port, $database, $charset),
            ];
        }

        return $attempts;
    }

    private static function validateConfig(array $config): void
    {
        $missing = [];
        foreach (['host', 'database', 'username'] as $key) {
            if (!isset($config[$key]) || trim((string) $config[$key]) === '') $missing[] = $key;
        }
        if ($missing) {
            self::logConfig('DATABASE_CONFIG_ERROR', $config, 'Missing keys: ' . implode(', ', $missing));
            throw new DatabaseException('Thiếu cấu hình cơ sở dữ liệu: ' . implode(', ', $missing));
        }
        if (($config['username'] ?? '') === 'root' && (string) ($config['password'] ?? '') === '') {
            self::logConfig('DATABASE_CONFIG_ERROR', $config, 'Refusing unsafe root database fallback');
            throw new DatabaseException('Cấu hình DB không hợp lệ: không được dùng root không mật khẩu trên Hosting.');
        }
    }

    private static function logConfig(string $type, array $config, string $message, ?string $attempt = null): void
    {
        error_log('[' . $type . '] ' . json_encode([
            'time' => date('c'),
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'socket' => $config['socket'] ?? null,
            'database' => $config['database'] ?? null,
            'username' => $config['username'] ?? null,
            'config_sources' => $config['config_sources'] ?? [],
            'attempt' => $attempt,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
