<?php
/**
 * Analytics Export API
 * Handles data export requests
 * Requirements: 8.1, 8.2, 8.3, 8.4
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../services/DataExporter.php';
require_once __DIR__ . '/../services/AnalyticsService.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Database.php';

session_start();

$auth = new Auth();
$exporter = new DataExporter();
$analytics = new AnalyticsService();

// Check authentication
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check permissions
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
    exit();
}

$action = $_GET['action'] ?? 'export';

switch ($action) {
    case 'export':
        handleExport();
        break;
    case 'progress':
        handleProgress();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function handleExport() {
    global $exporter, $analytics;
    
    $type = $_GET['type'] ?? 'kpis';
    $format = $_GET['format'] ?? 'csv';
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // Validate format
    if (!in_array($format, ['csv', 'excel', 'pdf'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid format']);
        exit();
    }
    
    // Get data based on type
    $data = [];
    $filename = '';
    
    switch ($type) {
        case 'kpis':
            $result = $analytics->calculateKPIs();
            $data = $result['data'] ?? [];
            $filename = 'kpis_export_' . date('Y-m-d') . '.' . $format;
            break();
            
        case 'members':
            $result = $analytics->getMemberAnalytics($startDate, $endDate);
            $data = $result['data'] ?? [];
            $filename = 'members_export_' . date('Y-m-d') . '.' . $format;
            break;
            
        case 'contributions':
            $result = $analytics->getContributionAnalytics($startDate, $endDate);
            $data = $result['data'] ?? [];
            $filename = 'contributions_export_' . date('Y-m-d') . '.' . $format;
            break;
            
        case 'centers':
            $result = $analytics->getCenterPerformance();
            $data = $result['data'] ?? [];
            $filename = 'centers_export_' . date('Y-m-d') . '.' . $format;
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid export type']);
            exit();
    }
    
    // Prepare metadata
    $metadata = [
        'Export Type' => ucfirst($type),
        'Export Date' => date('Y-m-d H:i:s'),
        'Date Range' => $startDate . ' to ' . $endDate,
        'Exported By' => $_SESSION['full_name'] ?? 'Unknown',
        'Total Records' => count($data)
    ];
    
    // Log export action
    logExportAction($type, $format, count($data));
    
    // Export data
    switch ($format) {
        case 'csv':
            $exporter->exportToCSV($data, $filename, $metadata);
            break;
        case 'excel':
            $exporter->exportToExcel($data, $filename, $metadata);
            break;
        case 'pdf':
            $exporter->exportToPDF($data, $filename, $metadata, ucfirst($type) . ' Report');
            break;
    }
}

function handleProgress() {
    global $exporter;
    
    $progress = $exporter->getExportProgress();
    echo json_encode([
        'success' => true,
        'progress' => $progress
    ]);
}

function logExportAction($type, $format, $recordCount) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO analytics_audit_log 
            (user_id, action, details, ip_address, created_at)
            VALUES (?, 'export', ?, ?, NOW())
        ");
        
        $userId = $_SESSION['user_id'] ?? 0;
        $details = json_encode([
            'type' => $type,
            'format' => $format,
            'record_count' => $recordCount
        ]);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt->bind_param('iss', $userId, $details, $ipAddress);
        $stmt->execute();
    } catch (Exception $e) {
        // Log error but don't fail export
        error_log('Failed to log export action: ' . $e->getMessage());
    }
}
?>
