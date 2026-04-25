<?php
/**
 * Real-time Updates SSE Endpoint
 * Provides Server-Sent Events for real-time dashboard data updates
 * Requirements: 1.2, 3.2
 */

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering
header('Access-Control-Allow-Origin: *');

// Disable output buffering for real-time streaming
if (ob_get_level()) {
    ob_end_clean();
}

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/KPICalculator.php';
require_once __DIR__ . '/../../classes/CacheManager.php';
require_once __DIR__ . '/../../classes/SessionManager.php';

// Initialize session and check authentication
$sessionManager = new SessionManager();
if (!$sessionManager->isLoggedIn()) {
    sendSSEMessage('error', ['message' => 'Unauthorized']);
    exit();
}

// Check if user has analytics access
$userRole = $sessionManager->get('role');
if (!in_array($userRole, ['superadmin', 'admin'])) {
    sendSSEMessage('error', ['message' => 'Forbidden - Insufficient permissions']);
    exit();
}

// Get filter parameters
$centerId = isset($_GET['center_id']) ? (int)$_GET['center_id'] : null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Initialize services
$kpiCalculator = new KPICalculator();
$cache = CacheManager::getInstance();

// Track last known data state for change detection
$lastDataHash = null;
$updateInterval = 5; // Check for updates every 5 seconds
$maxDuration = 300; // Maximum connection duration: 5 minutes
$startTime = time();

// Send initial connection message
sendSSEMessage('connected', [
    'message' => 'Real-time updates connected',
    'timestamp' => date('Y-m-d H:i:s'),
    'update_interval' => $updateInterval
]);

// Keep connection alive and send updates
while (true) {
    // Check if connection should be closed
    if (connection_aborted() || (time() - $startTime) > $maxDuration) {
        break;
    }
    
    try {
        // Fetch current data
        $currentData = fetchCurrentData($kpiCalculator, $centerId, $startDate, $endDate);
        
        // Calculate hash of current data to detect changes
        $currentHash = md5(json_encode($currentData));
        
        // Check if data has changed
        if ($lastDataHash === null) {
            // First iteration - send initial data
            sendSSEMessage('initial_data', $currentData);
            $lastDataHash = $currentHash;
        } elseif ($currentHash !== $lastDataHash) {
            // Data has changed - send update
            $changes = detectChanges($lastDataHash, $currentHash, $currentData);
            sendSSEMessage('data_update', [
                'data' => $currentData,
                'changes' => $changes,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            $lastDataHash = $currentHash;
        } else {
            // No changes - send heartbeat to keep connection alive
            sendSSEMessage('heartbeat', [
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'no_changes'
            ]);
        }
        
        // Flush output to client
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        // Wait before next check
        sleep($updateInterval);
        
    } catch (Exception $e) {
        error_log("SSE error: " . $e->getMessage());
        sendSSEMessage('error', [
            'message' => 'Error fetching data',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        sleep($updateInterval);
    }
}

// Connection closed
sendSSEMessage('disconnected', [
    'message' => 'Connection closed',
    'timestamp' => date('Y-m-d H:i:s')
]);

/**
 * Fetch current analytics data
 */
function fetchCurrentData($kpiCalculator, $centerId, $startDate, $endDate)
{
    return [
        'membership' => $kpiCalculator->calculateMembershipKPIs($centerId, $startDate, $endDate),
        'financial' => $kpiCalculator->calculateFinancialKPIs($centerId, $startDate, $endDate),
        'growth' => $kpiCalculator->calculateGrowthKPIs($centerId),
        'engagement' => $kpiCalculator->calculateEngagementKPIs($centerId),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Detect specific changes between data states
 */
function detectChanges($oldHash, $newHash, $newData)
{
    $changes = [];
    
    // Identify which KPI categories have alerts
    foreach (['membership', 'financial', 'growth', 'engagement'] as $category) {
        if (isset($newData[$category]['alerts']) && !empty($newData[$category]['alerts'])) {
            $changes[] = [
                'category' => $category,
                'type' => 'alert',
                'count' => count($newData[$category]['alerts'])
            ];
        }
    }
    
    // Check for significant metric changes
    if (isset($newData['membership']['new_registrations_today'])) {
        $changes[] = [
            'category' => 'membership',
            'type' => 'new_registration',
            'value' => $newData['membership']['new_registrations_today']
        ];
    }
    
    if (isset($newData['financial']['revenue_30d'])) {
        $changes[] = [
            'category' => 'financial',
            'type' => 'revenue_update',
            'value' => $newData['financial']['revenue_30d']
        ];
    }
    
    return $changes;
}

/**
 * Send SSE message to client
 */
function sendSSEMessage($event, $data)
{
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}
