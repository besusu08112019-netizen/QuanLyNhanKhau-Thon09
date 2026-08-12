-- Extend rural clean water management for Nong thon moi indicators.
-- Additive migration only: no data deletion, no reset, no seed data.

ALTER TABLE rural_clean_water
  MODIFY COLUMN connection_type ENUM('PIPED','BOREHOLE_WELL','DUG_WELL','WELL','RAINWATER','PURCHASED','OTHER') NOT NULL DEFAULT 'PIPED';

ALTER TABLE rural_clean_water
  ADD COLUMN IF NOT EXISTS water_supply_form ENUM('CENTRALIZED','HOUSEHOLD_SCALE','OTHER') NULL AFTER connection_type,
  ADD COLUMN IF NOT EXISTS clean_water_status ENUM('COMPLIANT','NON_COMPLIANT','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN' AFTER is_clean_standard,
  ADD COLUMN IF NOT EXISTS hygienic_water_status ENUM('YES','NO','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN' AFTER clean_water_status,
  ADD COLUMN IF NOT EXISTS has_water_meter ENUM('YES','NO','NOT_APPLICABLE') NOT NULL DEFAULT 'NOT_APPLICABLE' AFTER meter_number,
  ADD COLUMN IF NOT EXISTS verification_basis ENUM('TEST_RESULT','PROVIDER_CONFIRMATION','AUTHORITY_LIST','OTHER','NONE') NOT NULL DEFAULT 'NONE' AFTER test_result,
  ADD COLUMN IF NOT EXISTS confirmation_date DATE NULL AFTER verification_basis,
  ADD COLUMN IF NOT EXISTS confirmation_agency VARCHAR(255) NULL AFTER confirmation_date;

UPDATE rural_clean_water
SET clean_water_status = CASE WHEN is_clean_standard = 1 THEN 'COMPLIANT' ELSE clean_water_status END,
    water_supply_form = CASE
      WHEN water_supply_form IS NOT NULL THEN water_supply_form
      WHEN connection_type = 'PIPED' THEN 'CENTRALIZED'
      WHEN connection_type IN ('BOREHOLE_WELL','DUG_WELL','WELL','RAINWATER') THEN 'HOUSEHOLD_SCALE'
      ELSE 'OTHER'
    END,
    has_water_meter = CASE
      WHEN has_water_meter <> 'NOT_APPLICABLE' THEN has_water_meter
      WHEN NULLIF(TRIM(COALESCE(meter_number,'')), '') IS NOT NULL THEN 'YES'
      ELSE has_water_meter
    END
WHERE status <> 'DELETED';

CREATE INDEX IF NOT EXISTS idx_rural_clean_water_supply_form ON rural_clean_water (water_supply_form);
CREATE INDEX IF NOT EXISTS idx_rural_clean_water_clean_status ON rural_clean_water (clean_water_status);
CREATE INDEX IF NOT EXISTS idx_rural_clean_water_hygienic ON rural_clean_water (hygienic_water_status);
