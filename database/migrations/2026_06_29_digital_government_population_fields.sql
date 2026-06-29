-- Digital government population profile extension
-- Safe to run more than once on MySQL 8+ / MariaDB with IF NOT EXISTS support.
ALTER TABLE citizens
  ADD COLUMN IF NOT EXISTS party_member TINYINT(1) NOT NULL DEFAULT 0 AFTER presence_status,
  ADD COLUMN IF NOT EXISTS youth_union_member TINYINT(1) NOT NULL DEFAULT 0 AFTER party_member,
  ADD COLUMN IF NOT EXISTS women_union_member TINYINT(1) NOT NULL DEFAULT 0 AFTER youth_union_member,
  ADD COLUMN IF NOT EXISTS farmers_union_member TINYINT(1) NOT NULL DEFAULT 0 AFTER women_union_member,
  ADD COLUMN IF NOT EXISTS veterans_union_member TINYINT(1) NOT NULL DEFAULT 0 AFTER farmers_union_member,
  ADD COLUMN IF NOT EXISTS elderly_union_member TINYINT(1) NOT NULL DEFAULT 0 AFTER veterans_union_member,
  ADD COLUMN IF NOT EXISTS meritorious_person TINYINT(1) NOT NULL DEFAULT 0 AFTER elderly_union_member,
  ADD COLUMN IF NOT EXISTS martyr_relative TINYINT(1) NOT NULL DEFAULT 0 AFTER meritorious_person,
  ADD COLUMN IF NOT EXISTS wounded_soldier TINYINT(1) NOT NULL DEFAULT 0 AFTER martyr_relative,
  ADD COLUMN IF NOT EXISTS sick_soldier TINYINT(1) NOT NULL DEFAULT 0 AFTER wounded_soldier,
  ADD COLUMN IF NOT EXISTS disabled_person TINYINT(1) NOT NULL DEFAULT 0 AFTER sick_soldier,
  ADD COLUMN IF NOT EXISTS social_assistance TINYINT(1) NOT NULL DEFAULT 0 AFTER disabled_person,
  ADD COLUMN IF NOT EXISTS employed TINYINT(1) NOT NULL DEFAULT 0 AFTER social_assistance,
  ADD COLUMN IF NOT EXISTS unemployed TINYINT(1) NOT NULL DEFAULT 0 AFTER employed,
  ADD COLUMN IF NOT EXISTS freelance_labor TINYINT(1) NOT NULL DEFAULT 0 AFTER unemployed,
  ADD COLUMN IF NOT EXISTS out_province_labor TINYINT(1) NOT NULL DEFAULT 0 AFTER freelance_labor,
  ADD COLUMN IF NOT EXISTS foreign_labor TINYINT(1) NOT NULL DEFAULT 0 AFTER out_province_labor,
  ADD COLUMN IF NOT EXISTS pupil TINYINT(1) NOT NULL DEFAULT 0 AFTER foreign_labor,
  ADD COLUMN IF NOT EXISTS student TINYINT(1) NOT NULL DEFAULT 0 AFTER pupil,
  ADD COLUMN IF NOT EXISTS retired TINYINT(1) NOT NULL DEFAULT 0 AFTER student;

CREATE INDEX IF NOT EXISTS idx_citizens_party_member ON citizens (party_member);
CREATE INDEX IF NOT EXISTS idx_citizens_youth_union_member ON citizens (youth_union_member);
CREATE INDEX IF NOT EXISTS idx_citizens_policy_flags ON citizens (meritorious_person, disabled_person);
CREATE INDEX IF NOT EXISTS idx_citizens_labor_flags ON citizens (employed, unemployed, out_province_labor, foreign_labor);
