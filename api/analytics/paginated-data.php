<?php
/**
 * Paginated Data API Endpoint
 * Provides paginated data for large datasets
 * Requirements: 11.4
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/AnalyticsService.php';
require_once __DIR__ . '/../../classes/SessionManager.php';

// Initialize session and check authentication
$sessionManager = new SessionManager();
if (!$sessionManager->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if user has analytics access
$userRole = $sessionManager->get('role');
if (!in_array($userRole, ['superadmin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - Insufficient permissions']);
    exit();
}

try {
    $analyticsService = new AnalyticsService();
    
    // Get dataset type and pagination parameters
    $datasetType = $_GET['dataset_type'] ?? 'members';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $pageSize = isset($_GET['page_size']) ? (int)$_GET['page_size'] : 100;
    
    // Validate page size (max 500 records per page)
    if ($pageSize > 500) {
        $pageSize = 500;
    }
    
    // Build filters
    $filters = [
        'paginate' => true,
        'page' => $page,
        'page_size' => $pageSize
    ];
    
    // Add optional filters
    if (isset($_GET['center_id'])) {
        $filters['center_id'] = (int)$_GET['center_id'];
    }
    
    if (isset($_GET['gender'])) {
        $filters['gender'] = $_GET['gender'];
    }
    
    if (isset($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    
    if (isset($_GET['payment_status'])) {
        $filters['payment_status'] = $_GET['payment_status'];
    }
    
    if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
        $filters['date_from'] = $_GET['date_from'];
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Get paginated data based on dataset type
    $result = null;
    switch ($datasetType) {
        case 'members':
            $result = $analyticsService->getMemberAnalytics($filters);
            break;
        case 'contributions':
            $result = $analyticsService->getContributionAnalytics($filters);
            break;
        case 'centers':
            $result = $analyticsService->getCenterAnalytics($filters);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid dataset type'
            ]);
            exit();
    }
    
    // Return paginated response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'dataset_type' => $datasetType,
        'data' => $result['data'] ?? $result,
        'pagination' => $result['pagination'] ?? null,
        'filters_applied' => $filters,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Paginated data API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
