-- Phase: Environment Configuration & Multi-Tenant Foundation
-- Adds host-resolved villages and village_id columns for tenant scoping.

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS `villages` (
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
  `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_villages_code` (`code`),
  UNIQUE KEY `uq_villages_domain` (`domain`),
  UNIQUE KEY `uq_villages_subdomain` (`subdomain`),
  KEY `idx_villages_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `villages` (`code`, `name`, `unit_name`, `commune_name`, `status`)
VALUES ('default', 'Ten thon', 'Ten don vi', 'Ten xa', 'ACTIVE')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

SET @tenant_default_village_id = (
  SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1
);

DROP PROCEDURE IF EXISTS add_village_id_column;
DELIMITER //
CREATE PROCEDURE add_village_id_column(IN table_name_value VARCHAR(128))
BEGIN
  IF EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = table_name_value
  ) AND NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = table_name_value AND COLUMN_NAME = 'village_id'
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', REPLACE(table_name_value, '`', '``'), '` ADD COLUMN `village_id` BIGINT UNSIGNED NULL AFTER `id`');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT('UPDATE `', REPLACE(table_name_value, '`', '``'), '` SET `village_id` = @tenant_default_village_id WHERE `village_id` IS NULL');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    SET @sql = CONCAT('ALTER TABLE `', REPLACE(table_name_value, '`', '``'), '` MODIFY COLUMN `village_id` BIGINT UNSIGNED NOT NULL');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;

  IF EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = table_name_value
  ) AND NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = table_name_value AND INDEX_NAME = CONCAT('idx_', table_name_value, '_village')
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', REPLACE(table_name_value, '`', '``'), '` ADD KEY `idx_', REPLACE(table_name_value, '`', ''), '_village` (`village_id`)');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END//
DELIMITER ;

CALL add_village_id_column('users');
CALL add_village_id_column('households');
CALL add_village_id_column('citizens');
CALL add_village_id_column('movements');
CALL add_village_id_column('audit_logs');
CALL add_village_id_column('backups');
CALL add_village_id_column('import_batches');
CALL add_village_id_column('export_files');
CALL add_village_id_column('file_attachments');
CALL add_village_id_column('houses');
CALL add_village_id_column('household_business');
CALL add_village_id_column('household_business_files');
CALL add_village_id_column('agri_stakeholders');
CALL add_village_id_column('agri_land_parcels');
CALL add_village_id_column('agri_production_plots');
CALL add_village_id_column('agri_crop_seasons');
CALL add_village_id_column('livestock');
CALL add_village_id_column('vehicles');
CALL add_village_id_column('public_assets');
CALL add_village_id_column('public_asset_files');
CALL add_village_id_column('public_asset_inventory');
CALL add_village_id_column('public_asset_maintenance_schedules');
CALL add_village_id_column('complaints');
CALL add_village_id_column('complaint_attachments');
CALL add_village_id_column('calendar_events');
CALL add_village_id_column('calendar_event_attachments');
CALL add_village_id_column('work_tasks');
CALL add_village_id_column('work_task_attachments');
CALL add_village_id_column('photo_gallery');
CALL add_village_id_column('village_documents');
CALL add_village_id_column('village_document_attachments');
CALL add_village_id_column('notifications');
CALL add_village_id_column('finance_transactions');
CALL add_village_id_column('household_contributions');
CALL add_village_id_column('profile_notes');

DROP PROCEDURE IF EXISTS add_village_id_column;

SET @settings_has_village_id = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'village_id'
);
SET @settings_sql = IF(@settings_has_village_id = 0, 'ALTER TABLE `settings` ADD COLUMN `village_id` BIGINT UNSIGNED NULL FIRST', 'SELECT 1');
PREPARE stmt FROM @settings_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `settings` SET `village_id` = @tenant_default_village_id WHERE `village_id` IS NULL;
ALTER TABLE `settings` MODIFY COLUMN `village_id` BIGINT UNSIGNED NOT NULL;

SET @settings_primary_is_old = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'setting_key' AND SEQ_IN_INDEX = 1
);
SET @settings_sql = IF(@settings_primary_is_old = 1, 'ALTER TABLE `settings` DROP PRIMARY KEY, ADD PRIMARY KEY (`village_id`, `setting_key`)', 'SELECT 1');
PREPARE stmt FROM @settings_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
