<?php
/**
 * Secure Analytics API Endpoint
 * Handles all analytics requests with comprehensive security
 * Requirements: 12.1, 12.2, 12.3, 12.6, 12.7
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AnalyticsSecurityManager.php';

try {
    // Initialize database and security manager
    $database = new Database();
    $db = $database->getConnection();
    $securityManager = new AnalyticsSecurityManager($db);
    
    // Get request parameters
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri = $_SERVER['REQUEST_URI'];
    $queryParams = $_GET;
    $postData = json_decode(file_get_contents('php://input'), true) ?: [];
    
    // Determine request type and sensitivity level
    $viewType = $queryParams['view_type'] ?? 'kpis';
    $sensitivityLevel = $queryParams['sensitivity'] ?? 'public';
    $action = $queryParams['action'] ?? 'view';
    
    // Widget-specific parameters
    $widgetId = $queryParams['widgetId'] ?? $postData['widgetId'] ?? null;
    $widgetType = $queryParams['widgetType'] ?? $postData['widgetType'] ?? null;
    $widgetAuthToken = $queryParams['widgetAuthToken'] ?? $postData['widgetAuthToken'] ?? null;
    
    // Authenticate and authorize request
    $authResult = $securityManager->authenticateAnalyticsAccess($viewType, $sensitivityLevel);
    
    if (!$authResult['success']) {
        http_response_code($authResult['code']);
        echo json_encode([
            'success' => false,
            'error' => $authResult['error'],
            'requires_2fa' => $authResult['requires_2fa'] ?? false
        ]);
        exit();
    }
    
    $user = $authResult['user'];
    $userPermissions = $authResult['permissions'];
    $requiresMasking = $authResult['requires_masking'];
    
    // Additional widget authentication if required
    if ($widgetId && $widgetAuthToken) {
        if (!$securityManager->validateWidgetAuthToken($user['id'], $widgetId, $widgetAuthToken)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid or expired widget authentication token',
                'widget_id' => $widgetId
            ]);
            exit();
        }
    }
    
    // Check widget permissions if this is a widget request
    if ($widgetId && $widgetType) {
        $widgetConfig = [
            'id' => $widgetId,
            'type' => $widgetType,
            'dashboardContext' => $queryParams['dashboardContext'] ?? 'admin_main',
            'config' => [
                'sensitivityLevel' => $sensitivityLevel,
                'containsPII' => ($queryParams['containsPII'] ?? 'false') === 'true'
            ]
        ];
        
        if (!$securityManager->hasWidgetPermission($user['id'], $widgetConfig)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Insufficient permissions to access this widget',
                'widget_id' => $widgetId
            ]);
            exit();
        }
        
        // Check if additional authentication is required but not provided
        if ($securityManager->requiresWidgetAdditionalAuth($widgetConfig) && !$widgetAuthToken) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Additional authentication required for this widget',
                'requires_widget_auth' => true,
                'widget_id' => $widgetId
            ]);
            exit();
        }
    }
    
    // Validate analytics session
    $sessionResult = $securityManager->validateAnalyticsSession();
    if (!$sessionResult['success']) {
        // Create new analytics session
        $securityManager->createSecureAnalyticsSession($user['id']);
    }
    
    // Check for suspicious activity
    if ($securityManager->detectSuspiciousAnalyticsActivity()) {
        // Log but don't block - let security team investigate
        $securityManager->logAnalyticsAccess('SUSPICIOUS_ACTIVITY_DETECTED', $viewType, [
            'sensitivity' => $sensitivityLevel,
            'action' => $action
        ]);
    }
    
    // Log the access attempt
    $securityManager->logAnalyticsAccess($action, $viewType, [
        'sensitivity' => $sensitivityLevel,
        'requires_masking' => $requiresMasking,
        'user_role' => $user['role']
    ]);
    
    // Route request based on action
    switch ($action) {
        case 'view':
            $result = handleViewRequest($db, $securityManager, $viewType, $sensitivityLevel, $queryParams, $user, $requiresMasking);
            break;
            
        case 'export':
            $result = handleExportRequest($db, $securityManager, $viewType, $sensitivityLevel, $postData, $user, $requiresMasking);
            break;
            
        case 'search':
            $result = handleSearchRequest($db, $securityManager, $queryParams, $user, $requiresMasking);
            break;
            
        case 'audit':
            $result = handleAuditRequest($db, $securityManager, $queryParams, $user);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
    // Apply data masking and filtering if required
    if ($requiresMasking && isset($result['data'])) {
        if ($widgetId && $widgetType) {
            // Use widget-specific filtering
            $result['data'] = $securityManager->filterWidgetData($result['data'], $widgetConfig, $user['id']);
        } else {
            // Use general data masking
            $result['data'] = $securityManager->maskSensitiveData($result['data'], $sensitivityLevel, $user['role']);
        }
    }
    
    // Add security metadata to response
    $result['security'] = [
        'sensitivity_level' => $sensitivityLevel,
        'data_masked' => $requiresMasking,
        'user_role' => $user['role'],
        'access_time' => date('c'),
        'widget_id' => $widgetId,
        'widget_authenticated' => !empty($widgetAuthToken)
    ];
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Secure Analytics API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle view requests (KPIs, charts, reports)
 */
function handleViewRequest($db, $securityManager, $viewType, $sensitivityLevel, $params, $user, $requiresMasking) {
    switch ($viewType) {
        case 'kpis':
            return getSecureKPIs($db, $securityManager, $sensitivityLevel, $params, $user);
            
        case 'charts':
            return getSecureChartData($db, $securityManager, $sensitivityLevel, $params, $user);
            
        case 'reports':
            return getSecureReports($db, $securityManager, $sensitivityLevel, $params, $user);
            
        default:
            throw new Exception('Invalid view type');
    }
}

/**
 * Get secure KPIs based on user permissions and sensitivity level
 */
function getSecureKPIs($db, $securityManager, $sensitivityLevel, $params, $user) {
    $centerFilter = '';
    $centerParams = [];
    
    // Apply center-based access control
    if ($user['role'] !== 'superadmin') {
        $accessibleCenters = getAccessibleCenters($db, $user['id'], $user['role']);
        if (empty($accessibleCenters)) {
            return ['success' => true, 'data' => []];
        }
        
        $centerFilter = ' AND m.center_id IN (' . implode(',', array_fill(0, count($accessibleCenters), '?')) . ')';
        $centerParams = $accessibleCenters;
    }
    
    // Additional filters from request
    if (!empty($params['center_id'])) {
        $centerFilter = ' AND m.center_id = ?';
        $centerParams = [$params['center_id']];
    }
    
    $dateFilter = '';
    $dateParams = [];
    if (!empty($params['start_date'])) {
        $dateFilter .= ' AND m.registration_date >= ?';
        $dateParams[] = $params['start_date'];
    }
    if (!empty($params['end_date'])) {
        $dateFilter .= ' AND m.registration_date <= ?';
        $dateParams[] = $params['end_date'];
    }
    
    $kpis = [];
    
    // Public KPIs - basic counts
    if (in_array('public', getSensitivityLevelsForRole($user['role'], 'kpis'))) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_members,
                COUNT(CASE WHEN m.registration_date >= CURDATE() THEN 1 END) as new_today,
                COUNT(CASE WHEN m.membership_status = 'active' THEN 1 END) as active_members,
                COUNT(DISTINCT m.center_id) as active_centers
            FROM members m 
            WHERE 1=1 {$centerFilter} {$dateFilter}
        ");
        
        $allParams = array_merge($centerParams, $dateParams);
        if (!empty($allParams)) {
            $stmt->bind_param(str_repeat('s', count($allParams)), ...$allParams);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $publicData = $result->fetch_assoc();
        
        $kpis['membership'] = [
            'total_members' => (int)$publicData['total_members'],
            'new_registrations_today' => (int)$publicData['new_today'],
            'active_members' => (int)$publicData['active_members'],
            'active_centers' => (int)$publicData['active_centers']
        ];
    }
    
    // Internal KPIs - payment status, detailed demographics
    if (in_array('internal', getSensitivityLevelsForRole($user['role'], 'kpis'))) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(CASE WHEN m.payment_status = 'paid' THEN 1 END) as paid_members,
                COUNT(CASE WHEN m.payment_status = 'unpaid' THEN 1 END) as unpaid_members,
                ROUND(AVG(CASE WHEN m.payment_status = 'paid' THEN 1 ELSE 0 END) * 100, 1) as payment_compliance_rate
            FROM members m 
            WHERE 1=1 {$centerFilter} {$dateFilter}
        ");
        
        if (!empty($allParams)) {
            $stmt->bind_param(str_repeat('s', count($allParams)), ...$allParams);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $internalData = $result->fetch_assoc();
        
        $kpis['membership']['paid_members'] = (int)$internalData['paid_members'];
        $kpis['membership']['unpaid_members'] = (int)$internalData['unpaid_members'];
        $kpis['membership']['payment_compliance_rate'] = (float)$internalData['payment_compliance_rate'];
    }
    
    // Confidential KPIs - financial details
    if (in_array('confidential', getSensitivityLevelsForRole($user['role'], 'kpis'))) {
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN c.payment_status = 'paid' THEN c.amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN c.payment_status = 'paid' THEN c.amount END) as avg_contribution,
                SUM(CASE WHEN c.payment_status = 'unpaid' AND c.due_date < CURDATE() THEN c.amount ELSE 0 END) as overdue_amount
            FROM contributions c
            JOIN members m ON c.member_id = m.id
            WHERE 1=1 {$centerFilter} {$dateFilter}
        ");
        
        if (!empty($allParams)) {
            $stmt->bind_param(str_repeat('s', count($allParams)), ...$allParams);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $financialData = $result->fetch_assoc();
        
        $kpis['financial'] = [
            'total_revenue' => (float)($financialData['total_revenue'] ?? 0),
            'avg_contribution' => (float)($financialData['avg_contribution'] ?? 0),
            'overdue_amount' => (float)($financialData['overdue_amount'] ?? 0)
        ];
    }
    
    // Restricted KPIs - audit and compliance data
    if (in_array('restricted', getSensitivityLevelsForRole($user['role'], 'kpis'))) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT aal.user_id) as analytics_users_24h,
                COUNT(CASE WHEN aal.action = 'EXPORT' THEN 1 END) as exports_24h,
                COUNT(CASE WHEN asa.severity = 'high' THEN 1 END) as high_security_alerts
            FROM analytics_audit_logs aal
            LEFT JOIN analytics_security_alerts asa ON asa.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            WHERE aal.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        $auditData = $result->fetch_assoc();
        
        $kpis['audit'] = [
            'analytics_users_24h' => (int)($auditData['analytics_users_24h'] ?? 0),
            'exports_24h' => (int)($auditData['exports_24h'] ?? 0),
            'security_alerts' => (int)($auditData['high_security_alerts'] ?? 0)
        ];
    }
    
    return [
        'success' => true,
        'data' => $kpis,
        'sensitivity_level' => $sensitivityLevel,
        'timestamp' => date('c')
    ];
}

/**
 * Get secure chart data
 */
function getSecureChartData($db, $securityManager, $sensitivityLevel, $params, $user) {
    $chartType = $params['chart_type'] ?? 'member_trend';
    
    // Apply center-based access control
    $centerFilter = '';
    $centerParams = [];
    
    if ($user['role'] !== 'superadmin') {
        $accessibleCenters = getAccessibleCenters($db, $user['id'], $user['role']);
        if (empty($accessibleCenters)) {
            return ['success' => true, 'data' => ['labels' => [], 'datasets' => []]];
        }
        
        $centerFilter = ' AND m.center_id IN (' . implode(',', array_fill(0, count($accessibleCenters), '?')) . ')';
        $centerParams = $accessibleCenters;
    }
    
    switch ($chartType) {
        case 'member_trend':
            return getMemberTrendChart($db, $sensitivityLevel, $centerFilter, $centerParams, $params);
            
        case 'contribution_trend':
            if (!in_array($sensitivityLevel, ['internal', 'confidential', 'restricted'])) {
                throw new Exception('Insufficient permissions for financial data');
            }
            return getContributionTrendChart($db, $sensitivityLevel, $centerFilter, $centerParams, $params);
            
        case 'demographics':
            return getDemographicsChart($db, $sensitivityLevel, $centerFilter, $centerParams, $params);
            
        case 'center_performance':
            return getCenterPerformanceChart($db, $sensitivityLevel, $centerFilter, $centerParams, $params);
            
        default:
            throw new Exception('Invalid chart type');
    }
}

/**
 * Handle export requests with security controls
 */
function handleExportRequest($db, $securityManager, $viewType, $sensitivityLevel, $data, $user, $requiresMasking) {
    // Check export permissions
    $exportPermissions = getSensitivityLevelsForRole($user['role'], 'exports');
    if (!in_array($sensitivityLevel, $exportPermissions)) {
        throw new Exception('Insufficient permissions for data export');
    }
    
    // Log export attempt
    $securityManager->logAnalyticsAccess('EXPORT', $viewType, [
        'sensitivity' => $sensitivityLevel,
        'format' => $data['format'] ?? 'csv',
        'data_size' => strlen(json_encode($data))
    ]);
    
    // Check for export rate limiting
    if (checkExportRateLimit($db, $user['id'])) {
        throw new Exception('Export rate limit exceeded. Please try again later.');
    }
    
    // Generate export data
    $exportData = generateExportData($db, $viewType, $sensitivityLevel, $data, $user);
    
    // Apply masking if required
    if ($requiresMasking) {
        $exportData = $securityManager->maskSensitiveData($exportData, $sensitivityLevel, $user['role']);
    }
    
    return [
        'success' => true,
        'data' => $exportData,
        'export_id' => generateExportId(),
        'timestamp' => date('c')
    ];
}

/**
 * Handle search requests
 */
function handleSearchRequest($db, $securityManager, $params, $user, $requiresMasking) {
    $query = $params['q'] ?? '';
    $categories = explode(',', $params['categories'] ?? 'members');
    $sensitivityLevel = $params['sensitivity'] ?? 'public';
    
    // Check search permissions
    $searchPermissions = getSensitivityLevelsForRole($user['role'], 'search');
    if (!in_array($sensitivityLevel, $searchPermissions)) {
        throw new Exception('Insufficient permissions for this search level');
    }
    
    $results = [];
    
    foreach ($categories as $category) {
        switch ($category) {
            case 'members':
                $results['members'] = searchMembers($db, $query, $sensitivityLevel, $user);
                break;
                
            case 'contributions':
                if (in_array($sensitivityLevel, ['internal', 'confidential', 'restricted'])) {
                    $results['contributions'] = searchContributions($db, $query, $sensitivityLevel, $user);
                }
                break;
                
            case 'centers':
                $results['centers'] = searchCenters($db, $query, $sensitivityLevel, $user);
                break;
        }
    }
    
    return [
        'success' => true,
        'data' => $results,
        'query' => $query,
        'categories' => $categories
    ];
}

/**
 * Handle audit requests (restricted access)
 */
function handleAuditRequest($db, $securityManager, $params, $user) {
    // Only auditors and superadmins can access audit data
    if (!in_array($user['role'], ['auditor', 'superadmin'])) {
        throw new Exception('Insufficient permissions for audit data');
    }
    
    $auditType = $params['audit_type'] ?? 'access_logs';
    
    switch ($auditType) {
        case 'access_logs':
            return getAccessLogs($db, $params);
            
        case 'security_alerts':
            return getSecurityAlerts($db, $params);
            
        case 'export_history':
            return getExportHistory($db, $params);
            
        case 'user_activity':
            return getUserActivity($db, $params);
            
        default:
            throw new Exception('Invalid audit type');
    }
}

/**
 * Helper functions
 */

function getSensitivityLevelsForRole($role, $viewType) {
    $permissions = [
        'viewer' => ['kpis' => ['public'], 'charts' => ['public'], 'reports' => [], 'exports' => [], 'search' => ['public']],
        'manager' => ['kpis' => ['public', 'internal'], 'charts' => ['public', 'internal'], 'reports' => ['public'], 'exports' => ['public'], 'search' => ['public', 'internal']],
        'admin' => ['kpis' => ['public', 'internal', 'confidential'], 'charts' => ['public', 'internal', 'confidential'], 'reports' => ['public', 'internal'], 'exports' => ['public', 'internal'], 'search' => ['public', 'internal', 'confidential']],
        'superadmin' => ['kpis' => ['public', 'internal', 'confidential', 'restricted'], 'charts' => ['public', 'internal', 'confidential', 'restricted'], 'reports' => ['public', 'internal', 'confidential', 'restricted'], 'exports' => ['public', 'internal', 'confidential', 'restricted'], 'search' => ['public', 'internal', 'confidential', 'restricted']],
        'auditor' => ['kpis' => ['public', 'internal', 'confidential', 'restricted'], 'charts' => ['public', 'internal', 'confidential', 'restricted'], 'reports' => ['public', 'internal', 'confidential', 'restricted'], 'exports' => ['restricted'], 'search' => ['public', 'internal', 'confidential', 'restricted']]
    ];
    
    return $permissions[$role][$viewType] ?? [];
}

function getAccessibleCenters($db, $userId, $userRole) {
    if ($userRole === 'superadmin') {
        $stmt = $db->prepare("SELECT id FROM centers WHERE is_active = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $centers = [];
        while ($row = $result->fetch_assoc()) {
            $centers[] = $row['id'];
        }
        return $centers;
    }
    
    // Get user's assigned centers
    $stmt = $db->prepare("
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
        $centers[] = $row['center_id'];
    }
    
    return $centers;
}

function checkExportRateLimit($db, $userId) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as export_count 
        FROM analytics_audit_logs 
        WHERE user_id = ? 
        AND action = 'EXPORT' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['export_count'] > 10; // Max 10 exports per hour
}

function generateExportId() {
    return 'exp_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
}

// Additional helper functions for chart data, search, etc. would be implemented here
// For brevity, I'm including the main structure

?>