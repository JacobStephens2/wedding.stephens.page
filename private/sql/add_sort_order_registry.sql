-- Add manual sort order for registry items
-- Run: mysql -u root -p wedding_stephens_page < private/add_sort_order_registry.sql

USE wedding_stephens_page;

SET @dbname = DATABASE();
SET @tablename = 'registry_items';
SET @columnname = 'sort_order';

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NOT NULL DEFAULT 0 AFTER published')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill: newest first (same as previous created_at DESC). Assign sort_order 1 to newest id, 2 to next, etc.
SET @n = 0;
UPDATE registry_items SET sort_order = (@n := @n + 1) ORDER BY id DESC;

-- If no rows, the UPDATE is a no-op. Add index for ordering.
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_name = @tablename AND table_schema = @dbname AND index_name = 'idx_sort_order') > 0,
  'SELECT 1',
  CONCAT('CREATE INDEX idx_sort_order ON ', @tablename, ' (sort_order)')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
