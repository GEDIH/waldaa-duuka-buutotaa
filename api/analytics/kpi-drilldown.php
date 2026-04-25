<?php
/**
 * KPI Drill-Down API
 * Provides detailed member/admin lists when clicking on KPI cards
 * Requirements: 1.3 - Drill-down modals with filtered data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../api/config/database.php';

// Get request parameters
$kpiType = $_GET['type'] ?? '';
$centerId = $_GET['center_id'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $result = [];
    
    switch ($kpiType) {
        case 'total_members':
            $result = getTotalMembers($conn, $centerId, $limit, $offset);
            break;
            
        case 'active_members':
            $result = getActiveMembers($conn, $centerId, $limit, $offset);
            break;
            
        case 'paid_members':
            $result = getPaidMembers($conn, $centerId, $limit, $offset);
            break;
            
        case 'unpaid_members':
            $result = getUnpaidMembers($conn, $centerId, $limit, $offset);
            break;
            
        case 'new_today':
            $result = getNewToday($conn, $centerId, $limit, $offset);
            break;
            
        case 'new_registrants':
            $result = getNewRegistrants($conn, $centerId, $limit, $offset);
            break;
            
        case 'total_admins':
            $result = getTotalAdmins($conn, $limit, $offset);
            break;
            
        case 'all_centers':
            $result = getAllCenters($conn, $limit, $offset);
            break;
            
        default:
            throw new Exception("Invalid KPI type: {$kpiType}");
    }
    
    echo json_encode([
        'success' => true,
        'kpi_type' => $kpiType,
        'data' => $result['data'],
        'total' => $result['total'],
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($result['total'] / $limit)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get total members list
 */
function getTotalMembers($conn, $centerId, $limit, $offset) {
    $sql = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE 1=1";
    
    $params = [];
    
    if ($centerId) {
        $sql .= " AND m.center_id = :center_id";
        $params[':center_id'] = $centerId;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM members m WHERE 1=1";
    if ($centerId) {
        $countSql .= " AND m.center_id = :center_id";
    }
    
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $sql .= " ORDER BY m.membership_date DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['data' => $data, 'total' => $total];
}

/**
 * Get active members list
 */
function getActiveMembers($conn, $centerId, $limit, $offset) {
    $sql = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE m.status = 'active'";
    
    $params = [];
    
    if ($centerId) {
        $sql .= " AND m.center_id = :center_id";
        $params[':center_id'] = $centerId;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM members m WHERE m.status = 'active'";
    if ($centerId) {
        $countSql .= " AND m.center_id = :center_id";
    }
    
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $sql .= " ORDER BY m.membership_date DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['data' => $data, 'total' => $total];
}

/**
 * Get paid members list
 */
function getPaidMembers($conn, $centerId, $limit, $offset) {
    $sql = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE m.payment_status = 'paid'";
    
    $params = [];
    
    if ($centerId) {
        $sql .= " AND m.center_id = :center_id";
        $params[':center_id'] = $centerId;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM members m WHERE m.payment_status = 'paid'";
    if ($centerId) {
        $countSql .= " AND m.center_id = :center_id";
    }
    
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $sql .= " ORDER BY m.membership_date DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['data' => $data, 'total' => $total];
}

/**
 * Get unpaid members list
 */
function getUnpaidMembers($conn, $centerId, $limit, $offset) {
    $sql = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE m.payment_status = 'unpaid'";
    
    $params = [];
    
    if ($centerId) {
        $sql .= " AND m.center_id = :center_id";
        $params[':center_id'] = $centerId;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM members m WHERE m.payment_status = 'unpaid'";
    if ($centerId) {
        $countSql .= " AND m.center_id = :center_id";
    }
    
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $sql .= " ORDER BY m.membership_date DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['data' => $data, 'total' => $total];
}

/**
 * Get new members registered today
 */
function getNewToday($conn, $centerId, $limit, $offset) {
    $sql = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region,
                m.created_at
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE DATE(m.created_at) = CURDATE()";
    
    $params = [];
    
    if ($centerId) {
        $sql .= " AND m.center_id = :center_id";
        $params[':center_id'] = $centerId;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM members m WHERE DATE(m.created_at) = CURDATE()";
    if ($centerId) {
        $countSql .= " AND m.center_id = :center_id";
    }
    
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $sql .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['data' => $data, 'total' => $total];
}

/**
 * Get new registrants (last 30 days)
 */
function getNewRegistrants($conn, $centerId, $limit, $offset) {
    $sql = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region,
                m.created_at
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE m.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    
    $params = [];
    
    if ($centerId) {
        $sql .= " AND m.center_id = :center_id";
        $params[':center_id'] = $centerId;
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM members m WHERE m.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    if ($centerId) {
        $countSql .= " AND m.center_id = :center_id";
    }
    
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $sql .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['data' => $data, 'total' => $total];
}

/**
 * Get total admins list
 */
function getTotalAdmins($conn, $limit, $offset) {
    $sql = "SELECT 
                u.id,
                u.email,
                u.full_name,
                u.role,
                u.status,
                u.last_login,
                u.created_at
            FROM users u
            WHERE u.role IN ('admin', 'superadmin')";
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'superadmin')";
    $stmt = $conn->prepare($countSql);
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $sql .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['data' => $data, 'total' => $total];
}

/**
 * Get all centers list
 */
function getAllCenters($conn, $limit, $offset) {
    $sql = "SELECT 
                c.id,
                c.name,
                c.code,
                c.address,
                c.city,
                c.region,
                c.status,
                c.contact_person,
                COUNT(m.id) as total_members
            FROM centers c
            LEFT JOIN members m ON c.id = m.center_id
            GROUP BY c.id, c.name, c.code, c.address, c.city, c.region, c.status, c.contact_person";
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM centers";
    $stmt = $conn->prepare($countSql);
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated data
    $sql .= " ORDER BY c.name ASC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['data' => $data, 'total' => $total];
}
