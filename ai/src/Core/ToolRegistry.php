<?php

declare(strict_types=1);

namespace Ai\Core;

use Ai\Contracts\AiToolInterface;
use Ai\Contracts\PermissionAwareAiToolInterface;

final class ToolRegistry
{
    /**
     * @var array<string, AiToolInterface>
     */
    private array $tools = [];

    public function register(AiToolInterface $tool): void
    {
        $name = trim($tool->name());
        if ($name === '') {
            throw new \InvalidArgumentException('AI tool name is required.');
        }

        $this->tools[$name] = $tool;
    }

    public function get(string $name): ?AiToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * @return list<array{name:string, description:string, schema:array<string, mixed>, module:string|null, action:string|null, read_only:bool|null}>
     */
    public function describe(): array
    {
        return array_map(static fn (AiToolInterface $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'schema' => $tool->schema(),
            'module' => $tool instanceof PermissionAwareAiToolInterface ? $tool->module() : null,
            'action' => $tool instanceof PermissionAwareAiToolInterface ? $tool->action() : null,
            'read_only' => $tool instanceof PermissionAwareAiToolInterface ? $tool->readOnly() : null,
        ], array_values($this->tools));
    }
}
