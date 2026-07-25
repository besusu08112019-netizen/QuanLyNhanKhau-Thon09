<?php

$basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
require_once __DIR__ . '/env.php';
$sources = env_load($basePath);

$config = [
    'host' => env(['DB_HOST', 'MYSQL_HOST', 'DATABASE_HOST'], 'localhost'),
    'port' => (int) env(['DB_PORT', 'MYSQL_PORT', 'DATABASE_PORT'], 3306),
    'socket' => env(['DB_SOCKET', 'MYSQL_SOCKET', 'DATABASE_SOCKET']),
    'database' => env(['DB_DATABASE', 'DB_NAME', 'MYSQL_DATABASE', 'MYSQL_DB', 'DATABASE_NAME']),
    'username' => env(['DB_USERNAME', 'DB_USER', 'MYSQL_USER', 'DATABASE_USER']),
    'password' => (string) env(['DB_PASSWORD', 'DB_PASS', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD'], ''),
    'charset' => env(['DB_CHARSET', 'MYSQL_CHARSET'], 'utf8mb4'),
    'config_sources' => $sources,
];

return $config;
