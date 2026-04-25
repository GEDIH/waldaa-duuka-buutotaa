<?php
/**
 * System Administrator Authentication API
 * 
 * Handles secure authentication and session management for System Administrators.
 * Implements comprehensive security measures including two-factor authentication,
 * session management, and audit logging.
 * 
 * Endpoints:
 * - POST /login - Authenticate system administrator
 * - POST /logout - Logout system administrator
 * - GET /session - Validate current session
 * - POST /refresh - Refresh session token
 * - GET /permissions - Get user permissions
 * 
 * @author WDB Development Team
 * @version 1.0.0
 * @since 2024-12-19
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../services/SystemAdministratorService.php';

class SystemAdminAuthController {
    private $systemAdminService;
    
    public function __construct() {
        $this->systemAdminService = new SystemAdministratorService();
    }
    
    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $action = $_GET['action'] ?? '';
            
            switch ($method) {
                case 'POST':
                    $this->handlePost($action);
                    break;
                case 'GET':
                    $this->handleGet($action);
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log("SystemAdminAuthController Error: " . $e->getMessage());
            $this->sendError('Internal server error', 500);
        }
    }
    
    private function handlePost($action) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'login':
                $this->login($input);
                break;
            case 'logout':
                $this->logout($input);
                break;
            case 'refresh':
                $this->refreshSession($input);
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    private function handleGet($action) {
        switch ($action) {
            case 'session':
                $this->validateSession();
                break;
            case 'permissions':
                $this->getPermissions();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    /**
     * Authenticate system administrator
     */
    private function login($input) {
        try {
            // Validate input
            if (empty($input['username']) || empty($input['password'])) {
                $this->sendError('Username and password are required', 400);
                return;
            }
            
            $username = trim($input['username']);
            $password = $input['password'];
            $twoFactorCode = $input['two_factor_code'] ?? null;
            $rememberMe = $input['remember_me'] ?? false;
            
            // Rate limiting check
            if ($this->isRateLimited($username)) {
                $this->sendError('Too many login attempts. Please try again later.', 429);
                return;
            }
            
            // Authenticate
            $result = $this->systemAdminService->authenticateSystemAdmin($username, $password, $twoFactorCode);
            
            if (!$result['success']) {
                $this->trackFailedLogin($username);
                $this->sendError($result['error'], 401);
                return;
            }
            
            // Set session cookie if remember me is enabled
            if ($rememberMe) {
                $this->setSessionCookie($result['session']['token'], $result['session']['expires_at']);
            }
            
            // Clear rate limiting on successful login
            $this->clearRateLimit($username);
            
            $this->sendSuccess([
                'message' => 'Authentication successful',
                'user' => $result['user'],
                'session' => $result['session'],
                'permissions' => $result['permissions'],
                'requires_security_review' => $this->checkSecurityReviewStatus($result['user'])
            ]);
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $this->sendError('Authentication failed', 500);
        }
    }
    
    /**
     * Logout system administrator
     */
    private function logout($input) {
        try {
            $sessionToken = $this->getSessionToken($input);
            
            if ($sessionToken) {
                $result = $this->systemAdminService->logout($sessionToken);
                
                // Clear session cookie
                $this->clearSessionCookie();
                
                if ($result['success']) {
                    $this->sendSuccess(['message' => 'Logout successful']);
                } else {
                    $this->sendError('Logout failed', 500);
                }
            } else {
                $this->sendSuccess(['message' => 'No active session']);
            }
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            $this->sendError('Logout failed', 500);
        }
    }
    
    /**
     * Validate current session
     */
    private function validateSession() {
        try {
            $sessionToken = $this->getSessionToken();
            
            if (!$sessionToken) {
                $this->sendError('No session token provided', 401);
                return;
            }
            
            $result = $this->systemAdminService->validateSession($sessionToken);
            
            if ($result['success']) {
                $this->sendSuccess([
                    'valid' => true,
                    'user' => $result['user'],
                    'permissions' => $result['permissions']
                ]);
            } else {
                $this->sendError($result['error'], 401);
            }
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            $this->sendError('Session validation failed', 500);
        }
    }
    
    /**
     * Refresh session token
     */
    private function refreshSession($input) {
        try {
            $sessionToken = $this->getSessionToken($input);
            
            if (!$sessionToken) {
                $this->sendError('No session token provided', 401);
                return;
            }
            
            // Validate current session
            $result = $this->systemAdminService->validateSession($sessionToken);
            
            if (!$result['success']) {
                $this->sendError($result['error'], 401);
                return;
            }
            
            // Create new session
            $user = $result['user'];
            $newSession = $this->systemAdminService->createSecureSession($user);
            
            $this->sendSuccess([
                'message' => 'Session refreshed successfully',
                'session' => $newSession
            ]);
            
        } catch (Exception $e) {
            error_log("Session refresh error: " . $e->getMessage());
            $this->sendError('Session refresh failed', 500);
        }
    }
    
    /**
     * Get user permissions
     */
    private function getPermissions() {
        try {
            $sessionToken = $this->getSessionToken();
            
            if (!$sessionToken) {
                $this->sendError('Authentication required', 401);
                return;
            }
            
            $result = $this->systemAdminService->validateSession($sessionToken);
            
            if (!$result['success']) {
                $this->sendError($result['error'], 401);
                return;
            }
            
            $this->sendSuccess([
                'permissions' => $result['permissions'],
                'user_role' => $result['user']['role'],
                'security_clearance' => $result['user']['security_clearance'] ?? 'standard'
            ]);
            
        } catch (Exception $e) {
            error_log("Get permissions error: " . $e->getMessage());
            $this->sendError('Failed to get permissions', 500);
        }
    }
    
    /**
     * Get session token from various sources
     */
    private function getSessionToken($input = null) {
        // Try to get from input first
        if ($input && isset($input['session_token'])) {
            return $input['session_token'];
        }
        
        // Try Authorization header
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (strpos($auth, 'Bearer ') === 0) {
                return substr($auth, 7);
            }
        }
        
        // Try cookie
        if (isset($_COOKIE['system_admin_session'])) {
            return $_COOKIE['system_admin_session'];
        }
        
        // Try GET parameter
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }
        
        return null;
    }
    
    /**
     * Set session cookie
     */
    private function setSessionCookie($token, $expiresAt) {
        $cookieOptions = [
            'expires' => $expiresAt,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        setcookie('system_admin_session', $token, $cookieOptions);
    }
    
    /**
     * Clear session cookie
     */
    private function clearSessionCookie() {
        $cookieOptions = [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        setcookie('system_admin_session', '', $cookieOptions);
    }
    
    /**
     * Check if IP/username is rate limited
     */
    private function isRateLimited($username) {
        $cacheKey = 'login_attempts_' . md5($username . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Simple file-based rate limiting (in production, use Redis or similar)
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey;
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            
            if ($data && $data['count'] >= 5 && (time() - $data['first_attempt']) < 900) { // 15 minutes
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Track failed login attempt
     */
    private function trackFailedLogin($username) {
        $cacheKey = 'login_attempts_' . md5($username . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey;
        
        $data = ['count' => 1, 'first_attempt' => time()];
        
        if (file_exists($cacheFile)) {
            $existing = json_decode(file_get_contents($cacheFile), true);
            if ($existing && (time() - $existing['first_attempt']) < 900) {
                $data['count'] = $existing['count'] + 1;
                $data['first_attempt'] = $existing['first_attempt'];
            }
        }
        
        file_put_contents($cacheFile, json_encode($data));
    }
    
    /**
     * Clear rate limiting
     */
    private function clearRateLimit($username) {
        $cacheKey = 'login_attempts_' . md5($username . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey;
        
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
    
    /**
     * Check security review status
     */
    private function checkSecurityReviewStatus($user) {
        if (isset($user['security_review_due'])) {
            return strtotime($user['security_review_due']) < time();
        }
        return false;
    }
    
    private function sendSuccess($data) {
        echo json_encode(['success' => true] + $data);
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Initialize and handle request
try {
    $controller = new SystemAdminAuthController();
    $controller->handleRequest();
} catch (Exception $e) {
    error_log("SystemAdminAuthController Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?>