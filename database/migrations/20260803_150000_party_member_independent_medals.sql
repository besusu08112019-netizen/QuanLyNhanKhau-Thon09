ï»¿-- Party member independent profile and medal lifecycle.
-- Party member profiles may exist without a linked citizen/person record.

ALTER TABLE party_members DROP FOREIGN KEY fk_party_members_citizen;
ALTER TABLE party_members MODIFY COLUMN citizen_id BIGINT UNSIGNED NULL;
ALTER TABLE party_members ADD COLUMN person_id BIGINT UNSIGNED NULL AFTER citizen_id;
ALTER TABLE party_members ADD COLUMN full_name VARCHAR(180) NULL AFTER person_id;
ALTER TABLE party_members ADD COLUMN date_of_birth DATE NULL AFTER full_name;
ALTER TABLE party_members ADD COLUMN gender VARCHAR(20) NULL AFTER date_of_birth;
ALTER TABLE party_members ADD COLUMN identity_number VARCHAR(40) NULL AFTER gender;
ALTER TABLE party_members ADD COLUMN address VARCHAR(255) NULL AFTER identity_number;
ALTER TABLE party_members ADD COLUMN phone VARCHAR(40) NULL AFTER address;
ALTER TABLE party_members ADD COLUMN medal_status VARCHAR(20) NOT NULL DEFAULT 'WAITING' AFTER note;
ALTER TABLE party_members ADD COLUMN award_date DATE NULL AFTER medal_status;
ALTER TABLE party_members ADD COLUMN award_decision VARCHAR(120) NULL AFTER award_date;
ALTER TABLE party_members ADD COLUMN award_note TEXT NULL AFTER award_decision;

UPDATE party_members pm
LEFT JOIN citizens c ON c.id = pm.citizen_id AND c.village_id = pm.village_id
LEFT JOIN households h ON h.id = c.household_id AND h.village_id = c.village_id
SET pm.person_id = COALESCE(pm.person_id, pm.citizen_id),
    pm.full_name = COALESCE(NULLIF(pm.full_name, ''), c.full_name, 'Chua cap nhat'),
    pm.date_of_birth = COALESCE(pm.date_of_birth, c.date_of_birth),
    pm.gender = COALESCE(NULLIF(pm.gender, ''), c.gender),
    pm.identity_number = COALESCE(NULLIF(pm.identity_number, ''), c.identity_number),
    pm.phone = COALESCE(NULLIF(pm.phone, ''), c.phone),
    pm.address = COALESCE(NULLIF(pm.address, ''), h.address),
    pm.joined_party_date = COALESCE(pm.joined_party_date, CURDATE()),
    pm.official_party_date = COALESCE(pm.official_party_date, pm.joined_party_date, CURDATE()),
    pm.medal_status = COALESCE(NULLIF(pm.medal_status, ''), 'WAITING');

ALTER TABLE party_members MODIFY COLUMN full_name VARCHAR(180) NOT NULL;
ALTER TABLE party_members MODIFY COLUMN joined_party_date DATE NOT NULL;
ALTER TABLE party_members MODIFY COLUMN official_party_date DATE NOT NULL;
CREATE INDEX idx_party_members_person ON party_members (village_id, person_id);
CREATE INDEX idx_party_members_medal ON party_members (village_id, medal_status);

CREATE TABLE IF NOT EXISTS party_member_medal_awards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  party_member_id BIGINT UNSIGNED NOT NULL,
  medal_years INT NOT NULL,
  eligible_date DATE NULL,
  award_date DATE NOT NULL,
  award_decision VARCHAR(120) NULL,
  award_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_party_medal_awards_member_year (village_id, party_member_id, medal_years),
  KEY idx_party_medal_awards_member (village_id, party_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
