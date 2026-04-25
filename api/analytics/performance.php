<?php
/**
 * Analytics Performance Monitoring API
 * Provides performance metrics, alerts, and optimization recommendations
 * Requirements: 11.1, 11.2, 11.5
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../classes/PerformanceMonitor.php';
require_once __DIR__ . '/../middleware/auth.php';

try {
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Initialize performance monitor
    $perfMonitor = PerformanceMonitor::getInstance();

    // Start tracking this API request
    $perfMonitor->startMetric('api_performance');

    $action = $_GET['action'] ?? 'metrics';
    $response = ['success' => false, 'data' => null, 'error' => null];

    switch ($action) {
        case 'metrics':
            $response['data'] = $perfMonitor->getPerformanceMetrics();
            $response['success'] = true;
            break;

        case 'alerts':
            $alerts = $perfMonitor->getPerformanceAlerts();
            $response['data'] = ['alerts' => $alerts];
            $response['success'] = true;
            break;

        case 'recommendations':
            $recommendations = $perfMonitor->getOptimizationRecommendations();
            $response['data'] = ['recommendations' => $recommendations];
            $response['success'] = true;
            break;

        case 'dashboard_metrics':
            // Enhanced dashboard-specific metrics
            $dashboardMetrics = [
                'performance' => $perfMonitor->getPerformanceMetrics(),
                'alerts' => $perfMonitor->getPerformanceAlerts(),
                'recommendations' => $perfMonitor->getOptimizationRecommendations(),
                'real_time_stats' => [
                    'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'execution_time_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2),
                    'active_connections' => $perfMonitor->getActiveConnections(),
                    'cache_status' => $perfMonitor->getCacheStatus()
                ],
                'dashboard_specific' => [
                    'chart_render_times' => $perfMonitor->getChartRenderTimes(),
                    'kpi_load_times' => $perfMonitor->getKPILoadTimes(),
                    'filter_response_times' => $perfMonitor->getFilterResponseTimes(),
                    'export_performance' => $perfMonitor->getExportPerformance()
                ]
            ];
            $response['data'] = $dashboardMetrics;
            $response['success'] = true;
            break;

        case 'thresholds':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input && isset($input['thresholds'])) {
                    $updatedThresholds = $perfMonitor->updateThresholds($input['thresholds']);
                    $response['data'] = ['thresholds' => $updatedThresholds];
                    $response['success'] = true;
                } else {
                    $response['error'] = 'Invalid threshold data';
                }
            } else {
                $response['data'] = ['thresholds' => $perfMonitor->getThresholds()];
                $response['success'] = true;
            }
            break;

        case 'clear':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $cleared = $perfMonitor->clearMetrics();
                $response['data'] = ['cleared' => $cleared];
                $response['success'] = true;
            } else {
                $response['error'] = 'POST method required for clearing metrics';
            }
            break;

        case 'historical':
            $period = $_GET['period'] ?? '1h';
            $historicalData = $perfMonitor->getHistoricalMetrics($period);
            $response['data'] = $historicalData;
            $response['success'] = true;
            break;

        default:
            $response['error'] = 'Invalid action';
            break;
    }

    // Track API response time
    $apiMetric = $perfMonitor->endMetric('api_performance');
    if ($apiMetric) {
        $perfMonitor->trackAPIResponse('performance', $apiMetric['duration_ms'] / 1000);
    }

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Performance monitoring error: ' . $e->getMessage(),
        'data' => null
    ];
    
    error_log("Performance API Error: " . $e->getMessage());
}

echo json_encode($response);
?>