<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/admin_auth.php';

session_start();

$error = '';
$authenticated = false;

// Check unified admin auth first
if (isAdminAuthenticated()) {
    $authenticated = true;
} else {
    if (isset($_SESSION['rsvp_check_authenticated']) && $_SESSION['rsvp_check_authenticated'] === true) {
        $authenticated = true;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = trim($_POST['password'] ?? '');
    
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
    if ($password === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $authenticated = true;
    } else {
        $correctPassword = $_ENV['RSVP_CHECK_PASSWORD'] ?? '';
        if ($password === $correctPassword) {
            $_SESSION['rsvp_check_authenticated'] = true;
            $authenticated = true;
        } else {
            $error = 'Incorrect password. Please try again.';
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /check-rsvps');
    exit;
}

// Fetch guest RSVPs if authenticated
$guestRsvps = [];
$guestStats = ['total' => 0, 'attending' => 0, 'declined' => 0, 'pending' => 0];
if ($authenticated) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, mailing_group, group_name, attending, dietary, song_request, message, email, rsvp_submitted_at,
                   has_plus_one, plus_one_name, plus_one_attending, plus_one_dietary
            FROM guests
            WHERE rsvp_submitted_at IS NOT NULL
            ORDER BY rsvp_submitted_at DESC
        ");
        $guestRsvps = $stmt->fetchAll();
        
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN attending = 'yes' THEN 1 ELSE 0 END) as attending,
                SUM(CASE WHEN attending = 'no' THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN attending IS NULL THEN 1 ELSE 0 END) as pending
            FROM guests
        ");
        $guestStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error loading RSVPs: ' . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Check RSVPs - Jacob & Melissa";
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
        .rsvp-table-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .rsvp-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .rsvp-table th,
        .rsvp-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .rsvp-table th {
            background-color: var(--color-green);
            color: white;
            font-weight: bold;
        }
        .rsvp-table tr:hover {
            background-color: #f5f5f5;
        }
        .rsvp-table .attending-yes {
            color: #2d5016;
            font-weight: bold;
        }
        .rsvp-table .attending-no {
            color: #8b0000;
            font-weight: bold;
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
        .stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background-color: var(--color-light);
            border-radius: 8px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--color-green);
        }
        .stat-label {
            font-size: 0.9rem;
            color: var(--color-dark);
        }
        .plus-one-row td {
            padding-left: 2rem;
            color: #666;
            font-style: italic;
        }
        .manage-link {
            font-family: 'Crimson Text', serif;
            color: #666;
            margin-bottom: 1.5rem;
        }
        .manage-link a {
            color: var(--color-green);
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
                <h1 class="page-title">Check RSVPs</h1>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                <form method="POST" action="/check-rsvps">
                    <div class="form-group required">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="rsvp-table-container">
                <div class="logout-link">
                    <a href="/check-rsvps?logout=1">Logout</a>
                </div>
                <h1 class="page-title">RSVPs</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <p class="manage-link">
                    <a href="/admin-guests">Manage all guests →</a>
                </p>
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $guestStats['total']; ?></div>
                        <div class="stat-label">Total Guests</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: #2d5016;"><?php echo $guestStats['attending']; ?></div>
                        <div class="stat-label">Attending</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: #8b0000;"><?php echo $guestStats['declined']; ?></div>
                        <div class="stat-label">Declined</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: var(--color-lavender);"><?php echo $guestStats['pending']; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                
                <?php if (!empty($guestRsvps)): ?>
                    <table class="rsvp-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Group</th>
                                <th>Attending</th>
                                <th>Dietary</th>
                                <th>Song Request</th>
                                <th>Message</th>
                                <th>Email</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guestRsvps as $gr): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($gr['first_name'] . ' ' . $gr['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($gr['group_name']); ?></td>
                                    <td class="<?php echo $gr['attending'] === 'yes' ? 'attending-yes' : 'attending-no'; ?>">
                                        <?php echo $gr['attending'] === 'yes' ? 'Yes' : 'No'; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($gr['dietary'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($gr['song_request'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($gr['message'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($gr['email'] ?? ''); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($gr['rsvp_submitted_at'])); ?></td>
                                </tr>
                                <?php if ($gr['has_plus_one'] && $gr['plus_one_attending']): ?>
                                <tr class="plus-one-row">
                                    <td><?php echo htmlspecialchars($gr['plus_one_name'] ?? 'Guest of ' . $gr['first_name']); ?> (plus one)</td>
                                    <td></td>
                                    <td class="<?php echo $gr['plus_one_attending'] === 'yes' ? 'attending-yes' : 'attending-no'; ?>">
                                        <?php echo $gr['plus_one_attending'] === 'yes' ? 'Yes' : 'No'; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($gr['plus_one_dietary'] ?? ''); ?></td>
                                    <td colspan="4"></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666; font-family: 'Crimson Text', serif;">No RSVPs yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
