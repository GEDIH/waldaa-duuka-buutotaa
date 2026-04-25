<?php
/**
 * System Monitor
 * Tracks system-level metrics for production monitoring
 * Requirements: 5.1.4, 5.1.5
 * 
 * This class monitors:
 * - Disk space (5.1.4)
 * - Memory usage (5.1.5)
 * 
 * Integrates with ApplicationMonitor and DatabaseMonitor for comprehensive monitoring.
 */

require_once __DIR__ . '/CacheManager.php';

class SystemMonitor
{
    private static $instance = null;
    private $cache;
    
    // Monitoring thresholds
    private $thresholds = [
        'disk_space_percent' => 10.0,     // Alert when < 10% free disk space
        'memory_usage_percent' => 80.0,   // Alert when > 80% memory usage
        'critical_disk_space_percent' => 5.0,  // Critical when < 5% free
        'critical_memory_usage_percent' => 90.0 // Critical when > 90% memory
    ];
    
    // Storage configuration
    private $storageConfig = [
        'use_cache' => true,
        'cache_ttl' => 3600,              // 1 hour
        'aggregation_window' => 300       // 5 minutes
    ];
    
    private function __construct()
    {
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
     * Monitor disk space
     * Requirement 5.1.4: Disk space monitoring
     * 
     * @param string $path Path to monitor (default: root directory)
     * @return array Disk space metrics
     */
    public function monitorDiskSpace($path = '/')
    {
        try {
            $totalSpace = disk_total_space($path);
            $freeSpace = disk_free_space($path);
            $usedSpace = $totalSpace - $freeSpace;
            
            $freePercent = round(($freeSpace / $totalSpace) * 100, 2);
            $usedPercent = round(($usedSpace / $totalSpace) * 100, 2);
            
            $timestamp = time();
            $diskData = [
                'path' => $path,
                'total_space_bytes' => $totalSpace,
                'free_space_bytes' => $freeSpace,
                'used_space_bytes' => $usedSpace,
                'total_space_gb' => round($totalSpace / (1024 ** 3), 2),
                'free_space_gb' => round($freeSpace / (1024 ** 3), 2),
                'used_space_gb' => round($usedSpace / (1024 ** 3), 2),
                'free_percent' => $freePercent,
                'used_percent' => $usedPercent,
                'low_space_warning' => $freePercent < $this->thresholds['disk_space_percent'],
                'critical_space_warning' => $freePercent < $this->thresholds['critical_disk_space_percent'],
                'timestamp' => $timestamp,
                'datetime' => date('Y-m-d H:i:s', $timestamp)
            ];
            
            // Store in cache
            $window = $this->getTimeWindow();
            $key = "system_disk_space_{$window}";
            $this->cache->set($key, $diskData, $this->storageConfig['cache_ttl']);
            
            // Update aggregated metrics
            $this->updateDiskSpaceMetrics($diskData);
            
            // Trigger alert if low disk space
            if ($diskData['low_space_warning']) {
                $this->triggerLowDiskSpaceAlert($diskData);
            }
            
            return $diskData;
            
        } catch (Exception $e) {
            error_log("SystemMonitor: Failed to monitor disk space - " . $e->getMessage());
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Monitor memory usage
     * Requirement 5.1.5: Memory usage monitoring
     * 
     * @return array Memory usage metrics
     */
    public function monitorMemoryUsage()
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = $this->getMemoryLimit();
            
            $usagePercent = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0;
            $peakPercent = $memoryLimit > 0 ? round(($memoryPeak / $memoryLimit) * 100, 2) : 0;
            
            $timestamp = time();
            $memoryData = [
                'current_usage_bytes' => $memoryUsage,
                'peak_usage_bytes' => $memoryPeak,
                'memory_limit_bytes' => $memoryLimit,
                'current_usage_mb' => round($memoryUsage / (1024 ** 2), 2),
                'peak_usage_mb' => round($memoryPeak / (1024 ** 2), 2),
                'memory_limit_mb' => round($memoryLimit / (1024 ** 2), 2),
                'usage_percent' => $usagePercent,
                'peak_percent' => $peakPercent,
                'high_memory_warning' => $usagePercent > $this->thresholds['memory_usage_percent'],
                'critical_memory_warning' => $usagePercent > $this->thresholds['critical_memory_usage_percent'],
                'timestamp' => $timestamp,
                'datetime' => date('Y-m-d H:i:s', $timestamp)
            ];
            
            // Store in cache
            $window = $this->getTimeWindow();
            $key = "system_memory_usage_{$window}";
            $this->cache->set($key, $memoryData, $this->storageConfig['cache_ttl']);
            
            // Update aggregated metrics
            $this->updateMemoryUsageMetrics($memoryData);
            
            // Trigger alert if high memory usage
            if ($memoryData['high_memory_warning']) {
                $this->triggerHighMemoryUsageAlert($memoryData);
            }
            
            return $memoryData;
            
        } catch (Exception $e) {
            error_log("SystemMonitor: Failed to monitor memory usage - " . $e->getMessage());
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get PHP memory limit in bytes
     * 
     * @return int Memory limit in bytes
     */
    private function getMemoryLimit()
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit == -1) {
            // No limit
            return PHP_INT_MAX;
        }
        
        // Convert to bytes
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'G':
                return $value * 1024 ** 3;
            case 'M':
                return $value * 1024 ** 2;
            case 'K':
                return $value * 1024;
            default:
                return (int) $memoryLimit;
        }
    }
    
    /**
     * Update disk space metrics
     */
    private function updateDiskSpaceMetrics($diskData)
    {
        $window = $this->getTimeWindow();
        $key = "system_disk_metrics_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'samples' => 0,
                'avg_free_percent' => 0,
                'min_free_percent' => 100,
                'max_used_percent' => 0,
                'low_space_count' => 0,
                'critical_space_count' => 0
            ];
        }
        
        $metrics['samples']++;
        $metrics['avg_free_percent'] = round(
            (($metrics['avg_free_percent'] * ($metrics['samples'] - 1)) + $diskData['free_percent']) / $metrics['samples'],
            2
        );
        $metrics['min_free_percent'] = min($metrics['min_free_percent'], $diskData['free_percent']);
        $metrics['max_used_percent'] = max($metrics['max_used_percent'], $diskData['used_percent']);
        
        if ($diskData['low_space_warning']) {
            $metrics['low_space_count']++;
        }
        
        if ($diskData['critical_space_warning']) {
            $metrics['critical_space_count']++;
        }
        
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
    }

    /**
     * Update memory usage metrics
     */
    private function updateMemoryUsageMetrics($memoryData)
    {
        $window = $this->getTimeWindow();
        $key = "system_memory_metrics_{$window}";
        
        $metrics = $this->cache->get($key);
        if (!$metrics) {
            $metrics = [
                'window' => $window,
                'samples' => 0,
                'avg_usage_percent' => 0,
                'max_usage_percent' => 0,
                'peak_usage_mb' => 0,
                'high_memory_count' => 0,
                'critical_memory_count' => 0
            ];
        }
        
        $metrics['samples']++;
        $metrics['avg_usage_percent'] = round(
            (($metrics['avg_usage_percent'] * ($metrics['samples'] - 1)) + $memoryData['usage_percent']) / $metrics['samples'],
            2
        );
        $metrics['max_usage_percent'] = max($metrics['max_usage_percent'], $memoryData['usage_percent']);
        $metrics['peak_usage_mb'] = max($metrics['peak_usage_mb'], $memoryData['peak_usage_mb']);
        
        if ($memoryData['high_memory_warning']) {
            $metrics['high_memory_count']++;
        }
        
        if ($memoryData['critical_memory_warning']) {
            $metrics['critical_memory_count']++;
        }
        
        $this->cache->set($key, $metrics, $this->storageConfig['aggregation_window']);
    }
    
    /**
     * Trigger low disk space alert
     * Requirement 5.1.4: Alert on low disk space
     * 
     * @param array $diskData Disk space data
     * @return bool Success status
     */
    private function triggerLowDiskSpaceAlert($diskData)
    {
        $alertType = $diskData['critical_space_warning'] ? 'critical' : 'warning';
        
        // Store alert in cache for immediate retrieval
        $alertKey = "system_alert_low_disk_space_" . time();
        $alert = [
            'type' => $alertType,
            'category' => 'low_disk_space',
            'message' => "Low disk space detected: {$diskData['free_percent']}% free ({$diskData['free_space_gb']} GB)",
            'free_percent' => $diskData['free_percent'],
            'free_space_gb' => $diskData['free_space_gb'],
            'threshold' => $this->thresholds['disk_space_percent'],
            'timestamp' => $diskData['timestamp'],
            'datetime' => $diskData['datetime']
        ];
        
        $this->cache->set($alertKey, $alert, 3600); // Store for 1 hour
        
        // Log alert
        $logLevel = $alertType === 'critical' ? 'CRITICAL' : 'WARNING';
        error_log("SystemMonitor: {$logLevel} ALERT - Low disk space: {$diskData['free_percent']}% free");
        
        return true;
    }

    /**
     * Trigger high memory usage alert
     * Requirement 5.1.5: Alert on high memory usage
     * 
     * @param array $memoryData Memory usage data
     * @return bool Success status
     */
    private function triggerHighMemoryUsageAlert($memoryData)
    {
        $alertType = $memoryData['critical_memory_warning'] ? 'critical' : 'warning';
        
        // Store alert in cache for immediate retrieval
        $alertKey = "system_alert_high_memory_" . time();
        $alert = [
            'type' => $alertType,
            'category' => 'high_memory_usage',
            'message' => "High memory usage detected: {$memoryData['usage_percent']}% ({$memoryData['current_usage_mb']} MB)",
            'usage_percent' => $memoryData['usage_percent'],
            'current_usage_mb' => $memoryData['current_usage_mb'],
            'threshold' => $this->thresholds['memory_usage_percent'],
            'timestamp' => $memoryData['timestamp'],
            'datetime' => $memoryData['datetime']
        ];
        
        $this->cache->set($alertKey, $alert, 3600); // Store for 1 hour
        
        // Log alert
        $logLevel = $alertType === 'critical' ? 'CRITICAL' : 'WARNING';
        error_log("SystemMonitor: {$logLevel} ALERT - High memory usage: {$memoryData['usage_percent']}%");
        
        return true;
    }
    
    /**
     * Get disk space metrics
     * 
     * @param int $windowCount Number of time windows to retrieve
     * @return array Disk space metrics
     */
    public function getDiskSpaceMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "system_disk_metrics_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        if (empty($allMetrics)) {
            return [
                'avg_free_percent' => 0,
                'min_free_percent' => 0,
                'max_used_percent' => 0,
                'threshold' => $this->thresholds['disk_space_percent'],
                'threshold_exceeded' => false,
                'windows' => []
            ];
        }
        
        $avgFreePercent = round(array_sum(array_column($allMetrics, 'avg_free_percent')) / count($allMetrics), 2);
        $minFreePercent = min(array_column($allMetrics, 'min_free_percent'));
        $maxUsedPercent = max(array_column($allMetrics, 'max_used_percent'));
        $lowSpaceCount = array_sum(array_column($allMetrics, 'low_space_count'));
        
        return [
            'avg_free_percent' => $avgFreePercent,
            'min_free_percent' => $minFreePercent,
            'max_used_percent' => $maxUsedPercent,
            'threshold' => $this->thresholds['disk_space_percent'],
            'threshold_exceeded' => $lowSpaceCount > 0,
            'low_space_count' => $lowSpaceCount,
            'windows' => $allMetrics
        ];
    }

    /**
     * Get memory usage metrics
     * 
     * @param int $windowCount Number of time windows to retrieve
     * @return array Memory usage metrics
     */
    public function getMemoryUsageMetrics($windowCount = 1)
    {
        $currentWindow = $this->getTimeWindow();
        $window = $this->storageConfig['aggregation_window'];
        
        $allMetrics = [];
        
        for ($i = 0; $i < $windowCount; $i++) {
            $windowTime = $currentWindow - ($i * $window);
            $key = "system_memory_metrics_{$windowTime}";
            $metrics = $this->cache->get($key);
            
            if ($metrics) {
                $allMetrics[] = $metrics;
            }
        }
        
        if (empty($allMetrics)) {
            return [
                'avg_usage_percent' => 0,
                'max_usage_percent' => 0,
                'peak_usage_mb' => 0,
                'threshold' => $this->thresholds['memory_usage_percent'],
                'threshold_exceeded' => false,
                'windows' => []
            ];
        }
        
        $avgUsagePercent = round(array_sum(array_column($allMetrics, 'avg_usage_percent')) / count($allMetrics), 2);
        $maxUsagePercent = max(array_column($allMetrics, 'max_usage_percent'));
        $peakUsageMb = max(array_column($allMetrics, 'peak_usage_mb'));
        $highMemoryCount = array_sum(array_column($allMetrics, 'high_memory_count'));
        
        return [
            'avg_usage_percent' => $avgUsagePercent,
            'max_usage_percent' => $maxUsagePercent,
            'peak_usage_mb' => $peakUsageMb,
            'threshold' => $this->thresholds['memory_usage_percent'],
            'threshold_exceeded' => $highMemoryCount > 0,
            'high_memory_count' => $highMemoryCount,
            'windows' => $allMetrics
        ];
    }
    
    /**
     * Get comprehensive system monitoring dashboard
     * 
     * @return array Complete system metrics
     */
    public function getSystemMonitoringDashboard()
    {
        return [
            'disk_space_metrics' => $this->getDiskSpaceMetrics(12), // Last hour
            'memory_usage_metrics' => $this->getMemoryUsageMetrics(12),
            'current_disk_space' => $this->monitorDiskSpace(),
            'current_memory_usage' => $this->monitorMemoryUsage(),
            'alerts' => $this->getSystemAlerts(),
            'health_status' => $this->getSystemHealthStatus(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get active system alerts
     * 
     * @return array Active alerts
     */
    public function getSystemAlerts()
    {
        $alerts = [];
        
        // Check disk space
        $diskMetrics = $this->getDiskSpaceMetrics(1);
        if ($diskMetrics['threshold_exceeded']) {
            $alerts[] = [
                'type' => $diskMetrics['min_free_percent'] < $this->thresholds['critical_disk_space_percent'] ? 'critical' : 'warning',
                'category' => 'disk_space',
                'message' => "Low disk space: {$diskMetrics['min_free_percent']}% free",
                'value' => $diskMetrics['min_free_percent'],
                'threshold' => $diskMetrics['threshold'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check memory usage
        $memoryMetrics = $this->getMemoryUsageMetrics(1);
        if ($memoryMetrics['threshold_exceeded']) {
            $alerts[] = [
                'type' => $memoryMetrics['max_usage_percent'] > $this->thresholds['critical_memory_usage_percent'] ? 'critical' : 'warning',
                'category' => 'memory_usage',
                'message' => "High memory usage: {$memoryMetrics['max_usage_percent']}%",
                'value' => $memoryMetrics['max_usage_percent'],
                'threshold' => $memoryMetrics['threshold'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Get system health status
     * 
     * @return array Health status
     */
    public function getSystemHealthStatus()
    {
        $diskMetrics = $this->getDiskSpaceMetrics(1);
        $memoryMetrics = $this->getMemoryUsageMetrics(1);
        
        $status = 'healthy';
        $issues = [];
        
        // Check for critical disk space
        if ($diskMetrics['min_free_percent'] < $this->thresholds['critical_disk_space_percent']) {
            $status = 'critical';
            $issues[] = 'Critical low disk space';
        } elseif ($diskMetrics['threshold_exceeded']) {
            $status = 'degraded';
            $issues[] = 'Low disk space';
        }
        
        // Check for critical memory usage
        if ($memoryMetrics['max_usage_percent'] > $this->thresholds['critical_memory_usage_percent']) {
            if ($status === 'healthy') {
                $status = 'critical';
            }
            $issues[] = 'Critical high memory usage';
        } elseif ($memoryMetrics['threshold_exceeded']) {
            if ($status === 'healthy') {
                $status = 'degraded';
            }
            $issues[] = 'High memory usage';
        }
        
        return [
            'status' => $status,
            'issues' => $issues,
            'timestamp' => date('Y-m-d H:i:s')
        ];
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
        $this->cache->set('system_monitor_thresholds', $this->thresholds, 86400); // 24 hours
        
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
     * Clear all system monitoring metrics
     * 
     * @return bool Success status
     */
    public function clearMetrics()
    {
        $this->cache->invalidate('system_disk_*');
        $this->cache->invalidate('system_memory_*');
        $this->cache->invalidate('system_alert_*');
        
        return true;
    }
}
