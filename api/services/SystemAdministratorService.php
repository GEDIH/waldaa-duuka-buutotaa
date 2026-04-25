<?php
/**
 * System Administrator Service
 * 
 * Manages system administrator authentication, authorization, and operations.
 * Implements secure credential management and role-based access control
 * for system-level operations.
 * 
 * Features:
 * - Secure system administrator authentication
 * - Role-based permission validation
 * - System operation logging and auditing
 * - Two-factor authentication support
 * - Emergency access procedures
 * - Security clearance validation
 * 
 * @author WDB Development Team
 * @version 1.0.0
 * @since 2024-12-19
 */

require_once __DIR__ . '/../config/database.php';

class SystemAdministratorService {
    private $db;
    private $currentUser;
    private $sessionTimeout = 3600; // 1 hour default
    
    // Security levels
    const SECURITY_LOW = 'low';
    const SECURITY_MEDIUM = 'medium';
    const SECURITY_HIGH = 'high';
    const SECURITY_CRITICAL = 'critical';
    
    // Access levels
    const ACCESS_NONE = 'none';
    const ACCESS_READ = 'read';
    const ACCESS_WRITE = 'write';
    const ACCESS_ADMIN = 'admin';
    const ACCESS_FULL = 'full';
    
    public function __construct() {
        $this->initializeDatabase();
        $this->loadSystemConfiguration();
    }
    
    private function initializeDatabase() {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = $_ENV['DB_NAME'] ?? 'wdb_membership';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASS'] ?? '';
            
            $this->db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    private function loadSystemConfiguration() {
        try {
            $stmt = $this->db->prepare("
                SELECT config_key, config_value, config_type 
                FROM system_configuration 
                WHERE category = 'security'
            ");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($configs as $config) {
                switch ($config['config_key']) {
                    case 'security.session_timeout':
                        $this->sessionTimeout = (int)$config['config_value'];
                        break;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to load system configuration: " . $e->getMessage());
        }
    }
    
    /**
     * Authenticate system administrator
     */
    public function authenticateSystemAdmin($username, $password, $twoFactorCode = null) {
        try {
            // Get user with system_admin role
            $stmt = $this->db->prepare("
                SELECT u.*, sa.admin_code, sa.security_clearance, sa.two_factor_enabled,
                       sa.status as admin_status, sa.last_security_review, sa.security_review_due
                FROM users u
                JOIN system_administrators sa ON u.id = sa.user_id
                WHERE u.username = ? AND u.role = 'system_admin' AND u.status = 'active'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->logSecurityEvent('auth_failed', 'system_admin', [
                    'username' => $username,
                    'reason' => 'user_not_found'
                ], self::SECURITY_HIGH);
                return ['success' => false, 'error' => 'Invalid credentials'];
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $this->logSecurityEvent('auth_blocked', 'system_admin', [
                    'username' => $username,
                    'reason' => 'account_locked'
                ], self::SECURITY_HIGH);
                return ['success' => false, 'error' => 'Account is locked'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->incrementLoginAttempts($user['id']);
                $this->logSecurityEvent('auth_failed', 'system_admin', [
                    'username' => $username,
                    'reason' => 'invalid_password'
                ], self::SECURITY_HIGH);
                return ['success' => false, 'error' => 'Invalid credentials'];
            }
            
            // Check two-factor authentication if enabled
            if ($user['two_factor_enabled'] && !$this->verifyTwoFactorCode($user['id'], $twoFactorCode)) {
                $this->logSecurityEvent('auth_failed', 'system_admin', [
                    'username' => $username,
                    'reason' => 'invalid_2fa'
                ], self::SECURITY_HIGH);
                return ['success' => false, 'error' => 'Invalid two-factor authentication code'];
            }
            
            // Check security review status
            if ($user['security_review_due'] && strtotime($user['security_review_due']) < time()) {
                $this->logSecurityEvent('auth_warning', 'system_admin', [
                    'username' => $username,
                    'reason' => 'security_review_overdue'
                ], self::SECURITY_MEDIUM);
                // Allow login but flag for review
            }
            
            // Reset login attempts on successful authentication
            $this->resetLoginAttempts($user['id']);
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Create secure session
            $sessionData = $this->createSecureSession($user);
            
            // Log successful authentication
            $this->logSecurityEvent('auth_success', 'system_admin', [
                'username' => $username,
                'admin_code' => $user['admin_code'],
                'security_clearance' => $user['security_clearance']
            ], self::SECURITY_MEDIUM);
            
            return [
                'success' => true,
                'user' => $this->sanitizeUserData($user),
                'session' => $sessionData,
                'permissions' => $this->getUserPermissions($user['role'])
            ];
            
        } catch (Exception $e) {
            error_log("System admin authentication error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Authentication failed'];
        }
    }
    
    /**
     * Validate system administrator session
     */
    public function validateSession($sessionToken) {
        try {
            if (!$sessionToken) {
                return ['success' => false, 'error' => 'No session token provided'];
            }
            
            // Decode and verify session token
            $sessionData = $this->decodeSessionToken($sessionToken);
            if (!$sessionData) {
                return ['success' => false, 'error' => 'Invalid session token'];
            }
            
            // Check session expiration
            if ($sessionData['expires_at'] < time()) {
                $this->logSecurityEvent('session_expired', 'system_admin', [
                    'user_id' => $sessionData['user_id']
                ], self::SECURITY_LOW);
                return ['success' => false, 'error' => 'Session expired'];
            }
            
            // Get current user data
            $stmt = $this->db->prepare("
                SELECT u.*, sa.admin_code, sa.security_clearance, sa.status as admin_status
                FROM users u
                JOIN system_administrators sa ON u.id = sa.user_id
                WHERE u.id = ? AND u.role = 'system_admin' AND u.status = 'active'
            ");
            $stmt->execute([$sessionData['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'error' => 'User not found or inactive'];
            }
            
            $this->currentUser = $user;
            
            return [
                'success' => true,
                'user' => $this->sanitizeUserData($user),
                'permissions' => $this->getUserPermissions($user['role'])
            ];
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Session validation failed'];
        }
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission($permissionName, $requiredAccessLevel = self::ACCESS_READ) {
        if (!$this->currentUser) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT rp.access_level, sp.is_system_critical
                FROM role_permissions rp
                JOIN system_permissions sp ON rp.permission_id = sp.id
                WHERE rp.role_name = ? AND sp.permission_name = ?
            ");
            $stmt->execute([$this->currentUser['role'], $permissionName]);
            $permission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$permission) {
                return false;
            }
            
            // Check access level hierarchy
            $accessLevels = [
                self::ACCESS_NONE => 0,
                self::ACCESS_READ => 1,
                self::ACCESS_WRITE => 2,
                self::ACCESS_ADMIN => 3,
                self::ACCESS_FULL => 4
            ];
            
            $userLevel = $accessLevels[$permission['access_level']] ?? 0;
            $requiredLevel = $accessLevels[$requiredAccessLevel] ?? 1;
            
            return $userLevel >= $requiredLevel;
            
        } catch (Exception $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log system operation
     */
    public function logSystemOperation($operationType, $operationCategory, $resourceAffected = null, $operationDetails = [], $securityLevel = self::SECURITY_MEDIUM) {
        try {
            $operationId = $this->generateOperationId();
            
            $stmt = $this->db->prepare("
                INSERT INTO system_operations_log (
                    operation_id, user_id, user_role, operation_type, operation_category,
                    resource_affected, operation_details, ip_address, user_agent,
                    session_id, success, security_level, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $operationId,
                $this->currentUser['id'] ?? 0,
                $this->currentUser['role'] ?? 'unknown',
                $operationType,
                $operationCategory,
                $resourceAffected,
                json_encode($operationDetails),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                session_id(),
                true,
                $securityLevel
            ]);
            
            return $operationId;
            
        } catch (Exception $e) {
            error_log("Failed to log system operation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get system configuration value
     */
    public function getSystemConfig($configKey, $defaultValue = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT config_value, config_type, is_sensitive
                FROM system_configuration
                WHERE config_key = ?
            ");
            $stmt->execute([$configKey]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                return $defaultValue;
            }
            
            // Check if user has permission to access sensitive config
            if ($config['is_sensitive'] && !$this->hasPermission('system.config.view', self::ACCESS_ADMIN)) {
                throw new Exception('Access denied to sensitive configuration');
            }
            
            // Convert value based on type
            switch ($config['config_type']) {
                case 'boolean':
                    return filter_var($config['config_value'], FILTER_VALIDATE_BOOLEAN);
                case 'integer':
                    return (int)$config['config_value'];
                case 'json':
                    return json_decode($config['config_value'], true);
                case 'encrypted':
                    return $this->decryptConfigValue($config['config_value']);
                default:
                    return $config['config_value'];
            }
            
        } catch (Exception $e) {
            error_log("Failed to get system config: " . $e->getMessage());
            return $defaultValue;
        }
    }
    
    /**
     * Set system configuration value
     */
    public function setSystemConfig($configKey, $configValue, $configType = 'string') {
        if (!$this->hasPermission('system.config.modify', self::ACCESS_ADMIN)) {
            throw new Exception('Access denied to modify system configuration');
        }
        
        try {
            // Encrypt sensitive values
            if ($configType === 'encrypted') {
                $configValue = $this->encryptConfigValue($configValue);
            } elseif ($configType === 'json') {
                $configValue = json_encode($configValue);
            } elseif ($configType === 'boolean') {
                $configValue = $configValue ? 'true' : 'false';
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO system_configuration (config_key, config_value, config_type, last_modified_by, last_modified_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                config_value = VALUES(config_value),
                config_type = VALUES(config_type),
                last_modified_by = VALUES(last_modified_by),
                last_modified_at = NOW()
            ");
            
            $stmt->execute([$configKey, $configValue, $configType, $this->currentUser['id']]);
            
            // Log configuration change
            $this->logSystemOperation('config_update', 'system_config', $configKey, [
                'config_key' => $configKey,
                'config_type' => $configType,
                'is_sensitive' => $configType === 'encrypted'
            ], self::SECURITY_HIGH);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to set system config: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get user permissions
     */
    private function getUserPermissions($role) {
        try {
            $stmt = $this->db->prepare("
                SELECT sp.permission_name, sp.permission_category, rp.access_level, sp.is_system_critical
                FROM role_permissions rp
                JOIN system_permissions sp ON rp.permission_id = sp.id
                WHERE rp.role_name = ?
                ORDER BY sp.permission_category, sp.permission_name
            ");
            $stmt->execute([$role]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get user permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create secure session
     */
    private function createSecureSession($user) {
        $sessionData = [
            'user_id' => $user['id'],
            'admin_code' => $user['admin_code'],
            'role' => $user['role'],
            'security_clearance' => $user['security_clearance'],
            'created_at' => time(),
            'expires_at' => time() + $this->sessionTimeout,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $sessionToken = $this->encodeSessionToken($sessionData);
        
        // Store session in database for tracking
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at, created_at)
                VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), NOW())
                ON DUPLICATE KEY UPDATE
                session_token = VALUES(session_token),
                expires_at = VALUES(expires_at),
                last_activity = NOW()
            ");
            $stmt->execute([
                $user['id'],
                hash('sha256', $sessionToken),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $sessionData['expires_at']
            ]);
        } catch (Exception $e) {
            error_log("Failed to store session: " . $e->getMessage());
        }
        
        return [
            'token' => $sessionToken,
            'expires_at' => $sessionData['expires_at']
        ];
    }
    
    /**
     * Encode session token
     */
    private function encodeSessionToken($data) {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(json_encode($data), 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decode session token
     */
    private function decodeSessionToken($token) {
        try {
            $key = $this->getEncryptionKey();
            $data = base64_decode($token);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            return json_decode($decrypted, true);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get encryption key
     */
    private function getEncryptionKey() {
        return hash('sha256', $_ENV['APP_KEY'] ?? 'default-key-change-in-production', true);
    }
    
    /**
     * Encrypt configuration value
     */
    private function encryptConfigValue($value) {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt configuration value
     */
    private function decryptConfigValue($encryptedValue) {
        try {
            $key = $this->getEncryptionKey();
            $data = base64_decode($encryptedValue);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Verify two-factor authentication code
     */
    private function verifyTwoFactorCode($userId, $code) {
        // Implement your 2FA verification logic here
        // This could integrate with Google Authenticator, SMS, or email-based 2FA
        return true; // Placeholder - implement actual 2FA verification
    }
    
    /**
     * Increment login attempts
     */
    private function incrementLoginAttempts($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET login_attempts = login_attempts + 1,
                    locked_until = CASE 
                        WHEN login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        ELSE locked_until
                    END
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to increment login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Reset login attempts
     */
    private function resetLoginAttempts($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET login_attempts = 0, locked_until = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to reset login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Update last login
     */
    private function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }
    
    /**
     * Log security event
     */
    private function logSecurityEvent($eventType, $userRole, $details, $securityLevel) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_operations_log (
                    operation_id, user_id, user_role, operation_type, operation_category,
                    operation_details, ip_address, user_agent, success, security_level, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->generateOperationId(),
                $details['user_id'] ?? 0,
                $userRole,
                $eventType,
                'security',
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $eventType === 'auth_success',
                $securityLevel
            ]);
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
    
    /**
     * Generate unique operation ID
     */
    private function generateOperationId() {
        return 'OP_' . date('Ymd_His') . '_' . substr(uniqid(), -6);
    }
    
    /**
     * Sanitize user data for response
     */
    private function sanitizeUserData($user) {
        unset($user['password_hash']);
        return $user;
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        return $this->currentUser;
    }
    
    /**
     * Logout system administrator
     */
    public function logout($sessionToken) {
        try {
            // Invalidate session in database
            $stmt = $this->db->prepare("
                UPDATE user_sessions 
                SET expires_at = NOW() 
                WHERE session_token = ?
            ");
            $stmt->execute([hash('sha256', $sessionToken)]);
            
            // Log logout
            if ($this->currentUser) {
                $this->logSystemOperation('logout', 'security', null, [
                    'admin_code' => $this->currentUser['admin_code']
                ], self::SECURITY_LOW);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Logout failed'];
        }
    }
}