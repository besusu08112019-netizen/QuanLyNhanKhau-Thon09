<?php

declare(strict_types=1);

namespace Ai\Intent;

final class CommandNormalizer
{
    public function normalize(string $text): NormalizedCommand
    {
        $raw = trim($text);
        $normalized = mb_strtolower($raw, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        $tokens = $normalized === '' ? [] : preg_split('/\s+/u', $normalized);
        $tokens = array_values(array_filter($tokens ?: [], static fn (string $token): bool => $token !== ''));

        return new NormalizedCommand($raw, $normalized, $tokens);
    }
}

