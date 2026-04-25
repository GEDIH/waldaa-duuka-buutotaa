<?php
/**
 * Analytics Session Manager
 * 
 * Manages secure sessions for analytics operations with automatic logout
 * and secure token handling.
 * 
 * Feature: wdb-advanced-analytics
 * Requirements: 12.5
 */

class AnalyticsSessionManager {
    private $sessionTimeout = 1800; // 30 minutes default
    private $warningTime = 300; // 5 minutes before timeout
    private $db;
    
    // Session activity types
    const ACTIVITY_VIEW = 'view';
    const ACTIVITY_EXPORT = 'export';
    const ACTIVITY_FILTER = 'filter';
    const ACTIVITY_REPORT = 'report';
    
    public function __construct($db = null) {
        if ($db === null) {
            require_once __DIR__ . '/../api/config/database.php';
            $this->db = Database::getInstance()->getConnection();
        } else {
            $this->db = $db;
        }
        
        // Configure session settings
        $this->configureSession();
    }
    
    /**
     * Configure secure session settings
     */
    private function configureSession() {
        // Only configure if session not already started
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1); // HTTPS only
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', $this->sessionTimeout);
            
            // Regenerate session ID periodically
            session_start();
            
            // Initialize session if new
            if (!isset($_SESSION['analytics_initialized'])) {
                $this->initializeSession();
            }
        }
    }
    
    /**
     * Initialize analytics session
     */
    private function initializeSession() {
        $_SESSION['analytics_initialized'] = true;
        $_SESSION['analytics_start_time'] = time();
        $_SESSION['analytics_last_activity'] = time();
        $_SESSION['analytics_token'] = $this->generateSecureToken();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
    }
    
    /**
     * Generate secure session token
     */
    private function generateSecureToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Start analytics session
     * 
     * @param int $userId User ID
     * @param string $role User role
     * @return array Session information
     */
    public function startSession(int $userId, string $role): array {
        // Set role-specific timeout
        $this->setRoleBasedTimeout($role);
        
        // Initialize session data
        $_SESSION['analytics_user_id'] = $userId;
        $_SESSION['analytics_role'] = $role;
        $_SESSION['analytics_start_time'] = time();
        $_SESSION['analytics_last_activity'] = time();
        $_SESSION['analytics_token'] = $this->generateSecureToken();
        
        return [
            'session_id' => session_id(),
            'token' => $_SESSION['analytics_token'],
            'timeout' => $this->sessionTimeout,
            'warning_time' => $this->warningTime,
            'started_at' => $_SESSION['analytics_start_time']
        ];
    }
    
    /**
     * Set role-based session timeout
     */
    private function setRoleBasedTimeout(string $role) {
        switch ($role) {
            case 'superadmin':
                $this->sessionTimeout = 3600; // 1 hour
                break;
            case 'admin':
                $this->sessionTimeout = 1800; // 30 minutes
                break;
            default:
                $this->sessionTimeout = 900; // 15 minutes
                break;
        }
    }
    
    /**
     * Update session activity
     * 
     * @param string $activityType Type of activity
     * @param array $metadata Additional metadata
     * @return bool Success status
     */
    public function updateActivity(string $activityType, array $metadata = []): bool {
        if (!$this->isSessionActive()) {
            return false;
        }
        
        $_SESSION['analytics_last_activity'] = time();
        $_SESSION['analytics_last_activity_type'] = $activityType;
        
        // Log activity for audit trail
        $this->logActivity($activityType, $metadata);
        
        return true;
    }
    
    /**
     * Check if session is active
     * 
     * @return bool True if session is active
     */
    public function isSessionActive(): bool {
        if (!isset($_SESSION['analytics_initialized'])) {
            return false;
        }
        
        $lastActivity = $_SESSION['analytics_last_activity'] ?? 0;
        $elapsed = time() - $lastActivity;
        
        return $elapsed < $this->sessionTimeout;
    }
    
    /**
     * Get session status
     * 
     * @return array Session status information
     */
    public function getSessionStatus(): array {
        if (!isset($_SESSION['analytics_initialized'])) {
            return [
                'active' => false,
                'message' => 'No active session'
            ];
        }
        
        $lastActivity = $_SESSION['analytics_last_activity'] ?? 0;
        $elapsed = time() - $lastActivity;
        $remaining = $this->sessionTimeout - $elapsed;
        
        return [
            'active' => $this->isSessionActive(),
            'user_id' => $_SESSION['analytics_user_id'] ?? null,
            'role' => $_SESSION['analytics_role'] ?? null,
            'elapsed' => $elapsed,
            'remaining' => max(0, $remaining),
            'timeout' => $this->sessionTimeout,
            'warning' => $remaining <= $this->warningTime,
            'last_activity' => $lastActivity,
            'last_activity_type' => $_SESSION['analytics_last_activity_type'] ?? null
        ];
    }
    
    /**
     * Extend session
     * 
     * @return array Updated session status
     */
    public function extendSession(): array {
        if (!$this->isSessionActive()) {
            return [
                'success' => false,
                'message' => 'Session expired'
            ];
        }
        
        $_SESSION['analytics_last_activity'] = time();
        
        // Regenerate token for security
        $_SESSION['analytics_token'] = $this->generateSecureToken();
        
        return [
            'success' => true,
            'token' => $_SESSION['analytics_token'],
            'status' => $this->getSessionStatus()
        ];
    }
    
    /**
     * Validate session token
     * 
     * @param string $token Token to validate
     * @return bool True if valid
     */
    public function validateToken(string $token): bool {
        if (!isset($_SESSION['analytics_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['analytics_token'], $token);
    }
    
    /**
     * End analytics session
     * 
     * @return bool Success status
     */
    public function endSession(): bool {
        // Log session end
        if (isset($_SESSION['analytics_user_id'])) {
            $this->logActivity('session_end', [
                'duration' => time() - ($_SESSION['analytics_start_time'] ?? time())
            ]);
        }
        
        // Clear analytics session data
        unset($_SESSION['analytics_initialized']);
        unset($_SESSION['analytics_user_id']);
        unset($_SESSION['analytics_role']);
        unset($_SESSION['analytics_start_time']);
        unset($_SESSION['analytics_last_activity']);
        unset($_SESSION['analytics_token']);
        unset($_SESSION['analytics_last_activity_type']);
        
        return true;
    }
    
    /**
     * Force logout (automatic timeout)
     * 
     * @return array Logout information
     */
    public function forceLogout(): array {
        $reason = 'timeout';
        $userId = $_SESSION['analytics_user_id'] ?? null;
        
        // Log forced logout
        if ($userId) {
            $this->logActivity('forced_logout', [
                'reason' => $reason,
                'last_activity' => $_SESSION['analytics_last_activity'] ?? null
            ]);
        }
        
        $this->endSession();
        
        return [
            'logged_out' => true,
            'reason' => $reason,
            'message' => 'Session expired due to inactivity'
        ];
    }
    
    /**
     * Log session activity
     * 
     * @param string $activityType Activity type
     * @param array $metadata Additional metadata
     */
    private function logActivity(string $activityType, array $metadata = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO analytics_session_log 
                (user_id, activity_type, metadata, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $_SESSION['analytics_user_id'] ?? null,
                $activityType,
                json_encode($metadata),
                $this->getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("AnalyticsSessionManager: Failed to log activity - " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Get session activity log
     * 
     * @param int $userId Optional user ID filter
     * @param int $limit Number of records
     * @return array Activity log
     */
    public function getActivityLog(int $userId = null, int $limit = 50): array {
        try {
            $sql = "
                SELECT * FROM analytics_session_log
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($userId !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AnalyticsSessionManager: Failed to get activity log - " . $e->getMessage());
            return [];
        }
    }
}
