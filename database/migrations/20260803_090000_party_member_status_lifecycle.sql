-- Party member lifecycle status management.
-- Profiles are retained permanently; status changes are recorded through audit_logs.

DROP PROCEDURE IF EXISTS add_party_member_status_column;
DELIMITER //
CREATE PROCEDURE add_party_member_status_column(
  IN column_name_value VARCHAR(64),
  IN definition_value TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'party_members'
      AND COLUMN_NAME = column_name_value
  ) THEN
    SET @sql = CONCAT('ALTER TABLE party_members ADD COLUMN ', column_name_value, ' ', definition_value);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END//
DELIMITER ;

CALL add_party_member_status_column('party_status', 'VARCHAR(40) NOT NULL DEFAULT ''ACTIVE'' AFTER activity_status');
CALL add_party_member_status_column('status_changed_at', 'DATE NULL AFTER party_status');
CALL add_party_member_status_column('status_reason', 'TEXT NULL AFTER status_changed_at');
CALL add_party_member_status_column('decision_number', 'VARCHAR(120) NULL AFTER status_reason');
CALL add_party_member_status_column('decision_date', 'DATE NULL AFTER decision_number');
CALL add_party_member_status_column('transfer_to', 'VARCHAR(255) NULL AFTER decision_date');

UPDATE party_members
SET party_status = CASE activity_status
  WHEN 'TRANSFERRED_OUT' THEN 'TRANSFERRED'
  WHEN 'TRANSFERRED_IN' THEN 'TEMPORARY'
  WHEN 'TEMP_EXEMPT' THEN 'EXEMPT'
  WHEN 'RETIRED' THEN 'EXEMPT'
  WHEN 'DELETED' THEN 'LEFT_PARTY'
  ELSE activity_status
END
WHERE party_status IS NULL
   OR party_status = ''
   OR (party_status = 'ACTIVE' AND activity_status <> 'ACTIVE');

UPDATE party_members
SET party_status = 'ACTIVE'
WHERE party_status NOT IN ('ACTIVE','TEMPORARY','EXEMPT','AWAY','TRANSFERRED','LEFT_PARTY','DECEASED');

UPDATE party_members
SET activity_status = party_status,
    status_changed_at = COALESCE(DATE(updated_at), DATE(created_at), CURDATE())
WHERE status_changed_at IS NULL OR activity_status <> party_status;

SET @party_status_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'party_members'
    AND INDEX_NAME = 'idx_party_members_party_status'
);
SET @party_status_index_sql = IF(
  @party_status_index_exists = 0,
  'CREATE INDEX idx_party_members_party_status ON party_members (village_id, party_status)',
  'SELECT 1'
);
PREPARE stmt FROM @party_status_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP PROCEDURE IF EXISTS add_party_member_status_column;
