<?php

declare(strict_types=1);

namespace Ai\Core;

final class AiRequest
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly string $message,
        public readonly string $userId = '',
        public readonly array $metadata = [],
    ) {
        if (trim($conversationId) === '') {
            throw new \InvalidArgumentException('Conversation id is required.');
        }
    }
}

