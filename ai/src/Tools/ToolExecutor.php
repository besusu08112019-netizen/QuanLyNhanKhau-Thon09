<?php

declare(strict_types=1);

namespace Ai\Tools;

use Ai\Core\ToolRegistry;

final class ToolExecutor
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ToolPermissionChecker $permissionChecker = new ToolPermissionChecker(),
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     */
    public function execute(string $toolName, array $input = [], array $context = []): ToolExecutionResult
    {
        $tool = $this->registry->get($toolName);
        if ($tool === null) {
            return ToolExecutionResult::failure($toolName, 'tool_not_found');
        }

        if (!$this->permissionChecker->allowed($tool, $context)) {
            return ToolExecutionResult::failure($toolName, 'permission_denied', [
                'required' => $this->permissionChecker->requirement($tool),
            ]);
        }

        try {
            $result = $tool->execute($input, $context);
            return ToolExecutionResult::success($toolName, $result, [
                'required' => $this->permissionChecker->requirement($tool),
            ]);
        } catch (\Throwable $exception) {
            return ToolExecutionResult::failure($toolName, 'tool_execution_failed', [
                'exception' => $exception::class,
            ]);
        }
    }
}

