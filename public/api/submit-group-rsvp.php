<?php
/**
 * Submit RSVP for a group of guests.
 * 
 * POST /api/submit-group-rsvp
 * Body (JSON):
 * {
 *   "guests": [
 *     { "id": 1, "attending": "yes", "dietary": "" },
 *     { "id": 2, "attending": "no", "dietary": "" }
 *   ],
 *   "email": "email@example.com",
 *   "message": "...",
 *   "song_request": "..."
 * }
 */

require_once __DIR__ . '/../../private/config.php';
require_once __DIR__ . '/../../private/db.php';

header('Content-Type: application/json');

// Parse JSON body
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

if (empty($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email address is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

// Validate that at least one guest has a response
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
            rsvp_submitted_at = :rsvp_submitted_at
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
        
        // Skip guests with no response selected
        if ($attendingValue === '') continue;
        
        $attending = ($attendingValue === 'yes') ? 'yes' : 'no';
        
        // Get guest name for email notification
        $nameStmt = $pdo->prepare("SELECT first_name, last_name FROM guests WHERE id = ?");
        $nameStmt->execute([$guestId]);
        $guestInfo = $nameStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($guestInfo) {
            $fullName = trim($guestInfo['first_name'] . ' ' . $guestInfo['last_name']);
            $guestNames[] = $fullName . ' (' . ($attending === 'yes' ? 'Attending' : 'Not Attending') . ')';
            
            if ($attending === 'yes') {
                $attendingCount++;
            } else {
                $decliningCount++;
            }
        }
        
        $stmt->execute([
            ':attending' => $attending,
            ':dietary' => !empty($dietary) ? $dietary : null,
            ':email' => $email,
            ':song_request' => !empty($songRequest) ? $songRequest : null,
            ':message' => !empty($message) ? $message : null,
            ':rsvp_submitted_at' => $now,
            ':id' => $guestId,
        ]);
    }
    
    $pdo->commit();
    
    // Send email notification
    require_once __DIR__ . '/../../private/email_handler.php';
    
    $emailBody = "New RSVP Submission\n\n";
    $emailBody .= "Guests:\n";
    foreach ($guestNames as $gn) {
        $emailBody .= "  - $gn\n";
    }
    $emailBody .= "\nAttending: $attendingCount\n";
    $emailBody .= "Declining: $decliningCount\n";
    $emailBody .= "Contact Email: $email\n";
    if (!empty($message)) {
        $emailBody .= "Message: $message\n";
    }
    if (!empty($songRequest)) {
        $emailBody .= "Song Request: $songRequest\n";
    }
    $emailBody .= "\nCheck RSVPs at https://wedding.stephens.page/check-rsvps";
    
    $subject = 'New RSVP - ' . (count($guestNames) > 0 ? $guestNames[0] : 'Unknown');
    
    $rsvpEmails = [
        'melissa.longua@gmail.com',
        'jacob@stephens.page'
    ];
    
    foreach ($rsvpEmails as $emailAddress) {
        sendEmail($emailAddress, $subject, $emailBody);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your RSVP!',
        'attending_count' => $attendingCount,
        'declining_count' => $decliningCount,
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("RSVP submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'There was an error submitting your RSVP. Please try again.']);
}
