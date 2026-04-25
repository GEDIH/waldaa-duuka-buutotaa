<?php
/**
 * KPI Data API
 * Provides KPI metrics data for analytics widgets
 * Requirements: 4.1, 4.2, 4.5
 * Task: 2.1 Implement AnalyticsWidgetManager class - KPI Data Support
 */

require_once '../config/database.php';
require_once '../security/SecurityManager.php';
require_once '../handlers/ErrorHandler.php';

class KPIDataAPI {
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
                case 'get_kpi':
                    return $this->getKPIData();
                    
                case 'get_multiple_kpis':
                    return $this->getMultipleKPIs();
                    
                case 'get_kpi_history':
                    return $this->getKPIHistory();
                    
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
     * Get single KPI data
     */
    private function getKPIData() {
        $metric = $_GET['metric'] ?? '';
        $dashboardType = $_GET['dashboard_type'] ?? '';
        
        if (empty($metric)) {
            throw new Exception('Metric is required', 400);
        }
        
        // Get contextual filters
        $filters = $this->parseFilters();
        
        // Get KPI data based on metric type
        $data = $this->fetchKPIByMetric($metric, $dashboardType, $filters);
        
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * Get multiple KPIs at once
     */
    private function getMultipleKPIs() {
        $metrics = $_GET['metrics'] ?? '';
        $dashboardType = $_GET['dashboard_type'] ?? '';
        
        if (empty($metrics)) {
            throw new Exception('Metrics are required', 400);
        }
        
        $metricsList = explode(',', $metrics);
        $filters = $this->parseFilters();
        
        $results = [];
        foreach ($metricsList as $metric) {
            $metric = trim($metric);
            if (!empty($metric)) {
                try {
                    $results[$metric] = $this->fetchKPIByMetric($metric, $dashboardType, $filters);
                } catch (Exception $e) {
                    $results[$metric] = [
                        'error' => $e->getMessage(),
                        'current_value' => null,
                        'previous_value' => null
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'data' => $results
        ];
    }
    
    /**
     * Get KPI historical data
     */
    private function getKPIHistory() {
        $metric = $_GET['metric'] ?? '';
        $period = $_GET['period'] ?? '30_days';
        
        if (empty($metric)) {
            throw new Exception('Metric is required', 400);
        }
        
        $filters = $this->parseFilters();
        $history = $this->fetchKPIHistory($metric, $period, $filters);
        
        return [
            'success' => true,
            'data' => $history
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
            if (!in_array($key, ['action', 'metric', 'dashboard_type', 'center_ids', 'date_range'])) {
                $filters['custom_filters'][$key] = $value;
            }
        }
        
        return $filters;
    }
    
    /**
     * Fetch KPI data by metric type
     */
    private function fetchKPIByMetric($metric, $dashboardType, $filters) {
        switch ($metric) {
            case 'membership.total_members':
                return $this->getTotalMembersKPI($filters);
                
            case 'centers.active_count':
                return $this->getActiveCentersKPI($filters);
                
            case 'contributions.monthly_total':
                return $this->getMonthlyContributionsKPI($filters);
                
            case 'center.member_count':
                return $this->getCenterMemberCountKPI($filters);
                
            case 'center.contribution_total':
                return $this->getCenterContributionTotalKPI($filters);
                
            case 'members.active_count':
                return $this->getActiveMembersKPI($filters);
                
            case 'contributions.average_amount':
                return $this->getAverageContributionKPI($filters);
                
            default:
                throw new Exception("Unknown metric: $metric", 400);
        }
    }
    
    /**
     * Get total members KPI
     */
    private function getTotalMembersKPI($filters) {
        $conn = $this->db->getConnection();
        
        // Current total
        $currentSql = "SELECT COUNT(*) as total FROM members WHERE status = 'active'";
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $currentSql .= " AND center_id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $stmt = $conn->prepare($currentSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $currentResult = $stmt->get_result()->fetch_assoc();
        $currentValue = (int)$currentResult['total'];
        
        // Previous month total
        $previousSql = str_replace('WHERE', 'WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND', $currentSql);
        $stmt = $conn->prepare($previousSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $previousResult = $stmt->get_result()->fetch_assoc();
        $previousValue = (int)$previousResult['total'];
        
        return [
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'comparison' => [
                'period' => 'Previous Month',
                'value' => $previousValue
            ]
        ];
    }
    
    /**
     * Get active centers KPI
     */
    private function getActiveCentersKPI($filters) {
        $conn = $this->db->getConnection();
        
        // Current active centers
        $currentSql = "SELECT COUNT(*) as total FROM centers WHERE status = 'active'";
        $stmt = $conn->prepare($currentSql);
        $stmt->execute();
        $currentResult = $stmt->get_result()->fetch_assoc();
        $currentValue = (int)$currentResult['total'];
        
        // Previous month (centers that were active last month)
        $previousSql = "
            SELECT COUNT(*) as total FROM centers 
            WHERE status = 'active' AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ";
        $stmt = $conn->prepare($previousSql);
        $stmt->execute();
        $previousResult = $stmt->get_result()->fetch_assoc();
        $previousValue = (int)$previousResult['total'];
        
        return [
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'comparison' => [
                'period' => 'Previous Month',
                'value' => $previousValue
            ]
        ];
    }
    
    /**
     * Get monthly contributions KPI
     */
    private function getMonthlyContributionsKPI($filters) {
        $conn = $this->db->getConnection();
        
        // Current month contributions
        $currentSql = "
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM contributions 
            WHERE YEAR(contribution_date) = YEAR(NOW()) 
            AND MONTH(contribution_date) = MONTH(NOW())
            AND status = 'completed'
        ";
        
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $currentSql .= " AND center_id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $stmt = $conn->prepare($currentSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $currentResult = $stmt->get_result()->fetch_assoc();
        $currentValue = (float)$currentResult['total'];
        
        // Previous month contributions
        $previousSql = str_replace(
            'MONTH(contribution_date) = MONTH(NOW())',
            'MONTH(contribution_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))',
            $currentSql
        );
        $previousSql = str_replace(
            'YEAR(contribution_date) = YEAR(NOW())',
            'YEAR(contribution_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))',
            $previousSql
        );
        
        $stmt = $conn->prepare($previousSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $previousResult = $stmt->get_result()->fetch_assoc();
        $previousValue = (float)$previousResult['total'];
        
        return [
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'comparison' => [
                'period' => 'Previous Month',
                'value' => $previousValue
            ]
        ];
    }
    
    /**
     * Get center member count KPI
     */
    private function getCenterMemberCountKPI($filters) {
        $conn = $this->db->getConnection();
        
        if (empty($filters['center_ids'])) {
            throw new Exception('Center ID is required for center-specific metrics', 400);
        }
        
        $centerId = $filters['center_ids'][0]; // Use first center ID
        
        // Current member count for center
        $currentSql = "
            SELECT COUNT(*) as total 
            FROM members 
            WHERE center_id = ? AND status = 'active'
        ";
        
        $stmt = $conn->prepare($currentSql);
        $stmt->bind_param('i', $centerId);
        $stmt->execute();
        $currentResult = $stmt->get_result()->fetch_assoc();
        $currentValue = (int)$currentResult['total'];
        
        // Previous month member count
        $previousSql = "
            SELECT COUNT(*) as total 
            FROM members 
            WHERE center_id = ? AND status = 'active' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ";
        
        $stmt = $conn->prepare($previousSql);
        $stmt->bind_param('i', $centerId);
        $stmt->execute();
        $previousResult = $stmt->get_result()->fetch_assoc();
        $previousValue = (int)$previousResult['total'];
        
        return [
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'comparison' => [
                'period' => 'Previous Month',
                'value' => $previousValue
            ]
        ];
    }
    
    /**
     * Get center contribution total KPI
     */
    private function getCenterContributionTotalKPI($filters) {
        $conn = $this->db->getConnection();
        
        if (empty($filters['center_ids'])) {
            throw new Exception('Center ID is required for center-specific metrics', 400);
        }
        
        $centerId = $filters['center_ids'][0]; // Use first center ID
        
        // Current month contributions for center
        $currentSql = "
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM contributions 
            WHERE center_id = ? 
            AND YEAR(contribution_date) = YEAR(NOW()) 
            AND MONTH(contribution_date) = MONTH(NOW())
            AND status = 'completed'
        ";
        
        $stmt = $conn->prepare($currentSql);
        $stmt->bind_param('i', $centerId);
        $stmt->execute();
        $currentResult = $stmt->get_result()->fetch_assoc();
        $currentValue = (float)$currentResult['total'];
        
        // Previous month contributions
        $previousSql = "
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM contributions 
            WHERE center_id = ? 
            AND YEAR(contribution_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
            AND MONTH(contribution_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
            AND status = 'completed'
        ";
        
        $stmt = $conn->prepare($previousSql);
        $stmt->bind_param('i', $centerId);
        $stmt->execute();
        $previousResult = $stmt->get_result()->fetch_assoc();
        $previousValue = (float)$previousResult['total'];
        
        return [
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'comparison' => [
                'period' => 'Previous Month',
                'value' => $previousValue
            ]
        ];
    }
    
    /**
     * Get active members KPI
     */
    private function getActiveMembersKPI($filters) {
        $conn = $this->db->getConnection();
        
        // Current active members
        $currentSql = "
            SELECT COUNT(*) as total 
            FROM members 
            WHERE status = 'active' 
            AND last_activity_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $currentSql .= " AND center_id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $stmt = $conn->prepare($currentSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $currentResult = $stmt->get_result()->fetch_assoc();
        $currentValue = (int)$currentResult['total'];
        
        // Previous month active members
        $previousSql = str_replace(
            'last_activity_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            'last_activity_date >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND last_activity_date < DATE_SUB(NOW(), INTERVAL 30 DAY)',
            $currentSql
        );
        
        $stmt = $conn->prepare($previousSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $previousResult = $stmt->get_result()->fetch_assoc();
        $previousValue = (int)$previousResult['total'];
        
        return [
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'comparison' => [
                'period' => 'Previous Period',
                'value' => $previousValue
            ]
        ];
    }
    
    /**
     * Get average contribution KPI
     */
    private function getAverageContributionKPI($filters) {
        $conn = $this->db->getConnection();
        
        // Current month average contribution
        $currentSql = "
            SELECT COALESCE(AVG(amount), 0) as average 
            FROM contributions 
            WHERE YEAR(contribution_date) = YEAR(NOW()) 
            AND MONTH(contribution_date) = MONTH(NOW())
            AND status = 'completed'
        ";
        
        $params = [];
        $types = '';
        
        // Apply center filter if specified
        if (!empty($filters['center_ids'])) {
            $placeholders = str_repeat('?,', count($filters['center_ids']) - 1) . '?';
            $currentSql .= " AND center_id IN ($placeholders)";
            $params = array_merge($params, $filters['center_ids']);
            $types .= str_repeat('i', count($filters['center_ids']));
        }
        
        $stmt = $conn->prepare($currentSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $currentResult = $stmt->get_result()->fetch_assoc();
        $currentValue = (float)$currentResult['average'];
        
        // Previous month average
        $previousSql = str_replace(
            'MONTH(contribution_date) = MONTH(NOW())',
            'MONTH(contribution_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))',
            $currentSql
        );
        $previousSql = str_replace(
            'YEAR(contribution_date) = YEAR(NOW())',
            'YEAR(contribution_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))',
            $previousSql
        );
        
        $stmt = $conn->prepare($previousSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $previousResult = $stmt->get_result()->fetch_assoc();
        $previousValue = (float)$previousResult['average'];
        
        return [
            'current_value' => $currentValue,
            'previous_value' => $previousValue,
            'comparison' => [
                'period' => 'Previous Month',
                'value' => $previousValue
            ]
        ];
    }
    
    /**
     * Fetch KPI historical data
     */
    private function fetchKPIHistory($metric, $period, $filters) {
        $conn = $this->db->getConnection();
        
        // Determine date range based on period
        $dateCondition = $this->getDateConditionForPeriod($period);
        
        switch ($metric) {
            case 'membership.total_members':
                return $this->getMembershipHistory($dateCondition, $filters);
                
            case 'contributions.monthly_total':
                return $this->getContributionsHistory($dateCondition, $filters);
                
            default:
                throw new Exception("Historical data not available for metric: $metric", 400);
        }
    }
    
    /**
     * Get date condition for period
     */
    private function getDateConditionForPeriod($period) {
        switch ($period) {
            case '7_days':
                return 'DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
            case '30_days':
                return 'DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
            case '90_days':
                return 'DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)';
            case '1_year':
                return 'DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)';
            default:
                return 'DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        }
    }
    
    /**
     * Get membership history
     */
    private function getMembershipHistory($dateCondition, $filters) {
        $conn = $this->db->getConnection();
        
        $sql = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as daily_count,
                SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) as cumulative_count
            FROM members 
            WHERE $dateCondition AND status = 'active'
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
        
        $sql .= " GROUP BY DATE(created_at) ORDER BY DATE(created_at)";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'date' => $row['date'],
                'value' => (int)$row['cumulative_count'],
                'daily_change' => (int)$row['daily_count']
            ];
        }
        
        return $history;
    }
    
    /**
     * Get contributions history
     */
    private function getContributionsHistory($dateCondition, $filters) {
        $conn = $this->db->getConnection();
        
        $sql = "
            SELECT 
                DATE(contribution_date) as date,
                SUM(amount) as daily_total,
                COUNT(*) as daily_count,
                AVG(amount) as daily_average
            FROM contributions 
            WHERE $dateCondition AND status = 'completed'
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
        
        $sql .= " GROUP BY DATE(contribution_date) ORDER BY DATE(contribution_date)";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'date' => $row['date'],
                'value' => (float)$row['daily_total'],
                'count' => (int)$row['daily_count'],
                'average' => (float)$row['daily_average']
            ];
        }
        
        return $history;
    }
}

// Handle the request
try {
    header('Content-Type: application/json');
    
    $api = new KPIDataAPI();
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