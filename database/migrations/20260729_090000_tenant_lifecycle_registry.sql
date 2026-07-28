ALTER TABLE `villages`
  ADD COLUMN IF NOT EXISTS `database_charset` VARCHAR(50) NULL DEFAULT 'utf8mb4' AFTER `database_host`,
  ADD COLUMN IF NOT EXISTS `app_version` VARCHAR(50) NULL AFTER `version`,
  ADD COLUMN IF NOT EXISTS `build_version` VARCHAR(100) NULL AFTER `app_version`,
  ADD COLUMN IF NOT EXISTS `schema_version` VARCHAR(50) NULL AFTER `build_version`,
  ADD COLUMN IF NOT EXISTS `website_status` ENUM('ONLINE','OFFLINE','UNKNOWN','LOCKED') NOT NULL DEFAULT 'UNKNOWN' AFTER `connection_status`,
  ADD COLUMN IF NOT EXISTS `database_status` ENUM('CONNECTED','DISCONNECTED','UNKNOWN','LOCKED') NOT NULL DEFAULT 'UNKNOWN' AFTER `website_status`,
  ADD COLUMN IF NOT EXISTS `ssl_status` ENUM('VALID','INVALID','UNKNOWN','NOT_APPLICABLE') NOT NULL DEFAULT 'UNKNOWN' AFTER `database_status`,
  ADD COLUMN IF NOT EXISTS `storage_usage_bytes` BIGINT UNSIGNED NULL AFTER `ssl_status`,
  ADD COLUMN IF NOT EXISTS `last_website_checked_at` DATETIME NULL AFTER `last_checked_at`,
  ADD COLUMN IF NOT EXISTS `last_database_checked_at` DATETIME NULL AFTER `last_website_checked_at`,
  ADD COLUMN IF NOT EXISTS `last_backup_at` DATETIME NULL AFTER `last_database_checked_at`,
  ADD COLUMN IF NOT EXISTS `manager_name` VARCHAR(190) NULL AFTER `last_error`,
  ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `manager_name`;

UPDATE `villages`
SET
  `database_status` = CASE
    WHEN `status` <> 'ACTIVE' THEN 'LOCKED'
    WHEN `connection_status` IN ('CONNECTED','DISCONNECTED','UNKNOWN','LOCKED') THEN `connection_status`
    ELSE 'UNKNOWN'
  END,
  `website_status` = CASE
    WHEN `status` <> 'ACTIVE' THEN 'LOCKED'
    ELSE `website_status`
  END
WHERE `database_status` = 'UNKNOWN' OR `website_status` = 'UNKNOWN';
