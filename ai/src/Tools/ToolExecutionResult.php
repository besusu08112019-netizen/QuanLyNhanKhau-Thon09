<?php

declare(strict_types=1);

namespace Ai\Tools;

final class ToolExecutionResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $tool,
        public readonly array $data = [],
        public readonly ?string $error = null,
        public readonly array $meta = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function success(string $tool, array $data, array $meta = []): self
    {
        return new self(true, $tool, $data, null, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function failure(string $tool, string $error, array $meta = []): self
    {
        return new self(false, $tool, [], $error, $meta);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'tool' => $this->tool,
            'data' => $this->data,
            'error' => $this->error,
            'meta' => $this->meta,
        ];
    }
}

