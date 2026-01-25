<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/admin_auth.php';

session_start();

$error = '';
$success = '';
$authenticated = false;

// Check unified admin auth first
if (isAdminAuthenticated()) {
    $authenticated = true;
} else {
    // Fallback to old auth system for backward compatibility
    if (isset($_SESSION['registry_admin_authenticated']) && $_SESSION['registry_admin_authenticated'] === true) {
        $authenticated = true;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['amount'])) {
    $password = trim($_POST['password'] ?? '');
    
    // Try unified admin password first
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
    if ($password === $adminPassword) {
        $_SESSION['admin_authenticated'] = true;
        $authenticated = true;
    } else {
        // Fallback to old password
        $correctPassword = $_ENV['REGISTRY_ADMIN_PASSWORD'] ?? '';
        if ($password === $correctPassword) {
            $_SESSION['registry_admin_authenticated'] = true;
            $authenticated = true;
        } else {
            $error = 'Incorrect password. Please try again.';
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin-house-fund');
    exit;
}

// Handle adding new contribution or updating existing contribution
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    try {
        $pdo = getDbConnection();
        $contributionId = $_POST['contribution_id'] ?? null;
        $amount = floatval($_POST['amount'] ?? 0);
        $contributorName = trim($_POST['contributor_name'] ?? '');
        
        if ($amount <= 0) {
            $error = 'Amount must be greater than 0.';
        } else {
            if ($contributionId) {
                // Update existing contribution
                $stmt = $pdo->prepare("
                    UPDATE house_fund_contributions 
                    SET amount = ?, contributor_name = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $amount,
                    $contributorName ?: null,
                    $contributionId
                ]);
                $success = 'Contribution updated successfully!';
            } else {
                // Insert new contribution
                $stmt = $pdo->prepare("
                    INSERT INTO house_fund_contributions (amount, contributor_name)
                    VALUES (?, ?)
                ");
                $stmt->execute([
                    $amount,
                    $contributorName ?: null
                ]);
                $success = 'Contribution added successfully!';
            }
        }
    } catch (Exception $e) {
        $error = 'Error saving contribution: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle deleting contribution
if ($authenticated && isset($_GET['delete'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM house_fund_contributions WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = 'Contribution deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting contribution: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch contribution for editing
$editContribution = null;
if ($authenticated && isset($_GET['edit'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM house_fund_contributions WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editContribution = $stmt->fetch();
        if (!$editContribution) {
            $error = 'Contribution not found.';
            $editContribution = null;
        }
    } catch (Exception $e) {
        $error = 'Error loading contribution: ' . htmlspecialchars($e->getMessage());
        $editContribution = null;
    }
}

// Fetch all contributions if authenticated
$contributions = [];
$totalAmount = 0;
if ($authenticated) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT id, amount, contributor_name, created_at
            FROM house_fund_contributions
            ORDER BY created_at DESC
        ");
        $contributions = $stmt->fetchAll();
        
        // Calculate total
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM house_fund_contributions
        ");
        $result = $stmt->fetch();
        $totalAmount = $result['total'] ?? 0;
    } catch (Exception $e) {
        $error = 'Error loading contributions: ' . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Manage House Fund - Jacob & Melissa";
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
    <?php include __DIR__ . '/includes/admin_menu.php'; ?>
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .total-summary {
            background-color: var(--color-light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .total-summary h2 {
            color: var(--color-green);
            margin-bottom: 0.5rem;
        }
        .total-amount {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--color-green);
        }
        .contributions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .contributions-table th,
        .contributions-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .contributions-table th {
            background-color: var(--color-green);
            color: white;
            font-family: 'Cinzel', serif;
        }
        .contributions-table tr:hover {
            background-color: #f5f5f5;
        }
        .actions-cell {
            white-space: nowrap;
        }
        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        .btn-edit {
            background-color: var(--color-green);
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .form-container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <main class="page-container">
        <div class="admin-container">
            <div class="back-to-site">
                <a href="/">← Back to Main Site</a>
            </div>
            
            <?php if (!$authenticated): ?>
                <div class="form-container">
                    <h1 class="page-title">Manage House Fund</h1>
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="/admin-house-fund">
                        <div class="form-group required">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required autofocus>
                        </div>
                        <button type="submit" class="btn">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-header">
                    <h1 class="page-title">Manage House Fund</h1>
                    <div>
                        <a href="/admin-house-fund?logout=1" class="btn btn-secondary">Logout</a>
                    </div>
                </div>
                
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
                
                <div class="total-summary">
                    <h2>Total Contributions</h2>
                    <p class="total-amount">$<?php echo number_format($totalAmount, 2); ?></p>
                </div>
                
                <div class="form-container">
                    <h2><?php echo $editContribution ? 'Edit Contribution' : 'Add New Contribution'; ?></h2>
                    <form method="POST" action="/admin-house-fund">
                        <?php if ($editContribution): ?>
                            <input type="hidden" name="contribution_id" value="<?php echo htmlspecialchars($editContribution['id']); ?>">
                        <?php endif; ?>
                        
                        <div class="form-group required">
                            <label for="amount">Amount</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0.01" required 
                                   value="<?php echo htmlspecialchars($editContribution['amount'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="contributor_name">Contributor Name (optional)</label>
                            <input type="text" id="contributor_name" name="contributor_name" 
                                   value="<?php echo htmlspecialchars($editContribution['contributor_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn"><?php echo $editContribution ? 'Update Contribution' : 'Add Contribution'; ?></button>
                            <?php if ($editContribution): ?>
                                <a href="/admin-house-fund" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <?php if (!empty($contributions)): ?>
                    <div class="form-container">
                        <h2>All Contributions</h2>
                        <table class="contributions-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Amount</th>
                                    <th>Contributor</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contributions as $contribution): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($contribution['id']); ?></td>
                                        <td>$<?php echo number_format($contribution['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($contribution['contributor_name'] ?? '—'); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($contribution['created_at'])); ?></td>
                                        <td class="actions-cell">
                                            <a href="/admin-house-fund?edit=<?php echo $contribution['id']; ?>" class="btn btn-small btn-edit">Edit</a>
                                            <a href="/admin-house-fund?delete=<?php echo $contribution['id']; ?>" class="btn btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this contribution?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="form-container">
                        <p>No contributions yet.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
