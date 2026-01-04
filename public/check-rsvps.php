<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/admin_auth.php';

session_start();

$error = '';
$success = '';
$authenticated = false;
$editRsvp = null;

// Check unified admin auth first
if (isAdminAuthenticated()) {
    $authenticated = true;
} else {
    // Fallback to old auth system for backward compatibility
    if (isset($_SESSION['rsvp_check_authenticated']) && $_SESSION['rsvp_check_authenticated'] === true) {
        $authenticated = true;
    }
}

// Handle login - try unified admin auth first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = trim($_POST['password'] ?? '');
    
    // Try unified admin password first
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
    if ($password === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $authenticated = true;
    } else {
        // Fallback to old password
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

// Handle delete RSVP
if ($authenticated && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM rsvps WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = 'RSVP deleted successfully.';
        header('Location: /check-rsvps?success=' . urlencode($success));
        exit;
    } catch (Exception $e) {
        $error = 'Error deleting RSVP: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle edit RSVP - fetch RSVP data
if ($authenticated && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM rsvps WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editRsvp = $stmt->fetch();
        if (!$editRsvp) {
            $error = 'RSVP not found.';
            $editRsvp = null;
        }
    } catch (Exception $e) {
        $error = 'Error loading RSVP: ' . htmlspecialchars($e->getMessage());
        $editRsvp = null;
    }
}

// Handle update RSVP
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rsvp'])) {
    try {
        $id = intval($_POST['rsvp_id']);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $attending = $_POST['attending'] ?? '';
        $guests = intval($_POST['guests'] ?? 1);
        $dietary = trim($_POST['dietary'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        // Collect guest names
        $guestNames = [];
        if ($guests > 1) {
            for ($i = 1; $i < $guests; $i++) {
                $guestName = trim($_POST['guest_name_' . $i] ?? '');
                if (!empty($guestName)) {
                    $guestNames[] = $guestName;
                }
            }
        }
        $guestNamesJson = !empty($guestNames) ? json_encode($guestNames) : null;
        
        if (empty($name) || empty($email) || empty($attending)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                UPDATE rsvps 
                SET name = ?, email = ?, attending = ?, guests = ?, guest_names = ?, dietary = ?, message = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                $email,
                $attending,
                $guests,
                $guestNamesJson,
                !empty($dietary) ? $dietary : null,
                !empty($message) ? $message : null,
                $id
            ]);
            $success = 'RSVP updated successfully.';
            header('Location: /check-rsvps?success=' . urlencode($success));
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error updating RSVP: ' . htmlspecialchars($e->getMessage());
    }
}

// If authenticated, fetch RSVPs
$rsvps = [];
if ($authenticated) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT id, name, email, attending, guests, guest_names, dietary, message, created_at
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
        .actions-cell {
            white-space: nowrap;
        }
        .btn-edit, .btn-delete {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            margin: 0 0.2rem;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        .btn-edit {
            background-color: var(--color-green);
            color: white;
        }
        .btn-edit:hover {
            background-color: var(--color-gold);
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
            color: white;
        }
        .edit-form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        .btn-cancel:hover {
            background-color: #5a6268;
            color: white;
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
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <p><?php echo htmlspecialchars($_GET['success']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <p><?php echo htmlspecialchars($success); ?></p>
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
                                <th>Guest Names</th>
                                <th>Dietary Restrictions</th>
                                <th>Message</th>
                                <th>Submitted</th>
                                <th>Actions</th>
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
                                    <td>
                                        <?php
                                        if (!empty($rsvp['guest_names'])) {
                                            $guestNames = json_decode($rsvp['guest_names'], true);
                                            if (is_array($guestNames) && !empty($guestNames)) {
                                                echo htmlspecialchars(implode(', ', $guestNames));
                                            } else {
                                                echo '—';
                                            }
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($rsvp['dietary'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($rsvp['message'] ?? ''); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($rsvp['created_at'])); ?></td>
                                    <td class="actions-cell">
                                        <a href="/check-rsvps?edit=<?php echo $rsvp['id']; ?>" class="btn-edit">Edit</a>
                                        <a href="/check-rsvps?delete=<?php echo $rsvp['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this RSVP? This action cannot be undone.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if ($editRsvp): ?>
                    <div class="edit-form-container">
                        <h2>Edit RSVP #<?php echo htmlspecialchars($editRsvp['id']); ?></h2>
                        <form method="POST" action="/check-rsvps">
                            <input type="hidden" name="update_rsvp" value="1">
                            <input type="hidden" name="rsvp_id" value="<?php echo htmlspecialchars($editRsvp['id']); ?>">
                            
                            <div class="form-group required">
                                <label for="edit_name">Name</label>
                                <input type="text" id="edit_name" name="name" required value="<?php echo htmlspecialchars($editRsvp['name']); ?>">
                            </div>
                            
                            <div class="form-group required">
                                <label for="edit_email">Email</label>
                                <input type="email" id="edit_email" name="email" required value="<?php echo htmlspecialchars($editRsvp['email']); ?>">
                            </div>
                            
                            <div class="form-group required">
                                <label for="edit_attending">Will they be attending?</label>
                                <select id="edit_attending" name="attending" required>
                                    <option value="Yes" <?php echo ($editRsvp['attending'] === 'Yes') ? 'selected' : ''; ?>>Yes, they'll be there!</option>
                                    <option value="No" <?php echo ($editRsvp['attending'] === 'No') ? 'selected' : ''; ?>>Sorry, they can't make it</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_guests">Number of Guests</label>
                                <input type="number" id="edit_guests" name="guests" min="1" value="<?php echo htmlspecialchars($editRsvp['guests']); ?>">
                            </div>
                            
                            <div id="edit-guest-names-container" class="form-group" style="display: none;">
                                <label>Additional Guest Names</label>
                                <div id="edit-guest-names-fields"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_dietary">Dietary Restrictions or Allergies</label>
                                <textarea id="edit_dietary" name="dietary" placeholder="Please let us know about any dietary restrictions or allergies..."><?php echo htmlspecialchars($editRsvp['dietary'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_message">Message</label>
                                <textarea id="edit_message" name="message" placeholder="Any additional message..."><?php echo htmlspecialchars($editRsvp['message'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn">Update RSVP</button>
                                <a href="/check-rsvps" class="btn-cancel">Cancel</a>
                            </div>
                        </form>
                    </div>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const guestsInput = document.getElementById('edit_guests');
                        const guestNamesContainer = document.getElementById('edit-guest-names-container');
                        const guestNamesFields = document.getElementById('edit-guest-names-fields');
                        
                        const existingGuestNames = {};
                        <?php
                        if (!empty($editRsvp['guest_names'])) {
                            $guestNames = json_decode($editRsvp['guest_names'], true);
                            if (is_array($guestNames)) {
                                foreach ($guestNames as $i => $name) {
                                    echo "existingGuestNames[" . ($i + 1) . "] = " . json_encode($name) . ";\n";
                                }
                            }
                        }
                        ?>
                        
                        function updateGuestNameFields() {
                            const guests = parseInt(guestsInput.value) || 1;
                            
                            if (guests > 1) {
                                guestNamesContainer.style.display = 'block';
                                guestNamesFields.innerHTML = '';
                                
                                for (let i = 1; i < guests; i++) {
                                    const fieldGroup = document.createElement('div');
                                    fieldGroup.style.marginBottom = '0.75rem';
                                    
                                    const label = document.createElement('label');
                                    label.setAttribute('for', 'edit_guest_name_' + i);
                                    label.textContent = 'Guest ' + i + ' Name';
                                    
                                    const input = document.createElement('input');
                                    input.type = 'text';
                                    input.id = 'edit_guest_name_' + i;
                                    input.name = 'guest_name_' + i;
                                    input.placeholder = 'Enter guest name';
                                    input.style.width = '100%';
                                    input.className = 'form-control';
                                    if (existingGuestNames[i]) {
                                        input.value = existingGuestNames[i];
                                    }
                                    
                                    fieldGroup.appendChild(label);
                                    fieldGroup.appendChild(input);
                                    guestNamesFields.appendChild(fieldGroup);
                                }
                            } else {
                                guestNamesContainer.style.display = 'none';
                                guestNamesFields.innerHTML = '';
                            }
                        }
                        
                        updateGuestNameFields();
                        guestsInput.addEventListener('change', updateGuestNameFields);
                        guestsInput.addEventListener('input', updateGuestNameFields);
                    });
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

