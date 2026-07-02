-- Sprint 16 / GIS search indexes
-- Idempotent indexes for household and citizen lookup on the GIS map.

SET @idx := (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'households'
      AND INDEX_NAME = 'idx_households_household_code'
);
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_households_household_code ON households (household_code)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'households'
      AND INDEX_NAME = 'idx_households_head_name'
);
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_households_head_name ON households (head_citizen_name)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'citizens'
      AND INDEX_NAME = 'idx_citizens_full_name'
);
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_citizens_full_name ON citizens (full_name)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
