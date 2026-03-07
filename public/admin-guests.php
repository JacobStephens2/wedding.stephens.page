<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/admin_auth.php';

session_start();

$error = '';
$success = '';
$authenticated = false;

// Check auth
if (isAdminAuthenticated()) {
    $authenticated = true;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['admin_login'])) {
    $password = trim($_POST['password'] ?? '');
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
    if ($password === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $authenticated = true;
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin-guests');
    exit;
}

// Handle add guest
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_guest'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            INSERT INTO guests (first_name, last_name, group_name, mailing_group, has_plus_one)
            VALUES (?, ?, ?, ?, ?)
        ");
        $mailingGroup = trim($_POST['mailing_group'] ?? '');
        $hasPlusOne = isset($_POST['has_plus_one']) && $_POST['has_plus_one'] === '1' ? 1 : 0;
        $stmt->execute([
            trim($_POST['first_name'] ?? ''),
            trim($_POST['last_name'] ?? ''),
            trim($_POST['group_name'] ?? ''),
            $mailingGroup !== '' ? (int)$mailingGroup : null,
            $hasPlusOne,
        ]);
        header('Location: /admin-guests?added=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error adding guest: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle update guest
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_guest'])) {
    try {
        $pdo = getDbConnection();
        $mailingGroup = trim($_POST['mailing_group'] ?? '');
        $ceremonyAttending = $_POST['ceremony_attending'] ?? '';
        $receptionAttending = $_POST['reception_attending'] ?? '';
        $hasPlusOne = isset($_POST['has_plus_one']) && $_POST['has_plus_one'] === '1' ? 1 : 0;
        
        // Derive overall attending from event-specific fields
        $ca = $ceremonyAttending !== '' ? $ceremonyAttending : null;
        $ra = $receptionAttending !== '' ? $receptionAttending : null;
        if ($ca === 'yes' || $ra === 'yes') {
            $attending = 'yes';
        } elseif ($ca === 'no' && $ra === 'no') {
            $attending = 'no';
        } else {
            $attending = null;
        }
        
        $stmt = $pdo->prepare("
            UPDATE guests 
            SET first_name = ?, last_name = ?, group_name = ?, mailing_group = ?,
                attending = ?, ceremony_attending = ?, reception_attending = ?, has_plus_one = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($_POST['first_name'] ?? ''),
            trim($_POST['last_name'] ?? ''),
            trim($_POST['group_name'] ?? ''),
            $mailingGroup !== '' ? (int)$mailingGroup : null,
            $attending,
            $ca,
            $ra,
            $hasPlusOne,
            (int)$_POST['guest_id'],
        ]);
        header('Location: /admin-guests?updated=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error updating guest: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle delete guest
if ($authenticated && isset($_GET['delete'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM guests WHERE id = ?");
        $stmt->execute([(int)$_GET['delete']]);
        header('Location: /admin-guests?deleted=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error deleting guest: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle CSV re-import
if ($authenticated && isset($_GET['reimport']) && $_GET['reimport'] === 'confirm') {
    try {
        $pdo = getDbConnection();
        $csvPath = __DIR__ . '/../private/Guest List Feb 10 2026.csv';
        
        if (!file_exists($csvPath)) {
            $error = 'CSV file not found.';
        } else {
            $pdo->exec("DELETE FROM guests");
            $pdo->exec("ALTER TABLE guests AUTO_INCREMENT = 1");
            
            $handle = fopen($csvPath, 'r');
            fgetcsv($handle); // skip summary row 1
            fgetcsv($handle); // skip summary row 2
            $headers = fgetcsv($handle); // header row
            
            $colMap = [];
            foreach ($headers as $i => $header) {
                $colMap[trim($header)] = $i;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO guests (first_name, last_name, group_name, guest_id, mailing_group)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $imported = 0;
            while (($row = fgetcsv($handle)) !== false) {
                $firstName = trim($row[$colMap['First Name']] ?? '');
                if (empty($firstName)) continue;
                
                $mailingGroup = trim($row[$colMap['Mailing Group']] ?? '');
                $stmt->execute([
                    $firstName,
                    trim($row[$colMap['Last Name']] ?? ''),
                    trim($row[$colMap['Group']] ?? ''),
                    trim($row[$colMap['id']] ?? ''),
                    ($mailingGroup !== '' && is_numeric($mailingGroup)) ? (int)$mailingGroup : null,
                ]);
                $imported++;
            }
            fclose($handle);
            
            header('Location: /admin-guests?reimported=' . $imported);
            exit;
        }
    } catch (Exception $e) {
        $error = 'Import error: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch guest for editing
$editGuest = null;
if ($authenticated && isset($_GET['edit'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editGuest = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error loading guest: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch guest for admin RSVP entry
$rsvpGuest = null;
if ($authenticated && isset($_GET['rsvp'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ?");
        $stmt->execute([(int)$_GET['rsvp']]);
        $rsvpGuest = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error loading guest for RSVP: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch all guests if authenticated
$guests = [];
$stats = ['total' => 0, 'attending' => 0, 'declined' => 0, 'pending' => 0, 'ceremony' => 0, 'reception' => 0, 'ceremony_declined' => 0, 'reception_declined' => 0];
if ($authenticated) {
    try {
        $pdo = getDbConnection();
        
        // Apply search filter if present
        $search = trim($_GET['search'] ?? '');
        $groupFilter = trim($_GET['group_filter'] ?? '');
        
        // Sorting
        $sort = $_GET['sort'] ?? 'mailing_group';
        $order = $_GET['order'] ?? 'ASC';
        
        $allowedSorts = ['first_name', 'last_name', 'mailing_group', 'group_name', 'has_plus_one', 'attending'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'mailing_group';
        }
        $order = ($order === 'DESC') ? 'DESC' : 'ASC';
        
        $where = [];
        $params = [];
        
        if ($search !== '') {
            $where[] = "(g.first_name LIKE ? OR g.last_name LIKE ? OR CONCAT(g.first_name, ' ', g.last_name) LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($groupFilter !== '') {
            $where[] = "g.mailing_group = ?";
            $params[] = (int)$groupFilter;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $pdo->prepare("
            SELECT g.*, ma.address_1, ma.address_2, ma.city, ma.state, ma.zip, ma.country
            FROM guests g
            LEFT JOIN mailing_addresses ma ON g.mailing_group = ma.mailing_group
            $whereClause
            ORDER BY g.$sort $order, g.id ASC
        ");
        $stmt->execute($params);
        $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Stats (always from full table)
        // Counts are in "invites" (guests + granted plus-ones), per specification.
        $statsStmt = $pdo->query("
            SELECT 
                (
                    COUNT(*) + COALESCE(SUM(CASE WHEN has_plus_one = 1 THEN 1 ELSE 0 END), 0)
                ) as total,
                (
                    COALESCE(SUM(CASE WHEN attending = 'yes' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_attending = 'yes' THEN 1 ELSE 0 END), 0)
                ) as attending,
                (
                    COALESCE(SUM(CASE WHEN attending = 'no' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_attending = 'no' THEN 1 ELSE 0 END), 0)
                ) as declined,
                (
                    COALESCE(SUM(CASE WHEN attending IS NULL THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_attending IS NULL THEN 1 ELSE 0 END), 0)
                ) as pending,
                (
                    COALESCE(SUM(CASE WHEN ceremony_attending = 'yes' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_ceremony_attending = 'yes' THEN 1 ELSE 0 END), 0)
                ) as ceremony,
                (
                    COALESCE(SUM(CASE WHEN reception_attending = 'yes' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_reception_attending = 'yes' THEN 1 ELSE 0 END), 0)
                ) as reception,
                (
                    COALESCE(SUM(CASE WHEN ceremony_attending = 'no' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_ceremony_attending = 'no' THEN 1 ELSE 0 END), 0)
                ) as ceremony_declined,
                (
                    COALESCE(SUM(CASE WHEN reception_attending = 'no' THEN 1 ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN has_plus_one = 1 AND plus_one_reception_attending = 'no' THEN 1 ELSE 0 END), 0)
                ) as reception_declined
            FROM guests
        ");
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error loading guests: ' . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Manage Guests - Jacob & Melissa";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css?v=<?php 
        $cssPath = __DIR__ . '/css/style.css';
        echo file_exists($cssPath) ? filemtime($cssPath) : time(); 
    ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400&family=Beloved+Script&family=Crimson+Text:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .back-to-site {
            text-align: center;
            margin-bottom: 2rem;
        }
        .back-to-site a {
            color: var(--color-green);
            text-decoration: none;
            font-size: 1.1rem;
            transition: color 0.3s;
        }
        .back-to-site a:hover {
            color: var(--color-gold);
            text-decoration: underline;
        }
        .logout-link {
            text-align: right;
            margin-bottom: 1rem;
        }
        .logout-link a {
            color: var(--color-dark);
            text-decoration: none;
        }
        .logout-link a:hover {
            color: var(--color-green);
        }
        .stats-bar {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            font-family: 'Crimson Text', serif;
        }
        .stat-attending .stat-number { color: var(--color-green); }
        .stat-declined .stat-number { color: #dc3545; }
        .stat-pending .stat-number { color: var(--color-lavender); }
        
        .filters-bar {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filters-bar input[type="text"],
        .filters-bar input[type="number"] {
            padding: 0.5rem 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
        }
        .filters-bar input[type="text"]:focus,
        .filters-bar input[type="number"]:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
        }
        .btn-filter {
            padding: 0.5rem 1rem;
            background: var(--color-green);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Cinzel', serif;
            transition: background 0.3s;
        }
        .btn-filter:hover { background: #6a7a54; }
        .btn-clear {
            padding: 0.5rem 1rem;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Cinzel', serif;
            transition: background 0.3s;
        }
        .btn-clear:hover { background: #5a6268; color: white; }
        
        .add-guest-form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .add-guest-form h2 {
            color: var(--color-green);
            margin-bottom: 1.5rem;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 150px;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .btn-secondary:hover { background-color: #5a6268; color: white; }
        
        .guests-table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .guests-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Crimson Text', serif;
        }
        .guests-table th {
            background: var(--color-green);
            color: white;
            padding: 0;
            text-align: left;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            font-weight: 400;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .guests-table th a {
            display: block;
            padding: 0.75rem 1rem;
            color: white;
            text-decoration: none;
            width: 100%;
            height: 100%;
        }
        .guests-table th a:hover {
            background: rgba(0,0,0,0.1);
        }
        .sort-indicator {
            font-size: 0.7rem;
            margin-left: 0.3rem;
            opacity: 0.7;
        }
        .guests-table td {
            padding: 0.6rem 1rem;
            border-bottom: 1px solid #eee;
            font-size: 1rem;
        }
        .guests-table tr:hover {
            background-color: #f8f9fa;
        }
        .guests-table tr.group-start {
            border-top: 2px solid var(--color-green);
        }
        .rsvp-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .rsvp-attending { background: #d4edda; color: #155724; }
        .rsvp-declined { background: #f8d7da; color: #721c24; }
        .rsvp-pending { background: #e2e3e5; color: #383d41; }
        
        .action-links {
            display: flex;
            gap: 0.5rem;
        }
        .action-links a {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: background 0.3s;
        }
        .action-links .edit-link {
            color: var(--color-green);
        }
        .action-links .edit-link:hover {
            background: rgba(127, 143, 101, 0.1);
        }
        .action-links .delete-link {
            color: #dc3545;
        }
        .action-links .delete-link:hover {
            background: rgba(220, 53, 69, 0.1);
        }
        
        .reimport-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #fff3cd;
            border-radius: 8px;
            border: 1px solid #ffc107;
        }
        .reimport-section h3 {
            margin-bottom: 0.5rem;
            color: #856404;
        }
        .reimport-section p {
            font-family: 'Crimson Text', serif;
            color: #856404;
            margin-bottom: 1rem;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-family: 'Cinzel', serif;
            transition: background 0.3s;
        }
        .btn-danger:hover { background: #c82333; color: white; }
        
        .guest-count-label {
            font-family: 'Crimson Text', serif;
            color: #666;
            margin-bottom: 1rem;
            display: block;
        }
        
        .action-links .rsvp-link {
            color: #0d6efd;
        }
        .action-links .rsvp-link:hover {
            background: rgba(13, 110, 253, 0.1);
        }
        
        .admin-rsvp-form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .admin-rsvp-form h2 {
            color: var(--color-green);
            margin-bottom: 0.5rem;
        }
        .admin-rsvp-desc {
            font-family: 'Crimson Text', serif;
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 1.05rem;
        }
        .admin-rsvp-group-members {
            margin-bottom: 1.5rem;
        }
        .admin-rsvp-member-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            background: white;
            transition: border-color 0.2s;
        }
        .admin-rsvp-member-card.ar-attending {
            border-color: var(--color-green);
            background: rgba(127, 143, 101, 0.03);
        }
        .admin-rsvp-member-card.ar-declined {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.02);
        }
        .ar-member-name {
            font-size: 1.15rem;
            color: var(--color-dark);
            display: block;
            margin-bottom: 0.6rem;
        }
        .ar-event-rows {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .ar-event-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .ar-event-label {
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            color: var(--color-dark);
        }
        .ar-event-sublabel {
            font-size: 0.85rem;
            color: #888;
        }
        .ar-plus-one-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .ar-attending-toggle {
            display: flex;
            gap: 0;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #ccc;
        }
        .ar-attending-toggle button {
            padding: 0.4rem 1rem;
            border: none;
            background: white;
            cursor: pointer;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            transition: all 0.2s;
            color: #666;
        }
        .ar-attending-toggle button:not(:last-child) {
            border-right: 1px solid #ccc;
        }
        .ar-attending-toggle button.ar-active-yes {
            background: var(--color-green);
            color: white;
        }
        .ar-attending-toggle button.ar-active-no {
            background: #dc3545;
            color: white;
        }
        .ar-member-dietary {
            margin-top: 0.5rem;
        }
        .ar-member-dietary label {
            font-family: 'Crimson Text', serif;
            font-size: 0.95rem;
            color: #666;
            display: block;
            margin-bottom: 0.25rem;
        }
        .ar-member-dietary input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .ar-member-dietary input:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
        }
        .ar-plus-one-card {
            border-style: dashed;
        }
        .ar-plus-one-label {
            font-style: italic;
            color: var(--color-dark);
            font-size: 0.9rem;
        }
        .ar-plus-one-details.ar-hidden {
            display: none;
        }
        .ar-plus-one-details {
            margin-top: 0.75rem;
        }
        .ar-plus-one-name-group {
            margin-bottom: 0.5rem;
        }
        .ar-plus-one-name-group label {
            font-family: 'Cinzel', serif;
            font-size: 0.95rem;
            color: var(--color-dark);
            display: block;
            margin-bottom: 0.35rem;
            font-weight: 600;
        }
        .ar-plus-one-name-group input {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 2px solid var(--color-green);
            border-radius: 6px;
            font-family: 'Crimson Text', serif;
            font-size: 1.15rem;
            color: var(--color-dark);
            background: rgba(127, 143, 101, 0.04);
            box-sizing: border-box;
        }
        .ar-plus-one-name-group input:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(127, 143, 101, 0.25);
        }
        .admin-rsvp-form .form-group textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: 'Crimson Text', serif;
            font-size: 1rem;
            min-height: 60px;
            box-sizing: border-box;
        }
        .admin-rsvp-form .form-group textarea:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
        }
        .admin-rsvp-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .admin-rsvp-error {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
            display: none;
        }
        .admin-rsvp-success {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            display: none;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <main class="page-container">
        <div class="back-to-site">
            <a href="/">← Back to Main Site</a>
        </div>
        
        <?php if (!$authenticated): ?>
            <div class="form-container">
                <h1 class="page-title">Manage Guests</h1>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                <form method="POST" action="/admin-guests">
                    <input type="hidden" name="admin_login" value="1">
                    <div class="form-group required">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-container">
                <div class="logout-link">
                    <a href="/admin-guests?logout=1">Logout</a>
                </div>
                <h1 class="page-title">Manage Guests</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>
                <?php if (isset($_GET['added'])): ?>
                    <div class="alert alert-success"><p>Guest added successfully!</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success"><p>Guest updated successfully!</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success"><p>Guest deleted.</p></div>
                <?php endif; ?>
                <?php if (isset($_GET['reimported'])): ?>
                    <div class="alert alert-success"><p>Re-imported <?php echo intval($_GET['reimported']); ?> guests from CSV.</p></div>
                <?php endif; ?>
                
                <?php if ($rsvpGuest): ?>
                <!-- Admin RSVP Entry Form -->
                <div class="admin-rsvp-form" id="admin-rsvp-form">
                    <h2>Enter RSVP</h2>
                    <p class="admin-rsvp-desc">Entering RSVP for <strong><?php echo htmlspecialchars($rsvpGuest['first_name'] . ' ' . $rsvpGuest['last_name']); ?></strong>'s group (from mail-in card). No email required.</p>
                    
                    <div class="admin-rsvp-group-members" id="ar-group-members">
                        <p style="color:#666; font-family:'Crimson Text',serif;">Loading group members...</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="ar-email">Email Address (Optional)</label>
                        <input type="email" id="ar-email" placeholder="Guest's email address, if provided...">
                    </div>
                    
                    <div class="form-group">
                        <label for="ar-message">Message (Optional)</label>
                        <textarea id="ar-message" placeholder="Any message from the RSVP card..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="ar-song">Song Request (Optional)</label>
                        <textarea id="ar-song" placeholder="Song request from the RSVP card..."></textarea>
                    </div>
                    
                    <div class="admin-rsvp-actions">
                        <button type="button" class="btn" id="ar-btn-submit">Submit RSVP</button>
                        <a href="/admin-guests" class="btn-secondary">Cancel</a>
                    </div>
                    
                    <div class="admin-rsvp-error" id="ar-error"></div>
                    <div class="admin-rsvp-success" id="ar-success"></div>
                </div>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const guestId = <?php echo (int)$rsvpGuest['id']; ?>;
                    const groupContainer = document.getElementById('ar-group-members');
                    const btnSubmit = document.getElementById('ar-btn-submit');
                    const errorDiv = document.getElementById('ar-error');
                    const successDiv = document.getElementById('ar-success');
                    let groupMembers = [];
                    
                    function escapeHtml(text) {
                        const div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    }
                    
                    async function loadGroup() {
                        try {
                            const resp = await fetch('/api/guest-group?guest_id=' + guestId);
                            const data = await resp.json();
                            
                            if (data.error) {
                                groupContainer.innerHTML = '<p style="color:#dc3545;">Error: ' + escapeHtml(data.error) + '</p>';
                                return;
                            }
                            
                            groupMembers = data.group_members;
                            renderGroupForm();
                        } catch (err) {
                            groupContainer.innerHTML = '<p style="color:#dc3545;">Failed to load group. Please try again.</p>';
                        }
                    }
                    
                    function arEventToggleHtml(btnClass, currentVal) {
                        return '<div class="ar-attending-toggle">'
                             + '<button type="button" class="' + btnClass + (currentVal === 'yes' ? ' ar-active-yes' : '') + '" data-value="yes">Attending</button>'
                             + '<button type="button" class="' + btnClass + (currentVal === 'no' ? ' ar-active-no' : '') + '" data-value="no">Not Attending</button>'
                             + '</div>';
                    }
                    
                    function renderGroupForm() {
                        var html = '';
                        groupMembers.forEach(function(member) {
                            var name = member.first_name + (member.last_name ? ' ' + member.last_name : '');
                            var curCeremony = member.ceremony_attending;
                            var curReception = member.reception_attending;
                            var curDietary = member.dietary || '';
                            
                            html += '<div class="admin-rsvp-member-card" data-member-id="' + member.id + '">'
                                 + '<span class="ar-member-name">' + escapeHtml(name) + '</span>'
                                 + '<div class="ar-event-rows">'
                                 + '<div class="ar-event-row">'
                                 + '<span class="ar-event-label">Ceremony <span class="ar-event-sublabel">(St. Agatha St. James)</span></span>'
                                 + arEventToggleHtml('ar-btn-ceremony', curCeremony)
                                 + '</div>'
                                 + '<div class="ar-event-row">'
                                 + '<span class="ar-event-label">Reception <span class="ar-event-sublabel">(Bala Golf Club)</span></span>'
                                 + arEventToggleHtml('ar-btn-reception', curReception)
                                 + '</div>'
                                 + '</div>'
                                 + '<div class="ar-member-dietary">'
                                 + '<label>Dietary restrictions or allergies</label>'
                                 + '<input type="text" data-dietary-for="' + member.id + '" placeholder="e.g., vegetarian, nut allergy..." value="' + escapeHtml(curDietary) + '">'
                                 + '</div>'
                                 + '</div>';
                            
                            if (parseInt(member.has_plus_one)) {
                                var poName = member.plus_one_name || '';
                                var poCeremony = member.plus_one_ceremony_attending;
                                var poReception = member.plus_one_reception_attending;
                                var poDietary = member.plus_one_dietary || '';
                                var notBringing = poCeremony === 'no' && poReception === 'no';
                                
                                html += '<div class="admin-rsvp-member-card ar-plus-one-card" data-plus-one-for="' + member.id + '">'
                                     + '<div class="ar-plus-one-header">'
                                     + '<span class="ar-member-name ar-plus-one-label">Guest of ' + escapeHtml(member.first_name) + '</span>'
                                     + '<div class="ar-attending-toggle">'
                                     + '<button type="button" class="ar-btn-po-toggle' + (!notBringing ? ' ar-active-yes' : '') + '" data-value="bringing">Bringing</button>'
                                     + '<button type="button" class="ar-btn-po-toggle' + (notBringing ? ' ar-active-no' : '') + '" data-value="not-bringing">Not Bringing</button>'
                                     + '</div>'
                                     + '</div>'
                                     + '<div class="ar-plus-one-details' + (notBringing ? ' ar-hidden' : '') + '">'
                                     + '<div class="ar-plus-one-name-group">'
                                     + '<label>Guest\'s Full Name</label>'
                                     + '<input type="text" data-po-name-for="' + member.id + '" placeholder="Enter guest\'s full name..." value="' + escapeHtml(poName) + '">'
                                     + '</div>'
                                     + '<div class="ar-event-rows">'
                                     + '<div class="ar-event-row">'
                                     + '<span class="ar-event-label">Ceremony <span class="ar-event-sublabel">(St. Agatha St. James)</span></span>'
                                     + arEventToggleHtml('ar-btn-po-ceremony', poCeremony)
                                     + '</div>'
                                     + '<div class="ar-event-row">'
                                     + '<span class="ar-event-label">Reception <span class="ar-event-sublabel">(Bala Golf Club)</span></span>'
                                     + arEventToggleHtml('ar-btn-po-reception', poReception)
                                     + '</div>'
                                     + '</div>'
                                     + '<div class="ar-member-dietary">'
                                     + '<label>Dietary restrictions or allergies</label>'
                                     + '<input type="text" data-po-dietary-for="' + member.id + '" placeholder="e.g., vegetarian, nut allergy..." value="' + escapeHtml(poDietary) + '">'
                                     + '</div>'
                                     + '</div>'
                                     + '</div>';
                            }
                        });
                        
                        groupContainer.innerHTML = html;
                        
                        for (var i = 0; i < groupMembers.length; i++) {
                            if (groupMembers[i].email) {
                                document.getElementById('ar-email').value = groupMembers[i].email;
                                break;
                            }
                        }
                        for (var i = 0; i < groupMembers.length; i++) {
                            if (groupMembers[i].message) {
                                document.getElementById('ar-message').value = groupMembers[i].message;
                                break;
                            }
                        }
                        for (var i = 0; i < groupMembers.length; i++) {
                            if (groupMembers[i].song_request) {
                                document.getElementById('ar-song').value = groupMembers[i].song_request;
                                break;
                            }
                        }
                        
                        attachToggleHandlers();
                    }
                    
                    function attachToggleHandlers() {
                        groupContainer.querySelectorAll('.ar-btn-ceremony, .ar-btn-reception, .ar-btn-po-ceremony, .ar-btn-po-reception').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                var toggle = this.closest('.ar-attending-toggle');
                                toggle.querySelectorAll('button').forEach(function(b) {
                                    b.classList.remove('ar-active-yes', 'ar-active-no');
                                });
                                this.classList.add(this.dataset.value === 'yes' ? 'ar-active-yes' : 'ar-active-no');
                                var card = this.closest('.admin-rsvp-member-card');
                                var anyYes = card.querySelectorAll('.ar-active-yes').length > 0;
                                card.classList.toggle('ar-attending', anyYes);
                                card.classList.toggle('ar-declined', !anyYes && card.querySelectorAll('.ar-active-no').length > 0);
                            });
                        });
                        
                        groupContainer.querySelectorAll('.ar-btn-po-toggle').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                var card = this.closest('.ar-plus-one-card');
                                var toggle = this.closest('.ar-attending-toggle');
                                var details = card.querySelector('.ar-plus-one-details');
                                toggle.querySelectorAll('button').forEach(function(b) {
                                    b.classList.remove('ar-active-yes', 'ar-active-no');
                                });
                                if (this.dataset.value === 'bringing') {
                                    this.classList.add('ar-active-yes');
                                    if (details) details.classList.remove('ar-hidden');
                                    card.querySelectorAll('.ar-btn-po-ceremony.ar-active-no, .ar-btn-po-reception.ar-active-no').forEach(function(b) {
                                        b.classList.remove('ar-active-no');
                                    });
                                } else {
                                    this.classList.add('ar-active-no');
                                    if (details) details.classList.add('ar-hidden');
                                }
                            });
                        });
                    }
                    
                    async function submitAdminRsvp() {
                        errorDiv.style.display = 'none';
                        successDiv.style.display = 'none';
                        
                        var email = document.getElementById('ar-email').value.trim();
                        var message = document.getElementById('ar-message').value.trim();
                        var songRequest = document.getElementById('ar-song').value.trim();
                        
                        var guestData = [];
                        var hasResponse = false;
                        
                        groupContainer.querySelectorAll('.admin-rsvp-member-card:not(.ar-plus-one-card)').forEach(function(card) {
                            var memberId = parseInt(card.dataset.memberId);
                            var ceremonyBtn = card.querySelector('.ar-btn-ceremony.ar-active-yes, .ar-btn-ceremony.ar-active-no');
                            var receptionBtn = card.querySelector('.ar-btn-reception.ar-active-yes, .ar-btn-reception.ar-active-no');
                            var ceremonyAttending = ceremonyBtn ? ceremonyBtn.dataset.value : '';
                            var receptionAttending = receptionBtn ? receptionBtn.dataset.value : '';
                            var dietaryInput = card.querySelector('[data-dietary-for="' + memberId + '"]');
                            var dietary = dietaryInput ? dietaryInput.value.trim() : '';
                            
                            if (ceremonyAttending || receptionAttending) hasResponse = true;
                            
                            var entry = {
                                id: memberId,
                                ceremony_attending: ceremonyAttending,
                                reception_attending: receptionAttending,
                                dietary: dietary
                            };
                            
                            var poCard = groupContainer.querySelector('.ar-plus-one-card[data-plus-one-for="' + memberId + '"]');
                            if (poCard) {
                                var poToggleBtn = poCard.querySelector('.ar-btn-po-toggle.ar-active-no');
                                var notBringing = !!poToggleBtn;
                                var poCeremonyBtn = poCard.querySelector('.ar-btn-po-ceremony.ar-active-yes, .ar-btn-po-ceremony.ar-active-no');
                                var poReceptionBtn = poCard.querySelector('.ar-btn-po-reception.ar-active-yes, .ar-btn-po-reception.ar-active-no');
                                var poNameInput = poCard.querySelector('[data-po-name-for="' + memberId + '"]');
                                var poDietaryInput = poCard.querySelector('[data-po-dietary-for="' + memberId + '"]');
                                
                                entry.plus_one_ceremony_attending = notBringing ? 'no' : (poCeremonyBtn ? poCeremonyBtn.dataset.value : '');
                                entry.plus_one_reception_attending = notBringing ? 'no' : (poReceptionBtn ? poReceptionBtn.dataset.value : '');
                                entry.plus_one_name = poNameInput ? poNameInput.value.trim() : '';
                                entry.plus_one_dietary = poDietaryInput ? poDietaryInput.value.trim() : '';
                            }
                            
                            guestData.push(entry);
                        });
                        
                        if (!hasResponse) {
                            errorDiv.textContent = 'Please indicate ceremony or reception attendance for at least one guest.';
                            errorDiv.style.display = 'block';
                            return;
                        }
                        
                        btnSubmit.classList.add('loading');
                        btnSubmit.textContent = 'Submitting';
                        
                        try {
                            var resp = await fetch('/api/admin-submit-rsvp', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    guests: guestData,
                                    email: email,
                                    message: message,
                                    song_request: songRequest
                                })
                            });
                            
                            var data = await resp.json();
                            
                            if (data.success) {
                                successDiv.textContent = 'RSVP entered successfully! Attending: ' + data.attending_count + ', Declined: ' + data.declining_count;
                                successDiv.style.display = 'block';
                                btnSubmit.style.display = 'none';
                            } else {
                                errorDiv.textContent = data.error || 'An error occurred.';
                                errorDiv.style.display = 'block';
                            }
                        } catch (err) {
                            errorDiv.textContent = 'Network error. Please try again.';
                            errorDiv.style.display = 'block';
                        }
                        
                        btnSubmit.classList.remove('loading');
                        btnSubmit.textContent = 'Submit RSVP';
                    }
                    
                    btnSubmit.addEventListener('click', submitAdminRsvp);
                    loadGroup();
                });
                </script>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-bar">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total']; ?></span>
                        <span class="stat-label">Total Invites (incl. +1s)</span>
                    </div>
                    <div class="stat-item stat-attending">
                        <span class="stat-number"><?php echo $stats['attending']; ?></span>
                        <span class="stat-label">Attending Any</span>
                    </div>
                    <div class="stat-item stat-declined">
                        <span class="stat-number"><?php echo $stats['declined']; ?></span>
                        <span class="stat-label">Declined All</span>
                    </div>
                    <div class="stat-item stat-pending">
                        <span class="stat-number"><?php echo $stats['pending']; ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total'] - $stats['declined']; ?></span>
                        <span class="stat-label">Max if Pending Say Yes</span>
                    </div>
                </div>
                <div class="stats-bar" style="margin-top: -1rem;">
                    <div class="stat-item">
                        <span class="stat-number" style="color: var(--color-green);"><?php echo $stats['ceremony']; ?></span>
                        <span class="stat-label">Ceremony</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" style="color: #dc3545;"><?php echo $stats['ceremony_declined']; ?></span>
                        <span class="stat-label">Declined Ceremony</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" style="color: var(--color-green);"><?php echo $stats['reception']; ?></span>
                        <span class="stat-label">Reception</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" style="color: #dc3545;"><?php echo $stats['reception_declined']; ?></span>
                        <span class="stat-label">Declined Reception</span>
                    </div>
                </div>
                
                <!-- Add/Edit Guest Form -->
                <div class="add-guest-form" <?php if ($rsvpGuest): ?>style="display:none;"<?php endif; ?>>
                    <h2><?php echo $editGuest ? 'Edit Guest' : 'Add Guest'; ?></h2>
                    <form method="POST" action="/admin-guests">
                        <?php if ($editGuest): ?>
                            <input type="hidden" name="update_guest" value="1">
                            <input type="hidden" name="guest_id" value="<?php echo htmlspecialchars($editGuest['id']); ?>">
                        <?php else: ?>
                            <input type="hidden" name="add_guest" value="1">
                        <?php endif; ?>
                        <div class="form-row">
                            <div class="form-group required">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" required 
                                       value="<?php echo htmlspecialchars($editGuest['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($editGuest['last_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="group_name">Group Name</label>
                                <input type="text" id="group_name" name="group_name"
                                       value="<?php echo htmlspecialchars($editGuest['group_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="mailing_group">Mailing Group #</label>
                                <input type="number" id="mailing_group" name="mailing_group" min="0"
                                       value="<?php echo htmlspecialchars($editGuest['mailing_group'] ?? ''); ?>">
                            </div>
                            <div class="form-group" style="display:flex; align-items:center; gap:0.5rem; min-width:120px; padding-top:1.8rem;">
                                <input type="checkbox" id="has_plus_one" name="has_plus_one" value="1"
                                       <?php echo (!empty($editGuest['has_plus_one'])) ? 'checked' : ''; ?>
                                       style="width:auto; margin:0;">
                                <label for="has_plus_one" style="margin:0; cursor:pointer;">Plus One</label>
                            </div>
                            <?php if ($editGuest): ?>
                            <div class="form-group">
                                <label for="ceremony_attending">Ceremony</label>
                                <select id="ceremony_attending" name="ceremony_attending">
                                    <option value="" <?php echo ($editGuest['ceremony_attending'] === null) ? 'selected' : ''; ?>>Pending</option>
                                    <option value="yes" <?php echo ($editGuest['ceremony_attending'] === 'yes') ? 'selected' : ''; ?>>Attending</option>
                                    <option value="no" <?php echo ($editGuest['ceremony_attending'] === 'no') ? 'selected' : ''; ?>>Declined</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="reception_attending">Reception</label>
                                <select id="reception_attending" name="reception_attending">
                                    <option value="" <?php echo ($editGuest['reception_attending'] === null) ? 'selected' : ''; ?>>Pending</option>
                                    <option value="yes" <?php echo ($editGuest['reception_attending'] === 'yes') ? 'selected' : ''; ?>>Attending</option>
                                    <option value="no" <?php echo ($editGuest['reception_attending'] === 'no') ? 'selected' : ''; ?>>Declined</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn"><?php echo $editGuest ? 'Update Guest' : 'Add Guest'; ?></button>
                            <?php if ($editGuest): ?>
                                <a href="/admin-guests" class="btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Search/Filter -->
                <form method="GET" action="/admin-guests" class="filters-bar">
                    <input type="text" name="search" id="guest-search-input" placeholder="Search name..."
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <input type="number" name="group_filter" placeholder="Group #" min="0" style="width: 100px;"
                           value="<?php echo htmlspecialchars($_GET['group_filter'] ?? ''); ?>">
                    <button type="submit" class="btn-filter">Search</button>
                    <a href="/admin-guests" class="btn-clear">Clear</a>
                </form>
                
                <!-- Dietary Restrictions View -->
                <button type="button" id="dietary-toggle" class="btn-filter" style="margin-bottom: 1rem;">View Dietary Restrictions</button>
                <div id="dietary-panel" style="display: none; margin-bottom: 1.5rem; background: #f9f9f6; border: 1px solid #ddd; border-radius: 8px; padding: 1rem;">
                    <h3 style="margin: 0 0 0.75rem;">Dietary Restrictions</h3>
                    <?php
                    $dietaryEntries = [];
                    foreach ($guests as $g) {
                        if (!empty(trim($g['dietary'] ?? ''))) {
                            $dietaryEntries[] = [
                                'name' => htmlspecialchars($g['first_name'] . ' ' . $g['last_name']),
                                'dietary' => htmlspecialchars($g['dietary']),
                            ];
                        }
                        if ($g['has_plus_one'] && !empty(trim($g['plus_one_dietary'] ?? ''))) {
                            $poName = !empty(trim($g['plus_one_name'] ?? ''))
                                ? htmlspecialchars($g['plus_one_name'])
                                : htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) . "'s +1";
                            $dietaryEntries[] = [
                                'name' => $poName,
                                'dietary' => htmlspecialchars($g['plus_one_dietary']),
                            ];
                        }
                    }
                    if (empty($dietaryEntries)): ?>
                        <p style="color: #666;">No dietary restrictions have been submitted.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid #ccc;">Guest</th>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid #ccc;">Dietary Restriction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dietaryEntries as $entry): ?>
                                    <tr>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid #eee;"><?php echo $entry['name']; ?></td>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid #eee;"><?php echo $entry['dietary']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <script>
                document.getElementById('dietary-toggle').addEventListener('click', function() {
                    var panel = document.getElementById('dietary-panel');
                    var visible = panel.style.display !== 'none';
                    panel.style.display = visible ? 'none' : 'block';
                    this.textContent = visible ? 'View Dietary Restrictions' : 'Hide Dietary Restrictions';
                });
                </script>

                <!-- Song Requests View -->
                <button type="button" id="songs-toggle" class="btn-filter" style="margin-bottom: 1rem;">View Song Requests</button>
                <div id="songs-panel" style="display: none; margin-bottom: 1.5rem; background: #f9f9f6; border: 1px solid #ddd; border-radius: 8px; padding: 1rem;">
                    <h3 style="margin: 0 0 0.75rem;">Song Requests</h3>
                    <?php
                    $songEntries = [];
                    $seenGroups = [];
                    foreach ($guests as $g) {
                        if (!empty(trim($g['song_request'] ?? ''))) {
                            $groupKey = $g['mailing_group'] ?? $g['id'];
                            if (isset($seenGroups[$groupKey])) continue;
                            $seenGroups[$groupKey] = true;
                            $songEntries[] = [
                                'name' => !empty($g['group_name']) ? htmlspecialchars($g['group_name']) : htmlspecialchars($g['first_name'] . ' ' . $g['last_name']),
                                'song' => htmlspecialchars($g['song_request']),
                            ];
                        }
                    }
                    if (empty($songEntries)): ?>
                        <p style="color: #666;">No song requests have been submitted.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid #ccc;">Guest</th>
                                    <th style="text-align: left; padding: 0.5rem; border-bottom: 2px solid #ccc;">Song Request</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($songEntries as $entry): ?>
                                    <tr>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid #eee;"><?php echo $entry['name']; ?></td>
                                        <td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid #eee;"><?php echo $entry['song']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <script>
                document.getElementById('songs-toggle').addEventListener('click', function() {
                    var panel = document.getElementById('songs-panel');
                    var visible = panel.style.display !== 'none';
                    panel.style.display = visible ? 'none' : 'block';
                    this.textContent = visible ? 'View Song Requests' : 'Hide Song Requests';
                });
                </script>

                <!-- Guests Table -->
                <span class="guest-count-label">Showing <?php echo count($guests); ?> guest<?php echo count($guests) !== 1 ? 's' : ''; ?></span>
                <?php
                function getSortUrl($field, $currentSort, $currentOrder) {
                    $params = $_GET;
                    if ($currentSort === $field) {
                        $params['order'] = ($currentOrder === 'ASC') ? 'DESC' : 'ASC';
                    } else {
                        $params['sort'] = $field;
                        $params['order'] = 'ASC';
                    }
                    return '/admin-guests?' . http_build_query($params);
                }
                function getSortIndicator($field, $currentSort, $currentOrder) {
                    if ($currentSort !== $field) return '';
                    return $currentOrder === 'ASC' ? ' <span class="sort-indicator">▲</span>' : ' <span class="sort-indicator">▼</span>';
                }
                ?>
                <div class="guests-table-container">
                    <table class="guests-table">
                        <thead>
                            <tr>
                                <th><a href="<?php echo getSortUrl('first_name', $sort, $order); ?>">First Name<?php echo getSortIndicator('first_name', $sort, $order); ?></a></th>
                                <th><a href="<?php echo getSortUrl('last_name', $sort, $order); ?>">Last Name<?php echo getSortIndicator('last_name', $sort, $order); ?></a></th>
                                <th><a href="<?php echo getSortUrl('mailing_group', $sort, $order); ?>">Group #<?php echo getSortIndicator('mailing_group', $sort, $order); ?></a></th>
                                <th><a href="<?php echo getSortUrl('group_name', $sort, $order); ?>">Group Name<?php echo getSortIndicator('group_name', $sort, $order); ?></a></th>
                                <th>Address</th>
                                <th><a href="<?php echo getSortUrl('has_plus_one', $sort, $order); ?>">+1<?php echo getSortIndicator('has_plus_one', $sort, $order); ?></a></th>
                                <th><a href="<?php echo getSortUrl('attending', $sort, $order); ?>">RSVP Status<?php echo getSortIndicator('attending', $sort, $order); ?></a></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $lastGroup = -999;
                            foreach ($guests as $guest): 
                                $isGroupStart = ($guest['mailing_group'] !== null && $guest['mailing_group'] != $lastGroup);
                                $lastGroup = $guest['mailing_group'];
                            ?>
                                <tr class="<?php echo $isGroupStart ? 'group-start' : ''; ?>">
                                    <td><?php echo htmlspecialchars($guest['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($guest['last_name']); ?></td>
                                    <td><?php echo $guest['mailing_group'] !== null ? htmlspecialchars($guest['mailing_group']) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($guest['group_name']); ?></td>
                                    <td><?php
                                        $addrParts = array_filter([
                                            $guest['address_1'] ?? '',
                                            $guest['address_2'] ?? '',
                                        ]);
                                        $cityState = array_filter([
                                            $guest['city'] ?? '',
                                            $guest['state'] ?? '',
                                        ]);
                                        $addrLine1 = implode(', ', $addrParts);
                                        $addrLine2 = implode(', ', $cityState);
                                        if ($guest['zip'] ?? '') $addrLine2 .= ' ' . $guest['zip'];
                                        if ($guest['country'] ?? '') $addrLine2 .= ', ' . $guest['country'];
                                        $addrLine2 = trim($addrLine2, ', ');
                                        $fullAddr = implode('<br>', array_filter([$addrLine1, $addrLine2]));
                                        echo $fullAddr ?: '—';
                                    ?></td>
                                    <td><?php echo $guest['has_plus_one'] ? '✓' : ''; ?></td>
                                    <td>
                                        <?php if ($guest['attending'] === 'yes'): ?>
                                            <span class="rsvp-badge rsvp-attending">Attending</span>
                                        <?php elseif ($guest['attending'] === 'no'): ?>
                                            <span class="rsvp-badge rsvp-declined">Declined</span>
                                        <?php else: ?>
                                            <span class="rsvp-badge rsvp-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-links">
                                            <a href="/admin-guests?rsvp=<?php echo $guest['id']; ?>" class="rsvp-link">RSVP</a>
                                            <a href="/admin-guests?edit=<?php echo $guest['id']; ?>" class="edit-link">Edit</a>
                                            <a href="/admin-guests?delete=<?php echo $guest['id']; ?>" class="delete-link" 
                                               onclick="return confirm('Delete <?php echo htmlspecialchars(addslashes($guest['first_name'] . ' ' . $guest['last_name'])); ?>?');">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($guests)): ?>
                                <tr><td colspan="8" style="text-align:center; padding:2rem; color:#666;">No guests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Re-import Section -->
                <div class="reimport-section">
                    <h3>Re-import from CSV</h3>
                    <p>This will delete all current guest records and re-import from the original CSV file. All RSVP data will be lost.</p>
                    <a href="/admin-guests?reimport=confirm" class="btn-danger" 
                       onclick="return confirm('WARNING: This will delete ALL guest records including RSVP data and re-import from the CSV. Continue?');">
                        Re-import from CSV
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>
<script>
(function() {
    var searchInput = document.getElementById('guest-search-input');
    if (!searchInput) return;
    var table = document.querySelector('.guests-table');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    var countLabel = document.querySelector('.guest-count-label');

    searchInput.addEventListener('input', function() {
        var query = searchInput.value.toLowerCase().trim();
        var visible = 0;
        rows.forEach(function(row) {
            if (row.querySelector('td[colspan]')) {
                // "No guests found" row — hide during filtering
                row.style.display = query ? 'none' : '';
                return;
            }
            var cells = row.querySelectorAll('td');
            var firstName = cells[0] ? cells[0].textContent.toLowerCase() : '';
            var lastName = cells[1] ? cells[1].textContent.toLowerCase() : '';
            var match = !query || firstName.indexOf(query) !== -1 || lastName.indexOf(query) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (countLabel) {
            countLabel.textContent = 'Showing ' + visible + ' guest' + (visible !== 1 ? 's' : '');
        }
    });
})();
</script>
</body>
</html>
