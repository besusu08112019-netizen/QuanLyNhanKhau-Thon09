-- Sprint 11 GIS - Bản đồ địa bàn và định vị hộ gia đình
CREATE TABLE IF NOT EXISTS gis_areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  area_code VARCHAR(100) NOT NULL,
  geometry_json LONGTEXT NOT NULL,
  color VARCHAR(20) DEFAULT '#0f8a4b',
  note TEXT NULL,
  sort_order INT DEFAULT 0,
  status VARCHAR(20) DEFAULT 'ACTIVE',
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_gis_area_code (area_code),
  INDEX idx_gis_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE households ADD COLUMN latitude DECIMAL(10,7) NULL;
ALTER TABLE households ADD COLUMN longitude DECIMAL(10,7) NULL;
ALTER TABLE households ADD COLUMN google_map_url VARCHAR(255) NULL;
ALTER TABLE households ADD COLUMN location_note TEXT NULL;
ALTER TABLE households ADD COLUMN location_updated_at DATETIME NULL;
ALTER TABLE households ADD COLUMN location_updated_by INT NULL;
