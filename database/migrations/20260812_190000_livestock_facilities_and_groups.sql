CREATE TABLE IF NOT EXISTS livestock_facilities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NULL,
  household_id BIGINT UNSIGNED NOT NULL,
  owner_name VARCHAR(160) NULL,
  facility_name VARCHAR(180) NULL,
  facility_type ENUM('HOUSEHOLD','SMALL_FARM','FARM') NOT NULL DEFAULT 'HOUSEHOLD',
  location VARCHAR(255) NULL,
  area_code VARCHAR(80) NULL,
  farming_area_m2 DECIMAL(12,2) NULL,
  status ENUM('ACTIVE','PAUSED','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_livestock_facility_village (village_id),
  KEY idx_livestock_facility_household (household_id),
  KEY idx_livestock_facility_type (facility_type),
  KEY idx_livestock_facility_status (status),
  KEY idx_livestock_facility_area (area_code),
  CONSTRAINT fk_livestock_facility_household FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'livestock' AND COLUMN_NAME = 'facility_id') > 0, 'SELECT 1', 'ALTER TABLE livestock ADD COLUMN facility_id BIGINT UNSIGNED NULL AFTER village_id');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'livestock' AND COLUMN_NAME = 'animal_group') > 0, 'SELECT 1', 'ALTER TABLE livestock ADD COLUMN animal_group VARCHAR(80) NULL AFTER animal_type');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'livestock' AND COLUMN_NAME = 'unit') > 0, 'SELECT 1', 'ALTER TABLE livestock ADD COLUMN unit VARCHAR(30) NOT NULL DEFAULT ''con'' AFTER quantity');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE livestock MODIFY COLUMN status ENUM('ACTIVE','PAUSED','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE';

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'livestock' AND INDEX_NAME = 'idx_livestock_facility') > 0, 'SELECT 1', 'CREATE INDEX idx_livestock_facility ON livestock (facility_id)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'livestock' AND INDEX_NAME = 'idx_livestock_animal_group') > 0, 'SELECT 1', 'CREATE INDEX idx_livestock_animal_group ON livestock (animal_group)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO livestock_facilities (village_id, household_id, owner_name, facility_type, location, area_code, status, note, created_at, updated_at)
SELECT legacy.village_id, legacy.household_id, legacy.owner_name, 'HOUSEHOLD', legacy.location, legacy.area_code, 'ACTIVE', 'Tao tu dong khi nang cap du lieu chan nuoi cu', legacy.created_at, legacy.updated_at
FROM (
  SELECT COALESCE(l.village_id, h.village_id) AS village_id,
         l.household_id,
         h.head_citizen_name AS owner_name,
         h.address AS location,
         h.area_code,
         MIN(l.created_at) AS created_at,
         MAX(l.updated_at) AS updated_at
  FROM livestock l
  INNER JOIN households h ON h.id = l.household_id
  WHERE l.facility_id IS NULL
  GROUP BY COALESCE(l.village_id, h.village_id), l.household_id, h.head_citizen_name, h.address, h.area_code
) legacy
WHERE NOT EXISTS (
  SELECT 1
  FROM livestock_facilities lf
  WHERE lf.household_id = legacy.household_id
    AND COALESCE(lf.village_id, 0) = COALESCE(legacy.village_id, 0)
    AND lf.note = 'Tao tu dong khi nang cap du lieu chan nuoi cu'
);

UPDATE livestock l
INNER JOIN households h ON h.id = l.household_id
INNER JOIN livestock_facilities lf
  ON lf.household_id = l.household_id
 AND COALESCE(lf.village_id, 0) = COALESCE(l.village_id, h.village_id, 0)
 AND lf.note = 'Tao tu dong khi nang cap du lieu chan nuoi cu'
SET l.facility_id = lf.id,
    l.animal_group = CASE WHEN (l.animal_group IS NULL OR l.animal_group = '') AND l.animal_type = 'Lợn' THEN 'UNCLASSIFIED' ELSE l.animal_group END
WHERE l.facility_id IS NULL;