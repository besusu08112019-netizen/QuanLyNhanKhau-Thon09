<?php

declare(strict_types=1);

namespace Ai\Logging;

final class AiLogger
{
    /**
     * @param list<string> $sensitiveKeys
     */
    public function __construct(
        private readonly ?string $logFile = null,
        private readonly array $sensitiveKeys = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $event, array $context): void
    {
        if ($this->logFile === null) {
            return;
        }

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $line = json_encode([
            'at' => gmdate('c'),
            'level' => $level,
            'event' => $event,
            'context' => $this->redact($context),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $keyText = is_string($key) ? strtolower($key) : (string) $key;
            $redacted[$key] = in_array($keyText, $this->sensitiveKeys, true) ? '[redacted]' : $this->redact($item);
        }

        return $redacted;
    }
}

