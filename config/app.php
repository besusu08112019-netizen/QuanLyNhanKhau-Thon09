<?php

return [
    'name' => getenv('APP_NAME') ?: 'Quản Lý Nhân Khẩu Thôn 09 xã Hồng Phong',
    'app_key' => getenv('APP_KEY') ?: 'change-me-thon09-production-key',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Ho_Chi_Minh',
    'session_ttl_seconds' => (int) (getenv('SESSION_TTL_SECONDS') ?: 21600),
    'upload_path' => getenv('UPLOAD_PATH') ?: dirname(__DIR__) . '/uploads',
];
