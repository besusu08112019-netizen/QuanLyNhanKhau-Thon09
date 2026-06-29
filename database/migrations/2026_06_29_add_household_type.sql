-- Add normalized household category field for future imports and reports.
-- Existing production code remains backward compatible with the legacy boolean flags.

ALTER TABLE households
  ADD COLUMN household_type VARCHAR(50) NULL AFTER area_code;

CREATE INDEX idx_households_household_type ON households (household_type);

UPDATE households
SET household_type = CASE
  WHEN poor_household = 1 THEN 'poor'
  WHEN near_poor_household = 1 THEN 'near_poor'
  WHEN meritorious_family = 1 THEN 'meritorious'
  WHEN disabled_household = 1 THEN 'other'
  ELSE 'normal'
END
WHERE household_type IS NULL OR household_type = '';
