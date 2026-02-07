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

// Handle login - try unified admin auth first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !isset($_POST['title'])) {
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
    header('Location: /admin-registry');
    exit;
}

// Handle adding new item or updating existing item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    if (!$authenticated) {
        // Session expired - show error message
        $error = 'Your session has expired. Please log in again. Your form data has been saved and will be restored after you log in.';
    } else {
        try {
            $pdo = getDbConnection();
            $itemId = $_POST['item_id'] ?? null;
            
            if ($itemId) {
                // Update existing item
                $price = !empty($_POST['price']) ? $_POST['price'] : null;
                $published = isset($_POST['published']) && $_POST['published'] === '1' ? 1 : 0;
                $stmt = $pdo->prepare("
                    UPDATE registry_items 
                    SET title = ?, description = ?, url = ?, image_url = ?, price = ?, published = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    trim($_POST['title'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    trim($_POST['url'] ?? ''),
                    trim($_POST['image_url'] ?? ''),
                    $price,
                    $published,
                    $itemId
                ]);
                // Redirect to clear edit parameter and show success
                header('Location: /admin-registry?updated=1');
                exit;
            } else {
                // Insert new item - default to published (1)
                $price = !empty($_POST['price']) ? $_POST['price'] : null;
                $published = isset($_POST['published']) && $_POST['published'] === '1' ? 1 : 1; // Default to published
                $maxOrder = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM registry_items")->fetchColumn();
                $stmt = $pdo->prepare("
                    INSERT INTO registry_items (title, description, url, image_url, price, published, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    trim($_POST['title'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    trim($_POST['url'] ?? ''),
                    trim($_POST['image_url'] ?? ''),
                    $price,
                    $published,
                    $maxOrder
                ]);
                $success = 'Registry item added successfully!';
            }
        } catch (Exception $e) {
            $error = 'Error saving item: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch item for editing
$editItem = null;
if ($authenticated && isset($_GET['edit'])) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT id, title, description, url, image_url, price, published
            FROM registry_items
            WHERE id = ?
        ");
        $stmt->execute([$_GET['edit']]);
        $editItem = $stmt->fetch();
    } catch (Exception $e) {
        $error = 'Error loading item: ' . htmlspecialchars($e->getMessage());
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

// Handle move order (move up / move down)
if ($authenticated && isset($_GET['move_up'], $_GET['id']) && is_numeric($_GET['id'])) {
    $moveId = (int) $_GET['id'];
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, sort_order FROM registry_items WHERE id = ?");
        $stmt->execute([$moveId]);
        $current = $stmt->fetch();
        if ($current) {
            $stmt = $pdo->prepare("
                SELECT id, sort_order FROM registry_items
                WHERE sort_order < ? OR (sort_order = ? AND id < ?)
                ORDER BY sort_order DESC, id DESC LIMIT 1
            ");
            $stmt->execute([$current['sort_order'], $current['sort_order'], $moveId]);
            $prev = $stmt->fetch();
            if ($prev) {
                $pdo->prepare("UPDATE registry_items SET sort_order = ? WHERE id = ?")->execute([$prev['sort_order'], $moveId]);
                $pdo->prepare("UPDATE registry_items SET sort_order = ? WHERE id = ?")->execute([$current['sort_order'], $prev['id']]);
            }
        }
        header('Location: /admin-registry?reordered=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error reordering: ' . htmlspecialchars($e->getMessage());
    }
}
if ($authenticated && isset($_GET['move_down'], $_GET['id']) && is_numeric($_GET['id'])) {
    $moveId = (int) $_GET['id'];
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, sort_order FROM registry_items WHERE id = ?");
        $stmt->execute([$moveId]);
        $current = $stmt->fetch();
        if ($current) {
            $stmt = $pdo->prepare("
                SELECT id, sort_order FROM registry_items
                WHERE sort_order > ? OR (sort_order = ? AND id > ?)
                ORDER BY sort_order ASC, id ASC LIMIT 1
            ");
            $stmt->execute([$current['sort_order'], $current['sort_order'], $moveId]);
            $next = $stmt->fetch();
            if ($next) {
                $pdo->prepare("UPDATE registry_items SET sort_order = ? WHERE id = ?")->execute([$next['sort_order'], $moveId]);
                $pdo->prepare("UPDATE registry_items SET sort_order = ? WHERE id = ?")->execute([$current['sort_order'], $next['id']]);
            }
        }
        header('Location: /admin-registry?reordered=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error reordering: ' . htmlspecialchars($e->getMessage());
    }
}

// Handle bulk reorder
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_reorder']) && isset($_POST['positions'])) {
    try {
        $pdo = getDbConnection();
        $positions = $_POST['positions']; // array of id => new position number
        
        // Collect and validate: id => desired position (1-based)
        $desired = [];
        foreach ($positions as $id => $pos) {
            $id = (int) $id;
            $pos = (int) $pos;
            if ($id > 0 && $pos > 0) {
                $desired[$id] = $pos;
            }
        }
        
        if (!empty($desired)) {
            // Sort by desired position, then by id when positions tie (so duplicate positions are deterministic)
            $pairs = [];
            foreach ($desired as $id => $pos) {
                $pairs[] = [$pos, $id];
            }
            usort($pairs, function ($a, $b) {
                if ($a[0] !== $b[0]) return $a[0] - $b[0];
                return $a[1] - $b[1];
            });
            $stmt = $pdo->prepare("UPDATE registry_items SET sort_order = ? WHERE id = ?");
            foreach ($pairs as $i => $pair) {
                $stmt->execute([$i + 1, $pair[1]]);
            }
        }
        
        header('Location: /admin-registry?reordered=1');
        exit;
    } catch (Exception $e) {
        $error = 'Error reordering: ' . htmlspecialchars($e->getMessage());
    }
}

// Fetch registry items if authenticated
$items = [];
if ($authenticated) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("
            SELECT id, title, description, url, image_url, price, purchased, purchased_by, created_at, published, sort_order
            FROM registry_items
            ORDER BY sort_order ASC, id ASC
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
        .items-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 1.5rem;
        }
        .item-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1.5rem;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        /* Grid layout for wide viewports */
        @media (min-width: 768px) {
            .items-list-grid {
                display: grid;
            }
            .items-list-grid .item-card {
                display: flex;
                flex-direction: column;
                margin-bottom: 0;
                padding: 0;
                overflow: hidden;
                transition: transform 0.3s, box-shadow 0.3s;
            }
            .items-list-grid .item-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            }
            .items-list-grid .item-image {
                width: 100%;
                height: 250px;
                object-fit: contain;
                border-radius: 0;
                background-color: #f5f5f5;
                padding: 1rem;
            }
            .items-list-grid .item-content {
                padding: 1.5rem;
                display: flex;
                flex-direction: column;
                flex: 1;
            }
            .items-list-grid .item-actions {
                margin-top: auto;
                padding-top: 1rem;
            }
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
            text-transform: none;
            font-family: 'Crimson Text', serif;
            line-height: 1.6;
        }
        #description {
            text-transform: none;
            font-variant: normal;
            font-family: 'Crimson Text', serif;
        }
        .item-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--color-green);
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
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
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
        .btn-move {
            background-color: #e9ecef;
            color: var(--color-dark);
            min-width: 2rem;
            text-align: center;
        }
        .btn-move:hover {
            background-color: #dee2e6;
            color: var(--color-green);
        }
        .btn-edit {
            background-color: var(--color-gold);
            color: white;
        }
        .btn-edit:hover {
            background-color: hsl(13 37% 55% / 1);
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
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
        .btn-secondary:hover {
            background-color: #5a6268;
            color: white;
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
        .unpublished-badge {
            display: inline-block;
            background-color: #6c757d;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-left: 0.5rem;
        }
        .reorder-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .reorder-hint {
            font-size: 0.9rem;
            color: #666;
            margin: 0;
            font-family: 'Crimson Text', serif;
        }
        .btn-save-order {
            white-space: nowrap;
        }
        .btn-save-order:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .item-position {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
        }
        .item-position label {
            color: #666;
            font-family: 'Crimson Text', serif;
            margin: 0;
        }
        .position-input {
            width: 4rem;
            padding: 0.25rem 0.4rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: center;
            font-size: 0.9rem;
        }
        .position-input:focus {
            border-color: var(--color-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 143, 101, 0.25);
        }
        .position-input.changed {
            border-color: var(--color-gold);
            background-color: #fff9e6;
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
                
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">
                        <p>Registry item updated successfully!</p>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['reordered'])): ?>
                    <div class="alert alert-success">
                        <p>Order updated.</p>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <p><?php echo htmlspecialchars($success); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="add-item-form">
                    <h2 id="form-title"><?php echo $editItem ? 'Edit Registry Item' : 'Add New Registry Item'; ?></h2>
                    <form method="POST" action="/admin-registry" id="item-form">
                        <?php if ($editItem): ?>
                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($editItem['id']); ?>">
                        <?php endif; ?>
                        <div class="form-group required">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" value="<?php echo $editItem ? htmlspecialchars($editItem['title']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"><?php echo $editItem ? htmlspecialchars($editItem['description']) : ''; ?></textarea>
                        </div>
                        <div class="form-group required">
                            <label for="url">URL</label>
                            <input type="url" id="url" name="url" value="<?php echo $editItem ? htmlspecialchars($editItem['url']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="image_url">Image URL (optional)</label>
                            <input type="url" id="image_url" name="image_url" value="<?php echo $editItem ? htmlspecialchars($editItem['image_url']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="price">Price (optional)</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $editItem && $editItem['price'] ? htmlspecialchars($editItem['price']) : ''; ?>" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="published">Publishing Status</label>
                            <select id="published" name="published">
                                <option value="1" <?php echo (!$editItem || ($editItem && $editItem['published'])) ? 'selected' : ''; ?>>Published</option>
                                <option value="0" <?php echo ($editItem && !$editItem['published']) ? 'selected' : ''; ?>>Unpublished</option>
                            </select>
                            <small style="display: block; margin-top: 0.5rem; color: #666;">Published items appear on the public registry page. Unpublished items are only visible in the admin area.</small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn" id="submit-btn"><?php echo $editItem ? 'Update Item' : 'Add Item'; ?></button>
                            <?php if ($editItem): ?>
                                <a href="/admin-registry" class="btn btn-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="items-list">
                    <h2>Registry Items (<?php echo count($items); ?>)</h2>
                    <?php if (empty($items)): ?>
                        <p>No registry items yet. Add one above!</p>
                    <?php else: ?>
                        <form method="POST" action="/admin-registry" id="reorder-form">
                            <input type="hidden" name="bulk_reorder" value="1">
                            <div class="reorder-controls">
                                <p class="reorder-hint">Change position numbers and click "Save Order" to rearrange items. Use ↑↓ for single-step moves.</p>
                                <button type="submit" class="btn btn-save-order" id="save-order-btn" disabled>Save Order</button>
                            </div>
                            <div class="items-list-grid">
                                <?php $position = 1; ?>
                                <?php foreach ($items as $item): ?>
                                    <div class="item-card <?php echo $item['purchased'] ? 'purchased' : ''; ?>">
                                        <div class="item-position">
                                            <label for="pos-<?php echo $item['id']; ?>">Position</label>
                                            <input type="number" name="positions[<?php echo $item['id']; ?>]" id="pos-<?php echo $item['id']; ?>" value="<?php echo $position; ?>" min="1" max="<?php echo count($items); ?>" class="position-input" data-original="<?php echo $position; ?>">
                                        </div>
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="item-image">
                                        <?php endif; ?>
                                        <div class="item-content">
                                            <div class="item-title">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                                <?php if ($item['purchased']): ?>
                                                    <span class="purchased-badge">Purchased</span>
                                                <?php endif; ?>
                                                <?php if (!$item['published']): ?>
                                                    <span class="unpublished-badge">Unpublished</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($item['description']): ?>
                                                <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($item['price']): ?>
                                                <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
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
                                                <a href="/admin-registry?move_up=1&id=<?php echo $item['id']; ?>" class="btn-small btn-move" title="Move earlier">↑</a>
                                                <a href="/admin-registry?move_down=1&id=<?php echo $item['id']; ?>" class="btn-small btn-move" title="Move later">↓</a>
                                                <a href="/admin-registry?edit=<?php echo $item['id']; ?>" class="btn-small btn-edit">
                                                    Edit
                                                </a>
                                                <a href="/admin-registry?toggle_purchased=<?php echo $item['id']; ?>" class="btn-small btn-toggle">
                                                    <?php echo $item['purchased'] ? 'Mark as Available' : 'Mark as Purchased'; ?>
                                                </a>
                                                <a href="/admin-registry?delete=<?php echo $item['id']; ?>" class="btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this item?');">
                                                    Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php $position++; ?>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    <script>
        // Reorder position inputs
        (function() {
            const saveBtn = document.getElementById('save-order-btn');
            const positionInputs = document.querySelectorAll('.position-input');
            if (!saveBtn || positionInputs.length === 0) return;

            function checkChanges() {
                let hasChanges = false;
                positionInputs.forEach(input => {
                    if (input.value !== input.dataset.original) {
                        input.classList.add('changed');
                        hasChanges = true;
                    } else {
                        input.classList.remove('changed');
                    }
                });
                saveBtn.disabled = !hasChanges;
            }

            positionInputs.forEach(input => {
                input.addEventListener('input', checkChanges);
                input.addEventListener('change', checkChanges);
            });
        })();

        // Form data persistence to prevent data loss on session timeout
        (function() {
            const FORM_STORAGE_KEY = 'admin-registry-form-data';
            const form = document.getElementById('item-form');
            
            if (!form) return;
            
            // Don't auto-save if editing an existing item (it's already saved)
            const isEditing = form.querySelector('input[name="item_id"]') !== null;
            
            // Save form data to localStorage
            function saveFormData() {
                if (isEditing) return; // Don't save when editing
                
                const formData = {
                    title: document.getElementById('title')?.value || '',
                    description: document.getElementById('description')?.value || '',
                    url: document.getElementById('url')?.value || '',
                    image_url: document.getElementById('image_url')?.value || '',
                    price: document.getElementById('price')?.value || '',
                    published: document.getElementById('published')?.value || '1'
                };
                
                // Only save if at least one field has content
                const hasContent = Object.values(formData).some(val => val.trim() !== '');
                if (hasContent) {
                    localStorage.setItem(FORM_STORAGE_KEY, JSON.stringify(formData));
                } else {
                    localStorage.removeItem(FORM_STORAGE_KEY);
                }
            }
            
            // Restore form data from localStorage
            function restoreFormData() {
                if (isEditing) return; // Don't restore when editing
                
                const saved = localStorage.getItem(FORM_STORAGE_KEY);
                if (!saved) return;
                
                try {
                    const formData = JSON.parse(saved);
                    const hasContent = Object.entries(formData).some(([key, val]) => {
                        if (key === 'published') return val !== undefined && val !== '';
                        return val && typeof val === 'string' && val.trim() !== '';
                    });
                    
                    if (hasContent) {
                        // Check if form is already filled (user might have manually entered data)
                        const title = document.getElementById('title')?.value || '';
                        const url = document.getElementById('url')?.value || '';
                        
                        // Only restore if form is empty
                        if (!title && !url) {
                            if (formData.title) document.getElementById('title').value = formData.title;
                            if (formData.description) document.getElementById('description').value = formData.description;
                            if (formData.url) document.getElementById('url').value = formData.url;
                            if (formData.image_url) document.getElementById('image_url').value = formData.image_url;
                            if (formData.price) document.getElementById('price').value = formData.price;
                            if (formData.published) {
                                const publishedSelect = document.getElementById('published');
                                if (publishedSelect) publishedSelect.value = formData.published;
                            }
                            
                            // Show a notice that data was restored
                            const notice = document.createElement('div');
                            notice.className = 'alert alert-info';
                            notice.style.marginTop = '1rem';
                            notice.style.padding = '0.75rem 1rem';
                            notice.style.backgroundColor = '#d1ecf1';
                            notice.style.border = '1px solid #bee5eb';
                            notice.style.borderRadius = '4px';
                            notice.style.color = '#0c5460';
                            notice.innerHTML = '<p>⚠️ Your previous form data has been restored. Your session may have expired - please verify your data before submitting.</p>';
                            form.parentElement.insertBefore(notice, form);
                            
                            // Auto-remove notice after 10 seconds
                            setTimeout(() => {
                                if (notice.parentElement) {
                                    notice.remove();
                                }
                            }, 10000);
                        }
                    }
                } catch (e) {
                    console.error('Error restoring form data:', e);
                    localStorage.removeItem(FORM_STORAGE_KEY);
                }
            }
            
            // Clear saved form data
            function clearFormData() {
                localStorage.removeItem(FORM_STORAGE_KEY);
            }
            
            // Auto-save on input changes (debounced)
            let saveTimeout;
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(saveFormData, 500); // Debounce 500ms
                });
                input.addEventListener('change', () => {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(saveFormData, 500); // Debounce 500ms
                });
            });
            
            // Clear saved data on successful submission
            form.addEventListener('submit', function(e) {
                // Don't prevent submission, just clear saved data
                clearFormData();
            });
            
            // Restore on page load
            document.addEventListener('DOMContentLoaded', function() {
                restoreFormData();
                
                // Scroll to form when editing
                <?php if ($editItem): ?>
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    // Focus on first input
                    const firstInput = form.querySelector('input[type="text"]');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }
                <?php endif; ?>
            });
            
            // Also restore immediately if DOM is already loaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', restoreFormData);
            } else {
                restoreFormData();
            }
        })();
    </script>
</body>
</html>

