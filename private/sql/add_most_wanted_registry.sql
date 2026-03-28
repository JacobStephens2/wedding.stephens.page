-- Add most_wanted column to registry_items
USE wedding_stephens_page;
ALTER TABLE registry_items
    ADD COLUMN most_wanted BOOLEAN DEFAULT FALSE AFTER purchased_by;
CREATE INDEX idx_most_wanted ON registry_items (most_wanted);
