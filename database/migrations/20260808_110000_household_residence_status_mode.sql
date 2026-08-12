-- Add AUTO/MANUAL mode for household residence status.
-- AUTO only suggests/evaluates household residence from member presence counts.
-- MANUAL keeps the administrator-confirmed residence_status and must not be overwritten.

SET NAMES utf8mb4;
SET time_zone = '+07:00';

SET @has_residence_status_mode := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'residence_status_mode'
);
SET @add_residence_status_mode := IF(
  @has_residence_status_mode = 0,
  "ALTER TABLE households ADD COLUMN residence_status_mode ENUM('AUTO','MANUAL') NOT NULL DEFAULT 'AUTO' AFTER residence_status",
  'SELECT 1'
);
PREPARE add_residence_status_mode_stmt FROM @add_residence_status_mode;
EXECUTE add_residence_status_mode_stmt;
DEALLOCATE PREPARE add_residence_status_mode_stmt;

-- Preserve any already reviewed non-default residence statuses as manual overrides.
UPDATE households
SET residence_status_mode = 'MANUAL'
WHERE residence_status IN ('away_for_work','settled_elsewhere','partial','inactive');

SET @has_residence_status_mode_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND INDEX_NAME = 'idx_households_residence_status_mode'
);
SET @add_residence_status_mode_index := IF(
  @has_residence_status_mode_index = 0,
  'CREATE INDEX idx_households_residence_status_mode ON households (residence_status_mode)',
  'SELECT 1'
);
PREPARE add_residence_status_mode_index_stmt FROM @add_residence_status_mode_index;
EXECUTE add_residence_status_mode_index_stmt;
DEALLOCATE PREPARE add_residence_status_mode_index_stmt;
