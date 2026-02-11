<?php
/**
 * Get all guests in the same mailing group as the specified guest.
 * 
 * GET /api/guest-group?guest_id=<id>
 */

require_once __DIR__ . '/../../private/config.php';
require_once __DIR__ . '/../../private/db.php';

header('Content-Type: application/json');

$guestId = intval($_GET['guest_id'] ?? 0);

if (!$guestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Guest ID required.']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get the selected guest's mailing group
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, mailing_group FROM guests WHERE id = ?");
    $stmt->execute([$guestId]);
    $guest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$guest) {
        http_response_code(404);
        echo json_encode(['error' => 'Guest not found.']);
        exit;
    }
    
    // Get all guests in the same mailing group
    if ($guest['mailing_group'] !== null) {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, group_name, mailing_group, attending, dietary, song_request, message, email, rsvp_submitted_at, has_plus_one, plus_one_name, plus_one_attending, plus_one_dietary
            FROM guests
            WHERE mailing_group = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$guest['mailing_group']]);
        $groupMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Guest has no mailing group - return just this guest
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, group_name, mailing_group, attending, dietary, song_request, message, email, rsvp_submitted_at, has_plus_one, plus_one_name, plus_one_attending, plus_one_dietary
            FROM guests
            WHERE id = ?
        ");
        $stmt->execute([$guestId]);
        $groupMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'selected_guest' => $guest,
        'group_members' => $groupMembers,
    ]);
    
} catch (Exception $e) {
    error_log("Guest group error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred.']);
}
