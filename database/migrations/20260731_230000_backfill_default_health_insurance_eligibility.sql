-- Backfill BHYT defaults for students and citizens aged 70+.
-- This does not change table structure and only turns eligible active citizens on.

UPDATE citizens
SET has_health_insurance = 1
WHERE status <> 'DELETED'
  AND date_of_birth IS NOT NULL
  AND COALESCE(has_health_insurance, 0) = 0
  AND (
    TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 70
    OR (
      (CASE WHEN MONTH(CURDATE()) >= 8 THEN YEAR(CURDATE()) ELSE YEAR(CURDATE()) - 1 END)
      - YEAR(date_of_birth)
    ) <= 17
  );
