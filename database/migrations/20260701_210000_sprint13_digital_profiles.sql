-- Sprint 13 - Digital profile foundation.
-- Adds safe metadata for electronic household/citizen files and profile notes.

SET NAMES utf8mb4;

ALTER TABLE `file_attachments`
  MODIFY `file_type` ENUM('PHOTO','DOCUMENT','SCAN','WORD','EXCEL','IMAGE','VIDEO','LOGO','BACKGROUND','OTHER') NOT NULL DEFAULT 'OTHER',
  ADD COLUMN IF NOT EXISTS `description` VARCHAR(500) NULL AFTER `file_size`,
  ADD COLUMN IF NOT EXISTS `profile_section` VARCHAR(80) NULL AFTER `description`;

CREATE INDEX IF NOT EXISTS `idx_file_attachments_profile_section`
  ON `file_attachments` (`module`, `entity_id`, `profile_section`);

CREATE TABLE IF NOT EXISTS `profile_notes` (
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
  KEY `idx_profile_notes_created_by` (`created_by`),
  CONSTRAINT `fk_profile_notes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_profile_notes_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_profile_notes_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`role`, `module`, `action`, `allowed`) VALUES
('SUPER_ADMIN','profile','read',1),('SUPER_ADMIN','profile','create',1),('SUPER_ADMIN','profile','update',1),('SUPER_ADMIN','profile','delete',1),
('ADMIN','profile','read',1),('ADMIN','profile','create',1),('ADMIN','profile','update',1),('ADMIN','profile','delete',1),
('OFFICER','profile','read',1),('OFFICER','profile','create',1),('OFFICER','profile','update',1),
('COLLABORATOR','profile','read',1),('COLLABORATOR','profile','create',1),('COLLABORATOR','profile','update',1),
('VIEWER','profile','read',1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);
