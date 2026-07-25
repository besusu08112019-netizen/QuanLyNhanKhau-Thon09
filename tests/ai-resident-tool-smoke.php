<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Business\ResidentTool;
use Ai\Core\ToolRegistry;
use Ai\Tools\ToolExecutor;

final class FakeCitizenRepository
{
    public array $lastFilters = [];

    public function paginate(array $filters): array
    {
        $this->lastFilters = $filters;
        return [
            'items' => [['id' => 7, 'citizen_code' => 'TENANT-NK0007', 'full_name' => 'Nguyen Van A']],
            'page' => $filters['page'],
            'pageSize' => $filters['pageSize'],
            'total' => 1,
        ];
    }

    public function find(int $id): ?array
    {
        return $id === 7 ? ['id' => 7, 'citizen_code' => 'TENANT-NK0007'] : null;
    }

    public function findByIdentity(string $identity): ?array
    {
        return $identity === '036155013781' ? ['id' => 7, 'identity_number' => $identity] : null;
    }
}

function resident_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$repo = new FakeCitizenRepository();
$tool = new ResidentTool($repo);

resident_assert($tool->name() === 'resident', 'Tool name mismatch.');
resident_assert($tool->module() === 'citizen', 'Tool module mismatch.');
resident_assert($tool->action() === 'read', 'Tool action mismatch.');
resident_assert($tool->readOnly() === true, 'ResidentTool must be read-only.');
resident_assert(!str_contains(json_encode($tool->schema(), JSON_THROW_ON_ERROR), 'delete'), 'Schema must not expose delete.');

$registry = new ToolRegistry();
$registry->register($tool);
$executor = new ToolExecutor($registry);

$denied = $executor->execute('resident', ['action' => 'list'], ['permissions' => []]);
resident_assert($denied->ok === false && $denied->error === 'permission_denied', 'Permission denial mismatch.');

$allowedContext = ['permissions' => ['citizen' => ['read' => true]]];
$list = $executor->execute('resident', [
    'action' => 'list',
    'page' => 2,
    'pageSize' => 500,
    'search' => 'Nguyen',
    'householdCode' => 'H09-0001',
    'ageFrom' => -10,
    'ageTo' => 80,
], $allowedContext);
resident_assert($list->ok === true, 'List action must succeed.');
resident_assert(($repo->lastFilters['pageSize'] ?? null) === 50, 'Page size must be capped.');
resident_assert(($repo->lastFilters['search'] ?? null) === 'Nguyen', 'Search filter mismatch.');
resident_assert(($repo->lastFilters['householdId'] ?? null) === 'H09-0001', 'Household filter mismatch.');
resident_assert(($repo->lastFilters['ageFrom'] ?? null) === '0', 'Age lower bound mismatch.');

$find = $executor->execute('resident', ['action' => 'find', 'id' => 7], $allowedContext);
resident_assert(($find->data['item']['citizen_code'] ?? '') === 'TENANT-NK0007', 'Find action mismatch.');

$byIdentity = $executor->execute('resident', ['action' => 'find_by_identity', 'identity' => '036155013781'], $allowedContext);
resident_assert(($byIdentity->data['item']['identity_number'] ?? '') === '036155013781', 'Find by identity mismatch.');

$invalid = $executor->execute('resident', ['action' => 'update', 'id' => 7], $allowedContext);
resident_assert($invalid->ok === false && $invalid->error === 'tool_execution_failed', 'Unsupported write action must fail.');

echo 'AI resident tool smoke test passed' . PHP_EOL;

