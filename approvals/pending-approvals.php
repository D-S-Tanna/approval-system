<?php
/**
 * Pending Approvals - Complete Version
 * File: approvals/pending-approvals.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/Approval.php';

// Ensure user is director
requireRole([ROLE_DIRECTOR]);

$pageTitle = 'Pending Approvals';
$currentUser = getCurrentUser();

// Get filters from URL
$filters = [
    'urgency' => sanitize($_GET['urgency'] ?? ''),
    'type_id' => sanitize($_GET['type_id'] ?? ''),
    'business_id' => sanitize($_GET['business_id'] ?? ''),
    'amount_min' => floatval($_GET['amount_min'] ?? 0),
    'amount_max' => floatval($_GET['amount_max'] ?? 0),
    'date_from' => sanitize($_GET['date_from'] ?? ''),
    'date_to' => sanitize($_GET['date_to'] ?? ''),
    'search' => sanitize($_GET['search'] ?? '')
];

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(10, intval($_GET['limit'] ?? 25)));

// Get pending approvals
$approval = new Approval();
$result = $approval->getPendingApprovals($currentUser['id'], $filters, $page, $limit);

// Get filter options
$requestTypes = getRequestTypes();
$businesses = getBusinessList();

include '../includes/header.php';
?>

<div class="content-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-clock me-2"></i>Pending Approvals</h1>
            <p class="mb-0">Review and approve financial requests requiring your attention</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-success" id="bulkApproveBtn" onclick="bulkApprove()" disabled>
                <i class="fas fa-check me-2"></i>Bulk Approve
            </button>
            <button class="btn btn-danger" id="bulkRejectBtn" onclick="bulkReject()" disabled>
                <i class="fas fa-times me-2"></i>Bulk Reject
            </button>
        </div>
    </div>
</div>

<div class="content-body">
    <!-- Quick Stats -->
    <div class="row mb-4">
        <?php
        $stats = $approval->getApprovalStats($currentUser['id'], 'month');
        ?>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body text-center">
                    <div class="h2 text-warning mb-1"><?php echo $stats['pending_count']; ?></div>
                    <div class="small text-muted">Pending Approvals</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <div class="h2 text-success mb-1"><?php echo $stats['approved_count']; ?></div>
                    <div class="small text-muted">Approved This Month</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-danger bg-opacity-10 border-danger">
                <div class="card-body text-center">
                    <div class="h2 text-danger mb-1"><?php echo $stats['rejected_count']; ?></div>
                    <div class="small text-muted">Rejected This Month</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <div class="h2 text-info mb-1"><?php echo round($stats['avg_decision_time'] ?? 0, 1); ?>h</div>
                    <div class="small text-muted">Avg Decision Time</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-filter text-primary me-2"></i>Filters
                <button class="btn btn-sm btn-outline-secondary ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </h5>
        </div>
        <div class="collapse <?php echo array_filter($filters) ? 'show' : ''; ?>" id="filtersCollapse">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label for="urgency" class="form-label">Urgency</label>
                        <select class="form-select" id="urgency" name="urgency">
                            <option value="">All Urgencies</option>
                            <option value="critical" <?php echo $filters['urgency'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                            <option value="high" <?php echo $filters['urgency'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $filters['urgency'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $filters['urgency'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="type_id" class="form-label">Request Type</label>
                        <select class="form-select" id="type_id" name="type_id">
                            <option value="">All Types</option>
                            <?php foreach ($requestTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>" 
                                    <?php echo $filters['type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo e($type['type_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="business_id" class="form-label">Business</label>
                        <select class="form-select" id="business_id" name="business_id">
                            <option value="">All Businesses</option>
                            <?php foreach ($businesses as $business): ?>
                            <option value="<?php echo $business['id']; ?>" 
                                    <?php echo $filters['business_id'] == $business['id'] ? 'selected' : ''; ?>>
                                <?php echo e($business['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label for="amount_min" class="form-label">Min Amount</label>
                        <input type="number" class="form-control" id="amount_min" name="amount_min" 
                               step="0.01" min="0" placeholder="0" 
                               value="<?php echo $filters['amount_min'] > 0 ? $filters['amount_min'] : ''; ?>">
                    </div>
                    
                    <div class="col-md-1">
                        <label for="amount_max" class="form-label">Max Amount</label>
                        <input type="number" class="form-control" id="amount_max" name="amount_max" 
                               step="0.01" min="0" placeholder="∞" 
                               value="<?php echo $filters['amount_max'] > 0 ? $filters['amount_max'] : ''; ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo e($filters['date_from']); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo e($filters['date_to']); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Title, description, employee name..." 
                               value="<?php echo e($filters['search']); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            <?php if (array_filter($filters)): ?>
                            <a href="pending-approvals.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Results Summary -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            Showing <?php echo count($result['approvals']); ?> of <?php echo $result['total']; ?> pending approvals
            <?php if (!empty($filters['search'])): ?>
                for "<?php echo e($filters['search']); ?>"
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center">
            <label for="limit" class="form-label me-2 mb-0">Show:</label>
            <select class="form-select form-select-sm" id="limit" name="limit" onchange="changeLimit(this.value)" style="width: auto;">
                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
            </select>
        </div>
    </div>
    
    <!-- Pending Approvals Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($result['approvals'])): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h5>All caught up!</h5>
                    <p class="text-muted">
                        <?php if (array_filter($filters)): ?>
                            No pending approvals match your filters. Try <a href="pending-approvals.php">clearing filters</a>.
                        <?php else: ?>
                            You have no pending approvals at the moment.
                        <?php endif; ?>
                    </p>
                    <a href="approval-history.php" class="btn btn-outline-primary">
                        <i class="fas fa-history me-2"></i>View Approval History
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Request Details</th>
                                <th>Employee</th>
                                <th>Amount</th>
                                <th>Urgency</th>
                                <th>Progress</th>
                                <th>Waiting Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['approvals'] as $req): ?>
                            <tr data-request-id="<?php echo $req['id']; ?>" class="approval-row">
                                <td>
                                    <input type="checkbox" class="approval-checkbox" value="<?php echo $req['id']; ?>">
                                </td>
                                <td>
                                    <div>
                                        <a href="../requests/view-request.php?id=<?php echo $req['id']; ?>" 
                                           class="fw-bold text-decoration-none approval-title">
                                            <?php echo e($req['title']); ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">#<?php echo e($req['request_number']); ?></small>
                                        <br>
                                        <span class="badge bg-light text-dark"><?php echo e($req['type_name']); ?></span>
                                        <?php if ($req['required_by_date']): ?>
                                            <?php
                                            $daysUntil = ceil((strtotime($req['required_by_date']) - time()) / 86400);
                                            if ($daysUntil < 0): ?>
                                                <span class="badge bg-danger ms-1">Overdue</span>
                                            <?php elseif ($daysUntil <= 3): ?>
                                                <span class="badge bg-warning text-dark ms-1">Due in <?php echo $daysUntil; ?> days</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo e($req['first_name'] . ' ' . $req['last_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo e($req['business_name']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <strong class="h6 mb-0"><?php echo formatCurrency($req['amount']); ?></strong>
                                </td>
                                <td>
                                    <?php echo getUrgencyBadge($req['urgency']); ?>
                                </td>
                                <td>
                                    <div class="progress mb-1" style="height: 6px;">
                                        <?php
                                        $progressPercent = $req['total_approvers'] > 0 ? 
                                            ($req['approved_count'] / $req['total_approvers']) * 100 : 0;
                                        ?>
                                        <div class="progress-bar bg-success" 
                                             style="width: <?php echo $progressPercent; ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $req['approved_count']; ?>/<?php echo $req['total_approvers']; ?> approved
                                    </small>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <div class="fw-bold"><?php echo timeAgo($req['approval_requested_at']); ?></div>
                                        <?php
                                        $waitingHours = (time() - strtotime($req['approval_requested_at'])) / 3600;
                                        if ($waitingHours > 72): ?>
                                            <small class="text-danger">⚠️ Long wait</small>
                                        <?php elseif ($waitingHours > 24): ?>
                                            <small class="text-warning">⏰ 1+ day</small>
                                        <?php else: ?>
                                            <small class="text-muted">Recent</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-success" 
                                                onclick="quickApprove(<?php echo $req['id']; ?>)"
                                                data-bs-toggle="tooltip" 
                                                title="Quick Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-danger" 
                                                onclick="quickReject(<?php echo $req['id']; ?>)"
                                                data-bs-toggle="tooltip" 
                                                title="Quick Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <a href="approve-request.php?id=<?php echo $req['id']; ?>" 
                                           class="btn btn-primary"
                                           data-bs-toggle="tooltip" 
                                           title="Detailed Review">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="../requests/view-request.php?id=<?php echo $req['id']; ?>">
                                                        <i class="fas fa-file-alt me-2"></i>View Full Request
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="previewRequest(<?php echo $req['id']; ?>)">
                                                        <i class="fas fa-search me-2"></i>Quick Preview
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="requestMoreInfo(<?php echo $req['id']; ?>)">
                                                        <i class="fas fa-question-circle me-2"></i>Request More Info
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
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
    
    <!-- Pagination -->
    <?php if ($result['pages'] > 1): ?>
    <div class="d-flex justify-content-center mt-4">
        <?php 
        $queryParams = array_merge($filters, ['limit' => $limit]);
        $baseUrl = 'pending-approvals.php?' . http_build_query($queryParams);
        echo generatePagination($result['current_page'], $result['pages'], $baseUrl);
        ?>
    </div>
    <?php endif; ?>
    
    <!-- Bulk Actions Bar -->
    <div id="bulkActionsBar" class="position-fixed bottom-0 start-50 translate-middle-x bg-primary text-white p-3 rounded-top shadow" style="display: none; z-index: 1000;">
        <div class="d-flex align-items-center">
            <span id="selectedCount" class="me-3">0 selected</span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-success btn-sm" onclick="bulkApprove()">
                    <i class="fas fa-check me-1"></i>Approve All
                </button>
                <button class="btn btn-danger btn-sm" onclick="bulkReject()">
                    <i class="fas fa-times me-1"></i>Reject All
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="clearSelection()">
                    <i class="fas fa-times me-1"></i>Clear
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Decision Modals -->
<div class="modal fade" id="quickApproveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Quick Approve</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="approveRequestTitle">Are you sure you want to approve this request?</p>
                <div class="mb-3">
                    <label for="approveComments" class="form-label">Comments (optional):</label>
                    <textarea class="form-control" id="approveComments" rows="3" 
                              placeholder="Add any comments about your approval decision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="confirmApprove()">Approve Request</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickRejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Quick Reject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="rejectRequestTitle">Are you sure you want to reject this request?</p>
                <div class="mb-3">
                    <label for="rejectComments" class="form-label">Reason for rejection <span class="text-danger">*</span>:</label>
                    <textarea class="form-control" id="rejectComments" rows="3" 
                              placeholder="Please provide a reason for rejecting this request..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmReject()">Reject Request</button>
            </div>
        </div>
    </div>
</div>

<!-- Request Preview Modal -->
<div class="modal fade" id="requestPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requestPreviewContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="previewApproveBtn">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
                <button type="button" class="btn btn-danger" id="previewRejectBtn">
                    <i class="fas fa-times me-2"></i>Reject
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.approval-row:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.approval-row.selected {
    background-color: rgba(13, 110, 253, 0.1);
}

.approval-checkbox, #selectAll {
    transform: scale(1.2);
}

.approval-title:hover {
    text-decoration: underline !important;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

#bulkActionsBar {
    min-width: 300px;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.progress {
    background-color: #e9ecef;
}

.table th {
    border-top: none;
    font-weight: 600;
    background-color: var(--light-color);
    position: sticky;
    top: 0;
    z-index: 10;
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border: none;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.125rem 0.25rem;
    }
    
    #bulkActionsBar {
        min-width: 250px;
        padding: 0.75rem;
    }
}
</style>

<script>
let selectedApprovals = new Set();
let currentRequestId = null;

// Change limit and reload
function changeLimit(newLimit) {
    const url = new URL(window.location);
    url.searchParams.set('limit', newLimit);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// Toggle select all checkboxes
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.approval-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
        const row = checkbox.closest('tr');
        
        if (selectAllCheckbox.checked) {
            selectedApprovals.add(parseInt(checkbox.value));
            row.classList.add('selected');
        } else {
            selectedApprovals.delete(parseInt(checkbox.value));
            row.classList.remove('selected');
        }
    });
    
    updateBulkActionsBar();
}

// Handle individual checkbox changes
function handleCheckboxChange(checkbox) {
    const requestId = parseInt(checkbox.value);
    const row = checkbox.closest('tr');
    
    if (checkbox.checked) {
        selectedApprovals.add(requestId);
        row.classList.add('selected');
    } else {
        selectedApprovals.delete(requestId);
        row.classList.remove('selected');
        document.getElementById('selectAll').checked = false;
    }
    
    updateBulkActionsBar();
}

// Update bulk actions bar
function updateBulkActionsBar() {
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    const bulkApproveBtn = document.getElementById('bulkApproveBtn');
    const bulkRejectBtn = document.getElementById('bulkRejectBtn');
    
    if (selectedApprovals.size > 0) {
        selectedCount.textContent = `${selectedApprovals.size} selected`;
        bulkActionsBar.style.display = 'block';
        bulkApproveBtn.disabled = false;
        bulkRejectBtn.disabled = false;
    } else {
        bulkActionsBar.style.display = 'none';
        bulkApproveBtn.disabled = true;
        bulkRejectBtn.disabled = true;
    }
}

// Clear selection
function clearSelection() {
    selectedApprovals.clear();
    document.querySelectorAll('.approval-checkbox').forEach(cb => {
        cb.checked = false;
        cb.closest('tr').classList.remove('selected');
    });
    document.getElementById('selectAll').checked = false;
    updateBulkActionsBar();
}

// Quick approve
function quickApprove(requestId) {
    currentRequestId = requestId;
    const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
    const title = row.querySelector('.approval-title').textContent;
    
    document.getElementById('approveRequestTitle').textContent = `Approve: ${title}`;
    document.getElementById('approveComments').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('quickApproveModal'));
    modal.show();
}

// Quick reject
function quickReject(requestId) {
    currentRequestId = requestId;
    const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
    const title = row.querySelector('.approval-title').textContent;
    
    document.getElementById('rejectRequestTitle').textContent = `Reject: ${title}`;
    document.getElementById('rejectComments').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('quickRejectModal'));
    modal.show();
}

// Confirm approve
function confirmApprove() {
    const comments = document.getElementById('approveComments').value;
    processDecision(currentRequestId, 'approved', comments);
}

// Confirm reject
function confirmReject() {
    const comments = document.getElementById('rejectComments').value.trim();
    
    if (!comments) {
        alert('Please provide a reason for rejection.');
        return;
    }
    
    processDecision(currentRequestId, 'rejected', comments);
}

// Process approval decision
function processDecision(requestId, decision, comments) {
    showLoading();
    
    fetch('approval-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'process_decision',
            request_id: requestId,
            decision: decision,
            comments: comments
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        // Close any open modals
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();
        });
        
        if (data.success) {
            // Remove the row from the table
            const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
            if (row) {
                row.remove();
                selectedApprovals.delete(requestId);
                updateBulkActionsBar();
            }
            
            // Show success message
            showToast(data.message, 'success');
            
            // Update stats
            updateStats();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showToast('An error occurred. Please try again.', 'error');
        console.error('Error:', error);
    });
}

// Bulk approve
function bulkApprove() {
    if (selectedApprovals.size === 0) {
        alert('Please select at least one request to approve.');
        return;
    }
    
    if (confirm(`Are you sure you want to approve ${selectedApprovals.size} request(s)?`)) {
        bulkProcess('approved', 'Bulk approval');
    }
}

// Bulk reject
function bulkReject() {
    if (selectedApprovals.size === 0) {
        alert('Please select at least one request to reject.');
        return;
    }
    
    const reason = prompt('Please provide a reason for bulk rejection:');
    if (!reason || !reason.trim()) {
        alert('Reason is required for rejection.');
        return;
    }
    
    bulkProcess('rejected', reason);
}

// Bulk process requests
function bulkProcess(decision, comments) {
    showLoading();
    
    fetch('approval-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'bulk_process',
            request_ids: Array.from(selectedApprovals),
            decision: decision,
            comments: comments
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            // Remove processed rows
            selectedApprovals.forEach(requestId => {
                const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                if (row) row.remove();
            });
            
            clearSelection();
            showToast(data.message, 'success');
            updateStats();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showToast('An error occurred. Please try again.', 'error');
        console.error('Error:', error);
    });
}

// Preview request
function previewRequest(requestId) {
    const modal = new bootstrap.Modal(document.getElementById('requestPreviewModal'));
    const content = document.getElementById('requestPreviewContent');
    
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    fetch(`../api/requests.php?action=preview&id=${requestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.html;
                
                // Set up preview action buttons
                document.getElementById('previewApproveBtn').onclick = () => {
                    modal.hide();
                    quickApprove(requestId);
                };
                
                document.getElementById('previewRejectBtn').onclick = () => {
                    modal.hide();
                    quickReject(requestId);
                };
            } else {
                content.innerHTML = `<p class="text-danger">Error loading preview: ${data.message}</p>`;
            }
        })
        .catch(error => {
            content.innerHTML = `<p class="text-danger">Error loading preview.</p>`;
            console.error('Preview error:', error);
        });
}

// Request more information
function requestMoreInfo(requestId) {
    const message = prompt('What additional information do you need?');
    if (!message || !message.trim()) return;
    
    fetch('approval-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'request_info',
            request_id: requestId,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Information request sent to employee.', 'success');
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('An error occurred.', 'error');
        console.error('Error:', error);
    });
}

// Update stats
function updateStats() {
    fetch('../api/approvals.php?action=stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update stat cards
                document.querySelector('.bg-warning .h2').textContent = data.stats.pending_count;
                document.querySelector('.bg-success .h2').textContent = data.stats.approved_count;
                document.querySelector('.bg-danger .h2').textContent = data.stats.rejected_count;
            }
        })
        .catch(error => {
            console.log('Stats update failed:', error);
        });
}

// Show toast notification
function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast position-fixed top-0 end-0 m-3`;
    toast.innerHTML = `
        <div class="toast-header">
            <i class="fas fa-${type === 'success' ? 'check-circle text-success' : 'exclamation-triangle text-danger'} me-2"></i>
            <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">${message}</div>
    `;
    
    document.body.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add event listeners to checkboxes
    document.querySelectorAll('.approval-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            handleCheckboxChange(this);
        });
    });
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        if (document.hasFocus()) {
            fetch('../api/approvals.php?action=check_updates')
                .then(response => response.json())
                .then(data => {
                    if (data.has_updates) {
                        showToast('New approvals available. <a href="#" onclick="location.reload()">Refresh page</a>', 'info');
                    }
                })
                .catch(error => {
                    console.log('Update check failed:', error);
                });
        }
    }, 30000);
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Ctrl/Cmd + A to select all
        if ((event.ctrlKey || event.metaKey) && event.key === 'a' && event.target.tagName !== 'INPUT' && event.target.tagName !== 'TEXTAREA') {
            event.preventDefault();
            document.getElementById('selectAll').checked = true;
            toggleSelectAll();
        }
        
        // Escape to clear selection
        if (event.key === 'Escape') {
            clearSelection();
            
            // Close any open modals
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) modalInstance.hide();
            });
        }
        
        // Enter to approve selected (if only one selected)
        if (event.key === 'Enter' && selectedApprovals.size === 1) {
            const requestId = Array.from(selectedApprovals)[0];
            quickApprove(requestId);
        }
    });
    
    // Row click to select
    document.querySelectorAll('.approval-row').forEach(row => {
        row.addEventListener('click', function(event) {
            // Don't select if clicking on buttons or links
            if (event.target.tagName === 'BUTTON' || 
                event.target.tagName === 'A' || 
                event.target.closest('button') || 
                event.target.closest('a') ||
                event.target.type === 'checkbox') {
                return;
            }
            
            const checkbox = this.querySelector('.approval-checkbox');
            checkbox.checked = !checkbox.checked;
            handleCheckboxChange(checkbox);
        });
    });
    
    // Smart filters auto-submit
    let filterTimeout;
    document.querySelectorAll('#urgency, #type_id, #business_id').forEach(select => {
        select.addEventListener('change', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => {
                this.closest('form').submit();
            }, 500);
        });
    });
    
    // Search input auto-submit with debounce
    let searchTimeout;
    document.getElementById('search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.closest('form').submit();
        }, 1000);
    });
});

// Priority sorting functions
function sortByPriority() {
    const tbody = document.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const urgencyA = a.querySelector('.badge').textContent.toLowerCase();
        const urgencyB = b.querySelector('.badge').textContent.toLowerCase();
        
        const urgencyOrder = { 'critical': 1, 'high': 2, 'medium': 3, 'low': 4 };
        
        return urgencyOrder[urgencyA] - urgencyOrder[urgencyB];
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

function sortByAmount() {
    const tbody = document.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const amountA = parseFloat(a.querySelector('strong.h6').textContent.replace(/[^0-9.-]+/g, ''));
        const amountB = parseFloat(b.querySelector('strong.h6').textContent.replace(/[^0-9.-]+/g, ''));
        
        return amountB - amountA; // Descending order
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

function sortByWaitTime() {
    const tbody = document.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const timeA = a.querySelector('[data-request-id]').getAttribute('data-request-id');
        const timeB = b.querySelector('[data-request-id]').getAttribute('data-request-id');
        
        // Sort by waiting time (oldest first)
        return timeA - timeB;
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Export functions
function exportSelectedToPDF() {
    if (selectedApprovals.size === 0) {
        alert('Please select at least one approval to export.');
        return;
    }
    
    const requestIds = Array.from(selectedApprovals).join(',');
    window.open(`../reports/export-handler.php?format=pdf&type=approvals&ids=${requestIds}`, '_blank');
}

// Print function
function printApprovals() {
    window.print();
}

// Utility functions for loading states
function showLoading() {
    if (typeof window.showLoading === 'function') {
        window.showLoading();
    } else {
        // Fallback loading indicator
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'tempLoading';
        loadingDiv.className = 'position-fixed top-50 start-50 translate-middle';
        loadingDiv.innerHTML = '<div class="spinner-border text-primary" role="status"></div>';
        document.body.appendChild(loadingDiv);
    }
}

function hideLoading() {
    if (typeof window.hideLoading === 'function') {
        window.hideLoading();
    } else {
        // Remove fallback loading indicator
        const loadingDiv = document.getElementById('tempLoading');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>