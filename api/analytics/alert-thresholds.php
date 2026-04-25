<?php
/**
 * Analytics Alert Thresholds API
 * Handles configurable alert thresholds for dashboard metrics
 * Requirements: 12.1
 * Task: 10.1 Implement notification system integration
 */

require_once '../config/database.php';
require_once '../security/SecurityManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $security = new SecurityManager($db);
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // Validate user session
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }
    
    $userId = $_SESSION['user_id'];
    
    switch ($action) {
        case 'get_thresholds':
            echo json_encode(getAlertThresholds($db, $userId, $input));
            break;
            
        case 'save_threshold':
            echo json_encode(saveAlertThreshold($db, $userId, $input));
            break;
            
        case 'delete_threshold':
            echo json_encode(deleteAlertThreshold($db, $userId, $input));
            break;
            
        case 'check_threshold':
            echo json_encode(checkThreshold($db, $userId, $input));
            break;
            
        case 'get_available_metrics':
            echo json_encode(getAvailableMetrics($db, $userId, $input));
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get alert thresholds for user and dashboard type
 */
function getAlertThresholds($db, $userId, $input) {
    $dashboardType = $input['dashboard_type'] ?? null;
    
    $sql = "SELECT metric_key, warning_threshold, critical_threshold, enabled, notification_types, 
                   dashboard_type, created_at, updated_at
            FROM alert_thresholds 
            WHERE user_id = :user_id";
    
    $params = [':user_id' => $userId];
    
    if ($dashboardType) {
        $sql .= " AND (dashboard_type = :dashboard_type OR dashboard_type IS NULL)";
        $params[':dashboard_type'] = $dashboardType;
    }
    
    $sql .= " ORDER BY dashboard_type, metric_key";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $thresholds = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $thresholds[] = [
            'metric_key' => $row['metric_key'],
            'warning_threshold' => $row['warning_threshold'],
            'critical_threshold' => $row['critical_threshold'],
            'enabled' => (bool)$row['enabled'],
            'notification_types' => $row['notification_types'],
            'dashboard_type' => $row['dashboard_type'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    return [
        'success' => true,
        'data' => $thresholds
    ];
}

/**
 * Save alert threshold
 */
function saveAlertThreshold($db, $userId, $input) {
    $metricKey = $input['metric_key'] ?? '';
    $warningThreshold = $input['warning_threshold'] ?? null;
    $criticalThreshold = $input['critical_threshold'] ?? null;
    $enabled = $input['enabled'] ?? true;
    $notificationTypes = $input['notification_types'] ?? ['visual'];
    $dashboardType = $input['dashboard_type'] ?? null;
    
    if (empty($metricKey)) {
        throw new Exception('Metric key is required');
    }
    
    // Validate thresholds
    if ($warningThreshold !== null && $criticalThreshold !== null) {
        if ($criticalThreshold >= $warningThreshold) {
            throw new Exception('Critical threshold must be less than warning threshold');
        }
    }
    
    // Validate notification types
    $validTypes = ['visual', 'popup', 'toast', 'system', 'badge'];
    foreach ($notificationTypes as $type) {
        if (!in_array($type, $validTypes)) {
            throw new Exception("Invalid notification type: $type");
        }
    }
    
    $sql = "INSERT INTO alert_thresholds 
            (user_id, metric_key, warning_threshold, critical_threshold, enabled, notification_types, dashboard_type)
            VALUES (:user_id, :metric_key, :warning_threshold, :critical_threshold, :enabled, :notification_types, :dashboard_type)
            ON DUPLICATE KEY UPDATE 
                warning_threshold = :warning_threshold,
                critical_threshold = :critical_threshold,
                enabled = :enabled,
                notification_types = :notification_types,
                updated_at = CURRENT_TIMESTAMP";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':metric_key' => $metricKey,
        ':warning_threshold' => $warningThreshold,
        ':critical_threshold' => $criticalThreshold,
        ':enabled' => $enabled ? 1 : 0,
        ':notification_types' => json_encode($notificationTypes),
        ':dashboard_type' => $dashboardType
    ]);
    
    return [
        'success' => true,
        'message' => 'Alert threshold saved successfully'
    ];
}

/**
 * Delete alert threshold
 */
function deleteAlertThreshold($db, $userId, $input) {
    $metricKey = $input['metric_key'] ?? '';
    $dashboardType = $input['dashboard_type'] ?? null;
    
    if (empty($metricKey)) {
        throw new Exception('Metric key is required');
    }
    
    $sql = "DELETE FROM alert_thresholds 
            WHERE user_id = :user_id AND metric_key = :metric_key";
    
    $params = [
        ':user_id' => $userId,
        ':metric_key' => $metricKey
    ];
    
    if ($dashboardType) {
        $sql .= " AND dashboard_type = :dashboard_type";
        $params[':dashboard_type'] = $dashboardType;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return [
        'success' => true,
        'message' => 'Alert threshold deleted successfully'
    ];
}

/**
 * Check if value violates threshold
 */
function checkThreshold($db, $userId, $input) {
    $metricKey = $input['metric_key'] ?? '';
    $value = $input['value'] ?? null;
    $dashboardType = $input['dashboard_type'] ?? null;
    
    if (empty($metricKey) || $value === null) {
        throw new Exception('Metric key and value are required');
    }
    
    // Get threshold configuration
    $sql = "SELECT warning_threshold, critical_threshold, enabled, notification_types
            FROM alert_thresholds 
            WHERE user_id = :user_id AND metric_key = :metric_key AND enabled = 1";
    
    $params = [
        ':user_id' => $userId,
        ':metric_key' => $metricKey
    ];
    
    if ($dashboardType) {
        $sql .= " AND (dashboard_type = :dashboard_type OR dashboard_type IS NULL)";
        $params[':dashboard_type'] = $dashboardType;
    }
    
    $sql .= " ORDER BY dashboard_type DESC LIMIT 1"; // Prefer dashboard-specific over global
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $threshold = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$threshold) {
        return [
            'success' => true,
            'violation' => false,
            'message' => 'No threshold configured for this metric'
        ];
    }
    
    $violation = false;
    $level = null;
    $message = '';
    
    // Check critical threshold first
    if ($threshold['critical_threshold'] !== null && $value <= $threshold['critical_threshold']) {
        $violation = true;
        $level = 'critical';
        $message = "Critical threshold violated: $value <= {$threshold['critical_threshold']}";
    }
    // Check warning threshold
    elseif ($threshold['warning_threshold'] !== null && $value <= $threshold['warning_threshold']) {
        $violation = true;
        $level = 'warning';
        $message = "Warning threshold violated: $value <= {$threshold['warning_threshold']}";
    }
    
    $result = [
        'success' => true,
        'violation' => $violation,
        'level' => $level,
        'message' => $message,
        'threshold' => $threshold
    ];
    
    // Log threshold check if violation occurred
    if ($violation) {
        logThresholdViolation($db, $userId, $metricKey, $value, $level, $dashboardType);
    }
    
    return $result;
}

/**
 * Get available metrics for threshold configuration
 */
function getAvailableMetrics($db, $userId, $input) {
    $dashboardType = $input['dashboard_type'] ?? null;
    
    // Define available metrics per dashboard type
    $metrics = [
        'admin_main' => [
            'total_members' => 'Total Members',
            'active_members' => 'Active Members',
            'total_contributions' => 'Total Contributions',
            'monthly_contributions' => 'Monthly Contributions',
            'system_performance' => 'System Performance',
            'error_rate' => 'Error Rate'
        ],
        'center_management' => [
            'center_members' => 'Center Members',
            'center_contributions' => 'Center Contributions',
            'member_attendance' => 'Member Attendance',
            'center_performance' => 'Center Performance'
        ],
        'members_management' => [
            'new_registrations' => 'New Registrations',
            'member_engagement' => 'Member Engagement',
            'inactive_members' => 'Inactive Members',
            'member_satisfaction' => 'Member Satisfaction'
        ],
        'contributions_management' => [
            'daily_contributions' => 'Daily Contributions',
            'contribution_trends' => 'Contribution Trends',
            'payment_failures' => 'Payment Failures',
            'outstanding_amounts' => 'Outstanding Amounts'
        ]
    ];
    
    $availableMetrics = [];
    
    if ($dashboardType && isset($metrics[$dashboardType])) {
        $availableMetrics = $metrics[$dashboardType];
    } else {
        // Return all metrics if no specific dashboard type
        foreach ($metrics as $dashboard => $dashboardMetrics) {
            $availableMetrics = array_merge($availableMetrics, $dashboardMetrics);
        }
    }
    
    return [
        'success' => true,
        'data' => $availableMetrics
    ];
}

/**
 * Log threshold violation for audit purposes
 */
function logThresholdViolation($db, $userId, $metricKey, $value, $level, $dashboardType) {
    try {
        $sql = "INSERT INTO threshold_violations 
                (user_id, metric_key, value, threshold_level, dashboard_type, created_at)
                VALUES (:user_id, :metric_key, :value, :level, :dashboard_type, NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':metric_key' => $metricKey,
            ':value' => $value,
            ':level' => $level,
            ':dashboard_type' => $dashboardType
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log threshold violation: " . $e->getMessage());
    }
}
?>