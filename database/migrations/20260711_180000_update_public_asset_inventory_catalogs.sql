ALTER TABLE public_asset_inventory_items
  MODIFY condition_status ENUM('NEW','GOOD','IN_USE','MAINTENANCE','LIGHT_DAMAGE','HEAVY_DAMAGE','NEEDS_REPAIR','LIQUIDATED','DELETED') NOT NULL DEFAULT 'IN_USE';

INSERT INTO public_asset_inventory_groups (name, parent_name, sort_order) VALUES
('Thiết bị điện tử','Thiết bị điện tử',105),
('Thiết bị PCCC','Thiết bị PCCC',185),
('Thiết bị văn phòng','Thiết bị khác',270)
ON DUPLICATE KEY UPDATE parent_name=VALUES(parent_name), sort_order=VALUES(sort_order), is_active=1;
