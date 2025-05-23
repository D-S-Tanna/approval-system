<?php
/**
 * Request Management Class
 * File: classes/Request.php
 */

require_once 'Database.php';
require_once 'Notification.php';

class Request {
    private $db;
    private $notification;
    
    public function __construct() {
        $this->db = new Database();
        $this->notification = new Notification();
    }
    
    /**
     * Create new financial request
     * @param array $requestData
     * @param array $documents
     * @return array
     */
    public function create($requestData, $documents = []) {
        try {
            $this->db->beginTransaction();
            
            // Generate unique request number
            $requestData['request_number'] = $this->generateRequestNumber($requestData['business_id']);
            $requestData['created_at'] = date('Y-m-d H:i:s');
            $requestData['status'] = STATUS_PENDING;
            
            // Insert main request
            $requestId = $this->db->insert('financial_requests', $requestData);
            
            if (!$requestId) {
                throw new Exception('Failed to create request');
            }
            
            // Handle document uploads
            if (!empty($documents)) {
                $this->uploadDocuments($requestId, $documents);
            }
            
            // Create approval workflow
            $this->createApprovalWorkflow($requestId, $requestData['business_id'], $requestData['request_type_id'], $requestData['amount']);
            
            // Send notifications
            $this->sendRequestNotifications($requestId, 'created');
            
            // Log activity
            $this->auditLog($_SESSION['user_id'], AUDIT_CREATE, 'financial_requests', $requestId, null, $requestData);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'request_id' => $requestId,
                'request_number' => $requestData['request_number'],
                'message' => 'Request created successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Request creation failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to create request: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get request by ID with full details
     * @param int $requestId
     * @param int $userId (for access control)
     * @return array|false
     */
    public function getById($requestId, $userId = null) {
        $sql = "
            SELECT fr.*, rt.type_name, rt.requires_documentation, rt.max_amount,
                   u.first_name, u.last_name, u.email,
                   b.name as business_name, b.code as business_code,
                   approver.first_name as final_approver_first_name,
                   approver.last_name as final_approver_last_name
            FROM financial_requests fr
            JOIN request_types rt ON fr.request_type_id = rt.id
            JOIN users u ON fr.user_id = u.id
            JOIN businesses b ON fr.business_id = b.id
            LEFT JOIN users approver ON fr.final_decision_by = approver.id
            WHERE fr.id = :request_id
        ";
        
        // Add access control based on user role
        if ($userId) {
            $userRole = $_SESSION['role'] ?? '';
            
            if ($userRole === ROLE_EMPLOYEE) {
                $sql .= " AND fr.user_id = :user_id";
            } elseif ($userRole === ROLE_ACCOUNTANT) {
                $sql .= " AND (fr.business_id = :business_id OR :business_id IS NULL)";
            }
            // Directors and admins can see all requests
        }
        
        $this->db->query($sql);
        $this->db->bind(':request_id', $requestId);
        
        if ($userId) {
            if ($_SESSION['role'] === ROLE_EMPLOYEE) {
                $this->db->bind(':user_id', $userId);
            } elseif ($_SESSION['role'] === ROLE_ACCOUNTANT) {
                $this->db->bind(':business_id', $_SESSION['business_id']);
            }
        }
        
        $request = $this->db->single();
        
        if ($request) {
            // Get approvals
            $request['approvals'] = $this->getRequestApprovals($requestId);
            
            // Get documents
            $request['documents'] = $this->getRequestDocuments($requestId);
            
            // Get approval workflow
            $request['workflow'] = $this->getApprovalWorkflow($request['business_id'], $request['request_type_id'], $request['amount']);
        }
        
        return $request;
    }
    
    /**
     * Update request (only if pending)
     * @param int $requestId
     * @param array $updateData
     * @param int $userId
     * @return array
     */
    public function update($requestId, $updateData, $userId) {
        try {
            // Get current request
            $currentRequest = $this->getById($requestId, $userId);
            
            if (!$currentRequest) {
                return ['success' => false, 'message' => 'Request not found'];
            }
            
            // Check if user can edit
            if ($currentRequest['user_id'] != $userId && $_SESSION['role'] !== ROLE_ADMIN) {
                return ['success' => false, 'message' => 'Access denied'];
            }
            
            // Check if request is still editable
            if ($currentRequest['status'] !== STATUS_PENDING) {
                return ['success' => false, 'message' => 'Cannot edit request after approval process has started'];
            }
            
            $this->db->beginTransaction();
            
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            // Update request
            $result = $this->db->update('financial_requests', $updateData, 'id = :id', [':id' => $requestId]);
            
            if (!$result) {
                throw new Exception('Failed to update request');
            }
            
            // If amount changed, update approval workflow
            if (isset($updateData['amount']) && $updateData['amount'] != $currentRequest['amount']) {
                $this->updateApprovalWorkflow($requestId, $currentRequest['business_id'], $currentRequest['request_type_id'], $updateData['amount']);
            }
            
            // Log activity
            $this->auditLog($userId, AUDIT_UPDATE, 'financial_requests', $requestId, $currentRequest, $updateData);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Request updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Request update failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update request: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel request (only by owner or admin)
     * @param int $requestId
     * @param int $userId
     * @param string $reason
     * @return array
     */
    public function cancel($requestId, $userId, $reason = '') {
        try {
            $request = $this->getById($requestId, $userId);
            
            if (!$request) {
                return ['success' => false, 'message' => 'Request not found'];
            }
            
            // Check permissions
            if ($request['user_id'] != $userId && $_SESSION['role'] !== ROLE_ADMIN) {
                return ['success' => false, 'message' => 'Access denied'];
            }
            
            // Check if cancellable
            if (in_array($request['status'], [STATUS_APPROVED, STATUS_REJECTED, STATUS_CANCELLED])) {
                return ['success' => false, 'message' => 'Cannot cancel request in current status'];
            }
            
            $this->db->beginTransaction();
            
            // Update request status
            $updateData = [
                'status' => STATUS_CANCELLED,
                'rejection_reason' => $reason,
                'final_decision_date' => date('Y-m-d H:i:s'),
                'final_decision_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->db->update('financial_requests', $updateData, 'id = :id', [':id' => $requestId]);
            
            if (!$result) {
                throw new Exception('Failed to cancel request');
            }
            
            // Cancel pending approvals
            $this->db->update('request_approvals', 
                ['status' => APPROVAL_REJECTED, 'updated_at' => date('Y-m-d H:i:s')], 
                'request_id = :request_id AND status = :status', 
                [':request_id' => $requestId, ':status' => APPROVAL_PENDING]
            );
            
            // Send notifications
            $this->sendRequestNotifications($requestId, 'cancelled');
            
            // Log activity
            $this->auditLog($userId, AUDIT_UPDATE, 'financial_requests', $requestId, $request, $updateData);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Request cancelled successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Request cancellation failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to cancel request: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user's requests with pagination and filtering
     * @param int $userId
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getUserRequests($userId, $filters = [], $page = 1, $limit = 25) {
        $offset = ($page - 1) * $limit;
        $whereConditions = ['fr.user_id = :user_id'];
        $params = [':user_id' => $userId];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $whereConditions[] = 'fr.status = :status';
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['type_id'])) {
            $whereConditions[] = 'fr.request_type_id = :type_id';
            $params[':type_id'] = $filters['type_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'DATE(fr.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'DATE(fr.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = '(fr.title LIKE :search OR fr.description LIKE :search OR fr.request_number LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get requests
        $sql = "
            SELECT fr.*, rt.type_name, b.name as business_name
            FROM financial_requests fr
            JOIN request_types rt ON fr.request_type_id = rt.id
            JOIN businesses b ON fr.business_id = b.id
            WHERE {$whereClause}
            ORDER BY fr.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        
        $requests = $this->db->resultSet();
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM financial_requests fr WHERE {$whereClause}";
        $this->db->query($countSql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $totalResult = $this->db->single();
        $total = $totalResult['total'];
        
        return [
            'requests' => $requests,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Get all requests (for accountants/admins) with pagination and filtering
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @param int $businessId
     * @return array
     */
    public function getAllRequests($filters = [], $page = 1, $limit = 25, $businessId = null) {
        $offset = ($page - 1) * $limit;
        $whereConditions = ['1=1'];
        $params = [];
        
        // Business filter for accountants
        if ($businessId !== null) {
            $whereConditions[] = 'fr.business_id = :business_id';
            $params[':business_id'] = $businessId;
        }
        
        // Apply filters
        if (!empty($filters['status'])) {
            $whereConditions[] = 'fr.status = :status';
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['type_id'])) {
            $whereConditions[] = 'fr.request_type_id = :type_id';
            $params[':type_id'] = $filters['type_id'];
        }
        
        if (!empty($filters['urgency'])) {
            $whereConditions[] = 'fr.urgency = :urgency';
            $params[':urgency'] = $filters['urgency'];
        }
        
        if (!empty($filters['user_id'])) {
            $whereConditions[] = 'fr.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'DATE(fr.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'DATE(fr.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = '(fr.title LIKE :search OR fr.description LIKE :search OR fr.request_number LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get requests with user and business info
        $sql = "
            SELECT fr.*, rt.type_name, 
                   u.first_name, u.last_name, u.email,
                   b.name as business_name,
                   COUNT(ra.id) as total_approvals,
                   SUM(CASE WHEN ra.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                   SUM(CASE WHEN ra.status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM financial_requests fr
            JOIN request_types rt ON fr.request_type_id = rt.id
            JOIN users u ON fr.user_id = u.id
            JOIN businesses b ON fr.business_id = b.id
            LEFT JOIN request_approvals ra ON fr.id = ra.request_id
            WHERE {$whereClause}
            GROUP BY fr.id
            ORDER BY fr.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        
        $requests = $this->db->resultSet();
        
        // Get total count
        $countSql = "
            SELECT COUNT(DISTINCT fr.id) as total 
            FROM financial_requests fr
            JOIN users u ON fr.user_id = u.id
            WHERE {$whereClause}
        ";
        $this->db->query($countSql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $totalResult = $this->db->single();
        $total = $totalResult['total'];
        
        return [
            'requests' => $requests,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Generate unique request number
     * @param int $businessId
     * @return string
     */
    private function generateRequestNumber($businessId) {
        // Get business code
        $business = $this->db->select('businesses', 'id = :id', [':id' => $businessId]);
        $businessCode = $business[0]['code'] ?? 'REQ';
        
        // Generate number
        $year = date('Y');
        $month = date('m');
        
        // Get next sequence number for this month
        $sql = "SELECT COUNT(*) + 1 as next_seq FROM financial_requests 
                WHERE business_id = :business_id 
                AND MONTH(created_at) = :month 
                AND YEAR(created_at) = :year";
        
        $this->db->query($sql);
        $this->db->bind(':business_id', $businessId);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        $result = $this->db->single();
        $sequence = str_pad($result['next_seq'], 4, '0', STR_PAD_LEFT);
        
        return "{$businessCode}-{$year}{$month}-{$sequence}";
    }
    
    /**
     * Create approval workflow for request
     * @param int $requestId
     * @param int $businessId
     * @param int $requestTypeId
     * @param float $amount
     */
    private function createApprovalWorkflow($requestId, $businessId, $requestTypeId, $amount) {
        // Get applicable workflow
        $workflow = $this->getApprovalWorkflow($businessId, $requestTypeId, $amount);
        
        if (!$workflow) {
            throw new Exception('No approval workflow found for this request type and amount');
        }
        
        // Check for auto-approval
        if ($workflow['auto_approve_threshold'] && $amount <= $workflow['auto_approve_threshold']) {
            // Auto-approve the request
            $this->autoApproveRequest($requestId);
            return;
        }
        
        // Get directors for business
        $directors = $this->getDirectorsForBusiness($businessId);
        
        if (empty($directors)) {
            throw new Exception('No directors found for approval');
        }
        
        // Limit to required number of approvers
        $requiredApprovers = min($workflow['required_approvers'], count($directors));
        $selectedDirectors = array_slice($directors, 0, $requiredApprovers);
        
        // Create approval records
        foreach ($selectedDirectors as $index => $director) {
            $approvalData = [
                'request_id' => $requestId,
                'approver_id' => $director['id'],
                'status' => APPROVAL_PENDING,
                'approval_order' => $index + 1,
                'is_required' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('request_approvals', $approvalData);
        }
    }
    
    /**
     * Get approval workflow for business, type, and amount
     * @param int $businessId
     * @param int $requestTypeId
     * @param float $amount
     * @return array|false
     */
    private function getApprovalWorkflow($businessId, $requestTypeId, $amount) {
        $sql = "
            SELECT * FROM approval_workflows 
            WHERE business_id = :business_id 
            AND request_type_id = :request_type_id
            AND (min_amount IS NULL OR :amount >= min_amount)
            AND (max_amount IS NULL OR :amount <= max_amount)
            AND is_active = 1
            ORDER BY min_amount DESC
            LIMIT 1
        ";
        
        $this->db->query($sql);
        $this->db->bind(':business_id', $businessId);
        $this->db->bind(':request_type_id', $requestTypeId);
        $this->db->bind(':amount', $amount);
        
        return $this->db->single();
    }
    
    /**
     * Get directors for business
     * @param int $businessId
     * @return array
     */
    private function getDirectorsForBusiness($businessId) {
        $sql = "
            SELECT u.* FROM users u 
            JOIN user_roles ur ON u.role_id = ur.id 
            WHERE ur.role_name = :role 
            AND u.is_active = 1
            AND (u.business_id = :business_id OR u.business_id IS NULL)
            ORDER BY u.id ASC
        ";
        
        $this->db->query($sql);
        $this->db->bind(':role', ROLE_DIRECTOR);
        $this->db->bind(':business_id', $businessId);
        
        return $this->db->resultSet();
    }
    
    /**
     * Auto-approve request
     * @param int $requestId
     */
    private function autoApproveRequest($requestId) {
        $updateData = [
            'status' => STATUS_APPROVED,
            'final_decision_date' => date('Y-m-d H:i:s'),
            'final_decision_by' => null, // System approval
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->update('financial_requests', $updateData, 'id = :id', [':id' => $requestId]);
        
        // Send approval notification
        $this->sendRequestNotifications($requestId, 'auto_approved');
    }
    
    /**
     * Upload documents for request
     * @param int $requestId
     * @param array $documents
     */
    private function uploadDocuments($requestId, $documents) {
        foreach ($documents as $document) {
            $uploadResult = uploadFile($document, UPLOAD_PATH . 'documents/');
            
            if ($uploadResult['success']) {
                $documentData = [
                    'request_id' => $requestId,
                    'document_name' => $document['name'],
                    'file_path' => $uploadResult['path'],
                    'file_size' => $uploadResult['size'],
                    'file_type' => $uploadResult['type'],
                    'uploaded_by' => $_SESSION['user_id'],
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->insert('request_documents', $documentData);
            }
        }
    }
    
    /**
     * Get request approvals
     * @param int $requestId
     * @return array
     */
    private function getRequestApprovals($requestId) {
        $sql = "
            SELECT ra.*, u.first_name, u.last_name, u.email
            FROM request_approvals ra
            JOIN users u ON ra.approver_id = u.id
            WHERE ra.request_id = :request_id
            ORDER BY ra.approval_order ASC
        ";
        
        $this->db->query($sql);
        $this->db->bind(':request_id', $requestId);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get request documents
     * @param int $requestId
     * @return array
     */
    private function getRequestDocuments($requestId) {
        $sql = "
            SELECT rd.*, u.first_name, u.last_name
            FROM request_documents rd
            JOIN users u ON rd.uploaded_by = u.id
            WHERE rd.request_id = :request_id
            ORDER BY rd.uploaded_at ASC
        ";
        
        $this->db->query($sql);
        $this->db->bind(':request_id', $requestId);
        
        return $this->db->resultSet();
    }
    
    /**
     * Send request notifications
     * @param int $requestId
     * @param string $action
     */
    private function sendRequestNotifications($requestId, $action) {
        // Implementation will be completed with Notification class
        $this->notification->sendRequestNotification($requestId, $action);
    }
    
    /**
     * Update approval workflow (when amount changes)
     * @param int $requestId
     * @param int $businessId
     * @param int $requestTypeId
     * @param float $newAmount
     */
    private function updateApprovalWorkflow($requestId, $businessId, $requestTypeId, $newAmount) {
        // Delete existing pending approvals
        $this->db->delete('request_approvals', 'request_id = :request_id AND status = :status', 
            [':request_id' => $requestId, ':status' => APPROVAL_PENDING]);
        
        // Create new approval workflow
        $this->createApprovalWorkflow($requestId, $businessId, $requestTypeId, $newAmount);
    }
    
    /**
     * Log audit trail
     * @param int $userId
     * @param string $action
     * @param string $table
     * @param int $recordId
     * @param array $oldValues
     * @param array $newValues
     */
    private function auditLog($userId, $action, $table, $recordId, $oldValues = null, $newValues = null) {
        $auditData = [
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('audit_logs', $auditData);
    }
}
?>