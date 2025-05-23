<?php
/**
 * Main Dashboard
 * File: dashboard/index.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

$pageTitle = 'Dashboard';
$currentUser = getCurrentUser();

// Redirect to role-specific dashboard
switch ($currentUser['role']) {
    case ROLE_ADMIN:
        header('Location: ../admin/');
        exit();
    case ROLE_DIRECTOR:
        header('Location: director-dashboard.php');
        exit();
    case ROLE_ACCOUNTANT:
        header('Location: accountant-dashboard.php');
        exit();
    case ROLE_EMPLOYEE:
    default:
        header('Location: employee-dashboard.php');
        exit();
}
?>