<?php
/**
 * Seating chart API endpoint.
 * All actions require admin authentication.
 *
 * POST /api/seating
 * Body (JSON): { "action": "...", ... }
 *
 * Actions:
 *   move_guest     - { guest_id, table_id }  (table_id=null to unseat)
 *   update_table   - { table_id, name?, notes?, capacity? }
 *   add_table      - { table_name }
 *   delete_table   - { table_id }
 *   save_positions - { positions: [{table_id, pos_x, pos_y}, ...] }
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
if (!$input || empty($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $action = $input['action'];

    switch ($action) {

        case 'move_guest':
            $guestId = intval($input['guest_id'] ?? 0);
            $tableId = isset($input['table_id']) && $input['table_id'] !== null
                ? intval($input['table_id'])
                : null;

            if (!$guestId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid guest_id.']);
                exit;
            }

            // Verify guest exists
            $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM guests WHERE id = ?");
            $stmt->execute([$guestId]);
            $guest = $stmt->fetch();
            if (!$guest) {
                http_response_code(404);
                echo json_encode(['error' => 'Guest not found.']);
                exit;
            }

            // Verify table exists if provided
            if ($tableId !== null) {
                $stmt = $pdo->prepare("SELECT id, table_name FROM seating_tables WHERE id = ?");
                $stmt->execute([$tableId]);
                $table = $stmt->fetch();
                if (!$table) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Table not found.']);
                    exit;
                }
            }

            $stmt = $pdo->prepare("UPDATE guests SET seating_table_id = ?, seat_number = NULL WHERE id = ?");
            $stmt->execute([$tableId, $guestId]);

            $msg = $tableId === null
                ? "{$guest['first_name']} {$guest['last_name']} unseated."
                : "{$guest['first_name']} {$guest['last_name']} moved to {$table['table_name']}.";

            echo json_encode(['success' => true, 'message' => $msg]);
            break;

        case 'update_table':
            $tableId = intval($input['table_id'] ?? 0);
            if (!$tableId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid table_id.']);
                exit;
            }

            $fields = [];
            $params = [];

            if (isset($input['name'])) {
                $fields[] = 'table_name = ?';
                $params[] = trim($input['name']);
            }
            if (isset($input['notes'])) {
                $fields[] = 'notes = ?';
                $params[] = trim($input['notes']);
            }
            if (isset($input['capacity'])) {
                $fields[] = 'capacity = ?';
                $params[] = intval($input['capacity']);
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update.']);
                exit;
            }

            $params[] = $tableId;
            $pdo->prepare("UPDATE seating_tables SET " . implode(', ', $fields) . " WHERE id = ?")
                ->execute($params);

            echo json_encode(['success' => true, 'message' => 'Table updated.']);
            break;

        case 'add_table':
            $tableName = trim($input['table_name'] ?? '');
            if (!$tableName) {
                http_response_code(400);
                echo json_encode(['error' => 'Table name required.']);
                exit;
            }

            // Get next table number
            $stmt = $pdo->query("SELECT COALESCE(MAX(table_number), 0) + 1 FROM seating_tables");
            $nextNum = $stmt->fetchColumn();

            // Position new table in a default spot
            $posX = 50;
            $posY = 85;

            $stmt = $pdo->prepare("INSERT INTO seating_tables (table_number, table_name, capacity, pos_x, pos_y) VALUES (?, ?, 10, ?, ?)");
            $stmt->execute([$nextNum, $tableName, $posX, $posY]);
            $newId = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => "Table $nextNum created.",
                'table_id' => intval($newId),
                'table_number' => $nextNum,
            ]);
            break;

        case 'delete_table':
            $tableId = intval($input['table_id'] ?? 0);
            if (!$tableId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid table_id.']);
                exit;
            }

            // Check no guests are seated
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE seating_table_id = ?");
            $stmt->execute([$tableId]);
            if ($stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete a table that has guests. Unseat all guests first.']);
                exit;
            }

            $pdo->prepare("DELETE FROM seating_tables WHERE id = ?")->execute([$tableId]);

            echo json_encode(['success' => true, 'message' => 'Table deleted.']);
            break;

        case 'save_positions':
            $positions = $input['positions'] ?? [];
            if (empty($positions)) {
                http_response_code(400);
                echo json_encode(['error' => 'No positions provided.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE seating_tables SET pos_x = ?, pos_y = ? WHERE id = ?");
            foreach ($positions as $pos) {
                $stmt->execute([floatval($pos['pos_x']), floatval($pos['pos_y']), intval($pos['table_id'])]);
            }

            echo json_encode(['success' => true, 'message' => 'Positions saved.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . $action]);
    }

} catch (Exception $e) {
    error_log("Seating API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again.']);
}
