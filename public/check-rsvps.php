<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';

session_start();

$error = '';
$authenticated = false;

// Check if already authenticated
if (isset($_SESSION['rsvp_check_authenticated']) && $_SESSION['rsvp_check_authenticated'] === true) {
    $authenticated = true;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = trim($_POST['password'] ?? '');
    $correctPassword = $_ENV['RSVP_CHECK_PASSWORD'] ?? '';
    
    if ($password === $correctPassword) {
        $_SESSION['rsvp_check_authenticated'] = true;
        $authenticated = true;
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /check-rsvps');
    exit;
}

// If authenticated, fetch RSVPs
$rsvps = [];
if ($authenticated) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT id, name, email, attending, guests, dietary, message, created_at
            FROM rsvps
            ORDER BY created_at DESC
        ");
        $rsvps = $stmt->fetchAll();
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
    <link rel="stylesheet" href="/css/style.css?v=<?php echo filemtime(__DIR__ . '/../css/style.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400&family=Beloved+Script&display=swap" rel="stylesheet">
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
    </style>
</head>
<body>
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
                
                <?php if (empty($rsvps)): ?>
                    <p>No RSVPs found.</p>
                <?php else: ?>
                    <?php
                    $total = count($rsvps);
                    $attending = count(array_filter($rsvps, fn($r) => $r['attending'] === 'Yes'));
                    $notAttending = $total - $attending;
                    $totalGuests = array_sum(array_column($rsvps, 'guests'));
                    ?>
                    <div class="stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $total; ?></div>
                            <div class="stat-label">Total RSVPs</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $attending; ?></div>
                            <div class="stat-label">Attending</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $notAttending; ?></div>
                            <div class="stat-label">Not Attending</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $totalGuests; ?></div>
                            <div class="stat-label">Total Guests</div>
                        </div>
                    </div>
                    
                    <table class="rsvp-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Attending</th>
                                <th>Guests</th>
                                <th>Dietary Restrictions</th>
                                <th>Message</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rsvps as $rsvp): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rsvp['id']); ?></td>
                                    <td><?php echo htmlspecialchars($rsvp['name']); ?></td>
                                    <td><?php echo htmlspecialchars($rsvp['email']); ?></td>
                                    <td class="<?php echo $rsvp['attending'] === 'Yes' ? 'attending-yes' : 'attending-no'; ?>">
                                        <?php echo htmlspecialchars($rsvp['attending']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($rsvp['guests']); ?></td>
                                    <td><?php echo htmlspecialchars($rsvp['dietary'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($rsvp['message'] ?? ''); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($rsvp['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

