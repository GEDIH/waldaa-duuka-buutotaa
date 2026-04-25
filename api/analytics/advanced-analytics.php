<?php
/**
 * Advanced Analytics API Endpoint
 * 
 * Provides access to advanced analytics data including:
 * - Executive dashboard data
 * - Member analytics
 * - Financial analytics
 * - Predictive analytics
 * - Custom reports
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/AdvancedAnalyticsEngine.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

try {
    // Initialize analytics engine
    $analytics = new AdvancedAnalyticsEngine();
    
    // Get request parameters
    $action = $_GET['action'] ?? 'dashboard';
    $dateRange = $_GET['date_range'] ?? '30_days';
    $format = $_GET['format'] ?? 'json';
    
    // Authentication check for sensitive analytics
    $auth = new AuthMiddleware(Database::getInstance()->getConnection());
    $authResult = $auth->authenticate();
    
    if (!$authResult['success']) {
        throw new Exception('Authentication required for analytics access');
    }
    
    // Check if user has analytics permissions
    if (!in_array($authResult['user']['role'], ['admin', 'superadmin'])) {
        throw new Exception('Admin privileges required for analytics access');
    }
    
    $response = ['success' => true];
    
    switch ($action) {
        case 'dashboard':
        case 'executive':
            $response['data'] = $analytics->getExecutiveDashboard($dateRange);
            break;
            
        case 'members':
            $response['data'] = $analytics->getMemberAnalytics($dateRange);
            break;
            
        case 'financial':
            $response['data'] = $analytics->getFinancialAnalytics($dateRange);
            break;
            
        case 'predictive':
            $response['data'] = $analytics->getPredictiveAnalytics();
            break;
            
        case 'kpis':
            $response['data'] = $analytics->getExecutiveDashboard($dateRange)['kpis'];
            break;
            
        case 'trends':
            $response['data'] = $analytics->getExecutiveDashboard($dateRange)['trends'];
            break;
            
        case 'insights':
            $response['data'] = $analytics->getExecutiveDashboard($dateRange)['insights'];
            break;
            
        case 'alerts':
            $response['data'] = $analytics->getExecutiveDashboard($dateRange)['alerts'];
            break;
            
        default:
            throw new Exception('Invalid analytics action specified');
    }
    
    // Add metadata
    $response['metadata'] = [
        'generated_at' => date('Y-m-d H:i:s'),
        'date_range' => $dateRange,
        'user_id' => $authResult['user']['id'],
        'cache_expires' => date('Y-m-d H:i:s', strtotime('+1 hour'))
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>