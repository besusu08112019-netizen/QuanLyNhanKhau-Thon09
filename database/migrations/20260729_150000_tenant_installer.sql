ALTER TABLE `villages`
  MODIFY COLUMN `status` ENUM('ACTIVE','INACTIVE','CREATING','READY','FAILED','DISABLED','MAINTENANCE') NOT NULL DEFAULT 'CREATING';

UPDATE `villages` SET `status` = 'READY' WHERE `status` = 'ACTIVE';
UPDATE `villages` SET `status` = 'DISABLED' WHERE `status` = 'INACTIVE';

CREATE TABLE IF NOT EXISTS `tenant_install_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `village_id` BIGINT UNSIGNED NULL,
  `code` VARCHAR(50) NOT NULL,
  `status` ENUM('DRY_RUN_PASSED','CREATING','READY','FAILED','WAITING_MANUAL','ROLLED_BACK') NOT NULL DEFAULT 'CREATING',
  `current_step` VARCHAR(80) NULL,
  `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `input_json` JSON NOT NULL,
  `manual_action_json` JSON NULL,
  `error_code` VARCHAR(80) NULL,
  `error_message` TEXT NULL,
  `result_json` JSON NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `finished_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_install_jobs_code_status` (`code`, `status`),
  KEY `idx_tenant_install_jobs_village` (`village_id`),
  KEY `idx_tenant_install_jobs_status` (`status`),
  CONSTRAINT `fk_tenant_install_jobs_village` FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tenant_install_jobs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_install_job_steps` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `step_key` VARCHAR(80) NOT NULL,
  `status` ENUM('PENDING','RUNNING','DONE','FAILED','WAITING_MANUAL','SKIPPED','ROLLED_BACK') NOT NULL DEFAULT 'PENDING',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `message` TEXT NULL,
  `details_json` JSON NULL,
  `started_at` DATETIME NULL,
  `finished_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_install_job_steps_job_step` (`job_id`, `step_key`),
  KEY `idx_tenant_install_job_steps_status` (`status`),
  CONSTRAINT `fk_tenant_install_job_steps_job` FOREIGN KEY (`job_id`) REFERENCES `tenant_install_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_install_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` BIGINT UNSIGNED NOT NULL,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `actor_email` VARCHAR(190) NULL,
  `step_key` VARCHAR(80) NULL,
  `event` VARCHAR(80) NOT NULL,
  `level` ENUM('INFO','WARN','ERROR') NOT NULL DEFAULT 'INFO',
  `message` TEXT NULL,
  `details_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_install_audit_logs_job` (`job_id`),
  KEY `idx_tenant_install_audit_logs_event` (`event`),
  KEY `idx_tenant_install_audit_logs_created` (`created_at`),
  CONSTRAINT `fk_tenant_install_audit_logs_job` FOREIGN KEY (`job_id`) REFERENCES `tenant_install_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tenant_install_audit_logs_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
