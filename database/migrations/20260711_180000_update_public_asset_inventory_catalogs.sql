ALTER TABLE public_asset_inventory_items
  MODIFY condition_status ENUM('NEW','GOOD','IN_USE','MAINTENANCE','LIGHT_DAMAGE','HEAVY_DAMAGE','NEEDS_REPAIR','LIQUIDATED','DELETED') NOT NULL DEFAULT 'IN_USE';

INSERT INTO public_asset_inventory_groups (name, parent_name, sort_order) VALUES
('Thiáº¿t bá»‹ Ä‘iá»‡n tá»­','Thiáº¿t bá»‹ Ä‘iá»‡n tá»­',105),
('Thiáº¿t bá»‹ PCCC','Thiáº¿t bá»‹ PCCC',185),
('Thiáº¿t bá»‹ vÄƒn phÃ²ng','Thiáº¿t bá»‹ khÃ¡c',270)
ON DUPLICATE KEY UPDATE parent_name=VALUES(parent_name), sort_order=VALUES(sort_order), is_active=1;
