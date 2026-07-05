<?php

namespace App\Core;

final class Response
{
    public static function ok(mixed $data = null, int $status = 200): void
    {
        self::json(['ok' => true, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $details = []): void
    {
        self::json(['ok' => false, 'error' => ['message' => $message, 'details' => $details]], $status);
    }

    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(self::normalizeUtf8($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function normalizeUtf8(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalizeUtf8($item);
            }
            return $value;
        }

        if (!is_string($value) || $value === '' || !self::looksLikeMojibake($value)) {
            return $value;
        }

        $decoded = @iconv('UTF-8', 'Windows-1252//IGNORE', $value);
        if (!is_string($decoded) || $decoded === '' || preg_match('//u', $decoded) !== 1) {
            return $value;
        }

        return $decoded;
    }

    private static function looksLikeMojibake(string $value): bool
    {
        return preg_match('/(?:Ã|Â|Æ|Ä|áº|á»)/u', $value) === 1;
    }
}
