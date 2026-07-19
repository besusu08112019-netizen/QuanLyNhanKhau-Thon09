<?php

declare(strict_types=1);

const BASE_PATH = __DIR__ . '/..';

require BASE_PATH . '/app/Core/DatabaseException.php';
require BASE_PATH . '/app/Core/Database.php';
require BASE_PATH . '/app/Core/Encoding.php';

use App\Core\Database;
use App\Core\Encoding;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$options = getopt('', ['action:', 'yes']);
$action = (string) ($options['action'] ?? 'preview');
$apply = array_key_exists('yes', $options);

$pdo = Database::pdo();
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

$report = match ($action) {
    'diagnose' => diagnose($pdo),
    'preview' => preview($pdo, 5),
    'migrate' => migrate($pdo, $apply),
    default => throw new InvalidArgumentException('Unknown action. Use diagnose, preview, or migrate.'),
};

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;

function diagnose(PDO $pdo): array
{
    return [
        'ok' => true,
        'generatedAt' => date('c'),
        'database' => (string) $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'server' => $pdo->query("SHOW VARIABLES WHERE Variable_name IN ('character_set_client','character_set_connection','character_set_database','character_set_results','character_set_server','collation_connection','collation_database','collation_server')")->fetchAll(PDO::FETCH_KEY_PAIR),
        'schemaDefaults' => $pdo->query(
            "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
             FROM INFORMATION_SCHEMA.SCHEMATA
             WHERE SCHEMA_NAME = DATABASE()"
        )->fetch(PDO::FETCH_ASSOC),
        'tableIssues' => $pdo->query(
            "SELECT TABLE_NAME, TABLE_COLLATION
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_TYPE = 'BASE TABLE'
               AND TABLE_COLLATION NOT LIKE 'utf8mb4%'
             ORDER BY TABLE_NAME"
        )->fetchAll(PDO::FETCH_ASSOC),
        'columnIssues' => $pdo->query(
            "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_SET_NAME, COLLATION_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND CHARACTER_SET_NAME IS NOT NULL
               AND CHARACTER_SET_NAME <> 'utf8mb4'
             ORDER BY TABLE_NAME, ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function preview(PDO $pdo, int $limitPerColumn): array
{
    $changes = buildChanges($pdo, $limitPerColumn);
    return [
        'ok' => true,
        'generatedAt' => date('c'),
        'totalCandidates' => count($changes),
        'samples' => array_slice($changes, 0, 80),
        'byTable' => summarizeChanges($changes),
    ];
}

function migrate(PDO $pdo, bool $apply): array
{
    $changes = buildChanges($pdo, 0);
    $backupPath = writeBackup($changes);
    if (!$apply) {
        return [
            'ok' => false,
            'dryRun' => true,
            'message' => 'Backup written. Re-run with --yes to apply.',
            'backupPath' => $backupPath,
            'totalCandidates' => count($changes),
            'byTable' => summarizeChanges($changes),
        ];
    }

    $pdo->beginTransaction();
    try {
        $applied = 0;
        foreach ($changes as $change) {
            $stmt = $pdo->prepare(
                'UPDATE ' . qi($change['table']) .
                ' SET ' . qi($change['column']) . ' = :fixed' .
                ' WHERE ' . qi($change['pk']) . ' = :pk' .
                ' AND ' . qi($change['column']) . ' = :source'
            );
            $stmt->execute([
                'fixed' => $change['fixed'],
                'pk' => $change['pkValue'],
                'source' => $change['source'],
            ]);
            $applied += $stmt->rowCount();
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'ok' => true,
        'generatedAt' => date('c'),
        'backupPath' => $backupPath,
        'totalCandidates' => count($changes),
        'applied' => $applied,
        'byTable' => summarizeChanges($changes),
    ];
}

function buildChanges(PDO $pdo, int $limitPerColumn): array
{
    $changes = [];
    foreach (textColumns($pdo) as $column) {
        $table = (string) $column['TABLE_NAME'];
        $name = (string) $column['COLUMN_NAME'];
        $pk = primaryKey($pdo, $table);
        if ($pk === null) {
            continue;
        }

        $sql = 'SELECT ' . qi($pk) . ' AS pk_value, ' . qi($name) . ' AS source_value FROM ' . qi($table) .
            ' WHERE ' . qi($name) . " REGEXP '(Ã|Â|Æ|Ä|áº|á»)'";
        if ($limitPerColumn > 0) {
            $sql .= ' LIMIT ' . $limitPerColumn;
        }

        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $source = (string) ($row['source_value'] ?? '');
            $fixed = Encoding::repairMojibake($source);
            if ($fixed === $source) {
                continue;
            }

            $changes[] = [
                'table' => $table,
                'column' => $name,
                'pk' => $pk,
                'pkValue' => (string) $row['pk_value'],
                'source' => $source,
                'fixed' => $fixed,
            ];
        }
    }
    return $changes;
}

function textColumns(PDO $pdo): array
{
    return $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext')
         ORDER BY TABLE_NAME, ORDINAL_POSITION"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function primaryKey(PDO $pdo, string $table): ?string
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND CONSTRAINT_NAME = 'PRIMARY'
         ORDER BY ORDINAL_POSITION
         LIMIT 1"
    );
    $stmt->execute(['table' => $table]);
    $column = $stmt->fetchColumn();
    return $cache[$table] = $column ? (string) $column : null;
}

function writeBackup(array $changes): string
{
    $dir = BASE_PATH . '/storage/encoding-backups';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create encoding backup directory');
    }

    $path = $dir . '/encoding-backup-' . date('Ymd-His') . '.json';
    file_put_contents($path, json_encode([
        'generatedAt' => date('c'),
        'changes' => $changes,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    return $path;
}

function summarizeChanges(array $changes): array
{
    $summary = [];
    foreach ($changes as $change) {
        $key = $change['table'] . '.' . $change['column'];
        $summary[$key] = ($summary[$key] ?? 0) + 1;
    }
    ksort($summary);
    return $summary;
}

function qi(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}
