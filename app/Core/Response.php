<?php

namespace App\Core;

final class Response
{
    public static function ok(mixed $data = null, int $status = 200): void
    {
        self::json(['ok' => true, 'success' => true, 'data' => self::normalizeListPayload($data)], $status);
    }

    public static function error(string $message, int $status = 400, array $details = []): void
    {
        self::json(['ok' => false, 'success' => false, 'message' => $message, 'errors' => $details, 'error' => ['message' => $message, 'details' => $details]], $status);
    }

    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
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

    private static function normalizeListPayload(mixed $value): mixed
    {
        if (!is_array($value) || array_is_list($value)) {
            return $value;
        }

        $hasItems = array_key_exists('items', $value) && is_array($value['items']);
        $hasData = array_key_exists('data', $value) && is_array($value['data']) && array_is_list($value['data']);
        $hasPagination = array_key_exists('page', $value) || array_key_exists('pageSize', $value) || array_key_exists('total', $value) || array_key_exists('totalItems', $value) || array_key_exists('totalPages', $value);
        if (!$hasItems && !$hasData && !$hasPagination) {
            return $value;
        }

        $items = $hasData ? $value['data'] : ($hasItems ? $value['items'] : []);
        $page = max(1, (int) ($value['page'] ?? 1));
        $pageSize = max(1, (int) ($value['pageSize'] ?? count($items) ?: 20));
        $totalItems = max(0, (int) ($value['totalItems'] ?? $value['total'] ?? count($items)));
        $totalPages = max(1, (int) ($value['totalPages'] ?? (int) ceil($totalItems / $pageSize)));

        $value['data'] = $items;
        $value['items'] = $items;
        $value['page'] = $page;
        $value['pageSize'] = $pageSize;
        $value['totalItems'] = $totalItems;
        $value['total'] = $totalItems;
        $value['totalPages'] = $totalPages;
        $value['hasNext'] = $page < $totalPages;
        $value['hasPrevious'] = $page > 1;

        return $value;
    }

    private static function looksLikeMojibake(string $value): bool
    {
        return preg_match('/(?:Ã|Â|Æ|Ä|áº|á»)/u', $value) === 1;
    }
}
