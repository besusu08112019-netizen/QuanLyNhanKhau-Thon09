-- Sprint 15: Household GIS location markers
-- Idempotent migration. Safe to run multiple times on MariaDB/MySQL hosts that support IF NOT EXISTS.

ALTER TABLE households
  ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL,
  ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL,
  ADD COLUMN IF NOT EXISTS location_accuracy INT NULL,
  ADD COLUMN IF NOT EXISTS location_source ENUM('MANUAL','GPS') NOT NULL DEFAULT 'MANUAL',
  ADD COLUMN IF NOT EXISTS location_updated_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS location_updated_by BIGINT NULL;

ALTER TABLE households
  MODIFY COLUMN latitude DECIMAL(10,8) NULL,
  MODIFY COLUMN longitude DECIMAL(11,8) NULL,
  MODIFY COLUMN location_updated_by BIGINT NULL;

CREATE INDEX IF NOT EXISTS idx_households_location ON households(latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_households_area_location ON households(area_code, latitude, longitude);
