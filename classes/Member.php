<?php
/**
 * Member Class - Handles member operations
 */

if (!defined('WDB_SYSTEM')) {
    die('Direct access not allowed');
}

require_once 'Database.php';

class Member {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Register a new member
     */
    public function register($data) {
        try {
            // Validate required fields
            $required = ['first_name', 'last_name', 'gender', 'phone', 'address', 'baptized'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Field {$field} is required"];
                }
            }
            
            // Check if phone already exists
            if ($this->phoneExists($data['phone'])) {
                return ['success' => false, 'message' => 'Phone number already registered'];
            }
            
            // Check if email already exists (if provided)
            if (!empty($data['email']) && $this->emailExists($data['email'])) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Generate unique member ID
            $memberId = $this->generateMemberId();
            
            // Prepare member data
            $memberData = [
                'member_id' => $memberId,
                'first_name' => sanitizeInput($data['first_name']),
                'last_name' => sanitizeInput($data['last_name']),
                'gender' => $data['gender'] === 'Dhiira' ? 'male' : 'female',
                'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                'phone' => sanitizeInput($data['phone']),
                'email' => !empty($data['email']) ? sanitizeInput($data['email']) : null,
                'address' => sanitizeInput($data['address']),
                'current_church' => !empty($data['current_church']) ? sanitizeInput($data['current_church']) : null,
                'baptized' => $data['baptized'] === 'eeyyee' ? 'yes' : 'no',
                'service_interest' => !empty($data['service_interest']) ? sanitizeInput($data['service_interest']) : null,
                'how_heard' => !empty($data['how_heard']) ? sanitizeInput($data['how_heard']) : null,
                'notes' => !empty($data['notes']) ? sanitizeInput($data['notes']) : null,
                'status' => 'pending',
                'center_id' => 1 // Default to main center
            ];
            
            // Insert member
            $memberId = $this->db->insert('members', $memberData);
            
            // Log activity
            logActivity('member_registered', "New member registered: {$memberData['member_id']}", $memberId);
            
            // Send welcome email if email provided
            if (!empty($memberData['email'])) {
                $this->sendWelcomeEmail($memberData);
            }
            
            return [
                'success' => true,
                'message' => 'Registration successful',
                'member_id' => $memberData['member_id'],
                'id' => $memberId
            ];
            
        } catch (Exception $e) {
            error_log("Member registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Get member by ID
     */
    public function getById($id) {
        $sql = "
            SELECT m.*, c.name as center_name 
            FROM members m 
            LEFT JOIN centers c ON m.center_id = c.id 
            WHERE m.id = ?
        ";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Get member by member ID
     */
    public function getByMemberId($memberId) {
        $sql = "
            SELECT m.*, c.name as center_name 
            FROM members m 
            LEFT JOIN centers c ON m.center_id = c.id 
            WHERE m.member_id = ?
        ";
        return $this->db->fetchOne($sql, [$memberId]);
    }
    
    /**
     * Get all members with pagination
     */
    public function getAll($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $where[] = 'm.status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['center_id'])) {
            $where[] = 'm.center_id = ?';
            $params[] = $filters['center_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(CONCAT(m.first_name, ' ', m.last_name) LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM members m WHERE {$whereClause}";
        $totalResult = $this->db->fetchOne($countSql, $params);
        $total = $totalResult['total'];
        
        // Get members
        $sql = "
            SELECT m.*, c.name as center_name,
                   CONCAT(m.first_name, ' ', m.last_name) as full_name
            FROM members m 
            LEFT JOIN centers c ON m.center_id = c.id 
            WHERE {$whereClause}
            ORDER BY m.created_at DESC 
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $members = $this->db->fetchAll($sql, $params);
        
        return [
            'members' => $members,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    /**
     * Update member
     */
    public function update($id, $data) {
        try {
            // Remove fields that shouldn't be updated directly
            unset($data['id'], $data['member_id'], $data['created_at']);
            
            // Sanitize data
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $data[$key] = sanitizeInput($value);
                }
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $this->db->update('members', $data, 'id = ?', [$id]);
            
            // Log activity
            logActivity('member_updated', "Member updated: ID {$id}");
            
            return ['success' => true, 'message' => 'Member updated successfully'];
            
        } catch (Exception $e) {
            error_log("Member update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed'];
        }
    }
    
    /**
     * Delete member
     */
    public function delete($id) {
        try {
            $member = $this->getById($id);
            if (!$member) {
                return ['success' => false, 'message' => 'Member not found'];
            }
            
            $this->db->delete('members', 'id = ?', [$id]);
            
            // Log activity
            logActivity('member_deleted', "Member deleted: {$member['member_id']}");
            
            return ['success' => true, 'message' => 'Member deleted successfully'];
            
        } catch (Exception $e) {
            error_log("Member delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed'];
        }
    }
    
    /**
     * Update member status
     */
    public function updateStatus($id, $status) {
        $validStatuses = ['active', 'inactive', 'pending'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }
        
        try {
            $this->db->update('members', ['status' => $status], 'id = ?', [$id]);
            
            // Log activity
            logActivity('member_status_updated', "Member status changed to {$status}: ID {$id}");
            
            return ['success' => true, 'message' => 'Status updated successfully'];
            
        } catch (Exception $e) {
            error_log("Member status update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Status update failed'];
        }
    }
    
    /**
     * Upload member photo
     */
    public function uploadPhoto($memberId, $file) {
        try {
            $member = $this->getById($memberId);
            if (!$member) {
                return ['success' => false, 'message' => 'Member not found'];
            }
            
            // Handle file upload
            $uploadResult = handleFileUpload($file, 'members');
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            
            // Update member photo
            $this->db->update('members', ['photo' => $uploadResult['filename']], 'id = ?', [$memberId]);
            
            // Log activity
            logActivity('member_photo_uploaded', "Photo uploaded for member: {$member['member_id']}");
            
            return [
                'success' => true,
                'message' => 'Photo uploaded successfully',
                'photo_url' => $uploadResult['url']
            ];
            
        } catch (Exception $e) {
            error_log("Member photo upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Photo upload failed'];
        }
    }
    
    /**
     * Get member statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total members
        $stats['total'] = $this->db->count('members');
        
        // Members by status
        $sql = "SELECT status, COUNT(*) as count FROM members GROUP BY status";
        $statusCounts = $this->db->fetchAll($sql);
        foreach ($statusCounts as $row) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // Members by gender
        $sql = "SELECT gender, COUNT(*) as count FROM members GROUP BY gender";
        $genderCounts = $this->db->fetchAll($sql);
        foreach ($genderCounts as $row) {
            $stats['by_gender'][$row['gender']] = $row['count'];
        }
        
        // Recent registrations (last 30 days)
        $stats['recent'] = $this->db->count('members', 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
        
        // Members by center
        $sql = "
            SELECT c.name, COUNT(m.id) as count 
            FROM centers c 
            LEFT JOIN members m ON c.id = m.center_id 
            GROUP BY c.id, c.name
        ";
        $centerCounts = $this->db->fetchAll($sql);
        foreach ($centerCounts as $row) {
            $stats['by_center'][$row['name']] = $row['count'];
        }
        
        return $stats;
    }
    
    /**
     * Check if phone exists
     */
    private function phoneExists($phone) {
        return $this->db->exists('members', 'phone = ?', [$phone]);
    }
    
    /**
     * Check if email exists
     */
    private function emailExists($email) {
        return $this->db->exists('members', 'email = ?', [$email]);
    }
    
    /**
     * Generate unique member ID
     */
    private function generateMemberId() {
        do {
            $id = 'WDB-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->db->exists('members', 'member_id = ?', [$id]));
        
        return $id;
    }
    
    /**
     * Send welcome email
     */
    private function sendWelcomeEmail($memberData) {
        try {
            // Add to email queue
            $emailData = [
                'to_email' => $memberData['email'],
                'to_name' => $memberData['first_name'] . ' ' . $memberData['last_name'],
                'subject' => 'Welcome to Waldaa Duuka Bu\'ootaa',
                'body' => $this->getWelcomeEmailTemplate($memberData),
                'template' => 'welcome',
                'priority' => 'normal'
            ];
            
            $this->db->insert('email_queue', $emailData);
            
        } catch (Exception $e) {
            error_log("Welcome email queue error: " . $e->getMessage());
        }
    }
    
    /**
     * Get welcome email template
     */
    private function getWelcomeEmailTemplate($memberData) {
        return "
        <h2>Baga Nagaan Dhuftan - Welcome!</h2>
        <p>Dear {$memberData['first_name']} {$memberData['last_name']},</p>
        <p>Thank you for registering with Waldaa Duuka Bu'ootaa (WDB).</p>
        <p>Your member ID is: <strong>{$memberData['member_id']}</strong></p>
        <p>Your registration is currently pending approval. You will be notified once it's approved.</p>
        <p>If you have any questions, please contact us at info@wdb.org or +251911234567.</p>
        <p>Blessings,<br>WDB Team</p>
        ";
    }
}
?>