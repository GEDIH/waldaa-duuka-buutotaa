<?php
/**
 * Member Registration API Endpoint
 * Handles new member registration with comprehensive validation
 * 
 * CRITICAL: This file must output ONLY valid JSON
 * Any PHP warnings, notices, or echo statements will break the frontend
 */

// CRITICAL: Disable ALL error display to prevent breaking JSON
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/registration_errors.log');

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
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit(0);
}

// Suppress any warnings from require
@require_once '../config/database.php';

class MemberRegistrationAPI {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }
    
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->errorResponse('Method not allowed', 405);
        }
        
        try {
            return $this->registerMember();
        } catch (Exception $e) {
            return $this->errorResponse('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    private function registerMember() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            return $this->errorResponse('Invalid JSON input', 400);
        }
        
        // Validate required fields
        $validation = $this->validateRegistrationData($input);
        if (!$validation['valid']) {
            return $this->errorResponse($validation['message'], 400);
        }
        
        // Check for duplicate email
        if ($this->emailExists($input['email'])) {
            return $this->errorResponse('Email address already registered', 409);
        }
        
        // Generate member ID
        $memberId = $this->generateMemberId();
        
        // Prepare member data
        $memberData = $this->prepareMemberData($input, $memberId);
        
        try {
            $this->pdo->beginTransaction();
            
            // Insert member
            $this->insertMember($memberData);
            
            // Log registration
            $this->logRegistration($memberId, $input['email']);
            
            $this->pdo->commit();
            
            return $this->successResponse([
                'message' => 'Member registered successfully',
                'member_id' => $memberId,
                'registration_date' => $memberData['registration_date']
            ], 201);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    private function validateRegistrationData($data) {
        // Required fields
        $requiredFields = [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email address',
            'phone' => 'Phone number'
        ];
        
        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'message' => "{$label} is required"
                ];
            }
        }
        
        // Email validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Invalid email address format'
            ];
        }
        
        // Phone validation (basic)
        if (!preg_match('/^[\+]?[0-9\-\(\)\s]+$/', $data['phone'])) {
            return [
                'valid' => false,
                'message' => 'Invalid phone number format'
            ];
        }
        
        // Name validation
        if (strlen($data['first_name']) < 2 || strlen($data['last_name']) < 2) {
            return [
                'valid' => false,
                'message' => 'First name and last name must be at least 2 characters long'
            ];
        }
        
        return ['valid' => true];
    }
    
    private function emailExists($email) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function generateMemberId() {
        $currentYear = date('Y');
        
        // Get the highest sequence number for the current year
        $stmt = $this->pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(member_id, 10, 4) AS UNSIGNED)) as max_sequence
            FROM members
            WHERE member_id LIKE ?
        ");
        $stmt->execute(["WDB-{$currentYear}-%"]);
        
        $maxSequence = $stmt->fetchColumn() ?? 0;
        $nextSequence = $maxSequence + 1;
        
        return sprintf('WDB-%s-%04d', $currentYear, $nextSequence);
    }
    
    private function prepareMemberData($input, $memberId) {
        return [
            'member_id' => $memberId,
            'first_name' => trim($input['first_name']),
            'last_name' => trim($input['last_name']),
            'email' => strtolower(trim($input['email'])),
            'phone' => trim($input['phone']),
            'date_of_birth' => $input['date_of_birth'] ?? null,
            'gender' => $input['gender'] ?? null,
            'marital_status' => $input['marital_status'] ?? null,
            'education_level' => $input['education_level'] ?? null,
            'profession' => $input['profession'] ?? null,
            'country' => $input['country'] ?? '',
            'state_region' => $input['state_region'] ?? '',
            'city' => $input['city'] ?? '',
            'address' => $input['address'] ?? '',
            'postal_code' => $input['postal_code'] ?? '',
            'center' => $input['center'] ?? 'Default',
            'payment_status' => 'unpaid',
            'registration_date' => date('Y-m-d H:i:s'),
            'notes' => $input['notes'] ?? ''
        ];
    }
    
    private function insertMember($memberData) {
        $query = "
            INSERT INTO members (
                member_id, first_name, last_name, email, phone, date_of_birth,
                gender, marital_status, education_level, profession, country,
                state_region, city, address, postal_code, center, payment_status,
                registration_date, notes
            ) VALUES (
                :member_id, :first_name, :last_name, :email, :phone, :date_of_birth,
                :gender, :marital_status, :education_level, :profession, :country,
                :state_region, :city, :address, :postal_code, :center, :payment_status,
                :registration_date, :notes
            )
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($memberData);
    }
    
    private function logRegistration($memberId, $email) {
        $logData = [
            'action' => 'member_registration',
            'member_id' => $memberId,
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Create registration_log table if it Kebedesn't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS registration_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(50) NOT NULL,
                member_id VARCHAR(20),
                email VARCHAR(255),
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp DATETIME NOT NULL,
                INDEX idx_member_id (member_id),
                INDEX idx_timestamp (timestamp)
            )
        ");
        
        $query = "
            INSERT INTO registration_log (action, member_id, email, ip_address, user_agent, timestamp)
            VALUES (:action, :member_id, :email, :ip_address, :user_agent, :timestamp)
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($logData);
    }
    
    private function successResponse($data, $statusCode = 200) {
        // Clear ALL output buffers to ensure clean JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Start fresh buffer
        ob_start();
        
        http_response_code($statusCode);
        $response = json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Output ONLY the JSON
        echo $response;
        
        // Flush and end
        ob_end_flush();
        exit(0);
    }
    
    private function errorResponse($message, $statusCode = 400) {
        // Clear ALL output buffers to ensure clean JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Start fresh buffer
        ob_start();
        
        http_response_code($statusCode);
        $response = json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Output ONLY the JSON
        echo $response;
        
        // Flush and end
        ob_end_flush();
        exit(0);
    }
}

// Handle the request and output response
try {
    $api = new MemberRegistrationAPI();
    $api->handleRequest();
} catch (Exception $e) {
    // Emergency error handler - ensure we output valid JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    ob_end_flush();
    exit(1);
}
?>