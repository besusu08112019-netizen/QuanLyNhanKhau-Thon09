<?php

declare(strict_types=1);

return [
    'enabled' => false,
    'provider' => 'none',
    'model' => null,
    'max_context_messages' => 20,
    'log_channel' => 'ai',
    'log_sensitive_keys' => [
        'password',
        'token',
        'api_key',
        'secret',
        'authorization',
        'cccd',
        'so_dinh_danh',
    ],
    'features' => [
        'speech' => false,
        'ocr' => false,
        'tools' => false,
        'external_api' => false,
    ],
];

