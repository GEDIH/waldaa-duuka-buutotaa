<?php
/**
 * Performance Monitor
 * Tracks and analyzes system performance metrics
 * Requirements: 11.1, 11.2, 11.5
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/CacheManager.php';

class PerformanceMonitor
{
    private static $instance = null;
    private $db;
    private $conn;
    private $cache;
    
    // Performance thresholds
    private $thresholds = [
        'page_load_time_ms' => 3000,        // 3 seconds
        'query_execution_time_ms' => 1000,  // 1 second
        'api_response_time_ms' => 500,      // 500ms
        'cache_hit_rate' => 80,             // 80%
        'memory_usage_mb' => 512,           // 512MB
        'cpu_usage_percent' => 80           // 80%
    ];
    
    // Metric storage
    private $metrics = [];
    private $startTime = null;
    
    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->cache = CacheManager::getInstance();
        $this->startTime = microtime(true);
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
     * Start tracking a metric
     */
    public function startMetric($metricName)
    {
        $this->metrics[$metricName] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];
    }
    
    /**
     * End tracking a metric
     */
    public function endMetric($metricName)
    {
        if (!isset($this->metrics[$metricName])) {
            return null;
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $metric = $this->metrics[$metricName];
        $duration = ($endTime - $metric['start_time']) * 1000; // Convert to ms
        $memoryUsed = ($endMemory - $metric['start_memory']) / 1024 / 1024; // Convert to MB
        
        $this->metrics[$metricName]['duration_ms'] = $duration;
        $this->metrics[$metricName]['memory_used_mb'] = $memoryUsed;
        $this->metrics[$metricName]['end_time'] = $endTime;
        
        // Store in cache for aggregation
        $this->storeMetric($metricName, $duration, $memoryUsed);
        
        return [
            'duration_ms' => $duration,
            'memory_used_mb' => $memoryUsed
        ];
    }
    
    /**
     * Track page load time
     */
    public function trackPageLoad($pageName)
    {
        $loadTime = (microtime(true) - $this->startTime) * 1000;
        $memoryUsed = memory_get_usage(true) / 1024 / 1024;
        
        $this->storeMetric("page_load_{$pageName}", $loadTime, $memoryUsed);
        
        return [
            'page' => $pageName,
            'load_time_ms' => $loadTime,
            'memory_used_mb' => $memoryUsed,
            'threshold_exceeded' => $loadTime > $this->thresholds['page_load_time_ms']
        ];
    }
    
    /**
     * Track query execution time
     */
    public function trackQuery($queryName, $duration)
    {
        $durationMs = $duration * 1000;
        $this->storeMetric("query_{$queryName}", $durationMs, 0);
        
        return [
            'query' => $queryName,
            'duration_ms' => $durationMs,
            'threshold_exceeded' => $durationMs > $this->thresholds['query_execution_time_ms']
        ];
    }
    
    /**
     * Track API response time
     */
    public function trackAPIResponse($endpoint, $duration)
    {
        $durationMs = $duration * 1000;
        $this->storeMetric("api_{$endpoint}", $durationMs, 0);
        
        return [
            'endpoint' => $endpoint,
            'duration_ms' => $durationMs,
            'threshold_exceeded' => $durationMs > $this->thresholds['api_response_time_ms']
        ];
    }
    
    /**
     * Store metric in cache for aggregation
     */
    private function storeMetric($metricName, $duration, $memoryUsed)
    {
        $timestamp = time();
        $key = "perf_metric_{$metricName}_{$timestamp}";
        
        $data = [
            'metric_name' => $metricName,
            'duration_ms' => $duration,
            'memory_used_mb' => $memoryUsed,
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s')
        ];
        
        // Store with 1 hour TTL
        $this->cache->set($key, $data, 3600);
        
        // Also add to aggregated metrics
        $this->aggregateMetric($metricName, $duration);
    }
    
    /**
     * Aggregate metric for statistics
     */
    private function aggregateMetric($metricName, $value)
    {
        $key = "perf_agg_{$metricName}";
        $stats = $this->cache->get($key);
        
        if (!$stats) {
            $stats = [
                'count' => 0,
                'total' => 0,
                'min' => PHP_FLOAT_MAX,
                'max' => 0,
                'values' => []
            ];
        }
        
        $stats['count']++;
        $stats['total'] += $value;
        $stats['min'] = min($stats['min'], $value);
        $stats['max'] = max($stats['max'], $value);
        
        // Keep last 100 values for percentile calculations
        $stats['values'][] = $value;
        if (count($stats['values']) > 100) {
            array_shift($stats['values']);
        }
        
        // Store with 1 hour TTL
        $this->cache->set($key, $stats, 3600);
    }
    
    /**
     * Get all performance metrics
     */
    public function getPerformanceMetrics()
    {
        return [
            'page_load_metrics' => $this->getPageLoadMetrics(),
            'query_metrics' => $this->getQueryMetrics(),
            'api_metrics' => $this->getAPIMetrics(),
            'cache_metrics' => $this->getCacheMetrics(),
            'system_metrics' => $this->getSystemMetrics(),
            'alerts' => $this->getPerformanceAlerts(),
            'recommendations' => $this->getOptimizationRecommendations(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get page load metrics
     */
    private function getPageLoadMetrics()
    {
        $metrics = $this->getAggregatedMetrics('page_load_');
        
        return [
            'avg_load_time_ms' => $metrics['avg'] ?? 0,
            'min_load_time_ms' => $metrics['min'] ?? 0,
            'max_load_time_ms' => $metrics['max'] ?? 0,
            'p95_load_time_ms' => $metrics['p95'] ?? 0,
            'total_page_loads' => $metrics['count'] ?? 0,
            'threshold' => $this->thresholds['page_load_time_ms'],
            'threshold_exceeded_count' => $this->countThresholdExceeded('page_load_', $this->thresholds['page_load_time_ms'])
        ];
    }
    
    /**
     * Get query execution metrics
     */
    private function getQueryMetrics()
    {
        $metrics = $this->getAggregatedMetrics('query_');
        
        return [
            'avg_query_time_ms' => $metrics['avg'] ?? 0,
            'min_query_time_ms' => $metrics['min'] ?? 0,
            'max_query_time_ms' => $metrics['max'] ?? 0,
            'p95_query_time_ms' => $metrics['p95'] ?? 0,
            'total_queries' => $metrics['count'] ?? 0,
            'slow_queries_count' => $this->countThresholdExceeded('query_', $this->thresholds['query_execution_time_ms']),
            'threshold' => $this->thresholds['query_execution_time_ms']
        ];
    }
    
    /**
     * Get API response metrics
     */
    private function getAPIMetrics()
    {
        $metrics = $this->getAggregatedMetrics('api_');
        
        return [
            'avg_response_time_ms' => $metrics['avg'] ?? 0,
            'min_response_time_ms' => $metrics['min'] ?? 0,
            'max_response_time_ms' => $metrics['max'] ?? 0,
            'p95_response_time_ms' => $metrics['p95'] ?? 0,
            'total_requests' => $metrics['count'] ?? 0,
            'slow_requests_count' => $this->countThresholdExceeded('api_', $this->thresholds['api_response_time_ms']),
            'threshold' => $this->thresholds['api_response_time_ms']
        ];
    }
    
    /**
     * Get cache performance metrics
     */
    private function getCacheMetrics()
    {
        $cacheStats = $this->cache->getStats();
        
        $hitRate = $cacheStats['hit_rate'] ?? 0;
        
        return [
            'hit_rate' => $hitRate,
            'miss_rate' => 100 - $hitRate,
            'total_keys' => $cacheStats['keys'] ?? 0,
            'memory_used' => $cacheStats['memory_used'] ?? 'N/A',
            'cache_enabled' => $cacheStats['enabled'] ?? false,
            'threshold' => $this->thresholds['cache_hit_rate'],
            'threshold_met' => $hitRate >= $this->thresholds['cache_hit_rate']
        ];
    }
    
    /**
     * Get system resource metrics
     */
    private function getSystemMetrics()
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        $memoryPeak = memory_get_peak_usage(true) / 1024 / 1024; // MB
        
        return [
            'memory_usage_mb' => round($memoryUsage, 2),
            'memory_peak_mb' => round($memoryPeak, 2),
            'memory_limit' => ini_get('memory_limit'),
            'php_version' => PHP_VERSION,
            'server_load' => $this->getServerLoad(),
            'uptime_seconds' => $this->getUptime()
        ];
    }
    
    /**
     * Get aggregated metrics for a prefix
     */
    private function getAggregatedMetrics($prefix)
    {
        // Get all metrics matching prefix from cache
        $allMetrics = [];
        
        // This is a simplified version - in production, you'd scan cache keys
        // For now, we'll use the aggregated stats
        $key = "perf_agg_{$prefix}*";
        
        // Get aggregated stats for common metrics
        $commonMetrics = ['dashboard', 'kpis', 'chart_data', 'export'];
        $totalCount = 0;
        $totalSum = 0;
        $minValue = PHP_FLOAT_MAX;
        $maxValue = 0;
        $allValues = [];
        
        foreach ($commonMetrics as $metric) {
            $stats = $this->cache->get("perf_agg_{$prefix}{$metric}");
            if ($stats) {
                $totalCount += $stats['count'];
                $totalSum += $stats['total'];
                $minValue = min($minValue, $stats['min']);
                $maxValue = max($maxValue, $stats['max']);
                $allValues = array_merge($allValues, $stats['values'] ?? []);
            }
        }
        
        if ($totalCount === 0) {
            return [
                'count' => 0,
                'avg' => 0,
                'min' => 0,
                'max' => 0,
                'p95' => 0
            ];
        }
        
        $avg = $totalSum / $totalCount;
        
        // Calculate 95th percentile
        sort($allValues);
        $p95Index = (int) ceil(0.95 * count($allValues)) - 1;
        $p95 = $allValues[$p95Index] ?? $maxValue;
        
        return [
            'count' => $totalCount,
            'avg' => round($avg, 2),
            'min' => round($minValue, 2),
            'max' => round($maxValue, 2),
            'p95' => round($p95, 2)
        ];
    }
    
    /**
     * Count how many times threshold was exceeded
     */
    private function countThresholdExceeded($prefix, $threshold)
    {
        $commonMetrics = ['dashboard', 'kpis', 'chart_data', 'export'];
        $exceededCount = 0;
        
        foreach ($commonMetrics as $metric) {
            $stats = $this->cache->get("perf_agg_{$prefix}{$metric}");
            if ($stats && isset($stats['values'])) {
                foreach ($stats['values'] as $value) {
                    if ($value > $threshold) {
                        $exceededCount++;
                    }
                }
            }
        }
        
        return $exceededCount;
    }
    
    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts()
    {
        $alerts = [];
        
        // Check page load time
        $pageMetrics = $this->getPageLoadMetrics();
        if ($pageMetrics['avg_load_time_ms'] > $this->thresholds['page_load_time_ms']) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'page_load',
                'metric' => 'avg_load_time_ms',
                'value' => $pageMetrics['avg_load_time_ms'],
                'threshold' => $this->thresholds['page_load_time_ms'],
                'message' => "Average page load time ({$pageMetrics['avg_load_time_ms']}ms) exceeds threshold ({$this->thresholds['page_load_time_ms']}ms)"
            ];
        }
        
        // Check query execution time
        $queryMetrics = $this->getQueryMetrics();
        if ($queryMetrics['slow_queries_count'] > 0) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'database',
                'metric' => 'slow_queries_count',
                'value' => $queryMetrics['slow_queries_count'],
                'threshold' => 0,
                'message' => "{$queryMetrics['slow_queries_count']} slow queries detected (>{$this->thresholds['query_execution_time_ms']}ms)"
            ];
        }
        
        // Check API response time
        $apiMetrics = $this->getAPIMetrics();
        if ($apiMetrics['avg_response_time_ms'] > $this->thresholds['api_response_time_ms']) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'api',
                'metric' => 'avg_response_time_ms',
                'value' => $apiMetrics['avg_response_time_ms'],
                'threshold' => $this->thresholds['api_response_time_ms'],
                'message' => "Average API response time ({$apiMetrics['avg_response_time_ms']}ms) exceeds threshold ({$this->thresholds['api_response_time_ms']}ms)"
            ];
        }
        
        // Check cache hit rate
        $cacheMetrics = $this->getCacheMetrics();
        if ($cacheMetrics['cache_enabled'] && $cacheMetrics['hit_rate'] < $this->thresholds['cache_hit_rate']) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'cache',
                'metric' => 'hit_rate',
                'value' => $cacheMetrics['hit_rate'],
                'threshold' => $this->thresholds['cache_hit_rate'],
                'message' => "Cache hit rate ({$cacheMetrics['hit_rate']}%) is below threshold ({$this->thresholds['cache_hit_rate']}%)"
            ];
        }
        
        // Check memory usage
        $systemMetrics = $this->getSystemMetrics();
        if ($systemMetrics['memory_usage_mb'] > $this->thresholds['memory_usage_mb']) {
            $alerts[] = [
                'type' => 'critical',
                'category' => 'system',
                'metric' => 'memory_usage_mb',
                'value' => $systemMetrics['memory_usage_mb'],
                'threshold' => $this->thresholds['memory_usage_mb'],
                'message' => "Memory usage ({$systemMetrics['memory_usage_mb']}MB) exceeds threshold ({$this->thresholds['memory_usage_mb']}MB)"
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Get optimization recommendations
     */
    public function getOptimizationRecommendations()
    {
        $recommendations = [];
        
        $pageMetrics = $this->getPageLoadMetrics();
        $queryMetrics = $this->getQueryMetrics();
        $apiMetrics = $this->getAPIMetrics();
        $cacheMetrics = $this->getCacheMetrics();
        $systemMetrics = $this->getSystemMetrics();
        
        // Page load recommendations
        if ($pageMetrics['avg_load_time_ms'] > 2000) {
            $recommendations[] = [
                'category' => 'page_load',
                'priority' => 'high',
                'title' => 'Optimize Page Load Time',
                'description' => 'Average page load time is high. Consider implementing lazy loading, code splitting, and asset optimization.',
                'impact' => 'High - Improves user experience significantly'
            ];
        }
        
        // Query optimization recommendations
        if ($queryMetrics['slow_queries_count'] > 5) {
            $recommendations[] = [
                'category' => 'database',
                'priority' => 'high',
                'title' => 'Optimize Database Queries',
                'description' => 'Multiple slow queries detected. Review query execution plans, add indexes, and optimize complex joins.',
                'impact' => 'High - Reduces server load and improves response times'
            ];
        }
        
        // Cache recommendations
        if ($cacheMetrics['cache_enabled'] && $cacheMetrics['hit_rate'] < 70) {
            $recommendations[] = [
                'category' => 'cache',
                'priority' => 'critical',
                'title' => 'Improve Cache Hit Rate',
                'description' => 'Cache hit rate is low. Review caching strategy, increase TTL for stable data, and implement cache warming.',
                'impact' => 'Critical - Significantly reduces database load'
            ];
        } elseif (!$cacheMetrics['cache_enabled']) {
            $recommendations[] = [
                'category' => 'cache',
                'priority' => 'critical',
                'title' => 'Enable Caching',
                'description' => 'Caching is not enabled. Install and configure Redis to dramatically improve performance.',
                'impact' => 'Critical - Essential for production performance'
            ];
        }
        
        // API recommendations
        if ($apiMetrics['avg_response_time_ms'] > 300) {
            $recommendations[] = [
                'category' => 'api',
                'priority' => 'medium',
                'title' => 'Optimize API Response Time',
                'description' => 'API responses are slower than optimal. Implement response caching, optimize data serialization, and reduce payload size.',
                'impact' => 'Medium - Improves API consumer experience'
            ];
        }
        
        // Memory recommendations
        if ($systemMetrics['memory_usage_mb'] > 400) {
            $recommendations[] = [
                'category' => 'system',
                'priority' => 'medium',
                'title' => 'Optimize Memory Usage',
                'description' => 'Memory usage is high. Review object lifecycle, implement pagination for large datasets, and optimize data structures.',
                'impact' => 'Medium - Prevents memory exhaustion issues'
            ];
        }
        
        // General recommendations
        if (empty($recommendations)) {
            $recommendations[] = [
                'category' => 'general',
                'priority' => 'low',
                'title' => 'Performance is Good',
                'description' => 'All performance metrics are within acceptable ranges. Continue monitoring for any degradation.',
                'impact' => 'Low - Maintain current optimization level'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get server load (Unix-like systems)
     */
    private function getServerLoad()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2)
            ];
        }
        
        return null;
    }
    
    /**
     * Get system uptime
     */
    private function getUptime()
    {
        return time() - $this->startTime;
    }
    
    /**
     * Update performance thresholds
     */
    public function updateThresholds($newThresholds)
    {
        $this->thresholds = array_merge($this->thresholds, $newThresholds);
        
        // Store in cache
        $this->cache->set('perf_thresholds', $this->thresholds, 86400); // 24 hours
        
        return $this->thresholds;
    }
    
    /**
     * Get current thresholds
     */
    public function getThresholds()
    {
        return $this->thresholds;
    }
    
    /**
     * Clear performance metrics
     */
    public function clearMetrics()
    {
        $this->cache->invalidate('perf_*');
        $this->metrics = [];
        
        return true;
    }
    
    /**
     * Get active database connections
     */
    public function getActiveConnections()
    {
        try {
            $stmt = $this->conn->query("SHOW STATUS LIKE 'Threads_connected'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['Value'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get cache status
     */
    public function getCacheStatus()
    {
        $stats = $this->cache->getStats();
        return [
            'enabled' => $stats['enabled'] ?? false,
            'hit_rate' => $stats['hit_rate'] ?? 0,
            'memory_used' => $stats['memory_used'] ?? 'N/A',
            'keys_count' => $stats['keys'] ?? 0
        ];
    }
    
    /**
     * Get chart render times
     */
    public function getChartRenderTimes()
    {
        return $this->getAggregatedMetrics('chart_render_');
    }
    
    /**
     * Get KPI load times
     */
    public function getKPILoadTimes()
    {
        return $this->getAggregatedMetrics('kpi_load_');
    }
    
    /**
     * Get filter response times
     */
    public function getFilterResponseTimes()
    {
        return $this->getAggregatedMetrics('filter_response_');
    }
    
    /**
     * Get export performance metrics
     */
    public function getExportPerformance()
    {
        return $this->getAggregatedMetrics('export_');
    }
    
    /**
     * Get historical metrics for a time period
     */
    public function getHistoricalMetrics($period = '1h')
    {
        $hours = 1;
        switch ($period) {
            case '6h':
                $hours = 6;
                break;
            case '24h':
                $hours = 24;
                break;
            case '7d':
                $hours = 168;
                break;
        }
        
        $startTime = time() - ($hours * 3600);
        $historical = [];
        
        // Get metrics from cache for the time period
        $commonMetrics = ['page_load_dashboard', 'api_kpis', 'api_chart_data', 'query_analytics'];
        
        foreach ($commonMetrics as $metric) {
            $metricData = [];
            
            // Generate hourly data points
            for ($i = 0; $i < $hours; $i++) {
                $timestamp = $startTime + ($i * 3600);
                $key = "perf_metric_{$metric}_{$timestamp}";
                $data = $this->cache->get($key);
                
                if ($data) {
                    $metricData[] = [
                        'timestamp' => $timestamp,
                        'value' => $data['duration_ms'],
                        'memory' => $data['memory_used_mb']
                    ];
                } else {
                    // Fill gaps with interpolated or default values
                    $metricData[] = [
                        'timestamp' => $timestamp,
                        'value' => 0,
                        'memory' => 0
                    ];
                }
            }
            
            $historical[$metric] = $metricData;
        }
        
        return $historical;
    }
    
    /**
     * Record dashboard-specific metric
     */
    public function recordDashboardMetric($metricType, $value, $metadata = [])
    {
        $metricName = "dashboard_{$metricType}";
        $this->storeMetric($metricName, $value, $metadata['memory'] ?? 0);
        
        return [
            'metric' => $metricName,
            'value' => $value,
            'timestamp' => time()
        ];
    }
    
    /**
     * Get performance optimization suggestions based on current metrics
     */
    public function getPerformanceOptimizationSuggestions()
    {
        $suggestions = [];
        $metrics = $this->getPerformanceMetrics();
        
        // Analyze page load performance
        if ($metrics['page_load_metrics']['avg_load_time_ms'] > 2000) {
            $suggestions[] = [
                'category' => 'frontend',
                'priority' => 'high',
                'title' => 'Enable Chart Lazy Loading',
                'description' => 'Implement lazy loading for charts to reduce initial page load time',
                'implementation' => 'Add intersection observer to load charts when they become visible',
                'expected_improvement' => '30-50% reduction in initial load time'
            ];
        }
        
        // Analyze cache performance
        if ($metrics['cache_metrics']['hit_rate'] < 70) {
            $suggestions[] = [
                'category' => 'caching',
                'priority' => 'critical',
                'title' => 'Optimize Cache Strategy',
                'description' => 'Increase cache TTL for analytics data and implement cache warming',
                'implementation' => 'Set longer TTL for stable KPIs and pre-load frequently accessed data',
                'expected_improvement' => '60-80% reduction in database queries'
            ];
        }
        
        // Analyze API performance
        if ($metrics['api_metrics']['avg_response_time_ms'] > 300) {
            $suggestions[] = [
                'category' => 'api',
                'priority' => 'medium',
                'title' => 'Implement Response Compression',
                'description' => 'Enable gzip compression for API responses to reduce transfer time',
                'implementation' => 'Add compression middleware and optimize JSON payload size',
                'expected_improvement' => '40-60% reduction in response size'
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Get real-time performance status
     */
    public function getRealTimeStatus()
    {
        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        $activeConnections = $this->getActiveConnections();
        $cacheStatus = $this->getCacheStatus();
        
        // Determine overall health status
        $status = 'healthy';
        $issues = [];
        
        if ($currentMemory > $this->thresholds['memory_usage_mb']) {
            $status = 'warning';
            $issues[] = 'High memory usage';
        }
        
        if (!$cacheStatus['enabled']) {
            $status = 'critical';
            $issues[] = 'Cache disabled';
        } elseif ($cacheStatus['hit_rate'] < $this->thresholds['cache_hit_rate']) {
            $status = 'warning';
            $issues[] = 'Low cache hit rate';
        }
        
        if ($activeConnections > 50) {
            $status = 'warning';
            $issues[] = 'High database connections';
        }
        
        return [
            'status' => $status,
            'issues' => $issues,
            'metrics' => [
                'memory_usage_mb' => round($currentMemory, 2),
                'memory_peak_mb' => round($peakMemory, 2),
                'active_connections' => $activeConnections,
                'cache_hit_rate' => $cacheStatus['hit_rate'],
                'cache_enabled' => $cacheStatus['enabled']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
