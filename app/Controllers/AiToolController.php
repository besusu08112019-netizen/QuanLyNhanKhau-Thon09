<?php

namespace App\Controllers;

use Ai\Contracts\PermissionAwareAiToolInterface;
use Ai\Core\AiRuntimeFactory;
use Ai\Orchestration\ToolOrchestrator;
use Ai\Tools\ToolExecutor;
use App\Core\BaseController;

final class AiToolController extends BaseController
{
    public function index(): void
    {
        require_once BASE_PATH . '/ai/bootstrap.php';

        $this->requirePermission('dashboard', 'read');
        $this->ok(['tools' => AiRuntimeFactory::toolRegistry()->describe()]);
    }

    public function execute(): void
    {
        require_once BASE_PATH . '/ai/bootstrap.php';

        $input = (array) $this->input();
        $toolName = trim((string) ($input['tool'] ?? $input['name'] ?? ''));
        if ($toolName === '') {
            $this->fail('Tool name is required.', 422);
        }

        $registry = AiRuntimeFactory::toolRegistry();
        $tool = $registry->get($toolName);
        if ($tool === null) {
            $this->fail('AI tool does not exist.', 404);
        }

        $user = $this->requireToolPermission($tool);
        $payload = $input['input'] ?? $input['arguments'] ?? [];
        if (!is_array($payload)) {
            $this->fail('Tool input must be an object.', 422);
        }

        $result = (new ToolExecutor($registry))->execute($toolName, $payload, $this->toolContext($user, $tool));
        if (!$result->ok) {
            $this->fail($result->error ?? 'AI tool execution failed.', $result->error === 'permission_denied' ? 403 : 400, $result->meta);
        }

        $this->audit($user, 'ai', 'tool_execute_readonly', 'Execute read-only AI business tool', null, [
            'tool' => $toolName,
            'requirement' => $result->meta['required'] ?? null,
        ]);
        $this->ok($result->toArray());
    }

    public function ask(): void
    {
        require_once BASE_PATH . '/ai/bootstrap.php';

        $user = $this->requirePermission('dashboard', 'read');
        $question = trim((string) ($this->input('question', $this->input('message', $this->query('q', '')))));
        if ($question === '') {
            $this->fail('Question is required.', 422);
        }

        $registry = AiRuntimeFactory::toolRegistry();
        $orchestrator = new ToolOrchestrator($registry, new ToolExecutor($registry));
        $answer = $orchestrator->ask($question, $this->readOnlyToolContext($user));

        if (($answer['status'] ?? '') === 'failed' && (($answer['result']['error'] ?? null) === 'permission_denied')) {
            $this->fail('Permission denied for selected AI tool.', 403, $answer['result']['meta'] ?? []);
        }

        $this->audit($user, 'ai', 'tool_orchestrate_readonly', 'Ask read-only AI business tools', null, [
            'status' => $answer['status'] ?? null,
            'tool' => $answer['plan']['tool'] ?? null,
            'reason' => $answer['plan']['reason'] ?? null,
        ]);
        $this->ok($answer);
    }

    private function requireToolPermission(object $tool): array
    {
        if (!$tool instanceof PermissionAwareAiToolInterface) {
            return $this->requirePermission('dashboard', 'read');
        }

        if (!$tool->readOnly()) {
            $this->fail('Only read-only AI tools can be executed through this endpoint.', 403);
        }

        return $this->requirePermission($tool->module(), $tool->action());
    }

    /**
     * @return array<string, mixed>
     */
    private function toolContext(array $user, object $tool): array
    {
        $context = [
            'user_id' => $user['id'] ?? null,
            'role' => $user['role'] ?? null,
            'user_role' => $user['role'] ?? null,
            'permissions' => [],
        ];

        if ($tool instanceof PermissionAwareAiToolInterface) {
            $context['permissions'][$tool->module()] = [$tool->action() => true];
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function readOnlyToolContext(array $user): array
    {
        return [
            'user_id' => $user['id'] ?? null,
            'role' => $user['role'] ?? null,
            'user_role' => $user['role'] ?? null,
            'permissions' => [
                'dashboard' => ['read' => $this->users()->can($user, 'dashboard', 'read')],
                'household' => ['read' => $this->users()->can($user, 'household', 'read')],
                'citizen' => ['read' => $this->users()->can($user, 'citizen', 'read')],
                'statistics' => ['read' => $this->users()->can($user, 'statistics', 'read')],
                'contributions' => ['read' => $this->users()->can($user, 'contributions', 'read')],
                'complaints' => ['read' => $this->users()->can($user, 'complaints', 'read')],
                'public_assets' => ['read' => $this->users()->can($user, 'public_assets', 'read')],
                'livestock' => ['read' => $this->users()->can($user, 'livestock', 'read')],
                'movement' => ['read' => $this->users()->can($user, 'movement', 'read')],
            ],
        ];
    }
}
