<?php
/**
 * Member Registration API Endpoint
 * Handles direct member registration with immediate activation
 * 
 * CRITICAL: This file must output ONLY valid JSON
 * Any PHP warnings, notices, or echo statements will break the frontend
 */

// CRITICAL: Disable ALL error display to prevent breaking JSON
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/registration_errors.log');

// Start output buffering BEFORE any other code
ob_start();

// Clean any previous output
while (ob_get_level() > 1) {
    ob_end_clean();
}

// Set headers AFTER starting output buffering
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ob_end_flush();
    exit(0);
}

// Log the request for debugging
error_log("Registration request received from: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

try {
    // Check if required files exist
    $requiredFiles = [
        __DIR__ . '/config/database.php',
        __DIR__ . '/services/RegistrationService.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("Required file not found: " . basename($file));
        }
    }
    
    // Include required files
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/services/RegistrationService.php';
    
    // Get JSON input
    $rawInput = file_get_contents('php://input');
    error_log("Raw input received: " . $rawInput);
    
    $input = json_decode($rawInput, true);
    
    if ($input === null) {
        $jsonError = json_last_error_msg();
        error_log("JSON decode error: " . $jsonError);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid JSON input: ' . $jsonError,
            'debug' => [
                'raw_input' => substr($rawInput, 0, 200) . (strlen($rawInput) > 200 ? '...' : ''),
                'json_error' => $jsonError
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        ob_end_flush();
        exit(0);
    }
    
    error_log("Parsed input: " . json_encode($input));
    
    // Validate required fields based on registration type
    $isQuickRegistration = isset($input['quickRegistration']) && $input['quickRegistration'];
    
    if ($isQuickRegistration) {
        // Quick registration requires password
        $requiredFields = ['fullName', 'password'];
    } else {
        // Comprehensive registration — only fullName is truly required
        $requiredFields = ['fullName'];
    }
    
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required fields: ' . implode(', ', $missingFields),
            'missing_fields' => $missingFields,
            'registration_type' => $isQuickRegistration ? 'quick' : 'comprehensive'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        ob_end_flush();
        exit(0);
    }
    
    // Test database connection
    try {
        $db = Database::getInstance()->getConnection();
        error_log("Database connection successful");
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
    
    // Create registration service
    $registrationService = new RegistrationService();
    error_log("RegistrationService created successfully");
    
    // Register member
    $result = $registrationService->registerMember($input);
    error_log("Registration result: " . json_encode($result));
    
    if ($result['success']) {
        // Clear ALL output buffers to ensure clean JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $result['data'],
            'message' => 'Registration successful! You can now log in with your credentials.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        ob_end_flush();
        exit(0);
    } else {
        // Clear ALL output buffers to ensure clean JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'errors' => $result['errors']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        ob_end_flush();
        exit(0);
    }
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    
    error_log("Registration error: " . $errorMessage);
    error_log("Stack trace: " . $errorTrace);
    
    // Clear ALL output buffers to ensure clean JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Registration failed: ' . $errorMessage,
        'debug' => [
            'message' => $errorMessage,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $errorTrace)
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    ob_end_flush();
    exit(1);
}
?>