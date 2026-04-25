<?php
/**
 * Enhanced KPI Drilldown API Endpoint
 * 
 * Provides advanced filtering, sorting, and pagination for KPI drilldown modals.
 * 
 * Requirements: 3.1, 3.2, 6.1, 7.4, 7.5
 * 
 * Request Parameters:
 * - type (required): KPI type (total_members, active_members, etc.)
 * - page (optional): Page number (default: 1)
 * - limit (optional): Records per page (default: 50, max: 100)
 * - center_id (optional): Filter by center ID
 * - region (optional): Filter by region
 * - payment_status (optional): Filter by payment status (paid/unpaid)
 * - status (optional): Filter by member/admin status
 * - date_from (optional): Start date filter (YYYY-MM-DD)
 * - date_to (optional): End date filter (YYYY-MM-DD)
 * - sort_by (optional): Column to sort by
 * - sort_order (optional): Sort direction (ASC/DESC)
 * 
 * Response Format:
 * {
 *   "success": true,
 *   "kpi_type": "total_members",
 *   "data": [...],
 *   "total": 250,
 *   "page": 1,
 *   "limit": 50,
 *   "total_pages": 5,
 *   "filters_applied": {...},
 *   "sort": {"column": "full_name", "order": "ASC"},
 *   "timestamp": "2024-01-15 10:30:00"
 * }
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session
session_start();

// Include required files
require_once __DIR__ . '/../../classes/DrilldownService.php';

/**
 * Send JSON response and exit
 */
function sendResponse(array $response, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error response
 */
function sendError(string $message, int $statusCode = 400, string $errorType = 'error'): void {
    sendResponse([
        'success' => false,
        'error' => [
            'type' => $errorType,
            'message' => $message,
            'code' => $statusCode
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

/**
 * Validate required parameters
 */
function validateRequiredParams(): string {
    if (!isset($_GET['type']) || empty($_GET['type'])) {
        sendError('Missing required parameter: type', 400, 'validation_error');
    }
    
    return $_GET['type'];
}

/**
 * Parse and validate pagination parameters
 */
function parsePaginationParams(): array {
    $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
    $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT) : 50;
    
    // Validate page
    if ($page === false || $page < 1) {
        $page = 1;
    }
    
    // Validate limit (min 1, max 100)
    if ($limit === false || $limit < 1) {
        $limit = 50;
    } elseif ($limit > 100) {
        $limit = 100;
    }
    
    return ['page' => $page, 'limit' => $limit];
}

/**
 * Parse filter parameters from request
 */
function parseFilterParams(): array {
    $filters = [];
    
    // Center ID filter
    if (isset($_GET['center_id']) && $_GET['center_id'] !== '') {
        $centerId = filter_var($_GET['center_id'], FILTER_VALIDATE_INT);
        if ($centerId !== false) {
            $filters['center_id'] = $centerId;
        }
    }
    
    // Region filter
    if (isset($_GET['region']) && $_GET['region'] !== '') {
        $filters['region'] = trim($_GET['region']);
    }
    
    // Payment status filter
    if (isset($_GET['payment_status']) && $_GET['payment_status'] !== '') {
        $paymentStatus = trim($_GET['payment_status']);
        if (in_array($paymentStatus, ['paid', 'unpaid'])) {
            $filters['payment_status'] = $paymentStatus;
        }
    }
    
    // Status filter
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $filters['status'] = trim($_GET['status']);
    }
    
    // Gender filter
    if (isset($_GET['gender']) && $_GET['gender'] !== '') {
        $filters['gender'] = trim($_GET['gender']);
    }
    
    // Role filter (for admin KPIs)
    if (isset($_GET['role']) && $_GET['role'] !== '') {
        $filters['role'] = trim($_GET['role']);
    }
    
    // Date from filter
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $dateFrom = trim($_GET['date_from']);
        // Validate date format (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $filters['date_from'] = $dateFrom;
        }
    }
    
    // Date to filter
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
        $dateTo = trim($_GET['date_to']);
        // Validate date format (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $filters['date_to'] = $dateTo;
        }
    }
    
    return $filters;
}

/**
 * Parse sort parameters from request
 */
function parseSortParams(): array {
    $sortBy = isset($_GET['sort_by']) && $_GET['sort_by'] !== '' ? trim($_GET['sort_by']) : null;
    $sortOrder = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
    
    return ['sort_by' => $sortBy, 'sort_order' => $sortOrder];
}

// Main execution
try {
    // Validate required parameters
    $kpiType = validateRequiredParams();
    
    // Parse pagination parameters
    $pagination = parsePaginationParams();
    
    // Parse filter parameters
    $filters = parseFilterParams();
    
    // Parse sort parameters
    $sort = parseSortParams();
    
    // Create DrilldownService instance
    $service = new DrilldownService();
    
    // Get drilldown data
    $result = $service->getDrilldownData(
        $kpiType,
        $filters,
        $pagination['page'],
        $pagination['limit'],
        $sort['sort_by'],
        $sort['sort_order']
    );
    
    // Add KPI type and filters to response
    $result['kpi_type'] = $kpiType;
    $result['filters_applied'] = $filters;
    
    // Send successful response
    sendResponse($result, 200);
    
} catch (Exception $e) {
    // Determine error type and status code
    $statusCode = 500;
    $errorType = 'server_error';
    $message = 'An internal error occurred';
    
    // Check for specific error types
    if (strpos($e->getMessage(), 'Unauthorized') !== false) {
        $statusCode = 403;
        $errorType = 'unauthorized';
        $message = $e->getMessage();
    } elseif (strpos($e->getMessage(), 'Invalid') !== false) {
        $statusCode = 400;
        $errorType = 'validation_error';
        $message = $e->getMessage();
    } else {
        // Log unexpected errors
        error_log(sprintf(
            "[KPI Drilldown API Error] %s in %s:%d",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }
    
    // Send error response
    sendError($message, $statusCode, $errorType);
}
