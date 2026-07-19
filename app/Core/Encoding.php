<?php

namespace App\Core;

final class Encoding
{
    private const CP1252_TO_BYTE = [
        '€' => 0x80, '‚' => 0x82, 'ƒ' => 0x83, '„' => 0x84, '…' => 0x85,
        '†' => 0x86, '‡' => 0x87, 'ˆ' => 0x88, '‰' => 0x89, 'Š' => 0x8A,
        '‹' => 0x8B, 'Œ' => 0x8C, 'Ž' => 0x8E, '‘' => 0x91, '’' => 0x92,
        '“' => 0x93, '”' => 0x94, '•' => 0x95, '–' => 0x96, '—' => 0x97,
        '˜' => 0x98, '™' => 0x99, 'š' => 0x9A, '›' => 0x9B, 'œ' => 0x9C,
        'ž' => 0x9E, 'Ÿ' => 0x9F,
    ];

    public static function isValidUtf8(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }

    public static function looksLikeMojibake(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return self::mojibakeScore($value) > 0;
    }

    public static function repairMojibake(string $value): string
    {
        if ($value === '' || !self::looksLikeMojibake($value) || !self::isValidUtf8($value)) {
            return $value;
        }

        $current = $value;
        for ($pass = 0; $pass < 3; $pass++) {
            $next = self::repairMojibakeOnce($current);
            if ($next === $current) {
                return $current;
            }
            $current = $next;
            if (!self::looksLikeMojibake($current)) {
                return $current;
            }
        }

        return $current;
    }

    private static function repairMojibakeOnce(string $value): string
    {
        $bytes = '';
        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $code = self::codepoint($char);
            if ($code >= 0 && $code <= 0xFF) {
                $bytes .= chr($code);
                continue;
            }

            if (isset(self::CP1252_TO_BYTE[$char])) {
                $bytes .= chr(self::CP1252_TO_BYTE[$char]);
                continue;
            }

            return $value;
        }

        if ($bytes === '' || !self::isValidUtf8($bytes)) {
            return $value;
        }

        return self::isBetterRepair($value, $bytes) ? $bytes : $value;
    }

    public static function isBetterRepair(string $source, string $candidate): bool
    {
        if ($candidate === '' || $candidate === $source || !self::isValidUtf8($candidate)) {
            return false;
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $candidate) === 1) {
            return false;
        }

        if (preg_match('/[âăêôơưÂĂÊÔƠƯ][̣̀́̉̃àáảãạầấẩẫậằắẳẵặèéẻẽẹềếểễệìíỉĩịòóỏõọồốổỗộờớởỡợùúủũụừứửữựỳýỷỹỵ]/u', $candidate) === 1) {
            return false;
        }

        $sourceScore = self::mojibakeScore($source);
        $candidateScore = self::mojibakeScore($candidate);
        if ($candidateScore >= $sourceScore) {
            return false;
        }

        return preg_match('/[À-ỹĐđ]/u', $candidate) === 1 || $candidateScore === 0;
    }

    public static function mojibakeScore(string $value): int
    {
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $score = 0;
        $count = count($chars);
        for ($index = 0; $index < $count; $index++) {
            $code = self::codepoint($chars[$index]);
            $next = $index + 1 < $count ? self::codepoint($chars[$index + 1]) : -1;
            if (in_array($code, [0x00C2, 0x00C3, 0x00C4, 0x00C6], true) && $next >= 0x0080 && $next <= 0x00FF) {
                $score++;
                $index++;
                continue;
            }
            $third = $index + 2 < $count ? self::codepoint($chars[$index + 2]) : -1;
            if ($code === 0x00E1 && in_array($next, [0x00BA, 0x00BB], true) && $third > 0) {
                $score++;
                $index += 2;
            }
        }

        if (preg_match('/(?:\\?n kh\\?|\\?u|Nh\\?n|c\\?p nh\\?t)/u', $value) === 1) {
            $score++;
        }

        return $score;
    }

    private static function codepoint(string $char): int
    {
        $bytes = unpack('C*', $char);
        if (!$bytes) {
            return -1;
        }

        $first = $bytes[1];
        if ($first <= 0x7F) {
            return $first;
        }
        if (($first & 0xE0) === 0xC0) {
            return (($first & 0x1F) << 6) | ($bytes[2] & 0x3F);
        }
        if (($first & 0xF0) === 0xE0) {
            return (($first & 0x0F) << 12) | (($bytes[2] & 0x3F) << 6) | ($bytes[3] & 0x3F);
        }
        if (($first & 0xF8) === 0xF0) {
            return (($first & 0x07) << 18) | (($bytes[2] & 0x3F) << 12) | (($bytes[3] & 0x3F) << 6) | ($bytes[4] & 0x3F);
        }

        return -1;
    }
}
