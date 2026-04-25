<?php
/**
 * Layout Recommendations API
 * 
 * API endpoint for getting personalized dashboard layout recommendations
 * 
 * Feature: wdb-advanced-analytics
 * Task: 17.1 - Implement user interaction tracking
 * Requirements: 11.7
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $recommendations = $tracker->getDashboardLayoutRecommendations($userId);
        $patterns = $tracker->getUserInteractionPatterns($userId, 30);
        $heatmap = $tracker->getInteractionHeatmap($userId, 7);
        $avgSessionDuration = $tracker->getAverageSessionDuration($userId, 30);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'recommendations' => $recommendations,
                'interaction_patterns' => $patterns,
                'heatmap' => $heatmap,
                'avg_session_duration' => $avgSessionDuration,
                'timestamp' => date('c')
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to get recommendations: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}
