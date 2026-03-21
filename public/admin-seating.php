<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/admin_auth.php';

$auth = requireAdminAuth();
$authenticated = $auth['authenticated'];
$error = $auth['error'];

$seatingData = [];
$unseatedGuests = [];
$stats = [];
$allTablesJson = '[]';
$exportJson = '[]';

if ($authenticated) {
    // CSV export handler — must run before any HTML output
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("
                SELECT st.table_number, st.table_name,
                       g.first_name, g.last_name, g.group_name, g.dietary,
                       g.has_plus_one, g.plus_one_name, g.plus_one_reception_attending, g.plus_one_dietary
                FROM seating_tables st
                LEFT JOIN guests g ON g.seating_table_id = st.id
                ORDER BY st.table_number, g.seat_number, g.last_name, g.first_name
            ");
            $rows = $stmt->fetchAll();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="seating-chart.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($out, ['Table #', 'Table Name', 'Guest Name', 'Group', 'Dietary Restrictions']);
            foreach ($rows as $r) {
                if (!$r['first_name']) continue;
                fputcsv($out, [
                    $r['table_number'],
                    $r['table_name'],
                    $r['first_name'] . ' ' . $r['last_name'],
                    $r['group_name'],
                    $r['dietary'] ?? '',
                ]);
                if ($r['has_plus_one'] && $r['plus_one_reception_attending'] === 'yes') {
                    fputcsv($out, [
                        $r['table_number'],
                        $r['table_name'],
                        ($r['plus_one_name'] ?: 'Guest of ' . $r['first_name']) . ' (plus one)',
                        '',
                        $r['plus_one_dietary'] ?? '',
                    ]);
                }
            }
            // Unseated guests
            $stmt2 = $pdo->query("
                SELECT first_name, last_name, group_name, dietary
                FROM guests
                WHERE seating_table_id IS NULL AND reception_attending = 'yes'
                ORDER BY group_name, last_name, first_name
            ");
            foreach ($stmt2->fetchAll() as $ug) {
                fputcsv($out, ['', '(Unseated)', $ug['first_name'] . ' ' . $ug['last_name'], $ug['group_name'], $ug['dietary'] ?? '']);
            }
            fclose($out);
            exit;
        } catch (Exception $e) {
            $error = 'CSV export failed: ' . htmlspecialchars($e->getMessage());
        }
    }

    try {
        $pdo = getDbConnection();

        // Get all tables with positions
        $tablesStmt = $pdo->query("
            SELECT id, table_number, table_name, capacity, notes, pos_x, pos_y
            FROM seating_tables
            ORDER BY table_number
        ");
        $allTables = $tablesStmt->fetchAll();

        // Get all tables with their guests
        $stmt = $pdo->query("
            SELECT st.id as table_id, st.table_number, st.table_name, st.capacity, st.notes as table_notes,
                   st.pos_x, st.pos_y,
                   g.id as guest_id, g.first_name, g.last_name, g.group_name, g.seat_number,
                   g.reception_attending, g.dietary,
                   g.has_plus_one, g.plus_one_name, g.plus_one_reception_attending, g.plus_one_dietary
            FROM seating_tables st
            LEFT JOIN guests g ON g.seating_table_id = st.id
            ORDER BY st.table_number, g.seat_number, g.last_name, g.first_name
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

        // Get unseated guests who are attending the reception
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, group_name, reception_attending, dietary,
                   has_plus_one, plus_one_name, plus_one_reception_attending, plus_one_dietary
            FROM guests
            WHERE seating_table_id IS NULL
              AND reception_attending = 'yes'
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
                if ($g['has_plus_one'] && $g['plus_one_reception_attending'] === 'yes') {
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
                if ($g['has_plus_one'] && $g['plus_one_reception_attending'] === 'yes') $poc++;
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

        // Build full export data for JS (plain text + visual)
        $exportData = [];
        foreach ($seatingData as $tn => $t) {
            $guests = [];
            foreach ($t['guests'] as $g) {
                $guests[] = [
                    'name' => $g['first_name'] . ' ' . $g['last_name'],
                    'dietary' => $g['dietary'] ?? '',
                ];
                if ($g['has_plus_one'] && $g['plus_one_reception_attending'] === 'yes') {
                    $guests[] = [
                        'name' => ($g['plus_one_name'] ?: 'Guest of ' . $g['first_name']) . ' (plus one)',
                        'dietary' => $g['plus_one_dietary'] ?? '',
                    ];
                }
            }
            $exportData[] = [
                'number' => $tn,
                'name' => $t['table_name'],
                'capacity' => $t['capacity'],
                'pos_x' => floatval($t['pos_x'] ?? 50),
                'pos_y' => floatval($t['pos_y'] ?? 50),
                'guests' => $guests,
            ];
        }
        $unseatedExport = [];
        foreach ($unseatedGuests as $ug) {
            $unseatedExport[] = $ug['first_name'] . ' ' . $ug['last_name'];
        }
        $exportJson = json_encode(['tables' => $exportData, 'unseated' => $unseatedExport, 'stats' => $stats]);

    } catch (Exception $e) {
        $error = 'Error loading seating chart: ' . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Seating Chart - Jacob & Melissa";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
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
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        .toast.show { opacity: 1; }
        .toast.success { background-color: #2d5016; }
        .toast.error { background-color: #8b0000; }

        /* ---- Floor Plan ---- */
        .floorplan-wrapper {
            margin-bottom: 2rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .floorplan-header {
            background-color: #f8f8f8;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #ddd;
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
            border: 2px solid #ccc;
            border-radius: 4px;
        }
        .fp-dancefloor {
            position: absolute;
            left: 35%;
            top: 30%;
            width: 30%;
            height: 40%;
            border: 2px dashed #bbb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-size: 0.85rem;
            font-style: italic;
            background: rgba(255,255,255,0.5);
        }
        .fp-dj {
            position: absolute;
            bottom: 3%;
            left: 50%;
            transform: translateX(-50%);
            background: #666;
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .fp-sweetheart-label {
            position: absolute;
            top: 2%;
            left: 50%;
            transform: translateX(-50%);
            color: #999;
            font-size: 0.7rem;
            font-style: italic;
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
        .fp-table.sweetheart {
            border-radius: 6px;
            width: 90px;
            height: 40px;
        }

        .fp-edit-hint {
            color: #999;
            font-size: 0.75rem;
        }
        .fp-btn {
            background: none;
            border: 1px solid #ccc;
            padding: 0.3rem 0.7rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            color: #666;
        }
        .fp-btn:hover { background: #eee; }
        .fp-btn.active { background: var(--color-green); color: white; border-color: var(--color-green); }

        /* ---- Table cards ---- */
        .table-card {
            border: 1px solid #ddd;
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
        .table-body { padding: 0; }
        .table-body.collapsed { display: none; }
        .table-description {
            padding: 0.5rem 1.25rem;
            color: #666;
            font-style: italic;
            font-size: 0.9rem;
            border-bottom: 1px solid #eee;
        }

        /* ---- Inline editing ---- */
        .editable {
            cursor: pointer;
            border-bottom: 1px dashed rgba(255,255,255,0.4);
        }
        .editable:hover { border-bottom-color: white; }
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
            border: 1px solid #ccc;
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
            border-bottom: 1px solid #f0f0f0;
        }
        .guest-list th {
            background-color: #f8f8f8;
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .guest-list tr:last-child td { border-bottom: none; }
        .guest-list tr:hover { background-color: #f9f9f5; }
        .guest-list tr.drag-over { background-color: #e8f5e0; }
        .guest-list tr.dragging-row { opacity: 0.4; }
        .plus-one-row td { padding-left: 2rem; color: #666; font-style: italic; }

        .dietary-badge {
            display: inline-block;
            background-color: #fff3cd;
            color: #856404;
            padding: 0.1rem 0.4rem;
            border-radius: 12px;
            font-size: 0.75rem;
        }
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
            border: 1px solid #ccc;
            max-width: 130px;
        }
        .btn-sm {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border: 1px solid #ccc;
            border-radius: 3px;
            cursor: pointer;
            background: white;
            white-space: nowrap;
        }
        .btn-sm:hover { background: #f0f0f0; }
        .btn-sm.danger { color: #8b0000; border-color: #dca; }
        .btn-sm.danger:hover { background: #fff0f0; }
        .btn-sm.primary { color: var(--color-green); border-color: var(--color-green); }
        .btn-sm.primary:hover { background: #e8f5e0; }

        /* ---- Add guest row ---- */
        .add-guest-row {
            padding: 0.5rem 1rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
            border-top: 1px solid #eee;
            background: #fcfcfc;
        }
        .add-guest-row select {
            flex: 1;
            font-size: 0.85rem;
            padding: 0.3rem;
            border-radius: 3px;
            border: 1px solid #ccc;
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

        /* ---- Dietary summary ---- */
        .dietary-summary {
            margin-top: 2rem;
            border: 1px solid #fff3cd;
            border-radius: 8px;
            padding: 1.5rem;
            background-color: #fffdf5;
        }
        .dietary-summary h2 { margin-top: 0; color: #856404; }
        .dietary-table { width: 100%; border-collapse: collapse; }
        .dietary-table th, .dietary-table td {
            padding: 0.5rem 1rem;
            text-align: left;
            border-bottom: 1px solid #f0e8d0;
        }
        .dietary-table th { background-color: #fff8e1; font-size: 0.85rem; }

        /* ---- Add/Delete table ---- */
        .table-management {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .table-management input {
            padding: 0.4rem 0.6rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .delete-table-btn {
            margin-left: auto;
            font-size: 0.75rem;
            color: #8b0000;
            background: none;
            border: none;
            cursor: pointer;
            opacity: 0.6;
            padding: 0.75rem 1.25rem;
        }
        .delete-table-btn:hover { opacity: 1; text-decoration: underline; }

        /* ---- Misc ---- */
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
            color: #666;
            margin-right: 0.5rem;
        }
        .export-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 0.85rem;
            color: #444;
            transition: all 0.2s;
        }
        .export-btn:hover { background: #f5f5f5; border-color: var(--color-green); color: var(--color-green); }
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
            background: white;
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
            border-bottom: 1px solid #eee;
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
            color: #999;
            padding: 0;
            line-height: 1;
        }
        .export-modal-close:hover { color: #333; }
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
            color: #333;
        }
        .export-modal-footer {
            padding: 0.75rem 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        .export-modal-footer button {
            padding: 0.5rem 1.2rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            border: 1px solid #ccc;
            background: white;
        }
        .export-modal-footer button:hover { background: #f5f5f5; }
        .export-modal-footer .btn-copy {
            background: var(--color-green);
            color: white;
            border-color: var(--color-green);
        }
        .export-modal-footer .btn-copy:hover { opacity: 0.9; }

        @media (max-width: 768px) {
            .seating-container { padding: 1rem; }
            .stats { flex-direction: column; gap: 1rem; }
            .fp-table { width: 44px; height: 44px; font-size: 0.6rem; }
            .fp-table-num { font-size: 0.75rem; }
            .guest-list th, .guest-list td { padding: 0.4rem 0.5rem; font-size: 0.85rem; }
            .guest-actions { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <main class="page-container">
        <div class="back-to-site"><a href="/">&#8592; Back to Main Site</a></div>

        <?php if (!$authenticated): ?>
            <div class="form-container">
                <h1 class="page-title">Seating Chart</h1>
                <?php if ($error): ?>
                    <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>
                <form method="POST" action="/admin-seating">
                    <input type="hidden" name="admin_login" value="1">
                    <div class="form-group required">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="seating-container">
                <div class="logout-link"><a href="/admin?logout=1">Logout</a></div>
                <h1 class="page-title">Seating Chart</h1>

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
                        <div class="stat-number" style="color:#2d5016;" id="stat-seated"><?php echo $stats['seated']; ?></div>
                        <div class="stat-label">Seated</div>
                    </div>
                    <?php if ($stats['unseated'] > 0): ?>
                    <div class="stat-item" id="stat-unseated-wrap">
                        <div class="stat-number" style="color:#dc3545;" id="stat-unseated"><?php echo $stats['unseated']; ?></div>
                        <div class="stat-label">Unseated</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-item">
                        <div class="stat-number" style="color:#856404;" id="stat-dietary"><?php echo $stats['dietary']; ?></div>
                        <div class="stat-label">Dietary Needs</div>
                    </div>
                </div>

                <!-- Export bar -->
                <div class="export-bar">
                    <span class="export-bar-label">Export:</span>
                    <button class="export-btn" onclick="exportVisual()" title="Download floor plan as image">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                        Floor Plan Image
                    </button>
                    <a class="export-btn" href="/admin-seating?export=csv" title="Download spreadsheet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                        CSV Spreadsheet
                    </a>
                    <button class="export-btn" onclick="showTextExport()" title="Plain text for email">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Copy for Email
                    </button>
                </div>

                <!-- Floor Plan -->
                <div class="floorplan-wrapper">
                    <div class="floorplan-header">
                        <h2>Ballroom Layout</h2>
                        <div>
                            <span class="fp-edit-hint" id="fp-drag-hint">Drag tables to reposition</span>
                            <button class="fp-btn" id="fp-save-btn" style="display:none;" onclick="savePositions()">Save Layout</button>
                        </div>
                    </div>
                    <div class="floorplan" id="floorplan">
                        <div class="floorplan-room">
                            <div class="fp-dancefloor">dance floor</div>
                            <div class="fp-dj">DJ</div>
                        </div>
                        <!-- Tables rendered by JS -->
                    </div>
                </div>

                <!-- Add table -->
                <div class="table-management">
                    <input type="text" id="new-table-name" placeholder="New table name...">
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
                        if ($g['has_plus_one'] && $g['plus_one_reception_attending'] === 'yes') $plusOneCount++;
                    }
                    $totalAtTable = $guestCount + $plusOneCount;
                    ?>
                    <div class="table-card" id="card-<?php echo $table['table_id']; ?>" data-table-id="<?php echo $table['table_id']; ?>">
                        <div class="table-header" onclick="toggleTable(<?php echo $table['table_id']; ?>)">
                            <h3>
                                Table <?php echo $tableNum; ?> &mdash;
                                <span class="editable" onclick="event.stopPropagation(); editTableName(<?php echo $table['table_id']; ?>, this)"><?php echo htmlspecialchars($table['table_name']); ?></span>
                            </h3>
                            <span class="table-meta" id="meta-<?php echo $table['table_id']; ?>"><?php echo $totalAtTable; ?> / <?php echo $table['capacity']; ?> seats</span>
                        </div>
                        <div class="table-body collapsed" id="tbody-<?php echo $table['table_id']; ?>">
                            <?php if (!empty($table['notes'])): ?>
                                <div class="table-description" ondblclick="editTableNotes(<?php echo $table['table_id']; ?>, this)">
                                    <?php echo htmlspecialchars($table['notes']); ?>
                                    <span style="font-size:0.75rem;color:#aaa;margin-left:0.5rem;">(double-click to edit)</span>
                                </div>
                            <?php else: ?>
                                <div class="table-description" ondblclick="editTableNotes(<?php echo $table['table_id']; ?>, this)" style="color:#bbb;">
                                    No notes. <span style="font-size:0.75rem;">(double-click to add)</span>
                                </div>
                            <?php endif; ?>
                            <table class="guest-list">
                                <thead>
                                    <tr>
                                        <th>Guest</th>
                                        <th>Group</th>
                                        <th>Dietary</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="guests-<?php echo $table['table_id']; ?>">
                                    <?php foreach ($table['guests'] as $guest): ?>
                                        <tr draggable="true"
                                            ondragstart="dragGuest(event, <?php echo $guest['guest_id']; ?>)"
                                            data-guest-id="<?php echo $guest['guest_id']; ?>">
                                            <td><?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></td>
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
                                        <?php if ($guest['has_plus_one'] && $guest['plus_one_reception_attending'] === 'yes'): ?>
                                        <tr class="plus-one-row">
                                            <td><?php echo htmlspecialchars($guest['plus_one_name'] ?: 'Guest of ' . $guest['first_name']); ?> (plus one)</td>
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
                        <p>RSVP'd yes for reception but no table assigned. Drag guests here to unseat, or use the dropdown to assign.</p>
                        <div id="unseated-list">
                            <?php foreach ($unseatedGuests as $ug): ?>
                                <div class="unseated-guest" data-guest-id="<?php echo $ug['id']; ?>"
                                     draggable="true" ondragstart="dragGuest(event, <?php echo $ug['id']; ?>)">
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
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

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
                        if ($g['has_plus_one'] && $g['plus_one_reception_attending'] === 'yes' && !empty($g['plus_one_dietary'])) {
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
                <h2>Seating Chart — Plain Text</h2>
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

    <!-- Hidden canvas for floor plan export -->
    <canvas id="export-canvas" style="display:none;"></canvas>

    <div class="toast" id="toast"></div>

    <script>
    // ---- Data ----
    const tables = <?php echo $allTablesJson; ?>;
    const exportData = <?php echo $exportJson; ?>;
    let positionsDirty = false;
    let dragGuestId = null;

    // ---- Toast ----
    function showToast(msg, type = 'success') {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + type + ' show';
        setTimeout(() => t.classList.remove('show'), 2500);
    }

    // ---- API helper ----
    async function api(data) {
        const res = await fetch('/api/seating', {
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
            el.title = 'T' + t.number + ': ' + t.name + ' (' + t.guest_count + '/' + t.capacity + ')';

            // Click to scroll to card
            el.addEventListener('click', (e) => {
                if (el.classList.contains('dragging')) return;
                const card = document.getElementById('card-' + t.id);
                if (card) {
                    // Expand card
                    const tbody = document.getElementById('tbody-' + t.id);
                    if (tbody) tbody.classList.remove('collapsed');
                    card.classList.add('highlight');
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => card.classList.remove('highlight'), 2000);
                }
                // Highlight on plan
                fp.querySelectorAll('.fp-table').forEach(x => x.classList.remove('selected'));
                el.classList.add('selected');
            });

            // Drag-and-drop for repositioning
            el.addEventListener('mousedown', (e) => startDragTable(e, el, t));
            el.addEventListener('touchstart', (e) => startDragTable(e.touches[0], el, t), { passive: false });

            // Drop zone for guests
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
        // Only left mouse button or touch
        if (e.button && e.button !== 0) return;

        const room = el.parentElement;
        const rect = room.getBoundingClientRect();
        let hasMoved = false;

        function onMove(ev) {
            const clientX = ev.touches ? ev.touches[0].clientX : ev.clientX;
            const clientY = ev.touches ? ev.touches[0].clientY : ev.clientY;
            const x = ((clientX - rect.left) / rect.width) * 100;
            const y = ((clientY - rect.top) / rect.height) * 100;
            const clampX = Math.max(5, Math.min(95, x));
            const clampY = Math.max(5, Math.min(95, y));
            el.style.left = clampX + '%';
            el.style.top = clampY + '%';
            tableData.pos_x = clampX;
            tableData.pos_y = clampY;
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

    function dropUnseat(e) {
        e.preventDefault();
        if (dragGuestId) {
            unseatGuest(dragGuestId);
            dragGuestId = null;
        }
    }

    // ---- Guest actions ----
    async function moveGuest(guestId, tableId) {
        if (!tableId) return;
        const result = await api({ action: 'move_guest', guest_id: guestId, table_id: parseInt(tableId) });
        if (result) {
            showToast(result.message);
            setTimeout(() => location.reload(), 600);
        }
    }

    async function unseatGuest(guestId) {
        const result = await api({ action: 'move_guest', guest_id: guestId, table_id: null });
        if (result) {
            showToast(result.message);
            setTimeout(() => location.reload(), 600);
        }
    }

    function seatGuestFromSelect(tableId) {
        const sel = document.getElementById('add-select-' + tableId);
        if (sel.value) {
            moveGuest(parseInt(sel.value), tableId);
        }
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
                    // Update floor plan
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
                el.innerHTML = (newNotes || 'No notes.') + ' <span style="font-size:0.75rem;color:#aaa;margin-left:0.5rem;">(double-click to edit)</span>';
                if (!newNotes) el.style.color = '#bbb';
                else el.style.color = '#666';
                showToast('Notes updated.');
            } else {
                el.innerHTML = (current || 'No notes.') + ' <span style="font-size:0.75rem;color:#aaa;margin-left:0.5rem;">(double-click to edit)</span>';
            }
        }

        input.addEventListener('blur', save);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') {
                el.innerHTML = (current || 'No notes.') + ' <span style="font-size:0.75rem;color:#aaa;margin-left:0.5rem;">(double-click to edit)</span>';
            }
        });

        el.textContent = '';
        el.appendChild(input);
        input.focus();
    }

    async function addTable() {
        const input = document.getElementById('new-table-name');
        const name = input.value.trim();
        if (!name) { input.focus(); return; }
        const result = await api({ action: 'add_table', table_name: name });
        if (result) {
            showToast(result.message);
            setTimeout(() => location.reload(), 600);
        }
    }

    async function deleteTable(tableId, name) {
        if (!confirm('Delete table "' + name + '"? All guests must be unseated first.')) return;
        const result = await api({ action: 'delete_table', table_id: tableId });
        if (result) {
            showToast(result.message);
            setTimeout(() => location.reload(), 600);
        }
    }

    // ---- Table card toggle ----
    function toggleTable(tableId) {
        const body = document.getElementById('tbody-' + tableId);
        if (body) body.classList.toggle('collapsed');
    }

    function toggleAll() {
        const bodies = document.querySelectorAll('.table-body');
        const anyVisible = Array.from(bodies).some(b => !b.classList.contains('collapsed'));
        bodies.forEach(b => anyVisible ? b.classList.add('collapsed') : b.classList.remove('collapsed'));
    }

    // ---- Drop zones on table cards ----
    document.querySelectorAll('.table-card').forEach(card => {
        card.addEventListener('dragover', (e) => { e.preventDefault(); card.style.outline = '2px solid var(--color-green)'; });
        card.addEventListener('dragleave', () => { card.style.outline = ''; });
        card.addEventListener('drop', (e) => {
            e.preventDefault();
            card.style.outline = '';
            const tableId = card.dataset.tableId;
            if (dragGuestId && tableId) {
                moveGuest(dragGuestId, parseInt(tableId));
                dragGuestId = null;
            }
        });
    });

    // ---- Init ----
    renderFloorPlan();

    // Warn on leave if positions unsaved
    window.addEventListener('beforeunload', (e) => {
        if (positionsDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Handle Enter key on new table input
    document.getElementById('new-table-name')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') addTable();
    });

    // ==============================
    // EXPORT FUNCTIONS
    // ==============================

    // ---- Plain text export ----
    function buildPlainText() {
        const d = exportData;
        const line = '━'.repeat(44);
        const thinLine = '─'.repeat(44);
        let totalGuests = 0;
        d.tables.forEach(t => totalGuests += t.guests.length);

        let text = '';
        text += 'SEATING CHART\n';
        text += 'Jacob & Melissa\'s Wedding Reception\n';
        text += totalGuests + ' guests across ' + d.tables.length + ' tables\n';
        text += line + '\n\n';

        d.tables.forEach(t => {
            text += 'TABLE ' + t.number + ' — ' + t.name;
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
            d.unseated.forEach(name => {
                text += '   - ' + name + '\n';
            });
            text += '\n';
        }

        // Dietary summary
        const dietary = [];
        d.tables.forEach(t => {
            t.guests.forEach(g => {
                if (g.dietary) dietary.push({ name: g.name, table: t.number, tableName: t.name, dietary: g.dietary });
            });
        });
        if (dietary.length > 0) {
            text += 'DIETARY RESTRICTIONS\n';
            text += thinLine + '\n';
            dietary.forEach(dg => {
                text += '  ' + dg.name + ' (Table ' + dg.table + ')\n';
                text += '    ' + dg.dietary + '\n';
            });
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
            setTimeout(() => {
                document.getElementById('copy-btn').textContent = 'Copy to Clipboard';
            }, 2000);
        });
    }

    // Close modal on overlay click or Escape
    document.getElementById('text-export-modal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeTextExport();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeTextExport();
    });

    // ---- Visual floor plan export ----
    function exportVisual() {
        const d = exportData;
        const canvas = document.getElementById('export-canvas');
        const W = 1200, H = 700;
        canvas.width = W;
        canvas.height = H;
        const ctx = canvas.getContext('2d');

        // Background
        ctx.fillStyle = '#faf9f6';
        ctx.fillRect(0, 0, W, H);

        // Room border
        const rm = { x: W * 0.03, y: H * 0.03, w: W * 0.94, h: H * 0.94 };
        ctx.strokeStyle = '#bbb';
        ctx.lineWidth = 2;
        ctx.strokeRect(rm.x, rm.y, rm.w, rm.h);

        // Title
        ctx.fillStyle = '#333';
        ctx.font = 'bold 20px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Seating Chart — Jacob & Melissa', W / 2, rm.y + 24);

        // Dance floor
        const df = { x: rm.x + rm.w * 0.35, y: rm.y + rm.h * 0.30, w: rm.w * 0.30, h: rm.h * 0.40 };
        ctx.setLineDash([6, 4]);
        ctx.strokeStyle = '#bbb';
        ctx.strokeRect(df.x, df.y, df.w, df.h);
        ctx.setLineDash([]);
        ctx.fillStyle = '#ccc';
        ctx.font = 'italic 14px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.fillText('dance floor', df.x + df.w / 2, df.y + df.h / 2 + 5);

        // DJ
        ctx.fillStyle = '#666';
        ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        const djY = rm.y + rm.h * 0.95;
        roundRect(ctx, W / 2 - 22, djY - 10, 44, 20, 3);
        ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.fillText('DJ', W / 2, djY + 4);

        // Tables
        const tableR = 38;
        const green = '#4d6b2e';
        const red = '#b44';

        d.tables.forEach(t => {
            const cx = rm.x + (t.pos_x / 100) * rm.w;
            const cy = rm.y + (t.pos_y / 100) * rm.h;
            const overCap = t.guests.length > t.capacity;
            const isSweetheart = t.capacity <= 2;

            ctx.fillStyle = overCap ? red : green;

            if (isSweetheart) {
                // Rectangle for sweetheart table
                const rw = 100, rh = 36;
                roundRect(ctx, cx - rw / 2, cy - rh / 2, rw, rh, 6);
                ctx.fill();

                ctx.fillStyle = '#fff';
                ctx.font = 'bold 13px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(t.name, cx, cy + 5);

                // Name label below
                ctx.fillStyle = '#555';
                ctx.font = '10px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                ctx.fillText(t.guests.map(g => g.name.split(' ')[0]).join(' & '), cx, cy + rh / 2 + 14);
            } else {
                // Circle for round tables
                ctx.beginPath();
                ctx.arc(cx, cy, tableR, 0, Math.PI * 2);
                ctx.fill();

                ctx.fillStyle = '#fff';
                ctx.font = 'bold 16px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(t.number, cx, cy - 2);

                ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                ctx.fillText(t.guests.length + '/' + t.capacity, cx, cy + 14);

                // Table name below circle
                ctx.fillStyle = '#555';
                ctx.font = '10px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                const nameLines = wrapText(t.name, 100);
                nameLines.forEach((line, i) => {
                    ctx.fillText(line, cx, cy + tableR + 14 + i * 12);
                });
            }
        });

        // Stats bar
        const statsY = H - 22;
        ctx.fillStyle = '#888';
        ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'left';
        let totalGuests = 0;
        d.tables.forEach(t => totalGuests += t.guests.length);
        ctx.fillText(d.tables.length + ' tables · ' + totalGuests + ' guests seated' +
            (d.unseated.length > 0 ? ' · ' + d.unseated.length + ' unseated' : ''), rm.x + 10, statsY);

        // Download
        const link = document.createElement('a');
        link.download = 'seating-chart-floorplan.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        showToast('Floor plan image downloaded.');
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r);
        ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    function wrapText(text, maxWidth) {
        if (text.length <= 14) return [text];
        const words = text.split(' ');
        const lines = [];
        let cur = '';
        words.forEach(w => {
            if ((cur + ' ' + w).trim().length > 14 && cur) {
                lines.push(cur);
                cur = w;
            } else {
                cur = (cur + ' ' + w).trim();
            }
        });
        if (cur) lines.push(cur);
        return lines.slice(0, 2); // max 2 lines
    }
    </script>
</body>
</html>
