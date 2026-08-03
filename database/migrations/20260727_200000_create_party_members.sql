-- Create Party Member management module.
-- The table stores only Party-specific data; citizen identity fields are read
-- live from citizens/households to avoid duplicated population data.

CREATE TABLE IF NOT EXISTS party_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  party_member_code VARCHAR(80) NULL,
  party_card_number VARCHAR(80) NULL,
  joined_party_date DATE NULL,
  official_party_date DATE NULL,
  branch_name VARCHAR(180) NULL,
  parent_party_org VARCHAR(180) NULL,
  party_position VARCHAR(180) NULL,
  government_position VARCHAR(180) NULL,
  education_level VARCHAR(180) NULL,
  professional_level VARCHAR(180) NULL,
  political_theory_level VARCHAR(180) NULL,
  member_type VARCHAR(30) NOT NULL DEFAULT 'OFFICIAL',
  activity_status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
  party_status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
  status_changed_at DATE NULL,
  status_reason TEXT NULL,
  decision_number VARCHAR(120) NULL,
  decision_date DATE NULL,
  transfer_to VARCHAR(255) NULL,
  note TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_party_members_village_citizen (village_id, citizen_id),
  UNIQUE KEY uq_party_members_village_code (village_id, party_member_code),
  KEY idx_party_members_branch (village_id, branch_name),
  KEY idx_party_members_type (village_id, member_type),
  KEY idx_party_members_activity_status (village_id, activity_status),
  KEY idx_party_members_party_status (village_id, party_status),
  KEY idx_party_members_position (village_id, party_position),
  KEY idx_party_members_joined_date (joined_party_date),
  CONSTRAINT fk_party_members_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO party_members (village_id, citizen_id, member_type, activity_status, party_status, status_changed_at, status, created_at, updated_at)
SELECT c.village_id, c.id, 'OFFICIAL', 'ACTIVE', 'ACTIVE', CURDATE(), 'ACTIVE', NOW(), NOW()
FROM citizens c
INNER JOIN households h ON h.id = c.household_id AND h.village_id = c.village_id
WHERE c.party_member = 1
  AND c.status <> 'DELETED'
  AND h.status NOT IN ('DELETED','ENDED','MERGED','TRANSFERRED_OUT','MOVED_OUT','INACTIVE');

INSERT IGNORE INTO permissions (role, module, action, allowed, updated_by)
SELECT role_name, 'party_members', action_name, allowed, NULL
FROM (
  SELECT 'OFFICER' AS role_name, 'read' AS action_name, 1 AS allowed UNION ALL
  SELECT 'OFFICER', 'create', 1 UNION ALL
  SELECT 'OFFICER', 'update', 1 UNION ALL
  SELECT 'OFFICER', 'export', 1 UNION ALL
  SELECT 'OFFICER', 'restore', 1 UNION ALL
  SELECT 'VIEWER', 'read', 1
) seed;
