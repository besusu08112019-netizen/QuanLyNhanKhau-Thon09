-- Multi-tenant administrative management seed data
-- Import after database/schema.sql.


INSERT INTO `villages` (`code`, `name`, `unit_name`, `commune_name`, `domain`, `subdomain`, `status`) VALUES
('default', 'Ten thon', 'Ten don vi', 'Ten xa', NULL, NULL, 'ACTIVE')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `unit_name` = VALUES(`unit_name`), `commune_name` = VALUES(`commune_name`);


INSERT INTO `settings` (`village_id`, `setting_key`, `setting_value`) VALUES
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'unitName', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'hamletName', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'communeName', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'systemName', 'He thong Quan ly Hanh chinh'),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'address', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'phone', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'email', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'website', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'logoUrl', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'backgroundUrl', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'backgroundImages', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'themeColor', '#0b6b3a'),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'backgroundColor', '#eef3f8'),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'backupSchedule', 'DAILY'),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'reportSigner', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'reportTitlePrefix', 'Quan ly nhan khau'),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'supportEmail', ''),
((SELECT `id` FROM `villages` WHERE `code` = 'default' LIMIT 1), 'maintenanceMessage', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `permissions` (`role`, `module`, `action`, `allowed`) VALUES
('ADMIN','dashboard','read',1),('ADMIN','household','read',1),('ADMIN','household','create',1),('ADMIN','household','update',1),('ADMIN','household','delete',1),('ADMIN','citizen','read',1),('ADMIN','citizen','create',1),('ADMIN','citizen','update',1),('ADMIN','citizen','delete',1),('ADMIN','movement','read',1),('ADMIN','movement','create',1),('ADMIN','movement','update',1),('ADMIN','movement','delete',1),('ADMIN','report','read',1),('ADMIN','report','export',1),('ADMIN','pdf','read',1),('ADMIN','pdf','export',1),('ADMIN','import','read',1),('ADMIN','import','create',1),('ADMIN','backup','read',1),('ADMIN','backup','create',1),('ADMIN','backup','update',1),('ADMIN','user','read',1),('ADMIN','user','create',1),('ADMIN','user','update',1),('ADMIN','user','delete',1),('ADMIN','permission','read',1),('ADMIN','permission','update',1),('ADMIN','logs','read',1),('ADMIN','settings','read',1),('ADMIN','settings','update',1),
('OFFICER','dashboard','read',1),('OFFICER','household','read',1),('OFFICER','household','create',1),('OFFICER','household','update',1),('OFFICER','household','delete',1),('OFFICER','citizen','read',1),('OFFICER','citizen','create',1),('OFFICER','citizen','update',1),('OFFICER','citizen','delete',1),('OFFICER','movement','read',1),('OFFICER','movement','create',1),('OFFICER','movement','update',1),('OFFICER','report','read',1),('OFFICER','report','export',1),('OFFICER','pdf','read',1),('OFFICER','pdf','export',1),('OFFICER','import','read',1),('OFFICER','import','create',1),
('VIEWER','dashboard','read',1),('VIEWER','household','read',1),('VIEWER','citizen','read',1),('VIEWER','report','read',1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

INSERT INTO `permissions` (`role`, `module`, `action`, `allowed`)
VALUES
('ADMIN','household_business','read',1),
('ADMIN','household_business','create',1),
('ADMIN','household_business','update',1),
('ADMIN','household_business','delete',1),
('OFFICER','household_business','read',1),
('OFFICER','household_business','create',1),
('OFFICER','household_business','update',1),
('VIEWER','household_business','read',1)
ON DUPLICATE KEY UPDATE `allowed` = VALUES(`allowed`);

