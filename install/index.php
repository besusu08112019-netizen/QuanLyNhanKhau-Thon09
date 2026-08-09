<?php
declare(strict_types=1);

$basePath = dirname(__DIR__);
$installerHost = installer_current_host();
$lockDir = $basePath . '/storage/install-locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0755, true);
}
$globalLockFile = $basePath . '/installed.lock';
$lockFile = installer_lock_file($basePath, $installerHost);
$defaultStoragePath = $basePath . '/storage/' . $installerHost;
$defaultUploadPath = $basePath . '/uploads/' . $installerHost;
$defaultBackupPath = $basePath . '/backups/' . $installerHost;
$installerSessionPath = $basePath . '/storage/install-sessions';
if (!is_dir($installerSessionPath)) {
    @mkdir($installerSessionPath, 0755, true);
}
if (is_dir($installerSessionPath) && is_writable($installerSessionPath)) {
    session_save_path($installerSessionPath);
}

session_start();

if (is_file($globalLockFile) || is_file($lockFile)) {
    header('Location: ../', true, 302);
    exit;
}

$state = $_SESSION['installer'] ?? [
    'system' => [],
    'db' => [],
    'schema_imported' => false,
    'seed_imported' => false,
    'db_tested' => false,
    'env_written' => false,
    'admin_created' => false,
];
$messages = [];
$errors = [];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect_step(int $step): never
{
    header('Location: ?step=' . $step, true, 303);
    exit;
}

function field(array $data, string $key, string $default = ''): string
{
    return (string)($data[$key] ?? $default);
}

function installer_current_host(): string
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $host = preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
    return $host !== '' ? $host : 'localhost';
}

function installer_env_file(string $basePath, string $domain): string
{
    $host = strtolower(trim($domain));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $host = preg_replace('/[^a-z0-9.-]/', '', $host) ?? '';
    return $host !== '' ? $basePath . '/.env.' . $host : $basePath . '/.env';
}

function installer_lock_file(string $basePath, string $host): string
{
    $host = preg_replace('/[^a-z0-9.-]/', '', strtolower(trim($host))) ?: 'localhost';
    return $basePath . '/storage/install-locks/' . $host . '.installed.lock';
}

function env_quote(string $value): string
{
    if ($value === '' || preg_match('/\s|#|"|=/', $value)) {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
    return $value;
}

function install_pdo(array $db): PDO
{
    $host = trim((string)($db['host'] ?? 'localhost'));
    $port = (int)($db['port'] ?? 3306);
    $name = trim((string)($db['database'] ?? ''));
    $user = trim((string)($db['username'] ?? ''));
    $pass = (string)($db['password'] ?? '');
    $charset = trim((string)($db['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
    $socket = trim((string)($db['socket'] ?? ''));

    if ($name === '' || $user === '') {
        throw new RuntimeException('Vui lòng nhập database name và database user.');
    }

    $dsn = $socket !== ''
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $name, $charset)
        : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ]);
}

function split_sql(string $sql): array
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
                $buffer .= $char;
            }
            continue;
        }

        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }

        if ($quote === null && $char === '-' && $next === '-' && ($i + 2 >= $length || preg_match('/\s/', $sql[$i + 2]) === 1)) {
            $lineComment = true;
            $i++;
            continue;
        }
        if ($quote === null && $char === '#') {
            $lineComment = true;
            continue;
        }
        if ($quote === null && $char === '/' && $next === '*') {
            $blockComment = true;
            $i++;
            continue;
        }

        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $i + 1 < $length) {
                $buffer .= $sql[++$i];
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }
    return $statements;
}

function execute_sql_file(PDO $pdo, string $path): int
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Không đọc được file SQL: ' . basename($path));
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Không đọc được file SQL: ' . basename($path));
    }
    $count = 0;
    foreach (split_sql($sql) as $statement) {
        $pdo->exec($statement);
        $count++;
    }
    return $count;
}

function install_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_auth_tables(PDO $pdo): void
{
    if (!install_table_exists($pdo, 'villages')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS `villages` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(50) NOT NULL,
          `name` VARCHAR(190) NOT NULL,
          `unit_name` VARCHAR(190) NULL,
          `commune_name` VARCHAR(190) NULL,
          `domain` VARCHAR(190) NULL,
          `subdomain` VARCHAR(190) NULL,
          `logo_url` VARCHAR(500) NULL,
          `theme_color` VARCHAR(20) NULL,
          `address` VARCHAR(500) NULL,
          `phone` VARCHAR(50) NULL,
          `email` VARCHAR(190) NULL,
          `status` ENUM("ACTIVE","INACTIVE") NOT NULL DEFAULT "ACTIVE",
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_villages_code_install` (`code`),
          UNIQUE KEY `uq_villages_domain_install` (`domain`),
          UNIQUE KEY `uq_villages_subdomain_install` (`subdomain`),
          KEY `idx_villages_status_install` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    if (!install_table_exists($pdo, 'users')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS `users` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `village_id` BIGINT UNSIGNED NOT NULL,
          `email` VARCHAR(190) NOT NULL,
          `display_name` VARCHAR(190) NOT NULL,
          `password_hash` VARCHAR(255) NULL,
          `role` ENUM("SUPER_ADMIN","ADMIN","OFFICER","VIEWER") NOT NULL DEFAULT "VIEWER",
          `status` ENUM("ACTIVE","INACTIVE","DELETED") NOT NULL DEFAULT "ACTIVE",
          `last_login_at` DATETIME NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `created_by` BIGINT UNSIGNED NULL,
          `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          `updated_by` BIGINT UNSIGNED NULL,
          `deleted_at` DATETIME NULL,
          `deleted_by` BIGINT UNSIGNED NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_users_village_email_install` (`village_id`, `email`),
          KEY `idx_users_role_install` (`role`),
          KEY `idx_users_status_install` (`status`),
          KEY `idx_users_village_install` (`village_id`),
          CONSTRAINT `fk_users_village_install` FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    if (!install_table_exists($pdo, 'user_sessions')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS `user_sessions` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `village_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
          `user_id` BIGINT UNSIGNED NOT NULL,
          `token_hash` CHAR(64) NOT NULL,
          `ip_address` VARCHAR(45) NULL,
          `user_agent` VARCHAR(255) NULL,
          `expires_at` DATETIME NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `revoked_at` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_user_sessions_token_hash_install` (`token_hash`),
          KEY `idx_user_sessions_user_install` (`user_id`),
          KEY `idx_user_sessions_expires_install` (`expires_at`),
          KEY `idx_user_sessions_village_install` (`village_id`),
          CONSTRAINT `fk_user_sessions_user_install` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    foreach (['villages', 'users', 'user_sessions'] as $table) {
        if (!install_table_exists($pdo, $table)) {
            throw new RuntimeException('Missing required authentication table after schema import: ' . $table);
        }
    }
}

function write_env_file(string $basePath, array $system, array $db): string
{
    $appUrl = trim((string)$system['app_url']);
    $storagePath = trim((string)($system['storage_path'] ?? ''));
    if ($storagePath === '') {
        $storagePath = $basePath . '/storage/' . installer_current_host();
    }
    $uploadPath = trim((string)($system['upload_path'] ?? ''));
    if ($uploadPath === '') {
        $uploadPath = $basePath . '/uploads/' . installer_current_host();
    }
    $backupPath = trim((string)($system['backup_path'] ?? ''));
    if ($backupPath === '') {
        $backupPath = $basePath . '/backups/' . installer_current_host();
    }

    $lines = [
        'APP_NAME=' . env_quote((string)$system['system_name']),
        'APP_URL=' . env_quote($appUrl),
        'APP_KEY=' . env_quote(bin2hex(random_bytes(32))),
        'APP_TIMEZONE=Asia/Ho_Chi_Minh',
        'APP_DEBUG=false',
        'APP_VERSION=v2.0',
        'SESSION_TTL_SECONDS=21600',
        'UPLOAD_PATH=' . env_quote($uploadPath),
        'STORAGE_PATH=' . env_quote($storagePath),
        'CACHE_PATH=' . env_quote($storagePath . '/cache'),
        'LOGS_PATH=' . env_quote($storagePath . '/logs'),
        'BACKUP_PATH=' . env_quote($backupPath),
        'TEMP_PATH=' . env_quote($storagePath . '/temp'),
        'SESSION_PATH=' . env_quote($storagePath . '/sessions'),
        '',
        'APP_HOST=' . env_quote((string)$system['domain']),
        'TENANT_DEFAULT_VILLAGE_CODE=' . env_quote((string)$system['village_code']),
        'TENANT_UNIT_NAME=' . env_quote((string)$system['unit_name']),
        'TENANT_HAMLET_NAME=' . env_quote((string)$system['village_name']),
        'TENANT_COMMUNE_NAME=' . env_quote((string)$system['commune_name']),
        'TENANT_ADDRESS=' . env_quote((string)$system['address']),
        'TENANT_PHONE=' . env_quote((string)$system['phone']),
        'TENANT_EMAIL=' . env_quote((string)$system['email']),
        'TENANT_WEBSITE=' . env_quote($appUrl),
        'TENANT_LOGO_URL=' . env_quote((string)$system['logo_url']),
        'TENANT_THEME_COLOR=' . env_quote((string)$system['theme_color']),
        'TENANT_BACKGROUND_COLOR="#eef3f8"',
        '',
        'DB_HOST=' . env_quote((string)$db['host']),
        'DB_PORT=' . (int)$db['port'],
        'DB_DATABASE=' . env_quote((string)$db['database']),
        'DB_USERNAME=' . env_quote((string)$db['username']),
        'DB_PASSWORD=' . env_quote((string)$db['password']),
        'DB_CHARSET=' . env_quote((string)$db['charset']),
    ];
    if (trim((string)($db['socket'] ?? '')) !== '') {
        $lines[] = 'DB_SOCKET=' . env_quote((string)$db['socket']);
    }

    foreach ([$storagePath, $storagePath . '/cache', $storagePath . '/logs', $storagePath . '/temp', $storagePath . '/sessions', $backupPath, $uploadPath] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new RuntimeException('Không tạo được thư mục: ' . $dir);
        }
    }

    $target = installer_env_file($basePath, (string)$system['domain']);
    if (is_file($target) && !is_writable($target)) {
        throw new RuntimeException('File cấu hình hiện tại không có quyền ghi: ' . basename($target));
    }
    if (!@file_put_contents($target, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX)) {
        throw new RuntimeException('Không ghi được file cấu hình. Vui lòng kiểm tra quyền thư mục.');
    }
    return $target;
}

function update_default_village(PDO $pdo, array $system): int
{
    $code = trim((string)$system['village_code']);
    $stmt = $pdo->prepare(
        'UPDATE villages
         SET code=:code, name=:name, unit_name=:unit_name, commune_name=:commune_name,
             domain=:domain, subdomain=:domain, logo_url=:logo_url, theme_color=:theme_color,
             address=:address, phone=:phone, email=:email, status="ACTIVE"
         WHERE code="default" OR id=(SELECT id FROM (SELECT id FROM villages ORDER BY id ASC LIMIT 1) v)'
    );
    $stmt->execute([
        'code' => $code,
        'name' => trim((string)$system['village_name']),
        'unit_name' => trim((string)$system['unit_name']),
        'commune_name' => trim((string)$system['commune_name']),
        'domain' => trim((string)$system['domain']),
        'logo_url' => trim((string)$system['logo_url']),
        'theme_color' => trim((string)$system['theme_color']) ?: '#0b6b3a',
        'address' => trim((string)$system['address']),
        'phone' => trim((string)$system['phone']),
        'email' => trim((string)$system['email']),
    ]);
    return (int)$pdo->query('SELECT id FROM villages WHERE code=' . $pdo->quote($code) . ' LIMIT 1')->fetchColumn();
}

function create_admin(PDO $pdo, int $villageId, array $admin): void
{
    $email = strtolower(trim((string)$admin['email']));
    $name = trim((string)$admin['name']);
    $password = (string)$admin['password'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Email quản trị không hợp lệ.');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Mật khẩu quản trị tối thiểu 8 ký tự.');
    }
    $existing = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE village_id=' . (int)$villageId)->fetchColumn();
    if ($existing > 0) {
        throw new RuntimeException('Database đã có tài khoản người dùng cho tenant này.');
    }
    $stmt = $pdo->prepare('INSERT INTO users (village_id,email,display_name,password_hash,role,status) VALUES (:village_id,:email,:name,:hash,"SUPER_ADMIN","ACTIVE")');
    $stmt->execute([
        'village_id' => $villageId,
        'email' => $email,
        'name' => $name !== '' ? $name : $email,
        'hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

function environment_checks(string $basePath): array
{
    return [
        ['PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>=')],
        ['PDO MySQL extension', extension_loaded('pdo_mysql')],
        ['schema.sql readable', is_readable($basePath . '/database/schema.sql')],
        ['seed.sql readable', is_readable($basePath . '/database/seed.sql')],
        ['Root directory writable for host .env file', is_writable($basePath)],
        ['Install lock directory writable or creatable', is_dir($basePath . '/storage/install-locks') ? is_writable($basePath . '/storage/install-locks') : is_writable($basePath)],
        ['uploads directory writable or creatable', is_dir($basePath . '/uploads') ? is_writable($basePath . '/uploads') : is_writable($basePath)],
        ['storage directory writable or creatable', is_dir($basePath . '/storage') ? is_writable($basePath . '/storage') : is_writable($basePath)],
        ['backups directory writable or creatable', is_dir($basePath . '/backups') ? is_writable($basePath . '/backups') : is_writable($basePath)],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_system') {
            $required = ['system_name', 'village_code', 'village_name', 'unit_name', 'commune_name', 'domain', 'app_url'];
            foreach ($required as $key) {
                if (trim((string)($_POST[$key] ?? '')) === '') {
                    throw new RuntimeException('Vui lòng nhập đủ thông tin hệ thống bắt buộc.');
                }
            }
            $state['system'] = [
                'system_name' => trim((string)$_POST['system_name']),
                'village_code' => preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string)$_POST['village_code'])) ?: 'default',
                'village_name' => trim((string)$_POST['village_name']),
                'unit_name' => trim((string)$_POST['unit_name']),
                'commune_name' => trim((string)$_POST['commune_name']),
                'domain' => strtolower(trim((string)$_POST['domain'])),
                'app_url' => trim((string)$_POST['app_url']),
                'address' => trim((string)($_POST['address'] ?? '')),
                'phone' => trim((string)($_POST['phone'] ?? '')),
                'email' => trim((string)($_POST['email'] ?? '')),
                'logo_url' => trim((string)($_POST['logo_url'] ?? '')),
                'theme_color' => trim((string)($_POST['theme_color'] ?? '#0b6b3a')) ?: '#0b6b3a',
                'storage_path' => trim((string)($_POST['storage_path'] ?? '')),
                'upload_path' => trim((string)($_POST['upload_path'] ?? '')),
                'backup_path' => trim((string)($_POST['backup_path'] ?? '')),
            ];
            $_SESSION['installer'] = $state;
            redirect_step(3);
        }

        if ($action === 'save_db' || $action === 'test_db') {
            $state['db'] = [
                'host' => trim((string)($_POST['host'] ?? 'localhost')),
                'port' => (string)((int)($_POST['port'] ?? 3306) ?: 3306),
                'socket' => trim((string)($_POST['socket'] ?? '')),
                'database' => trim((string)($_POST['database'] ?? '')),
                'username' => trim((string)($_POST['username'] ?? '')),
                'password' => (string)($_POST['password'] ?? ''),
                'charset' => trim((string)($_POST['charset'] ?? 'utf8mb4')) ?: 'utf8mb4',
            ];
            $state['db_tested'] = false;
            $_SESSION['installer'] = $state;
            if ($action === 'test_db') {
                install_pdo($state['db'])->query('SELECT 1');
                $state['db_tested'] = true;
                $_SESSION['installer'] = $state;
                $messages[] = 'Kết nối database thành công.';
            } else {
                redirect_step(4);
            }
        }

        if ($action === 'import_schema') {
            if (empty($state['db_tested'])) {
                throw new RuntimeException('Vui lòng kiểm tra kết nối database trước khi import schema.');
            }
            $pdo = install_pdo($state['db']);
            $count = execute_sql_file($pdo, $basePath . '/database/schema.sql');
            ensure_auth_tables($pdo);
            $state['schema_imported'] = true;
            $_SESSION['installer'] = $state;
            $messages[] = 'Import schema.sql thành công: ' . $count . ' statements.';
        }

        if ($action === 'import_seed') {
            if (empty($state['schema_imported'])) {
                throw new RuntimeException('Vui lòng import schema.sql trước khi import seed.sql.');
            }
            $pdo = install_pdo($state['db']);
            $count = execute_sql_file($pdo, $basePath . '/database/seed.sql');
            $state['seed_imported'] = true;
            $state['village_id'] = update_default_village($pdo, $state['system']);
            $_SESSION['installer'] = $state;
            $messages[] = 'Import seed.sql và cập nhật tenant thành công: ' . $count . ' statements.';
        }

        if ($action === 'write_env') {
            if (empty($state['seed_imported'])) {
                throw new RuntimeException('Vui lòng import seed.sql trước khi sinh .env.');
            }
            $target = write_env_file($basePath, $state['system'], $state['db']);
            $state['env_written'] = true;
            $state['env_file'] = basename($target);
            $_SESSION['installer'] = $state;
            $messages[] = 'Đã sinh file cấu hình: ' . basename($target);
        }

        if ($action === 'create_admin') {
            if (empty($state['env_written'])) {
                throw new RuntimeException('Vui lòng sinh file .env trước khi tạo admin.');
            }
            $pdo = install_pdo($state['db']);
            ensure_auth_tables($pdo);
            $villageId = (int)($state['village_id'] ?? 0);
            if ($villageId <= 0) {
                $villageId = update_default_village($pdo, $state['system']);
            }
            create_admin($pdo, $villageId, [
                'email' => $_POST['admin_email'] ?? '',
                'name' => $_POST['admin_name'] ?? '',
                'password' => $_POST['admin_password'] ?? '',
            ]);
            $state['admin_created'] = true;
            $_SESSION['installer'] = $state;
            $messages[] = 'Đã tạo tài khoản quản trị đầu tiên.';
        }

        if ($action === 'finish') {
            foreach (['schema_imported', 'seed_imported', 'env_written', 'admin_created'] as $key) {
                if (empty($state[$key])) {
                    throw new RuntimeException('Chưa hoàn tất đủ các bước cài đặt.');
                }
            }
            if (!@file_put_contents($lockFile, 'installed_at=' . date('c') . PHP_EOL . 'host=' . $installerHost . PHP_EOL, LOCK_EX)) {
                throw new RuntimeException('Không tạo được installed lock.');
            }
            unset($_SESSION['installer']);
            header('Location: ../', true, 303);
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$checks = environment_checks($basePath);
$step = max(1, min(9, (int)($_GET['step'] ?? 1)));
$allChecksPass = array_reduce($checks, fn(bool $carry, array $item): bool => $carry && (bool)$item[1], true);
$system = $state['system'];
$db = $state['db'];
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cài đặt hệ thống</title>
  <style>
    :root{--bg:#f4f7fb;--panel:#fff;--text:#17202a;--muted:#64748b;--line:#dbe3ee;--primary:#0b6b3a;--danger:#b42318}
    *{box-sizing:border-box} body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--text)}
    .wrap{max-width:1120px;margin:0 auto;padding:28px}.layout{display:grid;grid-template-columns:280px 1fr;gap:20px}
    .panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:20px}.steps{list-style:none;margin:0;padding:0}
    .steps li{padding:11px 12px;border-radius:6px;color:var(--muted);display:flex;gap:8px}.steps li.active{background:#e8f5ee;color:var(--primary);font-weight:700}
    h1{font-size:24px;margin:0 0 8px} h2{font-size:20px;margin:0 0 16px}.muted{color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{display:flex;flex-direction:column;gap:6px}
    label{font-weight:700;font-size:13px} input{border:1px solid var(--line);border-radius:6px;padding:10px;font-size:14px}
    .full{grid-column:1/-1}.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap}
    button,.btn{border:0;border-radius:6px;padding:10px 14px;background:var(--primary);color:#fff;font-weight:700;text-decoration:none;cursor:pointer}
    .btn.secondary,button.secondary{background:#334155}.btn.light{background:#e2e8f0;color:#0f172a}.status{display:flex;justify-content:space-between;border-bottom:1px solid var(--line);padding:10px 0}
    .ok{color:var(--primary);font-weight:700}.bad{color:var(--danger);font-weight:700}.alert{padding:12px;border-radius:6px;margin:12px 0}.alert.ok{background:#e8f5ee}.alert.bad{background:#fee4e2;color:var(--danger)}
    code{background:#eef2f7;padding:2px 5px;border-radius:4px}@media(max-width:800px){.layout{grid-template-columns:1fr}.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="wrap">
  <h1>Trình cài đặt hệ thống</h1>
  <p class="muted">Cài đặt mới từ source code hiện có, không dùng dữ liệu thật của bất kỳ thôn nào. Domain hiện tại: <code><?= h($installerHost) ?></code></p>
  <div class="layout">
    <aside class="panel">
      <ol class="steps">
        <?php foreach (['Kiểm tra môi trường','Thông tin hệ thống','Thông tin database','Kiểm tra kết nối','Import schema','Import seed','Sinh .env','Tạo admin','Hoàn tất'] as $i => $label): ?>
          <li class="<?= $step === $i + 1 ? 'active' : '' ?>"><span><?= $i + 1 ?>.</span><span><?= h($label) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </aside>
    <main class="panel">
      <?php foreach ($messages as $message): ?><div class="alert ok"><?= h($message) ?></div><?php endforeach; ?>
      <?php foreach ($errors as $error): ?><div class="alert bad"><?= h($error) ?></div><?php endforeach; ?>

      <?php if ($step === 1): ?>
        <h2>Kiểm tra môi trường</h2>
        <?php foreach ($checks as [$label, $ok]): ?><div class="status"><span><?= h($label) ?></span><span class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'PASS' : 'FAIL' ?></span></div><?php endforeach; ?>
        <div class="actions"><a class="btn <?= $allChecksPass ? '' : 'secondary' ?>" href="<?= $allChecksPass ? '?step=2' : '?step=1' ?>">Tiếp tục</a></div>
      <?php elseif ($step === 2): ?>
        <h2>Nhập thông tin hệ thống</h2>
        <form method="post" class="grid">
          <input type="hidden" name="action" value="save_system">
          <div class="field"><label>Tên hệ thống</label><input name="system_name" required value="<?= h(field($system, 'system_name', 'Hệ thống Quản lý Hành chính')) ?>"></div>
          <div class="field"><label>Mã thôn/tenant</label><input name="village_code" required value="<?= h(field($system, 'village_code', 'default')) ?>"></div>
          <div class="field"><label>Tên thôn</label><input name="village_name" required value="<?= h(field($system, 'village_name')) ?>"></div>
          <div class="field"><label>Tên đơn vị</label><input name="unit_name" required value="<?= h(field($system, 'unit_name')) ?>"></div>
          <div class="field"><label>Tên xã/phường</label><input name="commune_name" required value="<?= h(field($system, 'commune_name')) ?>"></div>
          <div class="field"><label>Domain/Subdomain</label><input name="domain" required value="<?= h(field($system, 'domain', $installerHost)) ?>"></div>
          <div class="field full"><label>APP URL</label><input name="app_url" required value="<?= h(field($system, 'app_url', ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''))) ?>"></div>
          <div class="field"><label>Địa chỉ</label><input name="address" value="<?= h(field($system, 'address')) ?>"></div>
          <div class="field"><label>Điện thoại</label><input name="phone" value="<?= h(field($system, 'phone')) ?>"></div>
          <div class="field"><label>Email</label><input name="email" value="<?= h(field($system, 'email')) ?>"></div>
          <div class="field"><label>Logo URL</label><input name="logo_url" value="<?= h(field($system, 'logo_url')) ?>"></div>
          <div class="field"><label>Màu giao diện</label><input name="theme_color" value="<?= h(field($system, 'theme_color', '#0b6b3a')) ?>"></div>
          <div class="field"><label>Storage path</label><input name="storage_path" value="<?= h(field($system, 'storage_path', $defaultStoragePath)) ?>"></div>
          <div class="field"><label>Upload path</label><input name="upload_path" value="<?= h(field($system, 'upload_path', $defaultUploadPath)) ?>"></div>
          <div class="field full"><label>Backup path</label><input name="backup_path" value="<?= h(field($system, 'backup_path', $defaultBackupPath)) ?>"></div>
          <div class="actions full"><a class="btn light" href="?step=1">Quay lại</a><button type="submit">Tiếp tục</button></div>
        </form>
      <?php elseif ($step === 3 || $step === 4): ?>
        <h2>Thông tin database</h2>
        <form method="post" class="grid">
          <input type="hidden" name="action" value="<?= $step === 4 ? 'test_db' : 'save_db' ?>">
          <div class="field"><label>Host</label><input name="host" required value="<?= h(field($db, 'host', 'localhost')) ?>"></div>
          <div class="field"><label>Port</label><input name="port" required value="<?= h(field($db, 'port', '3306')) ?>"></div>
          <div class="field"><label>Database name</label><input name="database" required value="<?= h(field($db, 'database')) ?>"></div>
          <div class="field"><label>Username</label><input name="username" required value="<?= h(field($db, 'username')) ?>"></div>
          <div class="field"><label>Password</label><input name="password" type="password" value="<?= h(field($db, 'password')) ?>"></div>
          <div class="field"><label>Charset</label><input name="charset" value="<?= h(field($db, 'charset', 'utf8mb4')) ?>"></div>
          <div class="field full"><label>Socket (nếu hosting yêu cầu)</label><input name="socket" value="<?= h(field($db, 'socket')) ?>"></div>
          <div class="actions full"><a class="btn light" href="?step=2">Quay lại</a><button name="next" value="1"><?= $step === 4 ? 'Kiểm tra kết nối' : 'Lưu database' ?></button><?php if ($step === 4 && !empty($state['db_tested'])): ?><a class="btn" href="?step=5">Tiếp tục import</a><?php endif; ?></div>
        </form>
      <?php elseif ($step === 5): ?>
        <h2>Import schema.sql</h2>
        <p>Import toàn bộ cấu trúc database và các object runtime cần thiết như view.</p>
        <form method="post"><input type="hidden" name="action" value="import_schema"><div class="actions"><a class="btn light" href="?step=4">Quay lại</a><button>Import schema.sql</button><?php if (!empty($state['schema_imported'])): ?><a class="btn" href="?step=6">Tiếp tục</a><?php endif; ?></div></form>
      <?php elseif ($step === 6): ?>
        <h2>Import seed.sql</h2>
        <p>Import dữ liệu mặc định: tenant placeholder, settings và permissions. Sau đó installer cập nhật tenant theo biểu mẫu.</p>
        <form method="post"><input type="hidden" name="action" value="import_seed"><div class="actions"><a class="btn light" href="?step=5">Quay lại</a><button>Import seed.sql</button><?php if (!empty($state['seed_imported'])): ?><a class="btn" href="?step=7">Tiếp tục</a><?php endif; ?></div></form>
      <?php elseif ($step === 7): ?>
        <h2>Sinh file .env</h2>
        <p>Installer sẽ tạo file cấu hình riêng cho domain hiện tại, ví dụ <code>.env.<?= h($installerHost) ?></code>, từ thông tin hệ thống và database đã nhập.</p>
        <form method="post"><input type="hidden" name="action" value="write_env"><div class="actions"><a class="btn light" href="?step=6">Quay lại</a><button>Sinh .env</button><?php if (!empty($state['env_written'])): ?><a class="btn" href="?step=8">Tiếp tục</a><?php endif; ?></div></form>
      <?php elseif ($step === 8): ?>
        <h2>Tạo tài khoản quản trị đầu tiên</h2>
        <form method="post" class="grid">
          <input type="hidden" name="action" value="create_admin">
          <div class="field"><label>Email admin</label><input name="admin_email" type="email" required></div>
          <div class="field"><label>Tên hiển thị</label><input name="admin_name" required></div>
          <div class="field full"><label>Mật khẩu</label><input name="admin_password" type="password" required minlength="8"></div>
          <div class="actions full"><a class="btn light" href="?step=7">Quay lại</a><button>Tạo admin</button><?php if (!empty($state['admin_created'])): ?><a class="btn" href="?step=9">Tiếp tục</a><?php endif; ?></div>
        </form>
      <?php else: ?>
        <h2>Hoàn tất cài đặt</h2>
        <p>Nhấn hoàn tất để tạo install lock riêng cho domain hiện tại. Sau bước này, đường dẫn <code>/install</code> của domain này sẽ tự khóa và chuyển về trang đăng nhập.</p>
        <form method="post"><input type="hidden" name="action" value="finish"><div class="actions"><a class="btn light" href="?step=8">Quay lại</a><button>Hoàn tất cài đặt</button></div></form>
      <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>
