<?php
require_once __DIR__ . '/../../private/config.php';
require_once __DIR__ . '/../../private/db.php';
require_once __DIR__ . '/../../private/email_handler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$itemId = $input['item_id'] ?? null;
$purchaserName = trim($input['purchaser_name'] ?? '');
$purchaserMessage = trim($input['purchaser_message'] ?? '');
$turnstileToken = trim($input['cf_turnstile_token'] ?? '');
if (mb_strlen($purchaserMessage) > 2000) {
    $purchaserMessage = mb_substr($purchaserMessage, 0, 2000);
}

if (!$itemId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Item ID is required']);
    exit;
}

/**
 * Verify a Cloudflare Turnstile token against the siteverify endpoint.
 * Returns true when verification succeeds, false otherwise.
 */
function verifyTurnstileToken(string $token, string $secret, ?string $remoteIp): bool
{
    if ($secret === '' || $token === '') {
        return false;
    }
    $payload = http_build_query(array_filter([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $remoteIp,
    ]));
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 6,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    if ($resp === false) {
        return false;
    }
    $data = json_decode($resp, true);
    return is_array($data) && !empty($data['success']);
}

try {
    $pdo = getDbConnection();

    // Check if item exists
    $stmt = $pdo->prepare("SELECT id, purchased FROM registry_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit;
    }

    // Toggle purchased status
    $newPurchasedStatus = !$item['purchased'];

    // Bot protection: require a valid Turnstile token when transitioning an
    // item to purchased. Unmarking (available) stays open so the "Mark as
    // Available" button keeps working without re-solving a challenge.
    $turnstileSecret = $_ENV['TURNSTILE_SECRET_KEY'] ?? '';
    if ($newPurchasedStatus && $turnstileSecret !== '') {
        $remoteIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (!verifyTurnstileToken($turnstileToken, $turnstileSecret, $remoteIp)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Bot check failed. Please reload and try again.']);
            exit;
        }
    }
    $stmt = $pdo->prepare("
        UPDATE registry_items
        SET purchased = ?,
            purchased_by = ?,
            purchase_message = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $newPurchasedStatus ? 1 : 0,
        $newPurchasedStatus && $purchaserName ? $purchaserName : null,
        $newPurchasedStatus && $purchaserMessage !== '' ? $purchaserMessage : null,
        $itemId
    ]);
    
    // Send notification email when an item is marked as purchased
    if ($newPurchasedStatus) {
        $titleStmt = $pdo->prepare("SELECT title, price FROM registry_items WHERE id = ?");
        $titleStmt->execute([$itemId]);
        $itemInfo = $titleStmt->fetch();
        $itemTitle = $itemInfo['title'] ?? 'Unknown item';
        $itemPrice = $itemInfo['price'] ? '$' . number_format($itemInfo['price'], 2) : '';

        $subject = "Registry Item Purchased: $itemTitle";
        $body = "A registry item has been purchased!\n\n";
        $body .= "Item: $itemTitle\n";
        if ($itemPrice) {
            $body .= "Price: $itemPrice\n";
        }
        if ($purchaserName !== '') {
            $body .= "From: $purchaserName\n";
        }
        if ($purchaserMessage !== '') {
            $body .= "Message: $purchaserMessage\n";
        }
        $body .= "\n— Wedding Website";

        $recipients = $_ENV['REGISTRY_PURCHASE_NOTIFY'] ?? '';
        foreach (array_filter(array_map('trim', explode(',', $recipients))) as $email) {
            sendEmail($email, $subject, $body);
        }
    }

    echo json_encode([
        'success' => true,
        'purchased' => $newPurchasedStatus,
        'message' => $newPurchasedStatus ? 'Item marked as purchased' : 'Item marked as available'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}


