CREATE TABLE IF NOT EXISTS `agricultural_land_zones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `village_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `zone_code` VARCHAR(40) NOT NULL,
  `zone_name` VARCHAR(255) NOT NULL,
  `input_unit` ENUM('mau') NOT NULL DEFAULT 'mau',
  `report_year` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `total_area_m2` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `long_term_allocated_area_m2` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `public_utility_area_m2` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `leased_area_m2` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `converted_area_m2` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `latitude` DECIMAL(11,8) NULL,
  `longitude` DECIMAL(11,8) NULL,
  `polygon_json` LONGTEXT NULL,
  `photo_url` VARCHAR(500) NULL,
  `irrigation_note` TEXT NULL,
  `production_group_name` VARCHAR(255) NULL,
  `main_crop_type` VARCHAR(255) NULL,
  `annual_note` TEXT NULL,
  `note` TEXT NULL,
  `status` ENUM('ACTIVE','INACTIVE','CONVERTING','DELETED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_agricultural_land_zone_code_year` (`village_id`,`zone_code`,`report_year`),
  KEY `idx_agricultural_land_zones_village` (`village_id`),
  KEY `idx_agricultural_land_zones_name` (`zone_name`),
  KEY `idx_agricultural_land_zones_year` (`report_year`),
  KEY `idx_agricultural_land_zones_status` (`status`),
  KEY `idx_agricultural_land_zones_location` (`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `land_usage_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `village_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `code` VARCHAR(60) NOT NULL,
  `name` VARCHAR(180) NOT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_land_usage_types_code` (`village_id`,`code`),
  KEY `idx_land_usage_types_active` (`village_id`,`is_active`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agricultural_land_zone_usage_areas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `village_id` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `zone_id` BIGINT UNSIGNED NOT NULL,
  `usage_type_id` BIGINT UNSIGNED NOT NULL,
  `area_m2` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_agricultural_land_zone_usage` (`zone_id`,`usage_type_id`),
  KEY `idx_agricultural_land_zone_usage_village` (`village_id`),
  KEY `idx_agricultural_land_zone_usage_type` (`usage_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agricultural_land_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `village_id` BIGINT UNSIGNED NOT NULL,
  `default_unit` ENUM('mau') NOT NULL DEFAULT 'mau',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_agricultural_land_settings_village` (`village_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `land_usage_types` (`village_id`, `code`, `name`, `display_order`, `is_active`) VALUES
(1,'LUA','Lua',10,1),
(1,'NGO','Ngo',20,1),
(1,'LAC','Lac',30,1),
(1,'RAU_MAU','Rau mau',40,1),
(1,'HOA_MAU','Hoa mau',50,1),
(1,'CAY_AN_QUA','Cay an qua',60,1),
(1,'CAY_LAU_NAM','Cay lau nam',70,1),
(1,'THUY_SAN','Nuoi trong thuy san',80,1),
(1,'KHAC','Khac',90,1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `display_order` = VALUES(`display_order`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `permissions` (`role`, `module`, `action`, `allowed`) VALUES
('ADMIN','agricultural_land','read',1),
('ADMIN','agricultural_land','create',1),
('ADMIN','agricultural_land','update',1),
('ADMIN','agricultural_land','delete',1),
('ADMIN','agricultural_land','export',1),
('ADMIN','agricultural_land','print',1),
('OFFICER','agricultural_land','read',1),
('VIEWER','agricultural_land','read',1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);
