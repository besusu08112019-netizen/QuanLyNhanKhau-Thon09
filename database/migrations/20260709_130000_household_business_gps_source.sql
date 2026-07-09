ALTER TABLE `household_business`
  ADD COLUMN IF NOT EXISTS `gps_source` ENUM('household','activity') NOT NULL DEFAULT 'household' AFTER `longitude`;
