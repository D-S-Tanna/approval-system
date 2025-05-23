<?php
/**
 * Common Header Include
 * File: includes/header.php
 */

// Ensure user is authenticated
requireAuth();

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Get notification count
$db = new Database();
$notificationCount = $db->count('notifications', 
    'user_id = :user_id AND is_read = 0', 
    [':user_id' => $currentUser['id']]
);

// Get pending approvals count for directors
$pendingApprovalsCount = 0;
if ($currentUser['role'] === ROLE_DIRECTOR) {
    $pendingApprovalsCount = $db->count('request_approvals', 
        'approver_id = :approver_id AND status = :status', 
        [':approver_id' => $currentUser['id'], ':status' => APPROVAL_PENDING]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../assets/css/main.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5a6fd8;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        
        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 0.75rem 0;
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            margin: 0 0.25rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            background-color: rgba(255,255,255,0.15);
            color: white !important;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-radius: 8px;
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            transition: background-color 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: var(--light-color);
        }
        
        .badge-notification {
            background-color: var(--danger-color);
            font-size: 0.7rem;
            padding: 0.25rem 0.4rem;
            border-radius: 50%;
            position: absolute;
            top: -5px;
            right: -5px;
        }
        
        .notification-icon {
            position: relative;
            display: inline-block;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
        }
        
        .sidebar {
            background: white;
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            border-radius: 0 15px 15px 0;
        }
        
        .sidebar .nav-link {
            color: var(--dark-color);
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            margin: 0.25rem 0.75rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover {
            background-color: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }
        
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }
        
        .main-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin: 1.5rem 0;
            padding: 0;
            overflow: hidden;
        }
        
        .content-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1.5rem 2rem;
            margin: 0;
        }
        
        .content-header h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 300;
        }
        
        .content-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .content-body {
            padding: 2rem;
        }
        
        .quick-stats {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: var(--dark-color);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }
        
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table th {
            background-color: var(--light-color);
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table td {
            border: none;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 76px;
                left: -100%;
                width: 280px;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard/">
                <i class="fas fa-shield-alt me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentDir === 'dashboard' ? 'active' : ''; ?>" href="../dashboard/">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    
                    <?php if ($currentUser['role'] !== ROLE_ADMIN): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentDir === 'requests' ? 'active' : ''; ?>" href="../requests/">
                            <i class="fas fa-file-invoice-dollar me-1"></i>Requests
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($currentUser['role'] === ROLE_DIRECTOR): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentDir === 'approvals' ? 'active' : ''; ?>" href="../approvals/">
                            <i class="fas fa-check-circle me-1"></i>
                            Approvals
                            <?php if ($pendingApprovalsCount > 0): ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo $pendingApprovalsCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('view_reports')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentDir === 'reports' ? 'active' : ''; ?>" href="../reports/">
                            <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($currentUser['role'] === ROLE_ADMIN): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentDir === 'admin' ? 'active' : ''; ?>" href="../admin/">
                            <i class="fas fa-cogs me-1"></i>Admin
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="notification-icon">
                                <i class="fas fa-bell"></i>
                                <?php if ($notificationCount > 0): ?>
                                    <span class="badge-notification"><?php echo $notificationCount; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                            <li class="dropdown-header">
                                <strong>Notifications</strong>
                                <?php if ($notificationCount > 0): ?>
                                    <span class="badge bg-primary ms-2"><?php echo $notificationCount; ?></span>
                                <?php endif; ?>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Notifications will be loaded via AJAX -->
                            <li id="notification-list">
                                <div class="text-center p-3">
                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-center" href="../notifications/">
                                    <small>View All Notifications</small>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <?php echo e($currentUser['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">
                                <strong><?php echo e($currentUser['full_name']); ?></strong><br>
                                <small class="text-muted"><?php echo ucfirst($currentUser['role']); ?></small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../profile/">
                                    <i class="fas fa-user-circle me-2"></i>My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../profile/notification-settings.php">
                                    <i class="fas fa-cog me-2"></i>Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 px-0">
                <div class="sidebar">
                    <div class="nav flex-column py-3" id="sidebar-nav">
                        <!-- Role-specific navigation will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9">
                <div class="main-content">
                    <!-- Flash Messages -->
                    <?php
                    $flashMessages = getFlashMessages();
                    if (!empty($flashMessages)):
                    ?>
                    <div class="flash-messages">
                        <?php foreach ($flashMessages as $message): ?>
                        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show m-3" role="alert">
                            <?php echo $message['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>