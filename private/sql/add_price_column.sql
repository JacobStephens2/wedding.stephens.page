-- Migration script to add price column to registry_items table
-- Run this as MySQL root user: mysql -u root -p wedding_stephens_page < private/add_price_column.sql

USE wedding_stephens_page;

-- Add price column to registry_items table
-- If the column already exists, this will produce an error which can be safely ignored
SET @dbname = DATABASE();
SET @tablename = 'registry_items';
SET @columnname = 'price';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DECIMAL(10, 2) AFTER image_url')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

