<?php

declare(strict_types=1);

namespace Ai\Intent;

final class NormalizedCommand
{
    /**
     * @param list<string> $tokens
     */
    public function __construct(
        public readonly string $rawText,
        public readonly string $normalizedText,
        public readonly array $tokens,
    ) {
    }
}

