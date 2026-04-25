<?php
/**
 * SecurityManager - Centralized security operations for analytics dashboard
 * 
 * Provides role-based access control, additional authentication for sensitive data,
 * data masking, and comprehensive audit logging for analytics operations.
 */

class SecurityManager {
    private $db;
    private $auditLogger;
    
    // Role hierarchy and permissions
    private const ROLE_HIERARCHY = [
        'superadmin' => ['admin', 'user'],
        'admin' => ['user'],
        'user' => []
    ];
    
    // Analytics permission definitions
    private const ANALYTICS_PERMISSIONS = [
        'view_basic_analytics' => ['user', 'admin', 'superadmin'],
        'view_financial_analytics' => ['admin', 'superadmin'],
        'view_all_centers' => ['superadmin'],
        'export_data' => ['admin', 'superadmin'],
        'export_sensitive_data' => ['superadmin'],
        'manage_reports' => ['admin', 'superadmin'],
        'view_audit_logs' => ['superadmin']
    ];
    
    // Sensitive data fields requiring additional authentication
    private const SENSITIVE_FIELDS = [
        'email', 'mobile_phone', 'phone', 'address', 'spouse_phone',
        'payment_reference', 'reference_number', 'receipt_number'
    ];
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Check if user has permission for specific analytics operation
     */
    public function hasPermission($userId, $permission) {
        $userRole = $this->getUserRole($userId);
        
        if (!isset(self::ANALYTICS_PERMISSIONS[$permission])) {
            return false;
        }
        
        $allowedRoles = self::ANALYTICS_PERMISSIONS[$permission];
        return in_array($userRole, $allowedRoles);
    }
    
    /**
     * Get user role from database or session
     */
    private function getUserRole($userId) {
        if (isset($_SESSION['role'])) {
            return $_SESSION['role'];
        }
        
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['role'];
        }
        
        return 'user'; // Default role
    }
    
    /**
     * Get centers accessible by user based on role and assignments
     */
    public function getAccessibleCenters($userId) {
        $userRole = $this->getUserRole($userId);
        
        // Superadmins can access all centers
        if ($userRole === 'superadmin') {
            $stmt = $this->db->prepare("SELECT id FROM centers WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $centers = [];
            while ($row = $result->fetch_assoc()) {
                $centers[] = $row['id'];
            }
            return $centers;
        }
        
        // Admins can access assigned centers
        if ($userRole === 'admin') {
            $stmt = $this->db->prepare("
                SELECT DISTINCT c.id 
                FROM centers c
                INNER JOIN admin_centers ac ON c.id = ac.center_id
                INNER JOIN administrators a ON ac.admin_id = a.id
                WHERE a.user_id = ? AND c.status = 'active'
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $centers = [];
            while ($row = $result->fetch_assoc()) {
                $centers[] = $row['id'];
            }
            return $centers;
        }
        
        // Regular users can only access their own center
        $stmt = $this->db->prepare("
            SELECT center_id FROM members WHERE user_id = ? AND status = 'active'
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return [$row['center_id']];
        }
        
        return [];
    }
    
    /**
     * Require additional authentication for sensitive data access
     */
    public function requireAdditionalAuth($userId, $dataType) {
        // Check if additional auth is already verified in session
        $sessionKey = "additional_auth_{$dataType}";
        
        if (isset($_SESSION[$sessionKey]) && 
            $_SESSION[$sessionKey] === true &&
            isset($_SESSION['additional_auth_time']) &&
            (time() - $_SESSION['additional_auth_time']) < 300) { // 5 minutes
            return true;
        }
        
        // Log the access attempt
        $this->logSensitiveAccess($userId, $dataType, 'additional_auth_required');
        
        return false;
    }
    
    /**
     * Verify additional authentication (e.g., password re-entry)
     */
    public function verifyAdditionalAuth($userId, $password, $dataType) {
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                // Set session flag for additional auth
                $_SESSION["additional_auth_{$dataType}"] = true;
                $_SESSION['additional_auth_time'] = time();
                
                $this->logSensitiveAccess($userId, $dataType, 'additional_auth_success');
                return true;
            }
        }
        
        $this->logSensitiveAccess($userId, $dataType, 'additional_auth_failed');
        return false;
    }
    
    /**
     * Mask sensitive data fields for privacy protection
     */
    public function maskSensitiveData($data, $maskLevel = 'partial') {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($data as $key => &$value) {
            if (in_array($key, self::SENSITIVE_FIELDS)) {
                $value = $this->maskField($value, $key, $maskLevel);
            } elseif (is_array($value)) {
                $value = $this->maskSensitiveData($value, $maskLevel);
            }
        }
        
        return $data;
    }
    
    /**
     * Mask individual field based on type and level
     */
    private function maskField($value, $fieldType, $maskLevel) {
        if (empty($value)) {
            return $value;
        }
        
        switch ($maskLevel) {
            case 'full':
                return '***MASKED***';
                
            case 'partial':
                if (in_array($fieldType, ['email'])) {
                    return $this->maskEmail($value);
                } elseif (in_array($fieldType, ['mobile_phone', 'phone', 'spouse_phone'])) {
                    return $this->maskPhone($value);
                } elseif (in_array($fieldType, ['address'])) {
                    return $this->maskAddress($value);
                } else {
                    return $this->maskGeneric($value);
                }
                
            case 'none':
            default:
                return $value;
        }
    }
    
    /**
     * Mask email address (show first 2 chars and domain)
     */
    private function maskEmail($email) {
        if (strpos($email, '@') === false) {
            return $this->maskGeneric($email);
        }
        
        list($local, $domain) = explode('@', $email);
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2));
        return $maskedLocal . '@' . $domain;
    }
    
    /**
     * Mask phone number (show last 4 digits)
     */
    private function maskPhone($phone) {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned) < 4) {
            return str_repeat('*', strlen($phone));
        }
        
        $lastFour = substr($cleaned, -4);
        return str_repeat('*', strlen($cleaned) - 4) . $lastFour;
    }
    
    /**
     * Mask address (show only city/region)
     */
    private function maskAddress($address) {
        $parts = explode(',', $address);
        if (count($parts) > 1) {
            return '***,' . end($parts);
        }
        return '***';
    }
    
    /**
     * Generic masking (show first and last char)
     */
    private function maskGeneric($value) {
        $len = strlen($value);
        if ($len <= 2) {
            return str_repeat('*', $len);
        }
        return $value[0] . str_repeat('*', $len - 2) . $value[$len - 1];
    }
    
    /**
     * Anonymize data for external sharing
     */
    public function anonymizeData($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        // Remove all identifying fields
        $identifyingFields = [
            'id', 'user_id', 'member_id', 'full_name', 'baptism_name', 
            'facebook_name', 'email', 'mobile_phone', 'phone', 'address',
            'spouse_name', 'spouse_phone', 'confession_father', 
            'organization', 'current_church', 'registered_by'
        ];
        
        foreach ($data as $key => &$value) {
            if (in_array($key, $identifyingFields)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $value = $this->anonymizeData($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Log sensitive data access for audit trail
     */
    private function logSensitiveAccess($userId, $dataType, $action) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (action, entity_type, entity_id, user_id, details, ip_address, user_agent)
            VALUES (?, 'analytics_sensitive_data', NULL, ?, ?, ?, ?)
        ");
        
        $details = json_encode([
            'data_type' => $dataType,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param("sisss", $action, $userId, $details, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    /**
     * Log analytics access for audit trail
     */
    public function logAnalyticsAccess($userId, $action, $details = []) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (action, entity_type, entity_id, user_id, details, ip_address, user_agent)
            VALUES (?, 'analytics', NULL, ?, ?, ?, ?)
        ");
        
        $detailsJson = json_encode(array_merge($details, [
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param("sisss", $action, $userId, $detailsJson, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    /**
     * Check if data export is allowed for user
     */
    public function canExportData($userId, $includesSensitiveData = false) {
        if ($includesSensitiveData) {
            return $this->hasPermission($userId, 'export_sensitive_data');
        }
        
        return $this->hasPermission($userId, 'export_data');
    }
    
    /**
     * Apply security filters to analytics query based on user permissions
     */
    public function applySecurityFilters($userId, $filters = []) {
        $accessibleCenters = $this->getAccessibleCenters($userId);
        
        if (empty($accessibleCenters)) {
            // User has no center access - return empty result filter
            $filters['center_id'] = [-1];
        } else {
            // Restrict to accessible centers
            if (isset($filters['center_id'])) {
                // Intersect requested centers with accessible centers
                $requestedCenters = is_array($filters['center_id']) 
                    ? $filters['center_id'] 
                    : [$filters['center_id']];
                    
                $filters['center_id'] = array_intersect($requestedCenters, $accessibleCenters);
                
                if (empty($filters['center_id'])) {
                    $filters['center_id'] = [-1]; // No valid centers
                }
            } else {
                $filters['center_id'] = $accessibleCenters;
            }
        }
        
        return $filters;
    }
    
    /**
     * Validate session and check for timeout
     */
    public function validateSession($userId) {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
            return false;
        }
        
        // Check session timeout (30 minutes of inactivity)
        if (isset($_SESSION['last_activity'])) {
            $inactiveTime = time() - $_SESSION['last_activity'];
            if ($inactiveTime > 1800) { // 30 minutes
                return false;
            }
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Get audit logs for analytics operations
     */
    public function getAuditLogs($filters = [], $limit = 100) {
        $where = ["entity_type IN ('analytics', 'analytics_sensitive_data')"];
        $params = [];
        $types = "";
        
        if (isset($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        if (isset($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
            $types .= "s";
        }
        
        if (isset($filters['start_date'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['start_date'];
            $types .= "s";
        }
        
        if (isset($filters['end_date'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['end_date'];
            $types .= "s";
        }
        
        $sql = "SELECT * FROM audit_logs WHERE " . implode(" AND ", $where) . 
               " ORDER BY created_at DESC LIMIT ?";
        
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        return $logs;
    }
}
