<?php
/**
 * Admin menu component - displays navigation menu for admin pages
 * Only shows if user is authenticated
 */
if (!function_exists('isAdminAuthenticated')) {
    require_once __DIR__ . '/../../private/admin_auth.php';
}
if (!function_exists('isAdminSampleMode')) {
    require_once __DIR__ . '/../../private/admin_sample.php';
}

if (isAdminSampleMode() || isAdminAuthenticated()):
?>
    <nav class="admin-menu-nav">
        <div class="admin-menu-container">
            <ul class="admin-menu-list">
                <li><a href="<?php echo htmlspecialchars(adminUrl('/admin')); ?>">Admin Menu</a></li>
                <li><a href="<?php echo htmlspecialchars(adminUrl('/check-rsvps')); ?>">Check RSVPs</a></li>
                <li><a href="<?php echo htmlspecialchars(adminUrl('/admin-guests')); ?>">Manage Guests</a></li>
                <li><a href="<?php echo htmlspecialchars(adminUrl('/admin-seating')); ?>">Seating Chart</a></li>
                <li><a href="<?php echo htmlspecialchars(adminUrl('/admin-registry')); ?>">Manage Registry</a></li>
                <li><a href="<?php echo htmlspecialchars(adminUrl('/admin-house-fund')); ?>">Manage House Fund</a></li>
                <li><a href="<?php echo htmlspecialchars(adminUrl('/admin-honeymoon-fund')); ?>">Manage Honeymoon Fund</a></li>
                <li><a href="<?php echo htmlspecialchars(adminUrl('/admin-gallery')); ?>">Manage Gallery</a></li>
                <li><a href="<?php echo htmlspecialchars(isAdminSampleMode() ? '/admin' : '/admin?logout=1'); ?>"<?php echo isAdminSampleMode() ? ' data-sample-ignore="true"' : ''; ?>><?php echo isAdminSampleMode() ? 'Exit Sample Mode' : 'Logout'; ?></a></li>
                <li>
                    <button class="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode" style="color: white;">
                        <span class="icon-moon">&#9789;</span>
                        <span class="icon-sun">&#9788;</span>
                    </button>
                </li>
            </ul>
        </div>
    </nav>
    <script>
        (function() {
            var toggles = document.querySelectorAll('.theme-toggle');
            toggles.forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    if (isDark) {
                        document.documentElement.removeAttribute('data-theme');
                        localStorage.setItem('theme', 'light');
                    } else {
                        document.documentElement.setAttribute('data-theme', 'dark');
                        localStorage.setItem('theme', 'dark');
                    }
                });
            });
        })();
    </script>
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
