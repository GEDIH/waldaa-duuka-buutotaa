<?php
/**
 * Export Download API Endpoint
 * Handles download requests for exported files
 * Requirements: 8.1, 8.2
 */

require_once __DIR__ . '/../middleware/auth.php';

// Require authentication
requireAuth();

try {
    // Get filename from query parameter
    $filename = $_GET['filename'] ?? null;
    
    if (!$filename) {
        throw new Exception('Filename is required');
    }
    
    // Validate filename (security check)
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
        throw new Exception('Invalid filename');
    }
    
    // Build file path
    $exportDir = __DIR__ . '/../../exports';
    $filepath = $exportDir . '/' . $filename;
    
    // Check if file exists
    if (!file_exists($filepath)) {
        throw new Exception('File not found');
    }
    
    // Get file extension
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    
    // Set content type based on extension
    $contentTypes = [
        'csv' => 'text/csv',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf'
    ];
    
    $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
    
    // Set headers for download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Output file content
    readfile($filepath);
    
    // Optionally delete file after download
    if (isset($_GET['delete_after_download']) && $_GET['delete_after_download'] === 'true') {
        unlink($filepath);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
