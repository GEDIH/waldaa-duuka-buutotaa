<?php
/**
 * Member Registration API Endpoint
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'C:/xampp/apache/logs/php_errors.log');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean(); http_response_code(200); exit(0);
}

// Always guarantee JSON output — even on fatal errors
function sendJsonResponse(array $payload, int $code = 200): void {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(['success' => false, 'error' => 'Fatal: ' . $err['message']]);
        exit(0);
    }
    if (ob_get_level() > 0 && ob_get_length() === 0) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(['success' => false, 'error' => 'No output generated — check server logs']);
    }
});

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean(); http_response_code(200); exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/services/RegistrationService.php';

    $rawInput = file_get_contents('php://input');
    $input    = json_decode($rawInput, true);

    if ($input === null) {
        sendJsonResponse(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
    }

    // Validate required fields
    $isQuick = !empty($input['quickRegistration']);
    $required = $isQuick ? ['fullName', 'password'] : ['fullName'];
    $missing  = array_filter($required, fn($f) => empty($input[$f]));

    if (!empty($missing)) {
        sendJsonResponse(['success' => false, 'error' => 'Missing: ' . implode(', ', $missing)], 400);
    }

    $service = new RegistrationService();
    $result  = $service->registerMember($input);

    if ($result['success']) {
        sendJsonResponse([
            'success' => true,
            'data'    => $result['data'],
            'message' => 'Registration successful!'
        ], 201);
    } else {
        sendJsonResponse(['success' => false, 'errors' => $result['errors']], 400);
    }

} catch (Throwable $e) {
    error_log('api/register.php error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sendJsonResponse([
        'success' => false,
        'error'   => 'Registration failed: ' . $e->getMessage(),
    ], 500);
}
