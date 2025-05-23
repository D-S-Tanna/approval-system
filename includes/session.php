<?php
/**
 * Session Management
 * File: includes/session.php
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', SECURE_COOKIES ? 1 : 0);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    
    session_start();
}

/**
 * Initialize session security
 */
function initializeSession() {
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        destroySession();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Check if user is authenticated
 * @return bool
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['username']) && 
           isset($_SESSION['role']);
}

/**
 * Require authentication
 * @param string $redirectUrl
 */
function requireAuth($redirectUrl = null) {
    if (!initializeSession() || !isAuthenticated()) {
        if ($redirectUrl === null) {
            $redirectUrl = '/approval-system/auth/login.php';
        }
        
        // Store intended destination
        if (!isset($_SESSION['intended_url'])) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        }
        
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Require specific role
 * @param array|string $allowedRoles
 * @param string $redirectUrl
 */
function requireRole($allowedRoles, $redirectUrl = null) {
    requireAuth();
    
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        if ($redirectUrl === null) {
            $redirectUrl = '/approval-system/dashboard/';
        }
        
        setFlashMessage(MSG_ERROR, 'Access denied. Insufficient permissions.');
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Check if user has permission
 * @param string $permission
 * @return bool
 */
function hasPermission($permission) {
    if (!isAuthenticated()) {
        return false;
    }
    
    $permissions = $_SESSION['permissions'] ?? [];
    return isset($permissions[$permission]) && $permissions[$permission] === true;
}

/**
 * Require specific permission
 * @param string $permission
 * @param string $redirectUrl
 */
function requirePermission($permission, $redirectUrl = null) {
    requireAuth();
    
    if (!hasPermission($permission)) {
        if ($redirectUrl === null) {
            $redirectUrl = '/approval-system/dashboard/';
        }
        
        setFlashMessage(MSG_ERROR, 'Access denied. Required permission: ' . $permission);
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Get current user information
 * @return array
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'business_id' => $_SESSION['business_id'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? '',
        'permissions' => $_SESSION['permissions'] ?? []
    ];
}

/**
 * Set user session data
 * @param array $userData
 */
function setUserSession($userData) {
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['email'] = $userData['email'];
    $_SESSION['role'] = $userData['role_name'];
    $_SESSION['business_id'] = $userData['business_id'];
    $_SESSION['full_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
    $_SESSION['permissions'] = json_decode($userData['permissions'], true) ?: [];
    $_SESSION['last_activity'] = time();
    $_SESSION['created'] = time();
}

/**
 * Destroy session and logout
 */
function destroySession() {
    // Clear all session variables
    $_SESSION = [];
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Check for session hijacking
 * @return bool
 */
function validateSession() {
    // Check IP address (optional - can cause issues with mobile users)
    // if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== getClientIP()) {
    //     return false;
    // }
    
    // Check user agent
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        return false;
    }
    
    return true;
}

/**
 * Set session fingerprint
 */
function setSessionFingerprint() {
    $_SESSION['ip_address'] = getClientIP();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Track login attempts
 * @param string $identifier (username or email)
 * @return bool
 */
function checkLoginAttempts($identifier) {
    $key = 'login_attempts_' . md5($identifier . getClientIP());
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'last_attempt' => 0,
            'locked_until' => 0
        ];
    }
    
    $attempts = $_SESSION[$key];
    
    // Check if still locked
    if ($attempts['locked_until'] > time()) {
        return false;
    }
    
    // Reset if enough time has passed
    if (time() - $attempts['last_attempt'] > 3600) { // 1 hour
        $_SESSION[$key] = [
            'count' => 0,
            'last_attempt' => 0,
            'locked_until' => 0
        ];
    }
    
    return $attempts['count'] < MAX_LOGIN_ATTEMPTS;
}

/**
 * Record failed login attempt
 * @param string $identifier
 */
function recordFailedLogin($identifier) {
    $key = 'login_attempts_' . md5($identifier . getClientIP());
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'last_attempt' => 0,
            'locked_until' => 0
        ];
    }
    
    $_SESSION[$key]['count']++;
    $_SESSION[$key]['last_attempt'] = time();
    
    // Lock account after max attempts
    if ($_SESSION[$key]['count'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION[$key]['locked_until'] = time() + LOCKOUT_DURATION;
    }
}

/**
 * Clear login attempts
 * @param string $identifier
 */
function clearLoginAttempts($identifier) {
    $key = 'login_attempts_' . md5($identifier . getClientIP());
    unset($_SESSION[$key]);
}

/**
 * Get remaining lockout time
 * @param string $identifier
 * @return int
 */
function getRemainingLockoutTime($identifier) {
    $key = 'login_attempts_' . md5($identifier . getClientIP());
    
    if (!isset($_SESSION[$key])) {
        return 0;
    }
    
    $attempts = $_SESSION[$key];
    
    if ($attempts['locked_until'] > time()) {
        return $attempts['locked_until'] - time();
    }
    
    return 0;
}

// Initialize session on file include
initializeSession();
?>