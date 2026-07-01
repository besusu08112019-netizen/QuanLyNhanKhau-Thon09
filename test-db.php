<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Database;

Autoloader::register();

try {
    $report = Database::diagnostics();
    http_response_code($report['ok'] ? 200 : 500);
    echo json_encode([
        'ok' => $report['ok'],
        'message' => $report['ok'] ? 'Database Connected Successfully' : 'Database Connection Failed',
        'diagnostics' => $report,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Database diagnostic crashed',
        'exception' => [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => (string) $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
