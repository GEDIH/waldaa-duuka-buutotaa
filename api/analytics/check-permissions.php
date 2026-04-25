<?php
/**
 * Check Analytics Permissions
 * 
 * Returns user's analytics permissions and accessible centers
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

// Get accessible centers
$accessibleCenters = $securityManager->getAccessibleCenters($userId);

// Check all analytics permissions
$permissions = [
    'view_basic_analytics' => $securityManager->hasPermission($userId, 'view_basic_analytics'),
    'view_financial_analytics' => $securityManager->hasPermission($userId, 'view_financial_analytics'),
    'view_all_centers' => $securityManager->hasPermission($userId, 'view_all_centers'),
    'export_data' => $securityManager->hasPermission($userId, 'export_data'),
    'export_sensitive_data' => $securityManager->hasPermission($userId, 'export_sensitive_data'),
    'manage_reports' => $securityManager->hasPermission($userId, 'manage_reports'),
    'view_audit_logs' => $securityManager->hasPermission($userId, 'view_audit_logs')
];

// Log permission check
$securityManager->logAnalyticsAccess($userId, 'check_permissions', [
    'permissions' => $permissions,
    'center_count' => count($accessibleCenters)
]);

echo json_encode([
    'success' => true,
    'user_id' => $userId,
    'role' => $_SESSION['role'] ?? 'user',
    'permissions' => $permissions,
    'accessible_centers' => $accessibleCenters,
    'center_count' => count($accessibleCenters)
]);
