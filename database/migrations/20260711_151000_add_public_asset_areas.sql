-- Add administrative area fields for public facilities.
-- Area is for local management/statistics only; no asset value, finance, depreciation, construction cost, or technical dossier data.

ALTER TABLE public_assets
  ADD COLUMN IF NOT EXISTS campus_area DECIMAL(14,2) NULL AFTER area_code,
  ADD COLUMN IF NOT EXISTS building_area DECIMAL(14,2) NULL AFTER campus_area;