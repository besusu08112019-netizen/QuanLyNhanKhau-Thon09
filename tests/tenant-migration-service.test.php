<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

$service = new App\Services\TenantMigrationService();
$latest = $service->latestMigrationId();
if ($latest !== '20260812_190000_livestock_facilities_and_groups') {
    fwrite(STDERR, 'Expected latest livestock facilities migration, got ' . $latest . PHP_EOL);
    exit(1);
}

$sql = "CREATE TABLE a (id INT);\n-- comment ;\nINSERT INTO a VALUES ('value;kept');\n";
$parts = $service->splitSql($sql);
if (count($parts) !== 2) {
    fwrite(STDERR, 'Expected SQL splitter to keep semicolon inside quoted string.' . PHP_EOL);
    exit(1);
}

echo "Tenant migration service checks passed\n";