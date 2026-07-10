-- Add direct parent name fields to citizens. No foreign keys are created.
SET @father_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'citizens'
    AND COLUMN_NAME = 'father_name'
);

SET @sql := IF(
  @father_exists = 0,
  'ALTER TABLE citizens ADD COLUMN father_name VARCHAR(255) NULL AFTER occupation',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @mother_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'citizens'
    AND COLUMN_NAME = 'mother_name'
);

SET @sql := IF(
  @mother_exists = 0,
  'ALTER TABLE citizens ADD COLUMN mother_name VARCHAR(255) NULL AFTER father_name',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
