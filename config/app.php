<?php

$uploadPath = getenv('UPLOAD_PATH') ?: dirname(__DIR__) . '/uploads';

$resolveAppKey = static function () use ($uploadPath): string {
    $envKey = trim((string) getenv('APP_KEY'));
    if ($envKey !== '' && $envKey !== 'change-me-thon09-production-key') {
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
    'name' => getenv('APP_NAME') ?: json_decode('"Qu\u1ea3n l\u00fd Nh\u00e2n kh\u1ea9u Th\u00f4n 09 x\u00e3 H\u1ed3ng Phong"', true),
    'app_key' => $resolveAppKey(),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Ho_Chi_Minh',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'session_ttl_seconds' => (int) (getenv('SESSION_TTL_SECONDS') ?: 21600),
    'upload_path' => $uploadPath,
];
