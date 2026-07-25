<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Conversation\ConversationOrchestrator;
use Ai\Core\ConversationManager;
use Ai\Intent\IntentRecognizer;

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$manager = new ConversationManager(4);
$orchestrator = new ConversationOrchestrator($manager, new IntentRecognizer());

$unknown = $orchestrator->handleText('c1', 'xin chao');
expect_true($unknown['status'] === 'needs_clarification', 'Unknown text must ask for clarification.');
expect_true(($unknown['missing'][0] ?? '') === 'intent', 'Missing intent expected.');
expect_true($manager->pendingClarification('c1') !== null, 'Pending clarification must be stored.');

$search = $orchestrator->handleText('c1', 'Tim ho dan H09-0001');
expect_true($search['status'] === 'recognized', 'Search with module must be recognized.');
expect_true($manager->recall('c1', 'last_module') === 'household', 'Last module memory mismatch.');
expect_true($manager->pendingClarification('c1') === null, 'Pending clarification must be cleared.');

$manager->addUserMessage('c1', 'one');
$manager->addUserMessage('c1', 'two');
$manager->addUserMessage('c1', 'three');
$manager->addUserMessage('c1', 'four');
$manager->addUserMessage('c1', 'five');
expect_true(count($manager->history('c1')) === 4, 'History must respect max messages.');

$context = $manager->context('c1');
expect_true(array_key_exists('history', $context) && array_key_exists('memory', $context) && array_key_exists('pending_clarification', $context), 'Context shape mismatch.');

$manager->reset('c1');
expect_true($manager->history('c1') === [], 'Reset must clear history.');
expect_true($manager->recall('c1', 'last_module') === null, 'Reset must clear memory.');

echo 'AI conversation smoke test passed' . PHP_EOL;
