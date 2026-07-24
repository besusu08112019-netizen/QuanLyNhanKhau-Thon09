ALTER TABLE complaint_links MODIFY target_id BIGINT UNSIGNED NOT NULL DEFAULT 0;

SET @complaint_link_index_columns := (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'complaint_links'
    AND INDEX_NAME = 'uq_complaint_links_target'
);

SET @drop_complaint_link_index_sql := IF(
  @complaint_link_index_columns IS NOT NULL AND @complaint_link_index_columns <> 'complaint_id,target_type,target_id,label',
  'ALTER TABLE complaint_links DROP INDEX uq_complaint_links_target',
  'SELECT 1'
);
PREPARE drop_complaint_link_index_stmt FROM @drop_complaint_link_index_sql;
EXECUTE drop_complaint_link_index_stmt;
DEALLOCATE PREPARE drop_complaint_link_index_stmt;

SET @complaint_link_index_columns := (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'complaint_links'
    AND INDEX_NAME = 'uq_complaint_links_target'
);

SET @add_complaint_link_index_sql := IF(
  @complaint_link_index_columns IS NULL,
  'ALTER TABLE complaint_links ADD UNIQUE KEY uq_complaint_links_target (complaint_id, target_type, target_id, label)',
  'SELECT 1'
);
PREPARE add_complaint_link_index_stmt FROM @add_complaint_link_index_sql;
EXECUTE add_complaint_link_index_stmt;
DEALLOCATE PREPARE add_complaint_link_index_stmt;
