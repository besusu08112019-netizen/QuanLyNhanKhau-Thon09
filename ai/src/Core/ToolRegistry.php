<?php

declare(strict_types=1);

namespace Ai\Core;

use Ai\Contracts\AiToolInterface;

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

    /**
     * @return list<array{name:string, description:string, schema:array<string, mixed>}>
     */
    public function describe(): array
    {
        return array_map(static fn (AiToolInterface $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'schema' => $tool->schema(),
        ], array_values($this->tools));
    }
}

