<?php
/**
 * Filter Presets API
 * Manages filter presets for analytics widgets
 * Requirements: 6.2, 6.3
 * Task: 3.5 Implement FilterPanel component - Presets API support
 */

require_once '../config/database.php';
require_once '../security/SecurityManager.php';
require_once '../handlers/ErrorHandler.php';

class FilterPresetsAPI {
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
            
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            
            switch ($action) {
                case 'get_presets':
                    return $this->getPresets($input);
                    
                case 'save_preset':
                    return $this->savePreset($input);
                    
                case 'delete_preset':
                    return $this->deletePreset($input);
                    
                case 'update_preset':
                    return $this->updatePreset($input);
                    
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
     * Get filter presets
     */
    private function getPresets($input) {
        $dashboardType = $input['dashboard_type'] ?? '';
        $userId = $input['user_id'] ?? '';
        
        if (empty($dashboardType) || empty($userId)) {
            throw new Exception('Dashboard type and user ID are required', 400);
        }
        
        $conn = $this->db->getConnection();
        
        // Create table if it doesn't exist
        $this->createPresetsTableIfNotExists();
        
        $sql = "
            SELECT id, name, description, filters, filter_logic, is_global, created_at
            FROM analytics_filter_presets 
            WHERE (dashboard_type = ? AND user_id = ?) OR is_global = 1
            ORDER BY is_global DESC, name ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $dashboardType, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $presets = [];
        while ($row = $result->fetch_assoc()) {
            $presets[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'filters' => $row['filters'],
                'filter_logic' => $row['filter_logic'],
                'is_global' => (bool)$row['is_global'],
                'created_at' => $row['created_at']
            ];
        }
        
        return [
            'success' => true,
            'data' => $presets
        ];
    }
    
    /**
     * Save filter preset
     */
    private function savePreset($input) {
        $preset = $input['preset'] ?? [];
        
        if (empty($preset['name']) || empty($preset['filters'])) {
            throw new Exception('Preset name and filters are required', 400);
        }
        
        $conn = $this->db->getConnection();
        
        // Create table if it doesn't exist
        $this->createPresetsTableIfNotExists();
        
        $sql = "
            INSERT INTO analytics_filter_presets 
            (name, description, filters, filter_logic, dashboard_type, user_id, is_global, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssss',
            $preset['name'],
            $preset['description'] ?? '',
            $preset['filters'],
            $preset['filter_logic'] ?? 'AND',
            $preset['dashboard_type'],
            $preset['user_id']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save preset: ' . $stmt->error, 500);
        }
        
        $presetId = $conn->insert_id;
        
        return [
            'success' => true,
            'data' => [
                'id' => $presetId,
                'message' => 'Preset saved successfully'
            ]
        ];
    }
    
    /**
     * Delete filter preset
     */
    private function deletePreset($input) {
        $presetId = $input['preset_id'] ?? '';
        $userId = $input['user_id'] ?? '';
        
        if (empty($presetId) || empty($userId)) {
            throw new Exception('Preset ID and user ID are required', 400);
        }
        
        $conn = $this->db->getConnection();
        
        // Only allow users to delete their own presets (not global ones)
        $sql = "DELETE FROM analytics_filter_presets WHERE id = ? AND user_id = ? AND is_global = 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $presetId, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete preset: ' . $stmt->error, 500);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Preset not found or cannot be deleted', 404);
        }
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Preset deleted successfully'
            ]
        ];
    }
    
    /**
     * Update filter preset
     */
    private function updatePreset($input) {
        $presetId = $input['preset_id'] ?? '';
        $preset = $input['preset'] ?? [];
        $userId = $input['user_id'] ?? '';
        
        if (empty($presetId) || empty($preset['name']) || empty($userId)) {
            throw new Exception('Preset ID, name, and user ID are required', 400);
        }
        
        $conn = $this->db->getConnection();
        
        // Only allow users to update their own presets
        $sql = "
            UPDATE analytics_filter_presets 
            SET name = ?, description = ?, filters = ?, filter_logic = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ? AND is_global = 0
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssss',
            $preset['name'],
            $preset['description'] ?? '',
            $preset['filters'] ?? '',
            $preset['filter_logic'] ?? 'AND',
            $presetId,
            $userId
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update preset: ' . $stmt->error, 500);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Preset not found or no changes made', 404);
        }
        
        return [
            'success' => true,
            'data' => [
                'message' => 'Preset updated successfully'
            ]
        ];
    }
    
    /**
     * Create presets table if it doesn't exist
     */
    private function createPresetsTableIfNotExists() {
        $conn = $this->db->getConnection();
        
        $sql = "
            CREATE TABLE IF NOT EXISTS analytics_filter_presets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                filters JSON NOT NULL,
                filter_logic ENUM('AND', 'OR') DEFAULT 'AND',
                dashboard_type VARCHAR(100) NOT NULL,
                user_id VARCHAR(100) NOT NULL,
                is_global BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_dashboard_user (dashboard_type, user_id),
                INDEX idx_global (is_global),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$conn->query($sql)) {
            throw new Exception('Failed to create presets table: ' . $conn->error, 500);
        }
    }
}

// Handle the request
try {
    header('Content-Type: application/json');
    
    $api = new FilterPresetsAPI();
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