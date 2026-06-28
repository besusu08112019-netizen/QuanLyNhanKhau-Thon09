-- Sprint 8 migration notes
-- Run on existing installations before using the Sprint 8 account and permission UI.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS username VARCHAR(60) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER display_name,
  ADD COLUMN IF NOT EXISTS position VARCHAR(120) NULL AFTER phone;

UPDATE users
SET username = LOWER(SUBSTRING_INDEX(email, '@', 1))
WHERE username IS NULL OR username = '';

ALTER TABLE users
  MODIFY role ENUM('SUPER_ADMIN','ADMIN','OFFICER','VIEWER') NOT NULL DEFAULT 'VIEWER';

ALTER TABLE permissions
  MODIFY role ENUM('SUPER_ADMIN','ADMIN','OFFICER','VIEWER') NOT NULL;

INSERT INTO permissions (role, module, action, allowed)
VALUES
('OFFICER','dashboard','read',1),
('OFFICER','household','read',1),('OFFICER','household','create',1),('OFFICER','household','update',1),
('OFFICER','citizen','read',1),('OFFICER','citizen','create',1),('OFFICER','citizen','update',1),
('OFFICER','report','read',1),('OFFICER','import','read',1),('OFFICER','import','create',1),
('VIEWER','dashboard','read',1),('VIEWER','household','read',1),('VIEWER','citizen','read',1),('VIEWER','report','read',1)
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

-- MySQL before 8.0 may not support ADD COLUMN IF NOT EXISTS.
-- In that case, check columns first in phpMyAdmin and run only missing ALTER statements.
