<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Core\AiRuntimeFactory;
use Ai\Orchestration\ToolOrchestrator;
use Ai\Tools\ToolExecutor;

final class OrchestratorHouseholdRepository
{
    public array $lastFilters = [];

    public function paginate(array $filters): array
    {
        $this->lastFilters = $filters;
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

final class OrchestratorCitizenRepository
{
    public array $lastFilters = [];

    public function paginate(array $filters): array
    {
        $this->lastFilters = $filters;
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

final class OrchestratorStatisticsRepository
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

final class OrchestratorInsightRepository
{
    public function requiredModulesForQuestion(string $question): array
    {
        return ['complaints'];
    }

    public function ask(string $question): array
    {
        return ['question' => $question, 'intent' => 'open_complaints', 'mode' => 'READ_ONLY', 'answer' => 'Co 3 phan anh chua xu ly.', 'metrics' => ['open' => 3], 'items' => []];
    }
}

function orchestrator_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$registry = AiRuntimeFactory::toolRegistry([
    'household' => new OrchestratorHouseholdRepository(),
    'citizen' => new OrchestratorCitizenRepository(),
    'statistics' => new OrchestratorStatisticsRepository(),
    'insight' => new OrchestratorInsightRepository(),
]);
$orchestrator = new ToolOrchestrator($registry, new ToolExecutor($registry));
$context = [
    'permissions' => [
        'household' => ['read' => true],
        'citizen' => ['read' => true],
        'statistics' => ['read' => true],
        'dashboard' => ['read' => true],
        'complaints' => ['read' => true],
    ],
];

$household = $orchestrator->ask('Tim ho dan H09-0001', $context);
orchestrator_assert(($household['status'] ?? '') === 'answered', 'Household question must be answered.');
orchestrator_assert(($household['plan']['tool'] ?? '') === 'household', 'Household question must use household tool.');
orchestrator_assert(($household['plan']['input']['action'] ?? '') === 'find_by_code', 'Household code action mismatch.');

$resident = $orchestrator->ask('Tra nhan khau CCCD 036155013781', $context);
orchestrator_assert(($resident['plan']['tool'] ?? '') === 'resident', 'Identity question must use resident tool.');
orchestrator_assert(($resident['plan']['input']['action'] ?? '') === 'find_by_identity', 'Identity action mismatch.');

$health = $orchestrator->ask('Thong ke BHYT', $context);
orchestrator_assert(($health['plan']['tool'] ?? '') === 'statistics', 'Health insurance question must use statistics tool.');
orchestrator_assert(($health['plan']['input']['action'] ?? '') === 'health_insurance', 'Health insurance action mismatch.');

$complaints = $orchestrator->ask('Co bao nhieu phan anh chua xu ly?', $context);
orchestrator_assert(($complaints['status'] ?? '') === 'answered', 'Complaint insight question must be answered.');
orchestrator_assert(($complaints['plan']['tool'] ?? '') === 'insight', 'Complaint question must use insight tool.');
orchestrator_assert(($complaints['result']['data']['data']['intent'] ?? '') === 'open_complaints', 'Insight intent mismatch.');

$denied = $orchestrator->ask('Thong ke BHYT', ['permissions' => ['household' => ['read' => true]]]);
orchestrator_assert(($denied['status'] ?? '') === 'failed', 'Denied statistics question must fail.');
orchestrator_assert(($denied['result']['error'] ?? '') === 'permission_denied', 'Denied question must expose permission_denied.');

$unknown = $orchestrator->ask('xin chao', $context);
orchestrator_assert(($unknown['status'] ?? '') === 'needs_clarification', 'Unknown question must ask for clarification.');
orchestrator_assert(($unknown['mode'] ?? '') === 'READ_ONLY', 'Unknown question must stay read-only.');

echo 'AI tool orchestrator smoke test passed' . PHP_EOL;
