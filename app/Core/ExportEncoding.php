<?php

namespace App\Core;

final class ExportEncoding
{
    public static function text(mixed $value): string
    {
        if ($value === null) return '';
        if (is_bool($value)) return $value ? 'CÃ³' : 'KhÃ´ng';
        if (is_scalar($value)) return Encoding::repairMojibake((string) CitizenCodeFormatter::display($value));
        return Encoding::repairMojibake(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    public static function html(mixed $value): string
    {
        return htmlspecialchars(self::text($value), ENT_QUOTES, 'UTF-8');
    }

    public static function cleanArray(mixed $value): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[self::text($key)] = self::cleanArray($item);
            }
            return $clean;
        }
        return self::text($value);
    }
}
