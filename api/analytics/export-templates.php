<?php
/**
 * Export Templates API
 * Manages custom export templates for recurring reports
 * Requirements: 8.7
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../classes/ExportTemplateManager.php';

try {
    $templateManager = new ExportTemplateManager();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($templateManager);
            break;
        
        case 'POST':
            handlePost($templateManager);
            break;
        
        case 'PUT':
            handlePut($templateManager);
            break;
        
        case 'DELETE':
            handleDelete($templateManager);
            break;
        
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle GET requests - Retrieve templates
 */
function handleGet($templateManager)
{
    // Check if specific template ID is requested
    if (isset($_GET['id'])) {
        $template = $templateManager->getTemplate($_GET['id']);
        
        if ($template) {
            echo json_encode([
                'success' => true,
                'template' => $template
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Template not found'
            ]);
        }
    } else {
        // Get all templates with optional filters
        $filters = [];
        if (isset($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }
        if (isset($_GET['format'])) {
            $filters['format'] = $_GET['format'];
        }
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = $_GET['is_active'];
        }
        
        $templates = $templateManager->getTemplates($filters);
        
        echo json_encode([
            'success' => true,
            'templates' => $templates,
            'count' => count($templates)
        ]);
    }
}

/**
 * Handle POST requests - Create new template
 */
function handlePost($templateManager)
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON input'
        ]);
        return;
    }
    
    $result = $templateManager->createTemplate($input);
    
    http_response_code(201);
    echo json_encode($result);
}

/**
 * Handle PUT requests - Update existing template
 */
function handlePut($templateManager)
{
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Template ID is required'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON input'
        ]);
        return;
    }
    
    $result = $templateManager->updateTemplate($_GET['id'], $input);
    
    echo json_encode($result);
}

/**
 * Handle DELETE requests - Delete template
 */
function handleDelete($templateManager)
{
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Template ID is required'
        ]);
        return;
    }
    
    $result = $templateManager->deleteTemplate($_GET['id']);
    
    echo json_encode($result);
}
