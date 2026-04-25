<?php
/**
 * KPI Summary API
 * Provides count summaries for Report Management KPI cards
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get total members
    $stmt = $conn->query("SELECT COUNT(*) as count FROM members");
    $totalMembers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get active members
    $stmt = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
    $activeMembers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get paid members
    $stmt = $conn->query("SELECT COUNT(*) as count FROM members WHERE payment_status = 'paid'");
    $paidMembers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get unpaid members
    $stmt = $conn->query("SELECT COUNT(*) as count FROM members WHERE payment_status = 'unpaid'");
    $unpaidMembers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get new registrants (last 30 days)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM members WHERE membership_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $newRegistrants = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total admins
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('admin', 'superadmin')");
    $totalAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total active centers
    $stmt = $conn->query("SELECT COUNT(*) as count FROM centers WHERE status = 'active'");
    $totalCenters = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_members' => (int)$totalMembers,
            'active_members' => (int)$activeMembers,
            'paid_members' => (int)$paidMembers,
            'unpaid_members' => (int)$unpaidMembers,
            'new_registrants' => (int)$newRegistrants,
            'total_admins' => (int)$totalAdmins,
            'total_centers' => (int)$totalCenters
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch KPI summary',
        'message' => $e->getMessage()
    ]);
}
