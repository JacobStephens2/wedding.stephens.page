-- Create site_settings key-value table
USE wedding_stephens_page;
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('house_fund_visible', '1'),
    ('honeymoon_fund_visible', '1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
