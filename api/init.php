<?php
/**
 * API Initialization File
 * 
 * This file should be included at the top of all API entry points to ensure
 * consistent security hardening, session management, and configuration loading.
 * 
 * Usage:
 *   require_once __DIR__ . '/init.php';
 * 
 * Features:
 * - Applies security headers (CSP, HSTS, X-Frame-Options, etc.)
 * - Enforces HTTPS in production
 * - Loads environment configuration
 * - Initializes error handling
 * - Sets up CORS headers (configurable)
 * 
 * Requirements: 1.2.1, 1.2.2, 1.2.3, 1.2.4, 1.2.5, 1.2.6
 */

// Prevent direct access
if (!defined('API_INIT_LOADED')) {
    define('API_INIT_LOADED', true);
} else {
    // Already loaded, skip
    return;
}

// Load environment configuration
$envLoaderPath = __DIR__ . '/config/env-loader.php';
if (file_exists($envLoaderPath)) {
    require_once $envLoaderPath;
}

// Load security hardening class
$securityHardeningPath = __DIR__ . '/../deployment/security-hardening.php';
if (file_exists($securityHardeningPath)) {
    require_once $securityHardeningPath;
    
    // Apply security headers
    SecurityHardening::applySecurityHeaders();
    
    // Enforce HTTPS in production
    SecurityHardening::enforceHTTPS();
} else {
    // Fallback: Apply basic security headers if class not found
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
}

// Configure error handling based on environment
$appEnv = getenv('APP_ENV') ?: 'development';
$appDebug = getenv('APP_DEBUG') === 'true';

if ($appEnv === 'production') {
    // Production: Hide errors from output, log to file
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    
    // Set error log location
    $errorLogPath = __DIR__ . '/../logs/error.log';
    $logDir = dirname($errorLogPath);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('log_errors', '1');
    ini_set('error_log', $errorLogPath);
} else {
    // Development: Show errors for debugging
    if ($appDebug) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }
}

// Set default timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

// Set default character encoding
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Configure session security (if session not already started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $appEnv === 'production' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
}

// Set up custom error handler for production
if ($appEnv === 'production') {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Log the error
        error_log("Error [$errno]: $errstr in $errfile on line $errline");
        
        // Don't expose internal errors to users
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        return true;
    });
    
    set_exception_handler(function($exception) {
        // Log the exception
        error_log("Uncaught Exception: " . $exception->getMessage() . " in " . 
                  $exception->getFile() . " on line " . $exception->getLine());
        
        // Return generic error to user
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'An internal error occurred. Please try again later.'
        ]);
        
        exit;
    });
}

// Helper function to check if request is from allowed origin
function isAllowedOrigin($origin) {
    $allowedOrigins = explode(',', getenv('ALLOWED_ORIGINS') ?: '*');
    
    if (in_array('*', $allowedOrigins)) {
        return true;
    }
    
    return in_array($origin, $allowedOrigins);
}

// Apply CORS headers if configured
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && isAllowedOrigin($origin)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Default CORS for development
    if ($appEnv !== 'production') {
        header('Access-Control-Allow-Origin: *');
    }
}

// Common CORS headers
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set JSON as default content type for API responses
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}
