<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/admin_auth.php';
require_once __DIR__ . '/../private/admin_sample.php';

session_start();

$error = '';
$sampleMode = isAdminSampleMode();
$authenticated = $sampleMode;
$isFullAdmin = false;

// Check unified admin auth first
if (!$sampleMode && isAdminAuthenticated()) {
    $authenticated = true;
    $isFullAdmin = true;
} elseif (!$sampleMode) {
    if (isRehearsalSeatingAuthenticated()) {
        $authenticated = true;
    }
}

// Handle login
if (!$sampleMode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['rehearsal_login'])) {
    $password = trim($_POST['password'] ?? '');

    // Try unified admin password first
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
    if ($password === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $authenticated = true;
        $isFullAdmin = true;
    } else {
        // Try rehearsal-specific password
        $rehearsalPassword = $_ENV['REHEARSAL_SEATING_PASSWORD'] ?? '';
        if ($password === $rehearsalPassword) {
            $_SESSION['rehearsal_seating_authenticated'] = true;
            $authenticated = true;
        } else {
            $error = 'Incorrect password. Please try again.';
        }
    }
}

// Handle logout
if (!$sampleMode && isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin-rehearsal-seating');
    exit;
}

$seatingData = [];
$unseatedGuests = [];
$stats = [];
$allTablesJson = '[]';

if ($sampleMode) {
    $sampleSeating = getSampleRehearsalSeatingData();
    $seatingData = $sampleSeating['seating_data'];
    $unseatedGuests = $sampleSeating['unseated_guests'];
    $stats = $sampleSeating['stats'];
    $allTablesJson = $sampleSeating['tables_json'];
} elseif ($authenticated) {
    // CSV export handler
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("
                SELECT st.table_number, st.table_name,
                       g.first_name, g.last_name, g.group_name, g.dietary, g.rehearsal_seat_number,
                       g.has_plus_one, g.plus_one_name, g.plus_one_rehearsal_invited, g.plus_one_dietary,
                       g.rehearsal_plus_one_seat_before, g.rehearsal_plus_one_seat_number
                FROM rehearsal_seating_tables st
                LEFT JOIN guests g ON g.rehearsal_table_id = st.id
                ORDER BY st.table_number, g.rehearsal_seat_number, g.last_name, g.first_name
            ");
            $rows = $stmt->fetchAll();

            // Group rows by table so we can compute the true seated order
            // (combining each guest and their plus-one by their seat positions).
            $byTable = [];
            foreach ($rows as $r) {
                if (!$r['first_name']) continue;
                $key = $r['table_number'];
                if (!isset($byTable[$key])) {
                    $byTable[$key] = ['table_number' => $r['table_number'], 'table_name' => $r['table_name'], 'seats' => []];
                }
                $byTable[$key]['seats'][] = [
                    'pos' => (float)($r['rehearsal_seat_number'] ?? 999),
                    'first_name' => $r['first_name'],
                    'last_name' => $r['last_name'],
                    'group_name' => $r['group_name'],
                    'dietary' => $r['dietary'] ?? '',
                ];
                if ($r['has_plus_one'] && $r['plus_one_rehearsal_invited']) {
                    $poPos = $r['rehearsal_plus_one_seat_number'];
                    if ($poPos === null) {
                        $poPos = ((float)$r['rehearsal_seat_number']) + ($r['rehearsal_plus_one_seat_before'] ? -0.5 : 0.5);
                    }
                    $poName = $r['plus_one_name'] ?: 'Guest of ' . $r['first_name'];
                    $poParts = explode(' ', trim($poName), 2);
                    $byTable[$key]['seats'][] = [
                        'pos' => (float)$poPos,
                        'first_name' => $poParts[0] ?? '',
                        'last_name' => trim(($poParts[1] ?? '') . ' (plus one)'),
                        'group_name' => '',
                        'dietary' => $r['plus_one_dietary'] ?? '',
                    ];
                }
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="rehearsal-dinner-seating.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Table #', 'Table Name', 'Seat #', 'First Name', 'Last Name', 'Group', 'Dietary Restrictions']);
            foreach ($byTable as $tbl) {
                usort($tbl['seats'], fn($a, $b) => $a['pos'] <=> $b['pos']);
                $seatPos = 0;
                foreach ($tbl['seats'] as $s) {
                    $seatPos++;
                    fputcsv($out, [
                        $tbl['table_number'],
                        $tbl['table_name'],
                        $seatPos,
                        $s['first_name'],
                        $s['last_name'],
                        $s['group_name'],
                        $s['dietary'],
                    ]);
                }
            }
            // Unseated guests
            $stmt2 = $pdo->query("
                SELECT first_name, last_name, group_name, dietary
                FROM guests
                WHERE rehearsal_table_id IS NULL AND rehearsal_invited = 1
                ORDER BY group_name, last_name, first_name
            ");
            foreach ($stmt2->fetchAll() as $ug) {
                fputcsv($out, ['', '(Unseated)', '', $ug['first_name'], $ug['last_name'], $ug['group_name'], $ug['dietary'] ?? '']);
            }
            fclose($out);
            exit;
        } catch (Exception $e) {
            $error = 'CSV export failed: ' . htmlspecialchars($e->getMessage());
        }
    }

    try {
        $pdo = getDbConnection();

        // Get all rehearsal tables with their guests
        $stmt = $pdo->query("
            SELECT st.id as table_id, st.table_number, st.table_name, st.capacity, st.notes as table_notes,
                   st.pos_x, st.pos_y,
                   g.id as guest_id, g.first_name, g.last_name, g.group_name, g.rehearsal_seat_number as seat_number,
                   g.rehearsal_invited, g.dietary, g.message,
                   g.is_child, g.is_infant,
                   g.has_plus_one, g.plus_one_name, g.plus_one_rehearsal_invited, g.plus_one_dietary,
                   g.plus_one_is_child, g.plus_one_is_infant,
                   g.rehearsal_plus_one_seat_before as plus_one_seat_before,
                   g.rehearsal_plus_one_seat_number as plus_one_seat_number
            FROM rehearsal_seating_tables st
            LEFT JOIN guests g ON g.rehearsal_table_id = st.id
            ORDER BY st.table_number, g.rehearsal_seat_number, g.last_name, g.first_name
        ");
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $tn = $row['table_number'];
            if (!isset($seatingData[$tn])) {
                $seatingData[$tn] = [
                    'table_id' => $row['table_id'],
                    'table_name' => $row['table_name'],
                    'capacity' => $row['capacity'],
                    'notes' => $row['table_notes'],
                    'pos_x' => $row['pos_x'],
                    'pos_y' => $row['pos_y'],
                    'guests' => [],
                ];
            }
            if ($row['guest_id']) {
                $seatingData[$tn]['guests'][] = $row;
            }
        }

        // Get unseated guests who are invited to the rehearsal dinner
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, group_name, rehearsal_invited, dietary, message,
                   is_child, is_infant,
                   has_plus_one, plus_one_name, plus_one_rehearsal_invited, plus_one_dietary,
                   plus_one_is_child, plus_one_is_infant,
                   rehearsal_plus_one_seat_before as plus_one_seat_before,
                   rehearsal_plus_one_seat_number as plus_one_seat_number
            FROM guests
            WHERE rehearsal_table_id IS NULL
              AND rehearsal_invited = 1
            ORDER BY group_name, last_name, first_name
        ");
        $unseatedGuests = $stmt->fetchAll();

        // Stats
        $totalSeated = 0;
        $totalDietary = 0;
        foreach ($seatingData as $t) {
            $totalSeated += count($t['guests']);
            foreach ($t['guests'] as $g) {
                if (!empty($g['dietary'])) $totalDietary++;
                if ($g['has_plus_one'] && $g['plus_one_rehearsal_invited']) {
                    $totalSeated++;
                    if (!empty($g['plus_one_dietary'])) $totalDietary++;
                }
            }
        }
        $stats = [
            'tables' => count($seatingData),
            'seated' => $totalSeated,
            'unseated' => count($unseatedGuests),
            'dietary' => $totalDietary,
        ];

        // Build tables JSON for floor plan
        $tablesForJs = [];
        foreach ($seatingData as $tn => $t) {
            $gc = count($t['guests']);
            $poc = 0;
            foreach ($t['guests'] as $g) {
                if ($g['has_plus_one'] && $g['plus_one_rehearsal_invited']) $poc++;
            }
            $tablesForJs[] = [
                'id' => $t['table_id'],
                'number' => $tn,
                'name' => $t['table_name'],
                'capacity' => $t['capacity'],
                'guest_count' => $gc + $poc,
                'pos_x' => floatval($t['pos_x'] ?? 50),
                'pos_y' => floatval($t['pos_y'] ?? 50),
            ];
        }
        $allTablesJson = json_encode($tablesForJs);

    } catch (Exception $e) {
        $error = 'Error loading rehearsal seating: ' . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Rehearsal Dinner Seating - Jacob & Melissa";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <?php include __DIR__ . '/includes/theme_init.php'; ?>
    <?php renderAdminSampleModeAssets(); ?>
    <link rel="stylesheet" href="/css/style.css?v=<?php
        $cssPath = __DIR__ . '/../css/style.css';
        echo file_exists($cssPath) ? filemtime($cssPath) : time();
    ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400&family=Beloved+Script&family=Crimson+Text:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        /* ---- Layout ---- */
        .seating-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: var(--color-surface);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
        }
        .stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background-color: var(--color-light);
            border-radius: 8px;
            flex-wrap: wrap;
        }
        .stat-item { text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; color: var(--color-green); }
        .stat-number.stat-seated { color: var(--color-green); }
        .stat-number.stat-unseated { color: #dc3545; }
        .stat-number.stat-dietary { color: var(--color-gold); }
        .stat-label { font-size: 0.9rem; color: var(--color-dark); }

        /* ---- Toast ---- */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .toast.show { opacity: 1; pointer-events: auto; }
        .toast.success { background-color: #2d5016; }
        .toast.error { background-color: #8b0000; }

        /* ---- Floor Plan ---- */
        .floorplan-wrapper {
            margin-bottom: 2rem;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            overflow: hidden;
        }
        .floorplan-header {
            background-color: var(--color-surface-alt);
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .floorplan-header h2 { margin: 0; font-size: 1.1rem; }
        .floorplan {
            position: relative;
            width: 100%;
            padding-bottom: 55%;
            background: #faf9f6;
            overflow: hidden;
            user-select: none;
        }
        .floorplan-room {
            position: absolute;
            inset: 3% 3%;
            border: 2px solid var(--color-border);
            border-radius: 4px;
        }
        .fp-table {
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--color-green);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: grab;
            transform: translate(-50%, -50%);
            transition: box-shadow 0.2s;
            font-size: 0.7rem;
            line-height: 1.2;
            z-index: 10;
        }
        .fp-table:hover { box-shadow: 0 0 0 3px rgba(77, 107, 46, 0.4); }
        .fp-table.dragging { cursor: grabbing; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 20; opacity: 0.9; }
        .fp-table.selected { box-shadow: 0 0 0 3px var(--color-gold); }
        .fp-table.over-capacity { background: #b44; }
        .fp-table-num { font-weight: bold; font-size: 0.9rem; }
        .fp-table-count { opacity: 0.85; font-size: 0.65rem; }
        .fp-table-name {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 2px;
            font-size: 0.6rem;
            color: var(--color-text-secondary);
            white-space: nowrap;
            pointer-events: none;
        }
        .fp-table.sweetheart {
            border-radius: 6px;
            width: 90px;
            height: 40px;
            background: var(--color-gold);
        }
        .fp-table.sweetheart.over-capacity { background: #b44; }

        .fp-edit-hint {
            color: var(--color-text-muted);
            font-size: 0.75rem;
        }
        .fp-btn {
            background: none;
            border: 1px solid var(--color-border);
            padding: 0.3rem 0.7rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            color: var(--color-text-secondary);
        }
        .fp-btn:hover { background: var(--color-light); }
        .fp-btn.active { background: var(--color-green); color: white; border-color: var(--color-green); }

        /* ---- Table cards ---- */
        .table-card {
            border: 1px solid var(--color-border);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .table-card.highlight { border-color: var(--color-gold); box-shadow: 0 0 0 2px rgba(184, 157, 92, 0.3); }
        .table-header {
            background-color: var(--color-green);
            color: white;
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        .table-header:hover { background-color: #3d6b2e; }
        .table-header h3 { margin: 0; font-size: 1.1rem; }
        .table-header .table-meta { font-size: 0.9rem; opacity: 0.9; }
        .seat-remaining-badge { font-size: 0.75rem; margin-left: 0.5rem; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 600; }
        .seat-remaining-badge.seats-available { background: rgba(255,255,255,0.25); color: #fff; }
        .seat-remaining-badge.seats-full { background: #c0392b; color: #fff; }
        .seat-remaining-badge.seats-over { background: #e74c3c; color: #fff; }
        .table-body { padding: 0; }
        .table-body.collapsed { display: none; }
        .table-description {
            padding: 0.5rem 1.25rem;
            color: var(--color-text-secondary);
            font-style: italic;
            font-size: 0.9rem;
            border-bottom: 1px solid var(--color-border);
        }

        /* ---- Inline editing ---- */
        .editable {
            cursor: pointer;
            border-bottom: 1px dashed rgba(255,255,255,0.4);
        }
        .editable:hover { border-bottom-color: white; }
        .editable-number {
            cursor: pointer;
            border-bottom: 1px dashed rgba(255,255,255,0.4);
            padding: 0 0.1rem;
        }
        .editable-number:hover { border-bottom-color: white; }
        .editable-capacity {
            cursor: pointer;
            border-bottom: 1px dashed var(--color-text-muted);
            padding: 0 0.15rem;
        }
        .editable-capacity:hover { border-bottom-color: var(--color-dark); }
        .edit-input {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.5);
            color: white;
            padding: 0.1rem 0.4rem;
            border-radius: 3px;
            font-size: inherit;
            font-family: inherit;
        }
        .desc-edit-input {
            width: 100%;
            border: 1px solid var(--color-border);
            padding: 0.4rem;
            border-radius: 3px;
            font-size: 0.9rem;
            font-family: inherit;
        }

        /* ---- Guest list ---- */
        .guest-list {
            width: 100%;
            border-collapse: collapse;
        }
        .guest-list th,
        .guest-list td {
            padding: 0.5rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }
        .guest-list th {
            background-color: var(--color-surface-alt);
            font-size: 0.8rem;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .guest-list tr:last-child td { border-bottom: none; }
        .guest-list tr:hover { background-color: var(--color-surface-alt); }
        .guest-list tr.drag-over { background-color: #e8f5e0; }
        .guest-list tr.drag-over-above { box-shadow: inset 0 2px 0 var(--color-green); }
        .guest-list tr.drag-over-below { box-shadow: inset 0 -2px 0 var(--color-green); }
        .guest-list tr.dragging-row { opacity: 0.4; }
        .plus-one-row td { padding-left: 2rem; color: var(--color-text-secondary); font-style: italic; }
        .seat-num { color: var(--color-text-muted); font-size: 0.85rem; min-width: 1.5rem; text-align: center; }
        .drag-handle { cursor: grab; color: var(--color-text-muted); font-size: 1rem; user-select: none; padding: 0 0.25rem; }
        .drag-handle:hover { color: var(--color-dark); }
        .guest-list tr.dragging-row .drag-handle { cursor: grabbing; }

        .dietary-badge {
            display: inline-block;
            background-color: #fff3cd;
            color: #856404;
            padding: 0.1rem 0.4rem;
            border-radius: 12px;
            font-size: 0.75rem;
        }
        .age-badge {
            display: inline-block;
            padding: 0.1rem 0.4rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            vertical-align: middle;
        }
        .age-badge.child { background-color: #d4edda; color: #155724; }
        .age-badge.infant { background-color: #e2d6f3; color: #4a1a7a; }
        .group-badge {
            display: inline-block;
            background-color: var(--color-light);
            color: var(--color-dark);
            padding: 0.1rem 0.4rem;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        /* ---- Action buttons ---- */
        .guest-actions {
            display: flex;
            gap: 0.3rem;
            align-items: center;
        }
        .guest-actions select {
            font-size: 0.8rem;
            padding: 0.2rem 0.3rem;
            border-radius: 3px;
            border: 1px solid var(--color-border);
            max-width: 130px;
        }
        .btn-sm {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border: 1px solid var(--color-border);
            border-radius: 3px;
            cursor: pointer;
            background: var(--color-surface);
            white-space: nowrap;
        }
        .btn-sm:hover { background: var(--color-light); }
        .btn-sm.danger { color: #dc3545; border-color: #dc3545; }
        .btn-sm.danger:hover { background: rgba(220, 53, 69, 0.1); }
        .btn-sm.primary { color: var(--color-green); border-color: var(--color-green); }
        .btn-sm.primary:hover { background: #e8f5e0; }

        /* ---- Add guest row ---- */
        .add-guest-row {
            padding: 0.5rem 1rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
            border-top: 1px solid var(--color-border);
            background: var(--color-surface-alt);
        }
        .add-guest-row select {
            flex: 1;
            font-size: 0.85rem;
            padding: 0.3rem;
            border-radius: 3px;
            border: 1px solid var(--color-border);
        }

        /* ---- Unseated ---- */
        .unseated-section {
            margin-top: 2rem;
            border: 2px dashed #dc3545;
            border-radius: 8px;
            padding: 1.5rem;
        }
        .unseated-section h2 { color: #dc3545; margin-top: 0; }
        .unseated-guest {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0;
            flex-wrap: wrap;
        }
        .unseated-guest-name { min-width: 160px; }
        .unseated-guest input[type="checkbox"] { width: auto; margin: 0; cursor: pointer; }
        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid var(--color-border);
            font-size: 0.85rem;
        }
        .bulk-actions label { cursor: pointer; color: var(--color-text-secondary); }
        .bulk-actions select { font-size: 0.85rem; }
        .bulk-count { font-weight: bold; color: var(--color-dark); }

        /* ---- Dietary summary ---- */
        .dietary-summary {
            margin-top: 2rem;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 1.5rem;
            background-color: var(--color-surface-alt);
        }
        .dietary-summary h2 { margin-top: 0; color: var(--color-gold); }
        .dietary-table { width: 100%; border-collapse: collapse; }
        .dietary-table th, .dietary-table td {
            padding: 0.5rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }
        .dietary-table th { background-color: var(--color-light); font-size: 0.85rem; }

        /* ---- Add/Delete table ---- */
        .table-management {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .table-management input {
            padding: 0.4rem 0.6rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .delete-table-btn {
            margin-left: auto;
            font-size: 0.75rem;
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            opacity: 0.6;
            padding: 0.75rem 1.25rem;
        }
        .delete-table-btn:hover { opacity: 1; text-decoration: underline; }

        /* ---- Export bar ---- */
        .export-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .export-bar-label {
            font-size: 0.9rem;
            color: var(--color-text-secondary);
            margin-right: 0.5rem;
        }
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 1rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            background: var(--color-surface);
            cursor: pointer;
            font-size: 0.85rem;
            color: var(--color-text-caption);
            transition: all 0.2s;
            text-decoration: none;
        }
        .export-btn:hover { background: var(--color-light); border-color: var(--color-green); color: var(--color-green); }
        .export-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

        /* ---- Export modal ---- */
        .export-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
            align-items: center;
            justify-content: center;
        }
        .export-modal-overlay.open { display: flex; }
        .export-modal {
            background: var(--color-surface);
            border-radius: 8px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        .export-modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .export-modal-header h2 { margin: 0; font-size: 1.1rem; }
        .export-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--color-text-muted);
            padding: 0;
            line-height: 1;
        }
        .export-modal-close:hover { color: var(--color-dark); }
        .export-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.5rem;
        }
        .export-modal-body pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 0.85rem;
            line-height: 1.6;
            margin: 0;
            color: var(--color-dark);
        }
        .export-modal-footer {
            padding: 0.75rem 1.5rem;
            border-top: 1px solid var(--color-border);
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        .export-modal-footer button {
            padding: 0.5rem 1.2rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            border: 1px solid var(--color-border);
            background: var(--color-surface);
        }
        .export-modal-footer button:hover { background: var(--color-light); }
        .export-modal-footer .btn-copy {
            background: var(--color-green);
            color: white;
            border-color: var(--color-green);
        }
        .export-modal-footer .btn-copy:hover { opacity: 0.9; }

        /* ---- Grid (spreadsheet) view ---- */
        .grid-view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .grid-view-toggle button {
            padding: 0.4rem 1rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            background: var(--color-surface);
            color: var(--color-dark);
            cursor: pointer;
            font-size: 0.85rem;
        }
        .grid-view-toggle button.active {
            background: var(--color-green);
            color: white;
            border-color: var(--color-green);
        }
        .grid-spreadsheet-wrapper {
            overflow-x: auto;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            margin-bottom: 2rem;
            animation: gridFadeIn 0.15s ease-in;
        }
        @keyframes gridFadeIn { from { opacity: 0.3; } to { opacity: 1; } }
        .grid-spreadsheet {
            border-collapse: collapse;
            min-width: 100%;
        }
        .grid-spreadsheet th {
            position: sticky;
            top: 0;
            background: var(--color-green);
            color: white;
            padding: 0.6rem 1rem;
            text-align: left;
            font-size: 0.85rem;
            white-space: nowrap;
            border-right: 1px solid rgba(255,255,255,0.2);
            min-width: 160px;
        }
        .grid-spreadsheet td {
            padding: 0.35rem 1rem;
            border-right: 1px solid var(--color-border);
            border-bottom: 1px solid var(--color-border);
            font-size: 0.85rem;
            white-space: nowrap;
            vertical-align: top;
            color: var(--color-dark);
        }
        .grid-spreadsheet tr:nth-child(even) td { background: var(--color-surface-alt); }
        .grid-spreadsheet tr:nth-child(odd) td { background: var(--color-surface); }
        .grid-spreadsheet .grid-seat-num { color: var(--color-text-muted); font-size: 0.75rem; margin-right: 0.3rem; }
        .grid-info-row td {
            background: var(--color-surface-alt) !important;
            font-size: 0.75rem;
            color: var(--color-text-secondary);
            padding: 0.25rem 1rem !important;
            border-bottom: 2px solid var(--color-border);
        }
        .grid-cell-guest { cursor: pointer; position: relative; }
        .grid-cell-guest:hover { background: var(--color-light) !important; }
        .grid-cell-plusone { padding-left: 2rem !important; color: var(--color-text-secondary); }
        .grid-move-icon { float: right; opacity: 0; color: var(--color-text-muted); font-size: 0.8rem; transition: opacity 0.15s; }
        .grid-cell-guest:hover .grid-move-icon,
        .grid-cell-plusone:hover .grid-move-icon { opacity: 1; }
        .grid-unseated-header { background: #b44 !important; }
        .grid-cell-unseated { border-left: 2px solid #b44; }
        .grid-cell-guest[draggable="true"],
        .grid-cell-plusone[draggable="true"] { cursor: grab; }
        .grid-cell-guest.grid-dragging,
        .grid-cell-plusone.grid-dragging { opacity: 0.3; }
        .grid-cell-guest.grid-drag-over-above,
        .grid-cell-plusone.grid-drag-over-above { box-shadow: inset 0 2px 0 0 var(--color-primary); }
        .grid-cell-guest.grid-drag-over-below,
        .grid-cell-plusone.grid-drag-over-below { box-shadow: inset 0 -2px 0 0 var(--color-primary); }
        .grid-move-select {
            display: block;
            margin-top: 0.3rem;
            font-size: 0.8rem;
            padding: 0.2rem;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            background: var(--color-surface);
            color: var(--color-dark);
            width: 100%;
        }

        /* ---- Misc ---- */
        .top-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 1rem; align-items: center; }
        .top-actions a { color: var(--color-dark); text-decoration: none; }
        .top-actions a:hover { color: var(--color-green); }
        .share-btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.4rem 0.9rem; border: 1px solid var(--color-green); border-radius: 4px;
            background: none; color: var(--color-green); cursor: pointer; font-size: 0.85rem;
            transition: all 0.2s;
        }
        .share-btn:hover { background: var(--color-green); color: white; }
        .share-btn svg { width: 14px; height: 14px; }
        .share-modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 9998; align-items: center; justify-content: center;
        }
        .share-modal-overlay.open { display: flex; }
        .share-modal {
            background: var(--color-surface); border-radius: 8px; width: 90%; max-width: 480px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2); padding: 1.5rem;
        }
        .share-modal h2 { margin: 0 0 1rem; font-size: 1.1rem; }
        .share-modal-close {
            float: right; background: none; border: none; font-size: 1.5rem;
            cursor: pointer; color: var(--color-text-muted); padding: 0; line-height: 1;
        }
        .share-modal-close:hover { color: var(--color-dark); }
        .share-field { margin-bottom: 0.75rem; }
        .share-field label { display: block; font-size: 0.8rem; color: var(--color-text-secondary); margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .share-field .share-value {
            display: flex; align-items: center; gap: 0.5rem;
            background: var(--color-light); border: 1px solid var(--color-border); border-radius: 4px;
            padding: 0.6rem 0.8rem; font-size: 0.95rem; font-family: monospace;
            word-break: break-all;
        }
        .share-field .share-value .copy-icon {
            flex-shrink: 0; cursor: pointer; color: var(--color-text-muted);
            background: none; border: none; padding: 0.2rem; font-size: 1rem;
        }
        .share-field .share-value .copy-icon:hover { color: var(--color-green); }
        .share-copy-all {
            display: block; width: 100%; margin-top: 1rem; padding: 0.6rem;
            background: var(--color-green); color: white; border: none; border-radius: 4px;
            cursor: pointer; font-size: 0.9rem; font-weight: bold;
        }
        .share-copy-all:hover { opacity: 0.9; }
        .logout-link { text-align: right; margin-bottom: 1rem; }
        .logout-link a { color: var(--color-dark); text-decoration: none; }
        .logout-link a:hover { color: var(--color-green); }
        .back-to-site { text-align: center; margin-bottom: 2rem; }
        .back-to-site a { color: var(--color-green); text-decoration: none; font-size: 1.1rem; transition: color 0.3s; }
        .back-to-site a:hover { color: var(--color-gold); text-decoration: underline; }
        .toggle-all { margin-bottom: 1rem; }
        .toggle-all button {
            background: none; border: 1px solid var(--color-green); color: var(--color-green);
            padding: 0.4rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.9rem;
        }
        .toggle-all button:hover { background-color: var(--color-green); color: white; }

        /* ---- Add / Remove guests ---- */
        .guest-manage-section {
            margin-top: 2rem;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            padding: 1.5rem;
            background-color: var(--color-surface-alt);
        }
        .guest-manage-section h2 { margin-top: 0; font-size: 1.1rem; }
        .guest-search-row {
            display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem;
        }
        .guest-search-row input {
            flex: 1; padding: 0.5rem 0.75rem; border: 1px solid var(--color-border);
            border-radius: 4px; font-size: 0.9rem;
        }
        .search-results {
            max-height: 300px; overflow-y: auto;
        }
        .search-result {
            display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.4rem;
            border-bottom: 1px solid var(--color-border); flex-wrap: wrap;
        }
        .search-result:last-child { border-bottom: none; }
        .search-result-name { font-weight: 500; min-width: 140px; }
        .search-result .invited-badge {
            font-size: 0.75rem; padding: 0.1rem 0.5rem; border-radius: 9999px;
            font-weight: 600;
        }
        .invited-badge.yes { background: #d4edda; color: #155724; }
        .invited-badge.no { background: var(--color-light); color: var(--color-text-muted); }
        .btn-invite {
            font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 3px;
            cursor: pointer; border: 1px solid var(--color-green); background: var(--color-green);
            color: white; white-space: nowrap;
        }
        .btn-invite:hover { opacity: 0.9; }
        .btn-uninvite {
            font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 3px;
            cursor: pointer; border: 1px solid #dc3545; background: none;
            color: #dc3545; white-space: nowrap;
        }
        .btn-uninvite:hover { background: rgba(220, 53, 69, 0.1); }
        .po-toggle { font-size: 0.8rem; color: var(--color-text-secondary); margin-left: 0.25rem; }
        .po-toggle label { cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem; }
        .po-toggle input { width: auto; margin: 0; }
        .remove-guest-btn {
            font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 3px;
            cursor: pointer; border: 1px solid #dc3545; background: none;
            color: #dc3545; white-space: nowrap; margin-left: auto;
        }
        .remove-guest-btn:hover { background: rgba(220, 53, 69, 0.1); }

        @media (max-width: 768px) {
            .seating-container { padding: 1rem; }
            .stats { flex-direction: column; gap: 1rem; }
            .floorplan { padding-bottom: 75%; }
            .fp-table { width: 44px; height: 44px; font-size: 0.6rem; }
            .fp-table.sweetheart { width: 65px; height: 32px; }
            .fp-table-num { font-size: 0.75rem; }
            .fp-table-name { font-size: 0.5rem; }
            .guest-list th, .guest-list td { padding: 0.4rem 0.5rem; font-size: 0.85rem; }
            .guest-actions { flex-direction: column; align-items: flex-start; }
            .grid-spreadsheet th, .grid-spreadsheet td { min-width: 130px; padding: 0.3rem 0.5rem; font-size: 0.8rem; }
            .bulk-actions { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <?php if ($isFullAdmin || $sampleMode): ?>
        <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <?php endif; ?>
    <main class="page-container">
        <?php renderAdminSampleBanner('Rehearsal Dinner Seating Sample Mode'); ?>
        <div class="back-to-site"><a href="/">&#8592; Back to Main Site</a></div>

        <?php if (!$authenticated): ?>
            <div class="form-container">
                <h1 class="page-title">Rehearsal Dinner Seating</h1>
                <?php if ($error): ?>
                    <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>
                <form method="POST" action="/admin-rehearsal-seating">
                    <input type="hidden" name="rehearsal_login" value="1">
                    <div class="form-group required">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="seating-container">
                <div class="top-actions">
                    <button class="share-btn" onclick="openShareModal()" title="Share access to this page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                        Share
                    </button>
                    <a href="/admin-rehearsal-seating?logout=1">Logout</a>
                </div>
                <h1 class="page-title">Rehearsal Dinner Seating</h1>

                <?php if ($error): ?>
                    <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="stats" id="stats-bar">
                    <div class="stat-item">
                        <div class="stat-number" id="stat-tables"><?php echo $stats['tables']; ?></div>
                        <div class="stat-label">Tables</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number stat-seated" id="stat-seated"><?php echo $stats['seated']; ?></div>
                        <div class="stat-label">Seated</div>
                    </div>
                    <?php if ($stats['unseated'] > 0): ?>
                    <div class="stat-item" id="stat-unseated-wrap">
                        <div class="stat-number stat-unseated" id="stat-unseated"><?php echo $stats['unseated']; ?></div>
                        <div class="stat-label">Unseated</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-item">
                        <div class="stat-number stat-dietary" id="stat-dietary"><?php echo $stats['dietary']; ?></div>
                        <div class="stat-label">Dietary Needs</div>
                    </div>
                </div>

                <!-- Export bar -->
                <div class="export-bar">
                    <span class="export-bar-label">Export:</span>
                    <a class="export-btn" href="/admin-rehearsal-seating?export=csv" title="Download spreadsheet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                        CSV Spreadsheet
                    </a>
                    <button class="export-btn" onclick="showTextExport()" title="Plain text for email">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Copy for Email
                    </button>
                    <button class="export-btn" onclick="openPrintView()" title="Print-friendly seating chart">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print View
                    </button>
                </div>

                <!-- View toggle -->
                <div class="grid-view-toggle">
                    <button class="active" onclick="switchView('cards')" id="view-cards-btn">Card View</button>
                    <button onclick="switchView('grid')" id="view-grid-btn">Grid View</button>
                </div>

                <!-- Grid (spreadsheet) view -->
                <div id="grid-view-container" style="display:none;"></div>

                <!-- Floor Plan -->
                <div class="floorplan-wrapper">
                    <div class="floorplan-header">
                        <h2>Room Layout</h2>
                        <div>
                            <span class="fp-edit-hint" id="fp-drag-hint">Drag tables to reposition</span>
                            <button class="fp-btn" id="fp-save-btn" style="display:none;" onclick="savePositions()">Save Layout</button>
                        </div>
                    </div>
                    <div class="floorplan" id="floorplan">
                        <div class="floorplan-room">
                            <!-- No dancefloor/DJ for rehearsal dinner -->
                        </div>
                    </div>
                </div>

                <!-- Add table -->
                <div class="table-management">
                    <input type="text" id="new-table-name" placeholder="New table name...">
                    <input type="number" id="new-table-capacity" placeholder="Seats" min="1" max="50" value="10" style="width:70px;">
                    <button class="btn-sm primary" onclick="addTable()">Add Table</button>
                    <div class="toggle-all" style="margin:0 0 0 auto;">
                        <button onclick="toggleAll()">Expand / Collapse All</button>
                    </div>
                </div>

                <!-- Table cards -->
                <?php foreach ($seatingData as $tableNum => $table): ?>
                    <?php
                    $guestCount = count($table['guests']);
                    $plusOneCount = 0;
                    foreach ($table['guests'] as $g) {
                        if ($g['has_plus_one'] && $g['plus_one_rehearsal_invited']) $plusOneCount++;
                    }
                    $totalAtTable = $guestCount + $plusOneCount;
                    ?>
                    <div class="table-card" id="card-<?php echo $table['table_id']; ?>" data-table-id="<?php echo $table['table_id']; ?>">
                        <div class="table-header" onclick="toggleTable(<?php echo $table['table_id']; ?>)">
                            <h3>
                                Table <span class="editable-number" onclick="event.stopPropagation(); editTableNumber(<?php echo $table['table_id']; ?>, this)" title="Click to reassign table number"><?php echo $tableNum; ?></span> &mdash;
                                <span class="editable" onclick="event.stopPropagation(); editTableName(<?php echo $table['table_id']; ?>, this)"><?php echo htmlspecialchars($table['table_name']); ?></span>
                            </h3>
                            <span class="table-meta" id="meta-<?php echo $table['table_id']; ?>"><?php echo $totalAtTable; ?> / <span class="editable-capacity" onclick="event.stopPropagation(); editCapacity(<?php echo $table['table_id']; ?>, this)" title="Click to edit capacity"><?php echo $table['capacity']; ?></span> seats<?php
                                $remaining = $table['capacity'] - $totalAtTable;
                                if ($remaining > 0): ?>
                                    <span class="seat-remaining-badge seats-available"><?php echo $remaining; ?> remaining</span>
                                <?php elseif ($remaining === 0): ?>
                                    <span class="seat-remaining-badge seats-full">Full</span>
                                <?php else: ?>
                                    <span class="seat-remaining-badge seats-over">Over by <?php echo abs($remaining); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="table-body collapsed" id="tbody-<?php echo $table['table_id']; ?>">
                            <?php if (!empty($table['notes'])): ?>
                                <div class="table-description" ondblclick="editTableNotes(<?php echo $table['table_id']; ?>, this)">
                                    <?php echo htmlspecialchars($table['notes']); ?>
                                    <span style="font-size:0.75rem;color:var(--color-text-muted);margin-left:0.5rem;">(double-click to edit)</span>
                                </div>
                            <?php else: ?>
                                <div class="table-description" ondblclick="editTableNotes(<?php echo $table['table_id']; ?>, this)" style="color:var(--color-text-muted);">
                                    No notes. <span style="font-size:0.75rem;">(double-click to add)</span>
                                </div>
                            <?php endif; ?>
                            <table class="guest-list">
                                <thead>
                                    <tr>
                                        <th style="width:2rem;">#</th>
                                        <th>Guest</th>
                                        <th>Group</th>
                                        <th>Dietary</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="guests-<?php echo $table['table_id']; ?>">
                                    <?php
                                    $allSeats = [];
                                    foreach ($table['guests'] as $guest) {
                                        $guestInfo = [
                                            'id' => $guest['guest_id'],
                                            'name' => $guest['first_name'] . ' ' . $guest['last_name'],
                                            'group' => $guest['group_name'],
                                            'dietary' => $guest['dietary'] ?? '',
                                            'message' => $guest['message'] ?? '',
                                            'is_child' => (bool)$guest['is_child'],
                                            'is_infant' => (bool)$guest['is_infant'],
                                            'has_plus_one' => (bool)($guest['has_plus_one'] && $guest['plus_one_rehearsal_invited']),
                                            'plus_one_name' => $guest['plus_one_name'] ?: 'Guest of ' . $guest['first_name'],
                                            'plus_one_dietary' => $guest['plus_one_dietary'] ?? '',
                                            'plus_one_is_child' => (bool)($guest['plus_one_is_child'] ?? false),
                                            'plus_one_is_infant' => (bool)($guest['plus_one_is_infant'] ?? false),
                                            'plus_one_seat_before' => (bool)($guest['plus_one_seat_before'] ?? false),
                                        ];
                                        $allSeats[] = ['type' => 'guest', 'pos' => (float)($guest['seat_number'] ?? 999), 'guest' => $guest, 'info' => $guestInfo];
                                        if ($guestInfo['has_plus_one']) {
                                            $poPos = $guest['plus_one_seat_number'] ?? null;
                                            if ($poPos === null) {
                                                $poPos = $guestInfo['plus_one_seat_before']
                                                    ? ((float)$guest['seat_number'] - 0.5)
                                                    : ((float)$guest['seat_number'] + 0.5);
                                            }
                                            $allSeats[] = ['type' => 'plusone', 'pos' => (float)$poPos, 'guest' => $guest, 'info' => $guestInfo];
                                        }
                                    }
                                    usort($allSeats, fn($a, $b) => $a['pos'] <=> $b['pos']);
                                    ?>
                                    <?php $seatPos = 0; foreach ($allSeats as $seat): $seatPos++; ?>
                                        <?php if ($seat['type'] === 'guest'): $guest = $seat['guest']; $guestInfo = $seat['info']; ?>
                                        <tr draggable="true"
                                            ondragstart="dragGuest(event, <?php echo $guest['guest_id']; ?>)"
                                            data-guest-id="<?php echo $guest['guest_id']; ?>"
                                            data-guest-info='<?php echo htmlspecialchars(json_encode($guestInfo), ENT_QUOTES); ?>'>
                                            <td><span class="drag-handle" title="Drag to reorder">&#x2807;</span><span class="seat-num"><?php echo $seatPos; ?></span></td>
                                            <td><?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?><?php
                                                if ($guest['is_child']): ?> <span class="age-badge child" title="Child">child</span><?php endif;
                                                if ($guest['is_infant']): ?> <span class="age-badge infant" title="Infant">infant</span><?php endif;
                                                if (!empty($guest['dietary'])): ?> <span title="<?php echo htmlspecialchars($guest['dietary']); ?>" style="cursor:help;">&#127869;</span><?php endif;
                                                if (!empty($guest['message'])): ?> <span title="<?php echo htmlspecialchars($guest['message']); ?>" style="cursor:help;">&#128172;</span><?php endif;
                                            ?></td>
                                            <td><span class="group-badge"><?php echo htmlspecialchars($guest['group_name']); ?></span></td>
                                            <td>
                                                <?php if (!empty($guest['dietary'])): ?>
                                                    <span class="dietary-badge"><?php echo htmlspecialchars($guest['dietary']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="guest-actions">
                                                    <select onchange="moveGuest(<?php echo $guest['guest_id']; ?>, this.value); this.selectedIndex=0;">
                                                        <option value="">Move to...</option>
                                                        <?php foreach ($seatingData as $tn2 => $t2): ?>
                                                            <?php if ($t2['table_id'] != $table['table_id']): ?>
                                                                <option value="<?php echo $t2['table_id']; ?>">T<?php echo $tn2; ?>: <?php echo htmlspecialchars($t2['table_name']); ?></option>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button class="btn-sm danger" onclick="unseatGuest(<?php echo $guest['guest_id']; ?>)">Unseat</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php else: $guest = $seat['guest']; ?>
                                        <tr class="plus-one-row" draggable="true"
                                            ondragstart="dragPlusOne(event, <?php echo $guest['guest_id']; ?>)"
                                            data-parent-guest-id="<?php echo $guest['guest_id']; ?>">
                                            <td><span class="drag-handle" title="Drag to reorder">&#x2807;</span><span class="seat-num"><?php echo $seatPos; ?></span></td>
                                            <td><?php echo htmlspecialchars($guest['plus_one_name'] ?: 'Guest of ' . $guest['first_name']); ?> (plus one)<?php
                                                if (!empty($guest['plus_one_is_child'])): ?> <span class="age-badge child" title="Child">child</span><?php endif;
                                                if (!empty($guest['plus_one_is_infant'])): ?> <span class="age-badge infant" title="Infant">infant</span><?php endif;
                                                if (!empty($guest['plus_one_dietary'])): ?> <span title="<?php echo htmlspecialchars($guest['plus_one_dietary']); ?>" style="cursor:help;">&#127869;</span><?php endif;
                                            ?></td>
                                            <td></td>
                                            <td>
                                                <?php if (!empty($guest['plus_one_dietary'])): ?>
                                                    <span class="dietary-badge"><?php echo htmlspecialchars($guest['plus_one_dietary']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td></td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="add-guest-row">
                                <select id="add-select-<?php echo $table['table_id']; ?>">
                                    <option value="">Add guest to this table...</option>
                                    <?php foreach ($unseatedGuests as $ug): ?>
                                        <option value="<?php echo $ug['id']; ?>"><?php echo htmlspecialchars($ug['first_name'] . ' ' . $ug['last_name']); ?> (<?php echo htmlspecialchars($ug['group_name']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn-sm primary" onclick="seatGuestFromSelect(<?php echo $table['table_id']; ?>)">Add</button>
                            </div>
                            <button class="delete-table-btn" onclick="deleteTable(<?php echo $table['table_id']; ?>, '<?php echo htmlspecialchars(addslashes($table['table_name'])); ?>')">Delete this table...</button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Unseated guests -->
                <?php if (!empty($unseatedGuests)): ?>
                    <div class="unseated-section" id="unseated-section"
                         ondragover="event.preventDefault(); this.style.borderColor='var(--color-green)';"
                         ondragleave="this.style.borderColor='#dc3545';"
                         ondrop="dropUnseat(event); this.style.borderColor='#dc3545';">
                        <h2>Unseated Guests (<?php echo count($unseatedGuests); ?>)</h2>
                        <p>Invited to rehearsal dinner but no table assigned. Drag guests here to unseat, or use the dropdown to assign.</p>
                        <div class="bulk-actions" id="bulk-actions">
                            <label><input type="checkbox" id="bulk-select-all" onchange="toggleSelectAll(this.checked)"> Select all</label>
                            <span class="bulk-count" id="bulk-count"></span>
                            <select id="bulk-table-select">
                                <option value="">Assign selected to...</option>
                                <?php foreach ($seatingData as $tn2 => $t2): ?>
                                    <option value="<?php echo $t2['table_id']; ?>">T<?php echo $tn2; ?>: <?php echo htmlspecialchars($t2['table_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn-sm primary" onclick="bulkAssign()">Assign</button>
                        </div>
                        <div id="unseated-list">
                            <?php foreach ($unseatedGuests as $ug): ?>
                                <?php
                                $ugInfo = [
                                    'id' => $ug['id'],
                                    'name' => $ug['first_name'] . ' ' . $ug['last_name'],
                                    'group' => $ug['group_name'],
                                    'dietary' => $ug['dietary'] ?? '',
                                    'message' => $ug['message'] ?? '',
                                    'is_child' => (bool)$ug['is_child'],
                                    'is_infant' => (bool)$ug['is_infant'],
                                    'has_plus_one' => (bool)($ug['has_plus_one'] && $ug['plus_one_rehearsal_invited']),
                                    'plus_one_name' => $ug['plus_one_name'] ?: 'Guest of ' . $ug['first_name'],
                                    'plus_one_dietary' => $ug['plus_one_dietary'] ?? '',
                                    'plus_one_is_child' => (bool)($ug['plus_one_is_child'] ?? false),
                                    'plus_one_is_infant' => (bool)($ug['plus_one_is_infant'] ?? false),
                                    'plus_one_seat_before' => (bool)($ug['plus_one_seat_before'] ?? false),
                                ];
                                ?>
                                <div class="unseated-guest" data-guest-id="<?php echo $ug['id']; ?>"
                                     data-guest-info='<?php echo htmlspecialchars(json_encode($ugInfo), ENT_QUOTES); ?>'
                                     draggable="true" ondragstart="dragGuest(event, <?php echo $ug['id']; ?>)">
                                    <input type="checkbox" class="bulk-check" value="<?php echo $ug['id']; ?>" onchange="updateBulkCount()">
                                    <span class="unseated-guest-name"><?php echo htmlspecialchars($ug['first_name'] . ' ' . $ug['last_name']); ?></span>
                                    <span class="group-badge"><?php echo htmlspecialchars($ug['group_name']); ?></span>
                                    <?php if (!empty($ug['dietary'])): ?>
                                        <span class="dietary-badge"><?php echo htmlspecialchars($ug['dietary']); ?></span>
                                    <?php endif; ?>
                                    <select onchange="moveGuest(<?php echo $ug['id']; ?>, this.value); this.selectedIndex=0;" style="font-size:0.8rem;">
                                        <option value="">Assign to...</option>
                                        <?php foreach ($seatingData as $tn2 => $t2): ?>
                                            <option value="<?php echo $t2['table_id']; ?>">T<?php echo $tn2; ?>: <?php echo htmlspecialchars($t2['table_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="remove-guest-btn" onclick="removeFromRehearsal(<?php echo $ug['id']; ?>)" title="Remove from rehearsal dinner">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add guests to rehearsal -->
                <div class="guest-manage-section" id="guest-manage-section">
                    <h2>Add Guests to Rehearsal</h2>
                    <p style="font-size:0.85rem; color:var(--color-text-secondary); margin-bottom:0.75rem;">
                        Search for any guest to add or remove them from the rehearsal dinner.
                    </p>
                    <div class="guest-search-row">
                        <input type="text" id="guest-search-input" placeholder="Search by name or group..." autocomplete="off">
                    </div>
                    <div class="search-results" id="search-results"></div>
                </div>

                <!-- Dietary Summary -->
                <?php
                $dietaryGuests = [];
                foreach ($seatingData as $tableNum => $table) {
                    foreach ($table['guests'] as $g) {
                        if (!empty($g['dietary'])) {
                            $dietaryGuests[] = [
                                'name' => $g['first_name'] . ' ' . $g['last_name'],
                                'table' => $tableNum,
                                'table_name' => $table['table_name'],
                                'dietary' => $g['dietary'],
                            ];
                        }
                        if ($g['has_plus_one'] && $g['plus_one_rehearsal_invited'] && !empty($g['plus_one_dietary'])) {
                            $dietaryGuests[] = [
                                'name' => $g['plus_one_name'] ?: 'Guest of ' . $g['first_name'],
                                'table' => $tableNum,
                                'table_name' => $table['table_name'],
                                'dietary' => $g['plus_one_dietary'],
                            ];
                        }
                    }
                }
                ?>
                <?php if (!empty($dietaryGuests)): ?>
                    <div class="dietary-summary">
                        <h2>Dietary Restrictions Summary</h2>
                        <table class="dietary-table">
                            <thead><tr><th>Guest</th><th>Table</th><th>Restriction</th></tr></thead>
                            <tbody>
                                <?php foreach ($dietaryGuests as $dg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dg['name']); ?></td>
                                        <td><?php echo $dg['table']; ?> &mdash; <?php echo htmlspecialchars($dg['table_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dg['dietary']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Text export modal -->
    <div class="export-modal-overlay" id="text-export-modal">
        <div class="export-modal">
            <div class="export-modal-header">
                <h2>Rehearsal Dinner Seating &mdash; Plain Text</h2>
                <button class="export-modal-close" onclick="closeTextExport()">&times;</button>
            </div>
            <div class="export-modal-body">
                <pre id="text-export-content"></pre>
            </div>
            <div class="export-modal-footer">
                <button onclick="closeTextExport()">Close</button>
                <button class="btn-copy" id="copy-btn" onclick="copyTextExport()">Copy to Clipboard</button>
            </div>
        </div>
    </div>

    <!-- Share modal -->
    <div class="share-modal-overlay" id="share-modal">
        <div class="share-modal">
            <button class="share-modal-close" onclick="closeShareModal()">&times;</button>
            <h2>Share Rehearsal Seating</h2>
            <p style="font-size:0.85rem; color:var(--color-text-secondary); margin-bottom:1rem;">
                Send this link and password to anyone who needs to manage the rehearsal dinner seating.
                This gives access to the rehearsal seating chart only &mdash; not the rest of the admin area.
            </p>
            <div class="share-field">
                <label>Link</label>
                <div class="share-value">
                    <span id="share-url"><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'wedding.stephens.page') . '/admin-rehearsal-seating'); ?></span>
                    <button class="copy-icon" onclick="copyField('share-url')" title="Copy link">&#x2398;</button>
                </div>
            </div>
            <div class="share-field">
                <label>Password</label>
                <div class="share-value">
                    <span id="share-password"><?php echo htmlspecialchars($_ENV['REHEARSAL_SEATING_PASSWORD'] ?? ''); ?></span>
                    <button class="copy-icon" onclick="copyField('share-password')" title="Copy password">&#x2398;</button>
                </div>
            </div>
            <button class="share-copy-all" onclick="copyShareMessage()">Copy Link + Password</button>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
    // ---- Share modal ----
    function openShareModal() {
        document.getElementById('share-modal').classList.add('open');
    }
    function closeShareModal() {
        document.getElementById('share-modal').classList.remove('open');
    }
    function copyField(id) {
        const text = document.getElementById(id).textContent;
        navigator.clipboard.writeText(text).then(() => showToast('Copied!'));
    }
    function copyShareMessage() {
        const url = document.getElementById('share-url').textContent;
        const pw = document.getElementById('share-password').textContent;
        const msg = 'Rehearsal Dinner Seating Chart\n' + url + '\nPassword: ' + pw;
        navigator.clipboard.writeText(msg).then(() => {
            showToast('Link and password copied!');
            closeShareModal();
        });
    }
    document.getElementById('share-modal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeShareModal();
    });

    // ---- Data ----
    const tables = <?php echo $allTablesJson; ?>;
    function getExportData() {
        const tablesData = [];
        document.querySelectorAll('.table-card[data-table-id]').forEach(card => {
            const tableId = parseInt(card.dataset.tableId);
            const tData = tables.find(t => t.id === tableId);
            if (!tData) return;
            const h3 = card.querySelector('.table-header h3');
            const name = h3 ? h3.querySelector('.editable')?.textContent.trim() || h3.textContent.trim() : '';
            const tbody = document.getElementById('guests-' + tableId);
            const guests = [];
            if (tbody) {
                tbody.querySelectorAll('tr[data-guest-id]').forEach(row => {
                    const info = JSON.parse(row.dataset.guestInfo);
                    guests.push({ name: info.name, first_name: info.name.split(' ')[0], dietary: info.dietary || '' });
                    if (info.has_plus_one) {
                        guests.push({ name: info.plus_one_name + ' (plus one)', first_name: info.plus_one_name.split(' ')[0], dietary: info.plus_one_dietary || '' });
                    }
                });
            }
            tablesData.push({
                number: tData.number, name: name, capacity: tData.capacity,
                pos_x: tData.pos_x, pos_y: tData.pos_y, guests: guests
            });
        });
        const unseated = [];
        document.querySelectorAll('.unseated-guest[data-guest-id]').forEach(div => {
            const info = JSON.parse(div.dataset.guestInfo);
            unseated.push(info.name);
        });
        const seated = tablesData.reduce((s, t) => s + t.guests.length, 0);
        return { tables: tablesData, unseated: unseated, stats: { tables: tablesData.length, seated, unseated: unseated.length } };
    }
    let positionsDirty = false;
    let dragGuestId = null;

    // ---- Toast ----
    let toastTimer = null;
    function showToast(msg, type = 'success') {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + type + ' show';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 2500);
    }

    // ---- API helper ----
    async function api(data) {
        const res = await fetch('/api/rehearsal-seating', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        const json = await res.json();
        if (!res.ok || json.error) {
            showToast(json.error || 'Something went wrong.', 'error');
            return null;
        }
        return json;
    }

    // ---- Floor plan rendering ----
    function renderFloorPlan() {
        const fp = document.getElementById('floorplan');
        fp.querySelectorAll('.fp-table').forEach(el => el.remove());
        const room = fp.querySelector('.floorplan-room');

        tables.forEach(t => {
            const el = document.createElement('div');
            el.className = 'fp-table';
            if (t.capacity <= 2) el.classList.add('sweetheart');
            if (t.guest_count > t.capacity) el.classList.add('over-capacity');
            el.id = 'fp-' + t.id;
            el.style.left = t.pos_x + '%';
            el.style.top = t.pos_y + '%';
            el.innerHTML = '<span class="fp-table-num">' + t.number + '</span>'
                         + '<span class="fp-table-count">' + t.guest_count + '/' + t.capacity + '</span>';
            const nameLabel = document.createElement('span');
            nameLabel.className = 'fp-table-name';
            nameLabel.textContent = t.name;
            el.appendChild(nameLabel);
            el.title = 'T' + t.number + ': ' + t.name + ' (' + t.guest_count + '/' + t.capacity + ')';

            el.addEventListener('click', (e) => {
                if (el.classList.contains('dragging')) return;
                const card = document.getElementById('card-' + t.id);
                if (card) {
                    const tbody = document.getElementById('tbody-' + t.id);
                    if (tbody) { tbody.classList.remove('collapsed'); saveExpansionState(); }
                    card.classList.add('highlight');
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => card.classList.remove('highlight'), 2000);
                }
                fp.querySelectorAll('.fp-table').forEach(x => x.classList.remove('selected'));
                el.classList.add('selected');
            });

            el.addEventListener('mousedown', (e) => startDragTable(e, el, t));
            el.addEventListener('touchstart', (e) => startDragTable(e.touches[0], el, t), { passive: false });

            el.addEventListener('dragover', (e) => { e.preventDefault(); el.style.transform = 'translate(-50%,-50%) scale(1.15)'; });
            el.addEventListener('dragleave', () => { el.style.transform = 'translate(-50%,-50%)'; });
            el.addEventListener('drop', (e) => {
                e.preventDefault();
                el.style.transform = 'translate(-50%,-50%)';
                if (dragGuestId) {
                    moveGuest(dragGuestId, t.id);
                    dragGuestId = null;
                }
            });

            room.appendChild(el);
        });
    }

    // ---- Drag table to reposition ----
    function startDragTable(e, el, tableData) {
        if (e.button && e.button !== 0) return;
        const room = el.parentElement;
        const rect = room.getBoundingClientRect();
        let hasMoved = false;

        function onMove(ev) {
            const clientX = ev.touches ? ev.touches[0].clientX : ev.clientX;
            const clientY = ev.touches ? ev.touches[0].clientY : ev.clientY;
            const x = ((clientX - rect.left) / rect.width) * 100;
            const y = ((clientY - rect.top) / rect.height) * 100;
            el.style.left = Math.max(5, Math.min(95, x)) + '%';
            el.style.top = Math.max(5, Math.min(95, y)) + '%';
            tableData.pos_x = Math.max(5, Math.min(95, x));
            tableData.pos_y = Math.max(5, Math.min(95, y));
            el.classList.add('dragging');
            hasMoved = true;
            positionsDirty = true;
            document.getElementById('fp-save-btn').style.display = '';
        }

        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onUp);
            setTimeout(() => el.classList.remove('dragging'), 50);
        }

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('touchend', onUp);
    }

    async function savePositions() {
        const positions = tables.map(t => ({ table_id: t.id, pos_x: Math.round(t.pos_x * 10) / 10, pos_y: Math.round(t.pos_y * 10) / 10 }));
        const result = await api({ action: 'save_positions', positions });
        if (result) {
            showToast('Layout saved.');
            positionsDirty = false;
            document.getElementById('fp-save-btn').style.display = 'none';
        }
    }

    // ---- Guest drag ----
    function dragGuest(e, guestId) {
        dragGuestId = guestId;
        e.dataTransfer.effectAllowed = 'move';
        e.target.closest('tr, .unseated-guest')?.classList.add('dragging-row');
    }

    function dragPlusOne(e, parentGuestId) {
        dragGuestId = null;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
        e.target.closest('tr')?.classList.add('dragging-row');
    }

    function dropUnseat(e) {
        e.preventDefault();
        if (dragGuestId) {
            unseatGuest(dragGuestId);
            dragGuestId = null;
        }
    }

    // ---- DOM helpers for live updates ----
    function buildGuestRow(info, targetTableId) {
        const tr = document.createElement('tr');
        tr.draggable = true;
        tr.setAttribute('ondragstart', 'dragGuest(event, ' + info.id + ')');
        tr.dataset.guestId = info.id;
        tr.dataset.guestInfo = JSON.stringify(info);

        let dietaryHtml = info.dietary ? '<span class="dietary-badge">' + escHtml(info.dietary) + '</span>' : '';
        let dietaryIcon = info.dietary ? ' <span title="' + escHtml(info.dietary) + '" style="cursor:help;">&#127869;</span>' : '';
        let ageHtml = '';
        if (info.is_child) ageHtml += ' <span class="age-badge child" title="Child">child</span>';
        if (info.is_infant) ageHtml += ' <span class="age-badge infant" title="Infant">infant</span>';
        let msgIcon = info.message ? ' <span title="' + escHtml(info.message) + '" style="cursor:help;">&#128172;</span>' : '';

        let moveOpts = '<option value="">Move to...</option>';
        tables.forEach(t => {
            if (t.id !== parseInt(targetTableId)) {
                moveOpts += '<option value="' + t.id + '">T' + t.number + ': ' + escHtml(t.name) + '</option>';
            }
        });

        tr.innerHTML =
            '<td><span class="drag-handle" title="Drag to reorder">&#x2807;</span><span class="seat-num"></span></td>' +
            '<td>' + escHtml(info.name) + ageHtml + dietaryIcon + msgIcon + '</td>' +
            '<td><span class="group-badge">' + escHtml(info.group) + '</span></td>' +
            '<td>' + dietaryHtml + '</td>' +
            '<td><div class="guest-actions">' +
                '<select onchange="moveGuest(' + info.id + ', this.value); this.selectedIndex=0;">' + moveOpts + '</select>' +
                '<button class="btn-sm danger" onclick="unseatGuest(' + info.id + ')">Unseat</button>' +
            '</div></td>';
        return tr;
    }

    function buildPlusOneRow(info) {
        if (!info.has_plus_one) return null;
        const tr = document.createElement('tr');
        tr.className = 'plus-one-row';
        tr.draggable = true;
        tr.setAttribute('ondragstart', 'dragPlusOne(event, ' + info.id + ')');
        tr.dataset.parentGuestId = info.id;
        let dietaryHtml = info.plus_one_dietary ? '<span class="dietary-badge">' + escHtml(info.plus_one_dietary) + '</span>' : '';
        let dietaryIcon = info.plus_one_dietary ? ' <span title="' + escHtml(info.plus_one_dietary) + '" style="cursor:help;">&#127869;</span>' : '';
        let poAgeHtml = '';
        if (info.plus_one_is_child) poAgeHtml += ' <span class="age-badge child" title="Child">child</span>';
        if (info.plus_one_is_infant) poAgeHtml += ' <span class="age-badge infant" title="Infant">infant</span>';
        tr.innerHTML =
            '<td><span class="drag-handle" title="Drag to reorder">&#x2807;</span><span class="seat-num"></span></td>' +
            '<td>' + escHtml(info.plus_one_name) + ' (plus one)' + poAgeHtml + dietaryIcon + '</td>' +
            '<td></td>' +
            '<td>' + dietaryHtml + '</td>' +
            '<td></td>';
        return tr;
    }

    function buildUnseatedDiv(info) {
        const div = document.createElement('div');
        div.className = 'unseated-guest';
        div.dataset.guestId = info.id;
        div.dataset.guestInfo = JSON.stringify(info);
        div.draggable = true;
        div.setAttribute('ondragstart', 'dragGuest(event, ' + info.id + ')');

        let dietaryHtml = info.dietary ? '<span class="dietary-badge">' + escHtml(info.dietary) + '</span>' : '';
        let assignOpts = '<option value="">Assign to...</option>';
        tables.forEach(t => {
            assignOpts += '<option value="' + t.id + '">T' + t.number + ': ' + escHtml(t.name) + '</option>';
        });

        div.innerHTML =
            '<input type="checkbox" class="bulk-check" value="' + info.id + '" onchange="updateBulkCount()">' +
            '<span class="unseated-guest-name">' + escHtml(info.name) + '</span> ' +
            '<span class="group-badge">' + escHtml(info.group) + '</span> ' +
            dietaryHtml +
            '<select onchange="moveGuest(' + info.id + ', this.value); this.selectedIndex=0;" style="font-size:0.8rem;">' + assignOpts + '</select>' +
            '<button class="remove-guest-btn" onclick="removeFromRehearsal(' + info.id + ')" title="Remove from rehearsal dinner">Remove</button>';
        return div;
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function updateTableMeta(tableId) {
        const tbody = document.getElementById('guests-' + tableId);
        if (!tbody) return;
        const guestRows = tbody.querySelectorAll('tr[data-guest-id]');
        const plusOneRows = tbody.querySelectorAll('tr.plus-one-row');
        const totalAtTable = guestRows.length + plusOneRows.length;

        const tableData = tables.find(t => t.id === parseInt(tableId));
        if (!tableData) return;
        tableData.guest_count = totalAtTable;

        const meta = document.getElementById('meta-' + tableId);
        if (meta) {
            const remaining = tableData.capacity - totalAtTable;
            let badge = '';
            if (remaining > 0) badge = '<span class="seat-remaining-badge seats-available">' + remaining + ' remaining</span>';
            else if (remaining === 0) badge = '<span class="seat-remaining-badge seats-full">Full</span>';
            else badge = '<span class="seat-remaining-badge seats-over">Over by ' + Math.abs(remaining) + '</span>';
            meta.innerHTML = totalAtTable + ' / <span class="editable-capacity" onclick="event.stopPropagation(); editCapacity(' + parseInt(tableId) + ', this)" title="Click to edit capacity">' + tableData.capacity + '</span> seats' + badge;
        }
    }

    function updateUnseatedAddSelects(guestInfo, action) {
        document.querySelectorAll('[id^="add-select-"]').forEach(sel => {
            if (action === 'remove') {
                const opt = sel.querySelector('option[value="' + guestInfo.id + '"]');
                if (opt) opt.remove();
            } else if (action === 'add') {
                const opt = document.createElement('option');
                opt.value = guestInfo.id;
                opt.textContent = guestInfo.name + ' (' + guestInfo.group + ')';
                sel.appendChild(opt);
            }
        });
    }

    function updateUnseatedSection() {
        const list = document.getElementById('unseated-list');
        let section = document.getElementById('unseated-section');
        const count = list ? list.querySelectorAll('.unseated-guest').length : 0;
        if (count === 0 && section) section.remove();
        else if (count > 0 && section) section.querySelector('h2').textContent = 'Unseated Guests (' + count + ')';
    }

    function updateStats() {
        let seated = 0;
        document.querySelectorAll('.table-card[data-table-id]').forEach(card => {
            const tbody = document.getElementById('guests-' + card.dataset.tableId);
            if (!tbody) return;
            seated += tbody.querySelectorAll('tr[data-guest-id]').length;
            seated += tbody.querySelectorAll('tr.plus-one-row').length;
        });
        const unseated = document.querySelectorAll('.unseated-guest[data-guest-id]').length;
        const seatedEl = document.getElementById('stat-seated');
        const unseatedEl = document.getElementById('stat-unseated');
        if (seatedEl) seatedEl.textContent = seated;
        if (unseatedEl) unseatedEl.textContent = unseated;
        const unseatedWrap = document.getElementById('stat-unseated-wrap');
        if (unseatedWrap) unseatedWrap.style.display = unseated > 0 ? '' : 'none';
    }

    function ensureUnseatedSection() {
        let section = document.getElementById('unseated-section');
        if (!section) {
            section = document.createElement('div');
            section.className = 'unseated-section';
            section.id = 'unseated-section';
            section.setAttribute('ondragover', "event.preventDefault(); this.style.borderColor='var(--color-green)';");
            section.setAttribute('ondragleave', "this.style.borderColor='#dc3545';");
            section.setAttribute('ondrop', "dropUnseat(event); this.style.borderColor='#dc3545';");
            section.innerHTML = '<h2>Unseated Guests (0)</h2>' +
                '<p>Invited to rehearsal dinner but no table assigned. Drag guests here to unseat, or use the dropdown to assign.</p>' +
                '<div id="unseated-list"></div>';
            const dietary = document.querySelector('.dietary-summary');
            const container = dietary ? dietary.parentElement : document.querySelector('.seating-container');
            if (dietary) container.insertBefore(section, dietary);
            else container.appendChild(section);
        }
        return section;
    }

    // ---- Undo support ----
    let lastAction = null;

    function recordAction(guestId, fromTableId, toTableId) {
        lastAction = { guestId, fromTableId, toTableId };
    }

    async function undoLastAction() {
        if (!lastAction) return;
        const { guestId, fromTableId } = lastAction;
        lastAction = null;
        if (fromTableId) {
            await moveGuest(guestId, fromTableId);
        } else {
            await unseatGuest(guestId);
        }
    }

    function showUndoToast(message) {
        const toast = document.getElementById('toast');
        toast.className = 'toast success show';
        toast.innerHTML = message + ' <button onclick="undoLastAction()" style="margin-left:0.5rem;padding:0.15rem 0.5rem;border:1px solid white;border-radius:4px;background:transparent;color:white;cursor:pointer;font-size:0.85rem;">Undo</button>';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toast.classList.remove('show'); lastAction = null; }, 5000);
    }

    // ---- Guest actions ----
    async function moveGuest(guestId, tableId) {
        if (!tableId) return;
        tableId = parseInt(tableId);

        const existingRow = document.querySelector('tr[data-guest-id="' + guestId + '"]');
        const sourceTableIdForUndo = existingRow ? parseInt(existingRow.closest('tbody')?.id?.replace('guests-', '')) : null;

        const result = await api({ action: 'move_guest', guest_id: guestId, table_id: tableId });
        if (!result) return;

        recordAction(guestId, sourceTableIdForUndo, tableId);
        showUndoToast(result.message);

        const guestRow = document.querySelector('tr[data-guest-id="' + guestId + '"]');
        const unseatedDiv = document.querySelector('.unseated-guest[data-guest-id="' + guestId + '"]');

        if (guestRow) {
            const sourceTbody = guestRow.closest('tbody');
            const sourceTableId = sourceTbody?.id?.replace('guests-', '');
            const plusOneRow = sourceTbody?.querySelector('tr.plus-one-row[data-parent-guest-id="' + guestId + '"]');
            const targetTbody = document.getElementById('guests-' + tableId);
            if (targetTbody) {
                targetTbody.appendChild(guestRow);
                if (plusOneRow) targetTbody.appendChild(plusOneRow);
                const sel = guestRow.querySelector('.guest-actions select');
                if (sel) {
                    sel.innerHTML = '<option value="">Move to...</option>';
                    tables.forEach(t => {
                        if (t.id !== tableId) {
                            const opt = document.createElement('option');
                            opt.value = t.id;
                            opt.textContent = 'T' + t.number + ': ' + t.name;
                            sel.appendChild(opt);
                        }
                    });
                }
            }
            if (sourceTbody) { renumberSeats(sourceTbody); saveSeatingOrder(sourceTbody); }
            if (targetTbody) { renumberSeats(targetTbody); saveSeatingOrder(targetTbody); }
            if (sourceTableId) updateTableMeta(sourceTableId);
            updateTableMeta(tableId);
            renderFloorPlan();

        } else if (unseatedDiv) {
            const info = JSON.parse(unseatedDiv.dataset.guestInfo);
            unseatedDiv.remove();
            const targetTbody = document.getElementById('guests-' + tableId);
            if (targetTbody) {
                const guestRow = buildGuestRow(info, tableId);
                const poRow = buildPlusOneRow(info);
                if (poRow && info.plus_one_seat_before) {
                    targetTbody.appendChild(poRow);
                    targetTbody.appendChild(guestRow);
                } else {
                    targetTbody.appendChild(guestRow);
                    if (poRow) targetTbody.appendChild(poRow);
                }
                renumberSeats(targetTbody);
                saveSeatingOrder(targetTbody);
            }
            updateTableMeta(tableId);
            updateUnseatedAddSelects(info, 'remove');
            updateUnseatedSection();
            updateStats();
            renderFloorPlan();
        }
    }

    async function unseatGuest(guestId) {
        const existingRow = document.querySelector('tr[data-guest-id="' + guestId + '"]');
        const sourceTableIdForUndo = existingRow ? parseInt(existingRow.closest('tbody')?.id?.replace('guests-', '')) : null;

        const result = await api({ action: 'move_guest', guest_id: guestId, table_id: null });
        if (!result) return;

        recordAction(guestId, sourceTableIdForUndo, null);
        showUndoToast(result.message);

        const guestRow = document.querySelector('tr[data-guest-id="' + guestId + '"]');
        if (!guestRow) return;

        const info = JSON.parse(guestRow.dataset.guestInfo);
        const sourceTbody = guestRow.closest('tbody');
        const sourceTableId = sourceTbody?.id?.replace('guests-', '');
        const plusOneRow = sourceTbody?.querySelector('tr.plus-one-row[data-parent-guest-id="' + guestId + '"]');

        if (plusOneRow) plusOneRow.remove();
        guestRow.remove();

        if (sourceTbody) { renumberSeats(sourceTbody); saveSeatingOrder(sourceTbody); }

        ensureUnseatedSection();
        const list = document.getElementById('unseated-list');
        list.appendChild(buildUnseatedDiv(info));

        if (sourceTableId) updateTableMeta(sourceTableId);
        updateUnseatedAddSelects(info, 'add');
        updateUnseatedSection();
        updateStats();
        renderFloorPlan();
    }

    function seatGuestFromSelect(tableId) {
        const sel = document.getElementById('add-select-' + tableId);
        if (sel.value) moveGuest(parseInt(sel.value), tableId);
    }

    // ---- Bulk operations ----
    function toggleSelectAll(checked) {
        document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = checked);
        updateBulkCount();
    }

    function updateBulkCount() {
        const checked = document.querySelectorAll('.bulk-check:checked').length;
        const el = document.getElementById('bulk-count');
        if (el) el.textContent = checked > 0 ? checked + ' selected' : '';
    }

    async function bulkAssign() {
        const tableId = document.getElementById('bulk-table-select')?.value;
        if (!tableId) { showToast('Select a table first.', 'error'); return; }
        const checked = document.querySelectorAll('.bulk-check:checked');
        if (!checked.length) { showToast('No guests selected.', 'error'); return; }
        for (const cb of checked) {
            await moveGuest(parseInt(cb.value), parseInt(tableId));
        }
        document.getElementById('bulk-table-select').selectedIndex = 0;
        showToast(checked.length + ' guest' + (checked.length !== 1 ? 's' : '') + ' assigned.');
    }

    // ---- Table editing ----
    function editTableName(tableId, el) {
        const current = el.textContent.trim();
        const input = document.createElement('input');
        input.type = 'text';
        input.value = current;
        input.className = 'edit-input';
        input.style.width = Math.max(120, current.length * 9) + 'px';

        async function save() {
            const newName = input.value.trim();
            if (newName && newName !== current) {
                const result = await api({ action: 'update_table', table_id: tableId, name: newName });
                if (result) {
                    el.textContent = newName;
                    showToast('Table renamed.');
                    const t = tables.find(x => x.id === tableId);
                    if (t) t.name = newName;
                    renderFloorPlan();
                    return;
                }
            }
            el.textContent = current;
        }

        input.addEventListener('blur', save);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { el.textContent = current; }
        });

        el.textContent = '';
        el.appendChild(input);
        input.focus();
        input.select();
    }

    function editTableNotes(tableId, el) {
        const current = el.textContent.replace(/\(double-click to (?:edit|add)\)/g, '').trim();
        const input = document.createElement('input');
        input.type = 'text';
        input.value = current === 'No notes.' ? '' : current;
        input.className = 'desc-edit-input';
        input.placeholder = 'Table notes...';

        async function save() {
            const newNotes = input.value.trim();
            const result = await api({ action: 'update_table', table_id: tableId, notes: newNotes });
            if (result) {
                el.innerHTML = (newNotes || 'No notes.') + ' <span style="font-size:0.75rem;color:var(--color-text-muted);margin-left:0.5rem;">(double-click to edit)</span>';
                if (!newNotes) el.style.color = 'var(--color-text-muted)';
                else el.style.color = 'var(--color-text-secondary)';
                showToast('Notes updated.');
            } else {
                el.innerHTML = (current || 'No notes.') + ' <span style="font-size:0.75rem;color:var(--color-text-muted);margin-left:0.5rem;">(double-click to edit)</span>';
            }
        }

        input.addEventListener('blur', save);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') {
                el.innerHTML = (current || 'No notes.') + ' <span style="font-size:0.75rem;color:var(--color-text-muted);margin-left:0.5rem;">(double-click to edit)</span>';
            }
        });

        el.textContent = '';
        el.appendChild(input);
        input.focus();
    }

    function editTableNumber(tableId, el) {
        const current = parseInt(el.textContent.trim());
        const input = document.createElement('input');
        input.type = 'number';
        input.value = current;
        input.min = 1;
        input.className = 'edit-input';
        input.style.width = '55px';

        async function save() {
            const newNum = parseInt(input.value);
            if (newNum > 0 && newNum !== current) {
                const result = await api({ action: 'reassign_number', table_id: tableId, new_number: newNum });
                if (result && result.tables) {
                    result.tables.forEach(t => {
                        const tData = tables.find(x => x.id === t.id);
                        if (tData) tData.number = t.number;
                    });
                    document.querySelectorAll('.table-card[data-table-id]').forEach(card => {
                        const cid = parseInt(card.dataset.tableId);
                        const tData = tables.find(x => x.id === cid);
                        if (!tData) return;
                        const numSpan = card.querySelector('.editable-number');
                        if (numSpan) numSpan.textContent = tData.number;
                    });
                    renderFloorPlan();
                    showToast('Table number reassigned.');
                    return;
                }
            }
            el.textContent = current;
        }

        input.addEventListener('blur', save);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { el.textContent = current; }
        });

        el.textContent = '';
        el.appendChild(input);
        input.focus();
        input.select();
    }

    function editCapacity(tableId, el) {
        const current = parseInt(el.textContent.trim());
        const input = document.createElement('input');
        input.type = 'number';
        input.value = current;
        input.min = 1;
        input.max = 50;
        input.className = 'edit-input';
        input.style.width = '60px';

        async function save() {
            const newCap = parseInt(input.value);
            if (newCap > 0 && newCap !== current) {
                const result = await api({ action: 'update_table', table_id: tableId, capacity: newCap });
                if (result) {
                    el.textContent = newCap;
                    const t = tables.find(x => x.id === tableId);
                    if (t) t.capacity = newCap;
                    updateTableMeta(tableId);
                    renderFloorPlan();
                    showToast('Capacity updated.');
                    return;
                }
            }
            el.textContent = current;
        }

        input.addEventListener('blur', save);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { el.textContent = current; }
        });

        el.textContent = '';
        el.appendChild(input);
        input.focus();
        input.select();
    }

    function buildTableCardHtml(tableId, tableNum, name, capacity) {
        return '<div class="table-card" id="card-' + tableId + '" data-table-id="' + tableId + '">' +
            '<div class="table-header" onclick="toggleTable(' + tableId + ')">' +
                '<h3>Table <span class="editable-number" onclick="event.stopPropagation(); editTableNumber(' + tableId + ', this)" title="Click to reassign table number">' + tableNum + '</span> &mdash; ' +
                    '<span class="editable" onclick="event.stopPropagation(); editTableName(' + tableId + ', this)">' + escHtml(name) + '</span>' +
                '</h3>' +
                '<span class="table-meta" id="meta-' + tableId + '">0 / <span class="editable-capacity" onclick="event.stopPropagation(); editCapacity(' + tableId + ', this)" title="Click to edit capacity">' + capacity + '</span> seats' +
                    '<span class="seat-remaining-badge seats-available">' + capacity + ' remaining</span>' +
                '</span>' +
            '</div>' +
            '<div class="table-body" id="tbody-' + tableId + '">' +
                '<div class="table-description" ondblclick="editTableNotes(' + tableId + ', this)" style="color:var(--color-text-muted);">' +
                    'No notes. <span style="font-size:0.75rem;">(double-click to add)</span>' +
                '</div>' +
                '<table class="guest-list"><thead><tr>' +
                    '<th style="width:2rem;">#</th><th>Guest</th><th>Group</th><th>Dietary</th><th>Actions</th>' +
                '</tr></thead><tbody id="guests-' + tableId + '"></tbody></table>' +
                '<div class="add-guest-row">' +
                    '<select id="add-select-' + tableId + '"><option value="">Add guest to this table...</option></select>' +
                    '<button class="btn-sm primary" onclick="seatGuestFromSelect(' + tableId + ')">Add</button>' +
                '</div>' +
                '<button class="delete-table-btn" onclick="deleteTable(' + tableId + ', \'' + escHtml(name).replace(/'/g, "\\'") + '\')">Delete this table...</button>' +
            '</div>' +
        '</div>';
    }

    async function addTable() {
        const input = document.getElementById('new-table-name');
        const name = input.value.trim();
        if (!name) { input.focus(); return; }
        const capInput = document.getElementById('new-table-capacity');
        const capacity = parseInt(capInput.value) || 10;
        const result = await api({ action: 'add_table', table_name: name, capacity: capacity });
        if (!result) return;
        showToast(result.message);

        const tableId = result.table_id;
        const tableNum = result.table_number;

        tables.push({ id: tableId, number: tableNum, name: name, capacity: capacity, guest_count: 0, pos_x: 50, pos_y: 85 });

        const unseated = document.getElementById('unseated-section');
        const dietary = document.querySelector('.dietary-summary');
        const container = document.querySelector('.seating-container');
        const ref = unseated || dietary || null;
        const temp = document.createElement('div');
        temp.innerHTML = buildTableCardHtml(tableId, tableNum, name, capacity);
        const card = temp.firstElementChild;
        if (ref) container.insertBefore(card, ref);
        else container.appendChild(card);

        const addSel = document.getElementById('add-select-' + tableId);
        if (addSel) {
            document.querySelectorAll('.unseated-guest[data-guest-id]').forEach(div => {
                const info = JSON.parse(div.dataset.guestInfo);
                const opt = document.createElement('option');
                opt.value = info.id;
                opt.textContent = info.name + ' (' + info.group + ')';
                addSel.appendChild(opt);
            });
        }

        card.addEventListener('dragover', (e) => { e.preventDefault(); card.style.outline = '2px solid var(--color-green)'; });
        card.addEventListener('dragleave', () => { card.style.outline = ''; });
        card.addEventListener('drop', (e) => {
            e.preventDefault();
            card.style.outline = '';
            const tid = card.dataset.tableId;
            if (reorderSourceRow && reorderSourceRow.closest('.table-card')?.dataset.tableId === tid) return;
            if (dragGuestId && tid) {
                moveGuest(dragGuestId, parseInt(tid));
                dragGuestId = null;
            }
        });

        const statTables = document.getElementById('stat-tables');
        if (statTables) statTables.textContent = tables.length;
        renderFloorPlan();

        input.value = '';
        capInput.value = '10';
        input.focus();
    }

    async function deleteTable(tableId, name) {
        if (!confirm('Delete table "' + name + '"? All guests must be unseated first.')) return;
        const result = await api({ action: 'delete_table', table_id: tableId });
        if (!result) return;
        showToast(result.message);

        const card = document.getElementById('card-' + tableId);
        if (card) card.remove();

        const idx = tables.findIndex(t => t.id === tableId);
        if (idx !== -1) tables.splice(idx, 1);

        const statTables = document.getElementById('stat-tables');
        if (statTables) statTables.textContent = tables.length;
        renderFloorPlan();
    }

    // ---- Table card toggle ----
    function saveExpansionState() {
        const expanded = Array.from(document.querySelectorAll('.table-body'))
            .filter(b => !b.classList.contains('collapsed'))
            .map(b => b.id.replace('tbody-', ''));
        localStorage.setItem('rehearsal-seating-expanded', JSON.stringify(expanded));
    }

    function restoreExpansionState() {
        try {
            const expanded = JSON.parse(localStorage.getItem('rehearsal-seating-expanded') || '[]');
            if (expanded.length > 0) {
                expanded.forEach(id => {
                    const body = document.getElementById('tbody-' + id);
                    if (body) body.classList.remove('collapsed');
                });
            }
        } catch(e) {}
    }

    function toggleTable(tableId) {
        const body = document.getElementById('tbody-' + tableId);
        if (body) body.classList.toggle('collapsed');
        saveExpansionState();
    }

    function toggleAll() {
        const bodies = document.querySelectorAll('.table-body');
        const anyVisible = Array.from(bodies).some(b => !b.classList.contains('collapsed'));
        bodies.forEach(b => anyVisible ? b.classList.add('collapsed') : b.classList.remove('collapsed'));
        saveExpansionState();
    }

    // ---- Within-table reorder ----
    let reorderSourceRow = null;

    document.querySelectorAll('.table-card').forEach(card => {
        card.addEventListener('dragover', (e) => { if (!dragGuestId && !reorderSourceRow) return; e.preventDefault(); card.style.outline = '2px solid var(--color-green)'; });
        card.addEventListener('dragleave', () => { card.style.outline = ''; });
        card.addEventListener('drop', (e) => {
            e.preventDefault();
            card.style.outline = '';
            const tableId = card.dataset.tableId;
            if (reorderSourceRow && reorderSourceRow.closest('.table-card')?.dataset.tableId === tableId) return;
            if (dragGuestId && tableId) {
                moveGuest(dragGuestId, parseInt(tableId));
                dragGuestId = null;
            }
        });
    });

    document.addEventListener('dragover', (e) => {
        if (!reorderSourceRow) return;
        const tbody = e.target.closest('.guest-list tbody');
        if (!tbody) return;
        e.preventDefault();
        const targetRow = e.target.closest('tr[data-guest-id], tr.plus-one-row');
        if (!targetRow || targetRow === reorderSourceRow) return;
        if (targetRow.closest('tbody') !== reorderSourceRow.closest('tbody')) return;

        tbody.querySelectorAll('.drag-over-above, .drag-over-below').forEach(r => {
            r.classList.remove('drag-over-above', 'drag-over-below');
        });

        const rect = targetRow.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) {
            targetRow.classList.add('drag-over-above');
        } else {
            targetRow.classList.add('drag-over-below');
        }
    });

    document.addEventListener('dragleave', (e) => {
        const targetRow = e.target.closest('tr[data-guest-id], tr.plus-one-row');
        if (targetRow) targetRow.classList.remove('drag-over-above', 'drag-over-below');
    });

    document.addEventListener('drop', (e) => {
        if (!reorderSourceRow) return;
        const tbody = e.target.closest('.guest-list tbody');
        if (!tbody) return;
        const targetRow = e.target.closest('tr[data-guest-id], tr.plus-one-row');
        if (!targetRow || targetRow === reorderSourceRow) return;
        if (targetRow.closest('tbody') !== reorderSourceRow.closest('tbody')) return;

        e.preventDefault();
        e.stopPropagation();
        dragGuestId = null;

        tbody.querySelectorAll('.drag-over-above, .drag-over-below').forEach(r => {
            r.classList.remove('drag-over-above', 'drag-over-below');
        });

        const rect = targetRow.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) {
            tbody.insertBefore(reorderSourceRow, targetRow);
        } else {
            tbody.insertBefore(reorderSourceRow, targetRow.nextElementSibling);
        }

        renumberSeats(tbody);
        saveSeatingOrder(tbody);
        reorderSourceRow = null;
    });

    const origDragGuest = dragGuest;
    dragGuest = function(e, guestId) {
        const row = e.target.closest('tr[data-guest-id]');
        reorderSourceRow = row || null;
        origDragGuest(e, guestId);
    };
    const origDragPlusOne = dragPlusOne;
    dragPlusOne = function(e, parentGuestId) {
        const row = e.target.closest('tr.plus-one-row');
        reorderSourceRow = row || null;
        origDragPlusOne(e, parentGuestId);
    };

    document.addEventListener('dragend', () => {
        document.querySelectorAll('.drag-over-above, .drag-over-below, .dragging-row').forEach(r => {
            r.classList.remove('drag-over-above', 'drag-over-below', 'dragging-row');
        });
        reorderSourceRow = null;
    });

    function renumberSeats(tbody) {
        let pos = 1;
        tbody.querySelectorAll('tr').forEach(row => {
            const numEl = row.querySelector('.seat-num');
            if (numEl) { numEl.textContent = pos; pos++; }
        });
    }

    async function saveSeatingOrder(tbody) {
        const tableId = parseInt(tbody.id.replace('guests-', ''));
        const seatEntries = [];
        tbody.querySelectorAll('tr[data-guest-id], tr.plus-one-row').forEach(row => {
            if (row.dataset.guestId) {
                seatEntries.push(parseInt(row.dataset.guestId));
            } else if (row.dataset.parentGuestId) {
                seatEntries.push('p' + row.dataset.parentGuestId);
            }
        });
        if (!seatEntries.length) return;
        const result = await api({ action: 'reorder_seats', table_id: tableId, seat_entries: seatEntries });
        if (result) showToast(result.message);
    }

    // ---- Grid (spreadsheet) view ----
    let gridViewActive = false;

    function buildGridView() {
        const container = document.getElementById('grid-view-container');
        const tableCards = document.querySelectorAll('.table-card[data-table-id]');
        const cols = [];
        tableCards.forEach(card => {
            const tableId = card.dataset.tableId;
            const h3 = card.querySelector('.table-header h3');
            const name = h3 ? h3.textContent.trim() : 'Table';
            const tbody = document.getElementById('guests-' + tableId);
            const guests = [];
            if (tbody) {
                tbody.querySelectorAll('tr[data-guest-id], tr.plus-one-row').forEach(row => {
                    if (row.dataset.guestId) {
                        const info = JSON.parse(row.dataset.guestInfo);
                        guests.push({ name: info.name, guestId: info.id, tableId: parseInt(tableId), isPlusOne: false, hasPlusOne: info.has_plus_one });
                    } else if (row.dataset.parentGuestId) {
                        const parentId = parseInt(row.dataset.parentGuestId);
                        const parentRow = tbody.querySelector('tr[data-guest-id="' + parentId + '"]');
                        const parentInfo = parentRow ? JSON.parse(parentRow.dataset.guestInfo) : null;
                        const poName = parentInfo ? parentInfo.plus_one_name : 'Plus one';
                        guests.push({ name: poName + ' (plus one)', guestId: null, parentGuestId: parentId, tableId: parseInt(tableId), isPlusOne: true });
                    }
                });
            }
            cols.push({ name, tableId: parseInt(tableId), guests, isUnseated: false });
        });

        const unseatedDivs = document.querySelectorAll('.unseated-guest[data-guest-id]');
        if (unseatedDivs.length) {
            const unseatedGuests = [];
            unseatedDivs.forEach(div => {
                const info = JSON.parse(div.dataset.guestInfo);
                unseatedGuests.push({ name: info.name, guestId: info.id, tableId: null, isPlusOne: false });
                if (info.has_plus_one) {
                    unseatedGuests.push({ name: info.plus_one_name + ' (plus one)', guestId: null, tableId: null, isPlusOne: true });
                }
            });
            cols.push({ name: 'Unseated (' + unseatedDivs.length + ')', tableId: null, guests: unseatedGuests, isUnseated: true });
        }

        if (!cols.length) {
            container.innerHTML = '<p style="color:var(--color-text-muted);padding:1rem;">No tables yet.</p>';
            return;
        }

        const maxRows = Math.max(0, ...cols.map(c => c.guests.length));
        let html = '<div class="grid-spreadsheet-wrapper"><table class="grid-spreadsheet"><thead><tr>';
        cols.forEach(c => {
            html += c.isUnseated ? '<th class="grid-unseated-header">' + escHtml(c.name) + '</th>' : '<th>' + escHtml(c.name) + '</th>';
        });
        html += '</tr><tr class="grid-info-row">';
        cols.forEach(c => {
            if (c.isUnseated) { html += '<td></td>'; return; }
            const tData = tables.find(t => t.id === c.tableId);
            const cap = tData ? tData.capacity : '?';
            const remaining = tData ? tData.capacity - c.guests.length : 0;
            let badge = remaining > 0 ? '<span style="font-size:0.7rem;">' + remaining + ' left</span>'
                : remaining === 0 ? '<span style="font-size:0.7rem;">Full</span>'
                : '<span style="font-size:0.7rem;">Over ' + Math.abs(remaining) + '</span>';
            html += '<td>' + c.guests.length + '/' + cap + ' ' + badge + '</td>';
        });
        html += '</tr></thead><tbody>';
        for (let i = 0; i < maxRows; i++) {
            html += '<tr>';
            cols.forEach(c => {
                const entry = c.guests[i];
                if (entry) {
                    const seatNum = i + 1;
                    if (entry.isPlusOne) {
                        html += '<td class="grid-cell-plusone" draggable="true" data-parent-guest-id="' + entry.parentGuestId + '" data-table-id="' + entry.tableId + '">'
                            + '<span class="grid-seat-num">' + seatNum + '.</span>' + escHtml(entry.name) + '<span class="grid-move-icon">&#x21c4;</span></td>';
                    } else if (c.isUnseated) {
                        html += '<td class="grid-cell-guest grid-cell-unseated" data-guest-id="' + entry.guestId + '" data-table-id="">'
                            + escHtml(entry.name) + '<span class="grid-move-icon">&#x21c4;</span></td>';
                    } else {
                        html += '<td class="grid-cell-guest" draggable="true" data-guest-id="' + entry.guestId + '" data-table-id="' + entry.tableId + '">'
                            + '<span class="grid-seat-num">' + seatNum + '.</span>' + escHtml(entry.name) + '<span class="grid-move-icon">&#x21c4;</span></td>';
                    }
                } else {
                    html += '<td></td>';
                }
            });
            html += '</tr>';
        }
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    // Grid click handler for move select
    document.getElementById('grid-view-container').addEventListener('click', function(e) {
        if (e.target.closest('.grid-move-select')) return;
        const cell = e.target.closest('.grid-cell-guest');
        if (!cell) return;
        const existing = cell.querySelector('.grid-move-select');
        if (existing) { existing.remove(); return; }
        document.querySelectorAll('.grid-move-select').forEach(s => s.remove());
        const guestId = parseInt(cell.dataset.guestId);
        const currentTableId = cell.dataset.tableId ? parseInt(cell.dataset.tableId) : null;
        const isUnseated = currentTableId === null;
        const sel = document.createElement('select');
        sel.className = 'grid-move-select';
        sel.innerHTML = '<option value="">' + (isUnseated ? 'Assign to...' : 'Move to...') + '</option>';
        tables.forEach(t => { if (t.id !== currentTableId) sel.innerHTML += '<option value="' + t.id + '">T' + t.number + ': ' + escHtml(t.name) + '</option>'; });
        if (!isUnseated) sel.innerHTML += '<option value="unseat">Unseat</option>';
        cell.appendChild(sel);
        sel.focus();
        let changed = false;
        sel.addEventListener('change', async function() {
            changed = true;
            const val = sel.value;
            sel.remove();
            if (!val) return;
            if (val === 'unseat') await unseatGuest(guestId);
            else await moveGuest(guestId, parseInt(val));
            if (gridViewActive) buildGridView();
        });
        sel.addEventListener('blur', function() { setTimeout(() => { if (!changed) sel.remove(); }, 150); });
    });

    // Grid drag-and-drop
    (function() {
        const gridContainer = document.getElementById('grid-view-container');
        let gridDragGuestId = null, gridDragTableId = null, gridDragIsPlusOne = false;

        gridContainer.addEventListener('dragstart', function(e) {
            let cell = e.target.closest('.grid-cell-guest[draggable="true"]');
            if (cell) { gridDragGuestId = parseInt(cell.dataset.guestId); gridDragTableId = parseInt(cell.dataset.tableId); gridDragIsPlusOne = false; }
            else { cell = e.target.closest('.grid-cell-plusone[draggable="true"]'); if (!cell) return; gridDragGuestId = parseInt(cell.dataset.parentGuestId); gridDragTableId = parseInt(cell.dataset.tableId); gridDragIsPlusOne = true; }
            cell.classList.add('grid-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        });

        gridContainer.addEventListener('dragover', function(e) {
            const cell = e.target.closest('.grid-cell-guest[draggable="true"], .grid-cell-plusone[draggable="true"]');
            if (!cell) return;
            let targetTableId = cell.dataset.tableId ? parseInt(cell.dataset.tableId) : null;
            if (gridDragTableId !== targetTableId) return;
            e.preventDefault();
            gridContainer.querySelectorAll('.grid-drag-over-above, .grid-drag-over-below').forEach(el => el.classList.remove('grid-drag-over-above', 'grid-drag-over-below'));
            const rect = cell.getBoundingClientRect();
            cell.classList.add(e.clientY < rect.top + rect.height / 2 ? 'grid-drag-over-above' : 'grid-drag-over-below');
        });

        gridContainer.addEventListener('dragleave', function(e) {
            const cell = e.target.closest('.grid-cell-guest, .grid-cell-plusone');
            if (cell) cell.classList.remove('grid-drag-over-above', 'grid-drag-over-below');
        });

        function findPlusOneRow(tbody, guestRow) {
            const guestId = guestRow.dataset.guestId;
            const poRow = tbody.querySelector('tr.plus-one-row[data-parent-guest-id="' + guestId + '"]');
            if (!poRow) return null;
            const allRows = Array.from(tbody.querySelectorAll('tr'));
            return { row: poRow, isBefore: allRows.indexOf(poRow) < allRows.indexOf(guestRow) };
        }

        gridContainer.addEventListener('drop', async function(e) {
            e.preventDefault();
            gridContainer.querySelectorAll('.grid-drag-over-above, .grid-drag-over-below, .grid-dragging').forEach(el => el.classList.remove('grid-drag-over-above', 'grid-drag-over-below', 'grid-dragging'));
            if (!gridDragGuestId || !gridDragTableId) return;
            let dropCell = e.target.closest('.grid-cell-guest[draggable="true"], .grid-cell-plusone[draggable="true"]');
            if (!dropCell) return;
            const isBelow = e.clientY >= dropCell.getBoundingClientRect().top + dropCell.getBoundingClientRect().height / 2;
            let dropGuestId = dropCell.classList.contains('grid-cell-plusone') ? parseInt(dropCell.dataset.parentGuestId) : parseInt(dropCell.dataset.guestId);
            let dropOnPlusOne = dropCell.classList.contains('grid-cell-plusone');
            if (!dropGuestId) { gridDragGuestId = null; gridDragTableId = null; return; }

            const tbody = document.getElementById('guests-' + gridDragTableId);
            if (!tbody) return;
            const dragRow = tbody.querySelector('tr[data-guest-id="' + gridDragGuestId + '"]');
            if (!dragRow) return;

            if (gridDragIsPlusOne && dropGuestId === gridDragGuestId) {
                const po = findPlusOneRow(tbody, dragRow);
                if (po) {
                    if (!po.isBefore) tbody.insertBefore(po.row, dragRow);
                    else tbody.insertBefore(po.row, dragRow.nextElementSibling);
                    renumberSeats(tbody);
                    await saveSeatingOrder(tbody);
                }
            } else {
                let rowToMove = gridDragIsPlusOne ? tbody.querySelector('tr.plus-one-row[data-parent-guest-id="' + gridDragGuestId + '"]') : dragRow;
                if (!rowToMove) { gridDragGuestId = null; gridDragTableId = null; return; }
                let dropTargetRow = dropOnPlusOne ? tbody.querySelector('tr.plus-one-row[data-parent-guest-id="' + dropGuestId + '"]') : tbody.querySelector('tr[data-guest-id="' + dropGuestId + '"]');
                if (!dropTargetRow || dropTargetRow === rowToMove) { gridDragGuestId = null; gridDragTableId = null; return; }
                rowToMove.remove();
                tbody.insertBefore(rowToMove, isBelow ? dropTargetRow.nextElementSibling : dropTargetRow);
                renumberSeats(tbody);
                await saveSeatingOrder(tbody);
            }

            buildGridView();
            gridDragGuestId = null; gridDragTableId = null; gridDragIsPlusOne = false;
        });

        gridContainer.addEventListener('dragend', function() {
            gridContainer.querySelectorAll('.grid-drag-over-above, .grid-drag-over-below, .grid-dragging').forEach(el => el.classList.remove('grid-drag-over-above', 'grid-drag-over-below', 'grid-dragging'));
            gridDragGuestId = null; gridDragTableId = null; gridDragIsPlusOne = false;
        });
    })();

    function switchView(view) {
        const gridContainer = document.getElementById('grid-view-container');
        const cardElements = document.querySelectorAll('.table-card');
        const cardsBtn = document.getElementById('view-cards-btn');
        const gridBtn = document.getElementById('view-grid-btn');
        if (view === 'grid') {
            gridViewActive = true;
            buildGridView();
            gridContainer.style.display = '';
            cardElements.forEach(c => c.style.display = 'none');
            cardsBtn.classList.remove('active');
            gridBtn.classList.add('active');
        } else {
            gridViewActive = false;
            gridContainer.style.display = 'none';
            cardElements.forEach(c => c.style.display = '');
            cardsBtn.classList.add('active');
            gridBtn.classList.remove('active');
        }
    }

    // ---- Keyboard shortcuts ----
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
            if (lastAction) { e.preventDefault(); undoLastAction(); }
        }
        if (e.key === 'Escape') {
            const modal = document.getElementById('text-export-modal');
            if (modal?.classList.contains('open')) closeTextExport();
            const shareModal = document.getElementById('share-modal');
            if (shareModal?.classList.contains('open')) closeShareModal();
        }
    });

    // ---- Guest search & add/remove from rehearsal ----
    let searchTimer = null;
    const searchInput = document.getElementById('guest-search-input');
    const searchResults = document.getElementById('search-results');

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(doGuestSearch, 300);
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { searchInput.value = ''; searchResults.innerHTML = ''; }
        });
    }

    async function doGuestSearch() {
        const q = searchInput.value.trim();
        if (q.length < 2) { searchResults.innerHTML = ''; return; }
        const result = await api({ action: 'search_guests', query: q });
        if (!result) return;
        renderSearchResults(result.guests);
    }

    function renderSearchResults(guests) {
        if (!guests.length) {
            searchResults.innerHTML = '<p style="padding:0.5rem; color:var(--color-text-muted); font-size:0.85rem;">No guests found.</p>';
            return;
        }
        let html = '';
        guests.forEach(g => {
            const invited = g.rehearsal_invited;
            const badgeClass = invited ? 'yes' : 'no';
            const badgeText = invited ? 'Attending' : 'Not invited';
            const actionBtn = invited
                ? '<button class="btn-uninvite" onclick="toggleRehearsal(' + g.id + ', false, this)">Remove</button>'
                : '<button class="btn-invite" onclick="toggleRehearsal(' + g.id + ', true, this)">Add</button>';

            let poHtml = '';
            if (g.has_plus_one && g.plus_one_name) {
                const poInvited = g.plus_one_rehearsal_invited;
                const poChecked = poInvited ? ' checked' : '';
                poHtml = '<span class="po-toggle"><label><input type="checkbox" data-guest-id="' + g.id + '" onchange="togglePlusOneRehearsal(' + g.id + ', this.checked, this)"' + poChecked + '> +1: ' + escHtml(g.plus_one_name) + '</label></span>';
            }

            html += '<div class="search-result" data-search-guest-id="' + g.id + '">'
                + '<span class="search-result-name">' + escHtml(g.name) + '</span>'
                + '<span class="group-badge">' + escHtml(g.group) + '</span> '
                + '<span class="invited-badge ' + badgeClass + '">' + badgeText + '</span> '
                + poHtml
                + '<span style="margin-left:auto;">' + actionBtn + '</span>'
                + '</div>';
        });
        searchResults.innerHTML = html;
    }

    async function toggleRehearsal(guestId, invite, btnEl) {
        const result = await api({ action: 'set_rehearsal_invited', guest_id: guestId, invited: invite ? 1 : 0 });
        if (!result) return;
        showToast(result.message);

        if (invite) {
            // Guest was added — put them in the unseated list
            const info = result.guest;
            ensureUnseatedSection();
            const list = document.getElementById('unseated-list');
            // Remove if already there (shouldn't happen, but safety)
            const existing = list.querySelector('.unseated-guest[data-guest-id="' + guestId + '"]');
            if (!existing) {
                list.appendChild(buildUnseatedDiv(info));
                updateUnseatedAddSelects(info, 'remove'); // remove from "add to table" selects? no — add them
                // Actually, add them to the "add guest to table" selects
                document.querySelectorAll('[id^="add-select-"]').forEach(sel => {
                    const opt = document.createElement('option');
                    opt.value = info.id;
                    opt.textContent = info.name + ' (' + info.group + ')';
                    sel.appendChild(opt);
                });
            }
            updateUnseatedSection();
            updateStats();
        } else {
            // Guest was removed — remove from unseated list or from a table
            removeGuestFromDom(guestId);
        }

        // Refresh search results to reflect new state
        doGuestSearch();
    }

    async function togglePlusOneRehearsal(guestId, invite, cbEl) {
        const result = await api({ action: 'set_rehearsal_invited', guest_id: guestId, invited: 1, plus_one: invite ? 1 : 0 });
        if (!result) return;
        const poName = invite ? 'Plus-one added to rehearsal.' : 'Plus-one removed from rehearsal.';
        showToast(poName);

        // Update the guest info in DOM if they're seated or unseated
        // Simplest approach: reload page for seated guests with plus-ones changing
        // But for unseated, we can update the data attribute
        const unseatedDiv = document.querySelector('.unseated-guest[data-guest-id="' + guestId + '"]');
        if (unseatedDiv) {
            const info = JSON.parse(unseatedDiv.dataset.guestInfo);
            info.has_plus_one = invite;
            unseatedDiv.dataset.guestInfo = JSON.stringify(info);
        }

        // If seated, update the guest info data and handle plus-one row
        const seatedRow = document.querySelector('tr[data-guest-id="' + guestId + '"]');
        if (seatedRow) {
            const info = JSON.parse(seatedRow.dataset.guestInfo);
            info.has_plus_one = invite;
            seatedRow.dataset.guestInfo = JSON.stringify(info);
            const tbody = seatedRow.closest('tbody');
            const tableId = tbody?.id?.replace('guests-', '');
            const existingPoRow = tbody?.querySelector('tr.plus-one-row[data-parent-guest-id="' + guestId + '"]');
            if (invite && !existingPoRow) {
                const poRow = buildPlusOneRow(info);
                if (poRow) {
                    tbody.insertBefore(poRow, seatedRow.nextElementSibling);
                    renumberSeats(tbody);
                    saveSeatingOrder(tbody);
                }
            } else if (!invite && existingPoRow) {
                existingPoRow.remove();
                renumberSeats(tbody);
                saveSeatingOrder(tbody);
            }
            if (tableId) updateTableMeta(tableId);
            renderFloorPlan();
        }

        doGuestSearch();
    }

    async function removeFromRehearsal(guestId) {
        const result = await api({ action: 'set_rehearsal_invited', guest_id: guestId, invited: 0 });
        if (!result) return;
        showToast(result.message);
        removeGuestFromDom(guestId);
    }

    function removeGuestFromDom(guestId) {
        // Remove from unseated section
        const unseatedDiv = document.querySelector('.unseated-guest[data-guest-id="' + guestId + '"]');
        if (unseatedDiv) {
            unseatedDiv.remove();
            updateUnseatedSection();
        }

        // Remove from seated table
        const seatedRow = document.querySelector('tr[data-guest-id="' + guestId + '"]');
        if (seatedRow) {
            const tbody = seatedRow.closest('tbody');
            const tableId = tbody?.id?.replace('guests-', '');
            const poRow = tbody?.querySelector('tr.plus-one-row[data-parent-guest-id="' + guestId + '"]');
            if (poRow) poRow.remove();
            seatedRow.remove();
            if (tbody) { renumberSeats(tbody); saveSeatingOrder(tbody); }
            if (tableId) updateTableMeta(tableId);
            renderFloorPlan();
        }

        // Remove from "add guest to table" selects
        document.querySelectorAll('[id^="add-select-"] option[value="' + guestId + '"]').forEach(opt => opt.remove());

        updateStats();
    }

    // ---- Init ----
    restoreExpansionState();
    renderFloorPlan();

    window.addEventListener('beforeunload', (e) => {
        if (positionsDirty) { e.preventDefault(); e.returnValue = ''; }
    });

    document.getElementById('new-table-name')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') addTable();
    });

    // ---- Export functions ----
    function buildPlainText() {
        const d = getExportData();
        const line = '\u2501'.repeat(44);
        const thinLine = '\u2500'.repeat(44);
        let totalGuests = 0;
        d.tables.forEach(t => totalGuests += t.guests.length);

        let text = 'REHEARSAL DINNER SEATING\n';
        text += 'Jacob & Melissa\n';
        text += totalGuests + ' guests across ' + d.tables.length + ' tables\n';
        text += line + '\n\n';

        d.tables.forEach(t => {
            text += 'TABLE ' + t.number + ' \u2014 ' + t.name;
            text += ' (' + t.guests.length + '/' + t.capacity + ')\n';
            text += thinLine + '\n';
            t.guests.forEach((g, i) => {
                const num = String(i + 1).padStart(2, ' ');
                let entry = '  ' + num + '. ' + g.name;
                if (g.dietary) entry += '  [' + g.dietary + ']';
                text += entry + '\n';
            });
            text += '\n';
        });

        if (d.unseated.length > 0) {
            text += 'UNSEATED (' + d.unseated.length + ')\n';
            text += thinLine + '\n';
            d.unseated.forEach(name => { text += '   - ' + name + '\n'; });
            text += '\n';
        }

        const dietary = [];
        d.tables.forEach(t => {
            t.guests.forEach(g => {
                if (g.dietary) dietary.push({ name: g.name, table: t.number, tableName: t.name, dietary: g.dietary });
            });
        });
        if (dietary.length > 0) {
            text += 'DIETARY RESTRICTIONS\n' + thinLine + '\n';
            dietary.forEach(dg => { text += '  ' + dg.name + ' (Table ' + dg.table + ')\n    ' + dg.dietary + '\n'; });
        }
        return text;
    }

    function showTextExport() {
        document.getElementById('text-export-content').textContent = buildPlainText();
        document.getElementById('text-export-modal').classList.add('open');
        document.getElementById('copy-btn').textContent = 'Copy to Clipboard';
    }

    function closeTextExport() {
        document.getElementById('text-export-modal').classList.remove('open');
    }

    function copyTextExport() {
        const text = document.getElementById('text-export-content').textContent;
        navigator.clipboard.writeText(text).then(() => {
            document.getElementById('copy-btn').textContent = 'Copied!';
            showToast('Copied to clipboard.');
            setTimeout(() => { document.getElementById('copy-btn').textContent = 'Copy to Clipboard'; }, 2000);
        });
    }

    function openPrintView() {
        const d = getExportData();
        let totalGuests = 0;
        d.tables.forEach(t => totalGuests += t.guests.length);

        let tablesHtml = '';
        d.tables.forEach(t => {
            let guestsHtml = '';
            t.guests.forEach(g => {
                let line = '<li>' + escHtml(g.name);
                if (g.dietary) line += ' <span class="dietary">[' + escHtml(g.dietary) + ']</span>';
                line += '</li>';
                guestsHtml += line;
            });
            tablesHtml += '<div class="table-card"><h2>Table ' + t.number + ' &mdash; ' + escHtml(t.name)
                + ' <span class="cap">(' + t.guests.length + '/' + t.capacity + ')</span></h2><ol>' + guestsHtml + '</ol></div>';
        });

        let unseatedHtml = '';
        if (d.unseated.length > 0) {
            let items = '';
            d.unseated.forEach(name => { items += '<li>' + escHtml(name) + '</li>'; });
            unseatedHtml = '<div class="unseated-section"><h2>Unseated Guests (' + d.unseated.length + ')</h2><ul>' + items + '</ul></div>';
        }

        const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Rehearsal Dinner Seating &mdash; Jacob &amp; Melissa</title><style>'
            + '*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }'
            + 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; color: #222; padding: 1.5rem; line-height: 1.4; }'
            + 'h1 { text-align: center; font-size: 1.5rem; margin-bottom: 0.25rem; }'
            + '.subtitle { text-align: center; font-size: 0.9rem; color: #666; margin-bottom: 1.25rem; }'
            + '.grid { columns: 3; column-gap: 1.25rem; }'
            + '.table-card { break-inside: avoid; border: 1px solid #ccc; border-radius: 6px; padding: 0.6rem 0.75rem; margin-bottom: 1rem; }'
            + '.table-card h2 { font-size: 0.95rem; margin-bottom: 0.35rem; border-bottom: 1px solid #e0e0e0; padding-bottom: 0.3rem; }'
            + '.table-card h2 .cap { font-weight: normal; color: #888; font-size: 0.85rem; }'
            + '.table-card ol { padding-left: 1.4rem; font-size: 0.85rem; }'
            + '.table-card li { margin-bottom: 0.1rem; }'
            + '.dietary { color: #b45309; font-size: 0.78rem; }'
            + '.unseated-section { margin-top: 1.5rem; border-top: 2px solid #999; padding-top: 0.75rem; }'
            + '.unseated-section h2 { font-size: 1rem; margin-bottom: 0.4rem; }'
            + '.unseated-section ul { padding-left: 1.4rem; font-size: 0.85rem; columns: 3; }'
            + '@media print { body { padding: 0.5cm; } .grid { columns: 3; } }'
            + '@media (max-width: 800px) { .grid { columns: 2; } }'
            + '@media (max-width: 500px) { .grid { columns: 1; } }'
            + '</style></head><body>'
            + '<h1>Rehearsal Dinner Seating &mdash; Jacob &amp; Melissa</h1>'
            + '<div class="subtitle">' + totalGuests + ' guests across ' + d.tables.length + ' tables</div>'
            + '<div class="grid">' + tablesHtml + '</div>'
            + unseatedHtml
            + '<script>setTimeout(function(){ window.print(); }, 400);<\/script>'
            + '</body></html>';

        const w = window.open('', '_blank');
        w.document.write(html);
        w.document.close();
    }

    document.getElementById('text-export-modal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeTextExport();
    });
    </script>
</body>
</html>
