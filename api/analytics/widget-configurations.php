<?php
/**
 * Widget Configurations API
 * Handles CRUD operations for analytics widget configurations
 * Requirements: 1.1, 1.2, 8.2, 13.3
 * Task: 2.1 Implement AnalyticsWidgetManager class - Backend API support
 */

require_once '../config/database.php';
require_once '../security/SecurityManager.php';
require_once '../handlers/ErrorHandler.php';

class WidgetConfigurationsAPI {
    private $db;
    private $securityManager;
    private $errorHandler;
    
    public function __construct() {
        $this->db = new Database();
        $this->securityManager = new SecurityManager();
        $this->errorHandler = new ErrorHandler();
        
        // Enable CORS for API access
        $this->enableCORS();
    }
    
    /**
     * Enable CORS headers
     */
    private function enableCORS() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest() {
        try {
            // Authenticate user - check session
            session_start();
            if (!$this->isUserAuthenticated()) {
                throw new Exception('Authentication required', 401);
            }
            
            // Get request data
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? $_GET['action'] ?? '';
            
            // Route to appropriate handler
            switch ($action) {
                case 'get_configurations':
                    return $this->getConfigurations($input);
                    
                case 'save_configuration':
                    return $this->saveConfiguration($input);
                    
                case 'delete_configuration':
                    return $this->deleteConfiguration($input);
                    
                case 'update_configuration':
                    return $this->updateConfiguration($input);
                    
                case 'get_default_configurations':
                    return $this->getDefaultConfigurations($input);
                    
                case 'reset_to_defaults':
                    return $this->resetToDefaults($input);
                    
                default:
                    throw new Exception('Invalid action', 400);
            }
            
        } catch (Exception $e) {
            return $this->errorHandler->handleException($e);
        }
    }
    
    /**
     * Check if user is authenticated
     */
    private function isUserAuthenticated() {
        // Check if user is logged in via session
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get widget configurations for user and dashboard type
     */
    private function getConfigurations($input) {
        $dashboardType = $input['dashboard_type'] ?? '';
        $userId = $input['user_id'] ?? $this->securityManager->getCurrentUserId();
        
        if (empty($dashboardType)) {
            throw new Exception('Dashboard type is required', 400);
        }
        
        // Validate dashboard type
        $validDashboardTypes = ['admin_main', 'center_management', 'members_management', 'contributions_management'];
        if (!in_array($dashboardType, $validDashboardTypes)) {
            throw new Exception('Invalid dashboard type', 400);
        }
        
        $conn = $this->db->getConnection();
        
        // Get user-specific configurations first, then fall back to defaults
        $sql = "
            SELECT 
                wc.*,
                CASE 
                    WHEN wc.user_id = ? THEN 'user'
                    ELSE 'default'
                END as config_source
            FROM widget_configurations wc
            WHERE wc.dashboard_type = ? 
                AND (wc.user_id = ? OR wc.user_id IS NULL)
                AND wc.is_active = TRUE
            ORDER BY 
                wc.user_id DESC,  -- User configs first
                wc.priority_order DESC,
                wc.position_row ASC,
                wc.position_column ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isi', $userId, $dashboardType, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $configurations = [];
        $userWidgetIds = [];
        
        while ($row = $result->fetch_assoc()) {
            // Parse JSON fields
            $row['config'] = json_decode($row['config'] ?? '{}', true);
            $row['styling'] = json_decode($row['styling'] ?? '{}', true);
            $row['permissions'] = json_decode($row['permissions'] ?? '[]', true);
            
            // If this is a user config, track the widget_id
            if ($row['config_source'] === 'user') {
                $userWidgetIds[] = $row['widget_id'];
            }
            
            // Only add default configs if user doesn't have a custom config for this widget
            if ($row['config_source'] === 'user' || !in_array($row['widget_id'], $userWidgetIds)) {
                $configurations[] = $row;
            }
        }
        
        // Apply security filtering
        $configurations = $this->applySecurityFiltering($configurations);
        
        return [
            'success' => true,
            'data' => $configurations,
            'count' => count($configurations)
        ];
    }
    
    /**
     * Save widget configuration
     */
    private function saveConfiguration($input) {
        $config = $input['config'] ?? [];
        
        if (empty($config['widget_id']) || empty($config['dashboard_type']) || empty($config['widget_type'])) {
            throw new Exception('Widget ID, dashboard type, and widget type are required', 400);
        }
        
        $userId = $this->securityManager->getCurrentUserId();
        $conn = $this->db->getConnection();
        
        // Check if configuration already exists
        $checkSql = "
            SELECT id FROM widget_configurations 
            WHERE widget_id = ? AND dashboard_type = ? AND user_id = ?
        ";
        
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('ssi', $config['widget_id'], $config['dashboard_type'], $userId);
        $checkStmt->execute();
        $existingResult = $checkStmt->get_result();
        
        if ($existingResult->num_rows > 0) {
            // Update existing configuration
            return $this->updateExistingConfiguration($config, $userId);
        } else {
            // Insert new configuration
            return $this->insertNewConfiguration($config, $userId);
        }
    }
    
    /**
     * Insert new widget configuration
     */
    private function insertNewConfiguration($config, $userId) {
        $conn = $this->db->getConnection();
        
        $sql = "
            INSERT INTO widget_configurations (
                widget_id, dashboard_type, user_id, widget_type,
                position_row, position_column, position_span,
                width_percentage, height_pixels,
                config, styling, permissions,
                refresh_interval, cache_enabled, real_time_enabled, export_enabled,
                is_active, priority_order, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $conn->prepare($sql);
        
        // Prepare values with defaults
        $widgetId = $config['widget_id'];
        $dashboardType = $config['dashboard_type'];
        $widgetType = $config['widget_type'];
        $positionRow = $config['position_row'] ?? 1;
        $positionColumn = $config['position_column'] ?? 1;
        $positionSpan = $config['position_span'] ?? 1;
        $widthPercentage = $config['width_percentage'] ?? 100.00;
        $heightPixels = $config['height_pixels'] ?? 300;
        $configJson = json_encode($config['config'] ?? []);
        $stylingJson = json_encode($config['styling'] ?? []);
        $permissionsJson = json_encode($config['permissions'] ?? []);
        $refreshInterval = $config['refresh_interval'] ?? 300;
        $cacheEnabled = $config['cache_enabled'] ?? true;
        $realTimeEnabled = $config['real_time_enabled'] ?? true;
        $exportEnabled = $config['export_enabled'] ?? true;
        $isActive = $config['is_active'] ?? true;
        $priorityOrder = $config['priority_order'] ?? 0;
        
        $stmt->bind_param(
            'ssissiiddsssiiiiiii',
            $widgetId, $dashboardType, $userId, $widgetType,
            $positionRow, $positionColumn, $positionSpan,
            $widthPercentage, $heightPixels,
            $configJson, $stylingJson, $permissionsJson,
            $refreshInterval, $cacheEnabled, $realTimeEnabled, $exportEnabled,
            $isActive, $priorityOrder, $userId, $userId
        );
        
        if ($stmt->execute()) {
            $configId = $conn->insert_id;
            
            // Log the action
            $this->logConfigurationAction('CREATE', $widgetId, $userId);
            
            return [
                'success' => true,
                'message' => 'Widget configuration saved successfully',
                'config_id' => $configId
            ];
        } else {
            throw new Exception('Failed to save widget configuration: ' . $stmt->error, 500);
        }
    }
    
    /**
     * Update existing widget configuration
     */
    private function updateExistingConfiguration($config, $userId) {
        $conn = $this->db->getConnection();
        
        $sql = "
            UPDATE widget_configurations SET
                widget_type = ?,
                position_row = ?, position_column = ?, position_span = ?,
                width_percentage = ?, height_pixels = ?,
                config = ?, styling = ?, permissions = ?,
                refresh_interval = ?, cache_enabled = ?, real_time_enabled = ?, export_enabled = ?,
                is_active = ?, priority_order = ?, updated_by = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE widget_id = ? AND dashboard_type = ? AND user_id = ?
        ";
        
        $stmt = $conn->prepare($sql);
        
        // Prepare values
        $widgetType = $config['widget_type'];
        $positionRow = $config['position_row'] ?? 1;
        $positionColumn = $config['position_column'] ?? 1;
        $positionSpan = $config['position_span'] ?? 1;
        $widthPercentage = $config['width_percentage'] ?? 100.00;
        $heightPixels = $config['height_pixels'] ?? 300;
        $configJson = json_encode($config['config'] ?? []);
        $stylingJson = json_encode($config['styling'] ?? []);
        $permissionsJson = json_encode($config['permissions'] ?? []);
        $refreshInterval = $config['refresh_interval'] ?? 300;
        $cacheEnabled = $config['cache_enabled'] ?? true;
        $realTimeEnabled = $config['real_time_enabled'] ?? true;
        $exportEnabled = $config['export_enabled'] ?? true;
        $isActive = $config['is_active'] ?? true;
        $priorityOrder = $config['priority_order'] ?? 0;
        $widgetId = $config['widget_id'];
        $dashboardType = $config['dashboard_type'];
        
        $stmt->bind_param(
            'siiiddsssiiiiissi',
            $widgetType,
            $positionRow, $positionColumn, $positionSpan,
            $widthPercentage, $heightPixels,
            $configJson, $stylingJson, $permissionsJson,
            $refreshInterval, $cacheEnabled, $realTimeEnabled, $exportEnabled,
            $isActive, $priorityOrder, $userId,
            $widgetId, $dashboardType, $userId
        );
        
        if ($stmt->execute()) {
            // Log the action
            $this->logConfigurationAction('UPDATE', $widgetId, $userId);
            
            return [
                'success' => true,
                'message' => 'Widget configuration updated successfully'
            ];
        } else {
            throw new Exception('Failed to update widget configuration: ' . $stmt->error, 500);
        }
    }
    
    /**
     * Delete widget configuration
     */
    private function deleteConfiguration($input) {
        $widgetId = $input['widget_id'] ?? '';
        
        if (empty($widgetId)) {
            throw new Exception('Widget ID is required', 400);
        }
        
        $userId = $this->securityManager->getCurrentUserId();
        $conn = $this->db->getConnection();
        
        $sql = "DELETE FROM widget_configurations WHERE widget_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $widgetId, $userId);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            
            if ($affectedRows > 0) {
                // Log the action
                $this->logConfigurationAction('DELETE', $widgetId, $userId);
                
                return [
                    'success' => true,
                    'message' => 'Widget configuration deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Widget configuration not found or not owned by user'
                ];
            }
        } else {
            throw new Exception('Failed to delete widget configuration: ' . $stmt->error, 500);
        }
    }
    
    /**
     * Update widget configuration (partial update)
     */
    private function updateConfiguration($input) {
        $widgetId = $input['widget_id'] ?? '';
        $updates = $input['updates'] ?? [];
        
        if (empty($widgetId) || empty($updates)) {
            throw new Exception('Widget ID and updates are required', 400);
        }
        
        $userId = $this->securityManager->getCurrentUserId();
        $conn = $this->db->getConnection();
        
        // Build dynamic update query
        $setParts = [];
        $values = [];
        $types = '';
        
        $allowedFields = [
            'position_row' => 'i',
            'position_column' => 'i', 
            'position_span' => 'i',
            'width_percentage' => 'd',
            'height_pixels' => 'i',
            'config' => 's',
            'styling' => 's',
            'permissions' => 's',
            'refresh_interval' => 'i',
            'cache_enabled' => 'i',
            'real_time_enabled' => 'i',
            'export_enabled' => 'i',
            'is_active' => 'i',
            'priority_order' => 'i'
        ];
        
        foreach ($updates as $field => $value) {
            if (isset($allowedFields[$field])) {
                $setParts[] = "$field = ?";
                
                // Handle JSON fields
                if (in_array($field, ['config', 'styling', 'permissions'])) {
                    $values[] = is_string($value) ? $value : json_encode($value);
                } else {
                    $values[] = $value;
                }
                
                $types .= $allowedFields[$field];
            }
        }
        
        if (empty($setParts)) {
            throw new Exception('No valid fields to update', 400);
        }
        
        // Add updated_by and updated_at
        $setParts[] = 'updated_by = ?';
        $setParts[] = 'updated_at = CURRENT_TIMESTAMP';
        $values[] = $userId;
        $types .= 'i';
        
        // Add WHERE conditions
        $values[] = $widgetId;
        $values[] = $userId;
        $types .= 'si';
        
        $sql = "UPDATE widget_configurations SET " . implode(', ', $setParts) . 
               " WHERE widget_id = ? AND user_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $affectedRows = $stmt->affected_rows;
            
            if ($affectedRows > 0) {
                // Log the action
                $this->logConfigurationAction('UPDATE', $widgetId, $userId);
                
                return [
                    'success' => true,
                    'message' => 'Widget configuration updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No changes made or widget not found'
                ];
            }
        } else {
            throw new Exception('Failed to update widget configuration: ' . $stmt->error, 500);
        }
    }
    
    /**
     * Get default widget configurations for dashboard type
     */
    private function getDefaultConfigurations($input) {
        $dashboardType = $input['dashboard_type'] ?? '';
        
        if (empty($dashboardType)) {
            throw new Exception('Dashboard type is required', 400);
        }
        
        $conn = $this->db->getConnection();
        
        $sql = "
            SELECT * FROM widget_configurations 
            WHERE dashboard_type = ? AND user_id IS NULL AND is_default = TRUE AND is_active = TRUE
            ORDER BY priority_order DESC, position_row ASC, position_column ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $dashboardType);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $configurations = [];
        while ($row = $result->fetch_assoc()) {
            // Parse JSON fields
            $row['config'] = json_decode($row['config'] ?? '{}', true);
            $row['styling'] = json_decode($row['styling'] ?? '{}', true);
            $row['permissions'] = json_decode($row['permissions'] ?? '[]', true);
            
            $configurations[] = $row;
        }
        
        return [
            'success' => true,
            'data' => $configurations,
            'count' => count($configurations)
        ];
    }
    
    /**
     * Reset user configurations to defaults
     */
    private function resetToDefaults($input) {
        $dashboardType = $input['dashboard_type'] ?? '';
        
        if (empty($dashboardType)) {
            throw new Exception('Dashboard type is required', 400);
        }
        
        $userId = $this->securityManager->getCurrentUserId();
        $conn = $this->db->getConnection();
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete user's custom configurations for this dashboard
            $deleteSql = "DELETE FROM widget_configurations WHERE dashboard_type = ? AND user_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param('si', $dashboardType, $userId);
            $deleteStmt->execute();
            
            $deletedCount = $deleteStmt->affected_rows;
            
            // Log the action
            $this->logConfigurationAction('RESET_TO_DEFAULTS', $dashboardType, $userId);
            
            $conn->commit();
            
            return [
                'success' => true,
                'message' => 'Configurations reset to defaults successfully',
                'deleted_count' => $deletedCount
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Apply security filtering to configurations
     */
    private function applySecurityFiltering($configurations) {
        $userRole = $this->securityManager->getCurrentUserRole();
        $userPermissions = $this->securityManager->getCurrentUserPermissions();
        
        return array_filter($configurations, function($config) use ($userRole, $userPermissions) {
            // Check if user has required permissions for this widget
            if (!empty($config['permissions'])) {
                foreach ($config['permissions'] as $permission) {
                    if (strpos($permission, ':') !== false) {
                        list($viewType, $level) = explode(':', $permission, 2);
                        
                        // Check if user has this specific permission
                        if (!$this->securityManager->hasPermission($viewType, $level)) {
                            return false;
                        }
                    }
                }
            }
            
            return true;
        });
    }
    
    /**
     * Log configuration actions for audit trail
     */
    private function logConfigurationAction($action, $widgetId, $userId) {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "
                INSERT INTO audit_logs (
                    user_id, action, entity_type, entity_id, 
                    details, ip_address, user_agent, created_at
                ) VALUES (?, ?, 'widget_configuration', ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $conn->prepare($sql);
            
            $details = json_encode([
                'action' => $action,
                'widget_id' => $widgetId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt->bind_param('isssss', $userId, $action, $widgetId, $details, $ipAddress, $userAgent);
            $stmt->execute();
            
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log configuration action: " . $e->getMessage());
        }
    }
    
    /**
     * Validate widget configuration data
     */
    private function validateConfiguration($config) {
        $errors = [];
        
        // Required fields
        if (empty($config['widget_id'])) {
            $errors[] = 'Widget ID is required';
        }
        
        if (empty($config['dashboard_type'])) {
            $errors[] = 'Dashboard type is required';
        }
        
        if (empty($config['widget_type'])) {
            $errors[] = 'Widget type is required';
        }
        
        // Validate dashboard type
        $validDashboardTypes = ['admin_main', 'center_management', 'members_management', 'contributions_management'];
        if (!empty($config['dashboard_type']) && !in_array($config['dashboard_type'], $validDashboardTypes)) {
            $errors[] = 'Invalid dashboard type';
        }
        
        // Validate widget type
        $validWidgetTypes = ['kpi_card', 'chart', 'filter_panel', 'export_button', 'notification_panel', 'search_widget'];
        if (!empty($config['widget_type']) && !in_array($config['widget_type'], $validWidgetTypes)) {
            $errors[] = 'Invalid widget type';
        }
        
        // Validate position values
        if (isset($config['position_row']) && ($config['position_row'] < 1 || $config['position_row'] > 10)) {
            $errors[] = 'Position row must be between 1 and 10';
        }
        
        if (isset($config['position_column']) && ($config['position_column'] < 1 || $config['position_column'] > 12)) {
            $errors[] = 'Position column must be between 1 and 12';
        }
        
        if (isset($config['position_span']) && ($config['position_span'] < 1 || $config['position_span'] > 12)) {
            $errors[] = 'Position span must be between 1 and 12';
        }
        
        // Validate percentage and pixel values
        if (isset($config['width_percentage']) && ($config['width_percentage'] < 1 || $config['width_percentage'] > 100)) {
            $errors[] = 'Width percentage must be between 1 and 100';
        }
        
        if (isset($config['height_pixels']) && ($config['height_pixels'] < 50 || $config['height_pixels'] > 2000)) {
            $errors[] = 'Height pixels must be between 50 and 2000';
        }
        
        // Validate refresh interval
        if (isset($config['refresh_interval']) && ($config['refresh_interval'] < 0 || $config['refresh_interval'] > 3600)) {
            $errors[] = 'Refresh interval must be between 0 and 3600 seconds';
        }
        
        return $errors;
    }
}

// Handle the request
try {
    header('Content-Type: application/json');
    
    $api = new WidgetConfigurationsAPI();
    $response = $api->handleRequest();
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
?>