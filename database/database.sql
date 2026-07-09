-- Quan Ly Nhan Khau Thon 09 - MySQL/MariaDB schema
-- Sprint 1: database design for PHP 8.2 migration

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS `quan_ly_nhan_khau_thon09`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `quan_ly_nhan_khau_thon09`;

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `display_name` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NULL,
  `role` ENUM('SUPER_ADMIN','ADMIN','OFFICER','VIEWER') NOT NULL DEFAULT 'VIEWER',
  `status` ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sessions_token_hash` (`token_hash`),
  KEY `idx_user_sessions_user` (`user_id`),
  KEY `idx_user_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `households` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `household_code` VARCHAR(50) NOT NULL,
  `head_citizen_id` BIGINT UNSIGNED NULL,
  `head_citizen_name` VARCHAR(190) NULL,
  `address` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `area_code` VARCHAR(50) NULL,
  `member_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `meritorious_family` TINYINT(1) NOT NULL DEFAULT 0,
  `poor_household` TINYINT(1) NOT NULL DEFAULT 0,
  `near_poor_household` TINYINT(1) NOT NULL DEFAULT 0,
  `disabled_household` TINYINT(1) NOT NULL DEFAULT 0,
  `note` TEXT NULL,
  `status` ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_households_code` (`household_code`),
  KEY `idx_households_head_citizen` (`head_citizen_id`),
  KEY `idx_households_head_name` (`head_citizen_name`),
  KEY `idx_households_address` (`address`),
  KEY `idx_households_area` (`area_code`),
  KEY `idx_households_status` (`status`),
  KEY `idx_households_policy` (`meritorious_family`, `poor_household`, `near_poor_household`, `disabled_household`),
  CONSTRAINT `fk_households_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_households_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_households_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citizens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `citizen_code` VARCHAR(50) NOT NULL,
  `household_id` BIGINT UNSIGNED NOT NULL,
  `full_name` VARCHAR(190) NOT NULL,
  `gender` ENUM('Nam','Nữ','Khác') NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `identity_number` VARCHAR(20) NULL,
  `identity_issue_date` DATE NULL,
  `identity_issue_place` VARCHAR(255) NULL,
  `relationship` VARCHAR(100) NOT NULL,
  `ethnicity` VARCHAR(100) NULL,
  `religion` VARCHAR(100) NULL,
  `occupation` VARCHAR(120) NULL,
  `phone` VARCHAR(30) NULL,
  `residency_status` ENUM('PERMANENT','TEMPORARY') NOT NULL DEFAULT 'PERMANENT' COMMENT 'PERMANENT=Thuong tru, TEMPORARY=Tam tru',
  `current_address` VARCHAR(255) NULL,
  `education_level` VARCHAR(100) NULL,
  `marital_status` VARCHAR(100) NULL,
  `life_status` ENUM('ALIVE','DECEASED') NOT NULL DEFAULT 'ALIVE',
  `presence_status` ENUM('AT_HOME','AWAY') NOT NULL DEFAULT 'AT_HOME',
  `has_health_insurance` TINYINT(1) NOT NULL DEFAULT 0,
  `health_insurance_number` VARCHAR(20) NULL,
  `health_insurance_group` VARCHAR(100) NULL,
  `health_insurance_start_date` DATE NULL,
  `health_insurance_end_date` DATE NULL,
  `health_insurance_facility` VARCHAR(255) NULL,
  `status` ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_citizens_code` (`citizen_code`),
  UNIQUE KEY `uq_citizens_identity` (`identity_number`),
  KEY `idx_citizens_household` (`household_id`),
  KEY `idx_citizens_full_name` (`full_name`),
  KEY `idx_citizens_gender` (`gender`),
  KEY `idx_citizens_dob` (`date_of_birth`),
  KEY `idx_citizens_relationship` (`relationship`),
  KEY `idx_citizens_life_status` (`life_status`),
  KEY `idx_citizens_presence_status` (`presence_status`),
  KEY `idx_citizens_residency_status` (`residency_status`),
  KEY `idx_citizens_health_insurance` (`has_health_insurance`, `health_insurance_end_date`),
  KEY `idx_citizens_status` (`status`),
  CONSTRAINT `fk_citizens_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_citizens_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_citizens_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_citizens_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `households`
  ADD CONSTRAINT `fk_households_head_citizen` FOREIGN KEY (`head_citizen_id`) REFERENCES `citizens` (`id`) ON DELETE SET NULL;

CREATE TABLE `movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `citizen_id` BIGINT UNSIGNED NOT NULL,
  `household_id` BIGINT UNSIGNED NULL,
  `type` ENUM('BIRTH','DEATH','MOVE_IN','MOVE_OUT','TEMPORARY_RESIDENCE','TEMPORARY_ABSENCE','OTHER') NOT NULL DEFAULT 'OTHER',
  `from_address` VARCHAR(255) NULL,
  `to_address` VARCHAR(255) NULL,
  `reason` VARCHAR(255) NULL,
  `effective_date` DATE NOT NULL,
  `document_number` VARCHAR(100) NULL,
  `note` TEXT NULL,
  `status` ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_movements_citizen` (`citizen_id`),
  KEY `idx_movements_household` (`household_id`),
  KEY `idx_movements_type` (`type`),
  KEY `idx_movements_effective_date` (`effective_date`),
  KEY `idx_movements_status` (`status`),
  CONSTRAINT `fk_movements_citizen` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_movements_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_movements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_movements_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_movements_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` ENUM('SUPER_ADMIN','ADMIN','OFFICER','VIEWER') NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_role_module_action` (`role`, `module`, `action`),
  KEY `idx_permissions_module_action` (`module`, `action`),
  CONSTRAINT `fk_permissions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_permissions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `actor_email` VARCHAR(190) NULL,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_id` VARCHAR(80) NULL,
  `level` ENUM('INFO','WARN','ERROR') NOT NULL DEFAULT 'INFO',
  `message` VARCHAR(255) NOT NULL,
  `metadata` JSON NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_actor` (`actor_user_id`),
  KEY `idx_audit_logs_module_action` (`module`, `action`),
  KEY `idx_audit_logs_entity` (`entity_id`),
  CONSTRAINT `fk_audit_logs_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`setting_key`),
  CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `backups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` BIGINT UNSIGNED NULL,
  `checksum` CHAR(64) NULL,
  `status` ENUM('SUCCESS','FAILED','RESTORED','DELETED') NOT NULL DEFAULT 'SUCCESS',
  `note` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `restored_at` DATETIME NULL,
  `restored_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_backups_created_at` (`created_at`),
  KEY `idx_backups_status` (`status`),
  CONSTRAINT `fk_backups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_backups_restored_by` FOREIGN KEY (`restored_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `import_batches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('HOUSEHOLD','CITIZEN') NOT NULL,
  `source_file_name` VARCHAR(255) NOT NULL,
  `source_file_path` VARCHAR(500) NULL,
  `sheet_name` VARCHAR(190) NULL,
  `duplicate_mode` ENUM('SKIP','UPDATE') NOT NULL DEFAULT 'SKIP',
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `valid_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `success_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('PREVIEW','SUCCESS','FAILED','PARTIAL') NOT NULL DEFAULT 'PREVIEW',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_import_batches_type` (`type`),
  KEY `idx_import_batches_created_at` (`created_at`),
  CONSTRAINT `fk_import_batches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `import_errors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` BIGINT UNSIGNED NOT NULL,
  `row_number` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `raw_data` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_import_errors_batch` (`batch_id`),
  CONSTRAINT `fk_import_errors_batch` FOREIGN KEY (`batch_id`) REFERENCES `import_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `export_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('EXCEL','PDF','PRINT') NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NULL,
  `filters` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_export_files_type` (`type`),
  KEY `idx_export_files_module` (`module`),
  KEY `idx_export_files_created_at` (`created_at`),
  CONSTRAINT `fk_export_files_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW `v_household_member_counts` AS
SELECT
  h.id AS household_id,
  COUNT(c.id) AS total_members,
  SUM(CASE WHEN c.presence_status = 'AT_HOME' AND c.status <> 'DELETED' THEN 1 ELSE 0 END) AS at_home_count,
  SUM(CASE WHEN c.presence_status = 'AWAY' AND c.status <> 'DELETED' THEN 1 ELSE 0 END) AS away_count
FROM households h
LEFT JOIN citizens c ON c.household_id = h.id AND c.status <> 'DELETED'
GROUP BY h.id;

CREATE OR REPLACE VIEW `v_dashboard_summary` AS
SELECT
  (SELECT COUNT(*) FROM households WHERE status <> 'DELETED') AS total_households,
  (SELECT COUNT(*) FROM citizens WHERE status <> 'DELETED') AS total_citizens,
  (SELECT COUNT(*) FROM citizens WHERE status <> 'DELETED' AND gender = 'Nam') AS total_male,
  (SELECT COUNT(*) FROM citizens WHERE status <> 'DELETED' AND gender = 'Nữ') AS total_female,
  (SELECT COUNT(*) FROM citizens WHERE status <> 'DELETED' AND life_status = 'ALIVE') AS active_citizens,
  (SELECT COUNT(*) FROM citizens WHERE status <> 'DELETED' AND residency_status = 'TEMPORARY') AS temporary_residence,
  (SELECT COUNT(*) FROM citizens WHERE status <> 'DELETED' AND presence_status = 'AWAY') AS temporary_absence;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('unitName', 'UBND xã Hồng Phong'),
('hamletName', 'Thôn 09'),
('systemName', 'Quản Lý Nhân Khẩu Thôn 09 xã Hồng Phong'),
('backupSchedule', 'DAILY'),
('reportSigner', ''),
('reportTitlePrefix', 'Quản lý nhân khẩu'),
('supportEmail', ''),
('maintenanceMessage', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `permissions` (`role`, `module`, `action`, `allowed`) VALUES
('ADMIN','dashboard','read',1),('ADMIN','household','read',1),('ADMIN','household','create',1),('ADMIN','household','update',1),('ADMIN','household','delete',1),('ADMIN','citizen','read',1),('ADMIN','citizen','create',1),('ADMIN','citizen','update',1),('ADMIN','citizen','delete',1),('ADMIN','movement','read',1),('ADMIN','movement','create',1),('ADMIN','movement','update',1),('ADMIN','movement','delete',1),('ADMIN','report','read',1),('ADMIN','report','export',1),('ADMIN','pdf','read',1),('ADMIN','pdf','export',1),('ADMIN','import','read',1),('ADMIN','import','create',1),('ADMIN','backup','read',1),('ADMIN','backup','create',1),('ADMIN','backup','update',1),('ADMIN','user','read',1),('ADMIN','user','create',1),('ADMIN','user','update',1),('ADMIN','user','delete',1),('ADMIN','permission','read',1),('ADMIN','permission','update',1),('ADMIN','logs','read',1),('ADMIN','settings','read',1),('ADMIN','settings','update',1),
('OFFICER','dashboard','read',1),('OFFICER','household','read',1),('OFFICER','household','create',1),('OFFICER','household','update',1),('OFFICER','household','delete',1),('OFFICER','citizen','read',1),('OFFICER','citizen','create',1),('OFFICER','citizen','update',1),('OFFICER','citizen','delete',1),('OFFICER','movement','read',1),('OFFICER','movement','create',1),('OFFICER','movement','update',1),('OFFICER','report','read',1),('OFFICER','report','export',1),('OFFICER','pdf','read',1),('OFFICER','pdf','export',1),('OFFICER','import','read',1),('OFFICER','import','create',1),
('VIEWER','dashboard','read',1),('VIEWER','household','read',1),('VIEWER','citizen','read',1),('VIEWER','report','read',1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

-- Tai khoan quan tri dau tien se duoc tao bang man hinh cai dat o Sprint 2.
-- Khong seed password mac dinh trong file SQL de tranh mat an toan.

-- Sprint 19: Household production and business module
CREATE TABLE IF NOT EXISTS `household_business` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `household_id` BIGINT UNSIGNED NOT NULL,
  `business_type` ENUM('RESIDENT','PRODUCTION','BUSINESS','BOTH') NOT NULL DEFAULT 'RESIDENT',
  `economic_type` VARCHAR(120) NULL,
  `main_products` TEXT NULL,
  `business_scale` VARCHAR(120) NULL,
  `is_ocop` TINYINT(1) NOT NULL DEFAULT 0,
  `ocop_product` VARCHAR(255) NULL,
  `ocop_star` TINYINT UNSIGNED NULL,
  `food_safety_certified` TINYINT(1) NOT NULL DEFAULT 0,
  `food_safety_certificate_no` VARCHAR(120) NULL,
  `food_safety_expired_date` DATE NULL,
  `social_insurance` TINYINT(1) NOT NULL DEFAULT 0,
  `insured_workers` INT UNSIGNED NOT NULL DEFAULT 0,
  `business_name` VARCHAR(255) NULL,
  `owner_name` VARCHAR(255) NULL,
  `production_sector` VARCHAR(255) NULL,
  `business_sector` VARCHAR(255) NULL,
  `business_license` VARCHAR(100) NULL,
  `license_date` DATE NULL,
  `license_place` VARCHAR(255) NULL,
  `tax_code` VARCHAR(50) NULL,
  `start_date` DATE NULL,
  `worker_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `annual_revenue` DECIMAL(18,2) NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(150) NULL,
  `address` VARCHAR(500) NULL,
  `latitude` DECIMAL(10,8) NULL,
  `longitude` DECIMAL(11,8) NULL,
  `gps_source` ENUM('household','activity') NOT NULL DEFAULT 'household',
  `status` ENUM('ACTIVE','INACTIVE','SUSPENDED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  `note` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_household_business_household` (`household_id`),
  KEY `idx_household_business_type` (`business_type`),
  KEY `idx_household_business_status` (`status`),
  KEY `idx_household_business_sector` (`production_sector`, `business_sector`),
  KEY `idx_household_business_license` (`business_license`),
  KEY `idx_household_business_tax` (`tax_code`),
  KEY `idx_household_business_location` (`latitude`, `longitude`),
  CONSTRAINT `fk_household_business_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_household_business_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_household_business_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_household_business_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `household_business_catalogs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_type` VARCHAR(50) NOT NULL,
  `value` VARCHAR(150) NOT NULL,
  `label` VARCHAR(150) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hb_catalog` (`catalog_type`, `value`),
  KEY `idx_hb_catalog_type` (`catalog_type`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `household_business_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `household_business_id` BIGINT UNSIGNED NOT NULL,
  `file_kind` ENUM('IMAGE','DOCUMENT') NOT NULL,
  `category` VARCHAR(120) NOT NULL,
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
  KEY `idx_hb_files_business` (`household_business_id`, `status`, `file_kind`),
  KEY `idx_hb_files_category` (`category`),
  CONSTRAINT `fk_hb_files_business` FOREIGN KEY (`household_business_id`) REFERENCES `household_business` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hb_files_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hb_files_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`role`, `module`, `action`, `allowed`)
VALUES
('ADMIN','household_business','read',1),
('ADMIN','household_business','create',1),
('ADMIN','household_business','update',1),
('ADMIN','household_business','delete',1),
('OFFICER','household_business','read',1),
('OFFICER','household_business','create',1),
('OFFICER','household_business','update',1),
('VIEWER','household_business','read',1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);
