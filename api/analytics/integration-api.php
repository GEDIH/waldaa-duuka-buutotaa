<?php
/**
 * Analytics Integration API
 * Main API class for widget-based analytics integration across all dashboards
 * Requirements: 2.1, 2.2, 2.3, 3.1, 8.4
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Dashboard-Context');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AnalyticsSecurityManager.php';
require_once __DIR__ . '/secure-analytics.php';

class AnalyticsIntegrationAPI {
    private $db;
    private $securityManager;
    private $cacheManager;
    private $contextService;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->securityManager = new AnalyticsSecurityManager($this->db);
        $this->cacheManager = new AnalyticsCacheManager($this->db);
        $this->contextService = new ContextService($this->db);
    }
    
    /**
     * Main request handler
     */
    public function handleRequest() {
        try {
            $requestMethod = $_SERVER['REQUEST_METHOD'];
            $endpoint = $_GET['endpoint'] ?? '';
            $context = $this->parseContext();
            
            // Authenticate request
            $authResult = $this->authenticateRequest($context);
            if (!$authResult['success']) {
                return $this->sendResponse($authResult, $authResult['code']);
            }
            
            // Route to appropriate handler
            switch ($endpoint) {
                case 'widget-data':
                    return $this->handleWidgetDataRequest($context, $authResult['user']);
                    
                case 'stream':
                    return $this->handleStreamRequest($context, $authResult['user']);
                    
                case 'kpi-data':
                    return $this->handleKPIDataRequest($context, $authResult['user']);
                    
                case 'chart-data':
                    return $this->handleChartDataRequest($context, $authResult['user']);
                    
                case 'filter-data':
                    return $this->handleFilterDataRequest($context, $authResult['user']);
                    
                case 'export-widget':
                    return $this->handleWidgetExportRequest($context, $authResult['user']);
                    
                case 'performance-metrics':
                    return $this->handlePerformanceMetricsRequest($context, $authResult['user']);
                    
                default:
                    throw new Exception('Invalid endpoint');
            }
            
        } catch (Exception $e) {
            error_log("Analytics Integration API Error: " . $e->getMessage());
            return $this->sendResponse([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Parse dashboard context from request
     */
    private function parseContext() {
        return [
            'dashboard_type' => $_GET['dashboard_type'] ?? $_SERVER['HTTP_X_DASHBOARD_CONTEXT'] ?? 'admin_main',
            'user_id' => $_SESSION['user_id'] ?? null,
            'center_ids' => explode(',', $_GET['center_ids'] ?? ''),
            'filters' => json_decode($_GET['filters'] ?? '{}', true),
            'widget_id' => $_GET['widget_id'] ?? null,
            'sensitivity_level' => $_GET['sensitivity'] ?? 'public'
        ];
    }
    
    /**
     * Authenticate request with context awareness
     */
    private function authenticateRequest($context) {
        $viewType = $this->mapDashboardToViewType($context['dashboard_type']);
        return $this->securityManager->authenticateAnalyticsAccess($viewType, $context['sensitivity_level']);
    }
    
    /**
     * Handle widget data requests with context filtering
     * Requirements: 2.1, 2.2, 2.3
     */
    public function handleWidgetDataRequest($context, $user) {
        $widgetType = $_GET['widget_type'] ?? 'kpi_card';
        $widgetConfig = $_GET['config'] ?? '{}';
        $config = json_decode($widgetConfig, true);
        
        // Check cache first
        $cacheKey = $this->generateCacheKey('widget_data', $context, $config);
        $cachedData = $this->cacheManager->get($cacheKey);
        
        if ($cachedData && !$this->isCacheExpired($cachedData)) {
            return $this->sendResponse([
                'success' => true,
                'data' => $cachedData['data'],
                'cached' => true,
                'cache_timestamp' => $cachedData['timestamp']
            ]);
        }
        
        // Apply contextual filtering
        $contextualFilters = $this->contextService->getContextualFilters($context['dashboard_type'], $user['id']);
        $mergedFilters = array_merge($context['filters'], $contextualFilters);
        
        // Get widget data based on type and context
        switch ($widgetType) {
            case 'kpi_card':
                $data = $this->getKPIWidgetData($context, $config, $mergedFilters, $user);
                break;
                
            case 'chart':
                $data = $this->getChartWidgetData($context, $config, $mergedFilters, $user);
                break;
                
            case 'filter_panel':
                $data = $this->getFilterPanelData($context, $config, $mergedFilters, $user);
                break;
                
            case 'export_button':
                $data = $this->getExportButtonData($context, $config, $mergedFilters, $user);
                break;
                
            default:
                throw new Exception('Invalid widget type');
        }
        
        // Cache the result
        $this->cacheManager->set($cacheKey, $data, $this->getCacheTTL($widgetType));
        
        // Log access
        $this->securityManager->logAnalyticsAccess('WIDGET_VIEW', $widgetType, [
            'dashboard_type' => $context['dashboard_type'],
            'widget_id' => $context['widget_id'],
            'sensitivity' => $context['sensitivity_level']
        ]);
        
        return $this->sendResponse([
            'success' => true,
            'data' => $data,
            'cached' => false,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Get KPI widget data with dashboard context
     * Requirements: 2.1, 2.2, 2.3
     */
    private function getKPIWidgetData($context, $config, $filters, $user) {
        $metric = $config['metric'] ?? 'total_members';
        $dashboardType = $context['dashboard_type'];
        
        // Build contextual query based on dashboard type
        switch ($dashboardType) {
            case 'center_management':
                return $this->getCenterManagementKPIs($metric, $filters, $user);
                
            case 'members_management':
                return $this->getMembersManagementKPIs($metric, $filters, $user);
                
            case 'contributions_management':
                return $this->getContributionsManagementKPIs($metric, $filters, $user);
                
            case 'admin_main':
            default:
                return $this->getAdminDashboardKPIs($metric, $filters, $user);
        }
    }
    
    /**
     * Get center-specific analytics and member statistics
     * Requirements: 2.1
     */
    private function getCenterManagementKPIs($metric, $filters, $user) {
        $centerFilter = $this->buildCenterFilter($filters, $user);
        $dateFilter = $this->buildDateFilter($filters);
        
        switch ($metric) {
            case 'center_member_count':
                $stmt = $this->db->prepare("
                    SELECT 
                        c.name as center_name,
                        COUNT(m.id) as member_count,
                        COUNT(CASE WHEN m.membership_status = 'active' THEN 1 END) as active_members,
                        COUNT(CASE WHEN m.registration_date >= CURDATE() - INTERVAL 30 DAY THEN 1 END) as new_members_30d
                    FROM centers c
                    LEFT JOIN members m ON c.id = m.center_id {$dateFilter}
                    WHERE c.is_active = 1 {$centerFilter}
                    GROUP BY c.id, c.name
                    ORDER BY member_count DESC
                ");
                break;
                
            case 'center_growth_rate':
                $stmt = $this->db->prepare("
                    SELECT 
                        c.name as center_name,
                        COUNT(CASE WHEN m.registration_date >= CURDATE() - INTERVAL 30 DAY THEN 1 END) as new_members,
                        COUNT(CASE WHEN m.registration_date >= CURDATE() - INTERVAL 60 DAY 
                                   AND m.registration_date < CURDATE() - INTERVAL 30 DAY THEN 1 END) as prev_month_members,
                        ROUND(
                            (COUNT(CASE WHEN m.registration_date >= CURDATE() - INTERVAL 30 DAY THEN 1 END) - 
                             COUNT(CASE WHEN m.registration_date >= CURDATE() - INTERVAL 60 DAY 
                                        AND m.registration_date < CURDATE() - INTERVAL 30 DAY THEN 1 END)) / 
                            NULLIF(COUNT(CASE WHEN m.registration_date >= CURDATE() - INTERVAL 60 DAY 
                                              AND m.registration_date < CURDATE() - INTERVAL 30 DAY THEN 1 END), 0) * 100, 2
                        ) as growth_rate
                    FROM centers c
                    LEFT JOIN members m ON c.id = m.center_id
                    WHERE c.is_active = 1 {$centerFilter}
                    GROUP BY c.id, c.name
                ");
                break;
                
            case 'center_performance_score':
                $stmt = $this->db->prepare("
                    SELECT 
                        c.name as center_name,
                        COUNT(m.id) as total_members,
                        COUNT(CASE WHEN m.membership_status = 'active' THEN 1 END) as active_members,
                        COUNT(CASE WHEN m.payment_status = 'paid' THEN 1 END) as paid_members,
                        ROUND(
                            (COUNT(CASE WHEN m.membership_status = 'active' THEN 1 END) * 0.4 +
                             COUNT(CASE WHEN m.payment_status = 'paid' THEN 1 END) * 0.6) / 
                            NULLIF(COUNT(m.id), 0) * 100, 1
                        ) as performance_score
                    FROM centers c
                    LEFT JOIN members m ON c.id = m.center_id {$dateFilter}
                    WHERE c.is_active = 1 {$centerFilter}
                    GROUP BY c.id, c.name
                    ORDER BY performance_score DESC
                ");
                break;
                
            default:
                throw new Exception('Invalid center management metric');
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return [
            'metric' => $metric,
            'dashboard_context' => 'center_management',
            'data' => $data,
            'summary' => $this->calculateKPISummary($data, $metric)
        ];
    }
    
    /**
     * Get member demographics, status trends, and engagement metrics
     * Requirements: 2.2
     */
    private function getMembersManagementKPIs($metric, $filters, $user) {
        $centerFilter = $this->buildCenterFilter($filters, $user);
        $dateFilter = $this->buildDateFilter($filters);
        
        switch ($metric) {
            case 'member_demographics':
                $stmt = $this->db->prepare("
                    SELECT 
                        gender,
                        COUNT(*) as count,
                        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members m2 WHERE 1=1 {$centerFilter}), 1) as percentage,
                        AVG(YEAR(CURDATE()) - YEAR(date_of_birth)) as avg_age
                    FROM members m
                    WHERE 1=1 {$centerFilter} {$dateFilter}
                    GROUP BY gender
                ");
                break;
                
            case 'membership_status_trends':
                $stmt = $this->db->prepare("
                    SELECT 
                        membership_status,
                        COUNT(*) as current_count,
                        COUNT(CASE WHEN registration_date >= CURDATE() - INTERVAL 30 DAY THEN 1 END) as new_30d,
                        COUNT(CASE WHEN last_activity_date >= CURDATE() - INTERVAL 7 DAY THEN 1 END) as active_7d
                    FROM members m
                    WHERE 1=1 {$centerFilter} {$dateFilter}
                    GROUP BY membership_status
                ");
                break;
                
            case 'engagement_metrics':
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(CASE WHEN last_activity_date >= CURDATE() - INTERVAL 7 DAY THEN 1 END) as active_weekly,
                        COUNT(CASE WHEN last_activity_date >= CURDATE() - INTERVAL 30 DAY THEN 1 END) as active_monthly,
                        COUNT(CASE WHEN last_activity_date < CURDATE() - INTERVAL 90 DAY THEN 1 END) as inactive_90d,
                        AVG(DATEDIFF(CURDATE(), last_activity_date)) as avg_days_since_activity,
                        COUNT(CASE WHEN payment_status = 'paid' AND last_payment_date >= CURDATE() - INTERVAL 30 DAY THEN 1 END) as recent_contributors
                    FROM members m
                    WHERE 1=1 {$centerFilter} {$dateFilter}
                ");
                break;
                
            default:
                throw new Exception('Invalid members management metric');
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return [
            'metric' => $metric,
            'dashboard_context' => 'members_management',
            'data' => $data,
            'summary' => $this->calculateKPISummary($data, $metric)
        ];
    }
    
    /**
     * Get financial analytics, contribution trends, and payment statistics
     * Requirements: 2.3
     */
    private function getContributionsManagementKPIs($metric, $filters, $user) {
        $centerFilter = $this->buildCenterFilter($filters, $user);
        $dateFilter = $this->buildDateFilter($filters, 'c.created_at');
        
        switch ($metric) {
            case 'financial_analytics':
                $stmt = $this->db->prepare("
                    SELECT 
                        SUM(CASE WHEN c.payment_status = 'paid' THEN c.amount ELSE 0 END) as total_revenue,
                        SUM(CASE WHEN c.payment_status = 'unpaid' THEN c.amount ELSE 0 END) as pending_revenue,
                        COUNT(CASE WHEN c.payment_status = 'paid' THEN 1 END) as paid_contributions,
                        COUNT(CASE WHEN c.payment_status = 'unpaid' THEN 1 END) as unpaid_contributions,
                        AVG(CASE WHEN c.payment_status = 'paid' THEN c.amount END) as avg_contribution,
                        SUM(CASE WHEN c.payment_status = 'paid' AND c.created_at >= CURDATE() - INTERVAL 30 DAY THEN c.amount ELSE 0 END) as revenue_30d
                    FROM contributions c
                    JOIN members m ON c.member_id = m.id
                    WHERE 1=1 {$centerFilter} {$dateFilter}
                ");
                break;
                
            case 'contribution_trends':
                $stmt = $this->db->prepare("
                    SELECT 
                        DATE_FORMAT(c.created_at, '%Y-%m') as month,
                        SUM(CASE WHEN c.payment_status = 'paid' THEN c.amount ELSE 0 END) as monthly_revenue,
                        COUNT(CASE WHEN c.payment_status = 'paid' THEN 1 END) as monthly_contributions,
                        COUNT(DISTINCT c.member_id) as unique_contributors,
                        AVG(c.amount) as avg_monthly_contribution
                    FROM contributions c
                    JOIN members m ON c.member_id = m.id
                    WHERE c.created_at >= CURDATE() - INTERVAL 12 MONTH {$centerFilter} {$dateFilter}
                    GROUP BY DATE_FORMAT(c.created_at, '%Y-%m')
                    ORDER BY month DESC
                ");
                break;
                
            case 'payment_statistics':
                $stmt = $this->db->prepare("
                    SELECT 
                        c.payment_method,
                        COUNT(*) as transaction_count,
                        SUM(CASE WHEN c.payment_status = 'paid' THEN c.amount ELSE 0 END) as total_amount,
                        AVG(CASE WHEN c.payment_status = 'paid' THEN c.amount END) as avg_amount,
                        COUNT(CASE WHEN c.payment_status = 'paid' THEN 1 END) as successful_payments,
                        COUNT(CASE WHEN c.payment_status = 'failed' THEN 1 END) as failed_payments,
                        ROUND(COUNT(CASE WHEN c.payment_status = 'paid' THEN 1 END) * 100.0 / COUNT(*), 1) as success_rate
                    FROM contributions c
                    JOIN members m ON c.member_id = m.id
                    WHERE 1=1 {$centerFilter} {$dateFilter}
                    GROUP BY c.payment_method
                ");
                break;
                
            default:
                throw new Exception('Invalid contributions management metric');
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return [
            'metric' => $metric,
            'dashboard_context' => 'contributions_management',
            'data' => $data,
            'summary' => $this->calculateKPISummary($data, $metric)
        ];
    }
    
    /**
     * Handle real-time streaming endpoints with SSE support
     * Requirements: 3.1
     */
    public function handleStreamRequest($context, $user) {
        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        // Register this connection for real-time updates
        $subscriptionId = $this->registerStreamSubscription($context, $user);
        
        // Send initial connection confirmation
        $this->sendSSEEvent('connected', [
            'subscription_id' => $subscriptionId,
            'dashboard_type' => $context['dashboard_type'],
            'timestamp' => date('c')
        ]);
        
        // Keep connection alive and send updates
        $lastHeartbeat = time();
        while (true) {
            // Check for new updates
            $updates = $this->getRealtimeUpdates($subscriptionId, $context);
            
            foreach ($updates as $update) {
                $this->sendSSEEvent('update', $update);
            }
            
            // Send heartbeat every 30 seconds
            if (time() - $lastHeartbeat > 30) {
                $this->sendSSEEvent('heartbeat', ['timestamp' => date('c')]);
                $lastHeartbeat = time();
            }
            
            // Check if connection is still alive
            if (connection_aborted()) {
                $this->unregisterStreamSubscription($subscriptionId);
                break;
            }
            
            sleep(2); // Check for updates every 2 seconds
        }
    }
    
    /**
     * Register real-time subscription
     */
    private function registerStreamSubscription($context, $user) {
        $subscriptionId = uniqid('sub_', true);
        
        $stmt = $this->db->prepare("
            INSERT INTO realtime_subscriptions 
            (session_id, user_id, dashboard_type, context_filters, subscribed_widgets, last_heartbeat) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $contextFilters = json_encode($context['filters']);
        $subscribedWidgets = json_encode(['all']); // Subscribe to all widgets by default
        
        $stmt->bind_param("sisss", 
            $subscriptionId, 
            $user['id'], 
            $context['dashboard_type'], 
            $contextFilters, 
            $subscribedWidgets
        );
        
        $stmt->execute();
        
        return $subscriptionId;
    }
    
    /**
     * Get real-time updates for subscription
     */
    private function getRealtimeUpdates($subscriptionId, $context) {
        // Check for data changes that affect this subscription
        $stmt = $this->db->prepare("
            SELECT * FROM analytics_updates 
            WHERE target_dashboard_type = ? 
            AND created_at > (
                SELECT last_heartbeat FROM realtime_subscriptions 
                WHERE session_id = ?
            )
            ORDER BY created_at ASC
        ");
        
        $stmt->bind_param("ss", $context['dashboard_type'], $subscriptionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $updates = [];
        while ($row = $result->fetch_assoc()) {
            $updates[] = [
                'type' => $row['update_type'],
                'widget_id' => $row['widget_id'],
                'data' => json_decode($row['update_data'], true),
                'timestamp' => $row['created_at']
            ];
        }
        
        // Update last heartbeat
        $stmt = $this->db->prepare("
            UPDATE realtime_subscriptions 
            SET last_heartbeat = NOW() 
            WHERE session_id = ?
        ");
        $stmt->bind_param("s", $subscriptionId);
        $stmt->execute();
        
        return $updates;
    }
    
    /**
     * Send Server-Sent Event
     */
    private function sendSSEEvent($event, $data) {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
    
    /**
     * Handle performance optimization with caching
     * Requirements: 8.4
     */
    public function handlePerformanceMetricsRequest($context, $user) {
        $metrics = [
            'cache_hit_rate' => $this->cacheManager->getHitRate(),
            'avg_response_time' => $this->getAverageResponseTime($context['dashboard_type']),
            'active_connections' => $this->getActiveConnectionCount(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        return $this->sendResponse([
            'success' => true,
            'data' => $metrics,
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Helper methods
     */
    
    private function mapDashboardToViewType($dashboardType) {
        $mapping = [
            'admin_main' => 'kpis',
            'center_management' => 'kpis',
            'members_management' => 'kpis',
            'contributions_management' => 'kpis'
        ];
        
        return $mapping[$dashboardType] ?? 'kpis';
    }
    
    private function buildCenterFilter($filters, $user) {
        if ($user['role'] === 'superadmin') {
            return '';
        }
        
        $accessibleCenters = $this->getAccessibleCenters($user['id'], $user['role']);
        if (empty($accessibleCenters)) {
            return ' AND 1=0'; // No access
        }
        
        return ' AND m.center_id IN (' . implode(',', $accessibleCenters) . ')';
    }
    
    private function buildDateFilter($filters, $dateColumn = 'm.registration_date') {
        $filter = '';
        
        if (!empty($filters['start_date'])) {
            $filter .= " AND {$dateColumn} >= '" . $this->db->real_escape_string($filters['start_date']) . "'";
        }
        
        if (!empty($filters['end_date'])) {
            $filter .= " AND {$dateColumn} <= '" . $this->db->real_escape_string($filters['end_date']) . "'";
        }
        
        return $filter;
    }
    
    private function generateCacheKey($type, $context, $config) {
        $keyData = [
            'type' => $type,
            'dashboard' => $context['dashboard_type'],
            'user' => $context['user_id'],
            'centers' => $context['center_ids'],
            'filters' => $context['filters'],
            'config' => $config
        ];
        
        return 'analytics_' . md5(json_encode($keyData));
    }
    
    private function getCacheTTL($widgetType) {
        $ttls = [
            'kpi_card' => 300,      // 5 minutes
            'chart' => 600,         // 10 minutes
            'filter_panel' => 1800, // 30 minutes
            'export_button' => 60   // 1 minute
        ];
        
        return $ttls[$widgetType] ?? 300;
    }
    
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit();
    }
}

/**
 * Analytics Cache Manager
 * Handles caching for performance optimization
 */
class AnalyticsCacheManager {
    private $db;
    private $hitCount = 0;
    private $missCount = 0;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function get($key) {
        $stmt = $this->db->prepare("
            SELECT data, expires_at 
            FROM analytics_cache 
            WHERE cache_key = ? AND expires_at > NOW()
        ");
        
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $this->hitCount++;
            return [
                'data' => json_decode($row['data'], true),
                'timestamp' => $row['expires_at']
            ];
        }
        
        $this->missCount++;
        return null;
    }
    
    public function set($key, $data, $ttl = 300) {
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $contextHash = md5($key);
        
        $stmt = $this->db->prepare("
            INSERT INTO analytics_cache (cache_key, context_hash, data, expires_at) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            data = VALUES(data), 
            expires_at = VALUES(expires_at)
        ");
        
        $stmt->bind_param("ssss", $key, $contextHash, json_encode($data), $expiresAt);
        $stmt->execute();
    }
    
    public function getHitRate() {
        $total = $this->hitCount + $this->missCount;
        return $total > 0 ? round(($this->hitCount / $total) * 100, 2) : 0;
    }
    
    public function invalidate($pattern = null) {
        if ($pattern) {
            $stmt = $this->db->prepare("DELETE FROM analytics_cache WHERE cache_key LIKE ?");
            $stmt->bind_param("s", $pattern);
        } else {
            $stmt = $this->db->prepare("DELETE FROM analytics_cache WHERE expires_at < NOW()");
        }
        
        $stmt->execute();
        return $stmt->affected_rows;
    }
}

/**
 * Context Service
 * Handles dashboard context detection and filtering
 */
class ContextService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Detect dashboard context from request
     */
    public function detectDashboardContext($request) {
        $context = [
            'dashboard_type' => $request['dashboard_type'] ?? 'admin_main',
            'user_id' => $request['user_id'] ?? null,
            'user_role' => $request['user_role'] ?? 'viewer',
            'accessible_centers' => [],
            'permissions' => [],
            'filters' => $request['filters'] ?? []
        ];

        // Get user's accessible centers
        if ($context['user_id']) {
            $context['accessible_centers'] = $this->getUserAccessibleCenters($context['user_id'], $context['user_role']);
        }

        // Get contextual permissions
        $context['permissions'] = $this->getContextualPermissions($context, $context['user_id']);

        return $context;
    }

    /**
     * Get contextual permissions based on dashboard type and user
     */
    public function getContextualPermissions($context, $userId) {
        $permissions = [
            'view_level' => 'public',
            'actions' => [],
            'data_access' => []
        ];

        $userRole = $context['user_role'] ?? 'viewer';
        $dashboardType = $context['dashboard_type'] ?? 'admin_main';

        // Define permission levels by role
        $rolePermissions = [
            'viewer' => ['view_level' => 'public', 'actions' => ['view']],
            'manager' => ['view_level' => 'internal', 'actions' => ['view', 'export_basic']],
            'admin' => ['view_level' => 'confidential', 'actions' => ['view', 'export', 'manage']],
            'superadmin' => ['view_level' => 'restricted', 'actions' => ['view', 'export', 'manage', 'audit']]
        ];

        $permissions = array_merge($permissions, $rolePermissions[$userRole] ?? $rolePermissions['viewer']);

        // Dashboard-specific permissions
        switch ($dashboardType) {
            case 'center_management':
                $permissions['data_access'][] = 'center_specific';
                $permissions['data_access'][] = 'member_demographics';
                break;
            case 'members_management':
                $permissions['data_access'][] = 'member_details';
                $permissions['data_access'][] = 'engagement_metrics';
                break;
            case 'contributions_management':
                if (in_array($userRole, ['admin', 'superadmin'])) {
                    $permissions['data_access'][] = 'financial_data';
                    $permissions['data_access'][] = 'payment_details';
                }
                break;
            case 'admin_main':
            default:
                $permissions['data_access'][] = 'overview_metrics';
                break;
        }

        return $permissions;
    }

    /**
     * Filter data based on context and permissions
     */
    public function filterDataByContext($data, $context) {
        $filteredData = $data;

        // Apply center-based filtering
        if (!empty($context['accessible_centers'])) {
            $filteredData = $this->applyCenterFilter($filteredData, $context['accessible_centers']);
        }

        // Apply permission-based filtering
        $filteredData = $this->applyPermissionFilter($filteredData, $context['permissions']);

        // Apply dashboard-specific filtering
        $filteredData = $this->applyDashboardFilter($filteredData, $context['dashboard_type']);

        return $filteredData;
    }

    /**
     * Get contextual KPIs for dashboard type
     */
    public function getContextualKPIs($context) {
        $dashboardType = $context['dashboard_type'];
        $permissions = $context['permissions'];
        $centerIds = $context['accessible_centers'];

        $kpis = [];

        switch ($dashboardType) {
            case 'center_management':
                $kpis = $this->getCenterManagementKPIs($centerIds, $permissions);
                break;
            case 'members_management':
                $kpis = $this->getMembersManagementKPIs($centerIds, $permissions);
                break;
            case 'contributions_management':
                $kpis = $this->getContributionsManagementKPIs($centerIds, $permissions);
                break;
            case 'admin_main':
            default:
                $kpis = $this->getAdminMainKPIs($centerIds, $permissions);
                break;
        }

        return $kpis;
    }

    /**
     * Get contextual charts for dashboard type
     */
    public function getContextualCharts($context) {
        $dashboardType = $context['dashboard_type'];
        $permissions = $context['permissions'];

        $charts = [];

        switch ($dashboardType) {
            case 'center_management':
                $charts = [
                    ['type' => 'line', 'id' => 'center_member_growth', 'title' => 'Member Growth by Center'],
                    ['type' => 'bar', 'id' => 'center_performance', 'title' => 'Center Performance Comparison'],
                    ['type' => 'pie', 'id' => 'center_capacity', 'title' => 'Center Capacity Utilization']
                ];
                break;
            case 'members_management':
                $charts = [
                    ['type' => 'line', 'id' => 'member_registration_trend', 'title' => 'Member Registration Trends'],
                    ['type' => 'pie', 'id' => 'member_demographics', 'title' => 'Member Demographics'],
                    ['type' => 'bar', 'id' => 'engagement_metrics', 'title' => 'Member Engagement Metrics']
                ];
                break;
            case 'contributions_management':
                if (in_array('financial_data', $permissions['data_access'] ?? [])) {
                    $charts = [
                        ['type' => 'line', 'id' => 'contribution_trends', 'title' => 'Contribution Trends'],
                        ['type' => 'bar', 'id' => 'payment_status', 'title' => 'Payment Status Distribution'],
                        ['type' => 'area', 'id' => 'revenue_forecast', 'title' => 'Revenue Forecast']
                    ];
                }
                break;
            case 'admin_main':
            default:
                $charts = [
                    ['type' => 'line', 'id' => 'overall_growth', 'title' => 'Overall System Growth'],
                    ['type' => 'bar', 'id' => 'kpi_summary', 'title' => 'KPI Summary'],
                    ['type' => 'pie', 'id' => 'system_overview', 'title' => 'System Overview']
                ];
                break;
        }

        return $charts;
    }

    /**
     * Validate context and permissions
     */
    public function validateContext($context, $permissions) {
        // Validate required fields
        if (empty($context['dashboard_type']) || empty($context['user_id'])) {
            return false;
        }

        // Validate dashboard type
        $validDashboards = ['admin_main', 'center_management', 'members_management', 'contributions_management'];
        if (!in_array($context['dashboard_type'], $validDashboards)) {
            return false;
        }

        // Validate user has access to requested centers
        if (!empty($context['filters']['center_ids'])) {
            $requestedCenters = $context['filters']['center_ids'];
            $accessibleCenters = $context['accessible_centers'];

            foreach ($requestedCenters as $centerId) {
                if (!in_array($centerId, $accessibleCenters)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get user's default filters for dashboard type
     */
    public function getContextualFilters($dashboardType, $userId) {
        // Get user's saved preferences
        $stmt = $this->db->prepare("
            SELECT filter_config
            FROM user_dashboard_preferences
            WHERE user_id = ? AND dashboard_type = ?
        ");

        $stmt->bind_param("is", $userId, $dashboardType);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return json_decode($row['filter_config'], true) ?: [];
        }

        // Return default filters for dashboard type
        return $this->getDefaultFiltersForDashboard($dashboardType);
    }

    // Private helper methods

    private function getUserAccessibleCenters($userId, $userRole) {
        if ($userRole === 'superadmin') {
            // Superadmin has access to all centers
            $stmt = $this->db->prepare("SELECT id FROM centers WHERE status = 'active'");
            $stmt->execute();
            $result = $stmt->get_result();

            $centers = [];
            while ($row = $result->fetch_assoc()) {
                $centers[] = (int)$row['id'];
            }
            return $centers;
        }

        // Get user's assigned centers
        $stmt = $this->db->prepare("
            SELECT DISTINCT center_id
            FROM user_center_assignments
            WHERE user_id = ? AND is_active = 1
            UNION
            SELECT center_id
            FROM users
            WHERE id = ? AND center_id IS NOT NULL
        ");

        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $centers = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['center_id']) {
                $centers[] = (int)$row['center_id'];
            }
        }

        return $centers;
    }

    private function applyCenterFilter($data, $accessibleCenters) {
        if (empty($accessibleCenters)) {
            return [];
        }

        // Filter data arrays that have center_id field
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                if (is_array($item) && isset($item['center_id'])) {
                    if (!in_array($item['center_id'], $accessibleCenters)) {
                        unset($data[$key]);
                    }
                }
            }
        }

        return $data;
    }

    private function applyPermissionFilter($data, $permissions) {
        $viewLevel = $permissions['view_level'] ?? 'public';

        // Remove sensitive data based on permission level
        if ($viewLevel === 'public') {
            // Remove financial and personal data
            $data = $this->removeSensitiveFields($data, ['amount', 'salary', 'personal_info']);
        } elseif ($viewLevel === 'internal') {
            // Remove highly sensitive data
            $data = $this->removeSensitiveFields($data, ['salary', 'audit_logs']);
        }

        return $data;
    }

    private function applyDashboardFilter($data, $dashboardType) {
        // Apply dashboard-specific data filtering
        switch ($dashboardType) {
            case 'center_management':
                // Focus on center-related metrics
                break;
            case 'members_management':
                // Focus on member-related metrics
                break;
            case 'contributions_management':
                // Focus on financial metrics
                break;
        }

        return $data;
    }

    private function getCenterManagementKPIs($centerIds, $permissions) {
        if (empty($centerIds)) {
            return [];
        }

        $centerPlaceholders = implode(',', array_fill(0, count($centerIds), '?'));

        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT c.id) as total_centers,
                COUNT(DISTINCT m.id) as total_members,
                AVG(c.capacity) as avg_capacity,
                COUNT(DISTINCT m.id) / COUNT(DISTINCT c.id) as avg_members_per_center
            FROM centers c
            LEFT JOIN members m ON c.id = m.center_id AND m.status = 'active'
            WHERE c.id IN ($centerPlaceholders) AND c.status = 'active'
        ");

        $stmt->execute($centerIds);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function getMembersManagementKPIs($centerIds, $permissions) {
        if (empty($centerIds)) {
            return [];
        }

        $centerPlaceholders = implode(',', array_fill(0, count($centerIds), '?'));

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_members,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_members,
                COUNT(CASE WHEN registration_date >= CURDATE() THEN 1 END) as new_today,
                COUNT(CASE WHEN registration_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
            FROM members
            WHERE center_id IN ($centerPlaceholders) AND status = 'active'
        ");

        $stmt->execute($centerIds);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function getContributionsManagementKPIs($centerIds, $permissions) {
        if (empty($centerIds) || !in_array('financial_data', $permissions['data_access'] ?? [])) {
            return [];
        }

        $centerPlaceholders = implode(',', array_fill(0, count($centerIds), '?'));

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_contributions,
                SUM(CASE WHEN payment_status = 'confirmed' THEN amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN payment_status = 'confirmed' THEN amount END) as avg_contribution,
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments
            FROM contributions
            WHERE center_id IN ($centerPlaceholders) AND status = 'active'
        ");

        $stmt->execute($centerIds);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function getAdminMainKPIs($centerIds, $permissions) {
        if (empty($centerIds)) {
            return [];
        }

        $centerPlaceholders = implode(',', array_fill(0, count($centerIds), '?'));

        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT c.id) as total_centers,
                COUNT(DISTINCT m.id) as total_members,
                COUNT(DISTINCT cont.id) as total_contributions,
                COUNT(CASE WHEN m.payment_status = 'paid' THEN 1 END) as paid_members
            FROM centers c
            LEFT JOIN members m ON c.id = m.center_id AND m.status = 'active'
            LEFT JOIN contributions cont ON c.id = cont.center_id AND cont.status = 'active'
            WHERE c.id IN ($centerPlaceholders) AND c.status = 'active'
        ");

        $stmt->execute($centerIds);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function removeSensitiveFields($data, $sensitiveFields) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, $sensitiveFields)) {
                    unset($data[$key]);
                } elseif (is_array($value)) {
                    $data[$key] = $this->removeSensitiveFields($value, $sensitiveFields);
                }
            }
        }

        return $data;
    }

    private function getDefaultFiltersForDashboard($dashboardType) {
        $defaults = [
            'date_range' => [
                'start' => date('Y-m-01'), // First day of current month
                'end' => date('Y-m-d')     // Today
            ],
            'status' => 'active'
        ];

        switch ($dashboardType) {
            case 'center_management':
                $defaults['show_inactive_centers'] = false;
                break;
            case 'members_management':
                $defaults['payment_status'] = 'all';
                $defaults['membership_type'] = 'all';
                break;
            case 'contributions_management':
                $defaults['payment_status'] = 'all';
                $defaults['amount_range'] = ['min' => 0, 'max' => null];
                break;
        }

        return $defaults;
    }
}


// Initialize and handle request
if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    $api = new AnalyticsIntegrationAPI();
    $api->handleRequest();
}
?>
    /**
     * Get admin dashboard KPIs (overview metrics)
     */
    private function getAdminDashboardKPIs($metric, $filters, $user) {
        $centerFilter = $this->buildCenterFilter($filters, $user);
        $dateFilter = $this->buildDateFilter($filters);
        
        switch ($metric) {
            case 'total_members':
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN membership_status = 'active' THEN 1 END) as active,
                        COUNT(CASE WHEN registration_date >= CURDATE() - INTERVAL 30 DAY THEN 1 END) as new_30d,
                        COUNT(CASE WHEN last_activity_date >= CURDATE() - INTERVAL 7 DAY THEN 1 END) as active_7d
                    FROM members m
                    WHERE 1=1 {$centerFilter} {$dateFilter}
                ");
                break;
                
            case 'system_overview':
                $stmt = $this->db->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM members m WHERE 1=1 {$centerFilter}) as total_members,
                        (SELECT COUNT(*) FROM centers WHERE is_active = 1) as active_centers,
                        (SELECT SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) 
                         FROM contributions c JOIN members m ON c.member_id = m.id 
                         WHERE c.created_at >= CURDATE() - INTERVAL 30 DAY {$centerFilter}) as revenue_30d,
                        (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users
                ");
                break;
                
            default:
                throw new Exception('Invalid admin dashboard metric');
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        return [
            'metric' => $metric,
            'dashboard_context' => 'admin_main',
            'data' => $data,
            'summary' => $this->calculateKPISummary([$data], $metric)
        ];
    }
    
    /**
     * Get chart widget data with context filtering
     */
    private function getChartWidgetData($context, $config, $filters, $user) {
        $chartType = $config['chart_type'] ?? 'line';
        $metric = $config['metric'] ?? 'member_trend';
        $dashboardType = $context['dashboard_type'];
        
        switch ($dashboardType) {
            case 'center_management':
                return $this->getCenterManagementChartData($chartType, $metric, $filters, $user);
                
            case 'members_management':
                return $this->getMembersManagementChartData($chartType, $metric, $filters, $user);
                
            case 'contributions_management':
                return $this->getContributionsManagementChartData($chartType, $metric, $filters, $user);
                
            case 'admin_main':
            default:
                return $this->getAdminDashboardChartData($chartType, $metric, $filters, $user);
        }
    }
    
    /**
     * Get center management chart data
     */
    private function getCenterManagementChartData($chartType, $metric, $filters, $user) {
        $centerFilter = $this->buildCenterFilter($filters, $user);
        
        switch ($metric) {
            case 'center_growth_comparison':
                $stmt = $this->db->prepare("
                    SELECT 
                        c.name as center_name,
                        DATE_FORMAT(m.registration_date, '%Y-%m') as month,
                        COUNT(m.id) as new_members
                    FROM centers c
                    LEFT JOIN members m ON c.id = m.center_id 
                        AND m.registration_date >= CURDATE() - INTERVAL 12 MONTH
                    WHERE c.is_active = 1 {$centerFilter}
                    GROUP BY c.id, c.name, DATE_FORMAT(m.registration_date, '%Y-%m')
                    ORDER BY month, c.name
                ");
                break;
                
            case 'center_performance_trends':
                $stmt = $this->db->prepare("
                    SELECT 
                        c.name as center_name,
                        COUNT(m.id) as total_members,
                        COUNT(CASE WHEN m.membership_status = 'active' THEN 1 END) as active_members,
                        COUNT(CASE WHEN m.payment_status = 'paid' THEN 1 END) as paid_members,
                        ROUND(COUNT(CASE WHEN m.payment_status = 'paid' THEN 1 END) * 100.0 / NULLIF(COUNT(m.id), 0), 1) as payment_rate
                    FROM centers c
                    LEFT JOIN members m ON c.id = m.center_id
                    WHERE c.is_active = 1 {$centerFilter}
                    GROUP BY c.id, c.name
                    ORDER BY payment_rate DESC
                ");
                break;
                
            default:
                throw new Exception('Invalid center management chart metric');
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $this->formatChartData($result, $chartType, $metric);
    }
    
    /**
     * Get members management chart data
     */
    private function getMembersManagementChartData($chartType, $metric, $filters, $user) {
        $centerFilter = $this->buildCenterFilter($filters, $user);
        $dateFilter = $this->buildDateFilter($filters);
        
        switch ($metric) {
            case 'member_registration_trend':
                $stmt = $this->db->prepare("
                    SELECT 
                        DATE_FORMAT(registration_date, '%Y-%m') as month,
                        COUNT(*) as registrations,
                        COUNT(CASE WHEN gender = 'male' THEN 1 END) as male_registrations,
                        COUNT(CASE WHEN gender = 'female' THEN 1 END) as female_registrations
                    FROM members m
                    WHERE registration_date >= CURDATE() - INTERVAL 12 MONTH {$centerFilter} {$dateFilter}
                    GROUP BY DATE_FORMAT(registration_date, '%Y-%m')
                    ORDER BY month
                ");
                break;
                
            case 'membership_status_distribution':
                $stmt = $this->db->prepare("
                    SELECT 
                        membership_status,
                        COUNT(*) as count,
                        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM members m2 WHERE 1=1 {$centerFilter}), 1) as percentage
                    FROM members m
                    WHERE 1=1 {$centerFilter} {$dateFilter}
                    GROUP BY membership_status
                ");
                break;
                
            case 'age_demographics':
                $stmt = $this->db->prepare("
                    SELECT 
                        CASE 
                            WHEN YEAR(CURDATE()) - YEAR(date_of_birth) < 18 THEN 'Under 18'
                            WHEN YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN 18 AND 25 THEN '18-25'
                            WHEN YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN 26 AND 35 THEN '26-35'
                            WHEN YEAR(CURDATE()) - YEAR(date_of_birth) BETWEEN 36 AND 50 THEN '36-50'
                            ELSE 'Over 50'
                        END as age_group,
                        COUNT(*) as count
                    FROM members m
                    WHERE date_of_birth IS NOT NULL {$centerFilter} {$dateFilter}
                    GROUP BY age_group
                    ORDER BY 
                        CASE age_group
                            WHEN 'Under 18' THEN 1
                            WHEN '18-25' THEN 2
                            WHEN '26-35' THEN 3
                            WHEN '36-50' THEN 4
                            WHEN 'Over 50' THEN 5
                        END
                ");
                break;
                
            default:
                throw new Exception('Invalid members management chart metric');
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $this->formatChartData($result, $chartType, $metric);
    }
    
    /**
     * Get contributions management chart data
     */
    private function getContributionsManagementChartData($chartType, $metric, $filters, $user) {
        $centerFilter = $this->buildCenterFilter($filters, $user);
        $dateFilter = $this->buildDateFilter($filters, 'c.created_at');
        
        switch ($metric) {
            case 'revenue_trend':
                $stmt = $this->db->prepare("
                    SELECT 
                        DATE_FORMAT(c.created_at, '%Y-%m') as month,
                        SUM(CASE WHEN c.payment_status = 'paid' THEN c.amount ELSE 0 END) as revenue,
                        COUNT(CASE WHEN c.payment_status = 'paid' THEN 1 END) as paid_contributions,
                        COUNT(CASE WHEN c.payment_status = 'unpaid' THEN 1 END) as unpaid_contributions
                    FROM contributions c
                    JOIN members m ON c.member_id = m.id
                    WHERE c.created_at >= CURDATE() - INTERVAL 12 MONTH {$centerFilter} {$dateFilter}
                    GROUP BY DATE_FORMAT(c.created_at, '%Y-%m')
                    ORDER BY month
                ");
                break;
                
            case 'payment_method_distribution':
                $stmt = $this->db->prepare("
                    SELECT 
                        c.payment_method,
                        COUNT(*) as transaction_count,
                        SUM(CASE WHEN c.payment_status = 'paid' THEN c.amount ELSE 0 END) as total_amount,
                        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM contributions c2 JOIN members m2 ON c2.member_id = m2.id WHERE 1=1 {$centerFilter}), 1) as percentage
                    FROM contributions c
                    JOIN members m ON c.member_id = m.id
                    WHERE 1=1 {$centerFilter} {$dateFilter}
                    GROUP BY c.payment_method
                ");
                break;
                
            case 'contribution_amounts_distribution':
                $stmt = $this->db->prepare("
                    SELECT 
                        CASE 
                            WHEN c.amount < 100 THEN 'Under $100'
                            WHEN c.amount BETWEEN 100 AND 500 THEN '$100-$500'
                            WHEN c.amount BETWEEN 501 AND 1000 THEN '$501-$1000'
                            WHEN c.amount BETWEEN 1001 AND 5000 THEN '$1001-$5000'
                            ELSE 'Over $5000'
                        END as amount_range,
                        COUNT(*) as count,
                        SUM(c.amount) as total_amount
                    FROM contributions c
                    JOIN members m ON c.member_id = m.id
                    WHERE c.payment_status = 'paid' {$centerFilter} {$dateFilter}
                    GROUP BY amount_range
                    ORDER BY 
                        CASE amount_range
                            WHEN 'Under $100' THEN 1
                            WHEN '$100-$500' THEN 2
                            WHEN '$501-$1000' THEN 3
                            WHEN '$1001-$5000' THEN 4
                            WHEN 'Over $5000' THEN 5
                        END
                ");
                break;
                
            default:
                throw new Exception('Invalid contributions management chart metric');
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $this->formatChartData($result, $chartType, $metric);
    }
    
    /**
     * Get admin dashboard chart data
     */
    private function getAdminDashboardChartData($chartType, $metric, $filters, $user) {
        $centerFilter = $this->buildCenterFilter($filters, $user);
        
        switch ($metric) {
            case 'system_growth_overview':
                $stmt = $this->db->prepare("
                    SELECT 
                        DATE_FORMAT(registration_date, '%Y-%m') as month,
                        COUNT(*) as new_members,
                        (SELECT COUNT(*) FROM centers WHERE created_at <= LAST_DAY(STR_TO_DATE(CONCAT(DATE_FORMAT(m.registration_date, '%Y-%m'), '-01'), '%Y-%m-%d'))) as centers_count
                    FROM members m
                    WHERE registration_date >= CURDATE() - INTERVAL 12 MONTH {$centerFilter}
                    GROUP BY DATE_FORMAT(registration_date, '%Y-%m')
                    ORDER BY month
                ");
                break;
                
            case 'dashboard_kpi_summary':
                $stmt = $this->db->prepare("
                    SELECT 
                        'Members' as category,
                        COUNT(*) as total,
                        COUNT(CASE WHEN membership_status = 'active' THEN 1 END) as active,
                        COUNT(CASE WHEN registration_date >= CURDATE() - INTERVAL 30 DAY THEN 1 END) as recent
                    FROM members m
                    WHERE 1=1 {$centerFilter}
                    UNION ALL
                    SELECT 
                        'Revenue' as category,
                        COUNT(*) as total,
                        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as active,
                        SUM(CASE WHEN payment_status = 'paid' AND created_at >= CURDATE() - INTERVAL 30 DAY THEN amount ELSE 0 END) as recent
                    FROM contributions c
                    JOIN members m ON c.member_id = m.id
                    WHERE 1=1 {$centerFilter}
                ");
                break;
                
            default:
                throw new Exception('Invalid admin dashboard chart metric');
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $this->formatChartData($result, $chartType, $metric);
    }
    
    /**
     * Format chart data based on chart type
     */
    private function formatChartData($result, $chartType, $metric) {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        switch ($chartType) {
            case 'line':
            case 'bar':
                return $this->formatTimeSeriesData($data, $metric);
                
            case 'pie':
            case 'doughnut':
                return $this->formatDistributionData($data, $metric);
                
            case 'area':
                return $this->formatAreaChartData($data, $metric);
                
            default:
                return [
                    'labels' => array_keys($data[0] ?? []),
                    'datasets' => [$data]
                ];
        }
    }
    
    /**
     * Format time series data for line/bar charts
     */
    private function formatTimeSeriesData($data, $metric) {
        $labels = [];
        $datasets = [];
        
        if (empty($data)) {
            return ['labels' => [], 'datasets' => []];
        }
        
        // Extract labels (usually time periods)
        $timeField = isset($data[0]['month']) ? 'month' : (isset($data[0]['date']) ? 'date' : null);
        
        if ($timeField) {
            $labels = array_column($data, $timeField);
            
            // Create datasets for each numeric field
            $numericFields = array_filter(array_keys($data[0]), function($key) use ($timeField) {
                return $key !== $timeField && is_numeric($data[0][$key] ?? 0);
            });
            
            foreach ($numericFields as $field) {
                $datasets[] = [
                    'label' => ucwords(str_replace('_', ' ', $field)),
                    'data' => array_column($data, $field),
                    'borderColor' => $this->getChartColor($field),
                    'backgroundColor' => $this->getChartColor($field, 0.2)
                ];
            }
        } else {
            // Non-time series data
            $labels = array_column($data, array_keys($data[0])[0]);
            $datasets[] = [
                'label' => ucwords(str_replace('_', ' ', $metric)),
                'data' => array_column($data, array_keys($data[0])[1]),
                'borderColor' => $this->getChartColor('primary'),
                'backgroundColor' => $this->getChartColor('primary', 0.2)
            ];
        }
        
        return [
            'labels' => $labels,
            'datasets' => $datasets
        ];
    }
    
    /**
     * Format distribution data for pie/doughnut charts
     */
    private function formatDistributionData($data, $metric) {
        if (empty($data)) {
            return ['labels' => [], 'datasets' => []];
        }
        
        $labelField = array_keys($data[0])[0];
        $valueField = 'count';
        
        // Find the appropriate value field
        foreach (array_keys($data[0]) as $field) {
            if (in_array($field, ['count', 'total', 'amount', 'percentage']) && is_numeric($data[0][$field])) {
                $valueField = $field;
                break;
            }
        }
        
        $labels = array_column($data, $labelField);
        $values = array_column($data, $valueField);
        
        return [
            'labels' => $labels,
            'datasets' => [{
                'data' => $values,
                'backgroundColor' => array_map([$this, 'getChartColor'], range(0, count($labels) - 1))
            }]
        ];
    }
    
    /**
     * Format area chart data
     */
    private function formatAreaChartData($data, $metric) {
        $formatted = $this->formatTimeSeriesData($data, $metric);
        
        // Add area chart specific properties
        foreach ($formatted['datasets'] as &$dataset) {
            $dataset['fill'] = true;
        }
        
        return $formatted;
    }
    
    /**
     * Get chart colors
     */
    private function getChartColor($index, $alpha = 1) {
        $colors = [
            'primary' => "rgba(54, 162, 235, {$alpha})",
            'secondary' => "rgba(255, 99, 132, {$alpha})",
            'success' => "rgba(75, 192, 192, {$alpha})",
            'warning' => "rgba(255, 205, 86, {$alpha})",
            'info' => "rgba(153, 102, 255, {$alpha})",
            'danger' => "rgba(255, 159, 64, {$alpha})"
        ];
        
        if (is_string($index) && isset($colors[$index])) {
            return $colors[$index];
        }
        
        $colorKeys = array_keys($colors);
        $colorIndex = is_numeric($index) ? $index % count($colorKeys) : 0;
        
        return $colors[$colorKeys[$colorIndex]];
    }
    
    /**
     * Get filter panel data
     */
    private function getFilterPanelData($context, $config, $filters, $user) {
        $dashboardType = $context['dashboard_type'];
        
        $filterOptions = [
            'date_ranges' => [
                ['label' => 'Last 7 days', 'value' => '7d'],
                ['label' => 'Last 30 days', 'value' => '30d'],
                ['label' => 'Last 3 months', 'value' => '3m'],
                ['label' => 'Last 6 months', 'value' => '6m'],
                ['label' => 'Last year', 'value' => '1y'],
                ['label' => 'Custom range', 'value' => 'custom']
            ],
            'centers' => $this->getAvailableCenters($user),
            'dashboard_specific' => $this->getDashboardSpecificFilters($dashboardType, $user)
        ];
        
        return [
            'filter_options' => $filterOptions,
            'current_filters' => $filters,
            'dashboard_context' => $dashboardType
        ];
    }
    
    /**
     * Get export button data
     */
    private function getExportButtonData($context, $config, $filters, $user) {
        return [
            'available_formats' => ['csv', 'excel', 'pdf', 'json'],
            'export_permissions' => $this->getExportPermissions($user),
            'dashboard_context' => $context['dashboard_type'],
            'current_filters' => $filters
        ];
    }
    
    /**
     * Calculate KPI summary statistics
     */
    private function calculateKPISummary($data, $metric) {
        if (empty($data)) {
            return ['total' => 0, 'trend' => 'neutral'];
        }
        
        // Basic summary calculation
        $summary = [
            'total_records' => count($data),
            'timestamp' => date('c')
        ];
        
        // Add metric-specific calculations
        switch ($metric) {
            case 'total_members':
            case 'center_member_count':
                $summary['total'] = array_sum(array_column($data, 'total')) ?? 0;
                $summary['active'] = array_sum(array_column($data, 'active')) ?? 0;
                break;
                
            case 'financial_analytics':
                $summary['total_revenue'] = array_sum(array_column($data, 'total_revenue')) ?? 0;
                $summary['pending_revenue'] = array_sum(array_column($data, 'pending_revenue')) ?? 0;
                break;
        }
        
        return $summary;
    }
    
    /**
     * Get available centers for user
     */
    private function getAvailableCenters($user) {
        if ($user['role'] === 'superadmin') {
            $stmt = $this->db->prepare("SELECT id, name FROM centers WHERE is_active = 1 ORDER BY name");
        } else {
            $accessibleCenters = $this->getAccessibleCenters($user['id'], $user['role']);
            if (empty($accessibleCenters)) {
                return [];
            }
            
            $placeholders = implode(',', array_fill(0, count($accessibleCenters), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM centers WHERE id IN ({$placeholders}) AND is_active = 1 ORDER BY name");
            $stmt->bind_param(str_repeat('i', count($accessibleCenters)), ...$accessibleCenters);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $centers = [];
        while ($row = $result->fetch_assoc()) {
            $centers[] = ['value' => $row['id'], 'label' => $row['name']];
        }
        
        return $centers;
    }
    
    /**
     * Get dashboard-specific filter options
     */
    private function getDashboardSpecificFilters($dashboardType, $user) {
        switch ($dashboardType) {
            case 'members_management':
                return [
                    'membership_status' => [
                        ['label' => 'Active', 'value' => 'active'],
                        ['label' => 'Inactive', 'value' => 'inactive'],
                        ['label' => 'Pending', 'value' => 'pending']
                    ],
                    'gender' => [
                        ['label' => 'Male', 'value' => 'male'],
                        ['label' => 'Female', 'value' => 'female']
                    ]
                ];
                
            case 'contributions_management':
                return [
                    'payment_status' => [
                        ['label' => 'Paid', 'value' => 'paid'],
                        ['label' => 'Unpaid', 'value' => 'unpaid'],
                        ['label' => 'Failed', 'value' => 'failed']
                    ],
                    'payment_method' => [
                        ['label' => 'Cash', 'value' => 'cash'],
                        ['label' => 'Bank Transfer', 'value' => 'bank_transfer'],
                        ['label' => 'Mobile Money', 'value' => 'mobile_money']
                    ]
                ];
                
            default:
                return [];
        }
    }
    
    /**
     * Get export permissions for user
     */
    private function getExportPermissions($user) {
        $permissions = [
            'viewer' => ['csv'],
            'manager' => ['csv', 'excel'],
            'admin' => ['csv', 'excel', 'pdf'],
            'superadmin' => ['csv', 'excel', 'pdf', 'json'],
            'auditor' => ['csv', 'excel', 'pdf', 'json']
        ];
        
        return $permissions[$user['role']] ?? ['csv'];
    }
    
    /**
     * Get accessible centers for user
     */
    private function getAccessibleCenters($userId, $userRole) {
        if ($userRole === 'superadmin') {
            $stmt = $this->db->prepare("SELECT id FROM centers WHERE is_active = 1");
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("
                SELECT DISTINCT center_id as id
                FROM user_center_assignments 
                WHERE user_id = ? AND is_active = 1
                UNION
                SELECT center_id as id
                FROM users 
                WHERE id = ? AND center_id IS NOT NULL
            ");
            $stmt->bind_param("ii", $userId, $userId);
            $stmt->execute();
        }
        
        $result = $stmt->get_result();
        $centers = [];
        while ($row = $result->fetch_assoc()) {
            $centers[] = $row['id'];
        }
        
        return $centers;
    }
    
    /**
     * Check if cache is expired
     */
    private function isCacheExpired($cachedData) {
        return strtotime($cachedData['timestamp']) < time();
    }
    
    /**
     * Get average response time for dashboard
     */
    private function getAverageResponseTime($dashboardType) {
        $stmt = $this->db->prepare("
            SELECT AVG(response_time) as avg_time 
            FROM analytics_performance_logs 
            WHERE dashboard_type = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        $stmt->bind_param("s", $dashboardType);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['avg_time'] ?? 0;
    }
    
    /**
     * Get active connection count
     */
    private function getActiveConnectionCount() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as active_count 
            FROM realtime_subscriptions 
            WHERE last_heartbeat >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['active_count'] ?? 0;
    }
    
    /**
     * Unregister stream subscription
     */
    private function unregisterStreamSubscription($subscriptionId) {
        $stmt = $this->db->prepare("DELETE FROM realtime_subscriptions WHERE session_id = ?");
        $stmt->bind_param("s", $subscriptionId);
        $stmt->execute();
    }
    
    /**
     * Handle KPI data request
     */
    public function handleKPIDataRequest($context, $user) {
        return $this->handleWidgetDataRequest($context, $user);
    }
    
    /**
     * Handle chart data request
     */
    public function handleChartDataRequest($context, $user) {
        return $this->handleWidgetDataRequest($context, $user);
    }
    
    /**
     * Handle filter data request
     */
    public function handleFilterDataRequest($context, $user) {
        return $this->handleWidgetDataRequest($context, $user);
    }
    
    /**
     * Handle widget export request
     */
    public function handleWidgetExportRequest($context, $user) {
        $widgetType = $_GET['widget_type'] ?? 'kpi_card';
        $format = $_GET['format'] ?? 'csv';
        
        // Check export permissions
        $exportPermissions = $this->getExportPermissions($user);
        if (!in_array($format, $exportPermissions)) {
            throw new Exception('Insufficient permissions for this export format');
        }
        
        // Get widget data
        $widgetData = $this->handleWidgetDataRequest($context, $user);
        
        // Log export
        $this->securityManager->logAnalyticsAccess('EXPORT', $widgetType, [
            'format' => $format,
            'dashboard_type' => $context['dashboard_type'],
            'widget_id' => $context['widget_id']
        ]);
        
        return $this->sendResponse([
            'success' => true,
            'export_data' => $widgetData['data'],
            'format' => $format,
            'filename' => $this->generateExportFilename($widgetType, $format),
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Generate export filename
     */
    private function generateExportFilename($widgetType, $format) {
        $timestamp = date('Y-m-d_H-i-s');
        return "analytics_{$widgetType}_{$timestamp}.{$format}";
    }
}
        ]);
    }
    
    /**
     * Generate export filename
     */
    private function generateExportFilename($widgetType, $format) {
        return sprintf(
            '%s_%s_%s.%s',
            $widgetType,
            date('Y-m-d_H-i-s'),
            uniqid(),
            $format
        );
    }
}