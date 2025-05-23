<?php
/**
 * User Management Class
 * File: classes/User.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

class User {
    private $db;
    private $id;
    private $username;
    private $email;
    private $firstName;
    private $lastName;
    private $role;
    private $businessId;
    private $permissions;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Create new user
     * @param array $userData
     * @return bool|int
     */
    public function create($userData) {
        // Hash password
        $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        unset($userData['password']);
        
        $userData['created_at'] = date('Y-m-d H:i:s');
        
        $userId = $this->db->insert('users', $userData);
        
        if ($userId) {
            $this->auditLog($userId, AUDIT_CREATE, 'users', $userId, null, $userData);
            return $userId;
        }
        
        return false;
    }
    
    /**
     * Authenticate user
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function authenticate($username, $password) {
        $sql = "SELECT u.*, ur.role_name, ur.permissions, b.name as business_name 
                FROM users u 
                LEFT JOIN user_roles ur ON u.role_id = ur.id 
                LEFT JOIN businesses b ON u.business_id = b.id 
                WHERE u.username = :username AND u.is_active = 1";
        
        $this->db->query($sql);
        $this->db->bind(':username', $username);
        $user = $this->db->single();
        
        // Debugging output
        error_log('Login attempt: ' . $username);
        if ($user) {
            error_log('User found. Verifying password...');
            error_log('Password entered: ' . $password);
            error_log('Password hash in DB: ' . $user['password_hash']);
            if (password_verify($password, $user['password_hash'])) {
                error_log('Password correct!');
            } else {
                error_log('Password incorrect!');
            }
        } else {
            error_log('User not found or inactive.');
        }
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $this->loadUser($user);
            $this->updateLastLogin();
            $this->auditLog($this->id, AUDIT_LOGIN, 'users', $this->id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Load user data
     * @param array $userData
     */
    private function loadUser($userData) {
        $this->id = $userData['id'];
        $this->username = $userData['username'];
        $this->email = $userData['email'];
        $this->firstName = $userData['first_name'];
        $this->lastName = $userData['last_name'];
        $this->role = $userData['role_name'];
        $this->businessId = $userData['business_id'];
        $this->permissions = json_decode($userData['permissions'], true) ?: [];
        
        // Store in session
        $_SESSION['user_id'] = $this->id;
        $_SESSION['username'] = $this->username;
        $_SESSION['role'] = $this->role;
        $_SESSION['business_id'] = $this->businessId;
        $_SESSION['permissions'] = $this->permissions;
        $_SESSION['full_name'] = $this->firstName . ' ' . $this->lastName;
    }
    
    /**
     * Update last login timestamp
     */
    private function updateLastLogin() {
        $this->db->update('users', 
            ['last_login' => date('Y-m-d H:i:s')], 
            'id = :id', 
            [':id' => $this->id]
        );
    }
    
    /**
     * Get user by ID
     * @param int $userId
     * @return array|false
     */
    public function getUserById($userId) {
        $sql = "SELECT u.*, ur.role_name, ur.permissions, b.name as business_name 
                FROM users u 
                LEFT JOIN user_roles ur ON u.role_id = ur.id 
                LEFT JOIN businesses b ON u.business_id = b.id 
                WHERE u.id = :id";
        
        $this->db->query($sql);
        $this->db->bind(':id', $userId);
        return $this->db->single();
    }
    
    /**
     * Update user
     * @param int $userId
     * @param array $userData
     * @return bool
     */
    public function update($userId, $userData) {
        $oldData = $this->getUserById($userId);
        
        if (isset($userData['password'])) {
            $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            unset($userData['password']);
        }
        
        $userData['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $this->db->update('users', $userData, 'id = :id', [':id' => $userId]);
        
        if ($result) {
            $this->auditLog($_SESSION['user_id'] ?? null, AUDIT_UPDATE, 'users', $userId, $oldData, $userData);
        }
        
        return $result;
    }
    
    /**
     * Delete user (soft delete)
     * @param int $userId
     * @return bool
     */
    public function delete($userId) {
        $oldData = $this->getUserById($userId);
        
        $result = $this->db->update('users', 
            ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')], 
            'id = :id', 
            [':id' => $userId]
        );
        
        if ($result) {
            $this->auditLog($_SESSION['user_id'] ?? null, AUDIT_DELETE, 'users', $userId, $oldData);
        }
        
        return $result;
    }
    
    /**
     * Get all users with pagination
     * @param int $page
     * @param int $limit
     * @param string $search
     * @param int $businessId
     * @return array
     */
    public function getAllUsers($page = 1, $limit = 25, $search = '', $businessId = null) {
        $offset = ($page - 1) * $limit;
        $whereConditions = ['u.id > 0'];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        
        if ($businessId !== null) {
            $whereConditions[] = "u.business_id = :business_id";
            $params[':business_id'] = $businessId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT u.*, ur.role_name, b.name as business_name 
                FROM users u 
                LEFT JOIN user_roles ur ON u.role_id = ur.id 
                LEFT JOIN businesses b ON u.business_id = b.id 
                WHERE {$whereClause}
                ORDER BY u.created_at DESC 
                LIMIT {$limit} OFFSET {$offset}";
        
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        
        $users = $this->db->resultSet();
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE {$whereClause}";
        $this->db->query($countSql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $totalResult = $this->db->single();
        $total = $totalResult['total'];
        
        return [
            'users' => $users,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Check if user has permission
     * @param string $permission
     * @param int $userId
     * @return bool
     */
    public function hasPermission($permission, $userId = null) {
        if ($userId === null) {
            $permissions = $_SESSION['permissions'] ?? [];
        } else {
            $user = $this->getUserById($userId);
            $permissions = json_decode($user['permissions'], true) ?: [];
        }
        
        return isset($permissions[$permission]) && $permissions[$permission] === true;
    }
    
    /**
     * Check if user is director
     * @param int $userId
     * @return bool
     */
    public function isDirector($userId = null) {
        if ($userId === null) {
            return $_SESSION['role'] === ROLE_DIRECTOR;
        }
        
        $user = $this->getUserById($userId);
        return $user['role_name'] === ROLE_DIRECTOR;
    }
    
    /**
     * Get directors for business
     * @param int $businessId
     * @return array
     */
    public function getDirectorsForBusiness($businessId = null) {
        $sql = "SELECT u.* FROM users u 
                JOIN user_roles ur ON u.role_id = ur.id 
                WHERE ur.role_name = :role AND u.is_active = 1";
        
        $params = [':role' => ROLE_DIRECTOR];
        
        if ($businessId !== null) {
            $sql .= " AND u.business_id = :business_id";
            $params[':business_id'] = $businessId;
        }
        
        $this->db->query($sql);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        
        return $this->db->resultSet();
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->auditLog($_SESSION['user_id'], AUDIT_LOGOUT, 'users', $_SESSION['user_id']);
        }
        
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     * @return bool
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID
     * @return int|null
     */
    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
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
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('audit_logs', $auditData);
    }
}
?>