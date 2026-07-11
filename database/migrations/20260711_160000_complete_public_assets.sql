-- Complete administrative fields for public facilities.
-- No asset value, investment budget, construction cost, depreciation, technical dossier, settlement, revenue/expense, or maintenance data.

ALTER TABLE public_assets
  ADD COLUMN IF NOT EXISTS construction_year SMALLINT UNSIGNED NULL AFTER building_area,
  ADD COLUMN IF NOT EXISTS operation_year SMALLINT UNSIGNED NULL AFTER construction_year,
  ADD COLUMN IF NOT EXISTS gps_updated_at DATETIME NULL AFTER gps_accuracy,
  ADD COLUMN IF NOT EXISTS manager_position VARCHAR(255) NULL AFTER manager_name;