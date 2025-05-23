<?php
/**
 * Director Dashboard
 * File: dashboard/director-dashboard.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/User.php';

// Ensure user is director
requireRole([ROLE_DIRECTOR]);

$pageTitle = 'Director Dashboard';
$currentUser = getCurrentUser();
$db = new Database();

// Get dashboard statistics
$stats = [];

// Pending approvals for this director
$stats['pending_approvals'] = $db->count('request_approvals', 
    'approver_id = :user_id AND status = :status', 
    [':user_id' => $currentUser['id'], ':status' => APPROVAL_PENDING]
);

// Total requests this month
$stats['monthly_requests'] = $db->count('financial_requests', 
    'MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
     AND (business_id = :business_id OR :business_id IS NULL)',
    [':business_id' => $currentUser['business_id']]
);

// Total approved amount this month
$monthlyApprovedQuery = "
    SELECT COALESCE(SUM(fr.amount), 0) as total
    FROM financial_requests fr
    JOIN request_approvals ra ON fr.id = ra.request_id
    WHERE ra.approver_id = :user_id 
    AND ra.status = :status
    AND MONTH(ra.decision_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(ra.decision_date) = YEAR(CURRENT_DATE())
";
$db->query($monthlyApprovedQuery);
$db->bind(':user_id', $currentUser['id']);
$db->bind(':status', APPROVAL_APPROVED);
$monthlyApproved = $db->single();
$stats['monthly_approved'] = $monthlyApproved['total'] ?? 0;

// Average approval time (in hours)
$avgTimeQuery = "
    SELECT AVG(TIMESTAMPDIFF(HOUR, fr.created_at, ra.decision_date)) as avg_hours
    FROM financial_requests fr
    JOIN request_approvals ra ON fr.id = ra.request_id
    WHERE ra.approver_id = :user_id 
    AND ra.status IN (:approved, :rejected)
    AND ra.decision_date IS NOT NULL
    AND DATE(ra.decision_date) >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
";
$db->query($avgTimeQuery);
$db->bind(':user_id', $currentUser['id']);
$db->bind(':approved', APPROVAL_APPROVED);
$db->bind(':rejected', APPROVAL_REJECTED);
$avgTime = $db->single();
$stats['avg_approval_time'] = round($avgTime['avg_hours'] ?? 0, 1);

// Get recent pending approvals
$recentApprovalsQuery = "
    SELECT fr.*, rt.type_name, u.first_name, u.last_name, b.name as business_name,
           ra.created_at as approval_requested_at
    FROM financial_requests fr
    JOIN request_approvals ra ON fr.id = ra.request_id
    JOIN request_types rt ON fr.request_type_id = rt.id
    JOIN users u ON fr.user_id = u.id
    JOIN businesses b ON fr.business_id = b.id
    WHERE ra.approver_id = :user_id 
    AND ra.status = :status
    ORDER BY fr.urgency DESC, ra.created_at ASC
    LIMIT 10
";
$db->query($recentApprovalsQuery);
$db->bind(':user_id', $currentUser['id']);
$db->bind(':status', APPROVAL_PENDING);
$recentApprovals = $db->resultSet();

// Get recent activity
$recentActivityQuery = "
    SELECT fr.*, rt.type_name, u.first_name, u.last_name, b.name as business_name,
           ra.status as approval_status, ra.decision_date, ra.comments
    FROM financial_requests fr
    JOIN request_approvals ra ON fr.id = ra.request_id
    JOIN request_types rt ON fr.request_type_id = rt.id
    JOIN users u ON fr.user_id = u.id
    JOIN businesses b ON fr.business_id = b.id
    WHERE ra.approver_id = :user_id 
    AND ra.status IN (:approved, :rejected)
    ORDER BY ra.decision_date DESC
    LIMIT 5
";
$db->query($recentActivityQuery);
$db->bind(':user_id', $currentUser['id']);
$db->bind(':approved', APPROVAL_APPROVED);
$db->bind(':rejected', APPROVAL_REJECTED);
$recentActivity = $db->resultSet();

// Get monthly trends (last 6 months)
$trendsQuery = "
    SELECT 
        DATE_FORMAT(ra.decision_date, '%Y-%m') as month,
        COUNT(*) as total_approvals,
        SUM(CASE WHEN ra.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN ra.status = 'approved' THEN fr.amount ELSE 0 END) as approved_amount
    FROM request_approvals ra
    JOIN financial_requests fr ON ra.request_id = fr.id
    WHERE ra.approver_id = :user_id 
    AND ra.decision_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    AND ra.status IN ('approved', 'rejected')
    GROUP BY DATE_FORMAT(ra.decision_date, '%Y-%m')
    ORDER BY month ASC
";
$db->query($trendsQuery);
$db->bind(':user_id', $currentUser['id']);
$monthlyTrends = $db->resultSet();

include '../includes/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-tachometer-alt me-2"></i>Director Dashboard</h1>
    <p>Welcome back, <?php echo e($currentUser['full_name']); ?>! Here's your approval overview.</p>
</div>

<div class="content-body">
    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $stats['pending_approvals']; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                    <div class="mt-2">
                        <a href="../approvals/pending-approvals.php" class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-clock me-1"></i>Review Now
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
                    <div class="stat-number text-success"><?php echo formatCurrency($stats['monthly_approved']); ?></div>
                    <div class="stat-label">Approved This Month</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-check-circle me-1"></i>Total Amount
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="quick-stats text-center">
                <div class="stat-card">
                    <div class="stat-number text-primary"><?php echo $stats['avg_approval_time']; ?>h</div>
                    <div class="stat-label">Avg. Approval Time</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-stopwatch me-1"></i>Last 30 Days
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Pending Approvals -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-clock text-warning me-2"></i>Pending Approvals
                    </h5>
                    <a href="../approvals/pending-approvals.php" class="btn btn-sm btn-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentApprovals)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h6>All caught up!</h6>
                            <p class="text-muted mb-0">No pending approvals at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Request</th>
                                        <th>Employee</th>
                                        <th>Amount</th>
                                        <th>Urgency</th>
                                        <th>Waiting</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentApprovals as $approval): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo e($approval['title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo e($approval['type_name']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo e($approval['first_name'] . ' ' . $approval['last_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo e($approval['business_name']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo formatCurrency($approval['amount']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo getUrgencyBadge($approval['urgency']); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo timeAgo($approval['approval_requested_at']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../requests/view-request.php?id=<?php echo $approval['id']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button class="btn btn-outline-success" 
                                                        onclick="quickApprove(<?php echo $approval['id']; ?>)"
                                                        data-bs-toggle="tooltip" 
                                                        title="Quick Approve">
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
        
        <!-- Quick Actions & Recent Activity -->
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
                        <a href="../approvals/pending-approvals.php" class="btn btn-warning">
                            <i class="fas fa-clock me-2"></i>Review Pending (<?php echo $stats['pending_approvals']; ?>)
                        </a>
                        <a href="../requests/create-request.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Request
                        </a>
                        <a href="../reports/approval-summary.php" class="btn btn-info">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </a>
                        <a href="../approvals/approval-history.php" class="btn btn-outline-secondary">
                            <i class="fas fa-history me-2"></i>Approval History
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card">
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
                        <div class="timeline">
                            <?php foreach ($recentActivity as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <i class="fas fa-<?php echo $activity['approval_status'] === APPROVAL_APPROVED ? 'check text-success' : 'times text-danger'; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo $activity['approval_status'] === APPROVAL_APPROVED ? 'Approved' : 'Rejected'; ?></strong>
                                        <small class="text-muted"><?php echo timeAgo($activity['decision_date']); ?></small>
                                    </div>
                                    <div class="small">
                                        <?php echo e($activity['title']); ?> - <?php echo formatCurrency($activity['amount']); ?>
                                    </div>
                                    <div class="small text-muted">
                                        by <?php echo e($activity['first_name'] . ' ' . $activity['last_name']); ?>
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
    
    <!-- Monthly Trends Chart -->
    <?php if (!empty($monthlyTrends)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-primary me-2"></i>Approval Trends (Last 6 Months)
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="trendsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.timeline {
    position: relative;
}

.timeline-item {
    display: flex;
    margin-bottom: 1rem;
    align-items: flex-start;
}

.timeline-marker {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
    border: 2px solid #e9ecef;
}

.timeline-content {
    flex-grow: 1;
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 8px;
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border: none;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Quick approve function
function quickApprove(requestId) {
    showConfirmation('Are you sure you want to approve this request?', function() {
        showLoading();
        
        fetch('../approvals/approve-request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                request_id: requestId,
                action: 'approve',
                comments: 'Quick approval from dashboard'
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
    const approvedCounts = trendsData.map(item => parseInt(item.approved_count));
    const totalCounts = trendsData.map(item => parseInt(item.total_approvals));
    const amounts = trendsData.map(item => parseFloat(item.approved_amount));
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Approved Requests',
                data: approvedCounts,
                borderColor: 'rgb(40, 167, 69)',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                yAxisID: 'y'
            }, {
                label: 'Total Requests',
                data: totalCounts,
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
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
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>