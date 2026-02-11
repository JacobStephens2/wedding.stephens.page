<?php
/**
 * Guest search API endpoint for RSVP invite lookup.
 * Returns matching guests as JSON.
 * 
 * GET /api/guest-search?q=<name>
 */

require_once __DIR__ . '/../../private/config.php';
require_once __DIR__ . '/../../private/db.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['guests' => [], 'error' => 'Please enter at least 2 characters.']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Search by first name, last name, or combination
    // Use LIKE for partial matching
    $searchTerm = '%' . $query . '%';
    
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, group_name, mailing_group, attending, rsvp_submitted_at
        FROM guests
        WHERE first_name LIKE :q1
           OR last_name LIKE :q2
           OR CONCAT(first_name, ' ', last_name) LIKE :q3
        ORDER BY last_name, first_name
        LIMIT 20
    ");
    
    $stmt->execute([
        ':q1' => $searchTerm,
        ':q2' => $searchTerm,
        ':q3' => $searchTerm,
    ]);
    
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['guests' => $guests]);
    
} catch (Exception $e) {
    error_log("Guest search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['guests' => [], 'error' => 'An error occurred while searching.']);
}
