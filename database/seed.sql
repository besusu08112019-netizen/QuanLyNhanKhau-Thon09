-- Multi-tenant administrative management seed data
-- Import after database/schema.sql.

SET NAMES utf8mb4;
SET time_zone = '+07:00';

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('unitName', ''),
('hamletName', ''),
('communeName', ''),
('systemName', 'He thong Quan ly Hanh chinh'),
('address', ''),
('phone', ''),
('email', ''),
('website', ''),
('logoUrl', ''),
('backgroundUrl', ''),
('backgroundImages', ''),
('themeColor', '#0b6b3a'),
('backgroundColor', '#eef3f8'),
('backupSchedule', 'DAILY'),
('reportSigner', ''),
('reportTitlePrefix', 'Quáº£n lÃ½ nhÃ¢n kháº©u'),
('supportEmail', ''),
('maintenanceMessage', '')
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

