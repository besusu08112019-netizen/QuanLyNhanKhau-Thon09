CREATE TABLE IF NOT EXISTS document_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_document_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS village_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_code VARCHAR(40) NOT NULL UNIQUE,
  document_number VARCHAR(120) NOT NULL,
  title VARCHAR(255) NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  signer_name VARCHAR(255) NULL,
  issued_date DATE NOT NULL,
  effective_date DATE NULL,
  area_code VARCHAR(80) NULL,
  summary TEXT NULL,
  status ENUM('ACTIVE','ARCHIVED','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_village_documents_number (document_number),
  KEY idx_village_documents_title (title),
  KEY idx_village_documents_category (category_id),
  KEY idx_village_documents_issued (issued_date),
  KEY idx_village_documents_area (area_code),
  KEY idx_village_documents_status (status),
  CONSTRAINT fk_village_documents_category FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS village_document_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('PDF','DOCUMENT','IMAGE','OTHER') NOT NULL DEFAULT 'PDF',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_village_document_attachments_document (document_id),
  CONSTRAINT fk_village_document_attachments_document FOREIGN KEY (document_id) REFERENCES village_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_categories (code, name, sort_order) VALUES
  ('notice', 'Thông báo', 10),
  ('decision', 'Quyết định', 20),
  ('official_dispatch', 'Công văn', 30),
  ('plan', 'Kế hoạch', 40),
  ('report', 'Báo cáo', 50),
  ('minutes', 'Biên bản', 60)
ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order), is_active=1;
