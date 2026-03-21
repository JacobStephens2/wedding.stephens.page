-- Seating chart tables
CREATE TABLE IF NOT EXISTS seating_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number INT NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    capacity INT DEFAULT 10,
    notes TEXT,
    pos_x FLOAT DEFAULT NULL,
    pos_y FLOAT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (table_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add seating_table_id to guests
ALTER TABLE guests ADD COLUMN seating_table_id INT DEFAULT NULL;
ALTER TABLE guests ADD COLUMN seat_number INT DEFAULT NULL;
ALTER TABLE guests ADD CONSTRAINT fk_guest_seating_table FOREIGN KEY (seating_table_id) REFERENCES seating_tables(id) ON DELETE SET NULL;
