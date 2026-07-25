-- Keep household presence counts strictly based on actual member presence.
-- If every active living member of a household is AWAY, at_home_count must be 0.

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE OR REPLACE VIEW `v_household_member_counts` AS
SELECT
  h.`id` AS `household_id`,
  h.`village_id` AS `village_id`,
  SUM(CASE
    WHEN c.`id` IS NOT NULL
     AND c.`status` <> 'DELETED'
     AND COALESCE(c.`life_status`, 'ALIVE') <> 'DECEASED'
     AND COALESCE(c.`residency_status`, 'PERMANENT') <> 'TRANSFERRED_OUT'
    THEN 1 ELSE 0
  END) AS `total_members`,
  SUM(CASE
    WHEN c.`id` IS NOT NULL
     AND c.`status` <> 'DELETED'
     AND COALESCE(c.`life_status`, 'ALIVE') <> 'DECEASED'
     AND COALESCE(c.`residency_status`, 'PERMANENT') <> 'TRANSFERRED_OUT'
     AND c.`presence_status` = 'AT_HOME'
    THEN 1 ELSE 0
  END) AS `at_home_count`,
  SUM(CASE
    WHEN c.`id` IS NOT NULL
     AND c.`status` <> 'DELETED'
     AND COALESCE(c.`life_status`, 'ALIVE') <> 'DECEASED'
     AND COALESCE(c.`residency_status`, 'PERMANENT') <> 'TRANSFERRED_OUT'
     AND c.`presence_status` = 'AWAY'
    THEN 1 ELSE 0
  END) AS `away_count`
FROM `households` h
LEFT JOIN `citizens` c
  ON c.`household_id` = h.`id`
 AND c.`village_id` = h.`village_id`
WHERE h.`status` <> 'DELETED'
GROUP BY h.`id`, h.`village_id`;
