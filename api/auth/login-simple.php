<?php
/**
 * Simple Admin Login API
 * Creates BOTH localStorage data AND PHP session for proper authentication
 */

// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit();
}

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['username']) || empty($input['password'])) {
        throw new Exception('Username and password are required');
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    $expectedRole = $input['expected_role'] ?? null;
    
    // Database connection
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    
            // Find user in users table (where username and password are stored)
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.username,
            u.email,
            u.password_hash as password,
            u.role,
            u.status,
            a.id as admin_id,
            a.full_name,
            a.phone
        FROM users u
        LEFT JOIN administrators a ON u.id = a.user_id
        WHERE u.username = ? AND u.status = 'active'
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Invalid username or password');
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Invalid username or password');
    }
    
    // Check role if specified
    if ($expectedRole && $user['role'] !== $expectedRole) {
        throw new Exception('Access denied: Insufficient privileges for selected role');
    }
    
    // Verify admin, superadmin, or system_admin role
    if (!in_array($user['role'], ['admin', 'superadmin', 'system_admin'])) {
        throw new Exception('Access denied: Administrative privileges required');
    }
    
    // Get assigned centers for admin users
    $assignedCenters = [];
    if ($user['role'] === 'admin' && $user['admin_id']) {
        $stmt = $pdo->prepare("
            SELECT 
                ac.center_id,
                c.name as center_name,
                c.code as center_code
            FROM admin_centers ac
            LEFT JOIN centers c ON ac.center_id = c.id
            WHERE ac.admin_id = ?
        ");
        $stmt->execute([$user['admin_id']]);
        $assignedCenters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Generate CSRF token
    $csrfToken = bin2hex(random_bytes(32));
    
    // Create PHP SESSION (THIS IS CRITICAL FOR API FILES)
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['admin_id'] = $user['admin_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['csrf_token'] = $csrfToken;
    $_SESSION['login_time'] = time();
    $_SESSION['assigned_centers'] = array_column($assignedCenters, 'center_id');
    
    // Prepare response data (for localStorage)
    $userData = [
        'id' => $user['user_id'],
        'admin_id' => $user['admin_id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'] ?? $user['username'],
        'role' => $user['role'],
        'assigned_centers' => $assignedCenters
    ];
    
    // Set permissions based on role
    $permissions = [];
    if ($user['role'] === 'superadmin') {
        $permissions = [
            'manage_users' => true,
            'manage_centers' => true,
            'manage_members' => true,
            'manage_contributions' => true,
            'manage_announcements' => true,
            'view_analytics' => true,
            'system_settings' => true
        ];
    } elseif ($user['role'] === 'system_admin') {
        $permissions = [
            'manage_users' => true,
            'manage_centers' => true,
            'manage_members' => true,
            'manage_contributions' => true,
            'manage_announcements' => true,
            'view_analytics' => true,
            'system_settings' => true,
            'system_administration' => true,
            'database_management' => true,
            'security_management' => true
        ];
    } else {
        $permissions = [
            'manage_users' => false,
            'manage_centers' => false,
            'manage_members' => true,
            'manage_contributions' => true,
            'manage_announcements' => false,
            'view_analytics' => true,
            'system_settings' => false
        ];
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'user' => $userData,
            'csrf_token' => $csrfToken,
            'permissions' => $permissions,
            'session_created' => true // Confirm PHP session was created
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
