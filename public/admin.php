<?php
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/admin_auth.php';
require_once __DIR__ . '/../private/admin_sample.php';

$sampleMode = isAdminSampleMode();
$auth = $sampleMode ? ['authenticated' => true, 'error' => ''] : requireAdminAuth();
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
    <?php include __DIR__ . '/includes/theme_init.php'; ?>
    <?php renderAdminSampleModeAssets(); ?>
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
            background-color: var(--color-surface);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--color-shadow);
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
        <?php renderAdminSampleBanner('Admin Area Sample Mode'); ?>
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
                <p style="margin-top: 1rem; text-align: center; font-family: 'Crimson Text', serif; text-transform: none;">
                    Want a preview? <a href="<?php echo htmlspecialchars(adminUrl('/admin')); ?>?sample=1">Open sample mode</a>.
                </p>
            </div>
        <?php else: ?>
            <div class="admin-menu-container">
                <div class="logout-link">
                    <a href="<?php echo htmlspecialchars($sampleMode ? '/admin' : '/admin?logout=1'); ?>"<?php echo $sampleMode ? ' data-sample-ignore="true"' : ''; ?>><?php echo $sampleMode ? 'Exit Sample Mode' : 'Logout'; ?></a>
                </div>
                <h1 class="page-title">Admin Menu</h1>
                
                <ul class="admin-menu">
                    <li class="admin-menu-item">
                        <a href="<?php echo htmlspecialchars(adminUrl('/check-rsvps')); ?>">Check RSVPs</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="<?php echo htmlspecialchars(adminUrl('/admin-guests')); ?>">Manage Guests</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="<?php echo htmlspecialchars(adminUrl('/admin-seating')); ?>">Seating Chart</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="<?php echo htmlspecialchars(adminUrl('/admin-registry')); ?>">Manage Registry</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="<?php echo htmlspecialchars(adminUrl('/admin-house-fund')); ?>">Manage House Fund</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="<?php echo htmlspecialchars(adminUrl('/admin-gallery')); ?>">Manage Gallery</a>
                    </li>
                    <li class="admin-menu-item">
                        <a href="<?php echo htmlspecialchars(adminUrl('/admin-honeymoon-fund')); ?>">Manage Honeymoon Fund</a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
