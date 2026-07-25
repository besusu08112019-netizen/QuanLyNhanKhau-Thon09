<?php

declare(strict_types=1);

namespace Ai\Intent;

final class IntentResult
{
    /**
     * @param array<string, mixed> $entities
     */
    public function __construct(
        public readonly string $intent,
        public readonly string $category,
        public readonly float $confidence,
        public readonly NormalizedCommand $command,
        public readonly array $entities = [],
        public readonly bool $requiresConfirmation = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'category' => $this->category,
            'confidence' => $this->confidence,
            'normalized_text' => $this->command->normalizedText,
            'tokens' => $this->command->tokens,
            'entities' => $this->entities,
            'requires_confirmation' => $this->requiresConfirmation,
        ];
    }
}

