<?php
/**
 * KPIs API Endpoint
 * Provides specific KPI calculations
 * Requirements: 1.1, 1.4, 1.6, 1.7
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
require_once __DIR__ . '/../../classes/KPICalculator.php';
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
    $kpiCalculator = new KPICalculator();
    
    // Get KPI type from request
    $kpiType = $_GET['type'] ?? 'all';
    $centerId = isset($_GET['center_id']) ? (int)$_GET['center_id'] : null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    $response = ['success' => true];
    
    switch ($kpiType) {
        case 'membership':
            $response['data'] = $kpiCalculator->calculateMembershipKPIs($centerId, $startDate, $endDate);
            break;
            
        case 'financial':
            $response['data'] = $kpiCalculator->calculateFinancialKPIs($centerId, $startDate, $endDate);
            break;
            
        case 'growth':
            $response['data'] = $kpiCalculator->calculateGrowthKPIs($centerId);
            break;
            
        case 'engagement':
            $response['data'] = $kpiCalculator->calculateEngagementKPIs($centerId);
            break;
            
        case 'center':
            $response['data'] = $kpiCalculator->calculateCenterKPIs($centerId);
            break;
            
        case 'all':
        default:
            $response['data'] = [
                'membership' => $kpiCalculator->calculateMembershipKPIs($centerId, $startDate, $endDate),
                'financial' => $kpiCalculator->calculateFinancialKPIs($centerId, $startDate, $endDate),
                'growth' => $kpiCalculator->calculateGrowthKPIs($centerId),
                'engagement' => $kpiCalculator->calculateEngagementKPIs($centerId),
                'centers' => $kpiCalculator->calculateCenterKPIs($centerId)
            ];
            break;
    }
    
    $response['timestamp'] = date('Y-m-d H:i:s');
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("KPIs API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
