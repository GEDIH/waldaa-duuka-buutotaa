<?php
/**
 * Data Masking and Anonymization API
 * 
 * Provides data masking for sharing analytics with external stakeholders
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

// Check permission for data export
if (!$securityManager->hasPermission($userId, 'export_data')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient permissions'
    ]);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['data']) || !isset($input['mask_level'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Data and mask_level required'
    ]);
    exit;
}

$data = $input['data'];
$maskLevel = $input['mask_level']; // 'none', 'partial', 'full', 'anonymize'

// Apply masking or anonymization
if ($maskLevel === 'anonymize') {
    $processedData = $securityManager->anonymizeData($data);
} else {
    $processedData = $securityManager->maskSensitiveData($data, $maskLevel);
}

// Log the masking operation
$securityManager->logAnalyticsAccess($userId, 'mask_data', [
    'mask_level' => $maskLevel,
    'record_count' => is_array($data) ? count($data) : 1
]);

echo json_encode([
    'success' => true,
    'data' => $processedData,
    'mask_level' => $maskLevel,
    'record_count' => is_array($processedData) ? count($processedData) : 1
]);
