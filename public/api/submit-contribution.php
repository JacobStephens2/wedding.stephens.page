<?php
require_once __DIR__ . '/../../private/config.php';
require_once __DIR__ . '/../../private/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = $input['amount'] ?? null;
$contributorName = trim($input['contributor_name'] ?? '');

if (!$amount || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid amount is required']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Insert contribution
    $stmt = $pdo->prepare("
        INSERT INTO house_fund_contributions (amount, contributor_name)
        VALUES (?, ?)
    ");
    $stmt->execute([
        $amount,
        $contributorName ?: null
    ]);
    
    // Get updated total
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM house_fund_contributions
    ");
    $result = $stmt->fetch();
    $newTotal = $result['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'total' => $newTotal,
        'message' => 'Thank you for your contribution!'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

