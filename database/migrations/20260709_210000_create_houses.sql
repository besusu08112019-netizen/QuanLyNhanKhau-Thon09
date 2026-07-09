-- Sprint XX: Module Quan ly Nha o va Cong trinh.
-- Creates independent house tables; does not alter households/persons.

CREATE TABLE IF NOT EXISTS houses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  house_code VARCHAR(40) NOT NULL UNIQUE,
  house_name VARCHAR(255) NULL,
  address VARCHAR(500) NULL,
  house_type VARCHAR(120) NULL,
  structure_type VARCHAR(120) NULL,
  floors INT UNSIGNED NOT NULL DEFAULT 1,
  land_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  building_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  floor_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  build_year INT UNSIGNED NULL,
  renovated_year INT UNSIGNED NULL,
  `condition` VARCHAR(80) NULL,
  solidity VARCHAR(80) NULL,
  `usage` VARCHAR(120) NULL,
  legal_status VARCHAR(120) NULL,
  electric_meter VARCHAR(120) NULL,
  water_meter VARCHAR(120) NULL,
  internet TINYINT(1) NOT NULL DEFAULT 0,
  security_camera TINYINT(1) NOT NULL DEFAULT 0,
  fire_extinguisher TINYINT(1) NOT NULL DEFAULT 0,
  fire_risk VARCHAR(30) NOT NULL DEFAULT 'LOW',
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  gps_accuracy DECIMAL(10,2) NULL,
  notes TEXT NULL,
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_houses_household (household_id),
  KEY idx_houses_type (house_type),
  KEY idx_houses_condition (`condition`),
  KEY idx_houses_fire_risk (fire_risk),
  KEY idx_houses_status (status),
  CONSTRAINT fk_houses_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS house_structures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_id BIGINT UNSIGNED NOT NULL,
  structure_type VARCHAR(120) NOT NULL,
  structure_name VARCHAR(255) NULL,
  area DECIMAL(14,2) NOT NULL DEFAULT 0,
  build_year INT UNSIGNED NULL,
  `condition` VARCHAR(80) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_house_structures_house (house_id),
  KEY idx_house_structures_type (structure_type),
  CONSTRAINT fk_house_structures_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS house_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  stored_name VARCHAR(255) NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  photo_type VARCHAR(120) NULL,
  description VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_house_photos_house (house_id),
  KEY idx_house_photos_type (photo_type),
  CONSTRAINT fk_house_photos_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (role, module, action, allowed)
VALUES
('SUPER_ADMIN','houses','read',1),('SUPER_ADMIN','houses','create',1),('SUPER_ADMIN','houses','update',1),('SUPER_ADMIN','houses','delete',1),('SUPER_ADMIN','houses','export',1),
('ADMIN','houses','read',1),('ADMIN','houses','create',1),('ADMIN','houses','update',1),('ADMIN','houses','delete',1),('ADMIN','houses','export',1),
('OFFICER','houses','read',1),('OFFICER','houses','create',1),('OFFICER','houses','update',1),('OFFICER','houses','export',1),
('VIEWER','houses','read',1)
ON DUPLICATE KEY UPDATE allowed=VALUES(allowed);
