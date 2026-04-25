<?php
/**
 * Analytics Integration Manager
 * 
 * Coordinates all analytics system components and provides unified interface
 * 
 * Feature: wdb-advanced-analytics
 * Task: 18.1 - Complete system integration
 */

require_once __DIR__ . '/AnalyticsService.php';
require_once __DIR__ . '/KPICalculator.php';
require_once __DIR__ . '/CacheManager.php';
require_once __DIR__ . '/ReportGenerator.php';
require_once __DIR__ . '/DataExporter.php';
require_once __DIR__ . '/SearchService.php';
require_once __DIR__ . '/PerformanceMonitor.php';
require_once __DIR__ . '/SecurityManager.php';
require_once __DIR__ . '/InteractionTracker.php';
require_once __DIR__ . '/AnalyticsSessionManager.php';
require_once __DIR__ . '/AnalyticsAuditLogger.php';

class AnalyticsIntegration {
    private $db;
    private $analyticsService;
    private $kpiCalculator;
    private $cacheManager;
    private $reportGenerator;
    private $dataExporter;
    private $searchService;
    private $performanceMonitor;
    private $securityManager;
    private $interactionTracker;
    private $sessionManager;
    private $auditLogger;
    private $logger;
    
    public function __construct($db = null) {
        if ($db === null) {
            require_once __DIR__ . '/../api/config/database.php';
            $this->db = Database::getInstance()->getConnection();
        } else {
            $this->db = $db;
        }
        
        $this->setupLogging();
        $this->setupErrorHandling();
        $this->initializeComponents();
    }
    
    /**
     * Initialize all system components
     */
    private function initializeComponents(): void {
        try {
            $this->cacheManager = CacheManager::getInstance();
            $this->analyticsService = new AnalyticsService($this->db, $this->cacheManager);
            $this->kpiCalculator = new KPICalculator($this->db);
            $this->reportGenerator = new ReportGenerator($this->db);
            $this->dataExporter = new DataExporter($this->db);
            $this->searchService = new SearchService();
            $this->performanceMonitor = PerformanceMonitor::getInstance();
            $this->securityManager = new SecurityManager($this->db);
            $this->interactionTracker = new InteractionTracker($this->db);
            $this->sessionManager = new AnalyticsSessionManager($this->db);
            $this->auditLogger = new AnalyticsAuditLogger($this->db);
            
            $this->log('info', 'All analytics components initialized successfully');
        } catch (Exception $e) {
            $this->log('error', 'Component initialization failed: ' . $e->getMessage());
            throw new Exception('Analytics system initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Setup error handling
     */
    private function setupErrorHandling(): void {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
    }
    
    /**
     * Setup logging
     */
    private function setupLogging(): void {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->logger = $logDir . '/analytics_' . date('Y-m-d') . '.log';
    }
    
    /**
     * Get dashboard data with all components integrated
     */
    public function getDashboardData(int $userId, array $filters = []): array {
        $startTime = microtime(true);
        
        try {
            // Validate session
            if (!$this->sessionManager->validateSession($userId)) {
                throw new Exception('Invalid session');
            }
            
            // Check permissions
            if (!$this->securityManager->hasPermission($userId, 'view_analytics')) {
                $this->auditLogger->logAccess($userId, 'dashboard', 'denied');
                throw new Exception('Access denied');
            }
            
            // Track interaction
            $this->interactionTracker->trackInteraction($userId, 'dashboard_view', [
                'filters' => $filters
            ]);
            
            // Get cached data or fetch fresh
            $cacheKey = 'dashboard_' . $userId . '_' . md5(json_encode($filters));
            $data = $this->cacheManager->get($cacheKey);
            
            if ($data === null) {
                $data = [
                    'kpis' => $this->kpiCalculator->calculateAllKPIs($filters),
                    'charts' => $this->analyticsService->getChartData($filters),
                    'recent_activity' => $this->analyticsService->getRecentActivity($filters),
                    'recommendations' => $this->interactionTracker->getDashboardLayoutRecommendations($userId)
                ];
                
                $this->cacheManager->set($cacheKey, $data, 300); // 5 minutes
            }
            
            // Log audit
            $this->auditLogger->logAccess($userId, 'dashboard', 'success');
            
            // Monitor performance
            $executionTime = (microtime(true) - $startTime) * 1000;
            $this->performanceMonitor->recordMetric('dashboard_load', $executionTime);
            
            return [
                'success' => true,
                'data' => $data,
                'execution_time' => $executionTime
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'Dashboard data fetch failed: ' . $e->getMessage());
            $this->auditLogger->logError($userId, 'dashboard', $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate and export report
     */
    public function generateReport(int $userId, string $template, array $parameters, string $format = 'pdf'): array {
        try {
            // Validate permissions
            if (!$this->securityManager->hasPermission($userId, 'generate_reports')) {
                throw new Exception('Insufficient permissions');
            }
            
            // Generate report
            $report = $this->reportGenerator->generateReport($template, $parameters);
            
            // Export in requested format
            $exportPath = match($format) {
                'pdf' => $this->reportGenerator->exportToPDF($report),
                'excel' => $this->reportGenerator->exportToExcel($report),
                'csv' => $this->dataExporter->exportToCSV($report->data),
                default => throw new Exception('Unsupported format')
            };
            
            // Log audit
            $this->auditLogger->logExport($userId, 'report', $format, $exportPath);
            
            // Track interaction
            $this->interactionTracker->trackInteraction($userId, 'report_generation', [
                'template' => $template,
                'format' => $format
            ]);
            
            return [
                'success' => true,
                'report_id' => $report->id,
                'export_path' => $exportPath
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'Report generation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Perform search across analytics data
     */
    public function search(int $userId, string $query, array $options = []): array {
        try {
            // Validate session
            if (!$this->sessionManager->validateSession($userId)) {
                throw new Exception('Invalid session');
            }
            
            // Perform search
            $results = $this->searchService->search($query, $options);
            
            // Track interaction
            $this->interactionTracker->trackInteraction($userId, 'search', [
                'query' => $query,
                'results_count' => count($results)
            ]);
            
            // Log audit
            $this->auditLogger->logAccess($userId, 'search', 'success');
            
            return [
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ];
            
        } catch (Exception $e) {
            $this->log('error', 'Search failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get system health status
     */
    public function getSystemHealth(): array {
        try {
            $health = [
                'status' => 'healthy',
                'components' => [],
                'performance' => [],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Check database connection
            $health['components']['database'] = $this->checkDatabaseHealth();
            
            // Check cache
            $health['components']['cache'] = $this->checkCacheHealth();
            
            // Get performance metrics
            $health['performance'] = $this->performanceMonitor->getSystemMetrics();
            
            // Determine overall status
            foreach ($health['components'] as $component => $status) {
                if ($status['status'] !== 'healthy') {
                    $health['status'] = 'degraded';
                    break;
                }
            }
            
            return $health;
            
        } catch (Exception $e) {
            $this->log('error', 'Health check failed: ' . $e->getMessage());
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth(): array {
        try {
            $stmt = $this->db->query("SELECT 1");
            return [
                'status' => 'healthy',
                'response_time' => 0
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check cache health
     */
    private function checkCacheHealth(): array {
        try {
            $testKey = 'health_check_' . time();
            $this->cacheManager->set($testKey, 'test', 10);
            $value = $this->cacheManager->get($testKey);
            
            return [
                'status' => $value === 'test' ? 'healthy' : 'degraded',
                'response_time' => 0
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Invalidate all caches
     */
    public function invalidateAllCaches(): bool {
        try {
            $this->cacheManager->invalidateAnalyticsCache();
            $this->log('info', 'All caches invalidated');
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Cache invalidation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log message
     */
    private function log(string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logger, $logMessage, FILE_APPEND);
    }
    
    /**
     * Handle errors
     */
    public function handleError($errno, $errstr, $errfile, $errline): bool {
        $this->log('error', "Error [$errno]: $errstr in $errfile on line $errline");
        return true;
    }
    
    /**
     * Handle exceptions
     */
    public function handleException($exception): void {
        $this->log('critical', 'Uncaught exception: ' . $exception->getMessage());
        $this->log('critical', 'Stack trace: ' . $exception->getTraceAsString());
    }
    
    /**
     * Get all components status
     */
    public function getComponentsStatus(): array {
        return [
            'analytics_service' => isset($this->analyticsService),
            'kpi_calculator' => isset($this->kpiCalculator),
            'cache_manager' => isset($this->cacheManager),
            'report_generator' => isset($this->reportGenerator),
            'data_exporter' => isset($this->dataExporter),
            'search_service' => isset($this->searchService),
            'performance_monitor' => isset($this->performanceMonitor),
            'security_manager' => isset($this->securityManager),
            'interaction_tracker' => isset($this->interactionTracker),
            'session_manager' => isset($this->sessionManager),
            'audit_logger' => isset($this->auditLogger)
        ];
    }
}
