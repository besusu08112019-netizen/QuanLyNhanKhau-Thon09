-- Sprint 1 - Digital profile file storage foundation.
-- Adds normalized file metadata while preserving existing file_attachments data.

SET NAMES utf8mb4;

ALTER TABLE `file_attachments`
  MODIFY `file_type` ENUM('PHOTO','DOCUMENT','SCAN','WORD','EXCEL','IMAGE','VIDEO','AUDIO','LOGO','BACKGROUND','OTHER') NOT NULL DEFAULT 'OTHER',
  ADD COLUMN IF NOT EXISTS `entity_type` ENUM('household','citizen','settings') NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(80) NULL AFTER `entity_id`,
  ADD COLUMN IF NOT EXISTS `file_name` VARCHAR(255) NULL AFTER `file_type`,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_by`,
  ADD COLUMN IF NOT EXISTS `updated_by` BIGINT UNSIGNED NULL AFTER `updated_at`;

UPDATE `file_attachments`
SET
  `entity_type` = CASE
    WHEN `entity_type` IS NOT NULL THEN `entity_type`
    WHEN `module` = 'citizen' THEN 'citizen'
    WHEN `module` = 'household' THEN 'household'
    WHEN `module` = 'settings' THEN 'settings'
    ELSE NULL
  END,
  `category` = COALESCE(`category`, `profile_section`, LOWER(`file_type`)),
  `file_name` = COALESCE(`file_name`, `original_name`),
  `updated_by` = COALESCE(`updated_by`, `created_by`)
WHERE `status` IN ('ACTIVE','DELETED');

CREATE INDEX IF NOT EXISTS `idx_file_attachments_entity_type`
  ON `file_attachments` (`entity_type`, `entity_id`, `category`, `status`);

CREATE INDEX IF NOT EXISTS `idx_file_attachments_created_at`
  ON `file_attachments` (`created_at`);