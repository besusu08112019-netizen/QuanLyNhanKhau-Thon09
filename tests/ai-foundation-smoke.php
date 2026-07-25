<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Contracts\AiToolInterface;
use Ai\Core\AiConfig;
use Ai\Core\AiRequest;
use Ai\Core\AiRouter;
use Ai\Core\ContextManager;
use Ai\Core\ConversationManager;
use Ai\Core\ToolRegistry;
use Ai\Logging\AiLogger;

final class SmokeTool implements AiToolInterface
{
    public function name(): string
    {
        return 'smoke';
    }

    public function description(): string
    {
        return 'Smoke test tool.';
    }

    public function schema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(array $input, array $context = []): array
    {
        return ['ok' => true, 'input' => $input, 'context' => $context];
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$config = AiConfig::fromFile(__DIR__ . '/../ai/config/ai.php');
check($config->enabled() === false, 'AI must be disabled by default.');
check($config->maxContextMessages() === 20, 'Unexpected max context messages.');

$registry = new ToolRegistry();
$registry->register(new SmokeTool());
check($registry->get('smoke') instanceof SmokeTool, 'Tool registry cannot resolve registered tool.');
check(count($registry->describe()) === 1, 'Tool registry description count mismatch.');

$conversation = new ConversationManager(2);
$conversation->addMessage('c1', 'user', 'one');
$conversation->addMessage('c1', 'assistant', 'two');
$conversation->addMessage('c1', 'user', 'three');
check(count($conversation->history('c1')) === 2, 'Conversation history must be trimmed.');

$logger = new AiLogger(null, $config->sensitiveKeys());
$router = new AiRouter($config, new ContextManager(), $conversation, $registry, $logger);
$response = $router->route(new AiRequest('c1', 'Xin chao', 'u1', ['token' => 'secret']));
check($response->status === 'disabled', 'Router must not execute AI while disabled.');

echo 'AI foundation smoke test passed' . PHP_EOL;

