<?php
/**
 * Center Security Enforcement Middleware
 * 
 * This middleware provides real-time enforcement of center-based access control
 * for all administrator operations. It ensures that Center Administrators can
 * only access, modify, and manage data within their assigned centers.
 * 
 * Key Features:
 * - Real-time access validation
 * - Query interception and filtering
 * - Cross-center access prevention
 * - Comprehensive audit logging
 * - Security policy enforcement
 * 
 * @author WDB Security Team
 * @version 1.0.0
 * @since 2024-12-19
 */

require_once __DIR__ . '/../services/CenterAccessControlService.php';
require_once __DIR__ . '/../services/CenterAuditLogger.php';

class CenterSecurityEnforcement {
    
    private $db;
    private $accessControl;
    private $auditLogger;
    private $currentUser;
    private $userCenters;
    private $securityPolicies;
    
    public function __construct($database) {
        $this->db = $database;
        $this->accessControl = new CenterAccessControlService();
        $this->auditLogger = new CenterAuditLogger();
        $this->initializeSecurityPolicies();
    }
    
    /**
     * Initialize security policies
     */
    private function initializeSecurityPolicies() {
        $this->securityPolicies = [
            'enforce_center_isolation' => true,
            'log_all_access_attempts' => true,
            'block_cross_center_access' => true,
            'require_center_validation' => true,
            'enable_real_time_monitoring' => true,
            'max_failed_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'audit_retention_days' => 365
        ];
    }
    
    /**
     * Enforce center-based security for admin operations
     * 
     * @param array $user Current user information
     * @param string $operation Operation being performed
     * @param array $context Operation context (resource type, IDs, etc.)
     * @return array Security enforcement result
     */
    public function enforceSecurityPolicy($user, $operation, $context = []) {
        $this->currentUser = $user;
        
        try {
            // Load user's accessible centers
            $this->userCenters = $this->accessControl->getUserCenters($user['id']);
            
            // Perform security checks
            $securityResult = [
                'allowed' => false,
                'reason' => '',
                'user_id' => $user['id'],
                'operation' => $operation,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s'),
                'security_checks' => []
            ];
            
            // Check 1: Role validation
            $roleCheck = $this->validateUserRole($user, $operation);
            $securityResult['security_checks']['role_validation'] = $roleCheck;
            
            if (!$roleCheck['passed']) {
                $securityResult['reason'] = $roleCheck['reason'];
                $this->logSecurityViolation($securityResult);
                return $securityResult;
            }
            
            // Check 2: Center assignment validation
            $centerCheck = $this->validateCenterAssignment($user, $context);
            $securityResult['security_checks']['center_validation'] = $centerCheck;
            
            if (!$centerCheck['passed']) {
                $securityResult['reason'] = $centerCheck['reason'];
                $this->logSecurityViolation($securityResult);
                return $securityResult;
            }
            
            // Check 3: Resource access validation
            $resourceCheck = $this->validateResourceAccess($user, $operation, $context);
            $securityResult['security_checks']['resource_validation'] = $resourceCheck;
            
            if (!$resourceCheck['passed']) {
                $securityResult['reason'] = $resourceCheck['reason'];
                $this->logSecurityViolation($securityResult);
                return $securityResult;
            }
            
            // Check 4: Cross-center access prevention
            $crossCenterCheck = $this->preventCrossCenterAccess($user, $context);
            $securityResult['security_checks']['cross_center_prevention'] = $crossCenterCheck;
            
            if (!$crossCenterCheck['passed']) {
                $securityResult['reason'] = $crossCenterCheck['reason'];
                $this->logSecurityViolation($securityResult);
                return $securityResult;
            }
            
            // All checks passed
            $securityResult['allowed'] = true;
            $securityResult['reason'] = 'Access granted - all security checks passed';
            
            // Log successful access
            $this->logSuccessfulAccess($securityResult);
            
            return $securityResult;
            
        } catch (Exception $e) {
            $securityResult['allowed'] = false;
            $securityResult['reason'] = 'Security enforcement error: ' . $e->getMessage();
            $this->logSecurityError($securityResult, $e);
            return $securityResult;
        }
    }
    
    /**
     * Validate user role for operation
     */
    private function validateUserRole($user, $operation) {
        $allowedRoles = [
            'member_read' => ['user', 'admin', 'superadmin'],
            'member_write' => ['admin', 'superadmin'],
            'member_delete' => ['admin', 'superadmin'],
            'contribution_read' => ['user', 'admin', 'superadmin'],
            'contribution_write' => ['admin', 'superadmin'],
            'center_read' => ['admin', 'superadmin'],
            'center_write' => ['superadmin'],
            'user_management' => ['superadmin'],
            'system_admin' => ['superadmin']
        ];
        
        $requiredRoles = $allowedRoles[$operation] ?? ['superadmin'];
        
        if (!in_array($user['role'], $requiredRoles)) {
            return [
                'passed' => false,
                'reason' => "Role '{$user['role']}' not authorized for operation '$operation'",
                'required_roles' => $requiredRoles,
                'user_role' => $user['role']
            ];
        }
        
        return [
            'passed' => true,
            'reason' => 'Role validation passed',
            'user_role' => $user['role']
        ];
    }
    
    /**
     * Validate center assignment
     */
    private function validateCenterAssignment($user, $context) {
        // Superadmins bypass center restrictions
        if ($user['role'] === 'superadmin') {
            return [
                'passed' => true,
                'reason' => 'Superadmin access - center restrictions bypassed',
                'bypass' => true
            ];
        }
        
        // Check if user has any center assignments
        if (empty($this->userCenters)) {
            return [
                'passed' => false,
                'reason' => 'User has no center assignments',
                'user_centers' => []
            ];
        }
        
        return [
            'passed' => true,
            'reason' => 'User has valid center assignments',
            'user_centers' => array_column($this->userCenters, 'id')
        ];
    }
    
    /**
     * Validate resource access
     */
    private function validateResourceAccess($user, $operation, $context) {
        $resourceType = $context['resource_type'] ?? '';
        $resourceId = $context['resource_id'] ?? null;
        $centerId = $context['center_id'] ?? null;
        
        // If no specific resource, allow (will be filtered by center)
        if (!$resourceType || !$resourceId) {
            return [
                'passed' => true,
                'reason' => 'No specific resource validation required',
                'resource_type' => $resourceType
            ];
        }
        
        // Validate specific resource access
        switch ($resourceType) {
            case 'member':
                return $this->validateMemberAccess($resourceId);
                
            case 'contribution':
                return $this->validateContributionAccess($resourceId);
                
            case 'center':
                return $this->validateCenterAccess($resourceId);
                
            default:
                return [
                    'passed' => true,
                    'reason' => 'Unknown resource type - allowing with center filtering',
                    'resource_type' => $resourceType
                ];
        }
    }
    
    /**
     * Validate member access
     */
    private function validateMemberAccess($memberId) {
        try {
            $stmt = $this->db->prepare("SELECT center_id FROM members WHERE id = ?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member) {
                return [
                    'passed' => false,
                    'reason' => 'Member not found',
                    'member_id' => $memberId
                ];
            }
            
            // Superadmin bypass
            if ($this->currentUser['role'] === 'superadmin') {
                return [
                    'passed' => true,
                    'reason' => 'Superadmin access to member',
                    'member_center' => $member['center_id']
                ];
            }
            
            // Check if user has access to member's center
            $userCenterIds = array_column($this->userCenters, 'id');
            if (!in_array($member['center_id'], $userCenterIds)) {
                return [
                    'passed' => false,
                    'reason' => 'Access denied - member belongs to unauthorized center',
                    'member_center' => $member['center_id'],
                    'user_centers' => $userCenterIds
                ];
            }
            
            return [
                'passed' => true,
                'reason' => 'Member access authorized',
                'member_center' => $member['center_id']
            ];
            
        } catch (Exception $e) {
            return [
                'passed' => false,
                'reason' => 'Error validating member access: ' . $e->getMessage(),
                'member_id' => $memberId
            ];
        }
    }
    
    /**
     * Validate contribution access
     */
    private function validateContributionAccess($contributionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.id, m.center_id 
                FROM contributions c
                JOIN members m ON c.member_id = m.id
                WHERE c.id = ?
            ");
            $stmt->execute([$contributionId]);
            $contribution = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contribution) {
                return [
                    'passed' => false,
                    'reason' => 'Contribution not found',
                    'contribution_id' => $contributionId
                ];
            }
            
            // Superadmin bypass
            if ($this->currentUser['role'] === 'superadmin') {
                return [
                    'passed' => true,
                    'reason' => 'Superadmin access to contribution',
                    'contribution_center' => $contribution['center_id']
                ];
            }
            
            // Check if user has access to contribution's center
            $userCenterIds = array_column($this->userCenters, 'id');
            if (!in_array($contribution['center_id'], $userCenterIds)) {
                return [
                    'passed' => false,
                    'reason' => 'Access denied - contribution belongs to unauthorized center',
                    'contribution_center' => $contribution['center_id'],
                    'user_centers' => $userCenterIds
                ];
            }
            
            return [
                'passed' => true,
                'reason' => 'Contribution access authorized',
                'contribution_center' => $contribution['center_id']
            ];
            
        } catch (Exception $e) {
            return [
                'passed' => false,
                'reason' => 'Error validating contribution access: ' . $e->getMessage(),
                'contribution_id' => $contributionId
            ];
        }
    }
    
    /**
     * Validate center access
     */
    private function validateCenterAccess($centerId) {
        // Superadmin bypass
        if ($this->currentUser['role'] === 'superadmin') {
            return [
                'passed' => true,
                'reason' => 'Superadmin access to center',
                'center_id' => $centerId
            ];
        }
        
        // Check if user has access to this center
        $userCenterIds = array_column($this->userCenters, 'id');
        if (!in_array($centerId, $userCenterIds)) {
            return [
                'passed' => false,
                'reason' => 'Access denied - unauthorized center access',
                'requested_center' => $centerId,
                'user_centers' => $userCenterIds
            ];
        }
        
        return [
            'passed' => true,
            'reason' => 'Center access authorized',
            'center_id' => $centerId
        ];
    }
    
    /**
     * Prevent cross-center access
     */
    private function preventCrossCenterAccess($user, $context) {
        // Superadmin bypass
        if ($user['role'] === 'superadmin') {
            return [
                'passed' => true,
                'reason' => 'Superadmin - cross-center access allowed',
                'bypass' => true
            ];
        }
        
        $centerId = $context['center_id'] ?? null;
        
        // If no specific center in context, allow (will be filtered)
        if (!$centerId) {
            return [
                'passed' => true,
                'reason' => 'No specific center in context',
                'context_center' => null
            ];
        }
        
        // Check if requested center is in user's assignments
        $userCenterIds = array_column($this->userCenters, 'id');
        if (!in_array($centerId, $userCenterIds)) {
            return [
                'passed' => false,
                'reason' => 'Cross-center access attempt blocked',
                'requested_center' => $centerId,
                'user_centers' => $userCenterIds
            ];
        }
        
        return [
            'passed' => true,
            'reason' => 'Center access within authorized scope',
            'center_id' => $centerId
        ];
    }
    
    /**
     * Filter query results by center access
     */
    public function filterQueryByCenter($query, $params = []) {
        // Superadmin bypass
        if ($this->currentUser['role'] === 'superadmin') {
            return ['query' => $query, 'params' => $params];
        }
        
        // Get user's center IDs
        $userCenterIds = array_column($this->userCenters, 'id');
        
        if (empty($userCenterIds)) {
            // No centers assigned - return empty result query
            return [
                'query' => "SELECT * FROM (SELECT 1) as empty WHERE 1=0",
                'params' => []
            ];
        }
        
        // Add center filter to query
        $centerPlaceholders = str_repeat('?,', count($userCenterIds) - 1) . '?';
        
        // Detect table alias or name for center_id
        $centerColumn = 'center_id';
        if (preg_match('/FROM\s+members\s+(\w+)/i', $query, $matches)) {
            $centerColumn = $matches[1] . '.center_id';
        } elseif (preg_match('/FROM\s+contributions\s+c\s+JOIN\s+members\s+(\w+)/i', $query, $matches)) {
            $centerColumn = $matches[1] . '.center_id';
        }
        
        // Add WHERE clause or extend existing one
        if (stripos($query, 'WHERE') !== false) {
            $query .= " AND $centerColumn IN ($centerPlaceholders)";
        } else {
            $query .= " WHERE $centerColumn IN ($centerPlaceholders)";
        }
        
        // Add center IDs to parameters
        $params = array_merge($params, $userCenterIds);
        
        return ['query' => $query, 'params' => $params];
    }
    
    /**
     * Log security violation
     */
    private function logSecurityViolation($securityResult) {
        try {
            $this->auditLogger->logCenterAccess(
                $securityResult['user_id'],
                $securityResult['context']['resource_type'] ?? 'unknown',
                $securityResult['operation'],
                false,
                $securityResult['reason'],
                $securityResult['context']['center_id'] ?? null
            );
            
            // Check for repeated violations
            $this->checkRepeatedViolations($securityResult['user_id']);
            
        } catch (Exception $e) {
            error_log("Failed to log security violation: " . $e->getMessage());
        }
    }
    
    /**
     * Log successful access
     */
    private function logSuccessfulAccess($securityResult) {
        try {
            $this->auditLogger->logCenterAccess(
                $securityResult['user_id'],
                $securityResult['context']['resource_type'] ?? 'unknown',
                $securityResult['operation'],
                true,
                $securityResult['reason'],
                $securityResult['context']['center_id'] ?? null
            );
            
        } catch (Exception $e) {
            error_log("Failed to log successful access: " . $e->getMessage());
        }
    }
    
    /**
     * Log security error
     */
    private function logSecurityError($securityResult, $exception) {
        try {
            $this->auditLogger->logCenterAccess(
                $securityResult['user_id'],
                'security_system',
                'error',
                false,
                'Security enforcement error: ' . $exception->getMessage(),
                null
            );
            
        } catch (Exception $e) {
            error_log("Failed to log security error: " . $e->getMessage());
        }
    }
    
    /**
     * Check for repeated violations and implement lockout
     */
    private function checkRepeatedViolations($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as violation_count
                FROM access_control_logs
                WHERE user_id = ? 
                AND access_granted = FALSE
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$userId]);
            $violationCount = $stmt->fetch(PDO::FETCH_ASSOC)['violation_count'];
            
            if ($violationCount >= $this->securityPolicies['max_failed_attempts']) {
                $this->implementUserLockout($userId);
            }
            
        } catch (Exception $e) {
            error_log("Failed to check repeated violations: " . $e->getMessage());
        }
    }
    
    /**
     * Implement user lockout
     */
    private function implementUserLockout($userId) {
        try {
            $lockoutUntil = date('Y-m-d H:i:s', time() + $this->securityPolicies['lockout_duration']);
            
            $stmt = $this->db->prepare("
                UPDATE users 
                SET locked_until = ?, login_attempts = login_attempts + 1
                WHERE id = ?
            ");
            $stmt->execute([$lockoutUntil, $userId]);
            
            // Log lockout
            $this->auditLogger->logCenterAccess(
                $userId,
                'security_system',
                'user_lockout',
                true,
                "User locked due to repeated security violations until $lockoutUntil",
                null
            );
            
        } catch (Exception $e) {
            error_log("Failed to implement user lockout: " . $e->getMessage());
        }
    }
    
    /**
     * Get security status for user
     */
    public function getUserSecurityStatus($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.id, u.username, u.role, u.status, u.locked_until,
                    COUNT(ac.center_id) as assigned_centers,
                    (SELECT COUNT(*) FROM access_control_logs 
                     WHERE user_id = u.id AND access_granted = FALSE 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as recent_violations
                FROM users u
                LEFT JOIN admin_centers ac ON u.id = ac.admin_id
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['status' => 'USER_NOT_FOUND'];
            }
            
            $status = [
                'user_id' => $userId,
                'username' => $user['username'],
                'role' => $user['role'],
                'account_status' => $user['status'],
                'is_locked' => $user['locked_until'] && $user['locked_until'] > date('Y-m-d H:i:s'),
                'locked_until' => $user['locked_until'],
                'assigned_centers' => $user['assigned_centers'],
                'recent_violations' => $user['recent_violations'],
                'security_level' => $this->calculateSecurityLevel($user)
            ];
            
            return $status;
            
        } catch (Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Calculate security level for user
     */
    private function calculateSecurityLevel($user) {
        $score = 100;
        
        // Deduct points for violations
        $score -= $user['recent_violations'] * 10;
        
        // Deduct points if locked
        if ($user['locked_until'] && $user['locked_until'] > date('Y-m-d H:i:s')) {
            $score -= 30;
        }
        
        // Deduct points for no center assignments (if admin)
        if ($user['role'] === 'admin' && $user['assigned_centers'] == 0) {
            $score -= 20;
        }
        
        // Determine level
        if ($score >= 90) return 'HIGH';
        if ($score >= 70) return 'MEDIUM';
        if ($score >= 50) return 'LOW';
        return 'CRITICAL';
    }
}