<?php
/**
 * Notification Management Class
 * File: classes/Notification.php
 */

require_once 'Database.php';

class Notification {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Send request-related notification
     * @param int $requestId
     * @param string $action
     */
    public function sendRequestNotification($requestId, $action) {
        // Get request details
        $request = $this->getRequestDetails($requestId);
        
        if (!$request) {
            return false;
        }
        
        switch ($action) {
            case 'created':
                $this->notifyApprovers($request);
                break;
            case 'approved':
                $this->notifyRequestApproved($request);
                break;
            case 'rejected':
                $this->notifyRequestRejected($request);
                break;
            case 'cancelled':
                $this->notifyRequestCancelled($request);
                break;
            case 'auto_approved':
                $this->notifyAutoApproved($request);
                break;
        }
    }
    
    /**
     * Create system notification
     * @param int $userId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param int $requestId
     * @return bool
     */
    public function create($userId, $type, $title, $message, $requestId = null) {
        $notificationData = [
            'user_id' => $userId,
            'request_id' => $requestId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => 0,
            'sent_via' => NOTIFY_SYSTEM,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('notifications', $notificationData);
    }
    
    /**
     * Send email notification
     * @param string $email
     * @param string $subject
     * @param string $message
     * @param array $data
     * @return bool
     */
    public function sendEmail($email, $subject, $message, $data = []) {
        // Generate email content using template
        $emailContent = $this->generateEmailTemplate($subject, $message, $data);
        
        // Send email
        return sendEmail($email, $subject, $emailContent);
    }
    
    /**
     * Get user notifications
     * @param int $userId
     * @param bool $unreadOnly
     * @param int $limit
     * @return array
     */
    public function getUserNotifications($userId, $unreadOnly = false, $limit = 20) {
        $whereClause = 'user_id = :user_id';
        $params = [':user_id' => $userId];
        
        if ($unreadOnly) {
            $whereClause .= ' AND is_read = 0';
        }
        
        $sql = "
            SELECT n.*, fr.request_number, fr.title as request_title
            FROM notifications n
            LEFT JOIN financial_requests fr ON n.request_id = fr.id
            WHERE {$whereClause}
            ORDER BY n.created_at DESC
            LIMIT {$limit}
        ";
        
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        
        return $this->db->resultSet();
    }
    
    /**
     * Mark notification as read
     * @param int $notificationId
     * @param int $userId
     * @return bool
     */
    public function markAsRead($notificationId, $userId) {
        return $this->db->update('notifications', 
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'id = :id AND user_id = :user_id',
            [':id' => $notificationId, ':user_id' => $userId]
        );
    }
    
    /**
     * Mark all notifications as read for user
     * @param int $userId
     * @return bool
     */
    public function markAllAsRead($userId) {
        return $this->db->update('notifications',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'user_id = :user_id AND is_read = 0',
            [':user_id' => $userId]
        );
    }
    
    /**
     * Get unread notification count
     * @param int $userId
     * @return int
     */
    public function getUnreadCount($userId) {
        return $this->db->count('notifications', 
            'user_id = :user_id AND is_read = 0', 
            [':user_id' => $userId]
        );
    }
    
    /**
     * Notify approvers about new request
     * @param array $request
     */
    private function notifyApprovers($request) {
        // Get pending approvals for this request
        $sql = "
            SELECT ra.approver_id, u.email, u.first_name, u.last_name
            FROM request_approvals ra
            JOIN users u ON ra.approver_id = u.id
            WHERE ra.request_id = :request_id AND ra.status = :status
        ";
        
        $this->db->query($sql);
        $this->db->bind(':request_id', $request['id']);
        $this->db->bind(':status', APPROVAL_PENDING);
        
        $approvers = $this->db->resultSet();
        
        foreach ($approvers as $approver) {
            // Create system notification
            $title = 'New Request Needs Approval';
            $message = "Request #{$request['request_number']} from {$request['requester_name']} requires your approval. Amount: " . formatCurrency($request['amount']);
            
            $this->create($approver['approver_id'], NOTIFICATION_APPROVAL_NEEDED, $title, $message, $request['id']);
            
            // Send email notification
            $emailSubject = APP_NAME . " - Approval Required: {$request['title']}";
            $emailData = [
                'approver_name' => $approver['first_name'],
                'request' => $request,
                'action_url' => APP_URL . "/requests/view-request.php?id=" . $request['id']
            ];
            
            $this->sendEmail($approver['email'], $emailSubject, 'approval_needed', $emailData);
        }
    }
    
    /**
     * Notify requester about approval
     * @param array $request
     */
    private function notifyRequestApproved($request) {
        $title = 'Request Approved';
        $message = "Your request #{$request['request_number']} has been approved. Amount: " . formatCurrency($request['amount']);
        
        $this->create($request['user_id'], NOTIFICATION_REQUEST_APPROVED, $title, $message, $request['id']);
        
        // Send email
        $emailSubject = APP_NAME . " - Request Approved: {$request['title']}";
        $emailData = [
            'requester_name' => $request['requester_name'],
            'request' => $request
        ];
        
        $this->sendEmail($request['requester_email'], $emailSubject, 'request_approved', $emailData);
    }
    
    /**
     * Notify requester about rejection
     * @param array $request
     */
    private function notifyRequestRejected($request) {
        $title = 'Request Rejected';
        $message = "Your request #{$request['request_number']} has been rejected. Please review the comments and resubmit if necessary.";
        
        $this->create($request['user_id'], NOTIFICATION_REQUEST_REJECTED, $title, $message, $request['id']);
        
        // Send email
        $emailSubject = APP_NAME . " - Request Rejected: {$request['title']}";
        $emailData = [
            'requester_name' => $request['requester_name'],
            'request' => $request
        ];
        
        $this->sendEmail($request['requester_email'], $emailSubject, 'request_rejected', $emailData);
    }
    
    /**
     * Notify requester about cancellation
     * @param array $request
     */
    private function notifyRequestCancelled($request) {
        $title = 'Request Cancelled';
        $message = "Request #{$request['request_number']} has been cancelled.";
        
        $this->create($request['user_id'], NOTIFICATION_SYSTEM_ALERT, $title, $message, $request['id']);
    }
    
    /**
     * Notify requester about auto-approval
     * @param array $request
     */
    private function notifyAutoApproved($request) {
        $title = 'Request Auto-Approved';
        $message = "Your request #{$request['request_number']} has been automatically approved. Amount: " . formatCurrency($request['amount']);
        
        $this->create($request['user_id'], NOTIFICATION_REQUEST_APPROVED, $title, $message, $request['id']);
        
        // Send email
        $emailSubject = APP_NAME . " - Request Auto-Approved: {$request['title']}";
        $emailData = [
            'requester_name' => $request['requester_name'],
            'request' => $request
        ];
        
        $this->sendEmail($request['requester_email'], $emailSubject, 'request_approved', $emailData);
    }
    
    /**
     * Get request details for notifications
     * @param int $requestId
     * @return array|false
     */
    private function getRequestDetails($requestId) {
        $sql = "
            SELECT fr.*, u.first_name, u.last_name, u.email as requester_email,
                   CONCAT(u.first_name, ' ', u.last_name) as requester_name,
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
     * Generate email template
     * @param string $subject
     * @param string $template
     * @param array $data
     * @return string
     */
    private function generateEmailTemplate($subject, $template, $data = []) {
        $emailContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .request-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .highlight { color: #667eea; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . APP_NAME . "</h1>
                    <p>{$subject}</p>
                </div>
                <div class='content'>
        ";
        
        // Add template-specific content
        switch ($template) {
            case 'approval_needed':
                $emailContent .= $this->getApprovalNeededTemplate($data);
                break;
            case 'request_approved':
                $emailContent .= $this->getRequestApprovedTemplate($data);
                break;
            case 'request_rejected':
                $emailContent .= $this->getRequestRejectedTemplate($data);
                break;
            default:
                $emailContent .= "<p>{$template}</p>";
                break;
        }
        
        $emailContent .= "
                </div>
                <div class='footer'>
                    <p>This is an automated message from " . APP_NAME . "</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $emailContent;
    }
    
    /**
     * Get approval needed email template
     * @param array $data
     * @return string
     */
    private function getApprovalNeededTemplate($data) {
        $request = $data['request'];
        $actionUrl = $data['action_url'];
        
        return "
            <h2>Hello {$data['approver_name']},</h2>
            <p>A new financial request requires your approval:</p>
            
            <div class='request-details'>
                <h3>{$request['title']}</h3>
                <p><strong>Request Number:</strong> {$request['request_number']}</p>
                <p><strong>Requested by:</strong> {$request['requester_name']}</p>
                <p><strong>Amount:</strong> <span class='highlight'>" . formatCurrency($request['amount']) . "</span></p>
                <p><strong>Type:</strong> {$request['type_name']}</p>
                <p><strong>Urgency:</strong> " . ucfirst($request['urgency']) . "</p>
                <p><strong>Description:</strong></p>
                <p>{$request['description']}</p>
            </div>
            
            <p>Please review and approve this request at your earliest convenience.</p>
            <p><a href='{$actionUrl}' class='button'>Review Request</a></p>
        ";
    }
    
    /**
     * Get request approved email template
     * @param array $data
     * @return string
     */
    private function getRequestApprovedTemplate($data) {
        $request = $data['request'];
        
        return "
            <h2>Hello {$data['requester_name']},</h2>
            <p>Great news! Your financial request has been approved:</p>
            
            <div class='request-details'>
                <h3>{$request['title']}</h3>
                <p><strong>Request Number:</strong> {$request['request_number']}</p>
                <p><strong>Amount:</strong> <span class='highlight'>" . formatCurrency($request['amount']) . "</span></p>
                <p><strong>Type:</strong> {$request['type_name']}</p>
                <p><strong>Status:</strong> <span style='color: #28a745; font-weight: bold;'>APPROVED</span></p>
            </div>
            
            <p>Your request will now be processed by the accounting team. You will receive further updates on the progress.</p>
        ";
    }
    
    /**
     * Get request rejected email template
     * @param array $data
     * @return string
     */
    private function getRequestRejectedTemplate($data) {
        $request = $data['request'];
        
        return "
            <h2>Hello {$data['requester_name']},</h2>
            <p>We regret to inform you that your financial request has been rejected:</p>
            
            <div class='request-details'>
                <h3>{$request['title']}</h3>
                <p><strong>Request Number:</strong> {$request['request_number']}</p>
                <p><strong>Amount:</strong> <span class='highlight'>" . formatCurrency($request['amount']) . "</span></p>
                <p><strong>Type:</strong> {$request['type_name']}</p>
                <p><strong>Status:</strong> <span style='color: #dc3545; font-weight: bold;'>REJECTED</span></p>
                " . (!empty($request['rejection_reason']) ? "<p><strong>Reason:</strong> {$request['rejection_reason']}</p>" : "") . "
            </div>
            
            <p>If you have questions about this decision, please contact your manager or submit a new request with additional information.</p>
        ";
    }
    
    /**
     * Clean old notifications
     * @param int $daysOld
     * @return bool
     */
    public function cleanOldNotifications($daysOld = 90) {
        return $this->db->delete('notifications',
            'created_at < DATE_SUB(NOW(), INTERVAL :days DAY) AND is_read = 1',
            [':days' => $daysOld]
        );
    }
    
    /**
     * Send bulk notifications
     * @param array $userIds
     * @param string $type
     * @param string $title
     * @param string $message
     * @return bool
     */
    public function sendBulkNotifications($userIds, $type, $title, $message) {
        foreach ($userIds as $userId) {
            $this->create($userId, $type, $title, $message);
        }
        
        return true;
    }
}
?>