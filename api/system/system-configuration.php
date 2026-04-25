<?php
/**
 * System Configuration API
 * 
 * Manages system configuration exclusively for System Administrators.
 * Super Administrators have read-only access to non-sensitive configuration.
 * 
 * Features:
 * - Configuration management (CRUD operations)
 * - Sensitive data encryption
 * - Configuration validation
 * - Backup and restore
 * - Change tracking and audit
 * - Role-based access control
 * 
 * @author WDB Development Team
 * @version 1.0.0
 * @since 2024-12-19
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../services/SystemAdministratorService.php';
require_once __DIR__ . '/../config/database.php';

class SystemConfigurationController {
    private $db;
    private $systemAdminService;
    private $currentUser;
    
    // Configuration categories
    const CATEGORY_GENERAL = 'general';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_DATABASE = 'database';
    const CATEGORY_EMAIL = 'email';
    const CATEGORY_BACKUP = 'backup';
    const CATEGORY_MONITORING = 'monitoring';
    const CATEGORY_PERFORMANCE = 'performance';
    
    // Configuration types
    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_JSON = 'json';
    const TYPE_ENCRYPTED = 'encrypted';
    
    public function __construct() {
        $this->initializeDatabase();
        $this->systemAdminService = new SystemAdministratorService();
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
            $this->sendError('Database connection failed', 500);
        }
    }
    
    public function handleRequest() {
        try {
            // Authenticate user
            $sessionToken = $this->getSessionToken();
            if (!$sessionToken) {
                $this->sendError('Authentication required', 401);
                return;
            }
            
            $authResult = $this->systemAdminService->validateSession($sessionToken);
            if (!$authResult['success']) {
                $this->sendError($authResult['error'], 401);
                return;
            }
            
            $this->currentUser = $authResult['user'];
            
            // Check role permissions
            if (!in_array($this->currentUser['role'], ['system_admin', 'superadmin'])) {
                $this->sendError('Insufficient privileges', 403);
                return;
            }
            
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? '';
            
            switch ($method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action);
                    break;
                case 'PUT':
                    $this->handlePut($action);
                    break;
                case 'DELETE':
                    $this->handleDelete($action);
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("SystemConfigurationController Error: " . $e->getMessage());
            $this->sendError('Internal server error', 500);
        }
    }
    
    private function handleGet($action) {
        switch ($action) {
            case 'get_all':
                $this->getAllConfiguration();
                break;
            case 'get_category':
                $this->getConfigurationByCategory();
                break;
            case 'get_key':
                $this->getConfigurationByKey();
                break;
            case 'export':
                $this->exportConfiguration();
                break;
            case 'validate':
                $this->validateConfiguration();
                break;
            default:
                $this->getAllConfiguration();
        }
    }
    
    private function handlePost($action) {
        // Only system_admin can perform write operations
        if ($this->currentUser['role'] !== 'system_admin') {
            $this->sendError('System Administrator privileges required for configuration changes', 403);
            return;
        }
        
        switch ($action) {
            case 'create':
                $this->createConfiguration();
                break;
            case 'import':
                $this->importConfiguration();
                break;
            case 'backup':
                $this->backupConfiguration();
                break;
            case 'restore':
                $this->restoreConfiguration();
                break;
            case 'reset_category':
                $this->resetCategoryToDefaults();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    private function handlePut($action) {
        // Only system_admin can perform write operations
        if ($this->currentUser['role'] !== 'system_admin') {
            $this->sendError('System Administrator privileges required for configuration changes', 403);
            return;
        }
        
        switch ($action) {
            case 'update':
                $this->updateConfiguration();
                break;
            case 'bulk_update':
                $this->bulkUpdateConfiguration();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    private function handleDelete($action) {
        // Only system_admin can perform write operations
        if ($this->currentUser['role'] !== 'system_admin') {
            $this->sendError('System Administrator privileges required for configuration changes', 403);
            return;
        }
        
        switch ($action) {
            case 'delete':
                $this->deleteConfiguration();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    /**
     * Get all configuration settings
     */
    private function getAllConfiguration() {
        try {
            $category = $_GET['category'] ?? '';
            $includeSensitive = $this->currentUser['role'] === 'system_admin';
            
            $whereClause = '';
            $params = [];
            
            if ($category) {
                $whereClause = 'WHERE category = ?';
                $params[] = $category;
            }
            
            if (!$includeSensitive) {
                $whereClause .= $whereClause ? ' AND is_sensitive = FALSE' : 'WHERE is_sensitive = FALSE';
            }
            
            $sql = "
                SELECT 
                    id, config_key, config_value, config_type, category, description,
                    is_sensitive, requires_restart, validation_rules, default_value,
                    last_modified_by, last_modified_at, created_at
                FROM system_configuration 
                $whereClause
                ORDER BY category, config_key
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $configuration = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process configuration values
            foreach ($configuration as &$config) {
                if ($config['is_sensitive'] && $this->currentUser['role'] !== 'system_admin') {
                    $config['config_value'] = '••••••••';
                } else {
                    $config['config_value'] = $this->decryptConfigValue($config);
                }
                
                // Parse validation rules
                if ($config['validation_rules']) {
                    $config['validation_rules'] = json_decode($config['validation_rules'], true);
                }
            }
            
            $this->logConfigurationAccess('get_all_configuration', [
                'category' => $category,
                'count' => count($configuration),
                'include_sensitive' => $includeSensitive
            ]);
            
            $this->sendSuccess(['configuration' => $configuration]);
            
        } catch (Exception $e) {
            error_log("Get all configuration error: " . $e->getMessage());
            $this->sendError('Failed to retrieve configuration', 500);
        }
    }
    
    /**
     * Get configuration by category
     */
    private function getConfigurationByCategory() {
        try {
            $category = $_GET['category'] ?? '';
            if (empty($category)) {
                $this->sendError('Category parameter required', 400);
                return;
            }
            
            $includeSensitive = $this->currentUser['role'] === 'system_admin';
            
            $whereClause = 'WHERE category = ?';
            $params = [$category];
            
            if (!$includeSensitive) {
                $whereClause .= ' AND is_sensitive = FALSE';
            }
            
            $sql = "
                SELECT 
                    id, config_key, config_value, config_type, category, description,
                    is_sensitive, requires_restart, validation_rules, default_value,
                    last_modified_by, last_modified_at, created_at
                FROM system_configuration 
                $whereClause
                ORDER BY config_key
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $configuration = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process configuration values
            foreach ($configuration as &$config) {
                if ($config['is_sensitive'] && $this->currentUser['role'] !== 'system_admin') {
                    $config['config_value'] = '••••••••';
                } else {
                    $config['config_value'] = $this->decryptConfigValue($config);
                }
                
                if ($config['validation_rules']) {
                    $config['validation_rules'] = json_decode($config['validation_rules'], true);
                }
            }
            
            $this->logConfigurationAccess('get_category_configuration', [
                'category' => $category,
                'count' => count($configuration)
            ]);
            
            $this->sendSuccess(['configuration' => $configuration]);
            
        } catch (Exception $e) {
            error_log("Get configuration by category error: " . $e->getMessage());
            $this->sendError('Failed to retrieve configuration', 500);
        }
    }
    
    /**
     * Get configuration by key
     */
    private function getConfigurationByKey() {
        try {
            $configKey = $_GET['key'] ?? '';
            if (empty($configKey)) {
                $this->sendError('Key parameter required', 400);
                return;
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    id, config_key, config_value, config_type, category, description,
                    is_sensitive, requires_restart, validation_rules, default_value,
                    last_modified_by, last_modified_at, created_at
                FROM system_configuration 
                WHERE config_key = ?
            ");
            $stmt->execute([$configKey]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                $this->sendError('Configuration key not found', 404);
                return;
            }
            
            // Check access to sensitive configuration
            if ($config['is_sensitive'] && $this->currentUser['role'] !== 'system_admin') {
                $this->sendError('Access denied to sensitive configuration', 403);
                return;
            }
            
            $config['config_value'] = $this->decryptConfigValue($config);
            
            if ($config['validation_rules']) {
                $config['validation_rules'] = json_decode($config['validation_rules'], true);
            }
            
            $this->logConfigurationAccess('get_key_configuration', [
                'config_key' => $configKey,
                'is_sensitive' => $config['is_sensitive']
            ]);
            
            $this->sendSuccess(['configuration' => $config]);
            
        } catch (Exception $e) {
            error_log("Get configuration by key error: " . $e->getMessage());
            $this->sendError('Failed to retrieve configuration', 500);
        }
    }
    
    /**
     * Create new configuration
     */
    private function createConfiguration() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $requiredFields = ['config_key', 'config_value', 'config_type', 'category'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    $this->sendError("Field '$field' is required", 400);
                    return;
                }
            }
            
            // Validate configuration type
            $validTypes = [self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_BOOLEAN, self::TYPE_JSON, self::TYPE_ENCRYPTED];
            if (!in_array($input['config_type'], $validTypes)) {
                $this->sendError('Invalid configuration type', 400);
                return;
            }
            
            // Validate configuration value
            $validationResult = $this->validateConfigValue($input['config_value'], $input['config_type'], $input['validation_rules'] ?? null);
            if (!$validationResult['valid']) {
                $this->sendError($validationResult['error'], 400);
                return;
            }
            
            // Encrypt sensitive values
            $configValue = $input['config_value'];
            if ($input['config_type'] === self::TYPE_ENCRYPTED || ($input['is_sensitive'] ?? false)) {
                $configValue = $this->encryptConfigValue($configValue);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO system_configuration (
                    config_key, config_value, config_type, category, description,
                    is_sensitive, requires_restart, validation_rules, default_value,
                    last_modified_by, last_modified_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $input['config_key'],
                $configValue,
                $input['config_type'],
                $input['category'],
                $input['description'] ?? null,
                $input['is_sensitive'] ?? false,
                $input['requires_restart'] ?? false,
                isset($input['validation_rules']) ? json_encode($input['validation_rules']) : null,
                $input['default_value'] ?? null,
                $this->currentUser['id']
            ]);
            
            $configId = $this->db->lastInsertId();
            
            $this->logConfigurationAccess('create_configuration', [
                'config_id' => $configId,
                'config_key' => $input['config_key'],
                'category' => $input['category'],
                'is_sensitive' => $input['is_sensitive'] ?? false
            ]);
            
            $this->sendSuccess([
                'message' => 'Configuration created successfully',
                'config_id' => $configId
            ]);
            
        } catch (Exception $e) {
            error_log("Create configuration error: " . $e->getMessage());
            $this->sendError('Failed to create configuration', 500);
        }
    }
    
    /**
     * Update configuration
     */
    private function updateConfiguration() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['config_key']) || empty($input['config_key'])) {
                $this->sendError('Configuration key is required', 400);
                return;
            }
            
            // Get existing configuration
            $stmt = $this->db->prepare("SELECT * FROM system_configuration WHERE config_key = ?");
            $stmt->execute([$input['config_key']]);
            $existingConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existingConfig) {
                $this->sendError('Configuration key not found', 404);
                return;
            }
            
            // Validate new value if provided
            if (isset($input['config_value'])) {
                $validationResult = $this->validateConfigValue(
                    $input['config_value'], 
                    $input['config_type'] ?? $existingConfig['config_type'],
                    $input['validation_rules'] ?? json_decode($existingConfig['validation_rules'], true)
                );
                
                if (!$validationResult['valid']) {
                    $this->sendError($validationResult['error'], 400);
                    return;
                }
            }
            
            // Build update query
            $updateFields = [];
            $params = [];
            
            if (isset($input['config_value'])) {
                $configValue = $input['config_value'];
                if ($existingConfig['config_type'] === self::TYPE_ENCRYPTED || $existingConfig['is_sensitive']) {
                    $configValue = $this->encryptConfigValue($configValue);
                }
                $updateFields[] = 'config_value = ?';
                $params[] = $configValue;
            }
            
            if (isset($input['description'])) {
                $updateFields[] = 'description = ?';
                $params[] = $input['description'];
            }
            
            if (isset($input['validation_rules'])) {
                $updateFields[] = 'validation_rules = ?';
                $params[] = json_encode($input['validation_rules']);
            }
            
            if (isset($input['default_value'])) {
                $updateFields[] = 'default_value = ?';
                $params[] = $input['default_value'];
            }
            
            if (empty($updateFields)) {
                $this->sendError('No fields to update', 400);
                return;
            }
            
            $updateFields[] = 'last_modified_by = ?';
            $updateFields[] = 'last_modified_at = NOW()';
            $params[] = $this->currentUser['id'];
            $params[] = $input['config_key'];
            
            $sql = "UPDATE system_configuration SET " . implode(', ', $updateFields) . " WHERE config_key = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $this->logConfigurationAccess('update_configuration', [
                'config_key' => $input['config_key'],
                'updated_fields' => array_keys($input),
                'is_sensitive' => $existingConfig['is_sensitive']
            ]);
            
            $this->sendSuccess(['message' => 'Configuration updated successfully']);
            
        } catch (Exception $e) {
            error_log("Update configuration error: " . $e->getMessage());
            $this->sendError('Failed to update configuration', 500);
        }
    }
    
    /**
     * Delete configuration
     */
    private function deleteConfiguration() {
        try {
            $configKey = $_GET['key'] ?? '';
            if (empty($configKey)) {
                $this->sendError('Configuration key required', 400);
                return;
            }
            
            // Check if configuration exists
            $stmt = $this->db->prepare("SELECT id, is_sensitive FROM system_configuration WHERE config_key = ?");
            $stmt->execute([$configKey]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                $this->sendError('Configuration key not found', 404);
                return;
            }
            
            // Delete configuration
            $stmt = $this->db->prepare("DELETE FROM system_configuration WHERE config_key = ?");
            $stmt->execute([$configKey]);
            
            $this->logConfigurationAccess('delete_configuration', [
                'config_key' => $configKey,
                'config_id' => $config['id'],
                'is_sensitive' => $config['is_sensitive']
            ]);
            
            $this->sendSuccess(['message' => 'Configuration deleted successfully']);
            
        } catch (Exception $e) {
            error_log("Delete configuration error: " . $e->getMessage());
            $this->sendError('Failed to delete configuration', 500);
        }
    }
    
    /**
     * Export configuration
     */
    private function exportConfiguration() {
        try {
            $category = $_GET['category'] ?? '';
            $includeSensitive = ($this->currentUser['role'] === 'system_admin') && ($_GET['include_sensitive'] === 'true');
            
            $whereClause = '';
            $params = [];
            
            if ($category) {
                $whereClause = 'WHERE category = ?';
                $params[] = $category;
            }
            
            if (!$includeSensitive) {
                $whereClause .= $whereClause ? ' AND is_sensitive = FALSE' : 'WHERE is_sensitive = FALSE';
            }
            
            $sql = "
                SELECT 
                    config_key, config_value, config_type, category, description,
                    is_sensitive, requires_restart, validation_rules, default_value
                FROM system_configuration 
                $whereClause
                ORDER BY category, config_key
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $configuration = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process for export
            $exportData = [
                'export_info' => [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'exported_by' => $this->currentUser['username'],
                    'category' => $category ?: 'all',
                    'include_sensitive' => $includeSensitive,
                    'total_configs' => count($configuration)
                ],
                'configuration' => []
            ];
            
            foreach ($configuration as $config) {
                $exportConfig = [
                    'config_key' => $config['config_key'],
                    'config_type' => $config['config_type'],
                    'category' => $config['category'],
                    'description' => $config['description'],
                    'requires_restart' => (bool)$config['requires_restart'],
                    'validation_rules' => $config['validation_rules'] ? json_decode($config['validation_rules'], true) : null,
                    'default_value' => $config['default_value']
                ];
                
                // Handle sensitive values
                if ($config['is_sensitive']) {
                    if ($includeSensitive) {
                        $exportConfig['config_value'] = $this->decryptConfigValue($config);
                        $exportConfig['is_sensitive'] = true;
                    } else {
                        $exportConfig['config_value'] = '••••••••';
                        $exportConfig['is_sensitive'] = true;
                    }
                } else {
                    $exportConfig['config_value'] = $config['config_value'];
                    $exportConfig['is_sensitive'] = false;
                }
                
                $exportData['configuration'][] = $exportConfig;
            }
            
            $this->logConfigurationAccess('export_configuration', [
                'category' => $category,
                'include_sensitive' => $includeSensitive,
                'count' => count($configuration)
            ]);
            
            // Set headers for file download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="system_config_export_' . date('Y-m-d_H-i-s') . '.json"');
            
            echo json_encode($exportData, JSON_PRETTY_PRINT);
            
        } catch (Exception $e) {
            error_log("Export configuration error: " . $e->getMessage());
            $this->sendError('Failed to export configuration', 500);
        }
    }
    
    /**
     * Validate configuration value
     */
    private function validateConfigValue($value, $type, $validationRules = null) {
        // Type validation
        switch ($type) {
            case self::TYPE_INTEGER:
                if (!is_numeric($value)) {
                    return ['valid' => false, 'error' => 'Value must be a valid integer'];
                }
                $value = (int)$value;
                break;
                
            case self::TYPE_BOOLEAN:
                if (!in_array(strtolower($value), ['true', 'false', '1', '0'])) {
                    return ['valid' => false, 'error' => 'Value must be true or false'];
                }
                break;
                
            case self::TYPE_JSON:
                if (json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE) {
                    return ['valid' => false, 'error' => 'Value must be valid JSON'];
                }
                break;
        }
        
        // Custom validation rules
        if ($validationRules && is_array($validationRules)) {
            foreach ($validationRules as $rule => $ruleValue) {
                switch ($rule) {
                    case 'min_length':
                        if (strlen($value) < $ruleValue) {
                            return ['valid' => false, 'error' => "Value must be at least $ruleValue characters"];
                        }
                        break;
                        
                    case 'max_length':
                        if (strlen($value) > $ruleValue) {
                            return ['valid' => false, 'error' => "Value must not exceed $ruleValue characters"];
                        }
                        break;
                        
                    case 'min_value':
                        if (is_numeric($value) && $value < $ruleValue) {
                            return ['valid' => false, 'error' => "Value must be at least $ruleValue"];
                        }
                        break;
                        
                    case 'max_value':
                        if (is_numeric($value) && $value > $ruleValue) {
                            return ['valid' => false, 'error' => "Value must not exceed $ruleValue"];
                        }
                        break;
                        
                    case 'pattern':
                        if (!preg_match($ruleValue, $value)) {
                            return ['valid' => false, 'error' => 'Value does not match required pattern'];
                        }
                        break;
                        
                    case 'allowed_values':
                        if (!in_array($value, $ruleValue)) {
                            return ['valid' => false, 'error' => 'Value is not in allowed list'];
                        }
                        break;
                }
            }
        }
        
        return ['valid' => true];
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
    private function decryptConfigValue($config) {
        if ($config['config_type'] !== self::TYPE_ENCRYPTED && !$config['is_sensitive']) {
            return $config['config_value'];
        }
        
        try {
            $key = $this->getEncryptionKey();
            $data = base64_decode($config['config_value']);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        } catch (Exception $e) {
            return $config['config_value']; // Return as-is if decryption fails
        }
    }
    
    /**
     * Get encryption key
     */
    private function getEncryptionKey() {
        return hash('sha256', $_ENV['APP_KEY'] ?? 'default-key-change-in-production', true);
    }
    
    /**
     * Get session token
     */
    private function getSessionToken() {
        // Try Authorization header first
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (strpos($auth, 'Bearer ') === 0) {
                return substr($auth, 7);
            }
        }
        
        // Try cookie
        if (isset($_COOKIE['system_admin_session'])) {
            return $_COOKIE['system_admin_session'];
        }
        
        // Try GET parameter
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }
        
        return null;
    }
    
    /**
     * Log configuration access
     */
    private function logConfigurationAccess($operationType, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_operations_log (
                    operation_id, user_id, user_role, operation_type, operation_category,
                    operation_details, ip_address, user_agent, success, security_level, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                'CFG_' . uniqid(),
                $this->currentUser['id'],
                $this->currentUser['role'],
                $operationType,
                'system_configuration',
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                true,
                'medium'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log configuration access: " . $e->getMessage());
        }
    }
    
    private function sendSuccess($data) {
        echo json_encode(['success' => true] + $data);
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Initialize and handle request
try {
    $controller = new SystemConfigurationController();
    $controller->handleRequest();
} catch (Exception $e) {
    error_log("SystemConfigurationController Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>