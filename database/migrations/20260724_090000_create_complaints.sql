CREATE TABLE IF NOT EXISTS complaint_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_complaint_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaint_priorities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_complaint_priorities_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaint_statuses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  marker_color VARCHAR(20) NOT NULL DEFAULT 'red',
  is_terminal TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_complaint_statuses_active (is_active),
  KEY idx_complaint_statuses_terminal (is_terminal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaints (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  detail TEXT NOT NULL,
  received_at DATETIME NOT NULL,
  receiver_user_id BIGINT UNSIGNED NULL,
  receiver_name VARCHAR(255) NULL,
  reporter_name VARCHAR(255) NOT NULL,
  reporter_phone VARCHAR(40) NULL,
  household_id BIGINT UNSIGNED NULL,
  citizen_id BIGINT UNSIGNED NULL,
  category_id BIGINT UNSIGNED NULL,
  priority_id BIGINT UNSIGNED NULL,
  status_id BIGINT UNSIGNED NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  assigned_name VARCHAR(255) NULL,
  due_at DATETIME NULL,
  latitude DECIMAL(11,8) NULL,
  longitude DECIMAL(11,8) NULL,
  gps_accuracy DECIMAL(10,2) NULL,
  result_rating ENUM('SATISFIED','NEEDS_MORE','DISAGREE') NULL,
  result_note TEXT NULL,
  closed_at DATETIME NULL,
  soft_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_complaints_search (complaint_code, title),
  KEY idx_complaints_category (category_id),
  KEY idx_complaints_priority (priority_id),
  KEY idx_complaints_status (status_id),
  KEY idx_complaints_assigned (assigned_user_id),
  KEY idx_complaints_receiver (receiver_user_id),
  KEY idx_complaints_household (household_id),
  KEY idx_complaints_citizen (citizen_id),
  KEY idx_complaints_received (received_at),
  KEY idx_complaints_due (due_at),
  KEY idx_complaints_location (latitude, longitude),
  KEY idx_complaints_soft_status (soft_status),
  CONSTRAINT fk_complaints_category FOREIGN KEY (category_id) REFERENCES complaint_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_complaints_priority FOREIGN KEY (priority_id) REFERENCES complaint_priorities(id) ON DELETE SET NULL,
  CONSTRAINT fk_complaints_status FOREIGN KEY (status_id) REFERENCES complaint_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaint_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT UNSIGNED NOT NULL,
  target_type VARCHAR(60) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  label VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_complaint_links_target (complaint_id, target_type, target_id, label),
  KEY idx_complaint_links_target (target_type, target_id),
  CONSTRAINT fk_complaint_links_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaint_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT UNSIGNED NOT NULL,
  history_id BIGINT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('IMAGE','VIDEO','PDF','OTHER') NOT NULL DEFAULT 'OTHER',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_complaint_attachments_complaint (complaint_id),
  KEY idx_complaint_attachments_history (history_id),
  KEY idx_complaint_attachments_kind (file_kind),
  CONSTRAINT fk_complaint_attachments_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaint_histories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(255) NULL,
  content TEXT NOT NULL,
  status_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_complaint_histories_complaint (complaint_id),
  KEY idx_complaint_histories_status (status_id),
  CONSTRAINT fk_complaint_histories_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_complaint_histories_status FOREIGN KEY (status_id) REFERENCES complaint_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaint_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id BIGINT UNSIGNED NOT NULL,
  assignee_user_id BIGINT UNSIGNED NULL,
  assignee_name VARCHAR(255) NOT NULL,
  assigned_at DATETIME NOT NULL,
  due_at DATETIME NULL,
  note TEXT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_complaint_assignments_complaint (complaint_id),
  KEY idx_complaint_assignments_assignee (assignee_user_id),
  KEY idx_complaint_assignments_due (due_at),
  CONSTRAINT fk_complaint_assignments_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO complaint_categories (code, name, sort_order) VALUES
('security','An ninh tráº­t tá»±',10),('environment','Vá»‡ sinh mÃ´i trÆ°á»ng',20),('electricity','Äiá»‡n',30),('water','NÆ°á»›c',40),
('traffic','Giao thÃ´ng',50),('land','Äáº¥t Ä‘ai',60),('construction','XÃ¢y dá»±ng',70),('noise','Tiáº¿ng á»“n',80),
('pets','Váº­t nuÃ´i',90),('policy','ChÃ­nh sÃ¡ch',100),('poor_household','Há»™ nghÃ¨o',110),('other','KhÃ¡c',120)
ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1;

INSERT INTO complaint_priorities (code, name, sort_order) VALUES
('URGENT','Kháº©n cáº¥p',10),('HIGH','Cao',20),('NORMAL','BÃ¬nh thÆ°á»ng',30),('LOW','Tháº¥p',40)
ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1;

INSERT INTO complaint_statuses (code, name, marker_color, is_terminal, sort_order) VALUES
('NEW','Má»›i tiáº¿p nháº­n','red',0,10),('VERIFYING','Äang xÃ¡c minh','yellow',0,20),('PROCESSING','Äang xá»­ lÃ½','yellow',0,30),
('DONE','ÄÃ£ hoÃ n thÃ nh','green',1,40),('ESCALATED','ÄÃ£ chuyá»ƒn cáº¥p trÃªn','yellow',1,50),('REJECTED','KhÃ´ng Ä‘á»§ Ä‘iá»u kiá»‡n xá»­ lÃ½','red',1,60)
ON DUPLICATE KEY UPDATE name=VALUES(name), marker_color=VALUES(marker_color), is_terminal=VALUES(is_terminal), sort_order=VALUES(sort_order), is_active=1;

ALTER TABLE complaint_links MODIFY target_id BIGINT UNSIGNED NOT NULL DEFAULT 0;
