<?php

declare(strict_types=1);

namespace Ai\Core;

final class AiConfig
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException('AI config file not found.');
        }

        $config = require $file;
        if (!is_array($config)) {
            throw new \RuntimeException('AI config file must return an array.');
        }

        return new self($config);
    }

    public function enabled(): bool
    {
        return (bool) ($this->values['enabled'] ?? false);
    }

    public function maxContextMessages(): int
    {
        return max(1, (int) ($this->values['max_context_messages'] ?? 20));
    }

    /**
     * @return array<string, mixed>
     */
    public function featureFlags(): array
    {
        $features = $this->values['features'] ?? [];
        return is_array($features) ? $features : [];
    }

    /**
     * @return list<string>
     */
    public function sensitiveKeys(): array
    {
        $keys = $this->values['log_sensitive_keys'] ?? [];
        return array_values(array_filter(is_array($keys) ? $keys : [], 'is_string'));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}

