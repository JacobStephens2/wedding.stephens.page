-- Migration script to fix URL column sizes in registry_items table
-- Run this as MySQL root user: mysql -u root -p wedding_stephens_page < private/migrate_registry_urls.sql

USE wedding_stephens_page;

-- Alter url column from VARCHAR(500) to TEXT
ALTER TABLE registry_items MODIFY COLUMN url TEXT NOT NULL;

-- Alter image_url column from VARCHAR(500) to TEXT
ALTER TABLE registry_items MODIFY COLUMN image_url TEXT;

