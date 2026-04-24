<?php
/**
 * Member Registration API Endpoint
 */

// Define system constant
define('WDB_SYSTEM', true);

// Include configuration and classes
require_once '../config.php';
require_once '../classes/Member.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If no JSON input, try form data
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate input
    if (empty($input)) {
        jsonResponse(['success' => false, 'message' => 'No data received'], 400);
    }
    
    // Required fields validation
    $requiredFields = ['fname', 'lname', 'gender', 'phone', 'address', 'baptized'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        jsonResponse([
            'success' => false, 
            'message' => 'Missing required fields: ' . implode(', ', $missingFields)
        ], 400);
    }
    
    // Validate phone number format
    $phone = trim($input['phone']);
    if (!preg_match('/^(\+251|0)?[79]\d{8}$/', $phone)) {
        jsonResponse([
            'success' => false, 
            'message' => 'Invalid phone number format. Use Ethiopian format: +251XXXXXXXXX or 09XXXXXXXX'
        ], 400);
    }
    
    // Validate email if provided
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse([
            'success' => false, 
            'message' => 'Invalid email format'
        ], 400);
    }
    
    // Validate gender
    $validGenders = ['Dhiira', 'Dubartii'];
    if (!in_array($input['gender'], $validGenders)) {
        jsonResponse([
            'success' => false, 
            'message' => 'Invalid gender selection'
        ], 400);
    }
    
    // Validate baptized status
    $validBaptized = ['eeyyee', 'lakki'];
    if (!in_array($input['baptized'], $validBaptized)) {
        jsonResponse([
            'success' => false, 
            'message' => 'Invalid baptized status'
        ], 400);
    }
    
    // Prepare member data
    $memberData = [
        'first_name' => trim($input['fname']),
        'last_name' => trim($input['lname']),
        'gender' => $input['gender'],
        'date_of_birth' => !empty($input['dob']) ? $input['dob'] : null,
        'phone' => $phone,
        'email' => !empty($input['email']) ? trim($input['email']) : null,
        'address' => trim($input['address']),
        'current_church' => !empty($input['currentChurch']) ? trim($input['currentChurch']) : null,
        'baptized' => $input['baptized'],
        'service_interest' => !empty($input['service']) ? trim($input['service']) : null,
        'how_heard' => !empty($input['howHeard']) ? trim($input['howHeard']) : null,
        'notes' => !empty($input['notes']) ? trim($input['notes']) : null
    ];
    
    // Create member instance and register
    $member = new Member();
    $result = $member->register($memberData);
    
    if ($result['success']) {
        // Log successful registration
        logActivity('member_registration_api', "Member registered via API: {$result['member_id']}");
        
        jsonResponse([
            'success' => true,
            'message' => 'Galmaa\'inni milkaa\'inaan xumurame! Registration completed successfully!',
            'member_id' => $result['member_id'],
            'data' => [
                'id' => $result['id'],
                'member_id' => $result['member_id'],
                'full_name' => $memberData['first_name'] . ' ' . $memberData['last_name'],
                'status' => 'pending'
            ]
        ]);
    } else {
        // Log failed registration
        logActivity('member_registration_failed', "Registration failed: " . $result['message']);
        
        jsonResponse([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Registration API error: " . $e->getMessage());
    logActivity('member_registration_error', "API error: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'message' => 'Registration failed due to server error. Please try again.'
    ], 500);
}
?>