-- Admin Panel production migration
-- Run after database/database.sql on existing installations.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE `users`
  MODIFY `role` ENUM('SUPER_ADMIN','ADMIN','OFFICER','COLLABORATOR','VIEWER','DATA_ENTRY','NO_DELETE','NO_EXPORT') NOT NULL DEFAULT 'VIEWER';

ALTER TABLE `permissions`
  MODIFY `role` ENUM('SUPER_ADMIN','ADMIN','OFFICER','COLLABORATOR','VIEWER','DATA_ENTRY','NO_DELETE','NO_EXPORT') NOT NULL;

CREATE TABLE IF NOT EXISTS `file_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(50) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `file_type` ENUM('PHOTO','DOCUMENT','LOGO','BACKGROUND','OTHER') NOT NULL DEFAULT 'OTHER',
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_file_attachments_entity` (`module`, `entity_id`),
  KEY `idx_file_attachments_type` (`file_type`),
  CONSTRAINT `fk_file_attachments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_file_attachments_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('logoUrl', ''),
('backgroundUrl', ''),
('communeName', 'xã Hồng Phong'),
('phone', ''),
('email', ''),
('address', 'Thôn 09, xã Hồng Phong')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `permissions` (`role`, `module`, `action`, `allowed`) VALUES
('COLLABORATOR','dashboard','read',1),('COLLABORATOR','household','read',1),('COLLABORATOR','household','create',1),('COLLABORATOR','household','update',1),('COLLABORATOR','citizen','read',1),('COLLABORATOR','citizen','create',1),('COLLABORATOR','citizen','update',1),('COLLABORATOR','movement','read',1),('COLLABORATOR','movement','create',1),('COLLABORATOR','import','read',1),('COLLABORATOR','import','create',1),
('DATA_ENTRY','dashboard','read',1),('DATA_ENTRY','household','read',1),('DATA_ENTRY','household','create',1),('DATA_ENTRY','household','update',1),('DATA_ENTRY','citizen','read',1),('DATA_ENTRY','citizen','create',1),('DATA_ENTRY','citizen','update',1),('DATA_ENTRY','movement','read',1),('DATA_ENTRY','movement','create',1),('DATA_ENTRY','movement','update',1),('DATA_ENTRY','import','read',1),('DATA_ENTRY','import','create',1),
('NO_DELETE','dashboard','read',1),('NO_DELETE','household','read',1),('NO_DELETE','household','create',1),('NO_DELETE','household','update',1),('NO_DELETE','citizen','read',1),('NO_DELETE','citizen','create',1),('NO_DELETE','citizen','update',1),('NO_DELETE','movement','read',1),('NO_DELETE','movement','create',1),('NO_DELETE','movement','update',1),('NO_DELETE','report','read',1),('NO_DELETE','report','export',1),
('NO_EXPORT','dashboard','read',1),('NO_EXPORT','household','read',1),('NO_EXPORT','household','create',1),('NO_EXPORT','household','update',1),('NO_EXPORT','household','delete',1),('NO_EXPORT','citizen','read',1),('NO_EXPORT','citizen','create',1),('NO_EXPORT','citizen','update',1),('NO_EXPORT','citizen','delete',1),('NO_EXPORT','movement','read',1),('NO_EXPORT','movement','create',1),('NO_EXPORT','movement','update',1),('NO_EXPORT','movement','delete',1),('NO_EXPORT','report','read',1),
('ADMIN','backup','export',1),('ADMIN','backup','restore',1),('ADMIN','export','export',1),('ADMIN','print','print',1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

SET FOREIGN_KEY_CHECKS=1;
