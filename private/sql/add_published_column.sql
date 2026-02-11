-- Migration script to add published column to registry_items table
-- Run this as MySQL root user: mysql -u root -p wedding_stephens_page < private/add_published_column.sql

USE wedding_stephens_page;

-- Add published column to registry_items table
-- Default to TRUE (1) so existing items are published
-- If the column already exists, this will produce an error which can be safely ignored
SET @dbname = DATABASE();
SET @tablename = 'registry_items';
SET @columnname = 'published';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' BOOLEAN DEFAULT TRUE AFTER price')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update all existing items to be published by default
UPDATE registry_items SET published = TRUE WHERE published IS NULL;

-- Add index for published status for better query performance
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'idx_published')
  ) > 0,
  'SELECT 1',
  CONCAT('CREATE INDEX idx_published ON ', @tablename, ' (published)')
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;
