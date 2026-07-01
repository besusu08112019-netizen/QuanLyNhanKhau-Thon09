<?php

declare(strict_types=1);

// Temporary protected runner for Sprint 13 hosting migration.
// Delete this file after migration verification.

$secret = 'sprint13-20260701-profile-migration';
$provided = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_MIGRATION_KEY'] ?? '');
if (!hash_equals($secret, $provided)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Database;

Autoloader::register();
header('Content-Type: application/json; charset=utf-8');

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0;
}

function indexExists(PDO $db, string $table, string $index): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name');
    $stmt->execute(['table' => $table, 'index_name' => $index]);
    return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0;
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0;
}

function constraintExists(PDO $db, string $constraint): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :constraint_name');
    $stmt->execute(['constraint_name' => $constraint]);
    return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) > 0;
}

$report = [
    'started_at' => date(DATE_ATOM),
    'steps' => [],
    'verification' => [],
];

try {
    $db = Database::pdo();
    $db->exec('SET NAMES utf8mb4');

    $enum = "ENUM('PHOTO','DOCUMENT','SCAN','WORD','EXCEL','IMAGE','VIDEO','LOGO','BACKGROUND','OTHER') NOT NULL DEFAULT 'OTHER'";
    $db->exec("ALTER TABLE `file_attachments` MODIFY `file_type` $enum");
    $report['steps'][] = 'Updated file_attachments.file_type enum';

    if (!columnExists($db, 'file_attachments', 'description')) {
        $db->exec('ALTER TABLE `file_attachments` ADD COLUMN `description` VARCHAR(500) NULL AFTER `file_size`');
        $report['steps'][] = 'Added file_attachments.description';
    } else {
        $report['steps'][] = 'file_attachments.description already exists';
    }

    if (!columnExists($db, 'file_attachments', 'profile_section')) {
        $db->exec('ALTER TABLE `file_attachments` ADD COLUMN `profile_section` VARCHAR(80) NULL AFTER `description`');
        $report['steps'][] = 'Added file_attachments.profile_section';
    } else {
        $report['steps'][] = 'file_attachments.profile_section already exists';
    }

    if (!indexExists($db, 'file_attachments', 'idx_file_attachments_profile_section')) {
        $db->exec('CREATE INDEX `idx_file_attachments_profile_section` ON `file_attachments` (`module`, `entity_id`, `profile_section`)');
        $report['steps'][] = 'Created idx_file_attachments_profile_section';
    } else {
        $report['steps'][] = 'idx_file_attachments_profile_section already exists';
    }

    if (!tableExists($db, 'profile_notes')) {
        $db->exec("CREATE TABLE `profile_notes` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `module` ENUM('household','citizen') NOT NULL,
          `entity_id` BIGINT UNSIGNED NOT NULL,
          `section` VARCHAR(80) NOT NULL DEFAULT 'general',
          `title` VARCHAR(255) NOT NULL,
          `content` LONGTEXT NULL,
          `status` ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `created_by` BIGINT UNSIGNED NULL,
          `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
          `updated_by` BIGINT UNSIGNED NULL,
          `deleted_at` DATETIME NULL,
          `deleted_by` BIGINT UNSIGNED NULL,
          PRIMARY KEY (`id`),
          KEY `idx_profile_notes_entity` (`module`, `entity_id`, `section`, `status`),
          KEY `idx_profile_notes_created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $report['steps'][] = 'Created profile_notes';
    } else {
        $report['steps'][] = 'profile_notes already exists';
    }

    $foreignKeys = [
        'fk_profile_notes_created_by' => ['created_by'],
        'fk_profile_notes_updated_by' => ['updated_by'],
        'fk_profile_notes_deleted_by' => ['deleted_by'],
    ];
    foreach ($foreignKeys as $constraint => [$column]) {
        if (!constraintExists($db, $constraint)) {
            $db->exec("ALTER TABLE `profile_notes` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `users` (`id`) ON DELETE SET NULL");
            $report['steps'][] = "Created $constraint";
        } else {
            $report['steps'][] = "$constraint already exists";
        }
    }

    $stmt = $db->prepare('INSERT INTO `permissions` (`role`, `module`, `action`, `allowed`) VALUES (:role, :module, :action, 1) ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`)');
    $permissions = [
        ['SUPER_ADMIN','profile','read'], ['SUPER_ADMIN','profile','create'], ['SUPER_ADMIN','profile','update'], ['SUPER_ADMIN','profile','delete'],
        ['ADMIN','profile','read'], ['ADMIN','profile','create'], ['ADMIN','profile','update'], ['ADMIN','profile','delete'],
        ['OFFICER','profile','read'], ['OFFICER','profile','create'], ['OFFICER','profile','update'],
        ['COLLABORATOR','profile','read'], ['COLLABORATOR','profile','create'], ['COLLABORATOR','profile','update'],
        ['VIEWER','profile','read'],
    ];
    foreach ($permissions as [$role, $module, $action]) {
        $stmt->execute(['role' => $role, 'module' => $module, 'action' => $action]);
    }
    $report['steps'][] = 'Upserted profile permissions';

    $report['verification'] = [
        'profile_notes_table' => tableExists($db, 'profile_notes'),
        'file_description_column' => columnExists($db, 'file_attachments', 'description'),
        'file_profile_section_column' => columnExists($db, 'file_attachments', 'profile_section'),
        'file_profile_section_index' => indexExists($db, 'file_attachments', 'idx_file_attachments_profile_section'),
        'fk_created_by' => constraintExists($db, 'fk_profile_notes_created_by'),
        'fk_updated_by' => constraintExists($db, 'fk_profile_notes_updated_by'),
        'fk_deleted_by' => constraintExists($db, 'fk_profile_notes_deleted_by'),
        'households_count' => (int) $db->query("SELECT COUNT(*) FROM households WHERE status <> 'DELETED'")->fetchColumn(),
        'citizens_count' => (int) $db->query("SELECT COUNT(*) FROM citizens WHERE status <> 'DELETED'")->fetchColumn(),
    ];

    $report['ok'] = !in_array(false, $report['verification'], true);
    $report['finished_at'] = date(DATE_ATOM);
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    $report['ok'] = false;
    $report['error'] = [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
    ];
    $report['finished_at'] = date(DATE_ATOM);
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
