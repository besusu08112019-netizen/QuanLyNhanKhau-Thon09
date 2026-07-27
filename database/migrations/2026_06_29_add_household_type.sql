-- Legacy migration kept for older databases.
-- Household category is now computed at read time; this column is transitional
-- and is removed by 20260727_190000_normalize_household_categories.sql.

ALTER TABLE households
  ADD COLUMN IF NOT EXISTS household_type VARCHAR(50) NULL AFTER area_code;

CREATE INDEX IF NOT EXISTS idx_households_household_type ON households (household_type);

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

SET @update_household_type_sql := CONCAT(
  'UPDATE households SET household_type = CASE ',
  'WHEN poor_household = 1 THEN ''poor'' ',
  'WHEN near_poor_household = 1 THEN ''near_poor'' ',
  IF(@has_meritorious_family > 0, 'WHEN meritorious_family = 1 THEN ''meritorious'' ', ''),
  IF(@has_disabled_household > 0, 'WHEN disabled_household = 1 THEN ''other'' ', ''),
  'ELSE ''normal'' END WHERE household_type IS NULL OR household_type = '''''
);
PREPARE update_household_type_stmt FROM @update_household_type_sql;
EXECUTE update_household_type_stmt;
DEALLOCATE PREPARE update_household_type_stmt;
