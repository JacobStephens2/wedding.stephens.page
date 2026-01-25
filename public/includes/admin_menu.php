<?php
/**
 * Admin menu component - displays navigation menu for admin pages
 * Only shows if user is authenticated
 */
if (!function_exists('isAdminAuthenticated')) {
    require_once __DIR__ . '/../../private/admin_auth.php';
}

if (isAdminAuthenticated()):
?>
    <nav class="admin-menu-nav">
        <div class="admin-menu-container">
            <ul class="admin-menu-list">
                <li><a href="/admin">Admin Menu</a></li>
                <li><a href="/check-rsvps">Check RSVPs</a></li>
                <li><a href="/admin-registry">Manage Registry</a></li>
                <li><a href="/admin-house-fund">Manage House Fund</a></li>
                <li><a href="/admin?logout=1">Logout</a></li>
            </ul>
        </div>
    </nav>
    <style>
        .admin-menu-nav {
            background-color: var(--color-green);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        .admin-menu-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        .admin-menu-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .admin-menu-list li a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .admin-menu-list li a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
    </style>
<?php endif; ?>

