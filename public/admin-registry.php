<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/db.php';

session_start();

$error = '';
$success = '';
$authenticated = false;

// Check if already authenticated
if (isset($_SESSION['registry_admin_authenticated']) && $_SESSION['registry_admin_authenticated'] === true) {
    $authenticated = true;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['title'])) {
    $password = trim($_POST['password'] ?? '');
    $correctPassword = $_ENV['REGISTRY_ADMIN_PASSWORD'] ?? '';
    
    if ($password === $correctPassword) {
        $_SESSION['registry_admin_authenticated'] = true;
        $authenticated = true;
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin-registry');
    exit;
}

// Handle adding new item
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            INSERT INTO registry_items (title, description, url, image_url)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? ''),
            trim($_POST['url'] ?? ''),
            trim($_POST['image_url'] ?? '')
        ]);
        $success = 'Registry item added successfully!';
    } catch (Exception $e) {
        $error = 'Error adding item: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle deleting item
if ($authenticated && isset($_GET['delete'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM registry_items WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = 'Registry item deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting item: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle toggling purchased status
if ($authenticated && isset($_GET['toggle_purchased'])) {
    try {
        $pdo = getDbConnection();
        // First get current status
        $stmt = $pdo->prepare("SELECT purchased FROM registry_items WHERE id = ?");
        $stmt->execute([$_GET['toggle_purchased']]);
        $item = $stmt->fetch();
        
        if ($item) {
            $newPurchasedStatus = !$item['purchased'];
            // Update with proper purchased_by handling - clear it when toggling
            $stmt = $pdo->prepare("
                UPDATE registry_items 
                SET purchased = ?, 
                    purchased_by = NULL
                WHERE id = ?
            ");
            $stmt->execute([
                $newPurchasedStatus ? 1 : 0,
                $_GET['toggle_purchased']
            ]);
            $success = 'Item status updated!';
        }
    } catch (Exception $e) {
        $error = 'Error updating item: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch registry items if authenticated
$items = [];
if ($authenticated) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT id, title, description, url, image_url, purchased, purchased_by, created_at
            FROM registry_items
            ORDER BY created_at DESC
        ");
        $items = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = 'Error loading items: ' . htmlspecialchars($e->getMessage());
    }
}

$page_title = "Manage Registry - Jacob & Melissa";
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
        .add-item-form {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .add-item-form h2 {
            color: var(--color-green);
            margin-bottom: 1.5rem;
        }
        .items-list {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .items-list h2 {
            color: var(--color-green);
            margin-bottom: 1.5rem;
        }
        .item-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1.5rem;
        }
        .item-card.purchased {
            opacity: 0.6;
            background-color: #f5f5f5;
        }
        .item-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
            flex-shrink: 0;
        }
        .item-content {
            flex: 1;
        }
        .item-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--color-dark);
            margin-bottom: 0.5rem;
        }
        .item-description {
            color: #666;
            margin-bottom: 0.5rem;
        }
        .item-url {
            margin-bottom: 0.5rem;
        }
        .item-url a {
            color: var(--color-gold);
            text-decoration: none;
        }
        .item-url a:hover {
            text-decoration: underline;
        }
        .item-meta {
            font-size: 0.9rem;
            color: #999;
            margin-bottom: 1rem;
        }
        .item-actions {
            display: flex;
            gap: 1rem;
        }
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .btn-toggle {
            background-color: var(--color-green);
            color: white;
        }
        .btn-toggle:hover {
            background-color: #2d5016;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .purchased-badge {
            display: inline-block;
            background-color: var(--color-green);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-left: 0.5rem;
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
                <h1 class="page-title">Manage Registry</h1>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                <form method="POST" action="/admin-registry">
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
                    <a href="/admin-registry?logout=1">Logout</a>
                </div>
                <h1 class="page-title">Manage Registry</h1>
                
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
                
                <div class="add-item-form">
                    <h2>Add New Registry Item</h2>
                    <form method="POST" action="/admin-registry">
                        <div class="form-group required">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group required">
                            <label for="url">URL</label>
                            <input type="url" id="url" name="url" required>
                        </div>
                        <div class="form-group">
                            <label for="image_url">Image URL (optional)</label>
                            <input type="url" id="image_url" name="image_url">
                        </div>
                        <button type="submit" class="btn">Add Item</button>
                    </form>
                </div>
                
                <div class="items-list">
                    <h2>Registry Items (<?php echo count($items); ?>)</h2>
                    <?php if (empty($items)): ?>
                        <p>No registry items yet. Add one above!</p>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <div class="item-card <?php echo $item['purchased'] ? 'purchased' : ''; ?>">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="item-image">
                                <?php endif; ?>
                                <div class="item-content">
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                        <?php if ($item['purchased']): ?>
                                            <span class="purchased-badge">Purchased</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($item['description']): ?>
                                        <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="item-url">
                                        <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" rel="noopener noreferrer">
                                            View Item →
                                        </a>
                                    </div>
                                    <div class="item-meta">
                                        Added: <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                                        <?php if ($item['purchased_by']): ?>
                                            | Purchased by: <?php echo htmlspecialchars($item['purchased_by']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-actions">
                                        <a href="/admin-registry?toggle_purchased=<?php echo $item['id']; ?>" class="btn-small btn-toggle">
                                            <?php echo $item['purchased'] ? 'Mark as Available' : 'Mark as Purchased'; ?>
                                        </a>
                                        <a href="/admin-registry?delete=<?php echo $item['id']; ?>" class="btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this item?');">
                                            Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

