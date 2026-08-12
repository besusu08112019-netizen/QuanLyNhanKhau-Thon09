-- One-time age-based defaults for existing citizen records.
-- Only fills missing/unclassified fields; officer-entered classifications are preserved.

ALTER TABLE citizens
  ADD COLUMN IF NOT EXISTS not_attending_school TINYINT(1) NOT NULL DEFAULT 0 AFTER foreign_labor;

CREATE INDEX IF NOT EXISTS idx_citizens_not_attending_school ON citizens (not_attending_school);

UPDATE citizens
SET
  not_attending_school = CASE
    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 2 THEN 1
    ELSE COALESCE(not_attending_school, 0)
  END,
  pupil = CASE
    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 3 AND 17 THEN 1
    ELSE COALESCE(pupil, 0)
  END,
  student = CASE
    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 21 THEN 1
    ELSE COALESCE(student, 0)
  END,
  employed = CASE
    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 22 THEN 1
    ELSE COALESCE(employed, 0)
  END
WHERE status <> 'DELETED'
  AND date_of_birth IS NOT NULL
  AND COALESCE(employed, 0) = 0
  AND COALESCE(unemployed, 0) = 0
  AND COALESCE(freelance_labor, 0) = 0
  AND COALESCE(out_province_labor, 0) = 0
  AND COALESCE(foreign_labor, 0) = 0
  AND COALESCE(not_attending_school, 0) = 0
  AND COALESCE(pupil, 0) = 0
  AND COALESCE(student, 0) = 0
  AND COALESCE(retired, 0) = 0
  AND (occupation IS NULL OR TRIM(occupation) = '' OR LOWER(TRIM(occupation)) IN ('khac', 'khÃ¡c'));

UPDATE citizens
SET has_health_insurance = 1
WHERE status <> 'DELETED'
  AND has_health_insurance IS NULL;
