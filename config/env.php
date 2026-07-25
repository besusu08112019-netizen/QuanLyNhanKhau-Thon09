<?php

if (!function_exists('env_load')) {
    function env_load(?string $basePath = null): array
    {
        static $loaded = [];

        $basePath ??= defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $host = env_current_host();
        $cacheKey = $basePath . '|' . $host;
        if (isset($loaded[$cacheKey])) {
            return $loaded[$cacheKey];
        }

        $sources = [];
        foreach (env_candidate_files($basePath, $host) as $path) {
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
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        return $loaded[$cacheKey] = $sources;
    }
}

if (!function_exists('env_current_host')) {
    function env_current_host(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? getenv('APP_HOST') ?: '')));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        return preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
    }
}

if (!function_exists('env_candidate_files')) {
    function env_candidate_files(string $basePath, string $host = ''): array
    {
        $files = [];
        $files[] = dirname($basePath) . '/.env';
        $files[] = $basePath . '/.env';
        if ($host !== '') {
            $files[] = dirname($basePath) . '/.env.' . $host;
            $files[] = $basePath . '/.env.' . $host;
        }
        return array_values(array_unique($files));
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
