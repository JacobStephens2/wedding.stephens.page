<?php
/**
 * Unified admin authentication helper
 */

function requireAdminAuth() {
    session_start();
    
    $authenticated = false;
    
    // Check if already authenticated
    if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
        $authenticated = true;
    }
    
    // Handle login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['admin_login'])) {
        $password = trim($_POST['password'] ?? '');
        $correctPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
        
        if ($password === $correctPassword) {
            $_SESSION['admin_authenticated'] = true;
            $authenticated = true;
        } else {
            return [
                'authenticated' => false,
                'error' => 'Incorrect password. Please try again.'
            ];
        }
    }
    
    // Handle logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: /admin');
        exit;
    }
    
    return [
        'authenticated' => $authenticated,
        'error' => ''
    ];
}

function isAdminAuthenticated() {
    session_start();
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function adminLogout() {
    session_start();
    session_destroy();
}


