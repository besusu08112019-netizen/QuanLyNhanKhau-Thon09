<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Business\StatisticsTool;
use Ai\Core\ToolRegistry;
use Ai\Tools\ToolExecutor;

final class FakeStatisticsRepository
{
    public array $lastFilters = [];

    public function counts(): array
    {
        return [
            'total_households' => 148,
            'total_citizens' => 529,
        ];
    }

    public function metrics(array $filters = []): array
    {
        $this->lastFilters = $filters;
        return [
            'total_households' => 148,
            'total_citizens' => 529,
            'poor_households' => 2,
        ];
    }

    public function healthInsuranceStats(array $filters = []): array
    {
        $this->lastFilters = $filters;
        return [
            'total' => 529,
            'insured' => 500,
            'uninsured' => 29,
            'coverage_percent' => 94.52,
        ];
    }

    public function summary(array $filters = []): array
    {
        $this->lastFilters = $filters;
        return [
            'metrics' => $this->metrics($filters),
            'generatedAt' => '2026-07-25T00:00:00+07:00',
        ];
    }
}

function statistics_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$repo = new FakeStatisticsRepository();
$tool = new StatisticsTool($repo);

statistics_assert($tool->name() === 'statistics', 'Tool name mismatch.');
statistics_assert($tool->module() === 'statistics', 'Tool module mismatch.');
statistics_assert($tool->action() === 'read', 'Tool action mismatch.');
statistics_assert($tool->readOnly() === true, 'StatisticsTool must be read-only.');
statistics_assert(!str_contains(json_encode($tool->schema(), JSON_THROW_ON_ERROR), 'delete'), 'Schema must not expose delete.');

$registry = new ToolRegistry();
$registry->register($tool);
$executor = new ToolExecutor($registry);

$denied = $executor->execute('statistics', ['action' => 'metrics'], ['permissions' => []]);
statistics_assert($denied->ok === false && $denied->error === 'permission_denied', 'Permission denial mismatch.');

$allowedContext = ['permissions' => ['statistics' => ['read' => true]]];
$counts = $executor->execute('statistics', ['action' => 'counts'], $allowedContext);
statistics_assert(($counts->data['data']['total_households'] ?? 0) === 148, 'Counts action mismatch.');

$metrics = $executor->execute('statistics', [
    'action' => 'metrics',
    'dateFrom' => '2026-01-01',
    'dateTo' => '2026-07-25',
    'ageFrom' => -5,
    'gender' => 'Nam',
], $allowedContext);
statistics_assert($metrics->ok === true, 'Metrics action must succeed.');
statistics_assert(($repo->lastFilters['dateFrom'] ?? '') === '2026-01-01', 'dateFrom filter mismatch.');
statistics_assert(($repo->lastFilters['ageFrom'] ?? null) === '0', 'ageFrom must be capped at zero.');
statistics_assert(($repo->lastFilters['gender'] ?? '') === 'Nam', 'gender filter mismatch.');

$health = $executor->execute('statistics', ['action' => 'health_insurance'], $allowedContext);
statistics_assert(($health->data['data']['insured'] ?? 0) === 500, 'Health insurance action mismatch.');

$summary = $executor->execute('statistics', ['action' => 'summary'], $allowedContext);
statistics_assert(isset($summary->data['data']['metrics']), 'Summary action mismatch.');

$badDate = $executor->execute('statistics', ['action' => 'metrics', 'dateFrom' => '25/07/2026'], $allowedContext);
statistics_assert($badDate->ok === false && $badDate->error === 'tool_execution_failed', 'Invalid date must fail.');

$invalid = $executor->execute('statistics', ['action' => 'delete'], $allowedContext);
statistics_assert($invalid->ok === false && $invalid->error === 'tool_execution_failed', 'Unsupported write action must fail.');

echo 'AI statistics tool smoke test passed' . PHP_EOL;

