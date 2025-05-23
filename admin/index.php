<?php
/**
 * Admin Dashboard
 * File: admin/index.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Ensure user is admin
requireRole([ROLE_ADMIN]);

$pageTitle = 'Admin Dashboard';
$currentUser = getCurrentUser();
$db = new Database();

// Get system statistics
$stats = [];

// Total users
$stats['total_users'] = $db->count('users', 'is_active = 1');

// Total businesses
$stats['total_businesses'] = $db->count('businesses', 'is_active = 1');

// Total requests this month
$stats['monthly_requests'] = $db->count('financial_requests', 
    'MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())'
);

// Total approved amount this month
$monthlyAmountQuery = "
    SELECT COALESCE(SUM(amount), 0) as total
    FROM financial_requests 
    WHERE status = 'approved'
    AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
";
$db->query($monthlyAmountQuery);
$monthlyAmount = $db->single();
$stats['monthly_amount'] = $monthlyAmount['total'] ?? 0;

// Pending requests across all businesses
$stats['pending_requests'] = $db->count('financial_requests', 'status = :status', [':status' => STATUS_PENDING]);

// System health metrics
$stats['active_sessions'] = $db->count('users', 'last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)');

// Get recent activity
$recentActivityQuery = "
    SELECT al.*, u.first_name, u.last_name, u.username
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
";
$db->query($recentActivityQuery);
$recentActivity = $db->resultSet();

// Get user distribution by role
$userRolesQuery = "
    SELECT ur.role_name, COUNT(u.id) as count
    FROM user_roles ur
    LEFT JOIN users u ON ur.id = u.role_id AND u.is_active = 1
    GROUP BY ur.id, ur.role_name
    ORDER BY count DESC
";
$db->query($userRolesQuery);
$userRoles = $db->resultSet();

// Get request statistics by status
$requestStatusQuery = "
    SELECT status, COUNT(*) as count, SUM(amount) as total_amount
    FROM financial_requests 
    WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY status
";
$db->query($requestStatusQuery);
$requestStatus = $db->resultSet();

// Get top requesters
$topRequestersQuery = "
    SELECT u.first_name, u.last_name, u.email, b.name as business_name,
           COUNT(fr.id) as request_count, SUM(fr.amount) as total_amount
    FROM users u
    JOIN financial_requests fr ON u.id = fr.user_id
    LEFT JOIN businesses b ON u.business_id = b.id
    WHERE fr.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY u.id
    ORDER BY request_count DESC
    LIMIT 5
";
$db->query($topRequestersQuery);
$topRequesters = $db->resultSet();

// Get system alerts
$systemAlerts = [];

// Check for requests older than 72 hours without decision
$oldRequestsQuery = "
    SELECT COUNT(*) as count
    FROM financial_requests 
    WHERE status = 'pending' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 72 HOUR)
";
$db->query($oldRequestsQuery);
$oldRequests = $db->single();
if ($oldRequests['count'] > 0) {
    $systemAlerts[] = [
        'type' => 'warning',
        'message' => "{$oldRequests['count']} requests pending for more than 72 hours",
        'action' => '../requests/all-requests.php?status=pending'
    ];
}

// Check for failed login attempts
$failedLoginsQuery = "
    SELECT COUNT(*) as count
    FROM audit_logs 
    WHERE action = 'login' 
    AND table_name = 'users'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND old_values LIKE '%failed%'
";
$db->query($failedLoginsQuery);
$failedLogins = $db->single();
if ($failedLogins['count'] > 10) {
    $systemAlerts[] = [
        'type' => 'danger',
        'message' => "{$failedLogins['count']} failed login attempts in the last 24 hours",
        'action' => 'audit-logs.php?filter=login'
    ];
}

// Check for inactive directors
$inactiveDirectorsQuery = "
    SELECT COUNT(*) as count
    FROM users u
    JOIN user_roles ur ON u.role_id = ur.id
    WHERE ur.role_name = 'director'
    AND u.is_active = 1
    AND (u.last_login IS NULL OR u.last_login < DATE_SUB(NOW(), INTERVAL 7 DAY))
";
$db->query($inactiveDirectorsQuery);
$inactiveDirectors = $db->single();
if ($inactiveDirectors['count'] > 0) {
    $systemAlerts[] = [
        'type' => 'info',
        'message' => "{$inactiveDirectors['count']} directors haven't logged in this week",
        'action' => 'manage-users.php?role=director'
    ];
}

include '../includes/header.php';
?>

<div class="content-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-crown me-2"></i>Admin Dashboard</h1>
            <p class="mb-0">System overview and administrative controls</p>
        </div>
        <div class="btn-group">
            <a href="manage-users.php" class="btn btn-primary">
                <i class="fas fa-users me-2"></i>Manage Users
            </a>
            <a href="system-settings.php" class="btn btn-outline-secondary">
                <i class="fas fa-cogs me-2"></i>Settings
            </a>
        </div>
    </div>
</div>

<div class="content-body">
    <!-- System Alerts -->
    <?php if (!empty($systemAlerts)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning bg-opacity-10">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>System Alerts
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($systemAlerts as $alert): ?>
                    <div class="alert alert-<?php echo $alert['type']; ?> d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo $alert['message']; ?></span>
                        <?php if (isset($alert['action'])): ?>
                        <a href="<?php echo $alert['action']; ?>" class="btn btn-sm btn-outline-<?php echo $alert['type']; ?>">
                            View
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body text-center">
                    <div class="h2 text-primary mb-1"><?php echo $stats['total_users']; ?></div>
                    <div class="small text-muted">Active Users</div>
                    <div class="mt-2">
                        <a href="manage-users.php" class="btn btn-sm btn-outline-primary">Manage</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <div class="h2 text-success mb-1"><?php echo $stats['total_businesses']; ?></div>
                    <div class="small text-muted">Businesses</div>
                    <div class="mt-2">
                        <a href="manage-businesses.php" class="btn btn-sm btn-outline-success">Manage</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <div class="h2 text-info mb-1"><?php echo $stats['monthly_requests']; ?></div>
                    <div class="small text-muted">Monthly Requests</div>
                    <div class="mt-2">
                        <a href="../requests/all-requests.php" class="btn btn-sm btn-outline-info">View</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body text-center">
                    <div class="h2 text-warning mb-1"><?php echo $stats['pending_requests']; ?></div>
                    <div class="small text-muted">Pending Requests</div>
                    <div class="mt-2">
                        <a href="../requests/all-requests.php?status=pending" class="btn btn-sm btn-outline-warning">Review</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-secondary bg-opacity-10 border-secondary">
                <div class="card-body text-center">
                    <div class="h2 text-secondary mb-1"><?php echo $stats['active_sessions']; ?></div>
                    <div class="small text-muted">Active Sessions</div>
                    <div class="mt-2">
                        <small class="text-muted">Last 24h</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark bg-opacity-10 border-dark">
                <div class="card-body text-center">
                    <div class="h2 text-dark mb-1"><?php echo formatCurrency($stats['monthly_amount']); ?></div>
                    <div class="small text-muted">Monthly Volume</div>
                    <div class="mt-2">
                        <a href="../reports/" class="btn btn-sm btn-outline-dark">Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Dashboard Content -->
    <div class="row">
        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-info me-2"></i>Recent System Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentActivity)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-history fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No recent activity</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($recentActivity as $activity): ?>
                            <div class="activity-item d-flex mb-3">
                                <div class="activity-marker me-3">
                                    <i class="fas fa-<?php echo getActivityIcon($activity['action']); ?> text-<?php echo getActivityColor($activity['action']); ?>"></i>
                                </div>
                                <div class="activity-content flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo ucfirst($activity['action']); ?></strong>
                                        <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                    </div>
                                    <div class="small text-muted">
                                        <?php if ($activity['username']): ?>
                                            <?php echo e($activity['first_name'] . ' ' . $activity['last_name']); ?> (<?php echo e($activity['username']); ?>)
                                        <?php else: ?>
                                            System
                                        <?php endif; ?>
                                        • <?php echo e($activity['table_name']); ?> #<?php echo $activity['record_id']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="audit-logs.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-list me-1"></i>View All Logs
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- User Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users text-primary me-2"></i>User Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="userRolesChart" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($userRoles as $role): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold"><?php echo ucfirst($role['role_name']); ?>s</span>
                            <span class="badge bg-primary"><?php echo $role['count']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Request Status Overview -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie text-success me-2"></i>Request Status (Last 30 Days)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($requestStatus)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No requests in the last 30 days</p>
                        </div>
                    <?php else: ?>
                        <canvas id="requestStatusChart" height="150"></canvas>
                        <div class="mt-3">
                            <?php foreach ($requestStatus as $status): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <?php echo getStatusBadge($status['status']); ?>
                                </div>
                                <div class="text-end">
                                    <div><strong><?php echo $status['count']; ?></strong> requests</div>
                                    <small class="text-muted"><?php echo formatCurrency($status['total_amount']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Requesters -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy text-warning me-2"></i>Top Requesters (Last 30 Days)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topRequesters)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-friends fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No requests in the last 30 days</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topRequesters as $index => $requester): ?>
                        <div class="requester-item d-flex align-items-center mb-3 p-2 rounded bg-light">
                            <div class="requester-rank me-3">
                                <span class="badge bg-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'light text-dark'); ?> rounded-circle">
                                    <?php echo $index + 1; ?>
                                </span>
                            </div>
                            <div class="requester-info flex-grow-1">
                                <div class="fw-bold"><?php echo e($requester['first_name'] . ' ' . $requester['last_name']); ?></div>
                                <div class="small text-muted"><?php echo e($requester['business_name']); ?></div>
                            </div>
                            <div class="requester-stats text-end">
                                <div class="fw-bold"><?php echo $requester['request_count']; ?> requests</div>
                                <div class="small text-muted"><?php echo formatCurrency($requester['total_amount']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt text-primary me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="d-grid">
                                <a href="add-user.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Add New User
                                </a>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="d-grid">
                                <a href="add-business.php" class="btn btn-success">
                                    <i class="fas fa-building me-2"></i>Add New Business
                                </a>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="d-grid">
                                <a href="../requests/all-requests.php" class="btn btn-info">
                                    <i class="fas fa-list me-2"></i>View All Requests
                                </a>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="d-grid">
                                <a href="../reports/" class="btn btn-warning">
                                    <i class="fas fa-chart-bar me-2"></i>Generate Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.activity-timeline {
    max-height: 400px;
    overflow-y: auto;
}

.activity-marker {
    width: 30px;
    text-align: center;
    padding-top: 2px;
}

.activity-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

.requester-item {
    transition: all 0.2s ease;
}

.requester-item:hover {
    background-color: rgba(13, 110, 253, 0.1) !important;
    transform: translateY(-1px);
}

.requester-rank {
    font-size: 1.2rem;
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border: none;
}

.quick-stats:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .col-lg-2 {
        min-width: 50%;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Helper functions for activity display
<?php
function getActivityIcon($action) {
    $icons = [
        'create' => 'plus',
        'update' => 'edit',
        'delete' => 'trash',
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'approve' => 'check',
        'reject' => 'times'
    ];
    return $icons[$action] ?? 'info';
}

function getActivityColor($action) {
    $colors = [
        'create' => 'success',
        'update' => 'info',
        'delete' => 'danger',
        'login' => 'primary',
        'logout' => 'secondary',
        'approve' => 'success',
        'reject' => 'danger'
    ];
    return $colors[$action] ?? 'primary';
}
?>

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // User Roles Pie Chart
    <?php if (!empty($userRoles)): ?>
    const userRolesCtx = document.getElementById('userRolesChart').getContext('2d');
    new Chart(userRolesCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($userRoles, 'role_name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($userRoles, 'count')); ?>,
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#28a745',
                    '#ffc107',
                    '#dc3545'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    <?php endif; ?>
    
    // Request Status Chart
    <?php if (!empty($requestStatus)): ?>
    const requestStatusCtx = document.getElementById('requestStatusChart').getContext('2d');
    new Chart(requestStatusCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($requestStatus, 'status')); ?>,
            datasets: [{
                label: 'Number of Requests',
                data: <?php echo json_encode(array_column($requestStatus, 'count')); ?>,
                backgroundColor: [
                    '#6c757d', // pending
                    '#28a745', // approved
                    '#dc3545', // rejected
                    '#6f42c1', // cancelled
                    '#007bff'  // processing
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
    
    // Auto-refresh every 5 minutes
    setInterval(function() {
        if (document.hasFocus()) {
            fetch('../api/admin.php?action=dashboard_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stats cards
                        updateStatsCards(data.stats);
                    }
                })
                .catch(error => {
                    console.log('Stats refresh failed:', error);
                });
        }
    }, 300000); // 5 minutes
});

function updateStatsCards(stats) {
    // Update individual stat cards
    const statCards = document.querySelectorAll('.card .h2');
    if (statCards[0]) statCards[0].textContent = stats.total_users;
    if (statCards[1]) statCards[1].textContent = stats.total_businesses;
    if (statCards[2]) statCards[2].textContent = stats.monthly_requests;
    if (statCards[3]) statCards[3].textContent = stats.pending_requests;
    if (statCards[4]) statCards[4].textContent = stats.active_sessions;
    if (statCards[5]) statCards[5].textContent = formatCurrency(stats.monthly_amount);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}
</script>

<?php include '../includes/footer.php'; ?>