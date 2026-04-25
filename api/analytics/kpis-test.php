<?php
/**
 * KPIs API Test Endpoint (No Authentication)
 * For diagnostic purposes only - bypasses authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Test 1: Check if Database class can be loaded
    if (!file_exists(__DIR__ . '/../../classes/Database.php')) {
        throw new Exception('Database.php not found at classes/Database.php');
    }
    require_once __DIR__ . '/../../classes/Database.php';
    
    // Test 2: Check if KPICalculator class can be loaded
    if (!file_exists(__DIR__ . '/../../classes/KPICalculator.php')) {
        throw new Exception('KPICalculator.php not found at classes/KPICalculator.php');
    }
    require_once __DIR__ . '/../../classes/KPICalculator.php';
    
    // Test 3: Try to instantiate KPICalculator
    $kpiCalculator = new KPICalculator();
    
    // Test 4: Get KPI type from request
    $kpiType = $_GET['type'] ?? 'membership';
    $centerId = isset($_GET['center_id']) ? (int)$_GET['center_id'] : null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    $response = ['success' => true, 'test_mode' => true];
    
    // Test 5: Try to calculate KPIs
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
    $response['message'] = 'Test endpoint - authentication bypassed';
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("KPIs Test API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

?>
