-- Sprint agriculture production module. New agri_* tables only; no changes to households/persons.

CREATE TABLE IF NOT EXISTS agri_stakeholders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stakeholder_type ENUM('VILLAGE_HOUSEHOLD','OUTSIDE_PERSON','BUSINESS','COOPERATIVE','ORGANIZATION') NOT NULL DEFAULT 'VILLAGE_HOUSEHOLD',
  household_id BIGINT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  identity_number VARCHAR(80) NULL,
  tax_code VARCHAR(80) NULL,
  phone VARCHAR(50) NULL,
  address VARCHAR(500) NULL,
  note TEXT NULL,
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_agri_stakeholders_household (household_id),
  KEY idx_agri_stakeholders_name (name),
  CONSTRAINT fk_agri_stakeholders_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agri_land_parcels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcel_code VARCHAR(40) NOT NULL UNIQUE,
  map_sheet_no VARCHAR(80) NULL,
  parcel_no VARCHAR(80) NULL,
  field_area VARCHAR(255) NULL,
  field_name VARCHAR(255) NULL,
  land_type VARCHAR(120) NULL,
  legal_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  actual_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  cultivated_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  abandoned_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  owner_id BIGINT UNSIGNED NOT NULL,
  producer_id BIGINT UNSIGNED NOT NULL,
  usage_form ENUM('SELF','LEASE_OUT','LEASE_IN','BORROW','PARTNERSHIP') NOT NULL DEFAULT 'SELF',
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  polygon_geojson LONGTEXT NULL,
  status ENUM('ACTIVE','IDLE','LEASED','ABANDONED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_agri_parcels_owner (owner_id),
  KEY idx_agri_parcels_producer (producer_id),
  KEY idx_agri_parcels_status (status),
  KEY idx_agri_parcels_field (field_area),
  CONSTRAINT fk_agri_parcels_owner FOREIGN KEY (owner_id) REFERENCES agri_stakeholders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_agri_parcels_producer FOREIGN KEY (producer_id) REFERENCES agri_stakeholders(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agri_production_plots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcel_id BIGINT UNSIGNED NOT NULL,
  plot_code VARCHAR(80) NULL,
  plot_name VARCHAR(160) NOT NULL,
  area DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','IDLE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_agri_plots_parcel (parcel_id),
  CONSTRAINT fk_agri_plots_parcel FOREIGN KEY (parcel_id) REFERENCES agri_land_parcels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agri_crop_seasons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plot_id BIGINT UNSIGNED NOT NULL,
  season_name VARCHAR(80) NOT NULL,
  crop VARCHAR(120) NOT NULL,
  variety VARCHAR(160) NULL,
  area DECIMAL(14,2) NOT NULL DEFAULT 0,
  land_prep_date DATE NULL,
  sowing_date DATE NULL,
  transplant_date DATE NULL,
  fertilizer_date DATE NULL,
  pesticide_date DATE NULL,
  expected_harvest_date DATE NULL,
  actual_harvest_date DATE NULL,
  yield_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  output_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
  cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  profit DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('PLANNED','IN_PROGRESS','HARVESTED','CANCELLED','DELETED') NOT NULL DEFAULT 'IN_PROGRESS',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_agri_seasons_plot (plot_id),
  KEY idx_agri_seasons_crop (crop),
  KEY idx_agri_seasons_name (season_name),
  CONSTRAINT fk_agri_seasons_plot FOREIGN KEY (plot_id) REFERENCES agri_production_plots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agri_production_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id BIGINT UNSIGNED NOT NULL,
  activity_type VARCHAR(50) NOT NULL,
  activity_date DATE NOT NULL,
  actor_name VARCHAR(255) NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  KEY idx_agri_logs_season (season_id),
  CONSTRAINT fk_agri_logs_season FOREIGN KEY (season_id) REFERENCES agri_crop_seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agri_damages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcel_id BIGINT UNSIGNED NOT NULL,
  season_id BIGINT UNSIGNED NULL,
  damage_type VARCHAR(50) NOT NULL,
  event_date DATE NOT NULL,
  affected_area DECIMAL(14,2) NOT NULL DEFAULT 0,
  damage_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  estimated_output_loss DECIMAL(14,2) NOT NULL DEFAULT 0,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_agri_damages_parcel (parcel_id),
  KEY idx_agri_damages_season (season_id),
  CONSTRAINT fk_agri_damages_parcel FOREIGN KEY (parcel_id) REFERENCES agri_land_parcels(id) ON DELETE CASCADE,
  CONSTRAINT fk_agri_damages_season FOREIGN KEY (season_id) REFERENCES agri_crop_seasons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agri_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parcel_id BIGINT UNSIGNED NULL,
  season_id BIGINT UNSIGNED NULL,
  damage_id BIGINT UNSIGNED NULL,
  file_kind ENUM('IMAGE','DOCUMENT') NOT NULL DEFAULT 'IMAGE',
  category VARCHAR(120) NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  KEY idx_agri_files_parcel (parcel_id),
  KEY idx_agri_files_season (season_id),
  KEY idx_agri_files_damage (damage_id),
  CONSTRAINT fk_agri_files_parcel FOREIGN KEY (parcel_id) REFERENCES agri_land_parcels(id) ON DELETE CASCADE,
  CONSTRAINT fk_agri_files_season FOREIGN KEY (season_id) REFERENCES agri_crop_seasons(id) ON DELETE CASCADE,
  CONSTRAINT fk_agri_files_damage FOREIGN KEY (damage_id) REFERENCES agri_damages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
