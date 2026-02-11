-- Create guests table for invite list management
-- Run: mysql -u root -p wedding_stephens_page < private/create_guests_table.sql

USE wedding_stephens_page;

CREATE TABLE IF NOT EXISTS guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) DEFAULT '',
    group_name VARCHAR(255) DEFAULT '',
    guest_id VARCHAR(20) DEFAULT '',
    mailing_group INT DEFAULT NULL,
    attending ENUM('yes', 'no') DEFAULT NULL,
    dietary TEXT DEFAULT NULL,
    song_request TEXT DEFAULT NULL,
    message TEXT DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    rsvp_submitted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mailing_group (mailing_group),
    INDEX idx_name (first_name, last_name),
    INDEX idx_guest_id (guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
