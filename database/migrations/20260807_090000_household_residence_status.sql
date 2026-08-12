-- Add actual household residence status without changing household lifecycle status.
-- Existing households default to resident and keep their household code, members,
-- original address, and linked history.

SET NAMES utf8mb4;
SET time_zone = '+07:00';

SET @has_residence_status := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'residence_status'
);
SET @add_residence_status := IF(
  @has_residence_status = 0,
  "ALTER TABLE households ADD COLUMN residence_status ENUM('resident','away_for_work','settled_elsewhere','partial','inactive') NOT NULL DEFAULT 'resident' AFTER note",
  'SELECT 1'
);
PREPARE add_residence_status_stmt FROM @add_residence_status;
EXECUTE add_residence_status_stmt;
DEALLOCATE PREPARE add_residence_status_stmt;

SET @has_residence_status := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'residence_status'
);
SET @widen_residence_status := IF(
  @has_residence_status = 1,
  "ALTER TABLE households MODIFY COLUMN residence_status ENUM('resident','outside','away_for_work','settled_elsewhere','partial','inactive') NOT NULL DEFAULT 'resident'",
  'SELECT 1'
);
PREPARE widen_residence_status_stmt FROM @widen_residence_status;
EXECUTE widen_residence_status_stmt;
DEALLOCATE PREPARE widen_residence_status_stmt;

UPDATE households
SET residence_status = 'settled_elsewhere'
WHERE residence_status = 'outside';

SET @finalize_residence_status := IF(
  @has_residence_status = 1,
  "ALTER TABLE households MODIFY COLUMN residence_status ENUM('resident','away_for_work','settled_elsewhere','partial','inactive') NOT NULL DEFAULT 'resident'",
  'SELECT 1'
);
PREPARE finalize_residence_status_stmt FROM @finalize_residence_status;
EXECUTE finalize_residence_status_stmt;
DEALLOCATE PREPARE finalize_residence_status_stmt;

SET @has_current_residence_place := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'current_residence_place'
);
SET @add_current_residence_place := IF(
  @has_current_residence_place = 0,
  'ALTER TABLE households ADD COLUMN current_residence_place VARCHAR(255) NULL AFTER residence_status',
  'SELECT 1'
);
PREPARE add_current_residence_place_stmt FROM @add_current_residence_place;
EXECUTE add_current_residence_place_stmt;
DEALLOCATE PREPARE add_current_residence_place_stmt;

SET @has_residence_started_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'residence_started_at'
);
SET @add_residence_started_at := IF(
  @has_residence_started_at = 0,
  'ALTER TABLE households ADD COLUMN residence_started_at DATE NULL AFTER current_residence_place',
  'SELECT 1'
);
PREPARE add_residence_started_at_stmt FROM @add_residence_started_at;
EXECUTE add_residence_started_at_stmt;
DEALLOCATE PREPARE add_residence_started_at_stmt;

SET @has_residence_expected_return_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'residence_expected_return_at'
);
SET @add_residence_expected_return_at := IF(
  @has_residence_expected_return_at = 0,
  'ALTER TABLE households ADD COLUMN residence_expected_return_at DATE NULL AFTER residence_started_at',
  'SELECT 1'
);
PREPARE add_residence_expected_return_at_stmt FROM @add_residence_expected_return_at;
EXECUTE add_residence_expected_return_at_stmt;
DEALLOCATE PREPARE add_residence_expected_return_at_stmt;

SET @has_residence_note := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'residence_note'
);
SET @add_residence_note := IF(
  @has_residence_note = 0,
  'ALTER TABLE households ADD COLUMN residence_note TEXT NULL AFTER residence_expected_return_at',
  'SELECT 1'
);
PREPARE add_residence_note_stmt FROM @add_residence_note;
EXECUTE add_residence_note_stmt;
DEALLOCATE PREPARE add_residence_note_stmt;

SET @has_member_residence_json := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME = 'member_residence_json'
);
SET @add_member_residence_json := IF(
  @has_member_residence_json = 0,
  'ALTER TABLE households ADD COLUMN member_residence_json JSON NULL AFTER residence_note',
  'SELECT 1'
);
PREPARE add_member_residence_json_stmt FROM @add_member_residence_json;
EXECUTE add_member_residence_json_stmt;
DEALLOCATE PREPARE add_member_residence_json_stmt;

UPDATE households
SET residence_status = 'resident'
WHERE residence_status IS NULL OR residence_status = '';

SET @has_residence_status_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND INDEX_NAME = 'idx_households_residence_status'
);
SET @add_residence_status_index := IF(
  @has_residence_status_index = 0,
  'CREATE INDEX idx_households_residence_status ON households (residence_status)',
  'SELECT 1'
);
PREPARE add_residence_status_index_stmt FROM @add_residence_status_index;
EXECUTE add_residence_status_index_stmt;
DEALLOCATE PREPARE add_residence_status_index_stmt;
