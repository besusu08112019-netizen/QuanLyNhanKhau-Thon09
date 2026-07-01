<?php

$sources = [];
$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

$loadEnvFile = static function (string $path) use (&$sources): void {
    if (!is_file($path) || !is_readable($path)) return;
    $sources[] = $path;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (getenv($key) === false || getenv($key) === '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
};

$loadEnvFile($basePath . '/.env');
$loadEnvFile(dirname($basePath) . '/.env');

$env = static function (array|string $keys, mixed $default = null): mixed {
    foreach ((array) $keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') return $value;
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    }
    return $default;
};

$config = [
    'host' => $env(['DB_HOST', 'MYSQL_HOST', 'DATABASE_HOST'], 'localhost'),
    'port' => (int) $env(['DB_PORT', 'MYSQL_PORT', 'DATABASE_PORT'], 3306),
    'database' => $env(['DB_DATABASE', 'DB_NAME', 'MYSQL_DATABASE', 'MYSQL_DB', 'DATABASE_NAME']),
    'username' => $env(['DB_USERNAME', 'DB_USER', 'MYSQL_USER', 'DATABASE_USER']),
    'password' => (string) $env(['DB_PASSWORD', 'DB_PASS', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD'], ''),
    'charset' => $env(['DB_CHARSET', 'MYSQL_CHARSET'], 'utf8mb4'),
    'config_sources' => $sources,
];

$localConfigPath = __DIR__ . '/database.local.php';
if (is_file($localConfigPath) && is_readable($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_replace($config, array_filter($localConfig, static fn($value) => $value !== null && $value !== ''));
        $config['config_sources'][] = $localConfigPath;
    }
}

return $config;
