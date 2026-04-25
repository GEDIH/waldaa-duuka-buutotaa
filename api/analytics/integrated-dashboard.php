<?php
/**
 * Integrated Analytics Dashboard API
 * 
 * Unified endpoint for all analytics operations
 * 
 * Feature: wdb-advanced-analytics
 * Task: 18.1 - Complete system integration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../classes/AnalyticsIntegration.php';

try {
    // Initialize integration manager
    $integration = new AnalyticsIntegration();
    
    // Get request method and action
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'dashboard';
    
    // Get user ID from session
    session_start();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User not authenticated');
    }
    
    // Route to appropriate handler
    switch ($action) {
        case 'dashboard':
            $filters = $_GET['filters'] ?? [];
            $response = $integration->getDashboardData($userId, $filters);
            break;
            
        case 'report':
            if ($method !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $response = $integration->generateReport(
                $userId,
                $data['template'] ?? 'default',
                $data['parameters'] ?? [],
                $data['format'] ?? 'pdf'
            );
            break;
            
        case 'search':
            $query = $_GET['q'] ?? '';
            $options = $_GET['options'] ?? [];
            $response = $integration->search($userId, $query, $options);
            break;
            
        case 'health':
            $response = $integration->getSystemHealth();
            break;
            
        case 'components':
            $response = [
                'success' => true,
                'components' => $integration->getComponentsStatus()
            ];
            break;
            
        case 'invalidate_cache':
            if ($method !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $success = $integration->invalidateAllCaches();
            $response = [
                'success' => $success,
                'message' => $success ? 'Caches invalidated' : 'Cache invalidation failed'
            ];
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
