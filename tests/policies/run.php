<?php

require_once __DIR__ . '/bootstrap.php';

$required = [
    __DIR__ . '/helpers/PolicyFixtures.php',
    __DIR__ . '/fixtures/PolicyTestMatrix.php',
    __DIR__ . '/fixtures/PolicyGoldenDataset.php',
];

foreach ($required as $file) {
    require_once $file;
}

$filter = $argv[1] ?? null;
$testFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
    $path = $file->getPathname();
    if (!str_ends_with($path, 'Test.php')) continue;
    if ($filter !== null && !str_contains(str_replace('\\', '/', $path), '/' . trim($filter, '/') . '/')) continue;
    $testFiles[] = $path;
}
sort($testFiles);

foreach ($testFiles as $file) {
    require_once $file;
}

$passed = 0;
$failed = 0;

foreach (PolicyTestRegistry::all() as $test) {
    try {
        ($test['callback'])();
        $passed++;
        echo "[PASS] {$test['name']}\n";
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "[FAIL] {$test['name']}: {$e->getMessage()}\n");
    }
}

echo "Policy tests: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
