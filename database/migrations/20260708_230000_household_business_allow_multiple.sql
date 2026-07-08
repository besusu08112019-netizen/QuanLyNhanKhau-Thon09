-- Sprint 19 architecture update: allow one household to have many production/business activities.
-- Keep household_id as a foreign key only; do not remove existing data.

SET @hb_household_index_exists := (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'household_business'
      AND INDEX_NAME = 'idx_household_business_household'
);

SET @hb_add_index_sql := IF(@hb_household_index_exists = 0,
    'ALTER TABLE household_business ADD INDEX idx_household_business_household (household_id)',
    'SELECT 1'
);
PREPARE hb_add_index_stmt FROM @hb_add_index_sql;
EXECUTE hb_add_index_stmt;
DEALLOCATE PREPARE hb_add_index_stmt;

SET @hb_unique_exists := (
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'household_business'
      AND INDEX_NAME = 'uq_household_business_household'
);

SET @hb_drop_unique_sql := IF(@hb_unique_exists > 0,
    'ALTER TABLE household_business DROP INDEX uq_household_business_household',
    'SELECT 1'
);
PREPARE hb_drop_unique_stmt FROM @hb_drop_unique_sql;
EXECUTE hb_drop_unique_stmt;
DEALLOCATE PREPARE hb_drop_unique_stmt;
