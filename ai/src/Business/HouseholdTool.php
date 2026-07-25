<?php

declare(strict_types=1);

namespace Ai\Business;

use Ai\Contracts\PermissionAwareAiToolInterface;

final class HouseholdTool implements PermissionAwareAiToolInterface
{
    public function __construct(private readonly object $households)
    {
    }

    public function name(): string
    {
        return 'household';
    }

    public function description(): string
    {
        return 'Read-only household lookup tool backed by the existing Household model contract.';
    }

    public function module(): string
    {
        return 'household';
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
            'required' => ['action'],
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['list', 'find', 'find_by_code']],
                'id' => ['type' => 'integer', 'minimum' => 1],
                'code' => ['type' => 'string'],
                'search' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'page' => ['type' => 'integer', 'minimum' => 1],
                'pageSize' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $input, array $context = []): array
    {
        $action = strtolower(trim((string) ($input['action'] ?? 'list')));

        return match ($action) {
            'list' => $this->list($input),
            'find' => $this->find($input),
            'find_by_code' => $this->findByCode($input),
            default => throw new \InvalidArgumentException('Unsupported household tool action.'),
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function list(array $input): array
    {
        $this->assertMethod('paginate');

        return $this->households->paginate([
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'pageSize' => min(50, max(1, (int) ($input['pageSize'] ?? 20))),
            'search' => trim((string) ($input['search'] ?? '')),
            'status' => trim((string) ($input['status'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
            'household_type' => trim((string) ($input['category'] ?? '')),
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function find(array $input): array
    {
        $this->assertMethod('find');
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Household id is required.');
        }

        return ['item' => $this->households->find($id)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function findByCode(array $input): array
    {
        $this->assertMethod('findByCode');
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if ($code === '') {
            throw new \InvalidArgumentException('Household code is required.');
        }

        return ['item' => $this->households->findByCode($code)];
    }

    private function assertMethod(string $method): void
    {
        if (!method_exists($this->households, $method)) {
            throw new \RuntimeException('Household repository does not support ' . $method . '.');
        }
    }
}

