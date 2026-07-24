CREATE TABLE IF NOT EXISTS work_task_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_work_task_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_task_priorities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_work_task_priorities_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_task_statuses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_terminal TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_work_task_statuses_active (is_active),
  KEY idx_work_task_statuses_terminal (is_terminal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category_id BIGINT UNSIGNED NULL,
  priority_id BIGINT UNSIGNED NULL,
  status_id BIGINT UNSIGNED NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  assigned_name VARCHAR(255) NULL,
  start_at DATETIME NULL,
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  related_module VARCHAR(80) NULL,
  related_id BIGINT UNSIGNED NULL,
  area_code VARCHAR(80) NULL,
  note TEXT NULL,
  soft_status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_work_tasks_search (task_code, title),
  KEY idx_work_tasks_category (category_id),
  KEY idx_work_tasks_priority (priority_id),
  KEY idx_work_tasks_status (status_id),
  KEY idx_work_tasks_assigned (assigned_user_id),
  KEY idx_work_tasks_due (due_at),
  KEY idx_work_tasks_area (area_code),
  KEY idx_work_tasks_related (related_module, related_id),
  KEY idx_work_tasks_soft_status (soft_status),
  CONSTRAINT fk_work_tasks_category FOREIGN KEY (category_id) REFERENCES work_task_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_work_tasks_priority FOREIGN KEY (priority_id) REFERENCES work_task_priorities(id) ON DELETE SET NULL,
  CONSTRAINT fk_work_tasks_status FOREIGN KEY (status_id) REFERENCES work_task_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_task_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(255) NULL,
  content TEXT NOT NULL,
  status_id BIGINT UNSIGNED NULL,
  progress_percent TINYINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_work_task_logs_task (task_id),
  KEY idx_work_task_logs_status (status_id),
  CONSTRAINT fk_work_task_logs_task FOREIGN KEY (task_id) REFERENCES work_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_work_task_logs_status FOREIGN KEY (status_id) REFERENCES work_task_statuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_task_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id BIGINT UNSIGNED NOT NULL,
  log_id BIGINT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('IMAGE','VIDEO','PDF','DOCUMENT','OTHER') NOT NULL DEFAULT 'OTHER',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_work_task_attachments_task (task_id),
  KEY idx_work_task_attachments_log (log_id),
  KEY idx_work_task_attachments_kind (file_kind),
  CONSTRAINT fk_work_task_attachments_task FOREIGN KEY (task_id) REFERENCES work_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_work_task_attachments_log FOREIGN KEY (log_id) REFERENCES work_task_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO work_task_categories (code,name,sort_order) VALUES
('fund_collection','Thu quỹ',10),
('household_check','Kiểm tra hộ',20),
('gift_distribution','Phát quà',30),
('environment_cleanup','Vệ sinh môi trường',40),
('patrol','Tuần tra',50),
('public_asset_check','Kiểm tra công trình',60),
('production_check','Kiểm tra sản xuất',70),
('other','Khác',80)
ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1;

INSERT INTO work_task_priorities (code,name,sort_order) VALUES
('URGENT','Khẩn cấp',10),
('HIGH','Cao',20),
('NORMAL','Bình thường',30),
('LOW','Thấp',40)
ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1;

INSERT INTO work_task_statuses (code,name,progress_percent,is_terminal,sort_order) VALUES
('NEW','Mới tạo',0,0,10),
('ASSIGNED','Đã giao',10,0,20),
('IN_PROGRESS','Đang thực hiện',50,0,30),
('WAITING','Tạm dừng/chờ xử lý',50,0,40),
('DONE','Đã hoàn thành',100,1,50),
('CANCELLED','Đã hủy',0,1,60)
ON DUPLICATE KEY UPDATE name=VALUES(name), progress_percent=VALUES(progress_percent), is_terminal=VALUES(is_terminal), sort_order=VALUES(sort_order), is_active=1;
