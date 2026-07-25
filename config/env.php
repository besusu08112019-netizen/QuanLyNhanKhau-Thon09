<?php

if (!function_exists('env_load')) {
    function env_load(?string $basePath = null): array
    {
        static $loaded = [];

        $basePath ??= defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        if (isset($loaded[$basePath])) {
            return $loaded[$basePath];
        }

        $sources = [];
        foreach ([$basePath . '/.env', dirname($basePath) . '/.env'] as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $sources[] = $path;
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if ($key === '') {
                    continue;
                }

                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (getenv($key) === false || getenv($key) === '') {
                    putenv($key . '=' . $value);
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }

        return $loaded[$basePath] = $sources;
    }
}

if (!function_exists('env')) {
    function env(array|string $keys, mixed $default = null): mixed
    {
        env_load();
        foreach ((array) $keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                return $value;
            }
            if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
                return $_ENV[$key];
            }
            if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
                return $_SERVER[$key];
            }
        }

        return $default;
    }
}
