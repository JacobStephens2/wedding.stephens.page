<?php
/**
 * Update the purchased_by name on a registry item that is already marked
 * purchased. Used by the admin Recent Purchases inline edit.
 *
 * POST /api/update-purchaser
 * Body (JSON): { "item_id": 1, "purchaser_name": "Aunt Sue" }
 */

require_once __DIR__ . '/../../private/config.php';
require_once __DIR__ . '/../../private/db.php';
require_once __DIR__ . '/../../private/admin_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$authed = isAdminAuthenticated()
    || (isset($_SESSION['registry_admin_authenticated']) && $_SESSION['registry_admin_authenticated'] === true);
if (!$authed) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Admin authentication required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$itemId = (int) ($input['item_id'] ?? 0);
$purchaserName = trim((string) ($input['purchaser_name'] ?? ''));
if (mb_strlen($purchaserName) > 255) {
    $purchaserName = mb_substr($purchaserName, 0, 255);
}

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Item ID is required']);
    exit;
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT purchased FROM registry_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit;
    }
    if (empty($row['purchased'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Item is not currently marked as purchased']);
        exit;
    }
    // Self-assign updated_at so MySQL's ON UPDATE CURRENT_TIMESTAMP does not
    // fire. Without this, editing a name would bump the row's timestamp and
    // make it jump to the top of the admin Recent Purchases list, hiding
    // the real purchase time.
    $update = $pdo->prepare("UPDATE registry_items SET purchased_by = ?, updated_at = updated_at WHERE id = ?");
    $update->execute([$purchaserName !== '' ? $purchaserName : null, $itemId]);
    echo json_encode(['success' => true, 'purchased_by' => $purchaserName]);
} catch (Exception $e) {
    error_log('update-purchaser error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
