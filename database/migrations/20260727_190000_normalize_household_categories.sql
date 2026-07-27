-- Normalize household category data.
-- Household "meritorious" and "disabled" categories are derived from citizens.
-- This migration preserves old household-level flags for audit, then removes
-- duplicated columns from households.

CREATE TABLE IF NOT EXISTS household_legacy_category_flags (
  household_id BIGINT UNSIGNED NOT NULL,
  village_id BIGINT UNSIGNED NULL,
  meritorious_family TINYINT(1) NOT NULL DEFAULT 0,
  disabled_household TINYINT(1) NOT NULL DEFAULT 0,
  captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (household_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_meritorious_family := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'meritorious_family'
);
SET @has_disabled_household := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'disabled_household'
);
SET @has_household_type := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'household_type'
);
SET @has_village_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'village_id'
);

SET @backup_sql := IF(
  @has_meritorious_family > 0 OR @has_disabled_household > 0,
  CONCAT(
    'INSERT IGNORE INTO household_legacy_category_flags (household_id, village_id, meritorious_family, disabled_household) ',
    'SELECT id, ',
    IF(@has_village_id > 0, 'village_id', 'NULL'),
    ', ',
    IF(@has_meritorious_family > 0, 'COALESCE(meritorious_family,0)', '0'),
    ', ',
    IF(@has_disabled_household > 0, 'COALESCE(disabled_household,0)', '0'),
    ' FROM households'
  ),
  'SELECT 1'
);
PREPARE backup_stmt FROM @backup_sql;
EXECUTE backup_stmt;
DEALLOCATE PREPARE backup_stmt;

SET @has_policy_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND INDEX_NAME = 'idx_households_policy'
);
SET @drop_index_sql := IF(@has_policy_index > 0, 'ALTER TABLE households DROP INDEX idx_households_policy', 'SELECT 1');
PREPARE drop_index_stmt FROM @drop_index_sql;
EXECUTE drop_index_stmt;
DEALLOCATE PREPARE drop_index_stmt;

SET @has_type_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND INDEX_NAME = 'idx_households_household_type'
);
SET @drop_type_index_sql := IF(@has_type_index > 0, 'ALTER TABLE households DROP INDEX idx_households_household_type', 'SELECT 1');
PREPARE drop_type_index_stmt FROM @drop_type_index_sql;
EXECUTE drop_type_index_stmt;
DEALLOCATE PREPARE drop_type_index_stmt;

SET @drop_meritorious_sql := IF(@has_meritorious_family > 0, 'ALTER TABLE households DROP COLUMN meritorious_family', 'SELECT 1');
PREPARE drop_meritorious_stmt FROM @drop_meritorious_sql;
EXECUTE drop_meritorious_stmt;
DEALLOCATE PREPARE drop_meritorious_stmt;

SET @drop_disabled_sql := IF(@has_disabled_household > 0, 'ALTER TABLE households DROP COLUMN disabled_household', 'SELECT 1');
PREPARE drop_disabled_stmt FROM @drop_disabled_sql;
EXECUTE drop_disabled_stmt;
DEALLOCATE PREPARE drop_disabled_stmt;

SET @drop_household_type_sql := IF(@has_household_type > 0, 'ALTER TABLE households DROP COLUMN household_type', 'SELECT 1');
PREPARE drop_household_type_stmt FROM @drop_household_type_sql;
EXECUTE drop_household_type_stmt;
DEALLOCATE PREPARE drop_household_type_stmt;

CREATE INDEX idx_households_policy ON households (poor_household, near_poor_household);
