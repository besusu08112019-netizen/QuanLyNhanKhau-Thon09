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

INSERT INTO platform_settings (setting_key, setting_value, setting_type, setting_group, is_secret, created_at, updated_at)
VALUES ('general.copyright', 'Bản quyền thuộc về Thôn 09', 'string', 'general', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    setting_type = VALUES(setting_type),
    setting_group = VALUES(setting_group),
    is_secret = 0,
    updated_at = NOW();
