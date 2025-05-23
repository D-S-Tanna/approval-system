<?php
/**
 * View Request Details
 * File: requests/view-request.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/Request.php';

requireAuth();

$requestId = intval($_GET['id'] ?? 0);
$currentUser = getCurrentUser();

if (!$requestId) {
    setFlashMessage(MSG_ERROR, 'Invalid request ID.');
    header('Location: my-requests.php');
    exit();
}

$request = new Request();
$requestData = $request->getById($requestId, $currentUser['id']);

if (!$requestData) {
    setFlashMessage(MSG_ERROR, 'Request not found or access denied.');
    header('Location: my-requests.php');
    exit();
}

$pageTitle = 'Request #' . $requestData['request_number'];

// Check user permissions
$canEdit = ($requestData['user_id'] == $currentUser['id'] && $requestData['status'] === STATUS_PENDING) || $currentUser['role'] === ROLE_ADMIN;
$canCancel = ($requestData['user_id'] == $currentUser['id'] && in_array($requestData['status'], [STATUS_PENDING])) || $currentUser['role'] === ROLE_ADMIN;
$canApprove = $currentUser['role'] === ROLE_DIRECTOR && $requestData['status'] === STATUS_PENDING;

include '../includes/header.php';
?>

<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1><i class="fas fa-file-alt me-2"></i><?php echo e($requestData['title']); ?></h1>
            <p class="mb-0">
                Request #<?php echo e($requestData['request_number']); ?> • 
                <?php echo getStatusBadge($requestData['status']); ?> • 
                <?php echo getUrgencyBadge($requestData['urgency']); ?>
            </p>
        </div>
        <div class="btn-group">
            <?php if ($canEdit): ?>
            <a href="edit-request.php?id=<?php echo $requestData['id']; ?>" class="btn btn-outline-primary">
                <i class="fas fa-edit me-1"></i>Edit
            </a>
            <?php endif; ?>
            
            <?php if ($canApprove): ?>
            <a href="../approvals/approve-request.php?id=<?php echo $requestData['id']; ?>" class="btn btn-success">
                <i class="fas fa-check me-1"></i>Review for Approval
            </a>
            <?php endif; ?>
            
            <?php if ($canCancel): ?>
            <button class="btn btn-outline-danger" onclick="cancelRequest(<?php echo $requestData['id']; ?>)">
                <i class="fas fa-times me-1"></i>Cancel
            </button>
            <?php endif; ?>
            
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="printRequest()">
                        <i class="fas fa-print me-2"></i>Print
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i>Export PDF
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="my-requests.php">
                        <i class="fas fa-list me-2"></i>Back to My Requests
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <!-- Main Request Details -->
        <div class="col-lg-8">
            <!-- Request Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle text-primary me-2"></i>Request Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">Request Number:</dt>
                                <dd class="col-sm-8"><?php echo e($requestData['request_number']); ?></dd>
                                
                                <dt class="col-sm-4">Type:</dt>
                                <dd class="col-sm-8"><?php echo e($requestData['type_name']); ?></dd>
                                
                                <dt class="col-sm-4">Amount:</dt>
                                <dd class="col-sm-8">
                                    <strong class="h5 text-primary"><?php echo formatCurrency($requestData['amount'], $requestData['currency']); ?></strong>
                                </dd>
                                
                                <dt class="col-sm-4">Urgency:</dt>
                                <dd class="col-sm-8"><?php echo getUrgencyBadge($requestData['urgency']); ?></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">Status:</dt>
                                <dd class="col-sm-8"><?php echo getStatusBadge($requestData['status']); ?></dd>
                                
                                <dt class="col-sm-4">Requested By:</dt>
                                <dd class="col-sm-8"><?php echo e($requestData['first_name'] . ' ' . $requestData['last_name']); ?></dd>
                                
                                <dt class="col-sm-4">Business:</dt>
                                <dd class="col-sm-8"><?php echo e($requestData['business_name']); ?></dd>
                                
                                <dt class="col-sm-4">Date Submitted:</dt>
                                <dd class="col-sm-8"><?php echo formatDate($requestData['created_at'], DISPLAY_DATETIME_FORMAT); ?></dd>
                            </dl>
                        </div>
                    </div>
                    
                    <?php if ($requestData['required_by_date']): ?>
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <strong>Required by:</strong> <?php echo formatDate($requestData['required_by_date']); ?>
                        <?php
                        $daysUntil = ceil((strtotime($requestData['required_by_date']) - time()) / 86400);
                        if ($daysUntil < 0) {
                            echo '<span class="badge bg-danger ms-2">Overdue</span>';
                        } elseif ($daysUntil <= 7) {
                            echo '<span class="badge bg-warning text-dark ms-2">' . $daysUntil . ' days remaining</span>';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <h6>Description:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(e($requestData['description'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Approval Workflow -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-route text-success me-2"></i>Approval Workflow
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($requestData['approvals'])): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h6>Auto-Approved</h6>
                            <p class="text-muted mb-0">This request was automatically approved based on your business rules.</p>
                        </div>
                    <?php else: ?>
                        <div class="approval-timeline">
                            <?php foreach ($requestData['approvals'] as $approval): ?>
                            <div class="approval-step d-flex align-items-start mb-3">
                                <div class="approval-marker me-3">
                                    <?php if ($approval['status'] === APPROVAL_APPROVED): ?>
                                        <i class="fas fa-check-circle fa-2x text-success"></i>
                                    <?php elseif ($approval['status'] === APPROVAL_REJECTED): ?>
                                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                                    <?php else: ?>
                                        <i class="fas fa-clock fa-2x text-warning"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="approval-content flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo e($approval['first_name'] . ' ' . $approval['last_name']); ?></h6>
                                            <div class="small text-muted mb-2"><?php echo e($approval['email']); ?></div>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($approval['status'] === APPROVAL_APPROVED): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif ($approval['status'] === APPROVAL_REJECTED): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($approval['decision_date']): ?>
                                                <div class="small text-muted mt-1">
                                                    <?php echo formatDate($approval['decision_date'], DISPLAY_DATETIME_FORMAT); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($approval['comments']): ?>
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <small><strong>Comments:</strong> <?php echo nl2br(e($approval['comments'])); ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Supporting Documents -->
            <?php if (!empty($requestData['documents'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-paperclip text-info me-2"></i>Supporting Documents
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($requestData['documents'] as $document): ?>
                        <div class="col-md-6 mb-3">
                            <div class="document-item d-flex align-items-center p-3 border rounded">
                                <div class="document-icon me-3">
                                    <?php
                                    $extension = strtolower(pathinfo($document['document_name'], PATHINFO_EXTENSION));
                                    $iconClass = match($extension) {
                                        'pdf' => 'fas fa-file-pdf text-danger',
                                        'doc', 'docx' => 'fas fa-file-word text-primary',
                                        'xls', 'xlsx' => 'fas fa-file-excel text-success',
                                        'jpg', 'jpeg', 'png' => 'fas fa-file-image text-info',
                                        default => 'fas fa-file text-secondary'
                                    };
                                    ?>
                                    <i class="<?php echo $iconClass; ?> fa-2x"></i>
                                </div>
                                <div class="document-info flex-grow-1">
                                    <div class="fw-bold"><?php echo e($document['document_name']); ?></div>
                                    <div class="small text-muted">
                                        <?php echo formatFileSize($document['file_size']); ?> • 
                                        Uploaded by <?php echo e($document['first_name'] . ' ' . $document['last_name']); ?> • 
                                        <?php echo timeAgo($document['uploaded_at']); ?>
                                    </div>
                                </div>
                                <div class="document-actions">
                                    <a href="download-document.php?id=<?php echo $document['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       data-bs-toggle="tooltip" 
                                       title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'])): ?>
                                    <button class="btn btn-sm btn-outline-info ms-1" 
                                            onclick="previewDocument('<?php echo e($document['document_name']); ?>', '<?php echo $document['id']; ?>')"
                                            data-bs-toggle="tooltip" 
                                            title="Preview">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
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
                        <?php if ($canEdit): ?>
                        <a href="edit-request.php?id=<?php echo $requestData['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Request
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($canApprove): ?>
                        <a href="../approvals/approve-request.php?id=<?php echo $requestData['id']; ?>" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Review for Approval
                        </a>
                        <?php endif; ?>
                        
                        <button class="btn btn-info" onclick="printRequest()">
                            <i class="fas fa-print me-2"></i>Print Request
                        </button>
                        
                        <button class="btn btn-outline-secondary" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </button>
                        
                        <?php if ($canCancel): ?>
                        <button class="btn btn-outline-danger" onclick="cancelRequest(<?php echo $requestData['id']; ?>)">
                            <i class="fas fa-times me-2"></i>Cancel Request
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Request Details Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie text-success me-2"></i>Request Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="summary-item d-flex justify-content-between mb-2">
                        <span class="text-muted">Created:</span>
                        <span><?php echo formatDate($requestData['created_at']); ?></span>
                    </div>
                    
                    <?php if ($requestData['updated_at'] !== $requestData['created_at']): ?>
                    <div class="summary-item d-flex justify-content-between mb-2">
                        <span class="text-muted">Last Updated:</span>
                        <span><?php echo formatDate($requestData['updated_at']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($requestData['final_decision_date']): ?>
                    <div class="summary-item d-flex justify-content-between mb-2">
                        <span class="text-muted">Decision Date:</span>
                        <span><?php echo formatDate($requestData['final_decision_date']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($requestData['final_decision_by']): ?>
                    <div class="summary-item d-flex justify-content-between mb-2">
                        <span class="text-muted">Decided By:</span>
                        <span><?php echo e($requestData['final_approver_first_name'] . ' ' . $requestData['final_approver_last_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-item d-flex justify-content-between mb-2">
                        <span class="text-muted">Documents:</span>
                        <span><?php echo count($requestData['documents']); ?> files</span>
                    </div>
                    
                    <div class="summary-item d-flex justify-content-between">
                        <span class="text-muted">Approvers:</span>
                        <span><?php echo count($requestData['approvals']); ?> required</span>
                    </div>
                </div>
            </div>
            
            <!-- Approval Progress -->
            <?php if (!empty($requestData['approvals'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks text-warning me-2"></i>Approval Progress
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $totalApprovers = count($requestData['approvals']);
                    $approvedCount = 0;
                    $rejectedCount = 0;
                    $pendingCount = 0;
                    
                    foreach ($requestData['approvals'] as $approval) {
                        if ($approval['status'] === APPROVAL_APPROVED) $approvedCount++;
                        elseif ($approval['status'] === APPROVAL_REJECTED) $rejectedCount++;
                        else $pendingCount++;
                    }
                    
                    $progressPercentage = $totalApprovers > 0 ? ($approvedCount / $totalApprovers) * 100 : 0;
                    ?>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small">Progress</span>
                            <span class="small"><?php echo $approvedCount; ?>/<?php echo $totalApprovers; ?> approved</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" 
                                 style="width: <?php echo $progressPercentage; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="approval-stats">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-success">
                                <i class="fas fa-check-circle me-1"></i>Approved
                            </span>
                            <span class="fw-bold"><?php echo $approvedCount; ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-warning">
                                <i class="fas fa-clock me-1"></i>Pending
                            </span>
                            <span class="fw-bold"><?php echo $pendingCount; ?></span>
                        </div>
                        
                        <?php if ($rejectedCount > 0): ?>
                        <div class="d-flex justify-content-between">
                            <span class="text-danger">
                                <i class="fas fa-times-circle me-1"></i>Rejected
                            </span>
                            <span class="fw-bold"><?php echo $rejectedCount; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Related Requests -->
            <?php
            // Get related requests from same user
            $relatedQuery = "
                SELECT id, title, amount, status, created_at, request_number
                FROM financial_requests 
                WHERE user_id = :user_id 
                AND id != :current_id 
                ORDER BY created_at DESC 
                LIMIT 5
            ";
            $db = new Database();
            $db->query($relatedQuery);
            $db->bind(':user_id', $requestData['user_id']);
            $db->bind(':current_id', $requestData['id']);
            $relatedRequests = $db->resultSet();
            ?>
            
            <?php if (!empty($relatedRequests)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-link text-info me-2"></i>Related Requests
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($relatedRequests as $related): ?>
                    <div class="related-item d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <div class="small fw-bold">
                                <a href="view-request.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                    <?php echo e($related['title']); ?>
                                </a>
                            </div>
                            <div class="small text-muted">
                                #<?php echo e($related['request_number']); ?> • 
                                <?php echo formatCurrency($related['amount']); ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <?php echo getStatusBadge($related['status']); ?>
                            <div class="small text-muted"><?php echo timeAgo($related['created_at']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div class="modal fade" id="documentPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Document Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="documentPreviewContent" class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
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
.approval-timeline {
    position: relative;
    padding-left: 30px;
}

.approval-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 35px;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.approval-step {
    position: relative;
    margin-left: -30px;
}

.approval-marker {
    position: relative;
    z-index: 2;
    background: white;
    border-radius: 50%;
}

.document-item {
    transition: all 0.2s ease;
}

.document-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.summary-item {
    padding: 0.25rem 0;
}

.related-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

@media print {
    .btn, .dropdown, .card-header .fas, .sidebar {
        display: none !important;
    }
    
    .content-body {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>

<script>
let requestIdToCancel = null;

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
    
    fetch('../requests/request-handler.php', {
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

// Preview document function
function previewDocument(filename, documentId) {
    const modal = new bootstrap.Modal(document.getElementById('documentPreviewModal'));
    const content = document.getElementById('documentPreviewContent');
    
    // Show loading spinner
    content.innerHTML = `
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading ${filename}...</p>
    `;
    
    modal.show();
    
    // Load document preview
    fetch(`preview-document.php?id=${documentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.type === 'image') {
                    content.innerHTML = `<img src="${data.url}" class="img-fluid" alt="${filename}">`;
                } else if (data.type === 'pdf') {
                    content.innerHTML = `<iframe src="${data.url}" width="100%" height="500px" frameborder="0"></iframe>`;
                } else {
                    content.innerHTML = `<p>Preview not available for this file type.</p>`;
                }
            } else {
                content.innerHTML = `<p class="text-danger">Failed to load preview: ${data.message}</p>`;
            }
        })
        .catch(error => {
            content.innerHTML = `<p class="text-danger">Error loading preview.</p>`;
            console.error('Preview error:', error);
        });
}

// Print request function
function printRequest() {
    window.print();
}

// Export to PDF function
function exportToPDF() {
    window.open(`../reports/export-handler.php?format=pdf&type=request&id=<?php echo $requestData['id']; ?>`, '_blank');
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-refresh status every 30 seconds for pending requests
    <?php if ($requestData['status'] === STATUS_PENDING): ?>
    setInterval(function() {
        if (document.hasFocus()) {
            fetch(`../api/requests.php?action=status&id=<?php echo $requestData['id']; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.status !== '<?php echo $requestData['status']; ?>') {
                        // Status changed, reload page
                        location.reload();
                    }
                })
                .catch(error => {
                    console.log('Status check failed:', error);
                });
        }
    }, 30000); // Check every 30 seconds
    <?php endif; ?>
});

// Handle browser back button
window.addEventListener('popstate', function(event) {
    // Optionally handle navigation
});

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    // Ctrl/Cmd + P for print
    if ((event.ctrlKey || event.metaKey) && event.key === 'p') {
        event.preventDefault();
        printRequest();
    }
    
    // Esc to close modals
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>