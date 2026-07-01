-- Sprint 12 - Population movement automation
-- Safe for MariaDB/MySQL hosting that supports IF NOT EXISTS.

ALTER TABLE citizens
  MODIFY COLUMN residency_status ENUM('PERMANENT','TEMPORARY','TRANSFERRED_OUT') NOT NULL DEFAULT 'PERMANENT' COMMENT 'PERMANENT=Thuong tru, TEMPORARY=Tam tru, TRANSFERRED_OUT=Da chuyen di',
  ADD COLUMN IF NOT EXISTS move_out_date DATE NULL AFTER presence_status,
  ADD COLUMN IF NOT EXISTS move_out_place VARCHAR(255) NULL AFTER move_out_date,
  ADD COLUMN IF NOT EXISTS move_out_reason VARCHAR(255) NULL AFTER move_out_place,
  ADD COLUMN IF NOT EXISTS move_in_date DATE NULL AFTER move_out_reason,
  ADD COLUMN IF NOT EXISTS move_in_place VARCHAR(255) NULL AFTER move_in_date,
  ADD COLUMN IF NOT EXISTS move_in_type VARCHAR(120) NULL AFTER move_in_place,
  ADD COLUMN IF NOT EXISTS formation_source VARCHAR(120) NULL AFTER move_in_type,
  ADD COLUMN IF NOT EXISTS decision_number VARCHAR(100) NULL AFTER formation_source;

ALTER TABLE households
  MODIFY COLUMN status ENUM('ACTIVE','INACTIVE','TRANSFERRED_OUT','ENDED','MERGED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  ADD COLUMN IF NOT EXISTS household_move_out_date DATE NULL AFTER household_type,
  ADD COLUMN IF NOT EXISTS household_move_out_place VARCHAR(255) NULL AFTER household_move_out_date,
  ADD COLUMN IF NOT EXISTS household_move_in_date DATE NULL AFTER household_move_out_place,
  ADD COLUMN IF NOT EXISTS household_move_in_place VARCHAR(255) NULL AFTER household_move_in_date;

ALTER TABLE movements
  MODIFY COLUMN type ENUM('BIRTH','DEATH','MOVE_IN','MOVE_OUT','HOUSEHOLD_SPLIT','HOUSEHOLD_MERGE','HOUSEHOLD_HEAD_CHANGE','CITIZEN_UPDATE','RESTORE','TEMPORARY_RESIDENCE','TEMPORARY_ABSENCE','OTHER') NOT NULL DEFAULT 'OTHER',
  ADD COLUMN IF NOT EXISTS object_type VARCHAR(50) NULL AFTER household_id,
  ADD COLUMN IF NOT EXISTS object_id BIGINT UNSIGNED NULL AFTER object_type,
  ADD COLUMN IF NOT EXISTS object_code VARCHAR(80) NULL AFTER object_id,
  ADD COLUMN IF NOT EXISTS actor_name VARCHAR(190) NULL AFTER object_code,
  ADD COLUMN IF NOT EXISTS before_data JSON NULL AFTER note,
  ADD COLUMN IF NOT EXISTS after_data JSON NULL AFTER before_data;

CREATE INDEX IF NOT EXISTS idx_citizens_move_out_date ON citizens (move_out_date);
CREATE INDEX IF NOT EXISTS idx_citizens_move_in_date ON citizens (move_in_date);
CREATE INDEX IF NOT EXISTS idx_households_business_status ON households (status);
CREATE INDEX IF NOT EXISTS idx_movements_object ON movements (object_type, object_id);
CREATE INDEX IF NOT EXISTS idx_movements_created_by ON movements (created_by);

CREATE OR REPLACE VIEW v_household_member_counts AS
SELECT
  h.id AS household_id,
  COUNT(c.id) AS total_members,
  SUM(CASE WHEN c.presence_status = 'AT_HOME' THEN 1 ELSE 0 END) AS at_home_count,
  SUM(CASE WHEN c.presence_status = 'AWAY' THEN 1 ELSE 0 END) AS away_count
FROM households h
LEFT JOIN citizens c
  ON c.household_id = h.id
 AND c.status = 'ACTIVE'
 AND c.life_status = 'ALIVE'
 AND c.residency_status <> 'TRANSFERRED_OUT'
GROUP BY h.id;
