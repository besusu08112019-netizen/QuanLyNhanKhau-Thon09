<?php

declare(strict_types=1);

namespace Ai\Tools;

use Ai\Contracts\PermissionAwareAiToolInterface;

final class NullTool implements PermissionAwareAiToolInterface
{
    public function __construct(
        private readonly string $toolName,
        private readonly string $moduleName = 'ai',
        private readonly string $actionName = 'read',
        private readonly bool $isReadOnly = true,
    ) {
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return 'Framework-only null tool used for tests and wiring checks.';
    }

    public function module(): string
    {
        return $this->moduleName;
    }

    public function action(): string
    {
        return $this->actionName;
    }

    public function readOnly(): bool
    {
        return $this->isReadOnly;
    }

    public function schema(): array
    {
        return ['type' => 'object', 'additionalProperties' => true];
    }

    public function execute(array $input, array $context = []): array
    {
        return ['accepted' => true, 'input' => $input];
    }
}
