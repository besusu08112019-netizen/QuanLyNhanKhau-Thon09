-- Backfill default social assistance eligibility for active citizens aged 75+.
-- Safe to run more than once.

UPDATE citizens
SET social_assistance = 1
WHERE status <> 'DELETED'
  AND date_of_birth IS NOT NULL
  AND COALESCE(social_assistance, 0) = 0
  AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 75;
