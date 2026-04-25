<?php
/**
 * Track Interaction API
 * 
 * API endpoint for tracking user interactions with the analytics dashboard
 * 
 * Feature: wdb-advanced-analytics
 * Task: 17.1 - Implement user interaction tracking
 * Requirements: 11.7
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../api/config/database.php';
require_once __DIR__ . '/../../classes/InteractionTracker.php';

// Check if user is authenticated
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();
$tracker = new InteractionTracker($db);

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $interactionType = $input['interaction_type'] ?? '';
    $metadata = $input['metadata'] ?? [];
    
    if (empty($interactionType)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Interaction type is required'
        ]);
        exit();
    }
    
    $success = $tracker->trackInteraction($userId, $interactionType, $metadata);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Interaction tracked successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to track interaction'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
