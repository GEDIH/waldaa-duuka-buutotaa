<?php
/**
 * System Admin Auth Integration
 * Handles login/session for the System Administrator Dashboard
 */
ob_start();
error_reporting(0);
ini_set('display_errors', '0');
ini_set('error_log', 'C:/xampp/apache/logs/php_errors.log');

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean(); http_response_code(200); exit(0);
}

function sendJson(array $data, int $code = 200): void {
    ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

$action = $_GET['action'] ?? 'login';

// ── Session check ─────────────────────────────────────────────────────────────
if ($action === 'session') {
    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_user'])) {
        sendJson([
            'success'       => true,
            'authenticated' => true,
            'user'          => $_SESSION['admin_user'],
        ]);
    }
    sendJson(['success' => true, 'authenticated' => false]);
}

// ── Logout ────────────────────────────────────────────────────────────────────
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    sendJson(['success' => true]);
}

// ── Login ─────────────────────────────────────────────────────────────────────
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendJson(['success' => false, 'error' => 'Invalid request'], 400);

    $username   = trim($input['username'] ?? '');
    $password   = $input['password']   ?? '';
    $rememberMe = !empty($input['remember_me']);

    if (!$username || !$password) {
        sendJson(['success' => false, 'error' => 'Username and password are required'], 400);
    }

    try {
        require_once __DIR__ . '/api/config/database.php';
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare("
            SELECT id, username, email, password_hash, role, status, full_name
            FROM users
            WHERE username = ? AND status = 'active'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            sendJson(['success' => false, 'error' => 'Invalid username or password'], 401);
        }

        // Only allow admin roles
        if (!in_array($user['role'], ['admin', 'superadmin', 'system_admin'])) {
            sendJson(['success' => false, 'error' => 'Access denied: insufficient privileges'], 403);
        }

        // Store session
        $userData = [
            'id'        => $user['id'],
            'username'  => $user['username'],
            'email'     => $user['email'],
            'full_name' => $user['full_name'] ?? $user['username'],
            'role'      => $user['role'],
        ];

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $userData;
        $_SESSION['admin_login_time']= time();

        if ($rememberMe) {
            // Extend session lifetime
            ini_set('session.cookie_lifetime', 30 * 24 * 3600);
        }

        // Update last login
        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
            ->execute([$user['id']]);

        sendJson([
            'success'      => true,
            'user'         => $userData,
            'redirect_url' => 'system-administrator-dashboard.html',
        ]);

    } catch (Throwable $e) {
        error_log('system-admin-auth error: ' . $e->getMessage());
        sendJson(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

sendJson(['success' => false, 'error' => 'Unknown action'], 400);
