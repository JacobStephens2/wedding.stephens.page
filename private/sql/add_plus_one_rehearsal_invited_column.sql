USE wedding_stephens_page;

ALTER TABLE guests ADD COLUMN plus_one_rehearsal_invited TINYINT(1) NOT NULL DEFAULT 0 AFTER rehearsal_invited;
