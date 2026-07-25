<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Business\HouseholdTool;
use Ai\Core\ToolRegistry;
use Ai\Tools\ToolExecutor;

final class FakeHouseholdRepository
{
    public array $lastFilters = [];

    public function paginate(array $filters): array
    {
        $this->lastFilters = $filters;
        return [
            'items' => [['id' => 1, 'household_code' => 'H09-0001']],
            'page' => $filters['page'],
            'pageSize' => $filters['pageSize'],
            'total' => 1,
        ];
    }

    public function find(int $id): ?array
    {
        return $id === 1 ? ['id' => 1, 'household_code' => 'H09-0001'] : null;
    }

    public function findByCode(string $code): ?array
    {
        return $code === 'H09-0001' ? ['id' => 1, 'household_code' => $code] : null;
    }
}

function household_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$repo = new FakeHouseholdRepository();
$tool = new HouseholdTool($repo);

household_assert($tool->name() === 'household', 'Tool name mismatch.');
household_assert($tool->module() === 'household', 'Tool module mismatch.');
household_assert($tool->action() === 'read', 'Tool action mismatch.');
household_assert($tool->readOnly() === true, 'HouseholdTool must be read-only.');
household_assert(!str_contains(json_encode($tool->schema(), JSON_THROW_ON_ERROR), 'delete'), 'Schema must not expose delete.');

$registry = new ToolRegistry();
$registry->register($tool);
$executor = new ToolExecutor($registry);

$denied = $executor->execute('household', ['action' => 'list'], ['permissions' => []]);
household_assert($denied->ok === false && $denied->error === 'permission_denied', 'Permission denial mismatch.');

$allowedContext = ['permissions' => ['household' => ['read' => true]]];
$list = $executor->execute('household', ['action' => 'list', 'page' => 2, 'pageSize' => 500, 'search' => 'H09'], $allowedContext);
household_assert($list->ok === true, 'List action must succeed.');
household_assert(($repo->lastFilters['pageSize'] ?? null) === 50, 'Page size must be capped.');
household_assert(($repo->lastFilters['search'] ?? null) === 'H09', 'Search filter mismatch.');

$find = $executor->execute('household', ['action' => 'find', 'id' => 1], $allowedContext);
household_assert(($find->data['item']['household_code'] ?? '') === 'H09-0001', 'Find action mismatch.');

$byCode = $executor->execute('household', ['action' => 'find_by_code', 'code' => 'h09-0001'], $allowedContext);
household_assert(($byCode->data['item']['household_code'] ?? '') === 'H09-0001', 'Find by code action mismatch.');

$invalid = $executor->execute('household', ['action' => 'delete', 'id' => 1], $allowedContext);
household_assert($invalid->ok === false && $invalid->error === 'tool_execution_failed', 'Unsupported write action must fail.');

echo 'AI household tool smoke test passed' . PHP_EOL;

