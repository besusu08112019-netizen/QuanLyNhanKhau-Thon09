<?php

declare(strict_types=1);

namespace Ai\Core;

final class AiResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $payload = [],
    ) {
    }

    public static function disabled(): self
    {
        return new self('disabled', 'AI Foundation is installed but not enabled.');
    }
}

