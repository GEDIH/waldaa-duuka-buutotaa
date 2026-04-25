<?php
/**
 * Analytics Notification History API
 * Handles notification history and acknowledgment tracking
 * Requirements: 12.2, 12.3, 12.5
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
        case 'get_notifications':
            echo json_encode(getNotifications($db, $userId, $input));
            break;
            
        case 'acknowledge_notification':
            echo json_encode(acknowledgeNotification($db, $userId, $input));
            break;
            
        case 'acknowledge_all':
            echo json_encode(acknowledgeAllNotifications($db, $userId, $input));
            break;
            
        case 'delete_notification':
            echo json_encode(deleteNotification($db, $userId, $input));
            break;
            
        case 'get_stats':
            echo json_encode(getNotificationStats($db, $userId, $input));
            break;
            
        case 'create_notification':
            echo json_encode(createNotification($db, $userId, $input));
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
 * Get notifications for user
 */
function getNotifications($db, $userId, $input) {
    $dashboardType = $input['dashboard_type'] ?? null;
    $limit = min($input['limit'] ?? 20, 100); // Max 100 notifications
    $offset = $input['offset'] ?? 0;
    $unacknowledgedOnly = $input['unacknowledged_only'] ?? false;
    $levelFilter = $input['level_filter'] ?? null;
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    
    // Build WHERE clause
    $whereConditions = ['nh.user_id = :user_id'];
    $params = [':user_id' => $userId];
    
    if ($dashboardType) {
        $whereConditions[] = '(nh.dashboard_type = :dashboard_type OR nh.dashboard_type IS NULL)';
        $params[':dashboard_type'] = $dashboardType;
    }
    
    if ($unacknowledgedOnly) {
        $whereConditions[] = 'nh.acknowledged = FALSE';
    }
    
    if ($levelFilter) {
        $whereConditions[] = 'nh.alert_level = :level_filter';
        $params[':level_filter'] = $levelFilter;
    }
    
    if ($startDate) {
        $whereConditions[] = 'nh.created_at >= :start_date';
        $params[':start_date'] = $startDate;
    }
    
    if ($endDate) {
        $whereConditions[] = 'nh.created_at <= :end_date';
        $params[':end_date'] = $endDate;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get notifications
    $sql = "SELECT nh.id, nh.notification_id, nh.notification_type, nh.alert_level, 
                   nh.title, nh.message, nh.dashboard_type, nh.acknowledged, 
                   nh.acknowledged_at, nh.created_at
            FROM notification_history nh
            WHERE $whereClause
            ORDER BY nh.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    
    $notifications = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = [
            'id' => $row['id'],
            'notification_id' => $row['notification_id'],
            'notification_type' => $row['notification_type'],
            'alert_level' => $row['alert_level'],
            'title' => $row['title'],
            'message' => $row['message'],
            'dashboard_type' => $row['dashboard_type'],
            'acknowledged' => (bool)$row['acknowledged'],
            'acknowledged_at' => $row['acknowledged_at'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get statistics
    $stats = getNotificationStatsInternal($db, $userId, $dashboardType);
    
    return [
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'stats' => $stats,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => count($notifications) === $limit
            ]
        ]
    ];
}

/**
 * Acknowledge single notification
 */
function acknowledgeNotification($db, $userId, $input) {
    $notificationId = $input['notification_id'] ?? '';
    
    if (empty($notificationId)) {
        throw new Exception('Notification ID is required');
    }
    
    $sql = "UPDATE notification_history 
            SET acknowledged = TRUE, acknowledged_at = NOW()
            WHERE id = :notification_id AND user_id = :user_id AND acknowledged = FALSE";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId
    ]);
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected === 0) {
        throw new Exception('Notification not found or already acknowledged');
    }
    
    // Log acknowledgment
    logNotificationAction($db, $userId, $notificationId, 'acknowledged');
    
    return [
        'success' => true,
        'message' => 'Notification acknowledged successfully'
    ];
}

/**
 * Acknowledge all notifications for user
 */
function acknowledgeAllNotifications($db, $userId, $input) {
    $dashboardType = $input['dashboard_type'] ?? null;
    
    $sql = "UPDATE notification_history 
            SET acknowledged = TRUE, acknowledged_at = NOW()
            WHERE user_id = :user_id AND acknowledged = FALSE";
    
    $params = [':user_id' => $userId];
    
    if ($dashboardType) {
        $sql .= " AND (dashboard_type = :dashboard_type OR dashboard_type IS NULL)";
        $params[':dashboard_type'] = $dashboardType;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $rowsAffected = $stmt->rowCount();
    
    // Log bulk acknowledgment
    logNotificationAction($db, $userId, 'all', 'bulk_acknowledged', [
        'count' => $rowsAffected,
        'dashboard_type' => $dashboardType
    ]);
    
    return [
        'success' => true,
        'message' => "Acknowledged $rowsAffected notifications successfully"
    ];
}

/**
 * Delete notification
 */
function deleteNotification($db, $userId, $input) {
    $notificationId = $input['notification_id'] ?? '';
    
    if (empty($notificationId)) {
        throw new Exception('Notification ID is required');
    }
    
    $sql = "DELETE FROM notification_history 
            WHERE id = :notification_id AND user_id = :user_id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId
    ]);
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected === 0) {
        throw new Exception('Notification not found');
    }
    
    // Log deletion
    logNotificationAction($db, $userId, $notificationId, 'deleted');
    
    return [
        'success' => true,
        'message' => 'Notification deleted successfully'
    ];
}

/**
 * Get notification statistics
 */
function getNotificationStats($db, $userId, $input) {
    $dashboardType = $input['dashboard_type'] ?? null;
    
    $stats = getNotificationStatsInternal($db, $userId, $dashboardType);
    
    return [
        'success' => true,
        'data' => $stats
    ];
}

/**
 * Internal function to get notification statistics
 */
function getNotificationStatsInternal($db, $userId, $dashboardType = null) {
    $whereClause = 'user_id = :user_id';
    $params = [':user_id' => $userId];
    
    if ($dashboardType) {
        $whereClause .= ' AND (dashboard_type = :dashboard_type OR dashboard_type IS NULL)';
        $params[':dashboard_type'] = $dashboardType;
    }
    
    $sql = "SELECT 
                COUNT(*) as total_notifications,
                COUNT(CASE WHEN acknowledged = FALSE THEN 1 END) as unacknowledged_count,
                COUNT(CASE WHEN alert_level = 'critical' THEN 1 END) as critical_count,
                COUNT(CASE WHEN alert_level = 'warning' THEN 1 END) as warning_count,
                COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as recent_count,
                COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_count,
                MAX(created_at) as last_notification
            FROM notification_history 
            WHERE $whereClause";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Convert to appropriate types
    return [
        'total_notifications' => (int)$stats['total_notifications'],
        'unacknowledged_count' => (int)$stats['unacknowledged_count'],
        'critical_count' => (int)$stats['critical_count'],
        'warning_count' => (int)$stats['warning_count'],
        'recent_count' => (int)$stats['recent_count'],
        'weekly_count' => (int)$stats['weekly_count'],
        'last_notification' => $stats['last_notification']
    ];
}

/**
 * Create new notification
 */
function createNotification($db, $userId, $input) {
    $notificationId = $input['notification_id'] ?? 'manual_' . uniqid();
    $notificationType = $input['notification_type'] ?? 'visual';
    $alertLevel = $input['alert_level'] ?? 'info';
    $title = $input['title'] ?? '';
    $message = $input['message'] ?? '';
    $dashboardType = $input['dashboard_type'] ?? null;
    
    if (empty($title) || empty($message)) {
        throw new Exception('Title and message are required');
    }
    
    // Validate notification type
    $validTypes = ['visual', 'popup', 'toast', 'system', 'badge'];
    if (!in_array($notificationType, $validTypes)) {
        throw new Exception('Invalid notification type');
    }
    
    // Validate alert level
    $validLevels = ['info', 'warning', 'critical', 'success'];
    if (!in_array($alertLevel, $validLevels)) {
        throw new Exception('Invalid alert level');
    }
    
    $sql = "INSERT INTO notification_history 
            (user_id, notification_id, notification_type, alert_level, title, message, dashboard_type)
            VALUES (:user_id, :notification_id, :notification_type, :alert_level, :title, :message, :dashboard_type)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':notification_id' => $notificationId,
        ':notification_type' => $notificationType,
        ':alert_level' => $alertLevel,
        ':title' => $title,
        ':message' => $message,
        ':dashboard_type' => $dashboardType
    ]);
    
    $insertedId = $db->lastInsertId();
    
    // Log creation
    logNotificationAction($db, $userId, $insertedId, 'created');
    
    return [
        'success' => true,
        'data' => [
            'id' => $insertedId,
            'notification_id' => $notificationId
        ],
        'message' => 'Notification created successfully'
    ];
}

/**
 * Log notification action for audit purposes
 */
function logNotificationAction($db, $userId, $notificationId, $action, $metadata = null) {
    try {
        $sql = "INSERT INTO notification_audit_log 
                (user_id, notification_id, action, metadata, created_at)
                VALUES (:user_id, :notification_id, :action, :metadata, NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':notification_id' => $notificationId,
            ':action' => $action,
            ':metadata' => $metadata ? json_encode($metadata) : null
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log notification action: " . $e->getMessage());
    }
}

/**
 * Clean up old notifications (called by cron job)
 */
function cleanupOldNotifications($db, $daysToKeep = 30) {
    try {
        $sql = "DELETE FROM notification_history 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND acknowledged = TRUE";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':days' => $daysToKeep]);
        
        $deletedCount = $stmt->rowCount();
        
        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Cleaned up $deletedCount old notifications"
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>