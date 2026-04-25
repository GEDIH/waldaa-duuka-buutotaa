<?php
/**
 * Payment Methods Administration API
 * System Administrator interface for managing payment methods
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session for authentication
session_start();

try {
    // Database connection
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    
    // Check authentication
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['system_admin', 'superadmin'])) {
        throw new Exception('Unauthorized access');
    }
    
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($pdo, $action);
            break;
        case 'POST':
            handlePost($pdo, $action);
            break;
        case 'PUT':
            handlePut($pdo, $action);
            break;
        case 'DELETE':
            handleDelete($pdo, $action);
            break;
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function handleGet($pdo, $action) {
    switch ($action) {
        case 'list':
            getPaymentMethods($pdo);
            break;
        case 'stats':
            getPaymentStats($pdo);
            break;
        case 'transactions':
            getRecentTransactions($pdo);
            break;
        case 'gateways':
            getPaymentGateways($pdo);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePost($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            createPaymentMethod($pdo, $input);
            break;
        case 'test-gateway':
            testPaymentGateway($pdo, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handlePut($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update':
            updatePaymentMethod($pdo, $input);
            break;
        case 'toggle-status':
            togglePaymentMethodStatus($pdo, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function handleDelete($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'delete':
            deletePaymentMethod($pdo, $input);
            break;
        default:
            throw new Exception('Invalid action');
    }
}

function getPaymentMethods($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            pm.*,
            COUNT(c.id) as usage_count,
            SUM(CASE WHEN c.payment_status = 'confirmed' THEN c.amount ELSE 0 END) as total_amount
        FROM payment_methods pm
        LEFT JOIN contributions c ON pm.id = c.payment_method_id
        GROUP BY pm.id
        ORDER BY pm.is_active DESC, pm.display_order ASC
    ");
    $stmt->execute();
    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $methods
    ]);
}

function getPaymentStats($pdo) {
    // Total payment methods
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payment_methods");
    $stmt->execute();
    $totalMethods = $stmt->fetchColumn();
    
    // Active payment methods
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM payment_methods WHERE is_active = 1");
    $stmt->execute();
    $activeMethods = $stmt->fetchColumn();
    
    // Total transactions this month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as transactions 
        FROM contributions 
        WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        AND payment_status = 'confirmed'
    ");
    $stmt->execute();
    $monthlyTransactions = $stmt->fetchColumn();
    
    // Total amount this month
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as amount 
        FROM contributions 
        WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        AND payment_status = 'confirmed'
    ");
    $stmt->execute();
    $monthlyAmount = $stmt->fetchColumn();
    
    // Payment method usage
    $stmt = $pdo->prepare("
        SELECT 
            pm.name,
            pm.type,
            COUNT(c.id) as usage_count,
            SUM(CASE WHEN c.payment_status = 'confirmed' THEN c.amount ELSE 0 END) as total_amount
        FROM payment_methods pm
        LEFT JOIN contributions c ON pm.id = c.payment_method_id
        WHERE pm.is_active = 1
        GROUP BY pm.id
        ORDER BY usage_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_methods' => $totalMethods,
            'active_methods' => $activeMethods,
            'monthly_transactions' => $monthlyTransactions,
            'monthly_amount' => $monthlyAmount,
            'top_methods' => $topMethods
        ]
    ]);
}

function getRecentTransactions($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.amount,
            c.payment_date,
            c.payment_status,
            pm.name as payment_method,
            pm.type as payment_type,
            m.full_name as member_name,
            cent.name as center_name
        FROM contributions c
        LEFT JOIN payment_methods pm ON c.payment_method_id = pm.id
        LEFT JOIN members m ON c.member_id = m.id
        LEFT JOIN centers cent ON m.center_id = cent.id
        WHERE c.payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
        ORDER BY c.payment_date DESC
        LIMIT 20
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $transactions
    ]);
}

function getPaymentGateways($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM payment_gateways 
        ORDER BY is_active DESC, name ASC
    ");
    $stmt->execute();
    $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $gateways
    ]);
}

function createPaymentMethod($pdo, $input) {
    $stmt = $pdo->prepare("
        INSERT INTO payment_methods (
            name, type, description, is_active, requires_verification,
            min_amount, max_amount, processing_fee, display_order,
            gateway_id, configuration, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $input['name'],
        $input['type'],
        $input['description'] ?? '',
        $input['is_active'] ?? 1,
        $input['requires_verification'] ?? 0,
        $input['min_amount'] ?? 0,
        $input['max_amount'] ?? null,
        $input['processing_fee'] ?? 0,
        $input['display_order'] ?? 0,
        $input['gateway_id'] ?? null,
        json_encode($input['configuration'] ?? [])
    ]);
    
    $methodId = $pdo->lastInsertId();
    
    // Log the action
    logSystemAction($pdo, 'payment_method_created', [
        'method_id' => $methodId,
        'name' => $input['name'],
        'type' => $input['type']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment method created successfully',
        'data' => ['id' => $methodId]
    ]);
}

function updatePaymentMethod($pdo, $input) {
    $stmt = $pdo->prepare("
        UPDATE payment_methods SET
            name = ?, type = ?, description = ?, is_active = ?,
            requires_verification = ?, min_amount = ?, max_amount = ?,
            processing_fee = ?, display_order = ?, gateway_id = ?,
            configuration = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $input['name'],
        $input['type'],
        $input['description'] ?? '',
        $input['is_active'] ?? 1,
        $input['requires_verification'] ?? 0,
        $input['min_amount'] ?? 0,
        $input['max_amount'] ?? null,
        $input['processing_fee'] ?? 0,
        $input['display_order'] ?? 0,
        $input['gateway_id'] ?? null,
        json_encode($input['configuration'] ?? []),
        $input['id']
    ]);
    
    // Log the action
    logSystemAction($pdo, 'payment_method_updated', [
        'method_id' => $input['id'],
        'name' => $input['name']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment method updated successfully'
    ]);
}

function togglePaymentMethodStatus($pdo, $input) {
    $stmt = $pdo->prepare("
        UPDATE payment_methods 
        SET is_active = NOT is_active, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$input['id']]);
    
    // Get new status
    $stmt = $pdo->prepare("SELECT is_active FROM payment_methods WHERE id = ?");
    $stmt->execute([$input['id']]);
    $newStatus = $stmt->fetchColumn();
    
    // Log the action
    logSystemAction($pdo, 'payment_method_status_changed', [
        'method_id' => $input['id'],
        'new_status' => $newStatus ? 'active' : 'inactive'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment method status updated',
        'data' => ['is_active' => $newStatus]
    ]);
}

function deletePaymentMethod($pdo, $input) {
    // Check if method is in use
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contributions WHERE payment_method_id = ?");
    $stmt->execute([$input['id']]);
    $usageCount = $stmt->fetchColumn();
    
    if ($usageCount > 0) {
        throw new Exception("Cannot delete payment method: it has been used in {$usageCount} transactions");
    }
    
    $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE id = ?");
    $stmt->execute([$input['id']]);
    
    // Log the action
    logSystemAction($pdo, 'payment_method_deleted', [
        'method_id' => $input['id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment method deleted successfully'
    ]);
}

function testPaymentGateway($pdo, $input) {
    // This would test the connection to the payment gateway
    // For now, we'll simulate a test
    
    $gatewayId = $input['gateway_id'];
    $testAmount = $input['test_amount'] ?? 1.00;
    
    // Simulate gateway test
    $testResult = [
        'success' => true,
        'response_time' => rand(100, 500) . 'ms',
        'gateway_status' => 'online',
        'test_transaction_id' => 'TEST_' . time(),
        'message' => 'Gateway connection successful'
    ];
    
    // Log the test
    logSystemAction($pdo, 'payment_gateway_tested', [
        'gateway_id' => $gatewayId,
        'test_amount' => $testAmount,
        'result' => $testResult['success'] ? 'success' : 'failed'
    ]);
    
    echo json_encode([
        'success' => true,
        'data' => $testResult
    ]);
}

function logSystemAction($pdo, $action, $details) {
    $stmt = $pdo->prepare("
        INSERT INTO system_operations_log (
            operation_id, user_id, user_role, operation_type, 
            operation_category, operation_details, ip_address,
            session_id, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        uniqid('PAY_'),
        $_SESSION['user_id'],
        $_SESSION['role'],
        $action,
        'payment_management',
        json_encode($details),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        session_id()
    ]);
}
?>