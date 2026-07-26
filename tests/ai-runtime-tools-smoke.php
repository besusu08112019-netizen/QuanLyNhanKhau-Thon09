<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Core\AiRuntimeFactory;
use Ai\Tools\ToolExecutor;

final class RuntimeFakeHouseholdRepository
{
    public function paginate(array $filters): array
    {
        return ['items' => [['id' => 1, 'household_code' => 'H09-0001']], 'page' => 1, 'pageSize' => 20, 'total' => 1];
    }

    public function find(int $id): ?array
    {
        return ['id' => $id, 'household_code' => 'H09-0001'];
    }

    public function findByCode(string $code): ?array
    {
        return ['id' => 1, 'household_code' => $code];
    }
}

final class RuntimeFakeCitizenRepository
{
    public function paginate(array $filters): array
    {
        return ['items' => [['id' => 7, 'full_name' => 'Nguyen Van A']], 'page' => 1, 'pageSize' => 20, 'total' => 1];
    }

    public function find(int $id): ?array
    {
        return ['id' => $id, 'full_name' => 'Nguyen Van A'];
    }

    public function findByIdentity(string $identity): ?array
    {
        return ['id' => 7, 'identity_number' => $identity];
    }
}

final class RuntimeFakeStatisticsRepository
{
    public function counts(): array
    {
        return ['total_households' => 148, 'total_citizens' => 529];
    }

    public function metrics(array $filters = []): array
    {
        return ['total_households' => 148, 'total_citizens' => 529];
    }

    public function healthInsuranceStats(array $filters = []): array
    {
        return ['total' => 529, 'insured' => 500, 'uninsured' => 29, 'coverage_percent' => 94.52];
    }

    public function summary(array $filters = []): array
    {
        return ['metrics' => $this->metrics($filters)];
    }
}

final class RuntimeFakeInsightRepository
{
    public function requiredModulesForQuestion(string $question): array
    {
        return ['complaints'];
    }

    public function ask(string $question): array
    {
        return ['question' => $question, 'intent' => 'open_complaints', 'mode' => 'READ_ONLY', 'answer' => 'Co 2 phan anh chua xu ly.', 'metrics' => ['open' => 2], 'items' => []];
    }
}

function runtime_tools_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$registry = AiRuntimeFactory::toolRegistry([
    'household' => new RuntimeFakeHouseholdRepository(),
    'citizen' => new RuntimeFakeCitizenRepository(),
    'statistics' => new RuntimeFakeStatisticsRepository(),
    'insight' => new RuntimeFakeInsightRepository(),
]);

$names = $registry->names();
sort($names);
runtime_tools_assert($names === ['household', 'insight', 'resident', 'statistics'], 'Runtime registry tool names mismatch.');

$descriptions = $registry->describe();
runtime_tools_assert(count($descriptions) === 4, 'Runtime registry description count mismatch.');
foreach ($descriptions as $tool) {
    runtime_tools_assert(($tool['read_only'] ?? null) === true, 'Runtime business tools must stay read-only.');
}

$executor = new ToolExecutor($registry);
$context = [
    'permissions' => [
        'household' => ['read' => true],
        'citizen' => ['read' => true],
        'statistics' => ['read' => true],
        'dashboard' => ['read' => true],
        'complaints' => ['read' => true],
    ],
];

runtime_tools_assert($executor->execute('household', ['action' => 'find_by_code', 'code' => 'H09-0001'], $context)->ok === true, 'Household runtime execution failed.');
runtime_tools_assert($executor->execute('resident', ['action' => 'find_by_identity', 'identity' => '036155013781'], $context)->ok === true, 'Resident runtime execution failed.');
runtime_tools_assert($executor->execute('statistics', ['action' => 'counts'], $context)->ok === true, 'Statistics runtime execution failed.');
runtime_tools_assert($executor->execute('insight', ['action' => 'ask', 'question' => 'Co bao nhieu phan anh chua xu ly?'], $context)->ok === true, 'Insight runtime execution failed.');

$denied = $executor->execute('resident', ['action' => 'list'], ['permissions' => ['household' => ['read' => true]]]);
runtime_tools_assert($denied->ok === false && $denied->error === 'permission_denied', 'Runtime registry must enforce per-module permissions.');

$insightDenied = $executor->execute('insight', ['action' => 'ask', 'question' => 'Co bao nhieu phan anh chua xu ly?'], ['permissions' => ['dashboard' => ['read' => true]]]);
runtime_tools_assert($insightDenied->ok === false && $insightDenied->error === 'permission_denied', 'Insight source permissions must be enforced.');
runtime_tools_assert(($insightDenied->meta['required']['module'] ?? '') === 'complaints', 'Insight denied result must expose missing source module.');

echo 'AI runtime tools smoke test passed' . PHP_EOL;
