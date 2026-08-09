ALTER TABLE user_sessions
  ADD COLUMN IF NOT EXISTS central_user_id BIGINT UNSIGNED NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS central_email VARCHAR(190) NULL AFTER central_user_id,
  ADD COLUMN IF NOT EXISTS central_username VARCHAR(60) NULL AFTER central_email,
  ADD COLUMN IF NOT EXISTS central_display_name VARCHAR(190) NULL AFTER central_username,
  ADD COLUMN IF NOT EXISTS central_role VARCHAR(30) NULL AFTER central_display_name;
