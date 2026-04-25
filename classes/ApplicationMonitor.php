<?php
/**
 * Application Monitor
 * Tracks application-level metrics for production monitoring
 * Requirements: 5.1.1, 5.1.2
 * 
 * This class monitors:
 * - Error rates (5.1.1)
 * - Response times (5.1.2)
 * - Request counts
 * 
 * Metrics are stored in cache (Redis) or database for later analysis and alerting.
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/CacheManager.php';

class ApplicationMonitor
{
    private static $instance = null;
    private $db;
    private $conn;
    private $cache;
    
    // Metric thresholds for alerting
    private $thresholds = [
        'error_rate_percent' => 5.0,      // 5% error rate threshold
        'response_time_ms' => 1000,       // 1 second response time threshold
        'slow_response_time_ms' => 3000,  // 3 seconds slow response threshold
        'critical_error_rate' => 10.0     // 10% critical error rate
    ];
    
    // Metric storage configuration
    private $storageConfig = [
        'use_cache' => true,              // Store in Redis cache
        'use_database' => false,          // Also store in database (optional)
        'cache_ttl' => 3600,              // 1 hour cache TTL
        'aggregation_window' => 300       // 5 minutes aggregation window
    ];
    
    // Request tracking
    private $requestStartTime = null;
    private $requestId = null;
    
    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->cache = CacheManager::getInstance();
        $this->requestId = $this->generateRequestId();
        $this->requestStartTime = microtime(true);
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate unique request ID
     */
    private function generateRequestId()
    {
        return uniqid('req_', true);
    }
    
    /**
     * Track error occurrence
     * Requirement 5.1.1: Error rate monitoring
     * 
     * @param string $errorType Type of error (e.g., 'database', 'validation', 'authentication')
     * @param string $errorMessage Error message
     * @param string $severity Severity level ('low', 'medium', 'high', 'critical')
     * @param array $context Additional context information
     * @return bool Success status
     */
    public function trackError($errorType, $errorMessage, $severity = 'medium', $context = [])
    {
        $timestamp = time();
        $errorData = [
            'request_id' => $this->requestId,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'severity' => $severity,
            'context' => $context,
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Store individual error in cache
        $errorKey = "app_error_{$this->requestId}_{$timestamp}";
        $this->cache->set($errorKey, $errorData, $this->storageConfig['cache_ttl']);
        
        // Update error rate metrics
        $this->updateErrorRateMetrics($errorType, $severity);
        
        // Store in database if configured
        if ($this->storageConfig['use_database']) {
            $this->storeErrorInDatabase($errorData);
        }
        
        // Log error
        error_log("ApplicationMonitor: [{$severity}] {$errorType} - {$errorMessage}");
        
        return true;
    }
    
    /**
     * Track response time
     * Requirement 5.1.2: Response time monitoring
     * 
     * @param string $endpoint Endpoint or page identifier
     * @param float $responseTime Response time in seconds
     * @param int $statusCode HTTP status code
     * @param array $metadata Additional metadata
     * @return bool Success status
     */
    public function trackResponseTime($endpoint, $responseTime = null, $statusCode = 200, $metadata = [])
    {
        // Calculate response time if not provided
        if ($responseTime === null) {
            $responseTime = microtime(true) - $this->requestStartTime;
        }
        
        $responseTimeMs = $responseTime * 1000; // Convert to milliseconds
        $timestamp = time();
        
        $responseData = [
            'request_id' => $this->requestId,
            'endpoint' => $endpoint,
            'response_time_ms' => round($responseTimeMs, 2),
            'status_code' => $statusCode,
            'metadata' => $metadata,
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        // Store individual response time in cache
        $responseKey = "app_response_{$this->requestId}_{$timestamp}";
        $this->cache->set($responseKey, $responseData, $this->storageConfig['cache_ttl']);
        
        // Update response time metrics
        $this->updateResponseTimeMetrics($endpoint, $responseTimeMs, $statusCode);
        
        // Store in database if configured
        if ($this->storageConfig['use_database']) {
            $this->storeResponseTimeInDatabase($responseData);
        }
        
        return true;
    }
    
    /**
     * Track request count
     * 
     * @param string $endpoint Endpoint or page identifier
     * @param string $method HTTP method
     * @param int $statusCode HTTP status code
     * @return bool Success status
     */
    public function trackRequest($endpoint, $method = null, $statusCode = 200)
    {
        $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $timestamp = time();
        
        // Increment request counter
        $requestKey = "app_request_count_{$endpoint}";
        $this->cache->increment($requestKey);
        
        // Update request metrics by time window
        $this->updateRequestCountMetrics($endpoint, $method, $statusCode);
        
        return true;
    }
    
    /**
     * Update error rate metrics
     */
    private function updateErrorRateMetrics($errorType, $severity)
    {
        $window = $this->getTimeWindow();
        $key = "app_error_rate_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'total_errors' => 0,
                'total_requests' => 0,
                'error_rate' => 0.0,
                'errors_by_type' => [],
                'errors_by_severity' => [
                    'low' => 0,
                    'medium' => 0,
                    'high' => 0,
                    'critical' => 0
                ]
            ];
        }
        
        $metrics['total_errors']++;
        
        // Track by type
        if (!isset($metrics['errors_by_type'][$errorType])) {
            $metrics['errors_by_type'][$errorType] = 0;
        }
        $metrics['errors_by_type'][$errorType]++;
        
        // Track by severity
        if (isset($metrics['errors_by_severity'][$severity])) {
            $metrics['errors_by_severity'][$severity]++;
        }
        
        // Get total requests for this window
        $requestKey = "app_request_total_{$window}";
        $totalRequests = $this->cache->get($requestKey) ?? 0;
        $metrics['total_requests'] = $totalRequests;
        
        // Calculate error rate
        if ($totalRequests > 0) {
            $metrics['error_rate'] = round(($metrics['total_errors'] / $totalRequests) * 100, 2);
        }
        
        // Store updated metrics
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
    }
    
    /**
     * Update response time metrics
     */
    private function updateResponseTimeMetrics($endpoint, $responseTimeMs, $statusCode)
    {
        $window = $this->getTimeWindow();
        $key = "app_response_metrics_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'total_requests' => 0,
                'total_response_time' => 0,
                'avg_response_time_ms' => 0,
                'min_response_time_ms' => PHP_FLOAT_MAX,
                'max_response_time_ms' => 0,
                'slow_requests' => 0,
                'response_times' => [],
                'by_endpoint' => [],
                'by_status_code' => []
            ];
        }
        
        $metrics['total_requests']++;
        $metrics['total_response_time'] += $responseTimeMs;
        $metrics['avg_response_time_ms'] = round($metrics['total_response_time'] / $metrics['total_requests'], 2);
        $metrics['min_response_time_ms'] = min($metrics['min_response_time_ms'], $responseTimeMs);
        $metrics['max_response_time_ms'] = max($metrics['max_response_time_ms'], $responseTimeMs);
        
        // Track slow requests
        if ($responseTimeMs > $this->thresholds['slow_response_time_ms']) {
            $metrics['slow_requests']++;
        }
        
        // Keep last 100 response times for percentile calculations
        $metrics['response_times'][] = $responseTimeMs;
        if (count($metrics['response_times']) > 100) {
            array_shift($metrics['response_times']);
        }
        
        // Track by endpoint
        if (!isset($metrics['by_endpoint'][$endpoint])) {
            $metrics['by_endpoint'][$endpoint] = [
                'count' => 0,
                'total_time' => 0,
                'avg_time' => 0
            ];
        }
        $metrics['by_endpoint'][$endpoint]['count']++;
        $metrics['by_endpoint'][$endpoint]['total_time'] += $responseTimeMs;
        $metrics['by_endpoint'][$endpoint]['avg_time'] = round(
            $metrics['by_endpoint'][$endpoint]['total_time'] / $metrics['by_endpoint'][$endpoint]['count'],
            2
        );
        
        // Track by status code
        if (!isset($metrics['by_status_code'][$statusCode])) {
            $metrics['by_status_code'][$statusCode] = 0;
        }
        $metrics['by_status_code'][$statusCode]++;
        
        // Store updated metrics
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
    }
    
    /**
     * Update request count metrics
     */
    private function updateRequestCountMetrics($endpoint, $method, $statusCode)
    {
        $window = $this->getTimeWindow();
        $key = "app_request_metrics_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'total_requests' => 0,
                'by_endpoint' => [],
                'by_method' => [],
                'by_status_code' => []
            ];
        }
        
        $metrics['total_requests']++;
        
        // Track by endpoint
        if (!isset($metrics['by_endpoint'][$endpoint])) {
            $metrics['by_endpoint'][$endpoint] = 0;
        }
        $metrics['by_endpoint'][$endpoint]++;
        
        // Track by method
        if (!isset($metrics['by_method'][$method])) {
            $metrics['by_method'][$method] = 0;
        }
        $metrics['by_method'][$method]++;
        
        // Track by status code
        if (!isset($metrics['by_status_code'][$statusCode])) {
            $metrics['by_status_code'][$statusCode] = 0;
        }
        $metrics['by_status_code'][$statusCode]++;
        
        // Store updated metrics
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
        
        // Also update total request counter for error rate calculation
        $totalKey = "app_request_total_{$window}";
        $this->cache->increment($totalKey);
    }
    
    /**
     * Get current time window for aggregation
     */
    private function getTimeWindow()
    {
        $window = $this->storageConfig['aggregation_window'];
        return floor(time() / $window) * $window;
    }
    
    /**
     * Get error rate metrics
     * Requirement 5.1.1: Error rate monitoring
     * 
     * @param int $windowCount Number of time windows to retrieve (default: 1 = current window)
     * @return array Error rate metrics
     */
    public function getErrorRateMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "app_error_rate_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        // Calculate aggregate metrics
        if (empty($allMetrics)) {
            return [
                'current_error_rate' => 0.0,
                'total_errors' => 0,
                'total_requests' => 0,
                'threshold' => $this->thresholds['error_rate_percent'],
                'threshold_exceeded' => false,
                'windows' => []
            ];
        }
        
        $totalErrors = array_sum(array_column($allMetrics, 'total_errors'));
        $totalRequests = array_sum(array_column($allMetrics, 'total_requests'));
        $currentErrorRate = $totalRequests > 0 ? round(($totalErrors / $totalRequests) * 100, 2) : 0.0;
        
        return [
            'current_error_rate' => $currentErrorRate,
            'total_errors' => $totalErrors,
            'total_requests' => $totalRequests,
            'threshold' => $this->thresholds['error_rate_percent'],
            'threshold_exceeded' => $currentErrorRate > $this->thresholds['error_rate_percent'],
            'critical_threshold_exceeded' => $currentErrorRate > $this->thresholds['critical_error_rate'],
            'windows' => $allMetrics
        ];
    }
    
    /**
     * Get response time metrics
     * Requirement 5.1.2: Response time monitoring
     * 
     * @param int $windowCount Number of time windows to retrieve (default: 1 = current window)
     * @return array Response time metrics
     */
    public function getResponseTimeMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "app_response_metrics_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        // Calculate aggregate metrics
        if (empty($allMetrics)) {
            return [
                'avg_response_time_ms' => 0,
                'min_response_time_ms' => 0,
                'max_response_time_ms' => 0,
                'p95_response_time_ms' => 0,
                'total_requests' => 0,
                'slow_requests' => 0,
                'threshold' => $this->thresholds['response_time_ms'],
                'threshold_exceeded' => false,
                'windows' => []
            ];
        }
        
        $totalRequests = array_sum(array_column($allMetrics, 'total_requests'));
        $totalResponseTime = array_sum(array_column($allMetrics, 'total_response_time'));
        $avgResponseTime = $totalRequests > 0 ? round($totalResponseTime / $totalRequests, 2) : 0;
        
        // Collect all response times for percentile calculation
        $allResponseTimes = [];
        foreach ($allMetrics as $metric) {
            if (isset($metric['response_times'])) {
                $allResponseTimes = array_merge($allResponseTimes, $metric['response_times']);
            }
        }
        
        // Calculate 95th percentile
        sort($allResponseTimes);
        $p95Index = (int) ceil(0.95 * count($allResponseTimes)) - 1;
        $p95 = !empty($allResponseTimes) ? $allResponseTimes[max(0, $p95Index)] : 0;
        
        $minResponseTime = min(array_column($allMetrics, 'min_response_time_ms'));
        $maxResponseTime = max(array_column($allMetrics, 'max_response_time_ms'));
        $slowRequests = array_sum(array_column($allMetrics, 'slow_requests'));
        
        return [
            'avg_response_time_ms' => $avgResponseTime,
            'min_response_time_ms' => $minResponseTime,
            'max_response_time_ms' => $maxResponseTime,
            'p95_response_time_ms' => round($p95, 2),
            'total_requests' => $totalRequests,
            'slow_requests' => $slowRequests,
            'threshold' => $this->thresholds['response_time_ms'],
            'threshold_exceeded' => $avgResponseTime > $this->thresholds['response_time_ms'],
            'windows' => $allMetrics
        ];
    }
    
    /**
     * Get request count metrics
     * 
     * @param int $windowCount Number of time windows to retrieve (default: 1 = current window)
     * @return array Request count metrics
     */
    public function getRequestCountMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "app_request_metrics_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        // Calculate aggregate metrics
        if (empty($allMetrics)) {
            return [
                'total_requests' => 0,
                'requests_per_minute' => 0,
                'by_endpoint' => [],
                'by_method' => [],
                'by_status_code' => [],
                'windows' => []
            ];
        }
        
        $totalRequests = array_sum(array_column($allMetrics, 'total_requests'));
        $timeSpan = $windowCount * $window;
        $requestsPerMinute = round(($totalRequests / $timeSpan) * 60, 2);
        
        // Aggregate by endpoint, method, and status code
        $byEndpoint = [];
        $byMethod = [];
        $byStatusCode = [];
        
        foreach ($allMetrics as $metric) {
            foreach ($metric['by_endpoint'] ?? [] as $endpoint => $count) {
                $byEndpoint[$endpoint] = ($byEndpoint[$endpoint] ?? 0) + $count;
            }
            foreach ($metric['by_method'] ?? [] as $method => $count) {
                $byMethod[$method] = ($byMethod[$method] ?? 0) + $count;
            }
            foreach ($metric['by_status_code'] ?? [] as $code => $count) {
                $byStatusCode[$code] = ($byStatusCode[$code] ?? 0) + $count;
            }
        }
        
        return [
            'total_requests' => $totalRequests,
            'requests_per_minute' => $requestsPerMinute,
            'by_endpoint' => $byEndpoint,
            'by_method' => $byMethod,
            'by_status_code' => $byStatusCode,
            'windows' => $allMetrics
        ];
    }
    
    /**
     * Get comprehensive monitoring dashboard data
     * 
     * @return array Complete monitoring metrics
     */
    public function getMonitoringDashboard()
    {
        $dashboard = [
            'error_metrics' => $this->getErrorRateMetrics(12), // Last hour (12 x 5min windows)
            'response_metrics' => $this->getResponseTimeMetrics(12),
            'request_metrics' => $this->getRequestCountMetrics(12),
            'alerts' => $this->getActiveAlerts(),
            'health_status' => $this->getHealthStatus(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Include database metrics if DatabaseMonitor is available
        if (class_exists('DatabaseMonitor')) {
            try {
                require_once __DIR__ . '/DatabaseMonitor.php';
                $dbMonitor = DatabaseMonitor::getInstance();
                $dashboard['database_metrics'] = $dbMonitor->getDatabaseMonitoringDashboard();
            } catch (Exception $e) {
                error_log("ApplicationMonitor: Failed to get database metrics - " . $e->getMessage());
            }
        }
        
        return $dashboard;
    }
    
    /**
     * Get active alerts based on thresholds
     * 
     * @return array Active alerts
     */
    public function getActiveAlerts()
    {
        $alerts = [];
        
        // Check error rate
        $errorMetrics = $this->getErrorRateMetrics(1);
        if ($errorMetrics['threshold_exceeded']) {
            $alerts[] = [
                'type' => $errorMetrics['critical_threshold_exceeded'] ? 'critical' : 'warning',
                'category' => 'error_rate',
                'message' => "Error rate ({$errorMetrics['current_error_rate']}%) exceeds threshold ({$errorMetrics['threshold']}%)",
                'value' => $errorMetrics['current_error_rate'],
                'threshold' => $errorMetrics['threshold'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check response time
        $responseMetrics = $this->getResponseTimeMetrics(1);
        if ($responseMetrics['threshold_exceeded']) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'response_time',
                'message' => "Average response time ({$responseMetrics['avg_response_time_ms']}ms) exceeds threshold ({$responseMetrics['threshold']}ms)",
                'value' => $responseMetrics['avg_response_time_ms'],
                'threshold' => $responseMetrics['threshold'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check slow requests
        if ($responseMetrics['slow_requests'] > 0) {
            $alerts[] = [
                'type' => 'info',
                'category' => 'slow_requests',
                'message' => "{$responseMetrics['slow_requests']} slow requests detected (>{$this->thresholds['slow_response_time_ms']}ms)",
                'value' => $responseMetrics['slow_requests'],
                'threshold' => 0,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Get overall health status
     * 
     * @return array Health status
     */
    public function getHealthStatus()
    {
        $errorMetrics = $this->getErrorRateMetrics(1);
        $responseMetrics = $this->getResponseTimeMetrics(1);
        
        $status = 'healthy';
        $issues = [];
        
        if ($errorMetrics['critical_threshold_exceeded']) {
            $status = 'critical';
            $issues[] = 'Critical error rate';
        } elseif ($errorMetrics['threshold_exceeded']) {
            $status = 'degraded';
            $issues[] = 'High error rate';
        }
        
        if ($responseMetrics['threshold_exceeded']) {
            if ($status === 'healthy') {
                $status = 'degraded';
            }
            $issues[] = 'Slow response times';
        }
        
        return [
            'status' => $status,
            'issues' => $issues,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Store error in database (optional)
     */
    private function storeErrorInDatabase($errorData)
    {
        try {
            // This would require a monitoring_errors table
            // Implementation depends on database schema
            // For now, just log that we would store it
            error_log("Would store error in database: " . json_encode($errorData));
        } catch (Exception $e) {
            error_log("Failed to store error in database: " . $e->getMessage());
        }
    }
    
    /**
     * Store response time in database (optional)
     */
    private function storeResponseTimeInDatabase($responseData)
    {
        try {
            // This would require a monitoring_responses table
            // Implementation depends on database schema
            // For now, just log that we would store it
            error_log("Would store response time in database: " . json_encode($responseData));
        } catch (Exception $e) {
            error_log("Failed to store response time in database: " . $e->getMessage());
        }
    }
    
    /**
     * Update monitoring thresholds
     * 
     * @param array $newThresholds New threshold values
     * @return array Updated thresholds
     */
    public function updateThresholds($newThresholds)
    {
        $this->thresholds = array_merge($this->thresholds, $newThresholds);
        
        // Store in cache
        $this->cache->set('app_monitor_thresholds', $this->thresholds, 86400); // 24 hours
        
        return $this->thresholds;
    }
    
    /**
     * Get current thresholds
     * 
     * @return array Current thresholds
     */
    public function getThresholds()
    {
        return $this->thresholds;
    }
    
    /**
     * Clear all monitoring metrics
     * 
     * @return bool Success status
     */
    public function clearMetrics()
    {
        $this->cache->invalidate('app_error_*');
        $this->cache->invalidate('app_response_*');
        $this->cache->invalidate('app_request_*');
        
        return true;
    }
}
