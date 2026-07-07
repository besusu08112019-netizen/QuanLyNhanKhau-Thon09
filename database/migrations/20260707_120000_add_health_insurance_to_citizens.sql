-- Add health insurance fields for population records.
-- Safe to run more than once on MySQL 8+ / MariaDB with IF NOT EXISTS support.
ALTER TABLE citizens
  ADD COLUMN IF NOT EXISTS has_health_insurance TINYINT(1) NOT NULL DEFAULT 0 AFTER presence_status,
  ADD COLUMN IF NOT EXISTS health_insurance_number VARCHAR(20) NULL AFTER has_health_insurance,
  ADD COLUMN IF NOT EXISTS health_insurance_group VARCHAR(100) NULL AFTER health_insurance_number,
  ADD COLUMN IF NOT EXISTS health_insurance_start_date DATE NULL AFTER health_insurance_group,
  ADD COLUMN IF NOT EXISTS health_insurance_end_date DATE NULL AFTER health_insurance_start_date,
  ADD COLUMN IF NOT EXISTS health_insurance_facility VARCHAR(255) NULL AFTER health_insurance_end_date;


CREATE INDEX IF NOT EXISTS idx_citizens_health_insurance ON citizens (has_health_insurance, health_insurance_end_date);
