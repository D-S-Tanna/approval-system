<?php
/**
 * Employee Dashboard
 * File: dashboard/employee-dashboard.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Ensure user is employee (or can be any role that needs employee view)
requireAuth();

$pageTitle = 'Employee Dashboard';
$currentUser = getCurrentUser();
$db = new Database();

// Get dashboard statistics
$stats = [];

// Total requests by this employee
$stats['total_requests'] = $db->count('financial_requests', 
    'user_id = :user_id', 
    [':user_id' => $currentUser['id']]
);

// Pending requests
$stats['pending_requests'] = $db->count('financial_requests', 
    'user_id = :user_id AND status = :status', 
    [':user_id' => $currentUser['id'], ':status' => STATUS_PENDING]
);

// Approved requests this month
$stats['monthly_approved'] = $db->count('financial_requests', 
    'user_id = :user_id AND status = :status 
     AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
     AND YEAR(created_at) = YEAR(CURRENT_DATE())',
    [':user_id' => $currentUser['id'], ':status' => STATUS_APPROVED]
);

// Total approved amount this year
$yearlyAmountQuery = "
    SELECT COALESCE(SUM(amount), 0) as total
    FROM financial_requests 
    WHERE user_id = :user_id 
    AND status = :status
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
";
$db->query($yearlyAmountQuery);
$db->bind(':user_id', $currentUser['id']);
$db->bind(':status', STATUS_APPROVED);
$yearlyAmount = $db->single();
$stats['yearly_amount'] = $yearlyAmount['total'] ?? 0;

// Get recent requests
$recentRequestsQuery = "
    SELECT fr.*, rt.type_name, b.name as business_name
    FROM financial_requests fr
    JOIN request_types rt ON fr.request_type_id = rt.id
    JOIN businesses b ON fr.business_id = b.id
    WHERE fr.user_id = :user_id
    ORDER BY fr.created_at DESC
    LIMIT 10
";
$db->query($recentRequestsQuery);
$db->bind(':user_id', $currentUser['id']);
$recentRequests = $db->resultSet();

// Get request status summary
$statusSummaryQuery = "
    SELECT status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount
    FROM financial_requests 
    WHERE user_id = :user_id
    GROUP BY status
";
$db->query($statusSummaryQuery);
$db->bind(':user_id', $currentUser['id']);
$statusSummary = $db->resultSet();

// Get monthly request trends (last 6 months)
$trendsQuery = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount
    FROM financial_requests
    WHERE user_id = :user_id 
    AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
";
$db->query($trendsQuery);
$db->bind(':user_id', $currentUser['id']);
$monthlyTrends = $db->resultSet();

// Get quick actions based on request types
$requestTypes = getRequestTypes();

include '../includes/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-user-circle me-2"></i>Employee Dashboard</h1>
    <p>Welcome back, <?php echo e($currentUser['full_name']); ?>! Manage your financial requests here.</p>
</div>

<div class="content-body">
    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $stats['pending_requests']; ?></div>
                    <div class="stat-label">Pending Requests</div>
                    <div class="mt-2">
                        <a href="../requests/my-requests.php?status=pending" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-clock me-1"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-primary"><?php echo $stats['total_requests']; ?></div>
                    <div class="stat-label">Total Requests</div>
                    <div class="mt-2">
                        <a href="../requests/my-requests.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>View All
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $stats['monthly_approved']; ?></div>
                    <div class="stat-label">Approved This Month</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i><?php echo date('F Y'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo formatCurrency($stats['yearly_amount']); ?></div>
                    <div class="stat-label">Approved This Year</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-chart-line me-1"></i><?php echo date('Y'); ?> Total
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Recent Requests -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt text-primary me-2"></i>Recent Requests
                    </h5>
                    <a href="../requests/my-requests.php" class="btn btn-sm btn-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentRequests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-plus fa-3x text-muted mb-3"></i>
                            <h6>No requests yet</h6>
                            <p class="text-muted mb-3">Start by creating your first financial request.</p>
                            <a href="../requests/create-request.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Request
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Request</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo e($request['title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo e($request['request_number']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo e($request['type_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo formatCurrency($request['amount']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo getStatusBadge($request['status']); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo formatDate($request['created_at']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../requests/view-request.php?id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($request['status'] === STATUS_PENDING): ?>
                                                <a href="../requests/edit-request.php?id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-outline-secondary"
                                                   data-bs-toggle="tooltip" 
                                                   title="Edit Request">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions & Status Summary -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt text-primary me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../requests/create-request.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Request
                        </a>
                        <a href="../requests/my-requests.php?status=pending" class="btn btn-warning">
                            <i class="fas fa-clock me-2"></i>Pending (<?php echo $stats['pending_requests']; ?>)
                        </a>
                        <a href="../requests/my-requests.php" class="btn btn-info">
                            <i class="fas fa-list me-2"></i>All My Requests
                        </a>
                        <a href="../profile/view-profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user me-2"></i>My Profile
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Request Templates -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-magic text-success me-2"></i>Quick Request Templates
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php foreach (array_slice($requestTypes, 0, 4) as $type): ?>
                        <a href="../requests/create-request.php?type=<?php echo $type['id']; ?>" 
                           class="btn btn-outline-success btn-sm">
                            <i class="fas fa-plus me-2"></i><?php echo e($type['type_name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Status Summary -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie text-info me-2"></i>Request Summary
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($statusSummary)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No requests to summarize</p>
                        </div>
                    <?php else: ?>
                        <div class="status-summary">
                            <?php foreach ($statusSummary as $status): ?>
                            <div class="status-item d-flex justify-content-between align-items-center mb-2">
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
    </div>
    
    <!-- Monthly Trends Chart -->
    <?php if (!empty($monthlyTrends)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-primary me-2"></i>My Request Trends (Last 6 Months)
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="trendsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tips & Guidelines -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb text-warning me-2"></i>Tips for Faster Approvals
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="tip-card text-center p-3 bg-light rounded">
                                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                                <h6>Provide Details</h6>
                                <p class="small text-muted mb-0">Include clear descriptions and all necessary documentation</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="tip-card text-center p-3 bg-light rounded">
                                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                <h6>Set Urgency</h6>
                                <p class="small text-muted mb-0">Mark urgent requests appropriately to expedite approval</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="tip-card text-center p-3 bg-light rounded">
                                <i class="fas fa-check-double fa-2x text-success mb-2"></i>
                                <h6>Follow Up</h6>
                                <p class="small text-muted mb-0">Check status regularly and respond to requests for clarification</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tip-card {
    transition: transform 0.2s ease;
    border: 1px solid transparent;
}

.tip-card:hover {
    transform: translateY(-2px);
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.status-item {
    padding: 0.5rem;
    border-radius: 6px;
    background: rgba(0,0,0,0.02);
}

.status-summary {
    max-height: 300px;
    overflow-y: auto;
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
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($monthlyTrends)): ?>
// Initialize trends chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('trendsChart').getContext('2d');
    
    const trendsData = <?php echo json_encode($monthlyTrends); ?>;
    const labels = trendsData.map(item => {
        const date = new Date(item.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    const totalRequests = trendsData.map(item => parseInt(item.total_requests));
    const approvedCounts = trendsData.map(item => parseInt(item.approved_count));
    const amounts = trendsData.map(item => parseFloat(item.approved_amount));
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Requests',
                data: totalRequests,
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                yAxisID: 'y'
            }, {
                label: 'Approved Requests',
                data: approvedCounts,
                borderColor: 'rgb(40, 167, 69)',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                yAxisID: 'y'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Requests'
                    },
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        afterBody: function(context) {
                            if (context.length > 0) {
                                const index = context[0].dataIndex;
                                const amount = amounts[index];
                                return 'Approved Amount: ' + new Intl.NumberFormat('en-US', {
                                    style: 'currency',
                                    currency: 'USD'
                                }).format(amount);
                            }
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
});
<?php endif; ?>

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add click tracking for quick actions
    document.querySelectorAll('[href*="create-request"]').forEach(link => {
        link.addEventListener('click', function() {
            // Track usage analytics if needed
            console.log('Quick action used:', this.textContent.trim());
        });
    });
});

// Refresh stats every 5 minutes
setInterval(function() {
    // Only refresh if user is still active
    if (document.hasFocus()) {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                // Update notification count and stats if needed
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Update notification badge
                const currentBadge = document.querySelector('.badge-notification');
                const newBadge = doc.querySelector('.badge-notification');
                
                if (currentBadge && newBadge) {
                    currentBadge.textContent = newBadge.textContent;
                } else if (!currentBadge && newBadge) {
                    // Add badge if new notifications
                    location.reload();
                }
            })
            .catch(error => {
                console.log('Background refresh failed:', error);
            });
    }
}, 300000); // 5 minutes
</script>

<?php include '../includes/footer.php'; ?>