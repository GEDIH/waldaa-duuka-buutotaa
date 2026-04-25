<?php
/**
 * Analytics Dashboard Controller
 * Main API endpoint for analytics dashboard
 * Requirements: 1.1, 1.2, 2.1, 2.2, 2.3
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

require_once __DIR__ . '/../services/CachedAnalyticsService.php';
require_once __DIR__ . '/../../classes/Auth.php';

class AnalyticsDashboardController {
    private $analytics;
    private $auth;
    
    public function __construct() {
        // Load cache configuration
        $cacheConfig = file_exists(__DIR__ . '/../../config/cache.php') 
            ? require __DIR__ . '/../../config/cache.php'
            : [];
        
        $this->analytics = new CachedAnalyticsService($cacheConfig);
        $this->auth = new Auth();
    }
    
    /**
     * Main dashboard endpoint
     * Returns complete dashboard data
     * Requirements: 1.1, 1.2
     */
    public function dashboard() {
        try {
            // Check authentication
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            // Check permissions (admin or superadmin only)
            $role = $_SESSION['role'] ?? '';
            if (!in_array($role, ['admin', 'superadmin'])) {
                return $this->error('Insufficient permissions', 403);
            }
            
            // Get complete dashboard data
            $data = $this->analytics->getDashboardData();
            
            return $this->success($data['data'], [
                'cached' => true,
                'timestamp' => $data['timestamp']
            ]);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get KPIs
     * Requirements: 1.1, 1.4
     */
    public function getKPIs() {
        try {
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            $kpis = $this->analytics->calculateKPIs();
            
            if (!$kpis['success']) {
                return $this->error($kpis['error'] ?? 'Failed to calculate KPIs', 500);
            }
            
            return $this->success($kpis['data']);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get chart data
     * Requirements: 2.1, 2.2, 2.3
     */
    public function getChartData() {
        try {
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            $type = $_GET['type'] ?? 'registration_trends';
            $period = $_GET['period'] ?? 'monthly';
            $limit = (int)($_GET['limit'] ?? 12);
            
            $data = null;
            
            switch ($type) {
                case 'registration_trends':
                    $data = $this->analytics->getRegistrationTrends($period, $limit);
                    break;
                    
                case 'contribution_trends':
                    $data = $this->analytics->getContributionTrends($period, $limit);
                    break;
                    
                case 'demographics':
                    $data = $this->analytics->getDemographics();
                    break;
                    
                case 'center_performance':
                    $data = $this->analytics->getCenterPerformance();
                    break;
                    
                case 'top_performers':
                    $metric = $_GET['metric'] ?? 'members';
                    $entity = $_GET['entity'] ?? 'centers';
                    $data = $this->analytics->getTopPerformers($entity, $metric, $limit);
                    break;
                    
                default:
                    return $this->error('Invalid chart type', 400);
            }
            
            if (!$data['success']) {
                return $this->error($data['error'] ?? 'Failed to get chart data', 500);
            }
            
            return $this->success($data['data'], [
                'type' => $type,
                'period' => $period,
                'limit' => $limit
            ]);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get member analytics
     * Requirements: 5.1
     */
    public function getMemberAnalytics() {
        try {
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            // Validate dates
            if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                return $this->error('Invalid date format', 400);
            }
            
            $data = $this->analytics->getMemberAnalytics($startDate, $endDate);
            
            if (!$data['success']) {
                return $this->error($data['error'] ?? 'Failed to get member analytics', 500);
            }
            
            return $this->success($data['data']);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get contribution analytics
     * Requirements: 6.1
     */
    public function getContributionAnalytics() {
        try {
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
                return $this->error('Invalid date format', 400);
            }
            
            $data = $this->analytics->getContributionAnalytics($startDate, $endDate);
            
            if (!$data['success']) {
                return $this->error($data['error'] ?? 'Failed to get contribution analytics', 500);
            }
            
            return $this->success($data['data']);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get alerts
     * Requirements: 1.7
     */
    public function getAlerts() {
        try {
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            $data = $this->analytics->getAlerts();
            
            if (!$data['success']) {
                return $this->error($data['error'] ?? 'Failed to get alerts', 500);
            }
            
            return $this->success($data['data'], [
                'count' => $data['count']
            ]);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Invalidate cache
     * Admin only
     */
    public function invalidateCache() {
        try {
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            // Only superadmin can invalidate cache
            if (($_SESSION['role'] ?? '') !== 'superadmin') {
                return $this->error('Insufficient permissions', 403);
            }
            
            $type = $_POST['type'] ?? 'all';
            $this->analytics->invalidateCacheByType($type);
            
            return $this->success([
                'message' => 'Cache invalidated successfully',
                'type' => $type
            ]);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Get cache statistics
     * Admin only
     */
    public function getCacheStats() {
        try {
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            if (!in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'])) {
                return $this->error('Insufficient permissions', 403);
            }
            
            $stats = $this->analytics->getCacheStats();
            
            return $this->success($stats);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Warm up cache
     * Admin only
     */
    public function warmUpCache() {
        try {
            if (!$this->auth->isAuthenticated()) {
                return $this->error('Unauthorized', 401);
            }
            
            if (($_SESSION['role'] ?? '') !== 'superadmin') {
                return $this->error('Insufficient permissions', 403);
            }
            
            $result = $this->analytics->warmUpCache();
            
            if (!$result['success']) {
                return $this->error($result['error'] ?? 'Cache warmup failed', 500);
            }
            
            return $this->success($result);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Real-time updates via Server-Sent Events (SSE)
     * Requirements: 1.2, 3.2
     */
    public function getRealtimeUpdates() {
        try {
            // Check authentication
            if (!$this->auth->isAuthenticated()) {
                http_response_code(401);
                echo "event: error\n";
                echo "data: " . json_encode(['error' => 'Unauthorized']) . "\n\n";
                flush();
                exit();
            }
            
            // Check permissions (admin or superadmin only)
            $role = $_SESSION['role'] ?? '';
            if (!in_array($role, ['admin', 'superadmin'])) {
                http_response_code(403);
                echo "event: error\n";
                echo "data: " . json_encode(['error' => 'Insufficient permissions']) . "\n\n";
                flush();
                exit();
            }
            
            // Set SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable nginx buffering
            
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Store initial data snapshot for change detection
            $lastData = null;
            $lastHeartbeat = time();
            $heartbeatInterval = 15; // Send heartbeat every 15 seconds
            $checkInterval = 5; // Check for data changes every 5 seconds
            
            // Send initial connection message
            echo "event: connected\n";
            echo "data: " . json_encode([
                'message' => 'Real-time updates connected',
                'timestamp' => time()
            ]) . "\n\n";
            flush();
            
            // Main SSE loop
            while (true) {
                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }
                
                // Get current data
                try {
                    $currentData = $this->analytics->getDashboardData();
                    
                    // Check if data has changed
                    if ($lastData === null || $this->hasDataChanged($lastData, $currentData)) {
                        // Send data update event
                        echo "event: update\n";
                        echo "data: " . json_encode([
                            'kpis' => $currentData['data']['kpis'] ?? [],
                            'timestamp' => $currentData['timestamp'],
                            'changed' => $lastData !== null
                        ]) . "\n\n";
                        flush();
                        
                        $lastData = $currentData;
                    }
                    
                } catch (Exception $e) {
                    // Send error event but continue connection
                    echo "event: error\n";
                    echo "data: " . json_encode([
                        'error' => 'Failed to fetch data',
                        'message' => $e->getMessage(),
                        'timestamp' => time()
                    ]) . "\n\n";
                    flush();
                }
                
                // Send heartbeat to keep connection alive
                $currentTime = time();
                if ($currentTime - $lastHeartbeat >= $heartbeatInterval) {
                    echo "event: heartbeat\n";
                    echo "data: " . json_encode([
                        'timestamp' => $currentTime
                    ]) . "\n\n";
                    flush();
                    
                    $lastHeartbeat = $currentTime;
                }
                
                // Sleep before next check
                sleep($checkInterval);
            }
            
        } catch (Exception $e) {
            echo "event: error\n";
            echo "data: " . json_encode([
                'error' => 'SSE connection failed',
                'message' => $e->getMessage()
            ]) . "\n\n";
            flush();
        }
        
        exit();
    }
    
    /**
     * Check if dashboard data has changed
     * Compares key metrics to detect changes
     */
    private function hasDataChanged($oldData, $newData) {
        // If no old data, consider it changed
        if ($oldData === null) {
            return true;
        }
        
        // Compare KPI values
        $oldKPIs = $oldData['data']['kpis'] ?? [];
        $newKPIs = $newData['data']['kpis'] ?? [];
        
        // Check if any KPI value has changed
        foreach ($newKPIs as $key => $newKPI) {
            $oldKPI = $oldKPIs[$key] ?? null;
            
            if ($oldKPI === null) {
                return true; // New KPI added
            }
            
            // Compare values
            if (isset($newKPI['value']) && isset($oldKPI['value'])) {
                if ($newKPI['value'] !== $oldKPI['value']) {
                    return true;
                }
            }
        }
        
        // Check if timestamp difference is significant (more than 30 seconds)
        $oldTimestamp = $oldData['timestamp'] ?? 0;
        $newTimestamp = $newData['timestamp'] ?? 0;
        
        if (abs($newTimestamp - $oldTimestamp) > 30) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate date format
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Success response
     */
    private function success($data, $meta = []) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'meta' => $meta,
            'timestamp' => time()
        ]);
        exit();
    }
    
    /**
     * Error response
     */
    private function error($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code,
            'timestamp' => time()
        ]);
        exit();
    }
    
    /**
     * Route request to appropriate method
     */
    public function route() {
        $action = $_GET['action'] ?? 'dashboard';
        
        switch ($action) {
            case 'dashboard':
                return $this->dashboard();
                
            case 'kpis':
                return $this->getKPIs();
                
            case 'chart':
                return $this->getChartData();
                
            case 'members':
                return $this->getMemberAnalytics();
                
            case 'contributions':
                return $this->getContributionAnalytics();
                
            case 'alerts':
                return $this->getAlerts();
                
            case 'cache_stats':
                return $this->getCacheStats();
                
            case 'invalidate_cache':
                return $this->invalidateCache();
                
            case 'warmup_cache':
                return $this->warmUpCache();
                
            case 'realtime':
                return $this->getRealtimeUpdates();
                
            default:
                return $this->error('Invalid action', 400);
        }
    }
}

// Handle request
session_start();
$controller = new AnalyticsDashboardController();
$controller->route();
?>
