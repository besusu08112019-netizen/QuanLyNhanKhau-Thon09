<?php

declare(strict_types=1);

namespace Ai\Business;

use Ai\Contracts\PermissionAwareAiToolInterface;

final class StatisticsTool implements PermissionAwareAiToolInterface
{
    public function __construct(private readonly object $statistics)
    {
    }

    public function name(): string
    {
        return 'statistics';
    }

    public function description(): string
    {
        return 'Read-only statistics lookup tool backed by the existing statistics or dashboard model contract.';
    }

    public function module(): string
    {
        return 'statistics';
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
                'action' => [
                    'type' => 'string',
                    'enum' => ['counts', 'metrics', 'health_insurance', 'summary'],
                ],
                'dateFrom' => ['type' => 'string'],
                'dateTo' => ['type' => 'string'],
                'householdStatus' => ['type' => 'string'],
                'citizenStatus' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'gender' => ['type' => 'string'],
                'residencyStatus' => ['type' => 'string'],
                'presenceStatus' => ['type' => 'string'],
                'ageFrom' => ['type' => 'integer', 'minimum' => 0],
                'ageTo' => ['type' => 'integer', 'minimum' => 0],
            ],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $input, array $context = []): array
    {
        $action = strtolower(trim((string) ($input['action'] ?? 'metrics')));
        $filters = $this->filters($input);

        return match ($action) {
            'counts' => $this->call('counts'),
            'metrics' => $this->callWithFilters('metrics', $filters),
            'health_insurance' => $this->callWithFilters('healthInsuranceStats', $filters),
            'summary' => $this->callWithFilters('summary', $filters),
            default => throw new \InvalidArgumentException('Unsupported statistics tool action.'),
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function filters(array $input): array
    {
        return [
            'dateFrom' => $this->optionalDate($input['dateFrom'] ?? ''),
            'dateTo' => $this->optionalDate($input['dateTo'] ?? ''),
            'householdStatus' => trim((string) ($input['householdStatus'] ?? '')),
            'citizenStatus' => trim((string) ($input['citizenStatus'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
            'gender' => trim((string) ($input['gender'] ?? '')),
            'residencyStatus' => trim((string) ($input['residencyStatus'] ?? '')),
            'presenceStatus' => trim((string) ($input['presenceStatus'] ?? '')),
            'ageFrom' => $this->optionalInt($input['ageFrom'] ?? null),
            'ageTo' => $this->optionalInt($input['ageTo'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function call(string $method): array
    {
        $this->assertMethod($method);
        return ['data' => $this->statistics->{$method}()];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function callWithFilters(string $method, array $filters): array
    {
        $this->assertMethod($method);
        return [
            'data' => $this->statistics->{$method}($filters),
            'filters' => $filters,
        ];
    }

    private function optionalDate(mixed $value): string
    {
        $date = trim((string) $value);
        if ($date === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Date filters must use YYYY-MM-DD.');
        }

        return $date;
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
        if (!method_exists($this->statistics, $method)) {
            throw new \RuntimeException('Statistics repository does not support ' . $method . '.');
        }
    }
}

