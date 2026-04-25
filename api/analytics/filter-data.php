<?php
/**
 * Filter Data API
 * Provides filter options and data for analytics widgets
 * Requirements: 6.1, 6.2, 6.3, 6.4, 6.5
 * Task: 3.5 Implement FilterPanel component - Backend API support
 */

require_once '../config/database.php';
require_once '../security/SecurityManager.php';
require_once '../handlers/ErrorHandler.php';

class FilterDataAPI {
    private $db;
    private $securityManager;
    private $errorHandler;
    
    public function __construct() {
        $this->db = new Database();
        $this->securityManager = new SecurityManager();
        $this->errorHandler = new ErrorHandler();
        
        // Enable CORS for API access
        $this->enableCORS();
    }
    
    /**
     * Enable CORS headers
     */
    private function enableCORS() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest() {
        try {
            // Authenticate user - check session
            session_start();
            if (!$this->isUserAuthenticated()) {
                throw new Exception('Authentication required', 401);
            }
            
            $action = $_GET['action'] ?? '';
            
            switch ($action) {
                case 'get_filter_options':
                    return $this->getFilterOptions();
                    
                default:
                    throw new Exception('Invalid action', 400);
            }
            
        } catch (Exception $e) {
            return $this->errorHandler->handleException($e);
        }
    }
    
    /**
     * Check if user is authenticated
     */
    private function isUserAuthenticated() {
        // Check if user is logged in via session
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get filter options based on dashboard type
     */
    private function getFilterOptions() {
        $dashboardType = $_GET['dashboard_type'] ?? '';
        $centerIds = [];
        
        // Parse center IDs if provided
        if (!empty($_GET['center_ids'])) {
            $centerIds = json_decode($_GET['center_ids'], true);
            if (!is_array($centerIds)) {
                $centerIds = [];
            }
        }
        
        $filterOptions = [];
        
        // Get available filters based on dashboard type
        $availableFilters = $this->getAvailableFiltersForDashboard($dashboardType);
        
        foreach ($availableFilters as $filterKey) {
            $options = $this->getFilterOptionsForType($filterKey, $centerIds);
            if (!empty($options)) {
                $filterOptions[$filterKey] = $options;
            }
        }
        
        return [
            'success' => true,
            'data' => $filterOptions
        ];
    }
    
    /**
     * Get available filters for dashboard type
     */
    private function getAvailableFiltersForDashboard($dashboardType) {
        $filterMap = [
            'admin_dashboard' => ['center_ids', 'member_status', 'contribution_type'],
            'center_management' => ['member_status', 'contribution_type'],
            'members_management' => ['center_ids', 'member_status', 'gender'],
            'contributions_management' => ['center_ids', 'contribution_type', 'payment_method']
        ];
        
        return $filterMap[$dashboardType] ?? ['center_ids'];
    }
    
    /**
     * Get filter options for specific filter type
     */
    private function getFilterOptionsForType($filterKey, $centerIds = []) {
        switch ($filterKey) {
            case 'center_ids':
                return $this->getCenterOptions($centerIds);
                
            case 'member_status':
                return $this->getMemberStatusOptions();
                
            case 'contribution_type':
                return $this->getContributionTypeOptions();
                
            case 'payment_method':
                return $this->getPaymentMethodOptions();
                
            case 'gender':
                return $this->getGenderOptions();
                
            default:
                return [];
        }
    }
    
    /**
     * Get <center> <wiirtuu> options
     */
    private function getCenterOptions($accessibleCenterIds = []) {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT id, name FROM centers WHERE status = 'active'";
        $params = [];
        $types = '';
        
        // Filter by accessible centers if provided
        if (!empty($accessibleCenterIds)) {
            $placeholders = str_repeat('?,', count($accessibleCenterIds) - 1) . '?';
            $sql .= " AND id IN ($placeholders)";
            $params = $accessibleCenterIds;
            $types = str_repeat('i', count($accessibleCenterIds));
        }
        
        $sql .= " ORDER BY name";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $options = [];
        while ($row = $result->fetch_assoc()) {
            $options[] = [
                'value' => $row['id'],
                'label' => $row['name']
            ];
        }
        
        return $options;
    }
    
    /**
     * Get member status options
     */
    private function getMemberStatusOptions() {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'suspended', 'label' => 'Suspended']
        ];
    }
    
    /**
     * Get contribution type options
     */
    private function getContributionTypeOptions() {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT DISTINCT type FROM contributions WHERE type IS NOT NULL AND type != '' ORDER BY type";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $options = [];
        while ($row = $result->fetch_assoc()) {
            $options[] = [
                'value' => $row['type'],
                'label' => ucfirst($row['type'])
            ];
        }
        
        // Add default types if none found
        if (empty($options)) {
            $options = [
                ['value' => 'tithe', 'label' => 'Tithe'],
                ['value' => 'offering', 'label' => 'Offering'],
                ['value' => 'donation', 'label' => 'Donation'],
                ['value' => 'special', 'label' => 'Special']
            ];
        }
        
        return $options;
    }
    
    /**
     * Get payment method options
     */
    private function getPaymentMethodOptions() {
        $conn = $this->db->getConnection();
        
        $sql = "SELECT DISTINCT payment_method FROM contributions 
                WHERE payment_method IS NOT NULL AND payment_method != '' 
                ORDER BY payment_method";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $options = [];
        while ($row = $result->fetch_assoc()) {
            $options[] = [
                'value' => $row['payment_method'],
                'label' => ucfirst(str_replace('_', ' ', $row['payment_method']))
            ];
        }
        
        // Add default methods if none found
        if (empty($options)) {
            $options = [
                ['value' => 'cash', 'label' => 'Cash'],
                ['value' => 'credit_card', 'label' => 'Credit Card'],
                ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
                ['value' => 'check', 'label' => 'Check'],
                ['value' => 'online', 'label' => 'Online']
            ];
        }
        
        return $options;
    }
    
    /**
     * Get gender options
     */
    private function getGenderOptions() {
        return [
            ['value' => 'male', 'label' => 'Male'],
            ['value' => 'female', 'label' => 'Female'],
            ['value' => 'other', 'label' => 'Other'],
            ['value' => 'prefer_not_to_say', 'label' => 'Prefer not to say']
        ];
    }
}

// Handle the request
try {
    header('Content-Type: application/json');
    
    $api = new FilterDataAPI();
    $response = $api->handleRequest();
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
?>