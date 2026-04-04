-- Allow plus-ones to be seated at any position within a table (not just immediately before/after primary)
ALTER TABLE guests ADD COLUMN plus_one_seat_number INT DEFAULT NULL AFTER plus_one_seat_before;
