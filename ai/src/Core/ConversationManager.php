<?php

declare(strict_types=1);

namespace Ai\Core;

final class ConversationManager
{
    /**
     * @var array<string, list<array{role:string, content:string, at:string}>>
     */
    private array $conversations = [];

    public function __construct(private readonly int $maxMessages = 20)
    {
    }

    public function addMessage(string $conversationId, string $role, string $content): void
    {
        $this->conversations[$conversationId] ??= [];
        $this->conversations[$conversationId][] = [
            'role' => $role,
            'content' => $content,
            'at' => gmdate('c'),
        ];

        if (count($this->conversations[$conversationId]) > $this->maxMessages) {
            $this->conversations[$conversationId] = array_slice($this->conversations[$conversationId], -$this->maxMessages);
        }
    }

    /**
     * @return list<array{role:string, content:string, at:string}>
     */
    public function history(string $conversationId): array
    {
        return $this->conversations[$conversationId] ?? [];
    }
}

