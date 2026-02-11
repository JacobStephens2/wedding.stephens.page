-- Add plus-one columns to guests table
-- Run: mysql -u wedding_user -p wedding_stephens_page < private/add_plus_one_columns.sql

USE wedding_stephens_page;

ALTER TABLE guests
    ADD COLUMN has_plus_one TINYINT(1) NOT NULL DEFAULT 0 AFTER rsvp_submitted_at,
    ADD COLUMN plus_one_name VARCHAR(255) DEFAULT NULL AFTER has_plus_one,
    ADD COLUMN plus_one_attending ENUM('yes', 'no') DEFAULT NULL AFTER plus_one_name,
    ADD COLUMN plus_one_dietary TEXT DEFAULT NULL AFTER plus_one_attending;
