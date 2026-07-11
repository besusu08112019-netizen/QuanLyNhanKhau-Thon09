-- Sprint: Module Quan ly Cong trinh cong cong.
-- Administrative/community facility registry only; no asset value, finance, engineering, maintenance, or settlement data.

CREATE TABLE IF NOT EXISTS public_asset_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(120) NOT NULL,
  name VARCHAR(180) NOT NULL,
  icon VARCHAR(80) NOT NULL DEFAULT 'fa-building-columns',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_public_asset_types_name (name),
  KEY idx_public_asset_types_category (category),
  KEY idx_public_asset_types_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_code VARCHAR(40) NOT NULL UNIQUE,
  asset_name VARCHAR(255) NOT NULL,
  type_id BIGINT UNSIGNED NULL,
  type_name VARCHAR(180) NULL,
  category VARCHAR(120) NULL,
  area_code VARCHAR(80) NULL,
  address VARCHAR(500) NULL,
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  gps_accuracy DECIMAL(10,2) NULL,
  cover_photo_url VARCHAR(500) NULL,
  description TEXT NULL,
  managing_unit VARCHAR(255) NULL,
  manager_name VARCHAR(255) NULL,
  manager_phone VARCHAR(80) NULL,
  note TEXT NULL,
  status ENUM('ACTIVE','REPAIRING','SUSPENDED','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_public_assets_type (type_id),
  KEY idx_public_assets_area (area_code),
  KEY idx_public_assets_status (status),
  KEY idx_public_assets_location (latitude, longitude),
  CONSTRAINT fk_public_assets_type FOREIGN KEY (type_id) REFERENCES public_asset_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (role, module, action, allowed)
VALUES
('SUPER_ADMIN','public_assets','read',1),('SUPER_ADMIN','public_assets','create',1),('SUPER_ADMIN','public_assets','update',1),('SUPER_ADMIN','public_assets','delete',1),
('ADMIN','public_assets','read',1),('ADMIN','public_assets','create',1),('ADMIN','public_assets','update',1),('ADMIN','public_assets','delete',1),
('OFFICER','public_assets','read',1),('OFFICER','public_assets','create',1),('OFFICER','public_assets','update',1),
('VIEWER','public_assets','read',1)
ON DUPLICATE KEY UPDATE allowed=VALUES(allowed);