CREATE TABLE IF NOT EXISTS platform_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  setting_type VARCHAR(30) NOT NULL DEFAULT 'string',
  setting_group VARCHAR(60) NOT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_platform_settings_key (setting_key),
  KEY idx_platform_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO platform_settings (setting_key, setting_value, setting_type, setting_group, is_secret, created_at, updated_at) VALUES
('general.platform_name', 'HONG PHONG COMMUNITY PLATFORM', 'string', 'general', 0, NOW(), NOW()),
('general.admin_name', 'Community Control Center', 'string', 'general', 0, NOW(), NOW()),
('general.parent_unit_name', 'XÃ£ Há»“ng Phong', 'string', 'general', 0, NOW(), NOW()),
('general.province_name', 'Ninh BÃ¬nh', 'string', 'general', 0, NOW(), NOW()),
('general.timezone', 'Asia/Ho_Chi_Minh', 'string', 'general', 0, NOW(), NOW()),
('general.locale', 'vi_VN', 'string', 'general', 0, NOW(), NOW()),
('general.date_format', 'dd/mm/yyyy', 'string', 'general', 0, NOW(), NOW()),
('general.datetime_format', 'dd/mm/yyyy HH:mm', 'string', 'general', 0, NOW(), NOW()),
('tenant.default_status', 'ACTIVE', 'string', 'tenant', 0, NOW(), NOW()),
('maintenance.platform_enabled', '0', 'boolean', 'maintenance', 0, NOW(), NOW());
