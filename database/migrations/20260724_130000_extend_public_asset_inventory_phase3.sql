ALTER TABLE public_asset_inventory_items
  ADD COLUMN estimated_value DECIMAL(15,2) NULL AFTER quantity,
  ADD COLUMN purchase_date DATE NULL AFTER unit,
  ADD COLUMN warranty_until DATE NULL AFTER purchase_date,
  ADD COLUMN manager_name VARCHAR(255) NULL AFTER location_in_asset,
  ADD COLUMN manager_phone VARCHAR(80) NULL AFTER manager_name,
  ADD COLUMN maintenance_cycle VARCHAR(120) NULL AFTER manager_phone;

CREATE TABLE IF NOT EXISTS public_asset_maintenance_schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_asset_id BIGINT UNSIGNED NOT NULL,
  inventory_item_id BIGINT UNSIGNED NULL,
  maintenance_code VARCHAR(60) NOT NULL,
  title VARCHAR(255) NOT NULL,
  scheduled_date DATE NOT NULL,
  completed_at DATETIME NULL,
  manager_name VARCHAR(255) NULL,
  cost DECIMAL(15,2) NULL,
  status ENUM('SCHEDULED','DONE','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_public_asset_maintenance_code (maintenance_code),
  KEY idx_public_asset_maintenance_asset (public_asset_id),
  KEY idx_public_asset_maintenance_item (inventory_item_id),
  KEY idx_public_asset_maintenance_status (status),
  KEY idx_public_asset_maintenance_due (scheduled_date),
  CONSTRAINT fk_public_asset_maintenance_asset FOREIGN KEY (public_asset_id) REFERENCES public_assets(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_asset_maintenance_item FOREIGN KEY (inventory_item_id) REFERENCES public_asset_inventory_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
