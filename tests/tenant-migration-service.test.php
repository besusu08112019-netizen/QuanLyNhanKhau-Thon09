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


$serviceSource = file_get_contents(BASE_PATH . '/app/Services/TenantMigrationService.php') ?: '';
$toolSource = file_get_contents(BASE_PATH . '/tools/tenant_migrate.php') ?: '';
foreach (['actualSchemaAudit', 'livestockAudit', 'livestockMigrationCompatibility', 'migrationHistorySummary'] as $needle) {
    if (!str_contains($serviceSource, $needle)) {
        fwrite(STDERR, 'Tenant migration audit must expose ' . $needle . PHP_EOL);
        exit(1);
    }
}
if (!str_contains($toolSource, '$service->audit($pdo, true)')) {
    fwrite(STDERR, 'tenant_migrate.php default audit must call read-only audit mode.' . PHP_EOL);
    exit(1);
}
if (str_contains($toolSource, 'function livestockAudit(') || str_contains($toolSource, 'function livestockMigrationCompatibility(')) {
    fwrite(STDERR, 'tenant_migrate.php must not duplicate livestock audit logic outside TenantMigrationService.' . PHP_EOL);
    exit(1);
}
if (!str_contains($toolSource, 'migrationCatalog($service)') || !str_contains($toolSource, 'hasLivestockFacilitiesMigration')) {
    fwrite(STDERR, 'tenant_migrate.php must report whether the livestock facilities migration file is present in the host catalog.' . PHP_EOL);
    exit(1);
}
if (!str_contains($serviceSource, 'emptyHistoryWithSchema')) {
    fwrite(STDERR, 'Audit must detect schema present with empty migration history.' . PHP_EOL);
    exit(1);
}
echo "Tenant migration service checks passed\n";