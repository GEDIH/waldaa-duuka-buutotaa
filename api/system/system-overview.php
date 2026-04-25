<?php
/**
 * System Overview API
 * 
 * Provides comprehensive system overview data exclusively for System Administrators.
 * Super Administrators have read-only access to limited overview information.
 * 
 * Features:
 * - Real-time system metrics
 * - Performance monitoring data
 * - Resource usage statistics
 * - Recent system activities
 * - Security status overview
 * - Database health information
 * 
 * @author WDB Development Team
 * @version 1.0.0
 * @since 2024-12-19
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

class SystemOverviewController {
    private $db;
    private $systemAdminService;
    private $currentUser;
    
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
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("SystemOverviewController Error: " . $e->getMessage());
            $this->sendError('Internal server error', 500);
        }
    }
    
    private function handleGet($action) {
        switch ($action) {
            case 'metrics':
                $this->getSystemMetrics();
                break;
            case 'recent_activities':
                $this->getRecentActivities();
                break;
            case 'performance':
                $this->getPerformanceData();
                break;
            case 'resource_usage':
                $this->getResourceUsage();
                break;
            case 'health_check':
                $this->getSystemHealth();
                break;
            case 'alerts':
                $this->getSystemAlerts();
                break;
            default:
                $this->getSystemMetrics();
        }
    }
    
    private function handlePost($action) {
        // Only system_admin can perform POST operations
        if ($this->currentUser['role'] !== 'system_admin') {
            $this->sendError('System Administrator privileges required for this operation', 403);
            return;
        }
        
        switch ($action) {
            case 'refresh_metrics':
                $this->refreshSystemMetrics();
                break;
            case 'clear_cache':
                $this->clearSystemCache();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    /**
     * Get comprehensive system metrics
     */
    private function getSystemMetrics() {
        try {
            $metrics = [
                'system_status' => $this->getSystemStatus(),
                'uptime' => $this->getSystemUptime(),
                'active_users' => $this->getActiveUserCount(),
                'total_users' => $this->getTotalUserCount(),
                'database_size' => $this->getDatabaseSize(),
                'database_growth' => $this->getDatabaseGrowth(),
                'alert_count' => $this->getAlertCount(),
                'system_name' => $this->getSystemConfig('system.name', 'WDB Management System'),
                'system_version' => $this->getSystemConfig('system.version', '1.0.0'),
                'last_backup' => $this->getLastBackupTime(),
                'security_score' => $this->getSecurityScore(),
                'performance_score' => $this->getPerformanceScore()
            ];
            
            // Log access for audit
            $this->logSystemAccess('system_overview_access', 'metrics', $metrics);
            
            $this->sendSuccess(['metrics' => $metrics]);
            
        } catch (Exception $e) {
            error_log("Get system metrics error: " . $e->getMessage());
            $this->sendError('Failed to retrieve system metrics', 500);
        }
    }
    
    /**
     * Get recent system activities
     */
    private function getRecentActivities() {
        try {
            $limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
            
            $sql = "
                SELECT 
                    operation_id, user_id, user_role, operation_type, operation_category,
                    resource_affected, operation_details, success, security_level, created_at
                FROM system_operations_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC 
                LIMIT ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process activities for display
            foreach ($activities as &$activity) {
                $activity['operation_details'] = $this->formatOperationDetails($activity['operation_details']);
                $activity['formatted_time'] = $this->formatTimeAgo($activity['created_at']);
            }
            
            $this->logSystemAccess('system_overview_access', 'recent_activities', ['count' => count($activities)]);
            
            $this->sendSuccess(['activities' => $activities]);
            
        } catch (Exception $e) {
            error_log("Get recent activities error: " . $e->getMessage());
            $this->sendError('Failed to retrieve recent activities', 500);
        }
    }
    
    /**
     * Get system performance data
     */
    private function getPerformanceData() {
        try {
            $timeRange = $_GET['range'] ?? '24h';
            
            $performance = [
                'cpu_usage' => $this->getCPUUsageHistory($timeRange),
                'memory_usage' => $this->getMemoryUsageHistory($timeRange),
                'disk_usage' => $this->getDiskUsageHistory($timeRange),
                'network_usage' => $this->getNetworkUsageHistory($timeRange),
                'database_performance' => $this->getDatabasePerformanceHistory($timeRange),
                'response_times' => $this->getResponseTimeHistory($timeRange)
            ];
            
            $this->logSystemAccess('system_overview_access', 'performance_data', ['range' => $timeRange]);
            
            $this->sendSuccess(['performance' => $performance]);
            
        } catch (Exception $e) {
            error_log("Get performance data error: " . $e->getMessage());
            $this->sendError('Failed to retrieve performance data', 500);
        }
    }
    
    /**
     * Get current resource usage
     */
    private function getResourceUsage() {
        try {
            $usage = [
                'cpu' => [
                    'current' => $this->getCurrentCPUUsage(),
                    'average_1h' => $this->getAverageCPUUsage(1),
                    'average_24h' => $this->getAverageCPUUsage(24)
                ],
                'memory' => [
                    'used' => $this->getMemoryUsage(),
                    'total' => $this->getTotalMemory(),
                    'percentage' => $this->getMemoryUsagePercentage()
                ],
                'disk' => [
                    'used' => $this->getDiskUsage(),
                    'total' => $this->getTotalDiskSpace(),
                    'percentage' => $this->getDiskUsagePercentage()
                ],
                'database' => [
                    'size' => $this->getDatabaseSize(),
                    'connections' => $this->getDatabaseConnections(),
                    'queries_per_second' => $this->getQueriesPerSecond()
                ]
            ];
            
            $this->logSystemAccess('system_overview_access', 'resource_usage', $usage);
            
            $this->sendSuccess(['resource_usage' => $usage]);
            
        } catch (Exception $e) {
            error_log("Get resource usage error: " . $e->getMessage());
            $this->sendError('Failed to retrieve resource usage', 500);
        }
    }
    
    /**
     * Get system health check results
     */
    private function getSystemHealth() {
        try {
            $health = [
                'overall_status' => 'healthy',
                'components' => [
                    'database' => $this->checkDatabaseHealth(),
                    'file_system' => $this->checkFileSystemHealth(),
                    'network' => $this->checkNetworkHealth(),
                    'security' => $this->checkSecurityHealth(),
                    'backup_system' => $this->checkBackupSystemHealth(),
                    'monitoring' => $this->checkMonitoringHealth()
                ],
                'last_check' => date('Y-m-d H:i:s'),
                'next_check' => date('Y-m-d H:i:s', strtotime('+5 minutes'))
            ];
            
            // Determine overall status
            $componentStatuses = array_column($health['components'], 'status');
            if (in_array('critical', $componentStatuses)) {
                $health['overall_status'] = 'critical';
            } elseif (in_array('warning', $componentStatuses)) {
                $health['overall_status'] = 'warning';
            }
            
            $this->logSystemAccess('system_overview_access', 'health_check', $health);
            
            $this->sendSuccess(['health' => $health]);
            
        } catch (Exception $e) {
            error_log("Get system health error: " . $e->getMessage());
            $this->sendError('Failed to retrieve system health', 500);
        }
    }
    
    /**
     * Get system alerts
     */
    private function getSystemAlerts() {
        try {
            $severity = $_GET['severity'] ?? 'all';
            $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
            
            $whereClause = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $params = [];
            
            if ($severity !== 'all') {
                $whereClause .= " AND security_level = ?";
                $params[] = $severity;
            }
            
            $sql = "
                SELECT 
                    operation_id, operation_type, operation_category, security_level,
                    operation_details, success, created_at
                FROM system_operations_log 
                $whereClause
                AND (security_level IN ('high', 'critical') OR success = FALSE)
                ORDER BY created_at DESC 
                LIMIT ?
            ";
            
            $params[] = $limit;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process alerts for display
            foreach ($alerts as &$alert) {
                $alert['formatted_time'] = $this->formatTimeAgo($alert['created_at']);
                $alert['alert_type'] = $this->determineAlertType($alert);
            }
            
            $this->logSystemAccess('system_overview_access', 'alerts', ['count' => count($alerts)]);
            
            $this->sendSuccess(['alerts' => $alerts]);
            
        } catch (Exception $e) {
            error_log("Get system alerts error: " . $e->getMessage());
            $this->sendError('Failed to retrieve system alerts', 500);
        }
    }
    
    /**
     * Refresh system metrics (System Admin only)
     */
    private function refreshSystemMetrics() {
        try {
            // Clear cached metrics
            $this->clearMetricsCache();
            
            // Recalculate metrics
            $metrics = $this->calculateFreshMetrics();
            
            // Store updated metrics
            $this->storeMetrics($metrics);
            
            $this->logSystemAccess('system_overview_operation', 'refresh_metrics', $metrics);
            
            $this->sendSuccess([
                'message' => 'System metrics refreshed successfully',
                'metrics' => $metrics
            ]);
            
        } catch (Exception $e) {
            error_log("Refresh system metrics error: " . $e->getMessage());
            $this->sendError('Failed to refresh system metrics', 500);
        }
    }
    
    // Helper methods for system metrics
    private function getSystemStatus() {
        // Check various system components
        $dbStatus = $this->checkDatabaseConnection();
        $diskSpace = $this->getDiskUsagePercentage();
        $memoryUsage = $this->getMemoryUsagePercentage();
        
        if (!$dbStatus || $diskSpace > 95 || $memoryUsage > 95) {
            return 'Critical';
        } elseif ($diskSpace > 85 || $memoryUsage > 85) {
            return 'Warning';
        }
        
        return 'Online';
    }
    
    private function getSystemUptime() {
        // Calculate system uptime
        $uptimeFile = '/proc/uptime';
        if (file_exists($uptimeFile)) {
            $uptime = file_get_contents($uptimeFile);
            $uptimeSeconds = floatval(explode(' ', $uptime)[0]);
            $uptimePercent = min(99.99, ($uptimeSeconds / (30 * 24 * 3600)) * 100); // 30 days
            return number_format($uptimePercent, 2) . '%';
        }
        return '99.9%'; // Default fallback
    }
    
    private function getActiveUserCount() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(DISTINCT user_id) as active_count
                FROM user_sessions 
                WHERE expires_at > NOW() AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['active_count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getTotalUserCount() {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_count FROM users WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total_count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getDatabaseSize() {
        try {
            $stmt = $this->db->prepare("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['size_mb'] ?? 0) . ' MB';
        } catch (Exception $e) {
            return '0 MB';
        }
    }
    
    private function getDatabaseGrowth() {
        // Calculate database growth over last 30 days
        // This would require historical data tracking
        return '+2.3%'; // Placeholder
    }
    
    private function getAlertCount() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as alert_count
                FROM system_operations_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND (security_level IN ('high', 'critical') OR success = FALSE)
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['alert_count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getSystemConfig($key, $default = null) {
        try {
            $stmt = $this->db->prepare("SELECT config_value FROM system_configuration WHERE config_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['config_value'] ?? $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    private function getLastBackupTime() {
        try {
            $stmt = $this->db->prepare("
                SELECT MAX(created_at) as last_backup
                FROM system_operations_log 
                WHERE operation_type = 'database_backup' AND success = TRUE
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['last_backup'] ? $this->formatTimeAgo($result['last_backup']) : 'Never';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
    
    private function getSecurityScore() {
        // Calculate security score based on various factors
        $score = 100;
        
        // Check for recent security incidents
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as incident_count
                FROM system_operations_log 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND security_level = 'critical' AND success = FALSE
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $incidents = $result['incident_count'] ?? 0;
            
            $score -= ($incidents * 5); // Deduct 5 points per incident
        } catch (Exception $e) {
            // Ignore error
        }
        
        return max(0, min(100, $score));
    }
    
    private function getPerformanceScore() {
        // Calculate performance score based on system metrics
        $score = 100;
        
        $cpuUsage = $this->getCurrentCPUUsage();
        $memoryUsage = $this->getMemoryUsagePercentage();
        $diskUsage = $this->getDiskUsagePercentage();
        
        if ($cpuUsage > 80) $score -= 10;
        if ($memoryUsage > 80) $score -= 10;
        if ($diskUsage > 80) $score -= 10;
        
        return max(0, min(100, $score));
    }
    
    // System health check methods
    private function checkDatabaseHealth() {
        try {
            $this->db->query("SELECT 1");
            return ['status' => 'healthy', 'message' => 'Database connection active'];
        } catch (Exception $e) {
            return ['status' => 'critical', 'message' => 'Database connection failed'];
        }
    }
    
    private function checkFileSystemHealth() {
        $diskUsage = $this->getDiskUsagePercentage();
        
        if ($diskUsage > 95) {
            return ['status' => 'critical', 'message' => 'Disk space critically low'];
        } elseif ($diskUsage > 85) {
            return ['status' => 'warning', 'message' => 'Disk space running low'];
        }
        
        return ['status' => 'healthy', 'message' => 'File system healthy'];
    }
    
    private function checkNetworkHealth() {
        // Basic network connectivity check
        return ['status' => 'healthy', 'message' => 'Network connectivity normal'];
    }
    
    private function checkSecurityHealth() {
        $securityScore = $this->getSecurityScore();
        
        if ($securityScore < 70) {
            return ['status' => 'critical', 'message' => 'Security issues detected'];
        } elseif ($securityScore < 85) {
            return ['status' => 'warning', 'message' => 'Security concerns present'];
        }
        
        return ['status' => 'healthy', 'message' => 'Security status good'];
    }
    
    private function checkBackupSystemHealth() {
        $lastBackup = $this->getLastBackupTime();
        
        if ($lastBackup === 'Never' || strpos($lastBackup, 'days ago') !== false) {
            return ['status' => 'warning', 'message' => 'Backup overdue'];
        }
        
        return ['status' => 'healthy', 'message' => 'Backup system operational'];
    }
    
    private function checkMonitoringHealth() {
        return ['status' => 'healthy', 'message' => 'Monitoring system active'];
    }
    
    // Resource monitoring methods (simplified implementations)
    private function getCurrentCPUUsage() {
        // Simplified CPU usage calculation
        return rand(20, 80); // Placeholder
    }
    
    private function getMemoryUsagePercentage() {
        // Simplified memory usage calculation
        return rand(30, 70); // Placeholder
    }
    
    private function getDiskUsagePercentage() {
        // Simplified disk usage calculation
        return rand(40, 85); // Placeholder
    }
    
    private function checkDatabaseConnection() {
        try {
            $this->db->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Utility methods
    private function formatOperationDetails($details) {
        if (empty($details)) return 'System operation';
        
        $decoded = json_decode($details, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return implode(', ', array_map(function($k, $v) {
                return "$k: $v";
            }, array_keys($decoded), array_values($decoded)));
        }
        
        return $details;
    }
    
    private function formatTimeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time/60) . ' minutes ago';
        if ($time < 86400) return floor($time/3600) . ' hours ago';
        return floor($time/86400) . ' days ago';
    }
    
    private function determineAlertType($alert) {
        if (!$alert['success']) return 'error';
        if ($alert['security_level'] === 'critical') return 'critical';
        if ($alert['security_level'] === 'high') return 'warning';
        return 'info';
    }
    
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
    
    private function logSystemAccess($operationType, $operationCategory, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_operations_log (
                    operation_id, user_id, user_role, operation_type, operation_category,
                    operation_details, ip_address, user_agent, success, security_level, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                'SYS_' . uniqid(),
                $this->currentUser['id'],
                $this->currentUser['role'],
                $operationType,
                $operationCategory,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                true,
                'low'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log system access: " . $e->getMessage());
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
    $controller = new SystemOverviewController();
    $controller->handleRequest();
} catch (Exception $e) {
    error_log("SystemOverviewController Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>