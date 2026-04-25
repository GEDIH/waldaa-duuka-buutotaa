<?php
/**
 * Additional Authentication Verification Endpoint
 * Handles 2FA and password verification for sensitive data access
 * Requirements: 12.2, 12.6
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AnalyticsSecurityManager.php';

try {
    // Initialize database and security manager
    $database = new Database();
    $db = $database->getConnection();
    $securityManager = new AnalyticsSecurityManager($db);
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }
    
    $password = $input['password'] ?? '';
    $twoFactorCode = $input['two_factor_code'] ?? '';
    $sensitivityLevel = $input['sensitivity_level'] ?? $input['sensitivityLevel'] ?? 'confidential';
    $reason = $input['reason'] ?? '';
    
    // Widget-specific parameters
    $widgetId = $input['widgetId'] ?? $input['widget_id'] ?? null;
    $widgetType = $input['widgetType'] ?? $input['widget_type'] ?? null;
    
    if (empty($password)) {
        throw new Exception('Password is required');
    }
    
    if ($widgetId && empty($reason)) {
        throw new Exception('Access reason is required for widget authentication');
    }
    
    // Start session and get user ID
    session_start();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User not authenticated');
    }
    
    // Verify password
    $stmt = $db->prepare("SELECT id, password_hash, two_factor_enabled FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Log failed attempt
        $securityManager->logSecurityEvent('ADDITIONAL_AUTH_FAILED', [
            'user_id' => $userId,
            'reason' => 'invalid_password',
            'sensitivity_level' => $sensitivityLevel
        ]);
        
        throw new Exception('Invalid password');
    }
    
    // Check if 2FA is required
    $requires2FA = $user['two_factor_enabled'] && in_array($sensitivityLevel, ['confidential', 'restricted']);
    
    if ($requires2FA) {
        if (empty($twoFactorCode)) {
            // Password verified, but 2FA code needed
            echo json_encode([
                'success' => false,
                'error' => 'Two-factor authentication code required',
                'requires_2fa' => true
            ]);
            exit();
        }
        
        // Verify 2FA code
        if (!verify2FACode($db, $userId, $twoFactorCode)) {
            // Log failed 2FA attempt
            $securityManager->logSecurityEvent('2FA_VERIFICATION_FAILED', [
                'user_id' => $userId,
                'sensitivity_level' => $sensitivityLevel
            ]);
            
            throw new Exception('Invalid two-factor authentication code');
        }
    }
    
    // Set additional authentication session
    $method = $requires2FA ? '2fa' : 'password';
    $securityManager->setAdditionalAuthSession($userId, $method);
    
    // Generate widget-specific auth token if this is for a widget
    $authToken = null;
    $expiresAt = null;
    
    if ($widgetId) {
        $authToken = bin2hex(random_bytes(32));
        $expiresAt = date('c', time() + 1800); // 30 minutes for widget auth
        
        // Store widget auth token in database
        $stmt = $db->prepare("
            INSERT INTO widget_auth_tokens (user_id, widget_id, auth_token, reason, expires_at) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                auth_token = VALUES(auth_token),
                reason = VALUES(reason),
                expires_at = VALUES(expires_at),
                created_at = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("issss", $userId, $widgetId, $authToken, $reason, $expiresAt);
        $stmt->execute();
        
        // Log widget authentication
        $securityManager->logSecurityEvent('WIDGET_ADDITIONAL_AUTH_SUCCESS', [
            'user_id' => $userId,
            'widget_id' => $widgetId,
            'widget_type' => $widgetType,
            'method' => $method,
            'reason' => $reason,
            'sensitivity_level' => $sensitivityLevel
        ]);
    } else {
        // Log general additional authentication
        $securityManager->logSecurityEvent('ADDITIONAL_AUTH_SUCCESS', [
            'user_id' => $userId,
            'method' => $method,
            'sensitivity_level' => $sensitivityLevel
        ]);
    }
    
    $response = [
        'success' => true,
        'message' => 'Additional authentication successful',
        'method' => $method,
        'valid_until' => date('c', time() + ($method === '2fa' ? 1800 : 600)) // 30 min for 2FA, 10 min for password
    ];
    
    // Add widget-specific response data
    if ($widgetId && $authToken) {
        $response['authToken'] = $authToken;
        $response['expiresAt'] = $expiresAt;
        $response['widgetId'] = $widgetId;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Additional Auth Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Verify 2FA code
 */
function verify2FACode($db, $userId, $code) {
    // Get user's 2FA secret
    $stmt = $db->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $user = $result->fetch_assoc();
    $secret = $user['two_factor_secret'];
    
    if (empty($secret)) {
        return false;
    }
    
    // Simple TOTP verification (in production, use a proper TOTP library)
    // This is a simplified implementation for demonstration
    $timeSlice = floor(time() / 30);
    
    // Check current time slice and previous one (for clock drift)
    for ($i = 0; $i <= 1; $i++) {
        $calculatedCode = generateTOTP($secret, $timeSlice - $i);
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate TOTP code (simplified implementation)
 */
function generateTOTP($secret, $timeSlice) {
    // This is a simplified TOTP implementation
    // In production, use a proper library like RobThree/TwoFactorAuth
    
    $key = base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset+0]) & 0x7f) << 24) |
        ((ord($hash[$offset+1]) & 0xff) << 16) |
        ((ord($hash[$offset+2]) & 0xff) << 8) |
        (ord($hash[$offset+3]) & 0xff)
    ) % pow(10, 6);
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Base32 decode (simplified)
 */
function base32_decode($input) {
    // Simplified base32 decode - use proper library in production
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0, $j = strlen($input); $i < $j; $i++) {
        $v <<= 5;
        $v += strpos($alphabet, $input[$i]);
        $vbits += 5;
        
        if ($vbits >= 8) {
            $output .= chr($v >> ($vbits - 8));
            $vbits -= 8;
        }
    }
    
    return $output;
}
?>