<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Database;

Autoloader::register();
header('Content-Type: application/json; charset=utf-8');

$config = require BASE_PATH . '/config/database.php';
$secret = (string) ($config['password'] ?? '');
$expectedKey = substr(hash_hmac('sha256', '20260701-population-movement', $secret), 0, 32);
$key = (string) ($_GET['key'] ?? '');
if ($secret === '' || !hash_equals($expectedKey, $key)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Not found'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$report = [
    'ok' => false,
    'migration' => '20260701_180000_population_movement_automation',
    'startedAt' => date('c'),
    'database' => $config['database'] ?? null,
    'username' => $config['username'] ?? null,
    'steps' => [],
    'schema' => [],
    'errors' => [],
];

try {
    $pdo = Database::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $tableExists = static function (string $table) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name');
        $stmt->execute(['table' => $table, 'index_name' => $index]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $run = static function (string $name, string $sql) use ($pdo, &$report): void {
        $started = microtime(true);
        try {
            $pdo->exec($sql);
            $report['steps'][] = ['name' => $name, 'status' => 'applied', 'durationMs' => (int) round((microtime(true) - $started) * 1000)];
        } catch (Throwable $e) {
            $report['steps'][] = ['name' => $name, 'status' => 'failed', 'error' => $e->getMessage()];
            throw $e;
        }
    };

    $skip = static function (string $name) use (&$report): void {
        $report['steps'][] = ['name' => $name, 'status' => 'skipped'];
    };

    $addColumn = static function (string $table, string $column, string $definition, ?string $after = null) use ($columnExists, $run, $skip): void {
        if ($columnExists($table, $column)) {
            $skip("$table.$column already exists");
            return;
        }
        $afterSql = ($after && $columnExists($table, $after)) ? " AFTER `$after`" : '';
        $run("Add $table.$column", "ALTER TABLE `$table` ADD COLUMN `$column` $definition$afterSql");
    };

    if (!$tableExists('citizens') || !$tableExists('households') || !$tableExists('movements')) {
        throw new RuntimeException('Missing required tables: citizens, households, movements');
    }

    $run('Expand citizens.residency_status enum', "ALTER TABLE citizens MODIFY COLUMN residency_status ENUM('PERMANENT','TEMPORARY','TRANSFERRED_OUT') NOT NULL DEFAULT 'PERMANENT' COMMENT 'PERMANENT=Thuong tru, TEMPORARY=Tam tru, TRANSFERRED_OUT=Da chuyen di'");
    $addColumn('citizens', 'move_out_date', 'DATE NULL', 'presence_status');
    $addColumn('citizens', 'move_out_place', 'VARCHAR(255) NULL', 'move_out_date');
    $addColumn('citizens', 'move_out_reason', 'VARCHAR(255) NULL', 'move_out_place');
    $addColumn('citizens', 'move_in_date', 'DATE NULL', 'move_out_reason');
    $addColumn('citizens', 'move_in_place', 'VARCHAR(255) NULL', 'move_in_date');
    $addColumn('citizens', 'move_in_type', 'VARCHAR(120) NULL', 'move_in_place');
    $addColumn('citizens', 'formation_source', 'VARCHAR(120) NULL', 'move_in_type');
    $addColumn('citizens', 'decision_number', 'VARCHAR(100) NULL', 'formation_source');

    $run('Expand households.status enum', "ALTER TABLE households MODIFY COLUMN status ENUM('ACTIVE','INACTIVE','TRANSFERRED_OUT','ENDED','MERGED','DELETED') NOT NULL DEFAULT 'ACTIVE'");
    $householdAnchor = $columnExists('households', 'household_type') ? 'household_type' : 'note';
    $addColumn('households', 'household_move_out_date', 'DATE NULL', $householdAnchor);
    $addColumn('households', 'household_move_out_place', 'VARCHAR(255) NULL', 'household_move_out_date');
    $addColumn('households', 'household_move_in_date', 'DATE NULL', 'household_move_out_place');
    $addColumn('households', 'household_move_in_place', 'VARCHAR(255) NULL', 'household_move_in_date');

    $run('Expand movements.type enum', "ALTER TABLE movements MODIFY COLUMN type ENUM('BIRTH','DEATH','MOVE_IN','MOVE_OUT','HOUSEHOLD_SPLIT','HOUSEHOLD_MERGE','HOUSEHOLD_HEAD_CHANGE','CITIZEN_UPDATE','RESTORE','TEMPORARY_RESIDENCE','TEMPORARY_ABSENCE','OTHER') NOT NULL DEFAULT 'OTHER'");
    $addColumn('movements', 'object_type', 'VARCHAR(50) NULL', 'household_id');
    $addColumn('movements', 'object_id', 'BIGINT UNSIGNED NULL', 'object_type');
    $addColumn('movements', 'object_code', 'VARCHAR(80) NULL', 'object_id');
    $addColumn('movements', 'actor_name', 'VARCHAR(190) NULL', 'object_code');
    try {
        $addColumn('movements', 'before_data', 'JSON NULL', 'note');
    } catch (Throwable $e) {
        if (!$columnExists('movements', 'before_data')) $addColumn('movements', 'before_data', 'LONGTEXT NULL', 'note');
    }
    try {
        $addColumn('movements', 'after_data', 'JSON NULL', 'before_data');
    } catch (Throwable $e) {
        if (!$columnExists('movements', 'after_data')) $addColumn('movements', 'after_data', 'LONGTEXT NULL', 'before_data');
    }

    foreach ([
        ['citizens', 'idx_citizens_move_out_date', 'move_out_date'],
        ['citizens', 'idx_citizens_move_in_date', 'move_in_date'],
        ['households', 'idx_households_business_status', 'status'],
        ['movements', 'idx_movements_created_by', 'created_by'],
    ] as [$table, $index, $column]) {
        if ($indexExists($table, $index)) $skip("Index $index already exists");
        else $run("Create index $index", "CREATE INDEX `$index` ON `$table` (`$column`)");
    }
    if ($indexExists('movements', 'idx_movements_object')) $skip('Index idx_movements_object already exists');
    else $run('Create index idx_movements_object', 'CREATE INDEX `idx_movements_object` ON `movements` (`object_type`, `object_id`)');

    $run('Create or replace v_household_member_counts', "CREATE OR REPLACE VIEW v_household_member_counts AS
SELECT
  h.id AS household_id,
  COUNT(c.id) AS total_members,
  SUM(CASE WHEN c.presence_status = 'AT_HOME' THEN 1 ELSE 0 END) AS at_home_count,
  SUM(CASE WHEN c.presence_status = 'AWAY' THEN 1 ELSE 0 END) AS away_count
FROM households h
LEFT JOIN citizens c
  ON c.household_id = h.id
 AND c.status = 'ACTIVE'
 AND c.life_status = 'ALIVE'
 AND c.residency_status <> 'TRANSFERRED_OUT'
GROUP BY h.id");

    foreach (['citizens','households','movements'] as $table) {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION');
        $stmt->execute(['table' => $table]);
        $report['schema'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_household_member_counts'");
    $report['schema']['v_household_member_counts_exists'] = (bool) $stmt->fetchColumn();

    $report['ok'] = true;
} catch (Throwable $e) {
    http_response_code(500);
    $report['errors'][] = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'code' => (string) $e->getCode(),
    ];
}

$report['finishedAt'] = date('c');
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
