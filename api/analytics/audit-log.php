<?php
/**
 * Analytics Audit Log API
 * Server-side audit logging for analytics access and security events
 * Requirements: 9.4, 9.5, 12.5
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../config/database.php';

class AnalyticsAuditLogAPI {
    private $db;
    private $tableName = 'analytics_audit_log';
    
    public function __construct($database) {
        $this->db = $database;
        $this->ensureAuditTable();
    }
    
    /**
     * Ensure audit log table exists
     */
    private function ensureAuditTable() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            user_id INT NULL,
            user_role VARCHAR(100) NULL,
            event_type VARCHAR(100) NOT NULL,
            action VARCHAR(100) NOT NULL,
            severity VARCHAR(20) DEFAULT 'medium',
            details JSON NULL,
            metadata JSON NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id),
            INDEX idx_event_type (event_type),
            INDEX idx_action (action),
            INDEX idx_severity (severity),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create audit table: " . $e->getMessage());
        }
    }
    
    /**
     * Handle API requests
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? $_POST['action'] ?? 'log';
        
        try {
            switch ($method) {
                case 'POST':
                    return $this->handlePost($action);
                case 'GET':
                    return $this->handleGet($action);
                default:
                    throw new Exception('Method not allowed', 405);
            }
        } catch (Exception $e) {
            http_response_code($e->getCode() ?: 500);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode() ?: 500
            ];
        }
    }
    
    /**
     * Handle POST requests
     */
    private function handlePost($action) {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input', 400);
        }
        
        switch ($action) {
            case 'single_log':
                return $this->logSingleEntry($input);
            case 'batch_log':
                return $this->logBatchEntries($input);
            default:
                throw new Exception('Unknown action', 400);
        }
    }
    
    /**
     * Handle GET requests
     */
    private function handleGet($action) {
        switch ($action) {
            case 'recent':
                return $this->getRecentEntries();
            case 'summary':
                return $this->getAuditSummary();
            case 'security_events':
                return $this->getSecurityEvents();
            case 'user_activity':
                return $this->getUserActivity();
            default:
                throw new Exception('Unknown action', 400);
        }
    }
    
    /**
     * Log single audit entry
     */
    private function logSingleEntry($input) {
        $entry = $input['entry'] ?? null;
        $sessionInfo = $input['sessionInfo'] ?? [];
        
        if (!$entry) {
            throw new Exception('Entry data required', 400);
        }
        
        $entryId = $this->insertAuditEntry($entry, $sessionInfo);
        
        // Check for critical events that need immediate attention
        if ($this->isCriticalEvent($entry)) {
            $this->handleCriticalEvent($entry, $entryId);
        }
        
        return [
            'success' => true,
            'entry_id' => $entryId,
            'message' => 'Audit entry logged successfully'
        ];
    }
    
    /**
     * Log batch of audit entries
     */
    private function logBatchEntries($input) {
        $entries = $input['entries'] ?? [];
        $sessionInfo = $input['sessionInfo'] ?? [];
        
        if (empty($entries)) {
            throw new Exception('Entries array required', 400);
        }
        
        $entryIds = [];
        $criticalEvents = [];
        
        $this->db->beginTransaction();
        
        try {
            foreach ($entries as $entry) {
                $entryId = $this->insertAuditEntry($entry, $sessionInfo);
                $entryIds[] = $entryId;
                
                if ($this->isCriticalEvent($entry)) {
                    $criticalEvents[] = ['entry' => $entry, 'id' => $entryId];
                }
            }
            
            $this->db->commit();
            
            // Handle critical events after successful batch insert
            foreach ($criticalEvents as $critical) {
                $this->handleCriticalEvent($critical['entry'], $critical['id']);
            }
            
            return [
                'success' => true,
                'entries_logged' => count($entryIds),
                'entry_ids' => $entryIds,
                'critical_events' => count($criticalEvents),
                'message' => 'Batch audit entries logged successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Insert audit entry into database
     */
    private function insertAuditEntry($entry, $sessionInfo) {
        $sql = "INSERT INTO {$this->tableName} (
            session_id, user_id, user_role, event_type, action, severity,
            details, metadata, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            $entry['sessionId'] ?? $sessionInfo['sessionId'] ?? null,
            $entry['userId'] ?? $sessionInfo['userId'] ?? null,
            $entry['userRole'] ?? $sessionInfo['userRole'] ?? null,
            $entry['type'] ?? 'unknown',
            $entry['action'] ?? 'unknown',
            $entry['severity'] ?? 'medium',
            json_encode($entry['details'] ?? []),
            json_encode($entry['metadata'] ?? []),
            $this->getClientIP(),
            $entry['metadata']['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Check if event is critical
     */
    private function isCriticalEvent($entry) {
        $criticalActions = [
            'access_denied',
            'unauthorized_access_attempt',
            'session_hijack_detected',
            'suspicious_activity',
            'data_breach_attempt'
        ];
        
        return in_array($entry['action'], $criticalActions) || 
               ($entry['severity'] ?? 'medium') === 'critical';
    }
    
    /**
     * Handle critical security events
     */
    private function handleCriticalEvent($entry, $entryId) {
        // Log to system error log
        error_log("CRITICAL SECURITY EVENT: " . json_encode([
            'entry_id' => $entryId,
            'action' => $entry['action'],
            'user_id' => $entry['userId'],
            'session_id' => $entry['sessionId'],
            'timestamp' => date('Y-m-d H:i:s', $entry['timestamp'] / 1000)
        ]));
        
        // Could add additional alerting here (email, SMS, etc.)
        // For now, just ensure it's logged with high priority
    }
    
    /**
     * Get recent audit entries
     */
    private function getRecentEntries() {
        $limit = $_GET['limit'] ?? 100;
        $hours = $_GET['hours'] ?? 24;
        $eventType = $_GET['event_type'] ?? null;
        $userId = $_GET['user_id'] ?? null;
        
        $sql = "SELECT * FROM {$this->tableName} WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
        $params = [$hours];
        
        if ($eventType) {
            $sql .= " AND event_type = ?";
            $params[] = $eventType;
        }
        
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($entries as &$entry) {
            $entry['details'] = json_decode($entry['details'], true);
            $entry['metadata'] = json_decode($entry['metadata'], true);
        }
        
        return [
            'success' => true,
            'entries' => $entries,
            'count' => count($entries)
        ];
    }
    
    /**
     * Get audit summary statistics
     */
    private function getAuditSummary() {
        $hours = $_GET['hours'] ?? 24;
        
        // Total events
        $sql = "SELECT COUNT(*) as total FROM {$this->tableName} WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hours]);
        $total = $stmt->fetchColumn();
        
        // Events by type
        $sql = "SELECT event_type, COUNT(*) as count FROM {$this->tableName} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) 
                GROUP BY event_type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hours]);
        $byType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Events by severity
        $sql = "SELECT severity, COUNT(*) as count FROM {$this->tableName} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) 
                GROUP BY severity";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hours]);
        $bySeverity = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Top users
        $sql = "SELECT user_id, COUNT(*) as count FROM {$this->tableName} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) AND user_id IS NOT NULL
                GROUP BY user_id ORDER BY count DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hours]);
        $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent critical events
        $sql = "SELECT * FROM {$this->tableName} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) AND severity = 'critical'
                ORDER BY created_at DESC LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hours]);
        $criticalEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'summary' => [
                'total_events' => (int)$total,
                'events_by_type' => $byType,
                'events_by_severity' => $bySeverity,
                'top_users' => $topUsers,
                'critical_events' => $criticalEvents,
                'time_period_hours' => (int)$hours
            ]
        ];
    }
    
    /**
     * Get security events
     */
    private function getSecurityEvents() {
        $limit = $_GET['limit'] ?? 50;
        $severity = $_GET['severity'] ?? null;
        
        $sql = "SELECT * FROM {$this->tableName} WHERE event_type = 'security_event'";
        $params = [];
        
        if ($severity) {
            $sql .= " AND severity = ?";
            $params[] = $severity;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($events as &$event) {
            $event['details'] = json_decode($event['details'], true);
            $event['metadata'] = json_decode($event['metadata'], true);
        }
        
        return [
            'success' => true,
            'security_events' => $events,
            'count' => count($events)
        ];
    }
    
    /**
     * Get user activity
     */
    private function getUserActivity() {
        $userId = $_GET['user_id'] ?? null;
        $sessionId = $_GET['session_id'] ?? null;
        $limit = $_GET['limit'] ?? 100;
        
        if (!$userId && !$sessionId) {
            throw new Exception('User ID or Session ID required', 400);
        }
        
        $sql = "SELECT * FROM {$this->tableName} WHERE ";
        $params = [];
        
        if ($userId) {
            $sql .= "user_id = ?";
            $params[] = $userId;
        } else {
            $sql .= "session_id = ?";
            $params[] = $sessionId;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = (int)$limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($activities as &$activity) {
            $activity['details'] = json_decode($activity['details'], true);
            $activity['metadata'] = json_decode($activity['metadata'], true);
        }
        
        // Calculate activity statistics
        $stats = [
            'total_activities' => count($activities),
            'widget_interactions' => 0,
            'security_events' => 0,
            'notification_events' => 0,
            'system_events' => 0,
            'session_duration' => 0
        ];
        
        $firstActivity = null;
        $lastActivity = null;
        
        foreach ($activities as $activity) {
            $stats[$activity['event_type'] . 's']++;
            
            if (!$firstActivity || $activity['created_at'] < $firstActivity) {
                $firstActivity = $activity['created_at'];
            }
            if (!$lastActivity || $activity['created_at'] > $lastActivity) {
                $lastActivity = $activity['created_at'];
            }
        }
        
        if ($firstActivity && $lastActivity) {
            $stats['session_duration'] = strtotime($lastActivity) - strtotime($firstActivity);
        }
        
        return [
            'success' => true,
            'activities' => $activities,
            'statistics' => $stats
        ];
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}

// Handle the request
try {
    // Simple database connection for demo
    $dsn = "mysql:host=localhost;dbname=wdb_church;charset=utf8mb4";
    $username = "root";
    $password = "";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $api = new AnalyticsAuditLogAPI($pdo);
    $result = $api->handleRequest();
    
} catch (Exception $e) {
    http_response_code(500);
    $result = [
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>