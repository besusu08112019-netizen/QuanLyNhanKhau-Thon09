CREATE TABLE IF NOT EXISTS finance_funds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fund_code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  opening_balance DECIMAL(16,2) NOT NULL DEFAULT 0,
  note TEXT NULL,
  status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_finance_funds_status (status),
  KEY idx_finance_funds_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  transaction_type ENUM('INCOME','EXPENSE') NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_finance_categories_type (transaction_type),
  KEY idx_finance_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_code VARCHAR(40) NOT NULL UNIQUE,
  transaction_type ENUM('INCOME','EXPENSE') NOT NULL,
  fund_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  amount DECIMAL(16,2) NOT NULL DEFAULT 0,
  transaction_date DATE NOT NULL,
  payer_name VARCHAR(180) NULL,
  receiver_name VARCHAR(180) NULL,
  payment_method VARCHAR(60) NULL,
  receipt_number VARCHAR(100) NULL,
  description TEXT NULL,
  source_module VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  status ENUM('POSTED','CANCELLED','DELETED') NOT NULL DEFAULT 'POSTED',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_finance_transactions_type (transaction_type),
  KEY idx_finance_transactions_fund (fund_id),
  KEY idx_finance_transactions_category (category_id),
  KEY idx_finance_transactions_date (transaction_date),
  KEY idx_finance_transactions_status (status),
  KEY idx_finance_transactions_receipt (receipt_number),
  KEY idx_finance_transactions_source (source_module, source_id),
  CONSTRAINT fk_finance_transactions_fund FOREIGN KEY (fund_id) REFERENCES finance_funds(id),
  CONSTRAINT fk_finance_transactions_category FOREIGN KEY (category_id) REFERENCES finance_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_transaction_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  file_kind ENUM('PDF','IMAGE','DOCUMENT','OTHER') NOT NULL DEFAULT 'DOCUMENT',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_finance_attachments_transaction (transaction_id),
  CONSTRAINT fk_finance_attachments_transaction FOREIGN KEY (transaction_id) REFERENCES finance_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO finance_funds (fund_code,name,opening_balance)
VALUES ('GENERAL','Quy chung',0)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO finance_categories (code,name,transaction_type,sort_order) VALUES
('CONTRIBUTION','Thu dong gop','INCOME',10),
('SUPPORT','Thu ho tro','INCOME',20),
('OTHER_INCOME','Thu khac','INCOME',90),
('COMMUNITY_ACTIVITY','Chi hoat dong cong dong','EXPENSE',10),
('PUBLIC_ASSET_MAINTENANCE','Chi bao tri cong trinh tai san','EXPENSE',20),
('ENVIRONMENT','Chi ve sinh moi truong','EXPENSE',30),
('OTHER_EXPENSE','Chi khac','EXPENSE',90)
ON DUPLICATE KEY UPDATE name=VALUES(name), transaction_type=VALUES(transaction_type), sort_order=VALUES(sort_order), is_active=1;
