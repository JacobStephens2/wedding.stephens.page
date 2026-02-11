<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/admin_auth.php';

$auth = requireAdminAuth();
$authenticated = $auth['authenticated'];
$error = $auth['error'];

$page_title = "Admin - Jacob & Melissa";
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
        .admin-menu-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .admin-menu {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }
        .admin-menu-item {
            margin-bottom: 1rem;
        }
        .admin-menu-item a {
            display: block;
            padding: 1rem 1.5rem;
            background-color: var(--color-green);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
            font-size: 1.1rem;
        }
        .admin-menu-item a:hover {
            background-color: var(--color-gold);
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
    </style>
</head>
<body>
    <main class="page-container">
        <div class="back-to-site">
            <a href="/">← Back to Main Site</a>
        </div>
        
        <?php if (!$authenticated): ?>
            <div class="form-container">
                <h1 class="page-title">Admin Area</h1>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                <form method="POST" action="/admin">
                    <input type="hidden" name="admin_login" value="1">
                    <div class="form-group required">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-menu-container">
                <div class="logout-link">
                    <a href="/admin?logout=1">Logout</a>
                </div>
                <h1 class="page-title">Admin Menu</h1>
                
                <ul class="admin-menu">
                    <li class="admin-menu-item">
                        <a href="/check-rsvps">Check RSVPs</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="/admin-guests">Manage Guests</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="/admin-registry">Manage Registry</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="/admin-house-fund">Manage House Fund</a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>


