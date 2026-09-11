<?php

namespace App\Core;

final class CitizenCodeFormatter
{
    public static function display(mixed $value): mixed
    {
        if (!is_string($value) && !is_numeric($value)) {
            return $value;
        }

        $text = trim((string) $value);
        if (preg_match('/^[A-Za-z0-9_-]+-(NK[0-9]+)$/', $text, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    public static function normalizePayload(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = self::normalizePayload($item);
            }
            return $normalized;
        }

        return self::display($value);
    }
}
