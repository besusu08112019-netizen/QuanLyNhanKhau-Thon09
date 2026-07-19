<?php

declare(strict_types=1);

const BASE_PATH = __DIR__ . '/..';

require BASE_PATH . '/app/Core/DatabaseException.php';
require BASE_PATH . '/app/Core/Database.php';
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(BASE_PATH . '/app/Core/Encoding.php', true);
}
require BASE_PATH . '/app/Core/Encoding.php';

use App\Core\Database;
use App\Core\Encoding;

$patterns = [
    'qa_uat_test_demo' => '(QA[-_ ]?CODEX|QA Citizen|UAT[-_ ]?CODEX|UAT|TEST|DEMO|CODEX)',
    'mojibake' => '(\x{00C3}|\x{00C2}|\x{00C6}|\x{00C4}|\x{00E1}\x{00BA}|\x{00E1}\x{00BB}|\?n kh\?|\?u|Nh\?n|c\?p nh\?t)',
];

try {
    $pdo = Database::pdo();
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'ok' => false,
        'error' => [
            'message' => $exception->getMessage(),
            'type' => get_class($exception),
        ],
        'generatedAt' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(2);
}

$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$columns = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext','enum')
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_ASSOC);

$byTable = [];
foreach ($columns as $column) {
    $byTable[(string) $column['TABLE_NAME']][] = (string) $column['COLUMN_NAME'];
}

$report = [
    'database' => $database,
    'generatedAt' => date('c'),
    'tablesScanned' => count($byTable),
    'matches' => [],
    'collation' => $pdo->query(
        "SELECT TABLE_NAME, TABLE_COLLATION
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_ASSOC),
];

foreach ($byTable as $table => $tableColumns) {
    $safeTable = quoteIdentifier($table);
    foreach ($patterns as $type => $regex) {
        $where = implode(' OR ', array_map(fn($column) => quoteIdentifier($column) . ' REGEXP :pattern', $tableColumns));

        if ($type === 'mojibake') {
            $selectColumns = sampleColumns($tableColumns);
            $scanColumns = array_values(array_unique(array_merge($selectColumns, $tableColumns)));
            $select = implode(', ', array_map('quoteIdentifier', $scanColumns));
            $stmt = $pdo->prepare("SELECT $select FROM $safeTable WHERE $where");
            $stmt->execute(['pattern' => $regex]);
            $matches = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!rowHasMojibake($row, $tableColumns)) {
                    continue;
                }
                $matches[] = array_intersect_key($row, array_flip($selectColumns));
            }
            if (!$matches) {
                continue;
            }
            $report['matches'][] = [
                'type' => $type,
                'table' => $table,
                'total' => count($matches),
                'sampleColumns' => $selectColumns,
                'sample' => array_slice($matches, 0, 20),
            ];
            continue;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM $safeTable WHERE $where");
        $stmt->execute(['pattern' => $regex]);
        $total = (int) (($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));
        if ($total <= 0) {
            continue;
        }

        $selectColumns = sampleColumns($tableColumns);
        $select = implode(', ', array_map('quoteIdentifier', $selectColumns));
        $stmt = $pdo->prepare("SELECT $select FROM $safeTable WHERE $where LIMIT 20");
        $stmt->execute(['pattern' => $regex]);

        $report['matches'][] = [
            'type' => $type,
            'table' => $table,
            'total' => $total,
            'sampleColumns' => $selectColumns,
            'sample' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;

function rowHasMojibake(array $row, array $columns): bool
{
    foreach ($columns as $column) {
        $value = $row[$column] ?? null;
        if (is_string($value) && Encoding::looksLikeMojibake($value)) {
            return true;
        }
    }
    return false;
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function sampleColumns(array $columns): array
{
    $preferred = ['id', 'household_code', 'citizen_code', 'asset_code', 'vehicle_code', 'house_code', 'parcel_code', 'name', 'full_name', 'head_citizen_name', 'created_at', 'updated_at', 'status'];
    $selected = [];
    foreach ($preferred as $column) {
        if (in_array($column, $columns, true)) {
            $selected[] = $column;
        }
    }
    foreach ($columns as $column) {
        if (!in_array($column, $selected, true)) {
            $selected[] = $column;
        }
        if (count($selected) >= 12) {
            break;
        }
    }
    return $selected;
}
