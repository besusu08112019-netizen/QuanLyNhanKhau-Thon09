-- Sprint 16 - Smart Reporting saved report templates and scheduling-ready metadata.
-- Apply through the normal migration process; do not edit production databases manually.

CREATE TABLE IF NOT EXISTS report_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  type VARCHAR(80) NOT NULL,
  filters_json JSON NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  schedule_enabled TINYINT(1) NOT NULL DEFAULT 0,
  schedule_config JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_report_templates_user (user_id, status),
  INDEX idx_report_templates_default (user_id, is_default),
  INDEX idx_report_templates_schedule (schedule_enabled, status),
  CONSTRAINT fk_report_templates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
