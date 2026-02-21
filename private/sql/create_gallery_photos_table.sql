CREATE TABLE IF NOT EXISTS gallery_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(500) NOT NULL,
    alt VARCHAR(500) NOT NULL DEFAULT '',
    photo_date DATE NOT NULL,
    position VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed with existing photos
INSERT INTO gallery_photos (path, alt, photo_date, position) VALUES
('meeting/2024-11-15_Fusion_dance_at_Concierge_Ballroom.jpg', 'Fusion dance at Concierge Ballroom', '2024-11-15', 'top'),
('meeting/2024-11-17_Rittenhop_Dip_Landscape.jpg', 'Dancing in Rittenhouse Square', '2024-11-17', NULL),
('dating/2025-01-16_Mel_and_Jacob_2_dip_bw.jpg', 'At Jazz Attack', '2025-01-16', NULL),
('dating/2025-03-04_Mardi_Gras.JPG', 'Mardi Gras in Steubenville, Ohio', '2025-03-04', NULL),
('proposal/PeytoLakeBanff_Proposal_One_Knee_wide.jpg', 'Proposal at Peyto Lake', '2025-09-27', NULL),
('proposal/PeytoLakeBanff_Proposal_Closeup_Smile.jpg', 'Proposal closeup', '2025-09-27', NULL),
('blessing/Landscape_JM_at_Altar.jpg', 'Blessing of Engagement', '2025-09-28', NULL),
('blessing/JM_With_Parents_at_Scannichios.jpg', 'With parents at Scannichio''s', '2025-09-28', NULL);
