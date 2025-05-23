<?php
/**
 * Logout Handler
 * File: auth/logout.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Check if user is logged in
if (!isAuthenticated()) {
    header('Location: login.php');
    exit();
}

// Get user info before logging out
$userId = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

// Handle AJAX logout
if (isAjaxRequest()) {
    header('Content-Type: application/json');
    
    try {
        // Log the logout
        if ($userId && $username) {
            logActivity("User {$username} logged out");
        }
        
        // Clear remember me cookie if it exists
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', SECURE_COOKIES, true);
        }
        
        // Destroy session
        destroySession();
        
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully',
            'redirect_url' => '../auth/login.php'
        ]);
        
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred during logout'
        ]);
    }
    
    exit();
}

// Handle regular logout
try {
    // Log the logout
    if ($userId && $username) {
        logActivity("User {$username} logged out");
    }
    
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', SECURE_COOKIES, true);
    }
    
    // Destroy session
    destroySession();
    
    // Redirect to login with success message
    header('Location: login.php?logout=1');
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    header('Location: login.php?error=logout_failed');
}

exit();
?>