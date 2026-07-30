<?php

if (!function_exists('env_load')) {
    function env_load(?string $basePath = null): array
    {
        static $loaded = [];

        $basePath ??= defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $hosts = env_host_candidates($basePath);
        $host = $hosts[0] ?? '';
        $cacheKey = $basePath . '|' . implode(',', $hosts);
        if (isset($loaded[$cacheKey])) {
            return $loaded[$cacheKey];
        }

        $sources = [];

        foreach (env_candidate_files($basePath, $hosts) as $path) {
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
                $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
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

if (!class_exists(\App\Core\TenantResolver::class)) {
    $tenantResolverFile = dirname(__DIR__) . '/app/Core/TenantResolver.php';
    if (is_file($tenantResolverFile)) {
        require_once $tenantResolverFile;
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

if (!function_exists('env_host_candidates')) {
    function env_host_candidates(string $basePath): array
    {
        $values = [
            $_SERVER['HTTP_HOST'] ?? '',
            $_SERVER['SERVER_NAME'] ?? '',
            getenv('APP_HOST') ?: '',
            $_ENV['APP_HOST'] ?? '',
            $_SERVER['APP_HOST'] ?? '',
            basename((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')),
            basename($basePath),
        ];

        $hosts = [];
        foreach ($values as $value) {
            $host = strtolower(trim((string) $value));
            $host = preg_replace('/:\d+$/', '', $host) ?? $host;
            $host = preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
            if ($host !== '' && !in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
            if ($host !== '' && class_exists(\App\Core\TenantResolver::class)) {
                foreach (\App\Core\TenantResolver::candidateKeys($host) as $candidate) {
                    if ($candidate !== '' && !in_array($candidate, $hosts, true)) {
                        $hosts[] = $candidate;
                    }
                }
            }
        }

        return $hosts;
    }
}

if (!function_exists('env_candidate_files')) {
    function env_candidate_files(string $basePath, array|string $hosts = ''): array
    {
        $files = [];
        $files[] = dirname($basePath) . '/.env';
        $files[] = $basePath . '/.env';
        foreach ((array) $hosts as $host) {
            $host = strtolower(trim((string) $host));
            if ($host === '') {
                continue;
            }
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
