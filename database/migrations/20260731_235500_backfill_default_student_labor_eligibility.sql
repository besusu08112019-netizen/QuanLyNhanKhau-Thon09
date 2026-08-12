-- Backfill default labor classification for school-age citizens.
-- Safe to run more than once. Officer-entered non-empty occupations are preserved.

UPDATE citizens
SET
  not_attending_school = 0,
  pupil = 1,
  student = 0,
  employed = 0,
  unemployed = 0,
  occupation = CASE
    WHEN occupation IS NULL OR TRIM(occupation) = '' OR LOWER(TRIM(occupation)) IN ('khac', 'khÃ¡c') THEN 'Há»c sinh'
    ELSE occupation
  END
WHERE status <> 'DELETED'
  AND date_of_birth IS NOT NULL
  AND ((CASE WHEN MONTH(CURDATE()) >= 8 THEN YEAR(CURDATE()) ELSE YEAR(CURDATE()) - 1 END) - YEAR(date_of_birth) <= 17)
  AND (
    COALESCE(pupil, 0) = 0
    OR COALESCE(not_attending_school, 0) <> 0
    OR COALESCE(student, 0) <> 0
    OR COALESCE(employed, 0) <> 0
    OR COALESCE(unemployed, 0) <> 0
    OR occupation IS NULL
    OR TRIM(occupation) = ''
    OR LOWER(TRIM(occupation)) IN ('khac', 'khÃ¡c')
  );
