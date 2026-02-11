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
            INSERT INTO guests (first_name, last_name, group_name, mailing_group)
            VALUES (?, ?, ?, ?)
        ");
        $mailingGroup = trim($_POST['mailing_group'] ?? '');
        $stmt->execute([
            trim($_POST['first_name'] ?? ''),
            trim($_POST['last_name'] ?? ''),
            trim($_POST['group_name'] ?? ''),
            $mailingGroup !== '' ? (int)$mailingGroup : null,
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
        $attending = $_POST['attending'] ?? '';
        $stmt = $pdo->prepare("
            UPDATE guests 
            SET first_name = ?, last_name = ?, group_name = ?, mailing_group = ?, attending = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($_POST['first_name'] ?? ''),
            trim($_POST['last_name'] ?? ''),
            trim($_POST['group_name'] ?? ''),
            $mailingGroup !== '' ? (int)$mailingGroup : null,
            $attending !== '' ? $attending : null,
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

// Fetch all guests if authenticated
$guests = [];
$stats = ['total' => 0, 'attending' => 0, 'declined' => 0, 'pending' => 0];
if ($authenticated) {
    try {
        $pdo = getDbConnection();
        
        // Apply search filter if present
        $search = trim($_GET['search'] ?? '');
        $groupFilter = trim($_GET['group_filter'] ?? '');
        
        $where = [];
        $params = [];
        
        if ($search !== '') {
            $where[] = "(first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($groupFilter !== '') {
            $where[] = "mailing_group = ?";
            $params[] = (int)$groupFilter;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $pdo->prepare("
            SELECT * FROM guests $whereClause
            ORDER BY mailing_group ASC, id ASC
        ");
        $stmt->execute($params);
        $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Stats (always from full table)
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN attending = 'yes' THEN 1 ELSE 0 END) as attending,
                SUM(CASE WHEN attending = 'no' THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN attending IS NULL THEN 1 ELSE 0 END) as pending
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
            overflow-x: auto;
        }
        .guests-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Crimson Text', serif;
        }
        .guests-table th {
            background: var(--color-green);
            color: white;
            padding: 0.75rem 1rem;
            text-align: left;
            font-family: 'Cinzel', serif;
            font-size: 0.85rem;
            font-weight: 400;
            white-space: nowrap;
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
                
                <!-- Stats -->
                <div class="stats-bar">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total']; ?></span>
                        <span class="stat-label">Total Guests</span>
                    </div>
                    <div class="stat-item stat-attending">
                        <span class="stat-number"><?php echo $stats['attending']; ?></span>
                        <span class="stat-label">Attending</span>
                    </div>
                    <div class="stat-item stat-declined">
                        <span class="stat-number"><?php echo $stats['declined']; ?></span>
                        <span class="stat-label">Declined</span>
                    </div>
                    <div class="stat-item stat-pending">
                        <span class="stat-number"><?php echo $stats['pending']; ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
                
                <!-- Add/Edit Guest Form -->
                <div class="add-guest-form">
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
                            <?php if ($editGuest): ?>
                            <div class="form-group">
                                <label for="attending">RSVP Status</label>
                                <select id="attending" name="attending">
                                    <option value="" <?php echo ($editGuest['attending'] === null) ? 'selected' : ''; ?>>Pending</option>
                                    <option value="yes" <?php echo ($editGuest['attending'] === 'yes') ? 'selected' : ''; ?>>Attending</option>
                                    <option value="no" <?php echo ($editGuest['attending'] === 'no') ? 'selected' : ''; ?>>Declined</option>
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
                    <input type="text" name="search" placeholder="Search name..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <input type="number" name="group_filter" placeholder="Group #" min="0" style="width: 100px;"
                           value="<?php echo htmlspecialchars($_GET['group_filter'] ?? ''); ?>">
                    <button type="submit" class="btn-filter">Search</button>
                    <a href="/admin-guests" class="btn-clear">Clear</a>
                </form>
                
                <!-- Guests Table -->
                <span class="guest-count-label">Showing <?php echo count($guests); ?> guest<?php echo count($guests) !== 1 ? 's' : ''; ?></span>
                <div class="guests-table-container">
                    <table class="guests-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Group #</th>
                                <th>Group Name</th>
                                <th>RSVP Status</th>
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
                                            <a href="/admin-guests?edit=<?php echo $guest['id']; ?>" class="edit-link">Edit</a>
                                            <a href="/admin-guests?delete=<?php echo $guest['id']; ?>" class="delete-link" 
                                               onclick="return confirm('Delete <?php echo htmlspecialchars(addslashes($guest['first_name'] . ' ' . $guest['last_name'])); ?>?');">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($guests)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:2rem; color:#666;">No guests found.</td></tr>
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
</body>
</html>
