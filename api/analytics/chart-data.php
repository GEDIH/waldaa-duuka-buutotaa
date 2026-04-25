<?php
/**
 * Chart Data API
 * Provides chart data for analytics widgets
 * Requirements: 5.1, 5.2, 5.3, 5.5
 * Task: 3.3 Implement ChartWidget component - Backend API support
 */

require_once '../config/database.php';
require_once '../security/SecurityManager.php';
require_once '../handlers/ErrorHandler.php';

class ChartDataAPI {
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
                case 'get_chart_data':
                    return $this->getChartData();
                    
                case 'get_chart_types':
                    return $this->getChartTypes();
                    
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
     * Get chart data based on metric and type
     */
    private function getChartData() {
        $metric = $_GET['metric'] ?? '';
        $chartType = $_GET['chart_type'] ?? 'line';
        $dashboardType = $_GET['dashboard_type'] ?? '';
        
        if (empty($metric)) {
            throw new Exception('Metric is required', 400);
        }
        
        // Get contextual filters
        $filters = $this->parseFilters();
        
        // Get chart data based on metric type
        $data = $this->fetchChartByMetric($metric, $chartType, $dashboardType, $filters);
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * Get available chart types
     */
    private function getChartTypes() {
        return [
            'success' => true,
            'data' => [
                'line' => 'Line Chart',
                'bar' => 'Bar Chart', 
                'pie' => 'Pie Chart',
                'doughnut' => 'Doughnut Chart',
                'area' => 'Area Chart',
                'scatter' => 'Scatter Plot',
                'radar' => 'Radar Chart'
            ]
        ];
    }
    
    /**
     * Parse request filters
     */
    private function parseFilters() {
        $filters = [
            'user_id' => $this->securityManager->getCurrentUserId(),
            'user_role' => $this->securityManager->getCurrentUserRole(),
            'center_ids' => [],
            'date_range' => null,
            'custom_filters' => []
        ];
        
        // Parse center IDs
        if (!empty($_GET['center_ids'])) {
            $centerIds = json_decode($_GET['center_ids'], true);
            if (is_array($centerIds)) {
                $filters['center_ids'] = $centerIds;
            }
        }
        
        // Parse date range
        if (!empty($_GET['date_range'])) {
            $dateRange = json_decode($_GET['date_range'], true);
            if (is_array($dateRange)) {
                $filters['date_range'] = $dateRange;
            }
        }
        
        // Parse custom filters
        foreach ($_GET as $key => $value) {
            if (!in_array($key, ['action', 'metric', 'chart_type', 'dashboard_type', 'center_ids', 'date_range'])) {
                $filters['custom_filters'][$key] = $value;
            }
        }
        
        return $filters;
    }
    
    /**
     * Fetch chart data by metric type
     */
    private function fetchChartByMetric($metric, $chartType, $dashboardType, $filters) {
        switch ($metric) {
            case 'membership.trend':
                return $this->getMembershipTrendChart($chartType, $filters);
                
            case 'contributions.monthly_trend':
                return $this->getContributionsTrendChart($chartType, $filters);
                
            case 'members.demographics':
                return $this->getMemberDemographicsChart($chartType, $filters);
                
            case 'contributions.payment_methods':
                return $this->getPaymentMethodsChart($chartType, $filters);
                
            case 'centers.performance':
                return $this->getCenterPerformanceChart($chartType, $filters);
                
            case 'engagement.attendance':
                return $this->getAttendanceChart($chartType, $filters);
                
            default:
                throw new Exception("Unknown metric: $metric", 400);
        }
    }
    
    /**
     * Get membership trend chart data
     */
    private function getMembershipTrendChart($chartType, $filters) {
        $conn = $this->db->getConnection();
        
        // Get last 12 months of membership data
        $sql = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as new_members,
                SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m')) as total_members
            FROM members 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND status = 'active'
        ";
        
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $sql .= " AND center_id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $sql .= " GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $labels = [];
        $newMembersData = [];
        $totalMembersData = [];
        
        while ($row = $result->fetch_assoc()) {
            $labels[] = date('M Y', strtotime($row['month'] . '-01'));
            $newMembersData[] = (int)$row['new_members'];
            $totalMembersData[] = (int)$row['total_members'];
        }
        
        $datasets = [
            [
                'label' => 'New Members',
                'data' => $newMembersData,
                'type' => $chartType === 'line' ? 'bar' : $chartType
            ]
        ];
        
        if ($chartType === 'line') {
            $datasets[] = [
                'label' => 'Total Members',
                'data' => $totalMembersData,
                'type' => 'line',
                'yAxisID' => 'y1'
            ];
        }
        
        return [
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }
    
    /**
     * Get contributions trend chart data
     */
    private function getContributionsTrendChart($chartType, $filters) {
        $conn = $this->db->getConnection();
        
        $sql = "
            SELECT 
                DATE_FORMAT(contribution_date, '%Y-%m') as month,
                SUM(amount) as total_amount,
                COUNT(*) as contribution_count,
                AVG(amount) as average_amount
            FROM contributions 
            WHERE contribution_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND status = 'completed'
        ";
        
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $sql .= " AND center_id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $sql .= " GROUP BY DATE_FORMAT(contribution_date, '%Y-%m') ORDER BY month";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $labels = [];
        $totalData = [];
        $countData = [];
        $averageData = [];
        
        while ($row = $result->fetch_assoc()) {
            $labels[] = date('M Y', strtotime($row['month'] . '-01'));
            $totalData[] = (float)$row['total_amount'];
            $countData[] = (int)$row['contribution_count'];
            $averageData[] = (float)$row['average_amount'];
        }
        
        $datasets = [
            [
                'label' => 'Total Contributions',
                'data' => $totalData
            ]
        ];
        
        if ($chartType === 'line') {
            $datasets[] = [
                'label' => 'Number of Contributions',
                'data' => $countData,
                'yAxisID' => 'y1'
            ];
        }
        
        return [
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }
    
    /**
     * Get member demographics chart data
     */
    private function getMemberDemographicsChart($chartType, $filters) {
        $conn = $this->db->getConnection();
        
        $sql = "
            SELECT 
                gender,
                COUNT(*) as count
            FROM members 
            WHERE status = 'active'
        ";
        
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $sql .= " AND center_id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $sql .= " GROUP BY gender ORDER BY count DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $labels = [];
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $labels[] = ucfirst($row['gender'] ?: 'Unknown');
            $data[] = (int)$row['count'];
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Members by Gender',
                    'data' => $data
                ]
            ]
        ];
    }
    
    /**
     * Get payment methods chart data
     */
    private function getPaymentMethodsChart($chartType, $filters) {
        $conn = $this->db->getConnection();
        
        $sql = "
            SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(amount) as total_amount
            FROM contributions 
            WHERE status = 'completed'
            AND contribution_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ";
        
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $sql .= " AND center_id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $sql .= " GROUP BY payment_method ORDER BY total_amount DESC";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $labels = [];
        $countData = [];
        $amountData = [];
        
        while ($row = $result->fetch_assoc()) {
            $labels[] = ucfirst($row['payment_method'] ?: 'Unknown');
            $countData[] = (int)$row['count'];
            $amountData[] = (float)$row['total_amount'];
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Amount',
                    'data' => $amountData
                ]
            ]
        ];
    }
    
    /**
     * Get center performance chart data
     */
    private function getCenterPerformanceChart($chartType, $filters) {
        $conn = $this->db->getConnection();
        
        $sql = "
            SELECT 
                c.name as center_name,
                COUNT(DISTINCT m.id) as member_count,
                COALESCE(SUM(cont.amount), 0) as total_contributions
            FROM centers c
            LEFT JOIN members m ON c.id = m.center_id AND m.status = 'active'
            LEFT JOIN contributions cont ON c.id = cont.center_id 
                AND cont.status = 'completed' 
                AND cont.contribution_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            WHERE c.status = 'active'
        ";
        
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $sql .= " AND c.id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $sql .= " GROUP BY c.id, c.name ORDER BY total_contributions DESC LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $labels = [];
        $memberData = [];
        $contributionData = [];
        
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['center_name'];
            $memberData[] = (int)$row['member_count'];
            $contributionData[] = (float)$row['total_contributions'];
        }
        
        $datasets = [
            [
                'label' => 'Total Contributions',
                'data' => $contributionData
            ]
        ];
        
        if ($chartType === 'line' || $chartType === 'bar') {
            $datasets[] = [
                'label' => 'Member Count',
                'data' => $memberData,
                'yAxisID' => 'y1'
            ];
        }
        
        return [
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }
    
    /**
     * Get attendance chart data
     */
    private function getAttendanceChart($chartType, $filters) {
        // This would require an attendance tracking system
        // For now, return sample data
        $labels = [];
        $attendanceData = [];
        
        // Generate last 8 weeks of sample data
        for ($i = 7; $i >= 0; $i--) {
            $date = date('M j', strtotime("-$i weeks"));
            $labels[] = $date;
            $attendanceData[] = rand(60, 95); // Sample attendance percentage
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Attendance Rate (%)',
                    'data' => $attendanceData
                ]
            ]
        ];
    }
}

// Handle the request
try {
    header('Content-Type: application/json');
    
    $api = new ChartDataAPI();
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