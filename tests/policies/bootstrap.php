<?php

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

final class PolicyTestRegistry
{
    /** @var array<int, array{name: string, callback: callable}> */
    private static array $tests = [];

    public static function add(string $name, callable $callback): void
    {
        self::$tests[] = ['name' => $name, 'callback' => $callback];
    }

    /** @return array<int, array{name: string, callback: callable}> */
    public static function all(): array
    {
        return self::$tests;
    }
}

function policy_test(string $name, callable $callback): void
{
    PolicyTestRegistry::add($name, $callback);
}

function policy_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected: ' . var_export($expected, true) . ', actual: ' . var_export($actual, true));
    }
}

function policy_assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function policy_assert_false(bool $condition, string $message): void
{
    if ($condition) {
        throw new RuntimeException($message);
    }
}

function policy_assert_array_has_keys(array $keys, array $actual, string $message): void
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $actual)) {
            throw new RuntimeException($message . ' Missing key: ' . $key);
        }
    }
}
