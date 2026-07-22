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

        $config = self::config();
        self::validateConfig($config);

        $lastException = null;
        foreach (self::connectionAttempts($config) as $attempt) {
            try {
                self::$pdo = self::connect($attempt['dsn'], $config);
                return self::$pdo;
            } catch (PDOException $e) {
                $lastException = $e;
                self::logConfig('DATABASE_CONNECTION_ATTEMPT_FAILED', $config, self::exceptionMessage($e), $attempt['label']);
            }
        }

        self::logConfig('DATABASE_CONNECTION_ERROR', $config, $lastException ? self::exceptionMessage($lastException) : 'Unknown PDO error');
        throw new DatabaseException('Không kết nối được cơ sở dữ liệu. Vui lòng kiểm tra cấu hình DB trên Hosting.', 0, $lastException);
    }

    public static function diagnostics(): array
    {
        $config = self::config();
        $safeConfig = self::safeConfig($config);
        $report = [
            'ok' => false,
            'time' => date('c'),
            'php_version' => PHP_VERSION,
            'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
            'pdo_drivers' => PDO::getAvailableDrivers(),
            'config' => $safeConfig,
            'attempts' => [],
        ];

        try {
            self::validateConfig($config);
        } catch (\Throwable $e) {
            $report['error'] = [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'code' => (string) $e->getCode(),
            ];
            return $report;
        }

        foreach (self::connectionAttempts($config) as $attempt) {
            $attemptReport = [
                'label' => $attempt['label'],
                'dsn_public' => self::publicDsn($attempt['dsn']),
                'ok' => false,
            ];

            try {
                $pdo = self::connect($attempt['dsn'], $config);
                $attemptReport['ok'] = true;
                $attemptReport['server_version'] = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                $attemptReport['client_version'] = (string) $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION);
                $attemptReport['database'] = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
                $report['attempts'][] = $attemptReport;
                $report['ok'] = true;
                return $report;
            } catch (PDOException $e) {
                $attemptReport['error'] = [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => (string) $e->getCode(),
                    'sqlstate' => $e->errorInfo[0] ?? $e->getCode(),
                    'driver_code' => $e->errorInfo[1] ?? null,
                    'driver_message' => $e->errorInfo[2] ?? null,
                ];
                $report['attempts'][] = $attemptReport;
                self::logConfig('DATABASE_DIAGNOSTIC_ATTEMPT_FAILED', $config, self::exceptionMessage($e), $attempt['label']);
            }
        }

        $report['error'] = ['message' => 'All database connection attempts failed'];
        return $report;
    }

    private static function config(): array
    {
        $localConfig = BASE_PATH . '/config/database.php';
        $exampleConfig = BASE_PATH . '/config/database.example.php';

        if (is_file($localConfig)) {
            return require $localConfig;
        }

        if (is_file($exampleConfig)) {
            return require $exampleConfig;
        }

        throw new DatabaseException('Không tìm thấy cấu hình cơ sở dữ liệu. Vui lòng tạo file .env hoặc config/database.php từ file mẫu.');
    }

    private static function connect(string $dsn, array $config): PDO
    {
        return new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
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

    private static function safeConfig(array $config): array
    {
        return [
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'socket' => $config['socket'] ?? null,
            'database' => $config['database'] ?? null,
            'username' => $config['username'] ?? null,
            'charset' => $config['charset'] ?? null,
            'config_sources' => $config['config_sources'] ?? [],
            'password_present' => isset($config['password']) && (string) $config['password'] !== '',
            'password_length' => isset($config['password']) ? strlen((string) $config['password']) : 0,
        ];
    }

    private static function publicDsn(string $dsn): string
    {
        return preg_replace('/(password|pwd)=([^;]+)/i', '$1=***', $dsn) ?? $dsn;
    }

    private static function exceptionMessage(PDOException $e): string
    {
        return sprintf('SQLSTATE=%s; code=%s; driver_code=%s', $e->errorInfo[0] ?? $e->getCode(), (string) $e->getCode(), (string) ($e->errorInfo[1] ?? ''));
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
