<?php
/**
 * System Constants
 * File: config/constants.php
 */

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_DIRECTOR', 'director');
define('ROLE_ACCOUNTANT', 'accountant');
define('ROLE_EMPLOYEE', 'employee');

// Request statuses
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_PROCESSING', 'processing');

// Approval statuses
define('APPROVAL_PENDING', 'pending');
define('APPROVAL_APPROVED', 'approved');
define('APPROVAL_REJECTED', 'rejected');

// Request urgency levels
define('URGENCY_LOW', 'low');
define('URGENCY_MEDIUM', 'medium');
define('URGENCY_HIGH', 'high');
define('URGENCY_CRITICAL', 'critical');

// Workflow types
define('WORKFLOW_PARALLEL', 'parallel');
define('WORKFLOW_SEQUENTIAL', 'sequential');

// Notification types
define('NOTIFICATION_REQUEST_SUBMITTED', 'request_submitted');
define('NOTIFICATION_APPROVAL_NEEDED', 'approval_needed');
define('NOTIFICATION_REQUEST_APPROVED', 'request_approved');
define('NOTIFICATION_REQUEST_REJECTED', 'request_rejected');
define('NOTIFICATION_SYSTEM_ALERT', 'system_alert');

// Notification methods
define('NOTIFY_SYSTEM', 'system');
define('NOTIFY_EMAIL', 'email');
define('NOTIFY_SMS', 'sms');

// File types
define('FILE_TYPE_DOCUMENT', 'document');
define('FILE_TYPE_RECEIPT', 'receipt');
define('FILE_TYPE_IMAGE', 'image');

// Audit actions
define('AUDIT_CREATE', 'create');
define('AUDIT_UPDATE', 'update');
define('AUDIT_DELETE', 'delete');
define('AUDIT_LOGIN', 'login');
define('AUDIT_LOGOUT', 'logout');
define('AUDIT_APPROVE', 'approve');
define('AUDIT_REJECT', 'reject');

// HTTP Status codes
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_INTERNAL_ERROR', 500);

// Permissions
define('PERMISSION_VIEW_ALL_REQUESTS', 'view_all_requests');
define('PERMISSION_APPROVE_REQUESTS', 'approve_requests');
define('PERMISSION_MANAGE_USERS', 'manage_users');
define('PERMISSION_SYSTEM_ADMIN', 'system_admin');
define('PERMISSION_VIEW_REPORTS', 'view_reports');
define('PERMISSION_EXPORT_DATA', 'export_data');

// Currency symbols
$CURRENCY_SYMBOLS = [
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'JPY' => '¥',
    'CAD' => 'C$',
    'AUD' => 'A$',
    'UGX' => 'USh'
];

// Urgency colors for UI
$URGENCY_COLORS = [
    URGENCY_LOW => '#28a745',      // Green
    URGENCY_MEDIUM => '#ffc107',   // Yellow
    URGENCY_HIGH => '#fd7e14',     // Orange
    URGENCY_CRITICAL => '#dc3545'  // Red
];

// Status colors for UI
$STATUS_COLORS = [
    STATUS_PENDING => '#6c757d',     // Gray
    STATUS_APPROVED => '#28a745',    // Green
    STATUS_REJECTED => '#dc3545',    // Red
    STATUS_CANCELLED => '#6f42c1',   // Purple
    STATUS_PROCESSING => '#007bff'   // Blue
];

// Request type icons (for UI)
$REQUEST_TYPE_ICONS = [
    'Cash Advance' => 'fas fa-money-bill-wave',
    'Equipment Purchase' => 'fas fa-laptop',
    'Travel Expenses' => 'fas fa-plane',
    'Office Supplies' => 'fas fa-box',
    'Professional Services' => 'fas fa-handshake',
    'Marketing Expenses' => 'fas fa-bullhorn',
    'Maintenance & Repairs' => 'fas fa-tools',
    'Emergency Expenses' => 'fas fa-exclamation-triangle'
];

// Default pagination settings
define('DEFAULT_ITEMS_PER_PAGE', 25);
define('MAX_ITEMS_PER_PAGE', 100);

// Date ranges for reports
$REPORT_DATE_RANGES = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'this_week' => 'This Week',
    'last_week' => 'Last Week',
    'this_month' => 'This Month',
    'last_month' => 'Last Month',
    'this_quarter' => 'This Quarter',
    'last_quarter' => 'Last Quarter',
    'this_year' => 'This Year',
    'last_year' => 'Last Year',
    'custom' => 'Custom Range'
];

// Export formats
$EXPORT_FORMATS = [
    'pdf' => 'PDF',
    'excel' => 'Excel',
    'csv' => 'CSV'
];

// Maximum values
define('MAX_REQUEST_AMOUNT', 999999.99);
define('MAX_UPLOAD_FILES', 10);
define('MAX_COMMENT_LENGTH', 1000);
define('MAX_DESCRIPTION_LENGTH', 2000);

// System messages
define('MSG_SUCCESS', 'success');
define('MSG_ERROR', 'error');
define('MSG_WARNING', 'warning');
define('MSG_INFO', 'info');

// Cache keys
define('CACHE_USER_PERMISSIONS', 'user_permissions_');
define('CACHE_SYSTEM_SETTINGS', 'system_settings');
define('CACHE_BUSINESS_LIST', 'business_list');
define('CACHE_REQUEST_TYPES', 'request_types');

// Regular expressions for validation
define('REGEX_EMAIL', '/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
define('REGEX_PHONE', '/^[\+]?[1-9][\d]{0,15}$/');
define('REGEX_USERNAME', '/^[a-zA-Z0-9_]{3,20}$/');
define('REGEX_CURRENCY', '/^\d+(\.\d{1,2})?$/');
?>