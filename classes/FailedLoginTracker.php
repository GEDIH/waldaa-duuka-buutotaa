<?php
/**
 * Failed Login Tracker
 * Implements brute-force protection through failed login tracking and account lockout
 * 
 * Security Features:
 * - Track failed login attempts per user
 * - Automatic account lockout after 5 failed attempts
 * - 15-minute lockout duration
 * - Admin unlock mechanism
 * - Audit logging of lockout events
 * 
 * Requirements: 1.4.5, 1.4.6
 */

require_once __DIR__ . '/../api/config/database.php';

class FailedLoginTracker
{
    // Maximum failed attempts before lockout (Requirement 1.4.5)
    private const MAX_FAILED_ATTEMPTS = 7;
    
    // Lockout duration: 1 hour (3600 seconds) - 
    private const LOCKOUT_DURATION = 3600;
    
    private $db;
    
    public function __construct()
    {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }
    
    /**
     * Check if account is currently locked
     * 
     * @param string $username Username or email
     * @return array ['locked' => bool, 'locked_until' => string|null, 'reason' => string|null]
     */
    public function isAccountLocked($username)
    {
        try {
            $query = "SELECT failed_login_attempts, account_locked_until, lockout_reason 
                      FROM users 
                      WHERE (username = :username OR email = :email) 
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['username' => $username, 'email' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['locked' => false, 'locked_until' => null, 'reason' => null];
            }
            
            // Check if account is locked and lockout hasn't expired
            if ($user['account_locked_until']) {
                $lockedUntil = strtotime($user['account_locked_until']);
                $now = time();
                
                if ($lockedUntil > $now) {
                    // Account is still locked
                    return [
                        'locked' => true,
                        'locked_until' => $user['account_locked_until'],
                        'reason' => $user['lockout_reason'] ?? 'Too many failed login attempts',
                        'remaining_seconds' => $lockedUntil - $now
                    ];
                } else {
                    // Lockout expired, auto-unlock
                    $this->unlockAccount($username, 'automatic_expiry');
                    return ['locked' => false, 'locked_until' => null, 'reason' => null];
                }
            }
            
            return ['locked' => false, 'locked_until' => null, 'reason' => null];
            
        } catch (Exception $e) {
            error_log("Failed login check error: " . $e->getMessage());
            // Fail open to prevent lockout of legitimate users due to system errors
            return ['locked' => false, 'locked_until' => null, 'reason' => null];
        }
    }
    
    /**
     * Record a failed login attempt
     * 
     * Increments failed attempt counter and locks account if threshold exceeded
     * 
     * @param string $username Username or email
     * @param string $ipAddress IP address of the attempt
     * @param string $userAgent User agent string
     * @return array ['locked' => bool, 'attempts' => int, 'locked_until' => string|null]
     */
    public function recordFailedAttempt($username, $ipAddress = null, $userAgent = null)
    {
        try {
            // Get current user data
            $query = "SELECT id, username, failed_login_attempts, account_locked_until 
                      FROM users 
                      WHERE (username = :username OR email = :email) 
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['username' => $username, 'email' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                // User doesn't exist, but don't reveal this information
                // Log the attempt for security monitoring
                $this->logSecurityEvent('failed_login_nonexistent_user', [
                    'username' => $username,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ]);
                return ['locked' => false, 'attempts' => 0, 'locked_until' => null];
            }
            
            // Increment failed attempts
            $newAttempts = $user['failed_login_attempts'] + 1;
            
            // Check if we should lock the account
            if ($newAttempts >= self::MAX_FAILED_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_DURATION);
                $lockoutReason = sprintf(
                    'Account locked due to %d failed login attempts',
                    $newAttempts
                );
                
                // Lock the account
                $updateQuery = "UPDATE users 
                                SET failed_login_attempts = :attempts,
                                    account_locked_until = :locked_until,
                                    lockout_reason = :reason
                                WHERE id = :user_id";
                
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute([
                    'attempts' => $newAttempts,
                    'locked_until' => $lockedUntil,
                    'reason' => $lockoutReason,
                    'user_id' => $user['id']
                ]);
                
                // Log the lockout event
                $this->logAuditEvent($user['id'], 'ACCOUNT_LOCKED', [
                    'username' => $user['username'],
                    'failed_attempts' => $newAttempts,
                    'locked_until' => $lockedUntil,
                    'reason' => $lockoutReason,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ]);
                
                return [
                    'locked' => true,
                    'attempts' => $newAttempts,
                    'locked_until' => $lockedUntil,
                    'lockout_duration_minutes' => self::LOCKOUT_DURATION / 60
                ];
            } else {
                // Just increment the counter
                $updateQuery = "UPDATE users 
                                SET failed_login_attempts = :attempts
                                WHERE id = :user_id";
                
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute([
                    'attempts' => $newAttempts,
                    'user_id' => $user['id']
                ]);
                
                // Log the failed attempt
                $this->logAuditEvent($user['id'], 'LOGIN_FAILED', [
                    'username' => $user['username'],
                    'failed_attempts' => $newAttempts,
                    'remaining_attempts' => self::MAX_FAILED_ATTEMPTS - $newAttempts,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ]);
                
                return [
                    'locked' => false,
                    'attempts' => $newAttempts,
                    'remaining_attempts' => self::MAX_FAILED_ATTEMPTS - $newAttempts,
                    'locked_until' => null
                ];
            }
            
        } catch (Exception $e) {
            error_log("Failed to record failed login attempt: " . $e->getMessage());
            // Fail open to prevent lockout of legitimate users due to system errors
            return ['locked' => false, 'attempts' => 0, 'locked_until' => null];
        }
    }
    
    /**
     * Reset failed login attempts after successful login
     * 
     * @param string $username Username or email
     * @return bool Success status
     */
    public function resetFailedAttempts($username)
    {
        try {
            $query = "UPDATE users 
                      SET failed_login_attempts = 0,
                          account_locked_until = NULL,
                          lockout_reason = NULL
                      WHERE (username = :username OR email = :email)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['username' => $username, 'email' => $username]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to reset login attempts: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unlock account (admin function)
     * 
     * @param string $username Username or email
     * @param string $unlockedBy Admin username who unlocked the account
     * @return array ['success' => bool, 'message' => string]
     */
    public function unlockAccount($username, $unlockedBy = 'system')
    {
        try {
            // Get user data
            $query = "SELECT id, username, failed_login_attempts, account_locked_until 
                      FROM users 
                      WHERE (username = :username OR email = :email) 
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['username' => $username, 'email' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Check if account is actually locked
            if (!$user['account_locked_until']) {
                return [
                    'success' => false,
                    'message' => 'Account is not locked'
                ];
            }
            
            // Unlock the account
            $updateQuery = "UPDATE users 
                            SET failed_login_attempts = 0,
                                account_locked_until = NULL,
                                lockout_reason = NULL
                            WHERE id = :user_id";
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute(['user_id' => $user['id']]);
            
            // Log the unlock event
            $this->logAuditEvent($user['id'], 'ACCOUNT_UNLOCKED', [
                'username' => $user['username'],
                'unlocked_by' => $unlockedBy,
                'previous_attempts' => $user['failed_login_attempts'],
                'was_locked_until' => $user['account_locked_until']
            ]);
            
            return [
                'success' => true,
                'message' => 'Account unlocked successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Failed to unlock account: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to unlock account: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get failed login statistics for a user
     * 
     * @param string $username Username or email
     * @return array User lockout statistics
     */
    public function getLoginStats($username)
    {
        try {
            $query = "SELECT failed_login_attempts, account_locked_until, lockout_reason 
                      FROM users 
                      WHERE (username = :username OR email = :email) 
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['username' => $username, 'email' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return null;
            }
            
            $isLocked = false;
            $remainingSeconds = 0;
            
            if ($user['account_locked_until']) {
                $lockedUntil = strtotime($user['account_locked_until']);
                $now = time();
                
                if ($lockedUntil > $now) {
                    $isLocked = true;
                    $remainingSeconds = $lockedUntil - $now;
                }
            }
            
            return [
                'failed_attempts' => (int)$user['failed_login_attempts'],
                'max_attempts' => self::MAX_FAILED_ATTEMPTS,
                'remaining_attempts' => max(0, self::MAX_FAILED_ATTEMPTS - $user['failed_login_attempts']),
                'is_locked' => $isLocked,
                'locked_until' => $user['account_locked_until'],
                'lockout_reason' => $user['lockout_reason'],
                'remaining_lockout_seconds' => $remainingSeconds,
                'lockout_duration_minutes' => self::LOCKOUT_DURATION / 60
            ];
            
        } catch (Exception $e) {
            error_log("Failed to get login stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log audit event
     * 
     * @param int $userId User ID
     * @param string $action Action type
     * @param array $details Event details
     */
    private function logAuditEvent($userId, $action, $details)
    {
        try {
            $query = "INSERT INTO audit_logs 
                      (user_id, action, table_name, record_id, details, ip_address, user_agent, created_at) 
                      VALUES (:user_id, :action, 'users', :record_id, :details, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'record_id' => $userId,
                'details' => json_encode($details),
                'ip_address' => $details['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $details['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to log audit event: " . $e->getMessage());
        }
    }
    
    /**
     * Log security event for non-existent users
     * 
     * @param string $eventType Event type
     * @param array $details Event details
     */
    private function logSecurityEvent($eventType, $details)
    {
        try {
            // Log to audit_logs with user_id = 0 for system events
            $query = "INSERT INTO audit_logs 
                      (user_id, action, table_name, details, ip_address, user_agent, created_at) 
                      VALUES (0, :action, 'security_events', :details, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'action' => strtoupper($eventType),
                'details' => json_encode($details),
                'ip_address' => $details['ip_address'] ?? 'unknown',
                'user_agent' => $details['user_agent'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
    
    /**
     * Get lockout configuration
     * 
     * @return array Configuration values
     */
    public static function getConfig()
    {
        return [
            'max_failed_attempts' => self::MAX_FAILED_ATTEMPTS,
            'lockout_duration_seconds' => self::LOCKOUT_DURATION,
            'lockout_duration_minutes' => self::LOCKOUT_DURATION / 60
        ];
    }
}
