<?php

require_once __DIR__ . '/env.php';

env_load(dirname(__DIR__));

$uploadPath = (string) env('UPLOAD_PATH', dirname(__DIR__) . '/uploads');
$storagePath = (string) env('STORAGE_PATH', dirname(__DIR__) . '/storage');
$cachePath = (string) env('CACHE_PATH', $storagePath . '/cache');
$logsPath = (string) env('LOGS_PATH', $storagePath . '/logs');

$resolveAppKey = static function () use ($uploadPath): string {
    $envKey = trim((string) env('APP_KEY', ''));
    if ($envKey !== '' && $envKey !== 'change-this-to-a-long-random-production-secret') {
        return $envKey;
    }

    $keyFile = rtrim($uploadPath, '/\\') . '/.app_key';
    if (is_file($keyFile)) {
        $key = trim((string) file_get_contents($keyFile));
        if ($key !== '') {
            return $key;
        }
    }

    if (!is_dir($uploadPath)) {
        @mkdir($uploadPath, 0755, true);
    }

    $key = bin2hex(random_bytes(32));
    if (@file_put_contents($keyFile, $key, LOCK_EX) === false) {
        throw new RuntimeException('APP_KEY is not configured and the runtime key file cannot be written.');
    }
    return $key;
};

return [
    'name' => env('APP_NAME', 'He thong Quan ly Hanh chinh'),
    'app_key' => $resolveAppKey(),
    'url' => env('APP_URL', ''),
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'session_ttl_seconds' => (int) env('SESSION_TTL_SECONDS', 21600),
    'upload_path' => $uploadPath,
    'storage_path' => $storagePath,
    'cache_path' => $cachePath,
    'logs_path' => $logsPath,
    'mail' => [
        'mailer' => env('MAIL_MAILER', 'smtp'),
        'host' => env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => env('MAIL_FROM_ADDRESS', ''),
        'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'He thong Quan ly Hanh chinh')),
    ],
];
