<?php
/**
 * Secure Members API Router
 * Implements comprehensive security for all member-related endpoints
 * Requirements: 7.1, 7.2, 7.3, 7.4, 9.1, 9.2, 9.4
 */

// Security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/RateLimitingService.php';
require_once __DIR__ . '/../controllers/MemberController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../handlers/APISecurityErrorHandler.php';
require_once __DIR__ . '/../services/CenterAuditLogger.php';
require_once __DIR__ . '/../config/api-security-config.php';

class SecureMembersAPI {
    private $db;
    private $rateLimiter;
    private $controller;
    private $auth;
    private $errorHandler;
    private $auditLogger;
    private $clientIP;
    
    public function __construct() {
        try {
            // Initialize database connection
            $this->db = Database::getInstance()->getConnection();
            
            // Initialize services
            $this->rateLimiter = new RateLimitingService();
            $this->controller = new MemberController($this->db);
            $this->auth = new AuthMiddleware($this->db);
            $this->errorHandler = new APISecurityErrorHandler($this->db);
            $this->auditLogger = new CenterAuditLogger($this->db);
            
            // Get client IP
            $this->clientIP = $this->getClientIP();
            
        } catch (Exception $e) {
            $errorResponse = $this->errorHandler->handleInternalError(
                'Service initialization failed', 
                $e
            );
            $this->errorHandler->sendErrorResponse($errorResponse, 500);
        }
    }
    
    /**
     * Main request handler
     */
    public function handleRequest() {
        try {
            // Step 1: Rate limiting check
            if (!$this->checkRateLimit()) {
                return;
            }
            
            // Step 2: Authentication check
            if (!$this->authenticateRequest()) {
                return;
            }
            
            // Step 3: Route to appropriate handler
            $this->routeRequest();
            
        } catch (Exception $e) {
            error_log("SecureMembersAPI Error: " . $e->getMessage());
            $errorResponse = $this->errorHandler->handleInternalError(
                'Request processing failed', 
                $e
            );
            $this->errorHandler->sendErrorResponse($errorResponse, 500);
        }
    }
    
    /**
     * Check rate limits for API requests
     * Requirements: 7.4
     */
    private function checkRateLimit(): bool {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $this->getActionFromMethod($method);
            
            // Different limits for different operations
            $limits = [
                'read' => ['requests' => 300, 'window' => 3600],    // 300 reads per hour
                'create' => ['requests' => 50, 'window' => 3600],   // 50 creates per hour
                'update' => ['requests' => 100, 'window' => 3600],  // 100 updates per hour
                'delete' => ['requests' => 20, 'window' => 3600],   // 20 deletes per hour
            ];
            
            $limit = $limits[$action] ?? $limits['read'];
            $rateLimitResult = $this->rateLimiter->checkLimit($this->clientIP, "members_$action", $limit);
            
            if (!$rateLimitResult['allowed']) {
                $errorResponse = $this->errorHandler->handleRateLimitError($rateLimitResult);
                $this->errorHandler->sendErrorResponse($errorResponse, 429);
                return false;
            }
            
            // Add rate limit headers
            header("X-RateLimit-Limit: " . $rateLimitResult['limit']);
            header("X-RateLimit-Remaining: " . $rateLimitResult['remaining']);
            header("X-RateLimit-Reset: " . $rateLimitResult['reset']);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            // Allow request on rate limit service failure
            return true;
        }
    }
    
    /**
     * Authenticate the request
     * Requirements: 7.1, 7.2
     */
    private function authenticateRequest(): bool {
        try {
            $authResult = $this->auth->authenticate();
            
            if (!$authResult['success']) {
                // Log failed authentication attempt
                $this->auditLogger->logAPIAccess(
                    null,
                    null,
                    $_SERVER['REQUEST_URI'] ?? '/api/members/secure-api.php',
                    $_SERVER['REQUEST_METHOD'],
                    false,
                    401,
                    ['error' => $authResult['error']]
                );
                
                $errorResult = $this->errorHandler->handleError(
                    $authResult['error'], 
                    CenterAccessErrorHandler::ERROR_AUTHENTICATION
                );
                $this->errorHandler->sendErrorResponse($errorResult);
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            $this->sendErrorResponse([
                'success' => false,
                'error' => 'Authentication failed',
                'code' => 'AUTH_ERROR'
            ], 401);
            return false;
        }
    }
    
    /**
     * Route request to appropriate handler
     * Requirements: 7.1, 7.2, 7.3
     */
    private function routeRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $query = $_GET;
        
        // Log API access attempt
        $currentUser = $this->auth->getCurrentUser();
        $this->auditLogger->logAPIAccess(
            $currentUser['id'] ?? null,
            $query['center_id'] ?? null,
            $path,
            $method,
            true,
            200,
            ['query_params' => $query]
        );
        
        switch ($method) {
            case 'GET':
                $this->handleGetRequest($query);
                break;
                
            case 'POST':
                $this->handlePostRequest();
                break;
                
            case 'PUT':
                $this->handlePutRequest($query);
                break;
                
            case 'DELETE':
                $this->handleDeleteRequest($query);
                break;
                
            default:
                $this->sendErrorResponse([
                    'success' => false,
                    'error' => 'Method not allowed',
                    'code' => 'METHOD_NOT_ALLOWED'
                ], 405);
        }
    }
    
    /**
     * Handle GET requests (Read operations)
     * Requirements: 7.1, 7.2
     */
    private function handleGetRequest($query) {
        try {
            // Validate center access for specific center requests
            if (isset($query['center_id'])) {
                $currentUser = $this->auth->getCurrentUser();
                if (!$this->validateCenterAccess($currentUser['id'], $query['center_id'])) {
                    $this->sendErrorResponse([
                        'success' => false,
                        'error' => 'Access denied to requested center',
                        'code' => 'CENTER_ACCESS_DENIED'
                    ], 403);
                    return;
                }
            }
            
            // Delegate to MemberController
            $this->controller->handleRequest();
            
        } catch (Exception $e) {
            error_log("GET request error: " . $e->getMessage());
            $this->sendErrorResponse([
                'success' => false,
                'error' => 'Failed to retrieve members',
                'code' => 'READ_ERROR'
            ], 500);
        }
    }
    
    /**
     * Handle POST requests (Create operations)
     * Requirements: 7.1, 7.2, 7.3
     */
    private function handlePostRequest() {
        try {
            // Validate input
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->sendErrorResponse([
                    'success' => false,
                    'error' => 'Invalid JSON input',
                    'code' => 'INVALID_INPUT'
                ], 400);
                return;
            }
            
            // Validate center assignment if provided
            if (isset($input['center_id'])) {
                $currentUser = $this->auth->getCurrentUser();
                if (!$this->validateCenterAccess($currentUser['id'], $input['center_id'])) {
                    $this->sendErrorResponse([
                        'success' => false,
                        'error' => 'Cannot create member for unauthorized center',
                        'code' => 'CENTER_ACCESS_DENIED'
                    ], 403);
                    return;
                }
            }
            
            // Sanitize input
            $input = $this->sanitizeInput($input);
            
            // Delegate to MemberController
            $this->controller->handleRequest();
            
        } catch (Exception $e) {
            error_log("POST request error: " . $e->getMessage());
            $this->sendErrorResponse([
                'success' => false,
                'error' => 'Failed to create member',
                'code' => 'CREATE_ERROR'
            ], 500);
        }
    }
    
    /**
     * Handle PUT requests (Update operations)
     * Requirements: 7.1, 7.2, 7.3
     */
    private function handlePutRequest($query) {
        try {
            $memberId = $query['id'] ?? null;
            if (!$memberId) {
                $this->sendErrorResponse([
                    'success' => false,
                    'error' => 'Member ID is required for updates',
                    'code' => 'MISSING_MEMBER_ID'
                ], 400);
                return;
            }
            
            // Validate member access
            $currentUser = $this->auth->getCurrentUser();
            if (!$this->controller->validateMemberCenterAccess($memberId, $currentUser['id'])) {
                $this->sendErrorResponse([
                    'success' => false,
                    'error' => 'Access denied. Member belongs to unauthorized center.',
                    'code' => 'MEMBER_ACCESS_DENIED'
                ], 403);
                return;
            }
            
            // Validate and sanitize input
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->sendErrorResponse([
                    'success' => false,
                    'error' => 'Invalid JSON input',
                    'code' => 'INVALID_INPUT'
                ], 400);
                return;
            }
            
            // Prevent center_id modification
            if (isset($input['center_id'])) {
                unset($input['center_id']);
            }
            
            $input = $this->sanitizeInput($input);
            
            // Delegate to MemberController
            $this->controller->handleRequest();
            
        } catch (Exception $e) {
            error_log("PUT request error: " . $e->getMessage());
            $this->sendErrorResponse([
                'success' => false,
                'error' => 'Failed to update member',
                'code' => 'UPDATE_ERROR'
            ], 500);
        }
    }
    
    /**
     * Handle DELETE requests (Delete operations)
     * Requirements: 7.1, 7.2, 7.3
     */
    private function handleDeleteRequest($query) {
        try {
            $memberId = $query['id'] ?? null;
            if (!$memberId) {
                $this->sendErrorResponse([
                    'success' => false,
                    'error' => 'Member ID is required for deletion',
                    'code' => 'MISSING_MEMBER_ID'
                ], 400);
                return;
            }
            
            // Validate member access
            $currentUser = $this->auth->getCurrentUser();
            if (!$this->controller->validateMemberCenterAccess($memberId, $currentUser['id'])) {
                $this->sendErrorResponse([
                    'success' => false,
                    'error' => 'Access denied. Member belongs to unauthorized center.',
                    'code' => 'MEMBER_ACCESS_DENIED'
                ], 403);
                return;
            }
            
            // Delegate to MemberController
            $this->controller->handleRequest();
            
        } catch (Exception $e) {
            error_log("DELETE request error: " . $e->getMessage());
            $this->sendErrorResponse([
                'success' => false,
                'error' => 'Failed to delete member',
                'code' => 'DELETE_ERROR'
            ], 500);
        }
    }
    
    /**
     * Validate center access for user
     */
    private function validateCenterAccess($userId, $centerId): bool {
        try {
            $stmt = $this->db->prepare("
                SELECT 1 FROM user_center_assignments 
                WHERE user_id = ? AND center_id = ? AND is_active = 1
                UNION
                SELECT 1 FROM users 
                WHERE id = ? AND (center_id = ? OR role = 'superadmin')
            ");
            $stmt->execute([$userId, $centerId, $userId, $centerId]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Center access validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitize input data
     * Requirements: 9.1, 9.2
     */
    private function sanitizeInput($input): array {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                // Remove HTML tags and encode special characters
                $value = htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $value = $this->sanitizeInput($value);
            }
            
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }
    
    /**
     * Get action from HTTP method
     */
    private function getActionFromMethod($method): string {
        switch (strtoupper($method)) {
            case 'GET':
                return 'read';
            case 'POST':
                return 'create';
            case 'PUT':
            case 'PATCH':
                return 'update';
            case 'DELETE':
                return 'delete';
            default:
                return 'read';
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP(): string {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Send error response
     * Requirements: 9.1, 9.2, 9.4
     */
    private function sendErrorResponse($response, $statusCode = 400) {
        http_response_code($statusCode);
        
        // Add security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        
        // Add timestamp and request ID for tracking
        $response['timestamp'] = date('Y-m-d H:i:s');
        $response['request_id'] = uniqid('req_', true);
        
        echo json_encode($response);
        exit();
    }
}

// Initialize and handle request
try {
    $api = new SecureMembersAPI();
    $api->handleRequest();
} catch (Exception $e) {
    error_log("SecureMembersAPI Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Service unavailable',
        'code' => 'SERVICE_UNAVAILABLE',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>