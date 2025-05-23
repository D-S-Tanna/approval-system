<?php
/**
 * Approval Management Class
 * File: classes/Approval.php
 */

require_once 'Database.php';
require_once 'Notification.php';

class Approval {
    private $db;
    private $notification;
    
    public function __construct() {
        $this->db = new Database();
        $this->notification = new Notification();
    }
    
    /**
     * Process approval decision
     * @param int $requestId
     * @param int $approverId
     * @param string $decision (approve/reject)
     * @param string $comments
     * @return array
     */
    public function processDecision($requestId, $approverId, $decision, $comments = '') {
        try {
            $this->db->beginTransaction();
            
            // Validate decision
            if (!in_array($decision, [APPROVAL_APPROVED, APPROVAL_REJECTED])) {
                throw new Exception('Invalid decision');
            }
            
            // Get request details
            $request = $this->getRequestDetails($requestId);
            if (!$request) {
                throw new Exception('Request not found');
            }
            
            // Check if request is still pending
            if ($request['status'] !== STATUS_PENDING) {
                throw new Exception('Request is no longer pending approval');
            }
            
            // Get approval record
            $approval = $this->getApprovalRecord($requestId, $approverId);
            if (!$approval) {
                throw new Exception('Approval record not found');
            }
            
            // Check if already decided
            if ($approval['status'] !== APPROVAL_PENDING) {
                throw new Exception('You have already made a decision on this request');
            }
            
            // Update approval record
            $approvalData = [
                'status' => $decision,
                'decision_date' => date('Y-m-d H:i:s'),
                'comments' => $comments,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->update('request_approvals', $approvalData, 
                'id = :id', [':id' => $approval['id']]);
            
            // Check if request should be finalized
            $this->checkRequestStatus($requestId, $decision);
            
            // Log activity
            $this->auditLog($approverId, AUDIT_APPROVE, 'request_approvals', $approval['id'], 
                $approval, array_merge($approvalData, ['decision' => $decision]));
            
            $this->db->commit();
            
            // Send notifications
            $this->sendApprovalNotifications($requestId, $decision, $approverId);
            
            return [
                'success' => true,
                'message' => ucfirst($decision) . ' decision recorded successfully',
                'decision' => $decision
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Approval processing failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get pending approvals for director
     * @param int $approverId
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPendingApprovals($approverId, $filters = [], $page = 1, $limit = 25) {
        $offset = ($page - 1) * $limit;
        $whereConditions = [
            'ra.approver_id = :approver_id',
            'ra.status = :approval_status',
            'fr.status = :request_status'
        ];
        $params = [
            ':approver_id' => $approverId,
            ':approval_status' => APPROVAL_PENDING,
            ':request_status' => STATUS_PENDING
        ];
        
        // Apply filters
        if (!empty($filters['urgency'])) {
            $whereConditions[] = 'fr.urgency = :urgency';
            $params[':urgency'] = $filters['urgency'];
        }
        
        if (!empty($filters['type_id'])) {
            $whereConditions[] = 'fr.request_type_id = :type_id';
            $params[':type_id'] = $filters['type_id'];
        }
        
        if (!empty($filters['business_id'])) {
            $whereConditions[] = 'fr.business_id = :business_id';
            $params[':business_id'] = $filters['business_id'];
        }
        
        if (!empty($filters['amount_min'])) {
            $whereConditions[] = 'fr.amount >= :amount_min';
            $params[':amount_min'] = $filters['amount_min'];
        }
        
        if (!empty($filters['amount_max'])) {
            $whereConditions[] = 'fr.amount <= :amount_max';
            $params[':amount_max'] = $filters['amount_max'];
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
        
        // Get pending approvals
        $sql = "
            SELECT fr.*, rt.type_name, u.first_name, u.last_name, u.email,
                   b.name as business_name, ra.created_at as approval_requested_at,
                   ra.approval_order, ra.is_required,
                   COUNT(ra_all.id) as total_approvers,
                   SUM(CASE WHEN ra_all.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                   SUM(CASE WHEN ra_all.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM request_approvals ra
            JOIN financial_requests fr ON ra.request_id = fr.id
            JOIN request_types rt ON fr.request_type_id = rt.id
            JOIN users u ON fr.user_id = u.id
            JOIN businesses b ON fr.business_id = b.id
            LEFT JOIN request_approvals ra_all ON fr.id = ra_all.request_id
            WHERE {$whereClause}
            GROUP BY fr.id, ra.id
            ORDER BY 
                CASE fr.urgency 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    ELSE 4 
                END,
                fr.required_by_date ASC,
                ra.created_at ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        
        $approvals = $this->db->resultSet();
        
        // Get total count
        $countSql = "
            SELECT COUNT(DISTINCT ra.id) as total 
            FROM request_approvals ra
            JOIN financial_requests fr ON ra.request_id = fr.id
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
            'approvals' => $approvals,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Get approval history for director
     * @param int $approverId
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getApprovalHistory($approverId, $filters = [], $page = 1, $limit = 25) {
        $offset = ($page - 1) * $limit;
        $whereConditions = [
            'ra.approver_id = :approver_id',
            'ra.status IN (:approved, :rejected)'
        ];
        $params = [
            ':approver_id' => $approverId,
            ':approved' => APPROVAL_APPROVED,
            ':rejected' => APPROVAL_REJECTED
        ];
        
        // Apply filters similar to pending approvals
        if (!empty($filters['status'])) {
            $whereConditions[] = 'ra.status = :decision_status';
            $params[':decision_status'] = $filters['status'];
        }
        
        if (!empty($filters['type_id'])) {
            $whereConditions[] = 'fr.request_type_id = :type_id';
            $params[':type_id'] = $filters['type_id'];
        }
        
        if (!empty($filters['business_id'])) {
            $whereConditions[] = 'fr.business_id = :business_id';
            $params[':business_id'] = $filters['business_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'DATE(ra.decision_date) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'DATE(ra.decision_date) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = '(fr.title LIKE :search OR fr.description LIKE :search OR fr.request_number LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT fr.*, rt.type_name, u.first_name, u.last_name, u.email,
                   b.name as business_name, ra.status as decision_status,
                   ra.decision_date, ra.comments, ra.created_at as approval_requested_at
            FROM request_approvals ra
            JOIN financial_requests fr ON ra.request_id = fr.id
            JOIN request_types rt ON fr.request_type_id = rt.id
            JOIN users u ON fr.user_id = u.id
            JOIN businesses b ON fr.business_id = b.id
            WHERE {$whereClause}
            ORDER BY ra.decision_date DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        
        $history = $this->db->resultSet();
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) as total 
            FROM request_approvals ra
            JOIN financial_requests fr ON ra.request_id = fr.id
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
            'history' => $history,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Bulk approve/reject requests
     * @param array $requestIds
     * @param int $approverId
     * @param string $decision
     * @param string $comments
     * @return array
     */
    public function bulkProcess($requestIds, $approverId, $decision, $comments = '') {
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($requestIds as $requestId) {
            $result = $this->processDecision($requestId, $approverId, $decision, $comments);
            $results[$requestId] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        return [
            'success' => $errorCount === 0,
            'message' => "Processed {$successCount} requests successfully" . 
                        ($errorCount > 0 ? ", {$errorCount} failed" : ""),
            'results' => $results,
            'success_count' => $successCount,
            'error_count' => $errorCount
        ];
    }
    
    /**
     * Get approval statistics for director
     * @param int $approverId
     * @param string $period (day, week, month, year)
     * @return array
     */
    public function getApprovalStats($approverId, $period = 'month') {
        $dateCondition = match($period) {
            'day' => 'DATE(ra.decision_date) = CURDATE()',
            'week' => 'YEARWEEK(ra.decision_date) = YEARWEEK(CURDATE())',
            'month' => 'MONTH(ra.decision_date) = MONTH(CURDATE()) AND YEAR(ra.decision_date) = YEAR(CURDATE())',
            'year' => 'YEAR(ra.decision_date) = YEAR(CURDATE())',
            default => 'MONTH(ra.decision_date) = MONTH(CURDATE()) AND YEAR(ra.decision_date) = YEAR(CURDATE())'
        };
        
        $sql = "
            SELECT 
                COUNT(*) as total_decisions,
                SUM(CASE WHEN ra.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN ra.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN ra.status = 'approved' THEN fr.amount ELSE 0 END) as approved_amount,
                AVG(TIMESTAMPDIFF(HOUR, ra.created_at, ra.decision_date)) as avg_decision_time,
                MIN(ra.decision_date) as first_decision,
                MAX(ra.decision_date) as last_decision
            FROM request_approvals ra
            JOIN financial_requests fr ON ra.request_id = fr.id
            WHERE ra.approver_id = :approver_id 
            AND ra.status IN ('approved', 'rejected')
            AND {$dateCondition}
        ";
        
        $this->db->query($sql);
        $this->db->bind(':approver_id', $approverId);
        
        $stats = $this->db->single();
        
        // Get pending count
        $pendingQuery = "
            SELECT COUNT(*) as pending_count
            FROM request_approvals ra
            JOIN financial_requests fr ON ra.request_id = fr.id
            WHERE ra.approver_id = :approver_id 
            AND ra.status = 'pending'
            AND fr.status = 'pending'
        ";
        
        $this->db->query($pendingQuery);
        $this->db->bind(':approver_id', $approverId);
        $pendingResult = $this->db->single();
        
        $stats['pending_count'] = $pendingResult['pending_count'];
        $stats['approval_rate'] = $stats['total_decisions'] > 0 ? 
            ($stats['approved_count'] / $stats['total_decisions']) * 100 : 0;
        
        return $stats;
    }
    
    /**
     * Get request details
     * @param int $requestId
     * @return array|false
     */
    private function getRequestDetails($requestId) {
        $sql = "
            SELECT fr.*, u.first_name, u.last_name, u.email,
                   rt.type_name, b.name as business_name
            FROM financial_requests fr
            JOIN users u ON fr.user_id = u.id
            JOIN request_types rt ON fr.request_type_id = rt.id
            JOIN businesses b ON fr.business_id = b.id
            WHERE fr.id = :request_id
        ";
        
        $this->db->query($sql);
        $this->db->bind(':request_id', $requestId);
        
        return $this->db->single();
    }
    
    /**
     * Get approval record
     * @param int $requestId
     * @param int $approverId
     * @return array|false
     */
    private function getApprovalRecord($requestId, $approverId) {
        $sql = "
            SELECT * FROM request_approvals 
            WHERE request_id = :request_id AND approver_id = :approver_id
        ";
        
        $this->db->query($sql);
        $this->db->bind(':request_id', $requestId);
        $this->db->bind(':approver_id', $approverId);
        
        return $this->db->single();
    }
    
    /**
     * Check and update request status based on approvals
     * @param int $requestId
     * @param string $latestDecision
     */
    private function checkRequestStatus($requestId, $latestDecision) {
        // If any approval is rejected, reject the entire request
        if ($latestDecision === APPROVAL_REJECTED) {
            $this->finalizeRequest($requestId, STATUS_REJECTED);
            return;
        }
        
        // Check if all required approvals are complete
        $sql = "
            SELECT 
                COUNT(*) as total_required,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM request_approvals 
            WHERE request_id = :request_id AND is_required = 1
        ";
        
        $this->db->query($sql);
        $this->db->bind(':request_id', $requestId);
        $result = $this->db->single();
        
        if ($result['rejected_count'] > 0) {
            $this->finalizeRequest($requestId, STATUS_REJECTED);
        } elseif ($result['approved_count'] >= $result['total_required']) {
            $this->finalizeRequest($requestId, STATUS_APPROVED);
        }
    }
    
    /**
     * Finalize request status
     * @param int $requestId
     * @param string $status
     */
    private function finalizeRequest($requestId, $status) {
        $updateData = [
            'status' => $status,
            'final_decision_date' => date('Y-m-d H:i:s'),
            'final_decision_by' => $_SESSION['user_id'],
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->update('financial_requests', $updateData, 'id = :id', [':id' => $requestId]);
    }
    
    /**
     * Send approval notifications
     * @param int $requestId
     * @param string $decision
     * @param int $approverId
     */
    private function sendApprovalNotifications($requestId, $decision, $approverId) {
        $request = $this->getRequestDetails($requestId);
        
        if ($decision === APPROVAL_APPROVED) {
            $this->notification->sendRequestNotification($requestId, 'approved');
        } else {
            $this->notification->sendRequestNotification($requestId, 'rejected');
        }
        
        // Notify other approvers if request is finalized
        $finalStatus = $this->db->select('financial_requests', 'id = :id', [':id' => $requestId]);
        if ($finalStatus && $finalStatus[0]['status'] !== STATUS_PENDING) {
            $this->notifyOtherApprovers($requestId, $approverId, $decision);
        }
    }
    
    /**
     * Notify other approvers about final decision
     * @param int $requestId
     * @param int $decidingApproverId
     * @param string $decision
     */
    private function notifyOtherApprovers($requestId, $decidingApproverId, $decision) {
        $sql = "
            SELECT DISTINCT u.id, u.email, u.first_name, u.last_name
            FROM request_approvals ra
            JOIN users u ON ra.approver_id = u.id
            WHERE ra.request_id = :request_id 
            AND ra.approver_id != :deciding_approver
            AND ra.status = 'pending'
        ";
        
        $this->db->query($sql);
        $this->db->bind(':request_id', $requestId);
        $this->db->bind(':deciding_approver', $decidingApproverId);
        
        $otherApprovers = $this->db->resultSet();
        
        foreach ($otherApprovers as $approver) {
            $title = 'Request Decision Made';
            $message = "A request you were assigned to approve has been " . 
                      ($decision === APPROVAL_APPROVED ? 'approved' : 'rejected') . 
                      " by another director.";
            
            $this->notification->create($approver['id'], NOTIFICATION_SYSTEM_ALERT, $title, $message, $requestId);
        }
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