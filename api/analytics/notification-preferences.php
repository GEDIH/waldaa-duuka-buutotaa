<?php
/**
 * Analytics Notification Preferences API
 * Handles notification preferences and settings per dashboard type
 * Requirements: 12.4
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
        case 'get_preferences':
            echo json_encode(getNotificationPreferences($db, $userId, $input));
            break;
            
        case 'save_settings':
            echo json_encode(saveNotificationSettings($db, $userId, $input));
            break;
            
        case 'update_preference':
            echo json_encode(updateNotificationPreference($db, $userId, $input));
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
 * Get notification preferences for user and dashboard type
 */
function getNotificationPreferences($db, $userId, $input) {
    $dashboardType = $input['dashboard_type'] ?? null;
    
    $sql = "SELECT preference_key, preference_value, dashboard_type 
            FROM notification_preferences 
            WHERE user_id = :user_id";
    
    $params = [':user_id' => $userId];
    
    if ($dashboardType) {
        $sql .= " AND (dashboard_type = :dashboard_type OR dashboard_type IS NULL)";
        $params[':dashboard_type'] = $dashboardType;
    }
    
    $sql .= " ORDER BY dashboard_type, preference_key";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $preferences = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $preferences[] = [
            'preference_key' => $row['preference_key'],
            'preference_value' => json_decode($row['preference_value'], true),
            'dashboard_type' => $row['dashboard_type']
        ];
    }
    
    return [
        'success' => true,
        'data' => $preferences
    ];
}

/**
 * Save complete notification settings
 */
function saveNotificationSettings($db, $userId, $input) {
    $config = $input['config'] ?? [];
    $thresholds = $input['thresholds'] ?? [];
    $preferences = $input['preferences'] ?? [];
    
    $db->beginTransaction();
    
    try {
        // Save general configuration
        saveGeneralConfig($db, $userId, $config);
        
        // Save threshold settings
        foreach ($thresholds as $threshold) {
            saveThresholdSetting($db, $userId, $threshold);
        }
        
        // Save dashboard preferences
        foreach ($preferences as $preference) {
            saveDashboardPreference($db, $userId, $preference);
        }
        
        $db->commit();
        
        return [
            'success' => true,
            'message' => 'Settings saved successfully'
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Save general notification configuration
 */
function saveGeneralConfig($db, $userId, $config) {
    $configKeys = [
        'soundEnabled',
        'vibrationEnabled', 
        'browserNotificationsEnabled',
        'defaultDuration',
        'maxNotifications'
    ];
    
    foreach ($configKeys as $key) {
        if (isset($config[$key])) {
            $sql = "INSERT INTO notification_preferences (user_id, preference_key, preference_value, dashboard_type) 
                    VALUES (:user_id, :key, :value, NULL)
                    ON DUPLICATE KEY UPDATE preference_value = :value";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':key' => $key,
                ':value' => json_encode($config[$key])
            ]);
        }
    }
}

/**
 * Save threshold setting
 */
function saveThresholdSetting($db, $userId, $threshold) {
    $sql = "INSERT INTO alert_thresholds (user_id, metric_key, warning_threshold, critical_threshold, enabled, notification_types)
            VALUES (:user_id, :metric, :warning, :critical, :enabled, :types)
            ON DUPLICATE KEY UPDATE 
                warning_threshold = :warning,
                critical_threshold = :critical,
                enabled = :enabled,
                notification_types = :types";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':metric' => $threshold['metric'],
        ':warning' => $threshold['warning'],
        ':critical' => $threshold['critical'],
        ':enabled' => $threshold['enabled'] ? 1 : 0,
        ':types' => json_encode($threshold['notification_types'])
    ]);
}

/**
 * Save dashboard preference
 */
function saveDashboardPreference($db, $userId, $preference) {
    $keys = ['enabled', 'notification_types'];
    
    foreach ($keys as $key) {
        if (isset($preference[$key])) {
            $sql = "INSERT INTO notification_preferences (user_id, preference_key, preference_value, dashboard_type)
                    VALUES (:user_id, :key, :value, :dashboard_type)
                    ON DUPLICATE KEY UPDATE preference_value = :value";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':key' => $key,
                ':value' => json_encode($preference[$key]),
                ':dashboard_type' => $preference['dashboard_type']
            ]);
        }
    }
}

/**
 * Update single notification preference
 */
function updateNotificationPreference($db, $userId, $input) {
    $key = $input['key'] ?? '';
    $value = $input['value'] ?? '';
    $dashboardType = $input['dashboard_type'] ?? null;
    
    if (empty($key)) {
        throw new Exception('Preference key is required');
    }
    
    $sql = "INSERT INTO notification_preferences (user_id, preference_key, preference_value, dashboard_type)
            VALUES (:user_id, :key, :value, :dashboard_type)
            ON DUPLICATE KEY UPDATE preference_value = :value";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':key' => $key,
        ':value' => json_encode($value),
        ':dashboard_type' => $dashboardType
    ]);
    
    return [
        'success' => true,
        'message' => 'Preference updated successfully'
    ];
}
?>