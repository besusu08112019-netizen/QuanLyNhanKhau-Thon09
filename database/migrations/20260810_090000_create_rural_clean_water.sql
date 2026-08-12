-- Module Quan ly nuoc sach nong thon.
-- Additive migration: creates independent household-scoped tables only.

CREATE TABLE IF NOT EXISTS rural_clean_water (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  household_id BIGINT UNSIGNED NOT NULL,
  connection_type ENUM('PIPED','WELL','RAINWATER','PURCHASED','OTHER') NOT NULL DEFAULT 'PIPED',
  water_source VARCHAR(255) NULL,
  provider_name VARCHAR(255) NULL,
  meter_number VARCHAR(120) NULL,
  contract_number VARCHAR(120) NULL,
  installed_date DATE NULL,
  monthly_usage_m3 DECIMAL(12,2) NOT NULL DEFAULT 0,
  monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_clean_standard TINYINT(1) NOT NULL DEFAULT 0,
  last_test_date DATE NULL,
  test_result VARCHAR(120) NULL,
  status ENUM('ACTIVE','INACTIVE','NEEDS_REPAIR','DISCONNECTED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_rural_clean_water_village (village_id),
  KEY idx_rural_clean_water_household (household_id),
  KEY idx_rural_clean_water_type (connection_type),
  KEY idx_rural_clean_water_standard (is_clean_standard),
  KEY idx_rural_clean_water_status (status),
  CONSTRAINT fk_rural_clean_water_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (role, module, action, allowed)
VALUES
('SUPER_ADMIN','rural_clean_water','read',1),('SUPER_ADMIN','rural_clean_water','create',1),('SUPER_ADMIN','rural_clean_water','update',1),('SUPER_ADMIN','rural_clean_water','delete',1),('SUPER_ADMIN','rural_clean_water','export',1),('SUPER_ADMIN','rural_clean_water','print',1),
('ADMIN','rural_clean_water','read',1),('ADMIN','rural_clean_water','create',1),('ADMIN','rural_clean_water','update',1),('ADMIN','rural_clean_water','delete',1),('ADMIN','rural_clean_water','export',1),('ADMIN','rural_clean_water','print',1),
('OFFICER','rural_clean_water','read',1),('OFFICER','rural_clean_water','create',1),('OFFICER','rural_clean_water','update',1),('OFFICER','rural_clean_water','export',1),('OFFICER','rural_clean_water','print',1),
('VIEWER','rural_clean_water','read',1)
ON DUPLICATE KEY UPDATE allowed=VALUES(allowed);
