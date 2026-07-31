CREATE TABLE IF NOT EXISTS policy_subject_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_policy_subject_types_village_code (village_id, code),
  KEY idx_policy_subject_types_village_active (village_id, is_active, deleted_at),
  KEY idx_policy_subject_types_order (village_id, display_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS citizen_policy_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  policy_type_id BIGINT UNSIGNED NOT NULL,
  benefit_level VARCHAR(160) NULL,
  decision_number VARCHAR(120) NULL,
  decision_date DATE NULL,
  issuing_authority VARCHAR(180) NULL,
  benefit_start_date DATE NOT NULL,
  benefit_end_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_citizen_policy_village_type_status (village_id, policy_type_id, status),
  KEY idx_citizen_policy_citizen_type (village_id, citizen_id, policy_type_id, status),
  KEY idx_citizen_policy_dates (village_id, benefit_start_date, benefit_end_date),
  CONSTRAINT fk_citizen_policy_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT,
  CONSTRAINT fk_citizen_policy_type FOREIGN KEY (policy_type_id) REFERENCES policy_subject_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policy_subject_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  record_id BIGINT UNSIGNED NOT NULL,
  file_type VARCHAR(60) NOT NULL DEFAULT 'OTHER',
  original_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_policy_attachments_record (village_id, record_id, deleted_at),
  CONSTRAINT fk_policy_attachments_record FOREIGN KEY (record_id) REFERENCES citizen_policy_records(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policy_subject_change_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  record_id BIGINT UNSIGNED NULL,
  citizen_id BIGINT UNSIGNED NULL,
  policy_type_id BIGINT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_policy_logs_record (village_id, record_id),
  KEY idx_policy_logs_citizen (village_id, citizen_id),
  KEY idx_policy_logs_created (village_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
