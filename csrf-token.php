<?php
/**
 * CSRF Token Generator
 * 
 * Generates and returns a CSRF token for the current session
 */

session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Return token as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'csrf_token' => $_SESSION['csrf_token']
]);
