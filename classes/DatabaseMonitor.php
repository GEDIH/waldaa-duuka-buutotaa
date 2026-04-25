<?php
/**
 * Database Monitor
 * Tracks database-specific metrics for production monitoring
 * Requirements: 5.1.3
 * 
 * This class monitors:
 * - Connection count
 * - Slow queries (>2 seconds)
 * - Query errors
 * - Connection failures
 * 
 * Integrates with ApplicationMonitor for comprehensive monitoring.
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/CacheManager.php';

class DatabaseMonitor
{
    private static $instance = null;
    private $db;
    private $conn;
    private $cache;
    
    // Monitoring thresholds
    private $thresholds = [
        'slow_query_ms' => 2000,          // 2 seconds for slow query
        'max_connections' => 100,         // Maximum connection count
        'connection_failure_rate' => 5.0, // 5% connection failure rate
        'query_error_rate' => 2.0         // 2% query error rate
    ];
    
    // Storage configuration
    private $storageConfig = [
        'use_cache' => true,
        'cache_ttl' => 3600,              // 1 hour
        'aggregation_window' => 300       // 5 minutes
    ];
    
    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = CacheManager::getInstance();
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
     * Track database connection count
     * Requirement 5.1.3: Database connection monitoring
     * 
     * @return array Connection metrics
     */
    public function trackConnectionCount()
    {
        try {
            $this->conn = $this->db->getConnection();
            
            // Get current connection count from MySQL
            $stmt = $this->conn->query("SHOW STATUS LIKE 'Threads_connected'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentConnections = (int) $result['Value'];
            
            // Get max connections setting
            $stmt = $this->conn->query("SHOW VARIABLES LIKE 'max_connections'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $maxConnections = (int) $result['Value'];
            
            // Calculate connection usage percentage
            $usagePercent = round(($currentConnections / $maxConnections) * 100, 2);
            
            $timestamp = time();
            $connectionData = [
                'current_connections' => $currentConnections,
                'max_connections' => $maxConnections,
                'usage_percent' => $usagePercent,
                'threshold_exceeded' => $currentConnections > $this->thresholds['max_connections'],
                'timestamp' => $timestamp,
                'datetime' => date('Y-m-d H:i:s', $timestamp)
            ];
            
            // Store in cache
            $window = $this->getTimeWindow();
            $key = "db_connections_{$window}";
            $this->cache->set($key, $connectionData, $this->storageConfig['cache_ttl']);
            
            // Update aggregated metrics
            $this->updateConnectionMetrics($connectionData);
            
            return $connectionData;
            
        } catch (Exception $e) {
            error_log("DatabaseMonitor: Failed to track connection count - " . $e->getMessage());
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    
    /**
     * Track slow query
     * Requirement 5.1.3: Slow query monitoring (>2 seconds)
     * 
     * @param string $query SQL query
     * @param float $executionTime Execution time in seconds
     * @param array $params Query parameters
     * @param array $context Additional context
     * @return bool Success status
     */
    public function trackSlowQuery($query, $executionTime, $params = [], $context = [])
    {
        $executionTimeMs = $executionTime * 1000;
        
        // Only track if it's actually slow
        if ($executionTimeMs < $this->thresholds['slow_query_ms']) {
            return false;
        }
        
        $timestamp = time();
        $slowQueryData = [
            'query' => $this->sanitizeQuery($query),
            'execution_time_ms' => round($executionTimeMs, 2),
            'params' => $params,
            'context' => $context,
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        // Store individual slow query
        $queryKey = "db_slow_query_" . uniqid('', true);
        $this->cache->set($queryKey, $slowQueryData, $this->storageConfig['cache_ttl']);
        
        // Update slow query metrics
        $this->updateSlowQueryMetrics($slowQueryData);
        
        // Log slow query
        error_log("DatabaseMonitor: Slow query detected ({$executionTimeMs}ms) - " . substr($query, 0, 100));
        
        return true;
    }
    
    /**
     * Track query error
     * Requirement 5.1.3: Query error monitoring
     * 
     * @param string $query SQL query
     * @param string $errorMessage Error message
     * @param string $errorCode Error code
     * @param array $params Query parameters
     * @return bool Success status
     */
    public function trackQueryError($query, $errorMessage, $errorCode = '', $params = [])
    {
        $timestamp = time();
        $errorData = [
            'query' => $this->sanitizeQuery($query),
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'params' => $params,
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        // Store individual query error
        $errorKey = "db_query_error_" . uniqid('', true);
        $this->cache->set($errorKey, $errorData, $this->storageConfig['cache_ttl']);
        
        // Update query error metrics
        $this->updateQueryErrorMetrics($errorData);
        
        // Log query error
        error_log("DatabaseMonitor: Query error - {$errorCode}: {$errorMessage}");
        
        return true;
    }

    
    /**
     * Track connection failure
     * Requirement 5.1.3: Connection failure monitoring and alerting
     * 
     * @param string $errorMessage Error message
     * @param array $context Additional context
     * @return bool Success status
     */
    public function trackConnectionFailure($errorMessage, $context = [])
    {
        $timestamp = time();
        $failureData = [
            'error_message' => $errorMessage,
            'context' => $context,
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Store individual connection failure
        $failureKey = "db_connection_failure_" . uniqid('', true);
        $this->cache->set($failureKey, $failureData, $this->storageConfig['cache_ttl']);
        
        // Update connection failure metrics
        $this->updateConnectionFailureMetrics($failureData);
        
        // Log connection failure
        error_log("DatabaseMonitor: Connection failure - {$errorMessage}");
        
        // Trigger alert for connection failures
        $this->triggerConnectionFailureAlert($failureData);
        
        return true;
    }
    
    /**
     * Update connection metrics
     */
    private function updateConnectionMetrics($connectionData)
    {
        $window = $this->getTimeWindow();
        $key = "db_connection_metrics_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'samples' => 0,
                'avg_connections' => 0,
                'max_connections_seen' => 0,
                'peak_usage_percent' => 0,
                'threshold_exceeded_count' => 0
            ];
        }
        
        $metrics['samples']++;
        $metrics['avg_connections'] = round(
            (($metrics['avg_connections'] * ($metrics['samples'] - 1)) + $connectionData['current_connections']) / $metrics['samples'],
            2
        );
        $metrics['max_connections_seen'] = max($metrics['max_connections_seen'], $connectionData['current_connections']);
        $metrics['peak_usage_percent'] = max($metrics['peak_usage_percent'], $connectionData['usage_percent']);
        
        if ($connectionData['threshold_exceeded']) {
            $metrics['threshold_exceeded_count']++;
        }
        
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
    }

    
    /**
     * Update slow query metrics
     */
    private function updateSlowQueryMetrics($slowQueryData)
    {
        $window = $this->getTimeWindow();
        $key = "db_slow_query_metrics_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'total_slow_queries' => 0,
                'total_queries' => 0,
                'slow_query_rate' => 0.0,
                'avg_execution_time_ms' => 0,
                'max_execution_time_ms' => 0,
                'slow_queries' => []
            ];
        }
        
        $metrics['total_slow_queries']++;
        
        // Calculate average execution time
        $metrics['avg_execution_time_ms'] = round(
            (($metrics['avg_execution_time_ms'] * ($metrics['total_slow_queries'] - 1)) + $slowQueryData['execution_time_ms']) / $metrics['total_slow_queries'],
            2
        );
        
        $metrics['max_execution_time_ms'] = max($metrics['max_execution_time_ms'], $slowQueryData['execution_time_ms']);
        
        // Keep last 10 slow queries
        $metrics['slow_queries'][] = [
            'query' => substr($slowQueryData['query'], 0, 200),
            'execution_time_ms' => $slowQueryData['execution_time_ms'],
            'datetime' => $slowQueryData['datetime']
        ];
        if (count($metrics['slow_queries']) > 10) {
            array_shift($metrics['slow_queries']);
        }
        
        // Get total query count
        $totalQueryKey = "db_total_queries_{$window}";
        $totalQueries = $this->cache->get($totalQueryKey) ?? 0;
        $metrics['total_queries'] = $totalQueries;
        
        // Calculate slow query rate
        if ($totalQueries > 0) {
            $metrics['slow_query_rate'] = round(($metrics['total_slow_queries'] / $totalQueries) * 100, 2);
        }
        
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
    }
    
    /**
     * Update query error metrics
     */
    private function updateQueryErrorMetrics($errorData)
    {
        $window = $this->getTimeWindow();
        $key = "db_query_error_metrics_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'total_errors' => 0,
                'total_queries' => 0,
                'error_rate' => 0.0,
                'errors_by_code' => [],
                'recent_errors' => []
            ];
        }
        
        $metrics['total_errors']++;
        
        // Track by error code
        $errorCode = $errorData['error_code'] ?: 'unknown';
        if (!isset($metrics['errors_by_code'][$errorCode])) {
            $metrics['errors_by_code'][$errorCode] = 0;
        }
        $metrics['errors_by_code'][$errorCode]++;
        
        // Keep last 10 errors
        $metrics['recent_errors'][] = [
            'error_code' => $errorCode,
            'error_message' => substr($errorData['error_message'], 0, 200),
            'datetime' => $errorData['datetime']
        ];
        if (count($metrics['recent_errors']) > 10) {
            array_shift($metrics['recent_errors']);
        }
        
        // Get total query count
        $totalQueryKey = "db_total_queries_{$window}";
        $totalQueries = $this->cache->get($totalQueryKey) ?? 0;
        $metrics['total_queries'] = $totalQueries;
        
        // Calculate error rate
        if ($totalQueries > 0) {
            $metrics['error_rate'] = round(($metrics['total_errors'] / $totalQueries) * 100, 2);
        }
        
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
    }

    
    /**
     * Update connection failure metrics
     */
    private function updateConnectionFailureMetrics($failureData)
    {
        $window = $this->getTimeWindow();
        $key = "db_connection_failure_metrics_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'total_failures' => 0,
                'total_attempts' => 0,
                'failure_rate' => 0.0,
                'recent_failures' => []
            ];
        }
        
        $metrics['total_failures']++;
        
        // Keep last 10 failures
        $metrics['recent_failures'][] = [
            'error_message' => substr($failureData['error_message'], 0, 200),
            'datetime' => $failureData['datetime']
        ];
        if (count($metrics['recent_failures']) > 10) {
            array_shift($metrics['recent_failures']);
        }
        
        // Get total connection attempts
        $totalAttemptsKey = "db_connection_attempts_{$window}";
        $totalAttempts = $this->cache->get($totalAttemptsKey) ?? 0;
        $metrics['total_attempts'] = $totalAttempts;
        
        // Calculate failure rate
        if ($totalAttempts > 0) {
            $metrics['failure_rate'] = round(($metrics['total_failures'] / $totalAttempts) * 100, 2);
        }
        
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
    }
    
    /**
     * Increment total query count
     * Should be called for every query executed
     * 
     * @return bool Success status
     */
    public function incrementQueryCount()
    {
        $window = $this->getTimeWindow();
        $key = "db_total_queries_{$window}";
        
        $count = $this->cache->get($key) ?? 0;
        $this->cache->set($key, $count + 1, $this->storageConfig['aggregation_window']);
        
        return true;
    }
    
    /**
     * Increment connection attempt count
     * Should be called for every connection attempt
     * 
     * @return bool Success status
     */
    public function incrementConnectionAttempt()
    {
        $window = $this->getTimeWindow();
        $key = "db_connection_attempts_{$window}";
        
        $count = $this->cache->get($key) ?? 0;
        $this->cache->set($key, $count + 1, $this->storageConfig['aggregation_window']);
        
        return true;
    }

    
    /**
     * Get connection metrics
     * 
     * @param int $windowCount Number of time windows to retrieve
     * @return array Connection metrics
     */
    public function getConnectionMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "db_connection_metrics_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        if (empty($allMetrics)) {
            return [
                'avg_connections' => 0,
                'max_connections_seen' => 0,
                'peak_usage_percent' => 0,
                'threshold_exceeded' => false,
                'windows' => []
            ];
        }
        
        $avgConnections = round(array_sum(array_column($allMetrics, 'avg_connections')) / count($allMetrics), 2);
        $maxConnections = max(array_column($allMetrics, 'max_connections_seen'));
        $peakUsage = max(array_column($allMetrics, 'peak_usage_percent'));
        $thresholdExceeded = array_sum(array_column($allMetrics, 'threshold_exceeded_count')) > 0;
        
        return [
            'avg_connections' => $avgConnections,
            'max_connections_seen' => $maxConnections,
            'peak_usage_percent' => $peakUsage,
            'threshold' => $this->thresholds['max_connections'],
            'threshold_exceeded' => $thresholdExceeded,
            'windows' => $allMetrics
        ];
    }
    
    /**
     * Get slow query metrics
     * 
     * @param int $windowCount Number of time windows to retrieve
     * @return array Slow query metrics
     */
    public function getSlowQueryMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "db_slow_query_metrics_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        if (empty($allMetrics)) {
            return [
                'total_slow_queries' => 0,
                'slow_query_rate' => 0.0,
                'avg_execution_time_ms' => 0,
                'max_execution_time_ms' => 0,
                'threshold' => $this->thresholds['slow_query_ms'],
                'recent_slow_queries' => [],
                'windows' => []
            ];
        }
        
        $totalSlowQueries = array_sum(array_column($allMetrics, 'total_slow_queries'));
        $totalQueries = array_sum(array_column($allMetrics, 'total_queries'));
        $slowQueryRate = $totalQueries > 0 ? round(($totalSlowQueries / $totalQueries) * 100, 2) : 0.0;
        
        // Collect recent slow queries
        $recentSlowQueries = [];
        foreach ($allMetrics as $metric) {
            if (isset($metric['slow_queries'])) {
                $recentSlowQueries = array_merge($recentSlowQueries, $metric['slow_queries']);
            }
        }
        
        return [
            'total_slow_queries' => $totalSlowQueries,
            'slow_query_rate' => $slowQueryRate,
            'avg_execution_time_ms' => round(array_sum(array_column($allMetrics, 'avg_execution_time_ms')) / count($allMetrics), 2),
            'max_execution_time_ms' => max(array_column($allMetrics, 'max_execution_time_ms')),
            'threshold' => $this->thresholds['slow_query_ms'],
            'recent_slow_queries' => array_slice($recentSlowQueries, -10),
            'windows' => $allMetrics
        ];
    }

    
    /**
     * Get query error metrics
     * 
     * @param int $windowCount Number of time windows to retrieve
     * @return array Query error metrics
     */
    public function getQueryErrorMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "db_query_error_metrics_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        if (empty($allMetrics)) {
            return [
                'total_errors' => 0,
                'error_rate' => 0.0,
                'threshold' => $this->thresholds['query_error_rate'],
                'threshold_exceeded' => false,
                'errors_by_code' => [],
                'recent_errors' => [],
                'windows' => []
            ];
        }
        
        $totalErrors = array_sum(array_column($allMetrics, 'total_errors'));
        $totalQueries = array_sum(array_column($allMetrics, 'total_queries'));
        $errorRate = $totalQueries > 0 ? round(($totalErrors / $totalQueries) * 100, 2) : 0.0;
        
        // Aggregate errors by code
        $errorsByCode = [];
        foreach ($allMetrics as $metric) {
            foreach ($metric['errors_by_code'] ?? [] as $code => $count) {
                $errorsByCode[$code] = ($errorsByCode[$code] ?? 0) + $count;
            }
        }
        
        // Collect recent errors
        $recentErrors = [];
        foreach ($allMetrics as $metric) {
            if (isset($metric['recent_errors'])) {
                $recentErrors = array_merge($recentErrors, $metric['recent_errors']);
            }
        }
        
        return [
            'total_errors' => $totalErrors,
            'error_rate' => $errorRate,
            'threshold' => $this->thresholds['query_error_rate'],
            'threshold_exceeded' => $errorRate > $this->thresholds['query_error_rate'],
            'errors_by_code' => $errorsByCode,
            'recent_errors' => array_slice($recentErrors, -10),
            'windows' => $allMetrics
        ];
    }
    
    /**
     * Get connection failure metrics
     * 
     * @param int $windowCount Number of time windows to retrieve
     * @return array Connection failure metrics
     */
    public function getConnectionFailureMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "db_connection_failure_metrics_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        if (empty($allMetrics)) {
            return [
                'total_failures' => 0,
                'failure_rate' => 0.0,
                'threshold' => $this->thresholds['connection_failure_rate'],
                'threshold_exceeded' => false,
                'recent_failures' => [],
                'windows' => []
            ];
        }
        
        $totalFailures = array_sum(array_column($allMetrics, 'total_failures'));
        $totalAttempts = array_sum(array_column($allMetrics, 'total_attempts'));
        $failureRate = $totalAttempts > 0 ? round(($totalFailures / $totalAttempts) * 100, 2) : 0.0;
        
        // Collect recent failures
        $recentFailures = [];
        foreach ($allMetrics as $metric) {
            if (isset($metric['recent_failures'])) {
                $recentFailures = array_merge($recentFailures, $metric['recent_failures']);
            }
        }
        
        return [
            'total_failures' => $totalFailures,
            'failure_rate' => $failureRate,
            'threshold' => $this->thresholds['connection_failure_rate'],
            'threshold_exceeded' => $failureRate > $this->thresholds['connection_failure_rate'],
            'recent_failures' => array_slice($recentFailures, -10),
            'windows' => $allMetrics
        ];
    }

    
    /**
     * Get comprehensive database monitoring dashboard
     * 
     * @return array Complete database metrics
     */
    public function getDatabaseMonitoringDashboard()
    {
        return [
            'connection_metrics' => $this->getConnectionMetrics(12), // Last hour
            'slow_query_metrics' => $this->getSlowQueryMetrics(12),
            'query_error_metrics' => $this->getQueryErrorMetrics(12),
            'connection_failure_metrics' => $this->getConnectionFailureMetrics(12),
            'alerts' => $this->getDatabaseAlerts(),
            'health_status' => $this->getDatabaseHealthStatus(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get active database alerts
     * 
     * @return array Active alerts
     */
    public function getDatabaseAlerts()
    {
        $alerts = [];
        
        // Check connection metrics
        $connectionMetrics = $this->getConnectionMetrics(1);
        if ($connectionMetrics['threshold_exceeded']) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'database_connections',
                'message' => "Database connection count ({$connectionMetrics['max_connections_seen']}) exceeds threshold ({$connectionMetrics['threshold']})",
                'value' => $connectionMetrics['max_connections_seen'],
                'threshold' => $connectionMetrics['threshold'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check slow queries
        $slowQueryMetrics = $this->getSlowQueryMetrics(1);
        if ($slowQueryMetrics['total_slow_queries'] > 0) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'slow_queries',
                'message' => "{$slowQueryMetrics['total_slow_queries']} slow queries detected (>{$slowQueryMetrics['threshold']}ms)",
                'value' => $slowQueryMetrics['total_slow_queries'],
                'threshold' => $slowQueryMetrics['threshold'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check query errors
        $errorMetrics = $this->getQueryErrorMetrics(1);
        if ($errorMetrics['threshold_exceeded']) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'query_errors',
                'message' => "Query error rate ({$errorMetrics['error_rate']}%) exceeds threshold ({$errorMetrics['threshold']}%)",
                'value' => $errorMetrics['error_rate'],
                'threshold' => $errorMetrics['threshold'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check connection failures
        $failureMetrics = $this->getConnectionFailureMetrics(1);
        if ($failureMetrics['threshold_exceeded']) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'connection_failures',
                'message' => "Connection failure rate ({$failureMetrics['failure_rate']}%) exceeds threshold ({$failureMetrics['threshold']}%)",
                'value' => $failureMetrics['failure_rate'],
                'threshold' => $failureMetrics['threshold'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Get database health status
     * 
     * @return array Health status
     */
    public function getDatabaseHealthStatus()
    {
        $connectionMetrics = $this->getConnectionMetrics(1);
        $slowQueryMetrics = $this->getSlowQueryMetrics(1);
        $errorMetrics = $this->getQueryErrorMetrics(1);
        $failureMetrics = $this->getConnectionFailureMetrics(1);
        
        $status = 'healthy';
        $issues = [];
        
        if ($failureMetrics['threshold_exceeded']) {
            $status = 'critical';
            $issues[] = 'High connection failure rate';
        }
        
        if ($errorMetrics['threshold_exceeded']) {
            if ($status === 'healthy') {
                $status = 'critical';
            }
            $issues[] = 'High query error rate';
        }
        
        if ($connectionMetrics['threshold_exceeded']) {
            if ($status === 'healthy') {
                $status = 'degraded';
            }
            $issues[] = 'High connection count';
        }
        
        if ($slowQueryMetrics['total_slow_queries'] > 5) {
            if ($status === 'healthy') {
                $status = 'degraded';
            }
            $issues[] = 'Multiple slow queries';
        }
        
        return [
            'status' => $status,
            'issues' => $issues,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    
    /**
     * Trigger connection failure alert
     * Requirement 5.1.3: Alert on connection failures
     * 
     * @param array $failureData Failure data
     * @return bool Success status
     */
    private function triggerConnectionFailureAlert($failureData)
    {
        // Store alert in cache for immediate retrieval
        $alertKey = "db_alert_connection_failure_" . time();
        $alert = [
            'type' => 'critical',
            'category' => 'database_connection_failure',
            'message' => 'Database connection failure detected',
            'error_message' => $failureData['error_message'],
            'timestamp' => $failureData['timestamp'],
            'datetime' => $failureData['datetime']
        ];
        
        $this->cache->set($alertKey, $alert, 3600); // Store for 1 hour
        
        // Log critical alert
        error_log("DatabaseMonitor: CRITICAL ALERT - Connection failure: {$failureData['error_message']}");
        
        // If AlertManager exists, send notification
        if (class_exists('AlertManager')) {
            try {
                $alertManager = AlertManager::getInstance();
                $alertManager->sendAlert(
                    'database_connection_failure',
                    'critical',
                    'Database connection failure detected',
                    $failureData
                );
            } catch (Exception $e) {
                error_log("DatabaseMonitor: Failed to send alert via AlertManager - " . $e->getMessage());
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize query for logging (remove sensitive data)
     * 
     * @param string $query SQL query
     * @return string Sanitized query
     */
    private function sanitizeQuery($query)
    {
        // Remove potential sensitive data from query
        $query = preg_replace('/password\s*=\s*[\'"][^\'"]*[\'"]/i', 'password=***', $query);
        $query = preg_replace('/token\s*=\s*[\'"][^\'"]*[\'"]/i', 'token=***', $query);
        
        return $query;
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
     * Update monitoring thresholds
     * 
     * @param array $newThresholds New threshold values
     * @return array Updated thresholds
     */
    public function updateThresholds($newThresholds)
    {
        $this->thresholds = array_merge($this->thresholds, $newThresholds);
        
        // Store in cache
        $this->cache->set('db_monitor_thresholds', $this->thresholds, 86400); // 24 hours
        
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
     * Clear all database monitoring metrics
     * 
     * @return bool Success status
     */
    public function clearMetrics()
    {
        $this->cache->invalidate('db_connections_*');
        $this->cache->invalidate('db_slow_query_*');
        $this->cache->invalidate('db_query_error_*');
        $this->cache->invalidate('db_connection_failure_*');
        $this->cache->invalidate('db_total_queries_*');
        $this->cache->invalidate('db_connection_attempts_*');
        
        return true;
    }
}

