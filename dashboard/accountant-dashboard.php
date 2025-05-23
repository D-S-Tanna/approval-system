<?php
/**
 * Accountant Dashboard
 * File: dashboard/accountant-dashboard.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Ensure user is accountant
requireRole([ROLE_ACCOUNTANT]);

$pageTitle = 'Accountant Dashboard';
$currentUser = getCurrentUser();
$db = new Database();

// Get dashboard statistics
$stats = [];

// Requests for processing (approved but not processed)
$stats['to_process'] = $db->count('financial_requests', 
    'status = :status AND (business_id = :business_id OR :business_id IS NULL)', 
    [':status' => STATUS_APPROVED, ':business_id' => $currentUser['business_id']]
);

// Total requests this month for business
$stats['monthly_requests'] = $db->count('financial_requests', 
    'MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
     AND (business_id = :business_id OR :business_id IS NULL)',
    [':business_id' => $currentUser['business_id']]
);

// Total approved amount this month for business
$monthlyAmountQuery = "
    SELECT COALESCE(SUM(amount), 0) as total
    FROM financial_requests 
    WHERE status = :status 
    AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
    AND (business_id = :business_id OR :business_id IS NULL)
";
$db->query($monthlyAmountQuery);
$db->bind(':status', STATUS_APPROVED);
$db->bind(':business_id', $currentUser['business_id']);
$monthlyAmount = $db->single();
$stats['monthly_amount'] = $monthlyAmount['total'] ?? 0;

// Pending approvals count for business
$stats['pending_approvals'] = $db->count('financial_requests', 
    'status = :status AND (business_id = :business_id OR :business_id IS NULL)', 
    [':status' => STATUS_PENDING, ':business_id' => $currentUser['business_id']]
);

// Get recent approved requests to process
$toProcessQuery = "
    SELECT fr.*, rt.type_name, u.first_name, u.last_name, b.name as business_name
    FROM financial_requests fr
    JOIN request_types rt ON fr.request_type_id = rt.id
    JOIN users u ON fr.user_id = u.id
    JOIN businesses b ON fr.business_id = b.id
    WHERE fr.status = :status 
    AND (fr.business_id = :business_id OR :business_id IS NULL)
    ORDER BY fr.final_decision_date ASC
    LIMIT 10
";
$db->query($toProcessQuery);
$db->bind(':status', STATUS_APPROVED);
$db->bind(':business_id', $currentUser['business_id']);
$toProcess = $db->resultSet();

// Get recent activity for business
$recentActivityQuery = "
    SELECT fr.*, rt.type_name, u.first_name, u.last_name, b.name as business_name
    FROM financial_requests fr
    JOIN request_types rt ON fr.request_type_id = rt.id
    JOIN users u ON fr.user_id = u.id
    JOIN businesses b ON fr.business_id = b.id
    WHERE (fr.business_id = :business_id OR :business_id IS NULL)
    ORDER BY fr.updated_at DESC
    LIMIT 8
";
$db->query($recentActivityQuery);
$db->bind(':business_id', $currentUser['business_id']);
$recentActivity = $db->resultSet();

// Get expense breakdown by type for current month
$expenseBreakdownQuery = "
    SELECT rt.type_name, COUNT(*) as count, SUM(fr.amount) as total_amount
    FROM financial_requests fr
    JOIN request_types rt ON fr.request_type_id = rt.id
    WHERE fr.status = :status
    AND MONTH(fr.created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(fr.created_at) = YEAR(CURRENT_DATE())
    AND (fr.business_id = :business_id OR :business_id IS NULL)
    GROUP BY rt.id, rt.type_name
    ORDER BY total_amount DESC
";
$db->query($expenseBreakdownQuery);
$db->bind(':status', STATUS_APPROVED);
$db->bind(':business_id', $currentUser['business_id']);
$expenseBreakdown = $db->resultSet();

// Get monthly trends for business
$trendsQuery = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM financial_requests
    WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    AND (business_id = :business_id OR :business_id IS NULL)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
";
$db->query($trendsQuery);
$db->bind(':business_id', $currentUser['business_id']);
$monthlyTrends = $db->resultSet();

include '../includes/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-calculator me-2"></i>Accountant Dashboard</h1>
    <p>Welcome back, <?php echo e($currentUser['full_name']); ?>! Monitor and process financial requests for your business.</p>
</div>

<div class="content-body">
    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $stats['to_process']; ?></div>
                    <div class="stat-label">To Process</div>
                    <div class="mt-2">
                        <a href="../requests/all-requests.php?status=approved" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-cogs me-1"></i>Process Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $stats['pending_approvals']; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                    <div class="mt-2">
                        <a href="../requests/all-requests.php?status=pending" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-clock me-1"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo $stats['monthly_requests']; ?></div>
                    <div class="stat-label">Requests This Month</div>
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
                    <div class="stat-number text-primary"><?php echo formatCurrency($stats['monthly_amount']); ?></div>
                    <div class="stat-label">Monthly Approved</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-chart-line me-1"></i>Total Amount
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Requests to Process -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-cogs text-success me-2"></i>Approved Requests to Process
                    </h5>
                    <a href="../requests/all-requests.php?status=approved" class="btn btn-sm btn-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($toProcess)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h6>All processed!</h6>
                            <p class="text-muted mb-0">No approved requests need processing at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Request</th>
                                        <th>Employee</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Approved</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($toProcess as $request): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo e($request['title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo e($request['request_number']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo e($request['first_name'] . ' ' . $request['last_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo e($request['business_name']); ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-success"><?php echo formatCurrency($request['amount']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo e($request['type_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo timeAgo($request['final_decision_date']); ?>
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
                                                <button class="btn btn-outline-success" 
                                                        onclick="markAsProcessed(<?php echo $request['id']; ?>)"
                                                        data-bs-toggle="tooltip" 
                                                        title="Mark as Processed">
                                                    <i class="fas fa-check"></i>
                                                </button>
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
        
        <!-- Quick Actions & Expense Breakdown -->
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
                        <a href="../requests/all-requests.php?status=approved" class="btn btn-success">
                            <i class="fas fa-cogs me-2"></i>Process Approved (<?php echo $stats['to_process']; ?>)
                        </a>
                        <a href="../requests/create-request.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Request
                        </a>
                        <a href="../reports/business-report.php" class="btn btn-info">
                            <i class="fas fa-chart-bar me-2"></i>Business Reports
                        </a>
                        <a href="../requests/all-requests.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>All Requests
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Expense Breakdown -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie text-info me-2"></i>Monthly Expense Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($expenseBreakdown)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No expenses this month</p>
                        </div>
                    <?php else: ?>
                        <div class="expense-breakdown">
                            <?php foreach ($expenseBreakdown as $expense): ?>
                            <div class="expense-item d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-bold"><?php echo e($expense['type_name']); ?></div>
                                    <small class="text-muted"><?php echo $expense['count']; ?> requests</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-primary"><?php echo formatCurrency($expense['total_amount']); ?></div>
                                    <div class="progress" style="width: 100px; height: 4px;">
                                        <div class="progress-bar" 
                                             style="width: <?php echo min(100, ($expense['total_amount'] / $stats['monthly_amount']) * 100); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity & Trends -->
    <div class="row mt-4">
        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-info me-2"></i>Recent Activity
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
                                    <i class="fas fa-<?php echo $activity['status'] === STATUS_APPROVED ? 'check-circle text-success' : ($activity['status'] === STATUS_REJECTED ? 'times-circle text-danger' : 'clock text-warning'); ?>"></i>
                                </div>
                                <div class="activity-content flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo e($activity['title']); ?></strong>
                                        <small class="text-muted"><?php echo timeAgo($activity['updated_at']); ?></small>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo e($activity['first_name'] . ' ' . $activity['last_name']); ?> - 
                                        <?php echo formatCurrency($activity['amount']); ?> - 
                                        <?php echo getStatusBadge($activity['status']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Monthly Trends Chart -->
        <?php if (!empty($monthlyTrends)): ?>
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-primary me-2"></i>Request Trends (6 Months)
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="trendsChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Business Summary Cards -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-building text-primary me-2"></i>Business Financial Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="summary-card text-center p-3 bg-primary bg-opacity-10 rounded">
                                <div class="h4 text-primary mb-1"><?php echo formatCurrency($stats['monthly_amount']); ?></div>
                                <div class="small text-muted">Monthly Approved</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="summary-card text-center p-3 bg-success bg-opacity-10 rounded">
                                <div class="h4 text-success mb-1"><?php echo $stats['to_process']; ?></div>
                                <div class="small text-muted">Ready to Process</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="summary-card text-center p-3 bg-warning bg-opacity-10 rounded">
                                <div class="h4 text-warning mb-1"><?php echo $stats['pending_approvals']; ?></div>
                                <div class="small text-muted">Pending Approval</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="summary-card text-center p-3 bg-info bg-opacity-10 rounded">
                                <div class="h4 text-info mb-1"><?php echo $stats['monthly_requests']; ?></div>
                                <div class="small text-muted">Total Requests</div>
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

.expense-breakdown {
    max-height: 300px;
    overflow-y: auto;
}

.expense-item {
    padding: 0.75rem;
    border-radius: 8px;
    background: rgba(0,0,0,0.02);
    transition: background-color 0.2s ease;
}

.expense-item:hover {
    background: rgba(102, 126, 234, 0.05);
}

.summary-card {
    transition: transform 0.2s ease;
    border: 1px solid transparent;
}

.summary-card:hover {
    transform: translateY(-2px);
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
// Mark request as processed
function markAsProcessed(requestId) {
    showConfirmation('Mark this request as processed?', function() {
        showLoading();
        
        fetch('../requests/request-handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_processed',
                request_id: requestId
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            hideLoading();
            alert('An error occurred. Please try again.');
        });
    });
}

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
    const rejectedCounts = trendsData.map(item => parseInt(item.rejected_count));
    const amounts = trendsData.map(item => parseFloat(item.approved_amount));
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Approved',
                data: approvedCounts,
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: 'rgb(40, 167, 69)',
                borderWidth: 1
            }, {
                label: 'Rejected',
                data: rejectedCounts,
                backgroundColor: 'rgba(220, 53, 69, 0.8)',
                borderColor: 'rgb(220, 53, 69)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Requests'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
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
            }
        }
    });
});
<?php endif; ?>

// Initialize tooltips and auto-refresh
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-refresh stats every 5 minutes for accountants
    setInterval(function() {
        if (document.hasFocus()) {
            fetch('../api/requests.php?action=stats&business_id=<?php echo $currentUser['business_id']; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stat numbers
                        document.querySelector('.stat-number.text-success').textContent = data.stats.to_process;
                        document.querySelector('.stat-number.text-warning').textContent = data.stats.pending_approvals;
                        
                        // Update notification badge if needed
                        const badge = document.querySelector('.badge-notification');
                        if (badge && data.notification_count > 0) {
                            badge.textContent = data.notification_count;
                        }
                    }
                })
                .catch(error => {
                    console.log('Auto-refresh failed:', error);
                });
        }
    }, 300000); // 5 minutes
});

// Bulk actions for processing multiple requests
function bulkProcess() {
    const checkboxes = document.querySelectorAll('input[name="request_ids[]"]:checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one request to process.');
        return;
    }
    
    const requestIds = Array.from(checkboxes).map(cb => cb.value);
    
    showConfirmation(`Mark ${requestIds.length} requests as processed?`, function() {
        showLoading();
        
        fetch('../requests/bulk-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'bulk_process',
                request_ids: requestIds
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            hideLoading();
            alert('An error occurred. Please try again.');
        });
    });
}

// Export functions for reports
function exportToExcel() {
    window.open('../reports/export-handler.php?format=excel&type=business&business_id=<?php echo $currentUser['business_id']; ?>', '_blank');
}

function exportToPDF() {
    window.open('../reports/export-handler.php?format=pdf&type=business&business_id=<?php echo $currentUser['business_id']; ?>', '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>