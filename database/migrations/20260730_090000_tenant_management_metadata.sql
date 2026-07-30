ALTER TABLE `villages`
  ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`,
  ADD COLUMN `locked_at` DATETIME NULL AFTER `deleted_at`,
  ADD COLUMN `locked_by` BIGINT UNSIGNED NULL AFTER `locked_at`,
  ADD COLUMN `lock_reason` VARCHAR(255) NULL AFTER `locked_by`,
  ADD COLUMN `storage_quota_bytes` BIGINT UNSIGNED NULL AFTER `lock_reason`,
  ADD COLUMN `last_status_changed_at` DATETIME NULL AFTER `storage_quota_bytes`,
  ADD COLUMN `last_status_changed_by` BIGINT UNSIGNED NULL AFTER `last_status_changed_at`;

CREATE INDEX `idx_villages_deleted_at` ON `villages` (`deleted_at`);
CREATE INDEX `idx_villages_locked_at` ON `villages` (`locked_at`);
