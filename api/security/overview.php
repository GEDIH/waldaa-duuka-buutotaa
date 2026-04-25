<?php
/**
 * Security Overview API
 * Provides comprehensive security metrics and status overview
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Initialize database and auth
    $db = Database::getInstance()->getConnection();
    $auth = new AuthMiddleware($db);
    
    // Authenticate user
    $authResult = $auth->authenticate();
    if (!$authResult['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => $authResult['error']]);
        exit;
    }
    
    $currentUser = $authResult['user'];
    
    // Check permissions
    if (!in_array($currentUser['role'], ['admin', 'superadmin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Insufficient privileges']);
        exit;
    }
    
    // Get security overview data
    $overview = getSecurityOverview($db, $currentUser);
    
    echo json_encode([
        'success' => true,
        'overview' => $overview
    ]);
    
} catch (Exception $e) {
    error_log("Security overview error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function getSecurityOverview($db, $currentUser) {
    $overview = [
        'overall_status' => 'SECURE',
        'total_violations' => 0,
        'active_admins' => 0,
        'monitored_centers' => 0,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    try {
        // Get total violations in last 24 hours
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_violations
            FROM access_control_logs
            WHERE access_granted = FALSE
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $overview['total_violations'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_violations'];
        
        // Get active admins (logged in within last hour)
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT user_id) as active_admins
            FROM access_control_logs
            WHERE user_role IN ('admin', 'superadmin')
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $overview['active_admins'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['active_admins'];
        
        // Get monitored centers
        if ($currentUser['role'] === 'superadmin') {
            $stmt = $db->prepare("SELECT COUNT(*) as monitored_centers FROM centers WHERE status = 'active'");
            $stmt->execute();
        } else {
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT ac.center_id) as monitored_centers
                FROM admin_centers ac
                JOIN centers c ON ac.center_id = c.id
                WHERE ac.admin_id = ? AND c.status = 'active'
            ");
            $stmt->execute([$currentUser['id']]);
        }
        $overview['monitored_centers'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['monitored_centers'];
        
        // Determine overall security status
        $overview['overall_status'] = determineSecurityStatus($db, $overview['total_violations']);
        
        // Get additional metrics
        $overview['metrics'] = getAdditionalMetrics($db, $currentUser);
        
    } catch (Exception $e) {
        error_log("Error getting security overview: " . $e->getMessage());
    }
    
    return $overview;
}

function determineSecurityStatus($db, $totalViolations) {
    try {
        // Check for critical violations
        $stmt = $db->prepare("
            SELECT COUNT(*) as critical_violations
            FROM access_control_logs
            WHERE access_granted = FALSE
            AND denial_reason LIKE '%CRITICAL%'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $criticalViolations = (int)$stmt->fetch(PDO::FETCH_ASSOC)['critical_violations'];
        
        if ($criticalViolations > 0) {
            return 'CRITICAL';
        }
        
        if ($totalViolations > 50) {
            return 'WARNING';
        }
        
        if ($totalViolations > 10) {
            return 'ATTENTION_REQUIRED';
        }
        
        return 'SECURE';
        
    } catch (Exception $e) {
        error_log("Error determining security status: " . $e->getMessage());
        return 'UNKNOWN';
    }
}

function getAdditionalMetrics($db, $currentUser) {
    $metrics = [];
    
    try {
        // Failed login attempts
        $stmt = $db->prepare("
            SELECT COUNT(*) as failed_logins
            FROM access_control_logs
            WHERE action = 'login_attempt'
            AND access_granted = FALSE
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $metrics['failed_logins'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['failed_logins'];
        
        // Locked accounts
        $stmt = $db->prepare("
            SELECT COUNT(*) as locked_accounts
            FROM users
            WHERE locked_until > NOW()
        ");
        $stmt->execute();
        $metrics['locked_accounts'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['locked_accounts'];
        
        // Cross-center access attempts
        $stmt = $db->prepare("
            SELECT COUNT(*) as cross_center_attempts
            FROM access_control_logs
            WHERE access_granted = FALSE
            AND denial_reason LIKE '%center%'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $metrics['cross_center_attempts'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cross_center_attempts'];
        
        // Data integrity issues
        $stmt = $db->prepare("
            SELECT COUNT(*) as orphaned_members
            FROM members
            WHERE center_id IS NULL OR center_id = 0
        ");
        $stmt->execute();
        $metrics['orphaned_members'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['orphaned_members'];
        
    } catch (Exception $e) {
        error_log("Error getting additional metrics: " . $e->getMessage());
    }
    
    return $metrics;
}
?>