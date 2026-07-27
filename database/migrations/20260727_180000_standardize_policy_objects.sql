-- Standardize citizen policy object fields.
-- Safe to run more than once on MySQL 8+ / MariaDB with IF NOT EXISTS support.
-- This migration only adds specific policy object columns. It does not update
-- or overwrite existing citizen data.
ALTER TABLE citizens
  ADD COLUMN IF NOT EXISTS chemical_warfare_victim TINYINT(1) NOT NULL DEFAULT 0 AFTER sick_soldier,
  ADD COLUMN IF NOT EXISTS imprisoned_resistance_activist TINYINT(1) NOT NULL DEFAULT 0 AFTER chemical_warfare_victim,
  ADD COLUMN IF NOT EXISTS youth_volunteer TINYINT(1) NOT NULL DEFAULT 0 AFTER imprisoned_resistance_activist,
  ADD COLUMN IF NOT EXISTS resistance_hero TINYINT(1) NOT NULL DEFAULT 0 AFTER youth_volunteer,
  ADD COLUMN IF NOT EXISTS revolutionary_activist TINYINT(1) NOT NULL DEFAULT 0 AFTER resistance_hero;

CREATE INDEX IF NOT EXISTS idx_citizens_policy_objects ON citizens (
  martyr_relative,
  wounded_soldier,
  sick_soldier,
  chemical_warfare_victim,
  imprisoned_resistance_activist,
  youth_volunteer,
  resistance_hero,
  revolutionary_activist
);
