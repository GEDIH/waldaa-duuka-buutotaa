<?php
/**
 * External Analytics API
 * 
 * Provides REST API endpoints for programmatic access to analytics data
 * Supports authentication, rate limiting, and comprehensive data export
 * 
 * Feature: wdb-advanced-analytics
 * Requirements: 8.3
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../api/config/database.php';
require_once __DIR__ . '/../../classes/AnalyticsService.php';
require_once __DIR__ . '/../../classes/KPICalculator.php';

class ExternalAnalyticsAPI {
    private $db;
    private $analyticsService;
    private $kpiCalculator;
    private $apiKey;
    private $userId;
    
    // Rate limiting settings
    private $rateLimitWindow = 3600; // 1 hour
    private $rateLimitMax = 100; // 100 requests per hour
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->analyticsService = new AnalyticsService($this->db);
        $this->kpiCalculator = new KPICalculator($this->db);
    }
    
    /**
     * Handle API request
     */
    public function handleRequest() {
        try {
            // Authenticate request
            if (!$this->authenticate()) {
                $this->sendError('Unauthorized', 401);
                return;
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                $this->sendError('Rate limit exceeded', 429);
                return;
            }
            
            // Get endpoint and method
            $endpoint = $_GET['endpoint'] ?? '';
            $method = $_SERVER['REQUEST_METHOD'];
            
            // Route request
            switch ($endpoint) {
                case 'kpis':
                    $this->handleKPIs();
                    break;
                    
                case 'members':
                    $this->handleMembers();
                    break;
                    
                case 'contributions':
                    $this->handleContributions();
                    break;
                    
                case 'centers':
                    $this->handleCenters();
                    break;
                    
                case 'trends':
                    $this->handleTrends();
                    break;
                    
                case 'export':
                    $this->handleExport();
                    break;
                    
                default:
                    $this->sendError('Invalid endpoint', 404);
                    break;
            }
            
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }
    
    /**
     * Authenticate API request
     */
    private function authenticate(): bool {
        // Check for API key in header
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        
        if (empty($apiKey)) {
            // Check Authorization header
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $apiKey = $matches[1];
            }
        }
        
        if (empty($apiKey)) {
            return false;
        }
        
        // Validate API key
        $stmt = $this->db->prepare("
            SELECT user_id, is_active, expires_at 
            FROM api_keys 
            WHERE api_key = ? AND is_active = 1
        ");
        $stmt->execute([$apiKey]);
        $keyData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$keyData) {
            return false;
        }
        
        // Check expiration
        if ($keyData['expires_at'] && strtotime($keyData['expires_at']) < time()) {
            return false;
        }
        
        $this->apiKey = $apiKey;
        $this->userId = $keyData['user_id'];
        
        // Log API access
        $this->logApiAccess();
        
        return true;
    }
    
    /**
     * Check rate limit
     */
    private function checkRateLimit(): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as request_count
            FROM api_access_log
            WHERE api_key = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$this->apiKey, $this->rateLimitWindow]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['request_count'] < $this->rateLimitMax;
    }
    
    /**
     * Log API access
     */
    private function logApiAccess() {
        $endpoint = $_GET['endpoint'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $this->db->prepare("
            INSERT INTO api_access_log 
            (api_key, user_id, endpoint, method, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$this->apiKey, $this->userId, $endpoint, $method, $ipAddress]);
    }
    
    /**
     * Handle KPIs endpoint
     */
    private function handleKPIs() {
        $filters = $this->getFilters();
        $kpis = $this->analyticsService->calculateKPIs($filters);
        
        $this->sendSuccess([
            'kpis' => $kpis,
            'filters' => $filters,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Handle members endpoint
     */
    private function handleMembers() {
        $filters = $this->getFilters();
        $page = intval($_GET['page'] ?? 1);
        $limit = min(intval($_GET['limit'] ?? 50), 1000); // Max 1000 per request
        $offset = ($page - 1) * $limit;
        
        $members = $this->analyticsService->getMemberAnalytics($filters);
        
        // Apply pagination
        $total = count($members);
        $members = array_slice($members, $offset, $limit);
        
        $this->sendSuccess([
            'members' => $members,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'filters' => $filters,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Handle contributions endpoint
     */
    private function handleContributions() {
        $filters = $this->getFilters();
        $page = intval($_GET['page'] ?? 1);
        $limit = min(intval($_GET['limit'] ?? 50), 1000);
        $offset = ($page - 1) * $limit;
        
        $contributions = $this->analyticsService->getContributionAnalytics($filters);
        
        // Apply pagination
        $total = count($contributions);
        $contributions = array_slice($contributions, $offset, $limit);
        
        $this->sendSuccess([
            'contributions' => $contributions,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'filters' => $filters,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Handle centers endpoint
     */
    private function handleCenters() {
        $filters = $this->getFilters();
        $centers = $this->analyticsService->getCenterAnalytics($filters);
        
        $this->sendSuccess([
            'centers' => $centers,
            'filters' => $filters,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Handle trends endpoint
     */
    private function handleTrends() {
        $metric = $_GET['metric'] ?? 'members';
        $dateRange = [
            'start' => $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
            'end' => $_GET['end_date'] ?? date('Y-m-d')
        ];
        
        $trends = $this->analyticsService->generateTrendAnalysis($metric, $dateRange);
        
        $this->sendSuccess([
            'trends' => $trends,
            'metric' => $metric,
            'date_range' => $dateRange,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Handle export endpoint
     */
    private function handleExport() {
        $format = $_GET['format'] ?? 'json';
        $dataType = $_GET['data_type'] ?? 'kpis';
        $filters = $this->getFilters();
        
        // Get data based on type
        switch ($dataType) {
            case 'kpis':
                $data = $this->analyticsService->calculateKPIs($filters);
                break;
            case 'members':
                $data = $this->analyticsService->getMemberAnalytics($filters);
                break;
            case 'contributions':
                $data = $this->analyticsService->getContributionAnalytics($filters);
                break;
            case 'centers':
                $data = $this->analyticsService->getCenterAnalytics($filters);
                break;
            default:
                $this->sendError('Invalid data type', 400);
                return;
        }
        
        // Format data
        if ($format === 'csv') {
            $this->exportCSV($data, $dataType);
        } else {
            $this->sendSuccess([
                'data' => $data,
                'format' => $format,
                'data_type' => $dataType,
                'filters' => $filters,
                'timestamp' => date('c')
            ]);
        }
    }
    
    /**
     * Export data as CSV
     */
    private function exportCSV($data, $dataType) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $dataType . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if (!empty($data)) {
            // Write headers
            fputcsv($output, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit();
    }
    
    /**
     * Get filters from request
     */
    private function getFilters(): array {
        $filters = [];
        
        if (isset($_GET['center_id'])) {
            $filters['center_id'] = intval($_GET['center_id']);
        }
        
        if (isset($_GET['region'])) {
            $filters['region'] = $_GET['region'];
        }
        
        if (isset($_GET['start_date'])) {
            $filters['start_date'] = $_GET['start_date'];
        }
        
        if (isset($_GET['end_date'])) {
            $filters['end_date'] = $_GET['end_date'];
        }
        
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        
        return $filters;
    }
    
    /**
     * Send success response
     */
    private function sendSuccess($data, $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => date('c'),
                'rate_limit' => [
                    'limit' => $this->rateLimitMax,
                    'window' => $this->rateLimitWindow
                ]
            ]
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Send error response
     */
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code
            ],
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => date('c')
            ]
        ], JSON_PRETTY_PRINT);
        exit();
    }
}

// Handle request
$api = new ExternalAnalyticsAPI();
$api->handleRequest();
