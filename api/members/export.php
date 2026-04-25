<?php
/**
 * Members Export API
 */

header('Content-Type: application/json');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=wdb_membership;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->query("SELECT member_id, full_name, email, mobile_phone, country FROM members ORDER BY member_id");
    $members = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $members,
        'count' => count($members)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>