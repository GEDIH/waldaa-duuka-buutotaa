<?php
/**
 * WebSocket Broadcaster
 * Allows backend services to broadcast messages to WebSocket clients
 * Requirements: 6.1, 6.2, 6.3
 */

class WebSocketBroadcaster {
    private $host;
    private $port;
    private $socket;
    
    public function __construct($host = '127.0.0.1', $port = 8080) {
        $this->host = $host;
        $this->port = $port;
    }
    
    /**
     * Connect to WebSocket server
     */
    private function connect() {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if (!$this->socket) {
            throw new Exception('Failed to create socket');
        }
        
        if (!@socket_connect($this->socket, $this->host, $this->port)) {
            throw new Exception('Failed to connect to WebSocket server');
        }
        
        return true;
    }
    
    /**
     * Disconnect from WebSocket server
     */
    private function disconnect() {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }
    
    /**
     * Send message to WebSocket server
     */
    private function send($message) {
        try {
            $this->connect();
            
            $encoded = json_encode($message);
            socket_write($this->socket, $encoded, strlen($encoded));
            
            $this->disconnect();
            return true;
        } catch (Exception $e) {
            error_log("WebSocket broadcast error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Broadcast message to all clients
     */
    public function broadcast($type, $payload) {
        return $this->send([
            'action' => 'broadcast',
            'type' => $type,
            'payload' => $payload,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Broadcast to specific channel
     */
    public function broadcastToChannel($channel, $type, $payload) {
        return $this->send([
            'action' => 'channel_broadcast',
            'channel' => $channel,
            'type' => $type,
            'payload' => $payload,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Send notification to specific user
     */
    public function notifyUser($userId, $notification) {
        $channel = "user.{$userId}.notifications";
        
        return $this->broadcastToChannel($channel, 'notification', [
            'user_id' => $userId,
            'notification' => $notification,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Send system alert
     */
    public function sendSystemAlert($severity, $message, $details = []) {
        return $this->broadcastToChannel('system.health', 'system_alert', [
            'severity' => $severity,
            'message' => $message,
            'details' => $details,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Send security alert
     */
    public function sendSecurityAlert($type, $message, $details = []) {
        return $this->broadcastToChannel('security.alerts', 'security_alert', [
            'alert_type' => $type,
            'message' => $message,
            'details' => $details,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Notify center activity
     */
    public function notifyCenterActivity($centerId, $activityType, $data) {
        $channel = "center.{$centerId}.activities";
        
        return $this->broadcastToChannel($channel, 'center_activity', [
            'center_id' => $centerId,
            'activity_type' => $activityType,
            'data' => $data,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Notify member update
     */
    public function notifyMemberUpdate($memberId, $updateType, $data) {
        return $this->broadcastToChannel('member.updates', 'member_update', [
            'member_id' => $memberId,
            'update_type' => $updateType,
            'data' => $data,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Notify contribution
     */
    public function notifyContribution($centerId, $contributionData) {
        return $this->broadcastToChannel('contribution.notifications', 'new_contribution', [
            'center_id' => $centerId,
            'contribution' => $contributionData,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Send user activity update
     */
    public function sendUserActivity($userId, $activity, $details = []) {
        return $this->broadcastToChannel('user.activity', 'user_activity', [
            'user_id' => $userId,
            'activity' => $activity,
            'details' => $details,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Send database metrics
     */
    public function sendDatabaseMetrics($metrics) {
        return $this->broadcastToChannel('database.metrics', 'metrics_update', [
            'metrics' => $metrics,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Send audit log entry
     */
    public function sendAuditLog($logEntry) {
        return $this->broadcastToChannel('audit.logs', 'audit_entry', [
            'log_entry' => $logEntry,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Send center announcement
     */
    public function sendCenterAnnouncement($centerId, $announcement) {
        $channel = "center.{$centerId}.announcements";
        
        return $this->broadcastToChannel($channel, 'announcement', [
            'center_id' => $centerId,
            'announcement' => $announcement,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Send performance metrics
     */
    public function sendPerformanceMetrics($metrics) {
        return $this->broadcastToChannel('system.performance', 'performance_update', [
            'metrics' => $metrics,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Batch send multiple messages
     */
    public function batchSend($messages) {
        $results = [];
        
        foreach ($messages as $message) {
            if (isset($message['channel'])) {
                $results[] = $this->broadcastToChannel(
                    $message['channel'],
                    $message['type'],
                    $message['payload']
                );
            } else {
                $results[] = $this->broadcast(
                    $message['type'],
                    $message['payload']
                );
            }
        }
        
        return $results;
    }
}

// Global helper function
function broadcastWebSocket($type, $payload, $channel = null) {
    $broadcaster = new WebSocketBroadcaster();
    
    if ($channel) {
        return $broadcaster->broadcastToChannel($channel, $type, $payload);
    } else {
        return $broadcaster->broadcast($type, $payload);
    }
}
?>
