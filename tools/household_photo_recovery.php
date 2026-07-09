<?php

declare(strict_types=1);

/**
 * Audit and migrate household photos after restoring legacy storage backups.
 *
 * CLI:
 *   php tools/household_photo_recovery.php
 *   php tools/household_photo_recovery.php --migrate
 *
 * HTTP, for temporary production diagnostics only:
 *   /tools/household_photo_recovery.php?token=...&migrate=1
 * Set PHOTO_RECOVERY_TOKEN in .env to allow temporary HTTP execution. Delete
 * any manually uploaded temporary copy from production after recovery.
 */

define('BASE_PATH', dirname(__DIR__));

$isCli = PHP_SAPI === 'cli';
$options = $isCli ? getopt('', ['migrate', 'json']) : [];
$migrate = $isCli ? array_key_exists('migrate', $options) : (($_GET['migrate'] ?? '') === '1');
$json = $isCli ? array_key_exists('json', $options) : true;

if (!$isCli) {
    $expectedToken = getenv('PHOTO_RECOVERY_TOKEN') ?: '';
    if ($expectedToken === '' || !hash_equals($expectedToken, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Forbidden'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
}

$configFile = is_file(BASE_PATH . '/config/database.php')
    ? BASE_PATH . '/config/database.php'
    : BASE_PATH . '/config/database.example.php';
$config = require $configFile;

$dsn = !empty($config['socket'])
    ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $config['socket'], $config['database'], $config['charset'] ?? 'utf8mb4')
    : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], (int) ($config['port'] ?? 3306), $config['database'], $config['charset'] ?? 'utf8mb4');

$pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

function tableColumns(PDO $pdo, string $table): array
{
    $rows = $pdo->query('DESCRIBE `' . str_replace('`', '``', $table) . '`')->fetchAll();
    return array_values(array_map(static fn(array $row): string => (string) $row['Field'], $rows));
}

function hasColumn(array $columns, string $column): bool
{
    return in_array($column, $columns, true);
}

function normalizeStoredPath(?string $path): string
{
    $path = trim((string) $path);
    if (preg_match('#^https?://#i', $path)) {
        $parts = parse_url($path);
        $path = (string) ($parts['path'] ?? '');
    }
    $path = rawurldecode($path);
    $path = str_replace('\\', '/', $path);
    $base = str_replace('\\', '/', BASE_PATH);
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return ltrim($path, '/\\');
}

function fileCandidates(array $row): array
{
    $paths = [];
    foreach (['file_path', 'storage_path', 'relative_path', 'path', 'url'] as $field) {
        if (!empty($row[$field])) $paths[] = normalizeStoredPath((string) $row[$field]);
    }

    foreach (['stored_name', 'file_name', 'filename', 'original_name'] as $field) {
        if (empty($row[$field])) continue;
        $name = basename((string) $row[$field]);
        if ($name === '' || $name === '.') continue;
        $paths[] = 'storage/households/images/' . $name;
        $paths[] = 'uploads/households/images/' . $name;
    }

    $expanded = [];
    foreach (array_unique(array_filter($paths)) as $path) {
        $expanded[] = $path;
        foreach (['storage/', 'uploads/'] as $prefix) {
            if (!str_starts_with($path, $prefix)) $expanded[] = $prefix . $path;
        }
        if (str_starts_with($path, 'storage/')) {
            $expanded[] = 'uploads/' . substr($path, strlen('storage/'));
        }
        if (str_starts_with($path, 'uploads/')) {
            $expanded[] = 'storage/' . substr($path, strlen('uploads/'));
        }
    }

    return array_values(array_unique(array_filter($expanded)));
}

function findExistingPath(array $row): ?string
{
    foreach (fileCandidates($row) as $relative) {
        $path = BASE_PATH . '/' . ltrim($relative, '/\\');
        if (is_file($path)) return $path;
    }

    $basenames = [];
    foreach (['stored_name', 'file_name', 'filename', 'original_name', 'file_path'] as $field) {
        if (!empty($row[$field])) $basenames[] = basename((string) $row[$field]);
    }
    $basenames = array_values(array_unique(array_filter($basenames)));
    if ($basenames === []) return null;

    foreach (['storage', 'uploads'] as $root) {
        $base = BASE_PATH . '/' . $root;
        if (!is_dir($base)) continue;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getFilename(), $basenames, true)) {
                    $real = $file->getRealPath();
                    return $real !== false ? $real : null;
                }
            }
        } catch (Throwable) {
        }
    }

    return null;
}

function targetUploadPath(array $row, string $sourcePath): string
{
    $relative = normalizeStoredPath((string) ($row['file_path'] ?? ''));
    if (str_starts_with($relative, 'storage/')) {
        return 'uploads/' . substr($relative, strlen('storage/'));
    }
    if (str_starts_with($relative, 'uploads/')) {
        return $relative;
    }

    $date = isset($row['created_at']) ? strtotime((string) $row['created_at']) : false;
    $folderDate = $date ? date('Y/m', $date) : date('Y/m');
    $name = (string) ($row['stored_name'] ?? basename($sourcePath));
    if ($name === '') $name = basename($sourcePath);
    return 'uploads/households/images/' . $folderDate . '/' . basename($name);
}

$columns = tableColumns($pdo, 'file_attachments');
$where = [];
$params = [];

if (hasColumn($columns, 'status')) $where[] = "status = 'ACTIVE'";
if (hasColumn($columns, 'deleted_at')) $where[] = 'deleted_at IS NULL';

$moduleParts = [];
if (hasColumn($columns, 'module')) $moduleParts[] = "module IN ('household','households')";
if (hasColumn($columns, 'entity_type')) $moduleParts[] = "entity_type = 'household'";
if ($moduleParts !== []) $where[] = '(' . implode(' OR ', $moduleParts) . ')';

$photoParts = [];
if (hasColumn($columns, 'file_type')) $photoParts[] = "file_type IN ('PHOTO','IMAGE','image','image/jpeg','image/png')";
if (hasColumn($columns, 'mime_type')) $photoParts[] = "mime_type LIKE 'image/%'";
if (hasColumn($columns, 'profile_section')) $photoParts[] = "profile_section IN ('front_house','inside_house','yard','other','photo','image')";
if ($photoParts !== []) $where[] = '(' . implode(' OR ', $photoParts) . ')';

$sql = 'SELECT * FROM file_attachments';
if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= hasColumn($columns, 'id') ? ' ORDER BY id ASC' : '';

$rows = $pdo->query($sql)->fetchAll();
$report = [
    'mode' => $migrate ? 'audit+migrate' : 'audit',
    'base_path' => BASE_PATH,
    'config_file' => $configFile,
    'total_db_photos' => count($rows),
    'existing_files' => 0,
    'missing_files' => 0,
    'storage_references' => 0,
    'uploads_references' => 0,
    'migrated' => 0,
    'migration_errors' => 0,
    'missing' => [],
    'migrated_files' => [],
];

foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    $storedPath = normalizeStoredPath((string) ($row['file_path'] ?? ''));
    if (str_starts_with($storedPath, 'storage/')) $report['storage_references']++;
    if (str_starts_with($storedPath, 'uploads/')) $report['uploads_references']++;

    $existing = findExistingPath($row);
    if ($existing === null) {
        $report['missing_files']++;
        $report['missing'][] = [
            'id' => $id,
            'entity_id' => $row['entity_id'] ?? null,
            'file_path' => $row['file_path'] ?? null,
            'stored_name' => $row['stored_name'] ?? ($row['file_name'] ?? null),
            'original_name' => $row['original_name'] ?? null,
        ];
        continue;
    }

    $report['existing_files']++;
    if (!$migrate || !hasColumn($columns, 'file_path')) continue;

    $targetRelative = targetUploadPath($row, $existing);
    $targetAbsolute = BASE_PATH . '/' . $targetRelative;
    if (!is_dir(dirname($targetAbsolute)) && !mkdir(dirname($targetAbsolute), 0755, true) && !is_dir(dirname($targetAbsolute))) {
        $report['migration_errors']++;
        $report['migrated_files'][] = ['id' => $id, 'error' => 'Cannot create target directory', 'target' => $targetRelative];
        continue;
    }
    if (!is_file($targetAbsolute) && !copy($existing, $targetAbsolute)) {
        $report['migration_errors']++;
        $report['migrated_files'][] = ['id' => $id, 'error' => 'Cannot copy file', 'source' => $existing, 'target' => $targetRelative];
        continue;
    }
    if (($row['file_path'] ?? '') !== $targetRelative) {
        $stmt = $pdo->prepare('UPDATE file_attachments SET file_path = :file_path WHERE id = :id');
        $stmt->execute(['file_path' => $targetRelative, 'id' => $id]);
    }
    $report['migrated']++;
    $report['migrated_files'][] = ['id' => $id, 'source' => $existing, 'target' => $targetRelative];
}

if ($json) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

foreach ($report as $key => $value) {
    if (is_array($value)) continue;
    echo $key . ': ' . $value . PHP_EOL;
}

