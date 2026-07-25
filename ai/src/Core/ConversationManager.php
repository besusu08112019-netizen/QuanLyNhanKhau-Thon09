<?php

declare(strict_types=1);

namespace Ai\Core;

final class ConversationManager
{
    /**
     * @var array<string, list<array{role:string, content:string, at:string}>>
     */
    private array $conversations = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $memory = [];

    /**
     * @var array<string, array{intent:string, missing:list<string>, prompt:string, at:string}|null>
     */
    private array $pendingClarifications = [];

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

    public function addUserMessage(string $conversationId, string $content): void
    {
        $this->addMessage($conversationId, 'user', $content);
    }

    public function addAssistantMessage(string $conversationId, string $content): void
    {
        $this->addMessage($conversationId, 'assistant', $content);
    }

    /**
     * @return list<array{role:string, content:string, at:string}>
     */
    public function history(string $conversationId): array
    {
        return $this->conversations[$conversationId] ?? [];
    }

    public function remember(string $conversationId, string $key, mixed $value): void
    {
        if (trim($key) === '') {
            throw new \InvalidArgumentException('Memory key is required.');
        }

        $this->memory[$conversationId] ??= [];
        $this->memory[$conversationId][$key] = $value;
    }

    public function recall(string $conversationId, string $key, mixed $default = null): mixed
    {
        return $this->memory[$conversationId][$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(string $conversationId): array
    {
        return [
            'history' => $this->history($conversationId),
            'memory' => $this->memory[$conversationId] ?? [],
            'pending_clarification' => $this->pendingClarifications[$conversationId] ?? null,
        ];
    }

    /**
     * @param list<string> $missing
     */
    public function setPendingClarification(string $conversationId, string $intent, array $missing, string $prompt): void
    {
        $this->pendingClarifications[$conversationId] = [
            'intent' => $intent,
            'missing' => array_values($missing),
            'prompt' => $prompt,
            'at' => gmdate('c'),
        ];
        $this->addAssistantMessage($conversationId, $prompt);
    }

    /**
     * @return array{intent:string, missing:list<string>, prompt:string, at:string}|null
     */
    public function pendingClarification(string $conversationId): ?array
    {
        return $this->pendingClarifications[$conversationId] ?? null;
    }

    public function clearPendingClarification(string $conversationId): void
    {
        unset($this->pendingClarifications[$conversationId]);
    }

    public function reset(string $conversationId): void
    {
        unset($this->conversations[$conversationId], $this->memory[$conversationId], $this->pendingClarifications[$conversationId]);
    }
}
