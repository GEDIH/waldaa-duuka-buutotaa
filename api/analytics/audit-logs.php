<?php
/**
 * Analytics Audit Logs API
 * 
 * Retrieves audit logs for analytics operations
 */

header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../classes/SecurityManager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$securityManager = new SecurityManager();

// Validate session
if (!$securityManager->validateSession($userId)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Session expired'
    ]);
    exit;
}

// Check permission to view audit logs
if (!$securityManager->hasPermission($userId, 'view_audit_logs')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient permissions to view audit logs'
    ]);
    exit;
}

// Get filter parameters
$filters = [];

if (isset($_GET['user_id'])) {
    $filters['user_id'] = intval($_GET['user_id']);
}

if (isset($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}

if (isset($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}

if (isset($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

// Get audit logs
$logs = $securityManager->getAuditLogs($filters, $limit);

// Log this audit log access
$securityManager->logAnalyticsAccess($userId, 'view_audit_logs', [
    'filters' => $filters,
    'result_count' => count($logs)
]);

echo json_encode([
    'success' => true,
    'logs' => $logs,
    'count' => count($logs),
    'filters' => $filters
]);
