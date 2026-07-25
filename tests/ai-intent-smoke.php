<?php

declare(strict_types=1);

require __DIR__ . '/../ai/bootstrap.php';

use Ai\Intent\CommandNormalizer;
use Ai\Intent\IntentRecognizer;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$normalizer = new CommandNormalizer();
$command = $normalizer->normalize('  Tìm   hộ dân H09-0001! ');
assert_true($command->normalizedText === 'tìm hộ dân h09-0001', 'Normalized text mismatch.');
assert_true(in_array('tìm', $command->tokens, true), 'Tokenization failed.');

$recognizer = new IntentRecognizer($normalizer);

$search = $recognizer->recognize('Tìm hộ dân H09-0001');
assert_true($search->intent === 'search.query', 'Search intent mismatch.');
assert_true(($search->entities['module'] ?? '') === 'household', 'Search module mismatch.');
assert_true(($search->entities['household_code'] ?? '') === 'H09-0001', 'Household code extraction failed.');

$navigation = $recognizer->recognize('Mở trang nhân khẩu');
assert_true($navigation->intent === 'navigation.open_module', 'Navigation intent mismatch.');
assert_true(($navigation->entities['module'] ?? '') === 'citizen', 'Navigation module mismatch.');

$report = $recognizer->recognize('Báo cáo hộ nghèo');
assert_true($report->intent === 'report.view', 'Report intent mismatch.');

$draft = $recognizer->recognize('Thêm nhân khẩu mới');
assert_true($draft->intent === 'data.create_draft', 'Create draft intent mismatch.');
assert_true($draft->requiresConfirmation === true, 'Create draft must require confirmation.');

$unknown = $recognizer->recognize('xin chao he thong');
assert_true($unknown->intent === 'unknown', 'Unknown intent mismatch.');

echo 'AI intent smoke test passed' . PHP_EOL;

