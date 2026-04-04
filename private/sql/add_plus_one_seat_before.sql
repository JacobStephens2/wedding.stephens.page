-- Allow plus-ones to be seated before their primary guest
ALTER TABLE guests ADD COLUMN plus_one_seat_before TINYINT(1) NOT NULL DEFAULT 0 AFTER plus_one_is_infant;
