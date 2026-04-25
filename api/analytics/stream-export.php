<?php
/**
 * Stream Export API Endpoint
 * Provides streaming export for large datasets with progress tracking
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
require_once __DIR__ . '/../../classes/DataExporter.php';
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
    $dataExporter = new DataExporter();
    
    // Get export parameters
    $datasetType = $_GET['dataset_type'] ?? 'members';
    $format = $_GET['format'] ?? 'csv';
    
    // Build filters
    $filters = [];
    
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
    
    // Progress tracking callback
    $progressCallback = function($progress, $rowCount, $message) {
        // In a real implementation, this would send progress updates via SSE or WebSocket
        error_log("Export progress: {$progress}% - {$rowCount} rows - {$message}");
    };
    
    // Perform streaming export
    $result = $dataExporter->streamExport($datasetType, $filters, $format, $progressCallback);
    
    // Return export result
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'export_result' => $result,
        'download_url' => '/exports/' . $result['filename'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Stream export API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
