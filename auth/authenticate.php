<?php
/**
 * Authentication Handler (AJAX)
 * File: auth/authenticate.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if already logged in
if (isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Already logged in']);
    exit();
}

$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $username = sanitize($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $rememberMe = isset($input['remember_me']) && $input['remember_me'];
    
    // Validate CSRF token
    if (!validateCSRFToken($input['csrf_token'] ?? '')) {
        $response['message'] = 'Invalid security token. Please refresh and try again.';
        echo json_encode($response);
        exit();
    }
    
    // Validate required fields
    if (empty($username) || empty($password)) {
        $response['message'] = 'Please enter both username and password.';
        echo json_encode($response);
        exit();
    }
    
    // Check login attempts
    if (!checkLoginAttempts($username)) {
        $remainingTime = getRemainingLockoutTime($username);
        $minutes = ceil($remainingTime / 60);
        $response['message'] = "Too many failed attempts. Account locked for {$minutes} minutes.";
        $response['lockout_time'] = $remainingTime;
        echo json_encode($response);
        exit();
    }
    
    // Attempt authentication
    $user = new User();
    
    if ($user->authenticate($username, $password)) {
        // Clear failed login attempts
        clearLoginAttempts($username);
        
        // Set session fingerprint for security
        setSessionFingerprint();
        
        // Handle remember me functionality
        if ($rememberMe) {
            $token = generateRandomString(32);
            // In a full implementation, store this token in database
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', SECURE_COOKIES, true);
        }
        
        // Log successful login
        logActivity("User {$username} logged in successfully via API");
        
        // Get intended redirect URL
        $redirectUrl = $_SESSION['intended_url'] ?? '../dashboard/';
        unset($_SESSION['intended_url']);
        
        $response['success'] = true;
        $response['message'] = 'Login successful';
        $response['data'] = [
            'redirect_url' => $redirectUrl,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'full_name' => $_SESSION['full_name']
            ]
        ];
        
    } else {
        // Record failed attempt
        recordFailedLogin($username);
        
        // Log failed attempt
        logActivity("Failed login attempt for user {$username}", 'WARNING', [
            'ip' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        $response['message'] = 'Invalid username or password.';
        
        // Add remaining attempts info
        $remainingAttempts = MAX_LOGIN_ATTEMPTS - ($_SESSION['login_attempts_' . md5($username . getClientIP())]['count'] ?? 0);
        if ($remainingAttempts > 0) {
            $response['remaining_attempts'] = $remainingAttempts;
        }
    }
    
} catch (Exception $e) {
    error_log("Authentication error: " . $e->getMessage());
    $response['message'] = 'An error occurred during authentication. Please try again.';
    http_response_code(500);
}

echo json_encode($response);
?>