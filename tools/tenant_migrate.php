<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit(1);
}

use App\Core\Autoloader;
use App\Core\Database;
use App\Services\TenantMigrationService;

$basePath = dirname(__DIR__);
define('BASE_PATH', $basePath);

$index = @file_get_contents($basePath . '/index.php') ?: '';
if (preg_match("/APP_ASSET_VERSION',\s*'([^']+)'/", $index, $m)) {
    define('APP_ASSET_VERSION', $m[1]);
}

require_once $basePath . '/app/Core/Autoloader.php';
require_once $basePath . '/config/env.php';
Autoloader::register();
env_load($basePath);

$options = parseOptions($argv);
$apply = (bool) ($options['apply'] ?? false);
$json = (bool) ($options['json'] ?? false);
$only = strtolower((string) ($options['tenant'] ?? ''));

$service = new TenantMigrationService();
$tenants = discoverTenants($only);
if ($tenants === []) {
    $tenants[] = currentTenantConfig();
}

$rows = [];
foreach ($tenants as $tenant) {
    $label = $tenant['code'] ?: ($tenant['domain'] ?: $tenant['database']);
    try {
        $pdo = tenantPdo($tenant);
        $before = businessCounts($pdo);
        $result = $apply ? $service->applyPending($pdo, $label, false) : $service->audit($pdo, true);
        $after = businessCounts($pdo);
        $pending = $apply ? array_values(array_filter($result['results'], static fn(array $row): bool => $row['status'] !== 'SKIPPED')) : $result['pending'];
        $livestock = livestockAudit($pdo);
        $rows[] = [
            'tenant' => $label,
            'domain' => $tenant['domain'],
            'database' => $tenant['database'],
            'appVersion' => $service->currentAppVersion(),
            'schemaVersion' => $apply ? $service->latestMigrationId() : (string) ($result['schemaVersion'] ?? ''),
            'latestSchemaVersion' => $service->latestMigrationId(),
            'migration' => $apply ? (count($pending) ? 'APPLIED' : 'UP_TO_DATE') : ((int) ($result['pendingCount'] ?? 0) > 0 ? 'PENDING' : 'UP_TO_DATE'),
            'pending' => $pending,
            'features' => featureSummary($pdo),
            'livestock' => $livestock,
            'livestockMigrationCompatibility' => livestockMigrationCompatibility($livestock),
            'dataBefore' => $before,
            'dataAfter' => $after,
            'dataUnchanged' => $before === $after,
            'result' => 'PASS',
        ];
    } catch (Throwable $e) {
        $rows[] = [
            'tenant' => $label,
            'domain' => $tenant['domain'] ?? '',
            'database' => $tenant['database'] ?? '',
            'appVersion' => $service->currentAppVersion(),
            'schemaVersion' => '',
            'latestSchemaVersion' => $service->latestMigrationId(),
            'migration' => 'ERROR',
            'pending' => [],
            'features' => [],
            'dataBefore' => [],
            'dataAfter' => [],
            'dataUnchanged' => false,
            'result' => 'FAIL: ' . $e->getMessage(),
        ];
    }
}

if ($json) {
    echo json_encode(['apply' => $apply, 'tenants' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(anyFail($rows) ? 1 : 0);
}

printTable($rows);
exit(anyFail($rows) ? 1 : 0);

function parseOptions(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--apply') $options['apply'] = true;
        if ($arg === '--json') $options['json'] = true;
        if (str_starts_with($arg, '--tenant=')) $options['tenant'] = substr($arg, 9);
    }
    return $options;
}

function discoverTenants(string $only): array
{
    $tenants = [];
    try {
        $registry = Database::pdo();
        if (!tableExists($registry, 'villages')) return [];
        $columns = columns($registry, 'villages');
        $select = ['code', 'name', 'domain', 'subdomain'];
        foreach (['database_name', 'database_host', 'database_charset', 'status'] as $column) {
            if (in_array($column, $columns, true)) $select[] = $column;
        }
        $where = in_array('status', $columns, true) ? 'WHERE status NOT IN ("DELETED","FAILED","DISABLED")' : '';
        $stmt = $registry->query('SELECT ' . implode(',', $select) . ' FROM villages ' . $where . ' ORDER BY code');
        foreach ($stmt->fetchAll() as $row) {
            $domain = (string) ($row['domain'] ?: $row['subdomain'] ?: '');
            $code = (string) ($row['code'] ?? '');
            if ($only !== '' && !in_array($only, [strtolower($code), strtolower($domain)], true)) continue;
            $env = tenantEnv($domain);
            $database = (string) ($env['DB_DATABASE'] ?? $env['DB_NAME'] ?? $row['database_name'] ?? '');
            if ($database === '') continue;
            $tenants[] = [
                'code' => $code,
                'name' => (string) ($row['name'] ?? ''),
                'domain' => $domain,
                'host' => (string) ($env['DB_HOST'] ?? $env['MYSQL_HOST'] ?? $row['database_host'] ?? getenv('DB_HOST') ?: 'localhost'),
                'port' => (int) ($env['DB_PORT'] ?? $env['MYSQL_PORT'] ?? getenv('DB_PORT') ?: 3306),
                'database' => $database,
                'username' => (string) ($env['DB_USERNAME'] ?? $env['DB_USER'] ?? getenv('DB_USERNAME') ?: getenv('DB_USER') ?: ''),
                'password' => (string) ($env['DB_PASSWORD'] ?? $env['DB_PASS'] ?? getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: ''),
                'charset' => (string) ($env['DB_CHARSET'] ?? $row['database_charset'] ?? getenv('DB_CHARSET') ?: 'utf8mb4'),
            ];
        }
    } catch (Throwable) {
        return [];
    }
    return $tenants;
}

function currentTenantConfig(): array
{
    $config = require BASE_PATH . '/config/database.example.php';
    return [
        'code' => getenv('TENANT_DEFAULT_VILLAGE_CODE') ?: getenv('TENANT_CODE') ?: 'current',
        'name' => getenv('TENANT_HAMLET_NAME') ?: '',
        'domain' => getenv('APP_HOST') ?: '',
        'host' => (string) ($config['host'] ?? 'localhost'),
        'port' => (int) ($config['port'] ?? 3306),
        'database' => (string) ($config['database'] ?? ''),
        'username' => (string) ($config['username'] ?? ''),
        'password' => (string) ($config['password'] ?? ''),
        'charset' => (string) ($config['charset'] ?? 'utf8mb4'),
    ];
}

function tenantEnv(string $domain): array
{
    $candidates = [];
    $domain = strtolower(trim($domain));
    if ($domain !== '') {
        $candidates[] = dirname(BASE_PATH) . '/.env.' . $domain;
        $candidates[] = BASE_PATH . '/.env.' . $domain;
    }
    $data = [];
    foreach ($candidates as $path) {
        if (!is_file($path)) continue;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $data[$key] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
    return $data;
}

function tenantPdo(array $tenant): PDO
{
    if (($tenant['database'] ?? '') === '' || ($tenant['username'] ?? '') === '') {
        throw new RuntimeException('Missing database credentials');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $tenant['host'], (int) $tenant['port'], $tenant['database'], $tenant['charset'] ?: 'utf8mb4');
    return new PDO($dsn, (string) $tenant['username'], (string) $tenant['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ]);
}

function businessCounts(PDO $pdo): array
{
    $counts = [];
    foreach (['villages','users','households','citizens','gis_household_locations','file_attachments','settings'] as $table) {
        if (tableExists($pdo, $table)) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
    }
    return $counts;
}
function livestockAudit(PDO $pdo): array
{
    $hasLivestock = tableExists($pdo, 'livestock');
    $hasFacilities = tableExists($pdo, 'livestock_facilities');
    $livestockColumns = $hasLivestock ? columns($pdo, 'livestock') : [];
    $facilityColumns = $hasFacilities ? columns($pdo, 'livestock_facilities') : [];
    $activeCondition = in_array('status', $livestockColumns, true) ? "COALESCE(status,'ACTIVE') <> 'DELETED'" : '1=1';
    $hasQuantity = in_array('quantity', $livestockColumns, true);
    $hasAnimalType = in_array('animal_type', $livestockColumns, true);
    $hasHouseholdId = in_array('household_id', $livestockColumns, true);
    $summary = [
        'tables' => [
            'livestock' => $hasLivestock,
            'livestock_facilities' => $hasFacilities,
        ],
        'columns' => [
            'livestock' => $livestockColumns,
            'livestock_facilities' => $facilityColumns,
        ],
        'hasGroupsNew' => $hasFacilities
            && in_array('facility_id', $livestockColumns, true)
            && in_array('animal_group', $livestockColumns, true),
        'records' => 0,
        'households' => 0,
        'with_household_id' => 0,
        'missing_household_id' => 0,
        'total_quantity' => 0.0,
        'pig_total' => 0.0,
        'animal_type_totals' => [],
    ];

    if (!$hasLivestock) {
        return $summary;
    }

    $summary['records'] = (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE $activeCondition")->fetchColumn();
    if ($hasHouseholdId) {
        $summary['households'] = (int) $pdo->query("SELECT COUNT(DISTINCT household_id) FROM livestock WHERE $activeCondition AND household_id IS NOT NULL AND household_id > 0")->fetchColumn();
        $summary['with_household_id'] = (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE $activeCondition AND household_id IS NOT NULL AND household_id > 0")->fetchColumn();
        $summary['missing_household_id'] = (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE $activeCondition AND (household_id IS NULL OR household_id <= 0)")->fetchColumn();
    }
    if ($hasQuantity) {
        $summary['total_quantity'] = (float) $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM livestock WHERE $activeCondition")->fetchColumn();
    }
    if ($hasAnimalType && $hasQuantity) {
        $stmt = $pdo->query("SELECT animal_type, COALESCE(SUM(quantity),0) AS total FROM livestock WHERE $activeCondition GROUP BY animal_type ORDER BY animal_type");
        foreach ($stmt->fetchAll() as $row) {
            $type = (string) ($row['animal_type'] ?? '');
            $total = (float) ($row['total'] ?? 0);
            $summary['animal_type_totals'][] = ['animal_type' => $type, 'total' => $total];
            $key = mb_strtolower($type, 'UTF-8');
            if (str_contains($key, 'lợn') || str_contains($key, 'lon') || str_contains($key, 'heo') || str_contains($key, 'pig') || str_contains($key, 'lã')) {
                $summary['pig_total'] += $total;
            }
        }
    }

    return $summary;
}

function livestockMigrationCompatibility(array $audit): string
{
    if (($audit['tables']['livestock'] ?? false) === false) {
        return 'SAFE';
    }
    if (($audit['hasGroupsNew'] ?? false) === true) {
        return 'ALREADY_APPLIED';
    }
    $columns = $audit['columns']['livestock'] ?? [];
    foreach (['id', 'household_id', 'animal_type', 'quantity'] as $required) {
        if (!in_array($required, $columns, true)) {
            return 'NEEDS_ADJUSTMENT';
        }
    }
    return 'SAFE';
}
function featureSummary(PDO $pdo): array
{
    return [
        'household_residence' => tableExists($pdo, 'households') && columnExists($pdo, 'households', 'residence_status') && columnExists($pdo, 'households', 'residence_status_mode'),
        'reports' => tableExists($pdo, 'export_files'),
        'permissions' => tableExists($pdo, 'permissions') || tableExists($pdo, 'users'),
        'gis' => tableExists($pdo, 'gis_household_locations') || tableExists($pdo, 'gis_areas'),
        'files' => tableExists($pdo, 'file_attachments'),
        'schema_migrations' => tableExists($pdo, 'schema_migrations'),
    ];
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return array_map('strval', array_column($stmt->fetchAll(), 'COLUMN_NAME'));
}

function printTable(array $rows): void
{
    echo "Tenant | App version | Schema version | Migration | Chuc nang | Ket qua" . PHP_EOL;
    echo str_repeat('-', 120) . PHP_EOL;
    foreach ($rows as $row) {
        $features = [];
        foreach (($row['features'] ?? []) as $key => $ok) {
            $features[] = $key . '=' . ($ok ? 'OK' : 'MISS');
        }
        echo implode(' | ', [
            $row['tenant'],
            $row['appVersion'],
            ($row['schemaVersion'] ?: '-') . ' / latest ' . $row['latestSchemaVersion'],
            $row['migration'],
            implode(',', $features),
            $row['result'] . (($row['dataUnchanged'] ?? false) ? '; data-counts unchanged' : ''),
        ]) . PHP_EOL;
    }
}

function anyFail(array $rows): bool
{
    foreach ($rows as $row) {
        if (!str_starts_with((string) $row['result'], 'PASS')) return true;
    }
    return false;
}
