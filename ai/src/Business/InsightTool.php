<?php

declare(strict_types=1);

namespace Ai\Business;

use Ai\Contracts\PermissionAwareAiToolInterface;

final class InsightTool implements PermissionAwareAiToolInterface
{
    public function __construct(private readonly object $insights)
    {
    }

    public function name(): string
    {
        return 'insight';
    }

    public function description(): string
    {
        return 'Read-only operational insight assistant backed by the existing SystemInsight contract.';
    }

    public function module(): string
    {
        return 'dashboard';
    }

    public function action(): string
    {
        return 'read';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['action', 'question'],
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['ask']],
                'question' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function execute(array $input, array $context = []): array
    {
        $action = strtolower(trim((string) ($input['action'] ?? 'ask')));
        if ($action !== 'ask') {
            throw new \InvalidArgumentException('Unsupported insight tool action.');
        }

        $question = trim((string) ($input['question'] ?? ''));
        if ($question === '') {
            throw new \InvalidArgumentException('Insight question is required.');
        }

        $this->assertMethod('ask');
        if (method_exists($this->insights, 'requiredModulesForQuestion')) {
            $this->assertRequiredPermissions($this->insights->requiredModulesForQuestion($question), $context);
        }

        return ['data' => $this->insights->ask($question)];
    }

    /**
     * @param array<int, mixed> $modules
     * @param array<string, mixed> $context
     */
    private function assertRequiredPermissions(array $modules, array $context): void
    {
        $role = strtoupper((string) ($context['role'] ?? $context['user_role'] ?? ''));
        if (in_array($role, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return;
        }

        $permissions = $context['permissions'] ?? [];
        if (!is_array($permissions)) {
            throw new \RuntimeException('Insight permissions are required.');
        }

        foreach ($modules as $module) {
            $module = (string) $module;
            if (!(bool) ($permissions[$module]['read'] ?? false)) {
                throw new \RuntimeException('Missing read permission for ' . $module . '.');
            }
        }
    }

    private function assertMethod(string $method): void
    {
        if (!method_exists($this->insights, $method)) {
            throw new \RuntimeException('Insight repository does not support ' . $method . '.');
        }
    }
}
