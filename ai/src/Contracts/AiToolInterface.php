<?php

declare(strict_types=1);

namespace Ai\Contracts;

interface AiToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function execute(array $input, array $context = []): array;
}

