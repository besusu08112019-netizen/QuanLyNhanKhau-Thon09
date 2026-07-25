<?php

declare(strict_types=1);

namespace Ai\Business;

use Ai\Contracts\PermissionAwareAiToolInterface;

final class ResidentTool implements PermissionAwareAiToolInterface
{
    public function __construct(private readonly object $citizens)
    {
    }

    public function name(): string
    {
        return 'resident';
    }

    public function description(): string
    {
        return 'Read-only resident lookup tool backed by the existing Citizen model contract.';
    }

    public function module(): string
    {
        return 'citizen';
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
                'action' => ['type' => 'string', 'enum' => ['list', 'find', 'find_by_identity']],
                'id' => ['type' => 'integer', 'minimum' => 1],
                'identity' => ['type' => 'string'],
                'search' => ['type' => 'string'],
                'householdId' => ['type' => 'string'],
                'householdCode' => ['type' => 'string'],
                'gender' => ['type' => 'string'],
                'presenceStatus' => ['type' => 'string'],
                'residencyStatus' => ['type' => 'string'],
                'ageFrom' => ['type' => 'integer', 'minimum' => 0],
                'ageTo' => ['type' => 'integer', 'minimum' => 0],
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
            'find_by_identity' => $this->findByIdentity($input),
            default => throw new \InvalidArgumentException('Unsupported resident tool action.'),
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function list(array $input): array
    {
        $this->assertMethod('paginate');
        $household = trim((string) ($input['householdId'] ?? $input['householdCode'] ?? ''));

        return $this->citizens->paginate([
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'pageSize' => min(50, max(1, (int) ($input['pageSize'] ?? 20))),
            'search' => trim((string) ($input['search'] ?? '')),
            'householdId' => $household,
            'gender' => trim((string) ($input['gender'] ?? '')),
            'presenceStatus' => trim((string) ($input['presenceStatus'] ?? '')),
            'residencyStatus' => trim((string) ($input['residencyStatus'] ?? '')),
            'ageFrom' => $this->optionalInt($input['ageFrom'] ?? null),
            'ageTo' => $this->optionalInt($input['ageTo'] ?? null),
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
            throw new \InvalidArgumentException('Resident id is required.');
        }

        return ['item' => $this->citizens->find($id)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function findByIdentity(array $input): array
    {
        $this->assertMethod('findByIdentity');
        $identity = trim((string) ($input['identity'] ?? ''));
        if ($identity === '') {
            throw new \InvalidArgumentException('Resident identity number is required.');
        }

        return ['item' => $this->citizens->findByIdentity($identity)];
    }

    private function optionalInt(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) max(0, (int) $value);
    }

    private function assertMethod(string $method): void
    {
        if (!method_exists($this->citizens, $method)) {
            throw new \RuntimeException('Citizen repository does not support ' . $method . '.');
        }
    }
}

