<?php
/**
 * Members API Endpoint - Secured with Center-Based Access Control
 * Redirects to secure API implementation
 * Requirements: 7.1, 7.2, 7.3, 7.4, 9.1, 9.2, 9.4
 */

// Redirect to secure API implementation
require_once __DIR__ . '/secure-api.php';
exit();

class MembersAPI {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($method) {
                case 'GET':
                    return $this->getMembers();
                case 'POST':
                    return $this->createMember();
                case 'PUT':
                    return $this->updateMember();
                case 'DELETE':
                    return $this->deleteMember();
                default:
                    return $this->errorResponse('Method not allowed', 405);
            }
        } catch (Exception $e) {
            return $this->errorResponse('Internal server error: ' . $e->getMessage(), 500);
        }
    }
    
    private function getMembers() {
        // Get query parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $search = $_GET['search'] ?? '';
        $center = $_GET['center'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $offset = ($page - 1) * $limit;
        
        // Build query
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR member_id LIKE ? OR email LIKE ?)";
            $searchTerm = "%{$search}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($center)) {
            $whereConditions[] = "center = ?";
            $params[] = $center;
        }
        
        if (!empty($status)) {
            $whereConditions[] = "payment_status = ?";
            $params[] = $status;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) FROM members {$whereClause}";
        $countStmt = $this->pdo->prepare($countQuery);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetchColumn();
        
        // Get members
        $query = "
            SELECT 
                member_id,
                first_name,
                last_name,
                email,
                phone,
                center,
                payment_status,
                registration_date,
                country,
                state_region,
                city
            FROM members 
            {$whereClause}
            ORDER BY registration_date DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->successResponse([
            'members' => $members,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ]
        ]);
    }
    
    private function createMember() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            return $this->errorResponse('Invalid JSON input', 400);
        }
        
        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'email', 'phone'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                return $this->errorResponse("Missing required field: {$field}", 400);
            }
        }
        
        // Generate member ID
        $memberId = $this->generateMemberId();
        
        // Prepare member data
        $memberData = [
            'member_id' => $memberId,
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'center' => $input['center'] ?? 'Default',
            'payment_status' => $input['payment_status'] ?? 'unpaid',
            'country' => $input['country'] ?? '',
            'state_region' => $input['state_region'] ?? '',
            'city' => $input['city'] ?? '',
            'registration_date' => date('Y-m-d H:i:s')
        ];
        
        try {
            $query = "
                INSERT INTO members (
                    member_id, first_name, last_name, email, phone, center, 
                    payment_status, country, state_region, city, registration_date
                ) VALUES (
                    :member_id, :first_name, :last_name, :email, :phone, :center,
                    :payment_status, :country, :state_region, :city, :registration_date
                )
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($memberData);
            
            return $this->successResponse([
                'message' => 'Member created successfully',
                'member_id' => $memberId,
                'member' => $memberData
            ], 201);
            
        } catch (PKebedexception $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                return $this->errorResponse('Member with this email already exists', 409);
            }
            throw $e;
        }
    }
    
    private function updateMember() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || empty($input['member_id'])) {
            return $this->errorResponse('Member ID is required', 400);
        }
        
        $memberId = $input['member_id'];
        
        // Check if member exists
        $stmt = $this->pdo->prepare("SELECT member_id FROM members WHERE member_id = ?");
        $stmt->execute([$memberId]);
        
        if (!$stmt->fetch()) {
            return $this->errorResponse('Member not found', 404);
        }
        
        // Build update query
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'first_name', 'last_name', 'email', 'phone', 'center',
            'payment_status', 'country', 'state_region', 'city'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updateFields)) {
            return $this->errorResponse('No valid fields to update', 400);
        }
        
        $params[] = $memberId;
        
        $query = "UPDATE members SET " . implode(', ', $updateFields) . " WHERE member_id = ?";
        
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $this->successResponse([
                'message' => 'Member updated successfully',
                'member_id' => $memberId
            ]);
            
        } catch (PKebedexception $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                return $this->errorResponse('Email already exists for another member', 409);
            }
            throw $e;
        }
    }
    
    private function deleteMember() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || empty($input['member_id'])) {
            return $this->errorResponse('Member ID is required', 400);
        }
        
        $memberId = $input['member_id'];
        
        try {
            $this->pdo->beginTransaction();
            
            // Check if member exists
            $stmt = $this->pdo->prepare("SELECT member_id FROM members WHERE member_id = ?");
            $stmt->execute([$memberId]);
            
            if (!$stmt->fetch()) {
                $this->pdo->rollback();
                return $this->errorResponse('Member not found', 404);
            }
            
            // Delete related contributions first (if any)
            $stmt = $this->pdo->prepare("DELETE FROM contributions WHERE member_id = ?");
            $stmt->execute([$memberId]);
            
            // Delete member
            $stmt = $this->pdo->prepare("DELETE FROM members WHERE member_id = ?");
            $stmt->execute([$memberId]);
            
            $this->pdo->commit();
            
            return $this->successResponse([
                'message' => 'Member deleted successfully',
                'member_id' => $memberId
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
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
    
    private function successResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        return json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function errorResponse($message, $statusCode = 400) {
        http_response_code($statusCode);
        return json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Handle the request
$api = new MembersAPI();
echo $api->handleRequest();
?>