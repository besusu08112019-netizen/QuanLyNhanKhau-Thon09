<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Core\ToolRegistry;
use Ai\Tools\NullTool;
use Ai\Tools\ToolExecutor;
use Ai\Tools\ToolPermissionChecker;

function assert_tool(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$registry = new ToolRegistry();
$tool = new NullTool('framework.null', 'ai_framework', 'read');
$registry->register($tool);

assert_tool($registry->has('framework.null'), 'Registered tool must exist.');
assert_tool($registry->names() === ['framework.null'], 'Tool names mismatch.');
$description = $registry->describe()[0] ?? [];
assert_tool(($description['module'] ?? '') === 'ai_framework', 'Tool module metadata mismatch.');
assert_tool(($description['action'] ?? '') === 'read', 'Tool action metadata mismatch.');
assert_tool(($description['read_only'] ?? null) === true, 'Tool read_only metadata mismatch.');

$checker = new ToolPermissionChecker();
assert_tool($checker->allowed($tool, ['role' => 'ADMIN']) === true, 'Admin must be allowed.');
assert_tool($checker->allowed($tool, ['permissions' => ['ai_framework' => ['read' => true]]]) === true, 'Explicit permission must be allowed.');
assert_tool($checker->allowed($tool, ['permissions' => ['ai_framework' => ['read' => false]]]) === false, 'Denied permission must be denied.');

$executor = new ToolExecutor($registry, $checker);
$missing = $executor->execute('missing.tool');
assert_tool($missing->ok === false && $missing->error === 'tool_not_found', 'Missing tool error mismatch.');

$denied = $executor->execute('framework.null', ['a' => 1], ['permissions' => []]);
assert_tool($denied->ok === false && $denied->error === 'permission_denied', 'Permission denial mismatch.');

$allowed = $executor->execute('framework.null', ['a' => 1], ['permissions' => ['ai_framework' => ['read' => true]]]);
assert_tool($allowed->ok === true, 'Allowed execution must succeed.');
assert_tool(($allowed->data['accepted'] ?? false) === true, 'Allowed execution data mismatch.');
assert_tool(($allowed->data['input']['a'] ?? null) === 1, 'Input forwarding mismatch.');

echo 'AI tool framework smoke test passed' . PHP_EOL;

