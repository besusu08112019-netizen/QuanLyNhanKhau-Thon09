CREATE TABLE IF NOT EXISTS organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(190) NOT NULL,
  organization_type VARCHAR(80) NOT NULL DEFAULT 'MASS_ORGANIZATION',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_organizations_village_code (village_id, code),
  KEY idx_organizations_village_status (village_id, status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_positions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NULL,
  organization_code VARCHAR(40) NULL,
  name VARCHAR(190) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_positions_village_org_name (village_id, organization_id, name),
  KEY idx_org_positions_village_org (village_id, organization_id, status, sort_order),
  CONSTRAINT fk_org_positions_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NULL,
  position_id BIGINT UNSIGNED NULL,
  subgroup_name VARCHAR(190) NULL,
  member_number VARCHAR(120) NULL,
  joined_date DATE NULL,
  ended_date DATE NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
  active_member_key VARCHAR(80) NULL,
  note TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_member_current (village_id, organization_id, active_member_key),
  KEY idx_org_members_village_org (village_id, organization_id, status),
  KEY idx_org_members_village_citizen (village_id, citizen_id),
  KEY idx_org_members_village_person (village_id, person_id),
  KEY idx_org_members_position (village_id, position_id),
  CONSTRAINT fk_org_members_org FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_org_members_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id),
  CONSTRAINT fk_org_members_person FOREIGN KEY (person_id) REFERENCES citizens(id),
  CONSTRAINT fk_org_members_position FOREIGN KEY (position_id) REFERENCES organization_positions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organization_member_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NULL,
  old_position_id BIGINT UNSIGNED NULL,
  new_position_id BIGINT UNSIGNED NULL,
  change_type VARCHAR(60) NOT NULL,
  note TEXT NULL,
  changed_by BIGINT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_org_history_village_member (village_id, member_id, changed_at),
  KEY idx_org_history_village_citizen (village_id, citizen_id),
  CONSTRAINT fk_org_history_member FOREIGN KEY (member_id) REFERENCES organization_members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
