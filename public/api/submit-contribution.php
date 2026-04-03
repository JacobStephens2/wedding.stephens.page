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
$fund = strtolower(trim($input['fund'] ?? 'house'));

if (!$amount || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid amount is required']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Whitelist fund → table mapping to avoid SQL injection
    $allowedFunds = [
        'house' => 'house_fund_contributions',
        'honeymoon' => 'honeymoon_fund_contributions',
    ];
    $table = $allowedFunds[$fund] ?? null;
    if ($table === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid fund type']);
        exit;
    }

    // Check if fund is visible
    $settingKey = $fund . '_fund_visible';
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$settingKey]);
        $row = $stmt->fetch();
        if ($row && $row['setting_value'] !== '1') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'This fund is not currently accepting contributions']);
            exit;
        }
    } catch (Exception $e) {
        // If settings table doesn't exist, allow by default
    }
    
    // Insert contribution
    $stmt = $pdo->prepare("
        INSERT INTO {$table} (amount, contributor_name)
        VALUES (?, ?)
    ");
    $stmt->execute([
        $amount,
        $contributorName ?: null
    ]);
    
    // Get updated total
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM {$table}
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

