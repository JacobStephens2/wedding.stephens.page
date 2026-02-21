<?php
/**
 * Submit RSVP from the admin area (for mail-in RSVP cards).
 * Requires admin authentication. Does not require email.
 * 
 * POST /api/admin-submit-rsvp
 * Body (JSON):
 * {
 *   "guests": [
 *     { "id": 1, "attending": "yes", "dietary": "" },
 *     { "id": 2, "attending": "no", "dietary": "" }
 *   ],
 *   "message": "...",
 *   "song_request": "..."
 * }
 */

require_once __DIR__ . '/../../private/config.php';
require_once __DIR__ . '/../../private/db.php';
require_once __DIR__ . '/../../private/admin_auth.php';

header('Content-Type: application/json');

if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Admin authentication required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['guests'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data.']);
    exit;
}

$guestRsvps = $input['guests'];
$email = trim($input['email'] ?? '');
$message = trim($input['message'] ?? '');
$songRequest = trim($input['song_request'] ?? '');

$hasResponse = false;
foreach ($guestRsvps as $gr) {
    if (!empty($gr['attending'])) {
        $hasResponse = true;
        break;
    }
}

if (!$hasResponse) {
    http_response_code(400);
    echo json_encode(['error' => 'Please indicate attendance for at least one guest.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    
    $now = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("
        UPDATE guests 
        SET attending = :attending,
            dietary = :dietary,
            email = :email,
            song_request = :song_request,
            message = :message,
            rsvp_submitted_at = :rsvp_submitted_at,
            plus_one_name = :plus_one_name,
            plus_one_attending = :plus_one_attending,
            plus_one_dietary = :plus_one_dietary
        WHERE id = :id
    ");
    
    $guestNames = [];
    $attendingCount = 0;
    $decliningCount = 0;
    
    foreach ($guestRsvps as $gr) {
        $guestId = intval($gr['id'] ?? 0);
        $attendingValue = trim($gr['attending'] ?? '');
        $dietary = trim($gr['dietary'] ?? '');
        
        if (!$guestId) continue;
        if ($attendingValue === '') continue;
        
        $attending = ($attendingValue === 'yes') ? 'yes' : 'no';
        
        $nameStmt = $pdo->prepare("SELECT first_name, last_name FROM guests WHERE id = ?");
        $nameStmt->execute([$guestId]);
        $guestInfo = $nameStmt->fetch(PDO::FETCH_ASSOC);
        
        $plusOneName = trim($gr['plus_one_name'] ?? '');
        $plusOneAttending = trim($gr['plus_one_attending'] ?? '');
        $plusOneDietary = trim($gr['plus_one_dietary'] ?? '');
        
        if ($guestInfo) {
            $fullName = trim($guestInfo['first_name'] . ' ' . $guestInfo['last_name']);
            $guestNames[] = $fullName . ' (' . ($attending === 'yes' ? 'Attending' : 'Not Attending') . ')';
            
            if ($attending === 'yes') {
                $attendingCount++;
            } else {
                $decliningCount++;
            }
            
            if ($plusOneAttending === 'yes') {
                $poLabel = !empty($plusOneName) ? $plusOneName : 'Guest of ' . $guestInfo['first_name'];
                $guestNames[] = $poLabel . ' (Attending - plus one)';
                $attendingCount++;
            } elseif ($plusOneAttending === 'no') {
                $decliningCount++;
            }
        }
        
        $stmt->execute([
            ':attending' => $attending,
            ':dietary' => !empty($dietary) ? $dietary : null,
            ':email' => !empty($email) ? $email : null,
            ':song_request' => !empty($songRequest) ? $songRequest : null,
            ':message' => !empty($message) ? $message : null,
            ':rsvp_submitted_at' => $now,
            ':plus_one_name' => !empty($plusOneName) ? $plusOneName : null,
            ':plus_one_attending' => ($plusOneAttending === 'yes' || $plusOneAttending === 'no') ? $plusOneAttending : null,
            ':plus_one_dietary' => !empty($plusOneDietary) ? $plusOneDietary : null,
            ':id' => $guestId,
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'RSVP entered successfully.',
        'attending_count' => $attendingCount,
        'declining_count' => $decliningCount,
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Admin RSVP submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'There was an error submitting the RSVP. Please try again.']);
}
