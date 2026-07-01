<?php

$envPath = defined('BASE_PATH') ? BASE_PATH . '/.env' : dirname(__DIR__) . '/.env';
if (is_file($envPath) && is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$env = static function (array|string $keys, mixed $default = null): mixed {
    foreach ((array) $keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') return $value;
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    }
    return $default;
};

return [
    'host' => $env(['DB_HOST', 'MYSQL_HOST', 'DATABASE_HOST'], 'localhost'),
    'port' => (int) $env(['DB_PORT', 'MYSQL_PORT', 'DATABASE_PORT'], 3306),
    'database' => $env(['DB_DATABASE', 'DB_NAME', 'MYSQL_DATABASE', 'MYSQL_DB', 'DATABASE_NAME']),
    'username' => $env(['DB_USERNAME', 'DB_USER', 'MYSQL_USER', 'DATABASE_USER']),
    'password' => (string) $env(['DB_PASSWORD', 'DB_PASS', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD'], ''),
    'charset' => $env(['DB_CHARSET', 'MYSQL_CHARSET'], 'utf8mb4'),
];
