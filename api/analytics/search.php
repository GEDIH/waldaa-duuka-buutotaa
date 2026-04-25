<?php
/**
 * Analytics Search API Endpoint
 * 
 * Provides global search functionality across analytics data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../classes/SearchService.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $searchService = new SearchService();
    $action = $_GET['action'] ?? 'search';
    
    switch ($action) {
        case 'search':
            $query = $_GET['q'] ?? '';
            $options = [
                'categories' => isset($_GET['categories']) ? explode(',', $_GET['categories']) : null,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 50,
                'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0
            ];
            
            $results = $searchService->globalSearch($query, $options);
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;
            
        case 'suggestions':
            $query = $_GET['q'] ?? '';
            $suggestions = $searchService->generateSuggestions($query);
            echo json_encode([
                'success' => true,
                'data' => $suggestions
            ]);
            break;
            
        case 'history':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $history = $searchService->getSearchHistory($limit);
            echo json_encode([
                'success' => true,
                'data' => $history
            ]);
            break;
            
        case 'recommendations':
            $recommendations = $searchService->getRecommendations();
            echo json_encode([
                'success' => true,
                'data' => $recommendations
            ]);
            break;
            
        case 'natural':
            $query = $_GET['q'] ?? '';
            $results = $searchService->executeNaturalLanguageQuery($query);
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;
            
        case 'advanced':
            $criteria = json_decode(file_get_contents('php://input'), true);
            $results = $searchService->advancedSearch($criteria);
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
