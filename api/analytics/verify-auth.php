<?php
/**
 * Additional Authentication Verification for Sensitive Analytics Data
 * 
 * Requires users to re-enter password before accessing sensitive financial
 * or personal data in analytics dashboard.
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

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['password']) || !isset($input['data_type'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Password and data type required'
    ]);
    exit;
}

$password = $input['password'];
$dataType = $input['data_type'];

// Verify additional authentication
$verified = $securityManager->verifyAdditionalAuth($userId, $password, $dataType);

if ($verified) {
    echo json_encode([
        'success' => true,
        'message' => 'Additional authentication verified',
        'expires_in' => 300 // 5 minutes
    ]);
} else {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid password'
    ]);
}
