CREATE TABLE IF NOT EXISTS calendar_event_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT '#0d6efd',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_calendar_event_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category_id BIGINT UNSIGNED NULL,
  location VARCHAR(255) NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  reminder_at DATETIME NULL,
  host_user_id BIGINT UNSIGNED NULL,
  host_name VARCHAR(255) NULL,
  area_code VARCHAR(80) NULL,
  status ENUM('SCHEDULED','DONE','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  note TEXT NULL,
  soft_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_calendar_events_search (event_code, title),
  KEY idx_calendar_events_category (category_id),
  KEY idx_calendar_events_time (start_at, end_at),
  KEY idx_calendar_events_reminder (reminder_at),
  KEY idx_calendar_events_host (host_user_id),
  KEY idx_calendar_events_area (area_code),
  KEY idx_calendar_events_status (status),
  KEY idx_calendar_events_soft_status (soft_status),
  CONSTRAINT fk_calendar_events_category FOREIGN KEY (category_id) REFERENCES calendar_event_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_event_attendees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  attendee_name VARCHAR(255) NOT NULL,
  phone VARCHAR(40) NULL,
  role_name VARCHAR(120) NULL,
  attendance_status ENUM('INVITED','ATTENDED','ABSENT','EXCUSED') NOT NULL DEFAULT 'INVITED',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_calendar_event_attendees_event (event_id),
  KEY idx_calendar_event_attendees_status (attendance_status),
  CONSTRAINT fk_calendar_event_attendees_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_event_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('IMAGE','VIDEO','PDF','DOCUMENT','OTHER') NOT NULL DEFAULT 'OTHER',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_calendar_event_attachments_event (event_id),
  CONSTRAINT fk_calendar_event_attachments_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO calendar_event_categories (code,name,color,sort_order) VALUES
('meeting','Họp','#0d6efd',10),
('conference','Hội nghị','#6610f2',20),
('duty','Trực','#198754',30),
('vaccination','Tiêm chủng','#20c997',40),
('gift_distribution','Phát quà','#fd7e14',50),
('party_meeting','Sinh hoạt Chi bộ','#dc3545',60),
('union_activity','Sinh hoạt đoàn thể','#6f42c1',70),
('other','Khác','#6c757d',80)
ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), sort_order=VALUES(sort_order), is_active=1;
