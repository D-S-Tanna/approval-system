<?php
/**
 * Create Request Form
 * File: requests/create-request.php
 */

require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../classes/Request.php';

requireAuth();

$pageTitle = 'Create New Request';
$currentUser = getCurrentUser();
$db = new Database();

// Get form data
$businesses = getBusinessList();
$requestTypes = getRequestTypes();

// Pre-select business for non-admin users
$selectedBusinessId = $currentUser['business_id'] ?? '';
$selectedTypeId = sanitize($_GET['type'] ?? '');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage(MSG_ERROR, 'Invalid security token. Please try again.');
        header('Location: create-request.php');
        exit();
    }
    
    // Collect form data
    $requestData = [
        'user_id' => $currentUser['id'],
        'business_id' => sanitize($_POST['business_id']),
        'request_type_id' => sanitize($_POST['request_type_id']),
        'title' => sanitize($_POST['title']),
        'description' => sanitize($_POST['description']),
        'amount' => floatval($_POST['amount']),
        'currency' => sanitize($_POST['currency']) ?: DEFAULT_CURRENCY,
        'urgency' => sanitize($_POST['urgency']),
        'requested_date' => date('Y-m-d'),
        'required_by_date' => !empty($_POST['required_by_date']) ? $_POST['required_by_date'] : null
    ];
    
    // Validation
    $errors = [];
    
    if (empty($requestData['business_id'])) {
        $errors[] = 'Please select a business.';
    }
    
    if (empty($requestData['request_type_id'])) {
        $errors[] = 'Please select a request type.';
    }
    
    if (empty($requestData['title'])) {
        $errors[] = 'Please enter a request title.';
    }
    
    if (empty($requestData['description'])) {
        $errors[] = 'Please enter a description.';
    }
    
    if ($requestData['amount'] <= 0) {
        $errors[] = 'Please enter a valid amount.';
    }
    
    if ($requestData['amount'] > MAX_REQUEST_AMOUNT) {
        $errors[] = 'Amount exceeds maximum limit of ' . formatCurrency(MAX_REQUEST_AMOUNT);
    }
    
    // Check business access
    if ($currentUser['role'] !== ROLE_ADMIN && $currentUser['business_id'] && $currentUser['business_id'] != $requestData['business_id']) {
        $errors[] = 'You can only create requests for your assigned business.';
    }
    
    // Validate required by date
    if ($requestData['required_by_date'] && $requestData['required_by_date'] < date('Y-m-d')) {
        $errors[] = 'Required by date cannot be in the past.';
    }
    
    // Check request type limits
    $requestType = null;
    foreach ($requestTypes as $type) {
        if ($type['id'] == $requestData['request_type_id']) {
            $requestType = $type;
            break;
        }
    }
    
    if ($requestType && $requestType['max_amount'] && $requestData['amount'] > $requestType['max_amount']) {
        $errors[] = "Amount exceeds maximum limit for {$requestType['type_name']}: " . formatCurrency($requestType['max_amount']);
    }
    
    if (empty($errors)) {
        $request = new Request();
        
        // Handle file uploads
        $documents = [];
        if (!empty($_FILES['documents']['name'][0])) {
            foreach ($_FILES['documents']['name'] as $index => $name) {
                if (!empty($name)) {
                    $documents[] = [
                        'name' => $_FILES['documents']['name'][$index],
                        'tmp_name' => $_FILES['documents']['tmp_name'][$index],
                        'size' => $_FILES['documents']['size'][$index],
                        'type' => $_FILES['documents']['type'][$index],
                        'error' => $_FILES['documents']['error'][$index]
                    ];
                }
            }
        }
        
        $result = $request->create($requestData, $documents);
        
        if ($result['success']) {
            setFlashMessage(MSG_SUCCESS, "Request {$result['request_number']} created successfully!");
            header("Location: view-request.php?id={$result['request_id']}");
            exit();
        } else {
            $errors[] = $result['message'];
        }
    }
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            setFlashMessage(MSG_ERROR, $error);
        }
    }
}

$csrfToken = generateCSRFToken();

include '../includes/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-plus-circle me-2"></i>Create New Request</h1>
    <p>Submit a new financial request for approval</p>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-plus text-primary me-2"></i>Request Details
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="requestForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="row">
                            <!-- Business Selection -->
                            <div class="col-md-6 mb-3">
                                <label for="business_id" class="form-label">Business <span class="text-danger">*</span></label>
                                <select class="form-select" id="business_id" name="business_id" required>
                                    <option value="">Select Business</option>
                                    <?php foreach ($businesses as $business): ?>
                                        <?php if (!$currentUser['business_id'] || $currentUser['business_id'] == $business['id'] || $currentUser['role'] === ROLE_ADMIN): ?>
                                        <option value="<?php echo $business['id']; ?>" 
                                                <?php echo ($selectedBusinessId == $business['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($business['name']); ?>
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Request Type -->
                            <div class="col-md-6 mb-3">
                                <label for="request_type_id" class="form-label">Request Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="request_type_id" name="request_type_id" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($requestTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            data-max-amount="<?php echo $type['max_amount']; ?>"
                                            data-requires-docs="<?php echo $type['requires_documentation']; ?>"
                                            <?php echo ($selectedTypeId == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($type['type_name']); ?>
                                        <?php if ($type['max_amount']): ?>
                                            (Max: <?php echo formatCurrency($type['max_amount']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" id="typeDescription"></div>
                            </div>
                        </div>
                        
                        <!-- Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label">Request Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   placeholder="Brief description of your request" 
                                   maxlength="255" required value="<?php echo e($_POST['title'] ?? ''); ?>">
                            <div class="form-text">Enter a clear, concise title for your request</div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      placeholder="Detailed description of your request, including purpose and justification"
                                      maxlength="2000" required><?php echo e($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-text">
                                <span id="descriptionCount">0</span>/2000 characters. 
                                Be specific about the purpose and business justification.
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Amount -->
                            <div class="col-md-4 mb-3">
                                <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-select" id="currency" name="currency" style="max-width: 80px;">
                                        <option value="USD">$</option>
                                        <option value="EUR">€</option>
                                        <option value="GBP">£</option>
                                        <option value="UGX">USh</option>
                                    </select>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           step="0.01" min="0.01" max="<?php echo MAX_REQUEST_AMOUNT; ?>" 
                                           placeholder="0.00" required value="<?php echo e($_POST['amount'] ?? ''); ?>">
                                </div>
                                <div class="form-text" id="amountWarning"></div>
                            </div>
                            
                            <!-- Urgency -->
                            <div class="col-md-4 mb-3">
                                <label for="urgency" class="form-label">Urgency</label>
                                <select class="form-select" id="urgency" name="urgency">
                                    <option value="low" <?php echo (($_POST['urgency'] ?? 'medium') === 'low') ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo (($_POST['urgency'] ?? 'medium') === 'medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo (($_POST['urgency'] ?? 'medium') === 'high') ? 'selected' : ''; ?>>High</option>
                                    <option value="critical" <?php echo (($_POST['urgency'] ?? 'medium') === 'critical') ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            
                            <!-- Required By Date -->
                            <div class="col-md-4 mb-3">
                                <label for="required_by_date" class="form-label">Required By Date</label>
                                <input type="date" class="form-control" id="required_by_date" name="required_by_date" 
                                       min="<?php echo date('Y-m-d'); ?>" value="<?php echo e($_POST['required_by_date'] ?? ''); ?>">
                                <div class="form-text">When do you need this approved?</div>
                            </div>
                        </div>
                        
                        <!-- File Upload -->
                        <div class="mb-4">
                            <label for="documents" class="form-label">Supporting Documents</label>
                            <input type="file" class="form-control" id="documents" name="documents[]" 
                                   multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
                            <div class="form-text">
                                Upload receipts, quotes, invoices, or other supporting documents. 
                                Max file size: <?php echo formatFileSize(UPLOAD_MAX_SIZE); ?> per file. 
                                <span id="documentsRequired" class="text-warning" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i> Documentation required for this request type.
                                </span>
                            </div>
                            
                            <!-- File Preview -->
                            <div id="filePreview" class="mt-2"></div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="my-requests.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Tips -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb text-warning me-2"></i>Tips for Success
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Be specific:</strong> Provide clear details about what you need and why.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Include documentation:</strong> Attach quotes, receipts, or invoices when available.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Business justification:</strong> Explain how this benefits the company.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Set urgency appropriately:</strong> Critical should only be for true emergencies.
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Double-check amounts:</strong> Ensure all figures are accurate.
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Request Type Info -->
            <div class="card" id="typeInfoCard" style="display: none;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle text-info me-2"></i>Request Type Information
                    </h5>
                </div>
                <div class="card-body">
                    <div id="typeInfoContent"></div>
                </div>
            </div>
            
            <!-- Recent Requests -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-info me-2"></i>Your Recent Requests
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get user's recent requests
                    $recentRequestsQuery = "
                        SELECT fr.id, fr.title, fr.amount, fr.status, fr.created_at, rt.type_name
                        FROM financial_requests fr
                        JOIN request_types rt ON fr.request_type_id = rt.id
                        WHERE fr.user_id = :user_id
                        ORDER BY fr.created_at DESC
                        LIMIT 5
                    ";
                    $db->query($recentRequestsQuery);
                    $db->bind(':user_id', $currentUser['id']);
                    $recentRequests = $db->resultSet();
                    ?>
                    
                    <?php if (empty($recentRequests)): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-file-alt fa-2x mb-2"></i>
                            <p>No previous requests</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentRequests as $recent): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div>
                                <div class="small fw-bold"><?php echo e($recent['title']); ?></div>
                                <div class="small text-muted">
                                    <?php echo e($recent['type_name']); ?> - <?php echo formatCurrency($recent['amount']); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php echo getStatusBadge($recent['status']); ?>
                                <div class="small text-muted"><?php echo timeAgo($recent['created_at']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="my-requests.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.file-preview-item {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    margin: 0.25rem 0;
    background: #f8f9fa;
    border-radius: 0.375rem;
    border: 1px solid #dee2e6;
}

.file-preview-item .file-icon {
    margin-right: 0.75rem;
    font-size: 1.25rem;
}

.file-preview-item .file-info {
    flex-grow: 1;
}

.file-preview-item .file-size {
    font-size: 0.875rem;
    color: #6c757d;
}

.file-preview-item .remove-file {
    background: none;
    border: none;
    color: #dc3545;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0.25rem;
}

.file-preview-item .remove-file:hover {
    color: #c82333;
}

.form-control:invalid {
    border-color: #dc3545;
}

.form-control:valid {
    border-color: #28a745;
}

#amountWarning.text-warning {
    font-weight: 500;
}

#amountWarning.text-danger {
    font-weight: 500;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const requestTypeSelect = document.getElementById('request_type_id');
    const amountInput = document.getElementById('amount');
    const documentsInput = document.getElementById('documents');
    const descriptionTextarea = document.getElementById('description');
    const typeInfoCard = document.getElementById('typeInfoCard');
    const typeInfoContent = document.getElementById('typeInfoContent');
    const documentsRequired = document.getElementById('documentsRequired');
    const amountWarning = document.getElementById('amountWarning');
    const descriptionCount = document.getElementById('descriptionCount');
    const filePreview = document.getElementById('filePreview');
    
    let selectedFiles = [];
    
    // Update character count for description
    function updateDescriptionCount() {
        const count = descriptionTextarea.value.length;
        descriptionCount.textContent = count;
        
        if (count > 1800) {
            descriptionCount.className = 'text-warning';
        } else if (count > 1950) {
            descriptionCount.className = 'text-danger';
        } else {
            descriptionCount.className = 'text-muted';
        }
    }
    
    // Update request type information
    function updateRequestTypeInfo() {
        const selectedOption = requestTypeSelect.options[requestTypeSelect.selectedIndex];
        
        if (selectedOption.value) {
            const maxAmount = selectedOption.dataset.maxAmount;
            const requiresDocs = selectedOption.dataset.requiresDocs === '1';
            
            let infoHtml = '<ul class="list-unstyled mb-0">';
            
            if (maxAmount) {
                infoHtml += `<li><i class="fas fa-dollar-sign text-primary me-2"></i>Maximum amount: <strong>${formatCurrency(parseFloat(maxAmount))}</strong></li>`;
            }
            
            infoHtml += `<li><i class="fas fa-${requiresDocs ? 'file-alt text-warning' : 'file text-muted'} me-2"></i>Documentation: ${requiresDocs ? '<strong>Required</strong>' : 'Optional'}</li>`;
            
            infoHtml += '</ul>';
            
            typeInfoContent.innerHTML = infoHtml;
            typeInfoCard.style.display = 'block';
            
            // Show/hide documentation requirement notice
            if (requiresDocs) {
                documentsRequired.style.display = 'inline';
            } else {
                documentsRequired.style.display = 'none';
            }
            
            // Validate amount against max
            validateAmount();
        } else {
            typeInfoCard.style.display = 'none';
            documentsRequired.style.display = 'none';
        }
    }
    
    // Validate amount against type limits
    function validateAmount() {
        const selectedOption = requestTypeSelect.options[requestTypeSelect.selectedIndex];
        const amount = parseFloat(amountInput.value);
        
        if (selectedOption.value && amount > 0) {
            const maxAmount = parseFloat(selectedOption.dataset.maxAmount);
            
            if (maxAmount && amount > maxAmount) {
                amountWarning.textContent = `Amount exceeds maximum limit of ${formatCurrency(maxAmount)} for this request type.`;
                amountWarning.className = 'form-text text-danger';
                amountInput.setCustomValidity('Amount exceeds maximum limit');
            } else {
                amountWarning.textContent = '';
                amountWarning.className = 'form-text';
                amountInput.setCustomValidity('');
            }
        } else {
            amountWarning.textContent = '';
            amountWarning.className = 'form-text';
            amountInput.setCustomValidity('');
        }
    }
    
    // Handle file selection
    function handleFileSelection() {
        const files = Array.from(documentsInput.files);
        selectedFiles = files;
        updateFilePreview();
    }
    
    // Update file preview
    function updateFilePreview() {
        filePreview.innerHTML = '';
        
        selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-preview-item';
            
            const icon = getFileIcon(file.name);
            const size = formatFileSize(file.size);
            
            fileItem.innerHTML = `
                <div class="file-icon">${icon}</div>
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${size}</div>
                </div>
                <button type="button" class="remove-file" onclick="removeFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            filePreview.appendChild(fileItem);
        });
    }
    
    // Get file icon based on extension
    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            'pdf': '<i class="fas fa-file-pdf text-danger"></i>',
            'doc': '<i class="fas fa-file-word text-primary"></i>',
            'docx': '<i class="fas fa-file-word text-primary"></i>',
            'xls': '<i class="fas fa-file-excel text-success"></i>',
            'xlsx': '<i class="fas fa-file-excel text-success"></i>',
            'jpg': '<i class="fas fa-file-image text-info"></i>',
            'jpeg': '<i class="fas fa-file-image text-info"></i>',
            'png': '<i class="fas fa-file-image text-info"></i>'
        };
        
        return icons[ext] || '<i class="fas fa-file text-secondary"></i>';
    }
    
    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
    
    // Remove file from selection
    window.removeFile = function(index) {
        selectedFiles.splice(index, 1);
        
        // Update file input
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        documentsInput.files = dt.files;
        
        updateFilePreview();
    };
    
    // Event listeners
    requestTypeSelect.addEventListener('change', updateRequestTypeInfo);
    amountInput.addEventListener('input', validateAmount);
    documentsInput.addEventListener('change', handleFileSelection);
    descriptionTextarea.addEventListener('input', updateDescriptionCount);
    
    // Form validation
    document.getElementById('requestForm').addEventListener('submit', function(e) {
        const selectedOption = requestTypeSelect.options[requestTypeSelect.selectedIndex];
        const requiresDocs = selectedOption.dataset.requiresDocs === '1';
        
        if (requiresDocs && selectedFiles.length === 0) {
            e.preventDefault();
            alert('Documentation is required for this request type. Please upload supporting documents.');
            return false;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    });
    
    // Initialize
    updateDescriptionCount();
    updateRequestTypeInfo();
    
    // Auto-save draft (optional enhancement)
    let autoSaveTimer;
    function autoSave() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            const formData = new FormData(document.getElementById('requestForm'));
            // Could implement auto-save functionality here
        }, 30000); // Save every 30 seconds
    }
    
    // Set up auto-save on form changes
    document.getElementById('requestForm').addEventListener('input', autoSave);
});
</script>

<?php include '../includes/footer.php'; ?>