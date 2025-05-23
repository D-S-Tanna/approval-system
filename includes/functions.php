<?php
/**
 * Utility Functions
 * File: includes/functions.php
 */

/**
 * Sanitize input data
 * @param mixed $data
 * @return mixed
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 * @param string $phone
 * @return bool
 */
function validatePhone($phone) {
    return preg_match(REGEX_PHONE, $phone);
}

/**
 * Format currency amount
 * @param float $amount
 * @param string $currency
 * @return string
 */
function formatCurrency($amount, $currency = 'USD') {
    global $CURRENCY_SYMBOLS;
    $symbol = $CURRENCY_SYMBOLS[$currency] ?? $currency;
    return $symbol . number_format($amount, 2);
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '-';
    }
    
    return date($format, strtotime($date));
}

/**
 * Generate unique request number
 * @param string $businessCode
 * @return string
 */
function generateRequestNumber($businessCode = 'REQ') {
    return $businessCode . '-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Get status badge HTML
 * @param string $status
 * @return string
 */
function getStatusBadge($status) {
    global $STATUS_COLORS;
    $color = $STATUS_COLORS[$status] ?? '#6c757d';
    $statusText = ucfirst(str_replace('_', ' ', $status));
    
    return "<span class='badge' style='background-color: {$color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;'>{$statusText}</span>";
}

/**
 * Get urgency badge HTML
 * @param string $urgency
 * @return string
 */
function getUrgencyBadge($urgency) {
    global $URGENCY_COLORS;
    $color = $URGENCY_COLORS[$urgency] ?? '#6c757d';
    $urgencyText = ucfirst($urgency);
    
    return "<span class='badge' style='background-color: {$color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;'>{$urgencyText}</span>";
}

/**
 * Check if user is logged in, redirect if not
 * @param string $redirectTo
 */
function requireLogin($redirectTo = '/auth/login.php') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: {$redirectTo}");
        exit();
    }
}

/**
 * Check if user has required role
 * @param array $allowedRoles
 * @param string $redirectTo
 */
/*
function requireRole($allowedRoles, $redirectTo = '/dashboard/') {
    requireLogin();
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        header("Location: {$redirectTo}");
        exit();
    }
}
*/

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check if token is expired
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message
 * @param string $type
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash messages
 * @return array
 */
function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Redirect with message
 * @param string $url
 * @param string $type
 * @param string $message
 */
function redirectWithMessage($url, $type, $message) {
    setFlashMessage($type, $message);
    header("Location: {$url}");
    exit();
}

/**
 * Upload file
 * @param array $file
 * @param string $uploadDir
 * @param array $allowedTypes
 * @return array
 */
function uploadFile($file, $uploadDir, $allowedTypes = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    $allowedTypes = $allowedTypes ?? ALLOWED_FILE_TYPES;
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'File size too large'];
    }
    
    $fileName = uniqid() . '_' . sanitize($file['name']);
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return [
            'success' => true,
            'filename' => $fileName,
            'path' => $uploadPath,
            'size' => $file['size'],
            'type' => $file['type']
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Generate pagination HTML
 * @param int $currentPage
 * @param int $totalPages
 * @param string $baseUrl
 * @return string
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    
    $html = '<nav><ul class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Log system activity
 * @param string $message
 * @param string $level
 * @param array $context
 */
function logActivity($message, $level = 'INFO', $context = []) {
    $logFile = LOG_PATH . 'system_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logEntry = "[{$timestamp}] {$level}: User {$userId} ({$ip}) - {$message} {$contextStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Send email notification
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param array $headers
 * @return bool
 */
function sendEmail($to, $subject, $message, $headers = []) {
    $defaultHeaders = [
        'From' => MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
        'Reply-To' => MAIL_FROM_EMAIL,
        'X-Mailer' => APP_NAME,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    $headerString = '';
    
    foreach ($allHeaders as $key => $value) {
        $headerString .= $key . ': ' . $value . "\r\n";
    }
    
    return mail($to, $subject, $message, $headerString);
}

/**
 * Get business list
 * @return array
 */
function getBusinessList() {
    static $businesses = null;
    
    if ($businesses === null) {
        $db = new Database();
        $businesses = $db->select('businesses', 'is_active = 1', [], 'name ASC');
    }
    
    return $businesses;
}

/**
 * Get request types
 * @return array
 */
function getRequestTypes() {
    static $requestTypes = null;
    
    if ($requestTypes === null) {
        $db = new Database();
        $requestTypes = $db->select('request_types', 'is_active = 1', [], 'type_name ASC');
    }
    
    return $requestTypes;
}

/**
 * Get user roles
 * @return array
 */
function getUserRoles() {
    static $roles = null;
    
    if ($roles === null) {
        $db = new Database();
        $roles = $db->select('user_roles', '1=1', [], 'role_name ASC');
    }
    
    return $roles;
}

/**
 * Calculate time ago
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

/**
 * Validate password strength
 * @param string $password
 * @return array
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Clean old files
 * @param string $directory
 * @param int $maxAge (in seconds)
 */
function cleanOldFiles($directory, $maxAge = 86400) {
    if (!is_dir($directory)) return;
    
    $files = glob($directory . '*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
            unlink($file);
        }
    }
}

/**
 * Format file size
 * @param int $size
 * @return string
 */
function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unit = 0;
    
    while ($size > 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    
    return round($size, 2) . ' ' . $units[$unit];
}

/**
 * Generate secure random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if request is AJAX
 * @return bool
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP address
 * @return string
 */
function getClientIP() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

/**
 * Escape HTML output
 * @param string $text
 * @return string
 */
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Get configuration value
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function config($key, $default = null) {
    return getAppSetting($key, $default);
}
?>