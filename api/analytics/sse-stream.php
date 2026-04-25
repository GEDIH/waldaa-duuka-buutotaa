<?php
/**
 * Server-Sent Events Stream Endpoint
 * Task: 5.1 Implement Real-Time Synchronization Engine
 * Requirements: 3.1, 3.2, 3.3
 */

require_once '../config/database.php';
require_once '../security/SecurityManager.php';

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control');

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Get context from query parameters
$context = [];
foreach ($_GET as $key => $value) {
    if (in_array($key, ['dashboard_type', 'user_id', 'user_role', 'accessible_centers', 'filters'])) {
        $context[$key] = json_decode($value, true) ?: $value;
    }
}

// Validate user session
$securityManager = new SecurityManager($pdo);
$userId = $context['user_id'] ?? null;

if (!$userId || !$securityManager->validateSession($userId)) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Invalid session']) . "\n\n";
    flush();
    exit;
}

// Store subscription in database
try {
    $sessionId = session_id() ?: uniqid('sse_', true);
    
    $stmt = $pdo->prepare("
        INSERT INTO realtime_subscriptions 
        (session_id, user_id, dashboard_type, context_filters, subscribed_widgets, last_heartbeat) 
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        context_filters = VALUES(context_filters),
        last_heartbeat = NOW()
    ");
    
    $stmt->execute([
        $sessionId,
        $userId,
        $context['dashboard_type'] ?? 'admin_main',
        json_encode($context['filters'] ?? []),
        json_encode([]) // Will be updated when widgets subscribe
    ]);
    
} catch (Exception $e) {
    error_log("SSE subscription error: " . $e->getMessage());
}

// Send initial connection confirmation
echo "event: connected\n";
echo "data: " . json_encode([
    'timestamp' => time(),
    'session_id' => $sessionId,
    'context' => $context
]) . "\n\n";
flush();

// Main event loop
$lastHeartbeat = time();
$heartbeatInterval = 30; // seconds

while (true) {
    // Check if connection is still alive
    if (connection_aborted()) {
        break;
    }
    
    // Send heartbeat
    $currentTime = time();
    if ($currentTime - $lastHeartbeat >= $heartbeatInterval) {
        echo "event: heartbeat\n";
        echo "data: " . json_encode(['timestamp' => $currentTime]) . "\n\n";
        flush();
        $lastHeartbeat = $currentTime;
        
        // Update last heartbeat in database
        try {
            $stmt = $pdo->prepare("UPDATE realtime_subscriptions SET last_heartbeat = NOW() WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            error_log("Heartbeat update error: " . $e->getMessage());
        }
    }
    
    // Check for pending updates
    try {
        $updates = checkForUpdates($pdo, $userId, $context);
        
        foreach ($updates as $update) {
            echo "event: widget-update\n";
            echo "data: " . json_encode($update) . "\n\n";
            flush();
        }
        
    } catch (Exception $e) {
        error_log("Update check error: " . $e->getMessage());
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Update check failed']) . "\n\n";
        flush();
    }
    
    // Sleep for a short interval
    sleep(1);
}

// Cleanup on disconnect
try {
    $stmt = $pdo->prepare("DELETE FROM realtime_subscriptions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
} catch (Exception $e) {
    error_log("SSE cleanup error: " . $e->getMessage());
}

/**
 * Check for pending updates for the user
 */
function checkForUpdates($pdo, $userId, $context) {
    $updates = [];
    
    // This is a simplified implementation
    // In a real system, you'd check for actual data changes
    
    return $updates;
}
?>