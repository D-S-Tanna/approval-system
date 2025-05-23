<?php
/**
 * My Requests List
 * File: requests/my-requests.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/Request.php';

requireAuth();

$pageTitle = 'My Requests';
$currentUser = getCurrentUser();

// Get filters from URL
$filters = [
    'status' => sanitize($_GET['status'] ?? ''),
    'type_id' => sanitize($_GET['type_id'] ?? ''),
    'date_from' => sanitize($_GET['date_from'] ?? ''),
    'date_to' => sanitize($_GET['date_to'] ?? ''),
    'search' => sanitize($_GET['search'] ?? '')
];

$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(10, intval($_GET['limit'] ?? 25)));

// Get requests
$request = new Request();
$result = $request->getUserRequests($currentUser['id'], $filters, $page, $limit);

// Get filter options
$requestTypes = getRequestTypes();

// Get status counts for tabs
$db = new Database();
$statusCounts = [];
$statusCountQuery = "
    SELECT status, COUNT(*) as count 
    FROM financial_requests 
    WHERE user_id = :user_id 
    GROUP BY status
";
$db->query($statusCountQuery);
$db->bind(':user_id', $currentUser['id']);
$statusResults = $db->resultSet();

foreach ($statusResults as $statusResult) {
    $statusCounts[$statusResult['status']] = $statusResult['count'];
}

include '../includes/header.php';
?>

<div class="content-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="fas fa-list me-2"></i>My Requests</h1>
            <p class="mb-0">View and manage your financial requests</p>
        </div>
        <a href="create-request.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Request
        </a>
    </div>
</div>

<div class="content-body">
    <!-- Status Tabs -->
    <div class="card mb-4">
        <div class="card-body">
            <ul class="nav nav-pills mb-3" id="statusTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo empty($filters['status']) ? 'active' : ''; ?>" 
                       href="?<?php echo http_build_query(array_merge($filters, ['status' => '', 'page' => 1])); ?>">
                        All Requests
                        <span class="badge bg-secondary ms-1"><?php echo array_sum($statusCounts); ?></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $filters['status'] === STATUS_PENDING ? 'active' : ''; ?>" 
                       href="?<?php echo http_build_query(array_merge($filters, ['status' => STATUS_PENDING, 'page' => 1])); ?>">
                        Pending
                        <span class="badge bg-warning text-dark ms-1"><?php echo $statusCounts[STATUS_PENDING] ?? 0; ?></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $filters['status'] === STATUS_APPROVED ? 'active' : ''; ?>" 
                       href="?<?php echo http_build_query(array_merge($filters, ['status' => STATUS_APPROVED, 'page' => 1])); ?>">
                        Approved
                        <span class="badge bg-success ms-1"><?php echo $statusCounts[STATUS_APPROVED] ?? 0; ?></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $filters['status'] === STATUS_REJECTED ? 'active' : ''; ?>" 
                       href="?<?php echo http_build_query(array_merge($filters, ['status' => STATUS_REJECTED, 'page' => 1])); ?>">
                        Rejected
                        <span class="badge bg-danger ms-1"><?php echo $statusCounts[STATUS_REJECTED] ?? 0; ?></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $filters['status'] === STATUS_CANCELLED ? 'active' : ''; ?>" 
                       href="?<?php echo http_build_query(array_merge($filters, ['status' => STATUS_CANCELLED, 'page' => 1])); ?>">
                        Cancelled
                        <span class="badge bg-secondary ms-1"><?php echo $statusCounts[STATUS_CANCELLED] ?? 0; ?></span>
                    </a>
                </li>
            </ul>
            
            <!-- Filters -->
            <form method="GET" class="row g-3">
                <input type="hidden" name="status" value="<?php echo e($filters['status']); ?>">
                
                <div class="col-md-3">
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
                           placeholder="Title, description, or request number..." 
                           value="<?php echo e($filters['search']); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </div>
                </div>
                
                <?php if (array_filter($filters)): ?>
                <div class="col-12">
                    <a href="my-requests.php" class="btn btn-link p-0">
                        <i class="fas fa-times me-1"></i>Clear all filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Results Summary -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            Showing <?php echo count($result['requests']); ?> of <?php echo $result['total']; ?> requests
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
    
    <!-- Requests Table -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($result['requests'])): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <h5>No requests found</h5>
                    <p class="text-muted">
                        <?php if (array_filter($filters)): ?>
                            Try adjusting your filters or <a href="my-requests.php">view all requests</a>.
                        <?php else: ?>
                            You haven't created any requests yet.
                        <?php endif; ?>
                    </p>
                    <a href="create-request.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create Your First Request
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
                                <th>Request</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Urgency</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['requests'] as $req): ?>
                            <tr data-request-id="<?php echo $req['id']; ?>">
                                <td>
                                    <input type="checkbox" class="request-checkbox" value="<?php echo $req['id']; ?>">
                                </td>
                                <td>
                                    <div>
                                        <a href="view-request.php?id=<?php echo $req['id']; ?>" 
                                           class="fw-bold text-decoration-none">
                                            <?php echo e($req['title']); ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">#<?php echo e($req['request_number']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <?php echo e($req['type_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($req['amount']); ?></strong>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($req['status']); ?>
                                </td>
                                <td>
                                    <?php echo getUrgencyBadge($req['urgency']); ?>
                                </td>
                                <td>
                                    <div>
                                        <?php echo formatDate($req['created_at']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo timeAgo($req['created_at']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view-request.php?id=<?php echo $req['id']; ?>" 
                                           class="btn btn-outline-primary" 
                                           data-bs-toggle="tooltip" 
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($req['status'] === STATUS_PENDING): ?>
                                        <a href="edit-request.php?id=<?php echo $req['id']; ?>" 
                                           class="btn btn-outline-secondary"
                                           data-bs-toggle="tooltip" 
                                           title="Edit Request">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-outline-danger" 
                                                onclick="cancelRequest(<?php echo $req['id']; ?>)"
                                                data-bs-toggle="tooltip" 
                                                title="Cancel Request">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-outline-info" 
                                                onclick="duplicateRequest(<?php echo $req['id']; ?>)"
                                                data-bs-toggle="tooltip" 
                                                title="Duplicate Request">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="view-request.php?id=<?php echo $req['id']; ?>">
                                                        <i class="fas fa-eye me-2"></i>View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="exportRequestToPDF(<?php echo $req['id']; ?>)">
                                                        <i class="fas fa-file-pdf me-2"></i>Export PDF
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="duplicateRequest(<?php echo $req['id']; ?>)">
                                                        <i class="fas fa-copy me-2"></i>Duplicate
                                                    </a>
                                                </li>
                                                <?php if (in_array($req['status'], [STATUS_PENDING])): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="cancelRequest(<?php echo $req['id']; ?>)">
                                                        <i class="fas fa-times me-2"></i>Cancel Request
                                                    </a>
                                                </li>
                                                <?php endif; ?>
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
        $baseUrl = 'my-requests.php?' . http_build_query($queryParams);
        echo generatePagination($result['current_page'], $result['pages'], $baseUrl);
        ?>
    </div>
    <?php endif; ?>
    
    <!-- Bulk Actions Bar (initially hidden) -->
    <div id="bulkActionsBar" class="position-fixed bottom-0 start-50 translate-middle-x bg-primary text-white p-3 rounded-top shadow" style="display: none; z-index: 1000;">
        <div class="d-flex align-items-center">
            <span id="selectedCount" class="me-3">0 selected</span>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-light btn-sm" onclick="exportSelectedToPDF()">
                    <i class="fas fa-file-pdf me-1"></i>Export PDF
                </button>
                <button class="btn btn-light btn-sm" onclick="bulkCancel()" id="bulkCancelBtn" style="display: none;">
                    <i class="fas fa-times me-1"></i>Cancel Selected
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="clearSelection()">
                    <i class="fas fa-times me-1"></i>Clear
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Request Modal -->
<div class="modal fade" id="cancelRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this request?</p>
                <div class="mb-3">
                    <label for="cancelReason" class="form-label">Reason for cancellation (optional):</label>
                    <textarea class="form-control" id="cancelReason" rows="3" 
                              placeholder="Provide a reason for cancelling this request..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Request</button>
                <button type="button" class="btn btn-danger" onclick="confirmCancelRequest()">Cancel Request</button>
            </div>
        </div>
    </div>
</div>

<style>
.nav-pills .nav-link {
    border-radius: 20px;
    margin-right: 0.5rem;
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
}

.table th {
    border-top: none;
    font-weight: 600;
    background-color: var(--light-color);
}

.table-responsive {
    border-radius: 0.375rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.request-checkbox, #selectAll {
    transform: scale(1.2);
}

tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.badge {
    font-size: 0.75em;
}

#bulkActionsBar {
    min-width: 300px;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.125rem 0.25rem;
    }
    
    .btn-group-sm .btn i {
        font-size: 0.8rem;
    }
    
    #bulkActionsBar {
        min-width: 250px;
        padding: 0.75rem;
    }
}
</style>

<script>
let requestIdToCancel = null;
let selectedRequests = new Set();

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
    const checkboxes = document.querySelectorAll('.request-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
        if (selectAllCheckbox.checked) {
            selectedRequests.add(parseInt(checkbox.value));
        } else {
            selectedRequests.delete(parseInt(checkbox.value));
        }
    });
    
    updateBulkActionsBar();
}

// Handle individual checkbox changes
function handleCheckboxChange(checkbox) {
    const requestId = parseInt(checkbox.value);
    
    if (checkbox.checked) {
        selectedRequests.add(requestId);
    } else {
        selectedRequests.delete(requestId);
        document.getElementById('selectAll').checked = false;
    }
    
    updateBulkActionsBar();
}

// Update bulk actions bar
function updateBulkActionsBar() {
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    const bulkCancelBtn = document.getElementById('bulkCancelBtn');
    
    if (selectedRequests.size > 0) {
        selectedCount.textContent = `${selectedRequests.size} selected`;
        bulkActionsBar.style.display = 'block';
        
        // Show cancel button only if all selected requests are pending
        const allPending = Array.from(selectedRequests).every(requestId => {
            const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
            const statusBadge = row.querySelector('.badge');
            return statusBadge && statusBadge.textContent.trim().toLowerCase() === 'pending';
        });
        
        bulkCancelBtn.style.display = allPending ? 'inline-block' : 'none';
    } else {
        bulkActionsBar.style.display = 'none';
    }
}

// Clear selection
function clearSelection() {
    selectedRequests.clear();
    document.querySelectorAll('.request-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkActionsBar();
}

// Cancel request function
function cancelRequest(requestId) {
    requestIdToCancel = requestId;
    const modal = new bootstrap.Modal(document.getElementById('cancelRequestModal'));
    modal.show();
}

// Confirm cancel request
function confirmCancelRequest() {
    const reason = document.getElementById('cancelReason').value;
    
    showLoading();
    
    fetch('request-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'cancel',
            request_id: requestIdToCancel,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('cancelRequestModal'));
        modal.hide();
        
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        hideLoading();
        alert('An error occurred. Please try again.');
        console.error('Error:', error);
    });
}

// Duplicate request
function duplicateRequest(requestId) {
    if (confirm('Create a copy of this request?')) {
        window.location.href = `create-request.php?duplicate=${requestId}`;
    }
}

// Export single request to PDF
function exportRequestToPDF(requestId) {
    window.open(`../reports/export-handler.php?format=pdf&type=request&id=${requestId}`, '_blank');
}

// Export selected requests to PDF
function exportSelectedToPDF() {
    if (selectedRequests.size === 0) {
        alert('Please select at least one request to export.');
        return;
    }
    
    const requestIds = Array.from(selectedRequests).join(',');
    window.open(`../reports/export-handler.php?format=pdf&type=requests&ids=${requestIds}`, '_blank');
}

// Bulk cancel requests
function bulkCancel() {
    if (selectedRequests.size === 0) {
        alert('Please select at least one request to cancel.');
        return;
    }
    
    if (confirm(`Are you sure you want to cancel ${selectedRequests.size} request(s)?`)) {
        showLoading();
        
        fetch('bulk-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'bulk_cancel',
                request_ids: Array.from(selectedRequests),
                reason: 'Bulk cancellation'
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
            console.error('Error:', error);
        });
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add event listeners to checkboxes
    document.querySelectorAll('.request-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            handleCheckboxChange(this);
        });
    });
    
    // Auto-clear filters on page load if no results
    <?php if (empty($result['requests']) && array_filter($filters)): ?>
    const autoCleanParams = new URLSearchParams(window.location.search);
    if (autoCleanParams.has('status') || autoCleanParams.has('type_id') || autoCleanParams.has('search')) {
        const clearButton = document.createElement('div');
        clearButton.className = 'alert alert-info alert-dismissible fade show mt-3';
        clearButton.innerHTML = `
            <i class="fas fa-info-circle me-2"></i>
            No requests found with current filters. 
            <a href="my-requests.php" class="alert-link">Clear filters</a> to see all requests.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.content-body').prepend(clearButton);
    }
    <?php endif; ?>
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Ctrl/Cmd + A to select all
        if ((event.ctrlKey || event.metaKey) && event.key === 'a' && event.target.tagName !== 'INPUT') {
            event.preventDefault();
            document.getElementById('selectAll').checked = true;
            toggleSelectAll();
        }
        
        // Escape to clear selection
        if (event.key === 'Escape') {
            clearSelection();
        }
        
        // Delete key to cancel selected (if all pending)
        if (event.key === 'Delete' && selectedRequests.size > 0) {
            const bulkCancelBtn = document.getElementById('bulkCancelBtn');
            if (bulkCancelBtn.style.display !== 'none') {
                bulkCancel();
            }
        }
    });
    
    // Auto-refresh every 2 minutes for pending requests
    setInterval(function() {
        if (document.hasFocus() && window.location.search.includes('status=pending')) {
            // Check for status updates
            fetch('../api/requests.php?action=check_updates&user_id=<?php echo $currentUser['id']; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.has_updates) {
                        // Show notification about updates
                        const toast = document.createElement('div');
                        toast.className = 'toast position-fixed top-0 end-0 m-3';
                        toast.innerHTML = `
                            <div class="toast-header">
                                <i class="fas fa-sync-alt text-primary me-2"></i>
                                <strong class="me-auto">Updates Available</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                            </div>
                            <div class="toast-body">
                                Some of your requests have been updated. 
                                <a href="#" onclick="location.reload()">Refresh page</a>
                            </div>
                        `;
                        document.body.appendChild(toast);
                        
                        const bsToast = new bootstrap.Toast(toast);
                        bsToast.show();
                        
                        toast.addEventListener('hidden.bs.toast', function() {
                            toast.remove();
                        });
                    }
                })
                .catch(error => {
                    console.log('Update check failed:', error);
                });
        }
    }, 120000); // 2 minutes
});

// Handle URL changes (for back/forward navigation)
window.addEventListener('popstate', function(event) {
    // Optional: Handle browser navigation
});
</script>

<?php include '../includes/footer.php'; ?>