<?php

declare(strict_types=1);

namespace Ai\Tools;

use Ai\Contracts\AiToolInterface;
use Ai\Contracts\PermissionAwareAiToolInterface;

final class ToolPermissionChecker
{
    /**
     * @param array<string, mixed> $context
     */
    public function allowed(AiToolInterface $tool, array $context = []): bool
    {
        if (!$tool instanceof PermissionAwareAiToolInterface) {
            return true;
        }

        $role = strtoupper((string) ($context['role'] ?? $context['user_role'] ?? ''));
        if (in_array($role, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return true;
        }

        $permissions = $context['permissions'] ?? [];
        if (!is_array($permissions)) {
            return false;
        }

        $module = $tool->module();
        $action = $tool->action();
        return (bool) ($permissions[$module][$action] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function requirement(AiToolInterface $tool): array
    {
        if (!$tool instanceof PermissionAwareAiToolInterface) {
            return ['module' => null, 'action' => null, 'read_only' => null];
        }

        return [
            'module' => $tool->module(),
            'action' => $tool->action(),
            'read_only' => $tool->readOnly(),
        ];
    }
}

