<?php
/**
 * Center Administrator Security Audit System
 * 
 * Comprehensive security audit and enforcement system to ensure Center Administrators
 * can only access and manage data within their assigned centers.
 * 
 * This system provides:
 * - Real-time access control validation
 * - Security policy enforcement
 * - Comprehensive audit logging
 * - Cross-center access prevention
 * - Data isolation verification
 * 
 * @author WDB Security Team
 * @version 1.0.0
 * @since 2024-12-19
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/CenterAccessControlService.php';

class CenterAdminSecurityAudit {
    
    private $db;
    private $accessControl;
    private $auditResults;
    private $securityViolations;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->accessControl = new CenterAccessControlService();
        $this->auditResults = [];
        $this->securityViolations = [];
    }
    
    /**
     * Perform comprehensive security audit for center-based access control
     * 
     * @param int $adminUserId Optional specific admin to audit
     * @return array Comprehensive audit results
     */
    public function performSecurityAudit($adminUserId = null) {
        $this->auditResults = [
            'timestamp' => date('Y-m-d H:i:s'),
            'audit_type' => 'center_admin_security_audit',
            'admin_user_id' => $adminUserId,
            'tests_performed' => [],
            'security_violations' => [],
            'recommendations' => [],
            'overall_status' => 'PENDING'
        ];
        
        try {
            // Test 1: Validate center assignments
            $this->auditCenterAssignments($adminUserId);
            
            // Test 2: Verify data isolation
            $this->auditDataIsolation($adminUserId);
            
            // Test 3: Check API endpoint security
            $this->auditAPIEndpointSecurity($adminUserId);
            
            // Test 4: Validate session security
            $this->auditSessionSecurity($adminUserId);
            
            // Test 5: Check cross-center access attempts
            $this->auditCrossCenterAccess($adminUserId);
            
            // Test 6: Verify audit logging
            $this->auditLoggingSystem($adminUserId);
            
            // Generate final assessment
            $this->generateSecurityAssessment();
            
            // Log audit completion
            $this->logAuditCompletion();
            
            return $this->auditResults;
            
        } catch (Exception $e) {
            $this->auditResults['error'] = $e->getMessage();
            $this->auditResults['overall_status'] = 'ERROR';
            error_log("Security audit error: " . $e->getMessage());
            return $this->auditResults;
        }
    }
    
    /**
     * Audit center assignments for administrators
     */
    private function auditCenterAssignments($adminUserId = null) {
        $testName = 'center_assignments_validation';
        $this->auditResults['tests_performed'][] = $testName;
        
        try {
            $whereClause = $adminUserId ? "WHERE u.id = ?" : "WHERE u.role IN ('admin', 'superadmin')";
            $params = $adminUserId ? [$adminUserId] : [];
            
            $stmt = $this->db->prepare("
                SELECT 
                    u.id, u.username, u.role, u.center_id as primary_center,
                    COUNT(ac.center_id) as assigned_centers,
                    GROUP_CONCAT(c.name) as center_names
                FROM users u
                LEFT JOIN admin_centers ac ON u.id = ac.admin_id
                LEFT JOIN centers c ON ac.center_id = c.id
                $whereClause
                GROUP BY u.id
            ");
            $stmt->execute($params);
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($admins as $admin) {
                // Check for admins without center assignments
                if ($admin['role'] === 'admin' && $admin['assigned_centers'] == 0 && !$admin['primary_center']) {
                    $this->securityViolations[] = [
                        'type' => 'MISSING_CENTER_ASSIGNMENT',
                        'severity' => 'HIGH',
                        'admin_id' => $admin['id'],
                        'admin_username' => $admin['username'],
                        'description' => 'Administrator has no center assignments',
                        'recommendation' => 'Assign administrator to appropriate centers'
                    ];
                }
                
                // Check for excessive center assignments (potential security risk)
                if ($admin['assigned_centers'] > 5) {
                    $this->securityViolations[] = [
                        'type' => 'EXCESSIVE_CENTER_ASSIGNMENTS',
                        'severity' => 'MEDIUM',
                        'admin_id' => $admin['id'],
                        'admin_username' => $admin['username'],
                        'assigned_centers' => $admin['assigned_centers'],
                        'description' => 'Administrator has excessive center assignments',
                        'recommendation' => 'Review and limit center assignments to necessary centers only'
                    ];
                }
            }
            
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'COMPLETED',
                'admins_audited' => count($admins),
                'violations_found' => count(array_filter($this->securityViolations, function($v) {
                    return in_array($v['type'], ['MISSING_CENTER_ASSIGNMENT', 'EXCESSIVE_CENTER_ASSIGNMENTS']);
                }))
            ];
            
        } catch (Exception $e) {
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Audit data isolation between centers
     */
    private function auditDataIsolation($adminUserId = null) {
        $testName = 'data_isolation_validation';
        $this->auditResults['tests_performed'][] = $testName;
        
        try {
            // Test member data isolation
            $stmt = $this->db->prepare("
                SELECT 
                    m.center_id,
                    COUNT(*) as member_count,
                    c.name as center_name
                FROM members m
                JOIN centers c ON m.center_id = c.id
                GROUP BY m.center_id
                ORDER BY member_count DESC
            ");
            $stmt->execute();
            $centerData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check for members without center assignments
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as orphaned_members 
                FROM members 
                WHERE center_id IS NULL OR center_id = 0
            ");
            $stmt->execute();
            $orphanedMembers = $stmt->fetch(PDO::FETCH_ASSOC)['orphaned_members'];
            
            if ($orphanedMembers > 0) {
                $this->securityViolations[] = [
                    'type' => 'ORPHANED_MEMBER_DATA',
                    'severity' => 'HIGH',
                    'count' => $orphanedMembers,
                    'description' => 'Members found without center assignments',
                    'recommendation' => 'Assign all members to appropriate centers'
                ];
            }
            
            // Test contribution data isolation
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as orphaned_contributions
                FROM contributions c
                LEFT JOIN members m ON c.member_id = m.id
                WHERE m.center_id IS NULL
            ");
            $stmt->execute();
            $orphanedContributions = $stmt->fetch(PDO::FETCH_ASSOC)['orphaned_contributions'];
            
            if ($orphanedContributions > 0) {
                $this->securityViolations[] = [
                    'type' => 'ORPHANED_CONTRIBUTION_DATA',
                    'severity' => 'MEDIUM',
                    'count' => $orphanedContributions,
                    'description' => 'Contributions found without proper center linkage',
                    'recommendation' => 'Review and fix contribution-member-center relationships'
                ];
            }
            
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'COMPLETED',
                'centers_with_data' => count($centerData),
                'orphaned_members' => $orphanedMembers,
                'orphaned_contributions' => $orphanedContributions
            ];
            
        } catch (Exception $e) {
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Audit API endpoint security
     */
    private function auditAPIEndpointSecurity($adminUserId = null) {
        $testName = 'api_endpoint_security';
        $this->auditResults['tests_performed'][] = $testName;
        
        try {
            // Check for recent access control violations in logs
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    resource_type,
                    action,
                    denial_reason,
                    COUNT(*) as violation_count,
                    MAX(created_at) as last_violation
                FROM access_control_logs
                WHERE access_granted = FALSE
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                " . ($adminUserId ? "AND user_id = ?" : "") . "
                GROUP BY user_id, resource_type, action, denial_reason
                ORDER BY violation_count DESC
            ");
            
            $params = $adminUserId ? [$adminUserId] : [];
            $stmt->execute($params);
            $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($violations as $violation) {
                if ($violation['violation_count'] > 10) {
                    $this->securityViolations[] = [
                        'type' => 'REPEATED_ACCESS_VIOLATIONS',
                        'severity' => 'HIGH',
                        'user_id' => $violation['user_id'],
                        'resource_type' => $violation['resource_type'],
                        'violation_count' => $violation['violation_count'],
                        'last_violation' => $violation['last_violation'],
                        'description' => 'User has repeated access control violations',
                        'recommendation' => 'Review user permissions and investigate potential security breach'
                    ];
                }
            }
            
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'COMPLETED',
                'violations_analyzed' => count($violations),
                'high_risk_users' => count(array_filter($violations, function($v) {
                    return $v['violation_count'] > 10;
                }))
            ];
            
        } catch (Exception $e) {
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Audit session security
     */
    private function auditSessionSecurity($adminUserId = null) {
        $testName = 'session_security_validation';
        $this->auditResults['tests_performed'][] = $testName;
        
        try {
            // Check for expired session cache entries
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as expired_sessions
                FROM session_center_cache
                WHERE expires_at < NOW()
            ");
            $stmt->execute();
            $expiredSessions = $stmt->fetch(PDO::FETCH_ASSOC)['expired_sessions'];
            
            // Check for sessions without proper center assignments
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as invalid_sessions
                FROM session_center_cache scc
                LEFT JOIN users u ON scc.user_id = u.id
                WHERE u.role = 'admin' AND (
                    scc.center_assignments IS NULL OR 
                    scc.center_assignments = '[]' OR
                    scc.primary_center_id IS NULL
                )
            ");
            $stmt->execute();
            $invalidSessions = $stmt->fetch(PDO::FETCH_ASSOC)['invalid_sessions'];
            
            if ($expiredSessions > 100) {
                $this->securityViolations[] = [
                    'type' => 'EXCESSIVE_EXPIRED_SESSIONS',
                    'severity' => 'LOW',
                    'count' => $expiredSessions,
                    'description' => 'Large number of expired session cache entries',
                    'recommendation' => 'Implement automated cleanup of expired sessions'
                ];
            }
            
            if ($invalidSessions > 0) {
                $this->securityViolations[] = [
                    'type' => 'INVALID_ADMIN_SESSIONS',
                    'severity' => 'MEDIUM',
                    'count' => $invalidSessions,
                    'description' => 'Admin sessions found without proper center assignments',
                    'recommendation' => 'Clear invalid sessions and ensure proper center assignment caching'
                ];
            }
            
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'COMPLETED',
                'expired_sessions' => $expiredSessions,
                'invalid_sessions' => $invalidSessions
            ];
            
        } catch (Exception $e) {
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Audit cross-center access attempts
     */
    private function auditCrossCenterAccess($adminUserId = null) {
        $testName = 'cross_center_access_audit';
        $this->auditResults['tests_performed'][] = $testName;
        
        try {
            // Look for suspicious cross-center access patterns
            $stmt = $this->db->prepare("
                SELECT 
                    acl.user_id,
                    u.username,
                    u.role,
                    acl.center_id as attempted_center,
                    COUNT(*) as attempt_count,
                    MAX(acl.created_at) as last_attempt,
                    acl.denial_reason
                FROM access_control_logs acl
                JOIN users u ON acl.user_id = u.id
                WHERE acl.access_granted = FALSE
                AND acl.denial_reason LIKE '%center%'
                AND acl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                " . ($adminUserId ? "AND acl.user_id = ?" : "") . "
                GROUP BY acl.user_id, acl.center_id, acl.denial_reason
                HAVING attempt_count > 5
                ORDER BY attempt_count DESC
            ");
            
            $params = $adminUserId ? [$adminUserId] : [];
            $stmt->execute($params);
            $crossCenterAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($crossCenterAttempts as $attempt) {
                $this->securityViolations[] = [
                    'type' => 'SUSPICIOUS_CROSS_CENTER_ACCESS',
                    'severity' => 'HIGH',
                    'user_id' => $attempt['user_id'],
                    'username' => $attempt['username'],
                    'attempted_center' => $attempt['attempted_center'],
                    'attempt_count' => $attempt['attempt_count'],
                    'last_attempt' => $attempt['last_attempt'],
                    'description' => 'Repeated attempts to access unauthorized center data',
                    'recommendation' => 'Investigate user activity and consider access restrictions'
                ];
            }
            
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'COMPLETED',
                'suspicious_patterns' => count($crossCenterAttempts),
                'total_attempts_analyzed' => array_sum(array_column($crossCenterAttempts, 'attempt_count'))
            ];
            
        } catch (Exception $e) {
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Audit logging system effectiveness
     */
    private function auditLoggingSystem($adminUserId = null) {
        $testName = 'audit_logging_validation';
        $this->auditResults['tests_performed'][] = $testName;
        
        try {
            // Check audit log coverage
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as log_date,
                    COUNT(*) as log_entries,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT resource_type) as resource_types
                FROM access_control_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY log_date DESC
            ");
            $stmt->execute();
            $logCoverage = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check for gaps in logging
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_admin_actions
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND user_id IN (
                    SELECT id FROM users WHERE role IN ('admin', 'superadmin')
                )
            ");
            $stmt->execute();
            $totalAdminActions = $stmt->fetch(PDO::FETCH_ASSOC)['total_admin_actions'];
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as logged_access_attempts
                FROM access_control_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $loggedAccessAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['logged_access_attempts'];
            
            // Calculate logging coverage ratio
            $coverageRatio = $totalAdminActions > 0 ? ($loggedAccessAttempts / $totalAdminActions) : 0;
            
            if ($coverageRatio < 0.8) {
                $this->securityViolations[] = [
                    'type' => 'INSUFFICIENT_AUDIT_COVERAGE',
                    'severity' => 'MEDIUM',
                    'coverage_ratio' => round($coverageRatio * 100, 2),
                    'description' => 'Audit logging coverage is below recommended threshold',
                    'recommendation' => 'Review and enhance audit logging implementation'
                ];
            }
            
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'COMPLETED',
                'log_coverage_days' => count($logCoverage),
                'total_admin_actions' => $totalAdminActions,
                'logged_access_attempts' => $loggedAccessAttempts,
                'coverage_ratio' => round($coverageRatio * 100, 2) . '%'
            ];
            
        } catch (Exception $e) {
            $this->auditResults['tests_performed'][$testName] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate final security assessment
     */
    private function generateSecurityAssessment() {
        $totalViolations = count($this->securityViolations);
        $highSeverityViolations = count(array_filter($this->securityViolations, function($v) {
            return $v['severity'] === 'HIGH';
        }));
        $mediumSeverityViolations = count(array_filter($this->securityViolations, function($v) {
            return $v['severity'] === 'MEDIUM';
        }));
        
        // Determine overall security status
        if ($highSeverityViolations > 0) {
            $this->auditResults['overall_status'] = 'CRITICAL';
        } elseif ($mediumSeverityViolations > 3) {
            $this->auditResults['overall_status'] = 'WARNING';
        } elseif ($totalViolations > 0) {
            $this->auditResults['overall_status'] = 'ATTENTION_REQUIRED';
        } else {
            $this->auditResults['overall_status'] = 'SECURE';
        }
        
        $this->auditResults['security_violations'] = $this->securityViolations;
        $this->auditResults['summary'] = [
            'total_violations' => $totalViolations,
            'high_severity' => $highSeverityViolations,
            'medium_severity' => $mediumSeverityViolations,
            'low_severity' => $totalViolations - $highSeverityViolations - $mediumSeverityViolations
        ];
        
        // Generate recommendations
        $this->generateRecommendations();
    }
    
    /**
     * Generate security recommendations
     */
    private function generateRecommendations() {
        $recommendations = [];
        
        // Based on violations found, generate specific recommendations
        $violationTypes = array_unique(array_column($this->securityViolations, 'type'));
        
        foreach ($violationTypes as $type) {
            switch ($type) {
                case 'MISSING_CENTER_ASSIGNMENT':
                    $recommendations[] = 'Implement mandatory center assignment validation for all admin users';
                    break;
                case 'EXCESSIVE_CENTER_ASSIGNMENTS':
                    $recommendations[] = 'Review and implement center assignment limits based on role requirements';
                    break;
                case 'ORPHANED_MEMBER_DATA':
                    $recommendations[] = 'Run data cleanup to assign all members to appropriate centers';
                    break;
                case 'REPEATED_ACCESS_VIOLATIONS':
                    $recommendations[] = 'Implement automated account lockout for repeated access violations';
                    break;
                case 'SUSPICIOUS_CROSS_CENTER_ACCESS':
                    $recommendations[] = 'Enhance monitoring and alerting for cross-center access attempts';
                    break;
                case 'INSUFFICIENT_AUDIT_COVERAGE':
                    $recommendations[] = 'Improve audit logging coverage to capture all admin actions';
                    break;
            }
        }
        
        // General recommendations
        if (empty($this->securityViolations)) {
            $recommendations[] = 'Security posture is good. Continue regular security audits';
            $recommendations[] = 'Consider implementing additional monitoring for proactive threat detection';
        }
        
        $this->auditResults['recommendations'] = array_unique($recommendations);
    }
    
    /**
     * Log audit completion
     */
    private function logAuditCompletion() {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO access_control_logs (
                    user_id, user_role, resource_type, action, 
                    access_granted, additional_data, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $_SESSION['user_id'] ?? 0,
                $_SESSION['role'] ?? 'system',
                'security_audit',
                'audit_completed',
                true,
                json_encode([
                    'audit_results' => $this->auditResults['overall_status'],
                    'violations_found' => count($this->securityViolations),
                    'tests_performed' => count($this->auditResults['tests_performed'])
                ])
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to log audit completion: " . $e->getMessage());
        }
    }
    
    /**
     * Get security recommendations for a specific admin
     */
    public function getAdminSecurityRecommendations($adminUserId) {
        $audit = $this->performSecurityAudit($adminUserId);
        
        $adminViolations = array_filter($audit['security_violations'], function($v) use ($adminUserId) {
            return isset($v['admin_id']) && $v['admin_id'] == $adminUserId ||
                   isset($v['user_id']) && $v['user_id'] == $adminUserId;
        });
        
        return [
            'admin_id' => $adminUserId,
            'security_status' => empty($adminViolations) ? 'SECURE' : 'NEEDS_ATTENTION',
            'violations' => $adminViolations,
            'recommendations' => $audit['recommendations']
        ];
    }
}