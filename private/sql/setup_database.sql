-- Database setup script for wedding.stephens.page
-- Run this as MySQL root user: mysql -u root -p < private/setup_database.sql

-- Create database
CREATE DATABASE IF NOT EXISTS wedding_stephens_page CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (if it doesn't exist)
CREATE USER IF NOT EXISTS 'wedding_user'@'localhost' IDENTIFIED BY '[password]';

-- Grant privileges
GRANT ALL PRIVILEGES ON wedding_stephens_page.* TO 'wedding_user'@'localhost';
FLUSH PRIVILEGES;

-- Use the database
USE wedding_stephens_page;

-- Create RSVPs table
CREATE TABLE IF NOT EXISTS rsvps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    attending ENUM('Yes', 'No') NOT NULL,
    guests INT NOT NULL DEFAULT 1,
    dietary TEXT,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
