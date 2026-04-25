<?php
/**
 * Analytics Audit Logger
 * 
 * Maintains comprehensive audit logs of all analytics access, exports,
 * and administrative actions for compliance and security monitoring.
 * 
 * Feature: wdb-advanced-analytics
 * Requirements: 12.7
 */

class AnalyticsAuditLogger {
    private $db;
    
    // Event types
    const EVENT_VIEW = 'view';
    const EVENT_EXPORT = 'export';
    const EVENT_FILTER = 'filter';
    const EVENT_REPORT_GENERATE = 'report_generate';
    const EVENT_REPORT_SCHEDULE = 'report_schedule';
    const EVENT_ADMIN_ACTION = 'admin_action';
    const EVENT_ACCESS_DENIED = 'access_denied';
    const EVENT_SENSITIVE_DATA_ACCESS = 'sensitive_data_access';
    const EVENT_DATA_ANONYMIZATION = 'data_anonymization';
    
    // Severity levels
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';
    
    public function __construct($db = null) {
        if ($db === null) {
            require_once __DIR__ . '/../api/config/database.php';
            $this->db = Database::getInstance()->getConnection();
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * Log analytics event
     * 
     * @param string $eventType Event type
     * @param int $userId User ID
     * @param string $resource Resource accessed
     * @param array $details Event details
     * @param string $severity Severity level
     * @return bool Success status
     */
    public function logEvent(
        string $eventType,
        int $userId,
        string $resource,
        array $details = [],
        string $severity = self::SEVERITY_INFO
    ): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO analytics_audit_log 
                (event_type, user_id, resource, details, severity, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            return $stmt->execute([
                $eventType,
                $userId,
                $resource,
                json_encode($details),
                $severity,
                $this->getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            error_log("AnalyticsAuditLogger: Failed to log event - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log analytics view
     * 
     * @param int $userId User ID
     * @param string $dashboardType Dashboard type
     * @param array $filters Applied filters
     * @return bool Success status
     */
    public function logView(int $userId, string $dashboardType, array $filters = []): bool {
        return $this->logEvent(
            self::EVENT_VIEW,
            $userId,
            $dashboardType,
            [
                'filters' => $filters,
                'timestamp' => time()
            ],
            self::SEVERITY_INFO
        );
    }
    
    /**
     * Log data export
     * 
     * @param int $userId User ID
     * @param string $exportType Export type (CSV, PDF, Excel)
     * @param string $dataType Data type exported
     * @param array $filters Applied filters
     * @param int $recordCount Number of records exported
     * @return bool Success status
     */
    public function logExport(
        int $userId,
        string $exportType,
        string $dataType,
        array $filters = [],
        int $recordCount = 0
    ): bool {
        return $this->logEvent(
            self::EVENT_EXPORT,
            $userId,
            $dataType,
            [
                'export_type' => $exportType,
                'filters' => $filters,
                'record_count' => $recordCount,
                'timestamp' => time()
            ],
            self::SEVERITY_INFO
        );
    }
    
    /**
     * Log report generation
     * 
     * @param int $userId User ID
     * @param string $reportType Report type
     * @param array $parameters Report parameters
     * @return bool Success status
     */
    public function logReportGeneration(
        int $userId,
        string $reportType,
        array $parameters = []
    ): bool {
        return $this->logEvent(
            self::EVENT_REPORT_GENERATE,
            $userId,
            $reportType,
            [
                'parameters' => $parameters,
                'timestamp' => time()
            ],
            self::SEVERITY_INFO
        );
    }
    
    /**
     * Log scheduled report creation
     * 
     * @param int $userId User ID
     * @param string $reportType Report type
     * @param string $schedule Schedule (daily, weekly, monthly)
     * @param array $parameters Report parameters
     * @return bool Success status
     */
    public function logScheduledReport(
        int $userId,
        string $reportType,
        string $schedule,
        array $parameters = []
    ): bool {
        return $this->logEvent(
            self::EVENT_REPORT_SCHEDULE,
            $userId,
            $reportType,
            [
                'schedule' => $schedule,
                'parameters' => $parameters,
                'timestamp' => time()
            ],
            self::SEVERITY_INFO
        );
    }
    
    /**
     * Log administrative action
     * 
     * @param int $userId User ID
     * @param string $action Action performed
     * @param array $details Action details
     * @return bool Success status
     */
    public function logAdminAction(
        int $userId,
        string $action,
        array $details = []
    ): bool {
        return $this->logEvent(
            self::EVENT_ADMIN_ACTION,
            $userId,
            $action,
            $details,
            self::SEVERITY_WARNING
        );
    }
    
    /**
     * Log access denied event
     * 
     * @param int $userId User ID
     * @param string $resource Resource attempted
     * @param string $reason Denial reason
     * @return bool Success status
     */
    public function logAccessDenied(
        int $userId,
        string $resource,
        string $reason
    ): bool {
        return $this->logEvent(
            self::EVENT_ACCESS_DENIED,
            $userId,
            $resource,
            [
                'reason' => $reason,
                'timestamp' => time()
            ],
            self::SEVERITY_WARNING
        );
    }
    
    /**
     * Log sensitive data access
     * 
     * @param int $userId User ID
     * @param string $dataType Data type accessed
     * @param array $details Access details
     * @return bool Success status
     */
    public function logSensitiveDataAccess(
        int $userId,
        string $dataType,
        array $details = []
    ): bool {
        return $this->logEvent(
            self::EVENT_SENSITIVE_DATA_ACCESS,
            $userId,
            $dataType,
            $details,
            self::SEVERITY_WARNING
        );
    }
    
    /**
     * Log data anonymization
     * 
     * @param int $userId User ID
     * @param string $dataType Data type anonymized
     * @param int $recordCount Number of records
     * @return bool Success status
     */
    public function logDataAnonymization(
        int $userId,
        string $dataType,
        int $recordCount
    ): bool {
        return $this->logEvent(
            self::EVENT_DATA_ANONYMIZATION,
            $userId,
            $dataType,
            [
                'record_count' => $recordCount,
                'timestamp' => time()
            ],
            self::SEVERITY_INFO
        );
    }
    
    /**
     * Get audit log entries
     * 
     * @param array $filters Filter criteria
     * @param int $limit Number of entries
     * @param int $offset Offset for pagination
     * @return array Audit log entries
     */
    public function getAuditLog(
        array $filters = [],
        int $limit = 50,
        int $offset = 0
    ): array {
        try {
            $sql = "
                SELECT 
                    al.*,
                    u.email as user_email,
                    u.role as user_role
                FROM analytics_audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (isset($filters['event_type'])) {
                $sql .= " AND al.event_type = ?";
                $params[] = $filters['event_type'];
            }
            
            if (isset($filters['user_id'])) {
                $sql .= " AND al.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (isset($filters['severity'])) {
                $sql .= " AND al.severity = ?";
                $params[] = $filters['severity'];
            }
            
            if (isset($filters['date_from'])) {
                $sql .= " AND al.created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (isset($filters['date_to'])) {
                $sql .= " AND al.created_at <= ?";
                $params[] = $filters['date_to'];
            }
            
            $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("AnalyticsAuditLogger: Failed to get audit log - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get audit log count
     * 
     * @param array $filters Filter criteria
     * @return int Total count
     */
    public function getAuditLogCount(array $filters = []): int {
        try {
            $sql = "SELECT COUNT(*) FROM analytics_audit_log WHERE 1=1";
            $params = [];
            
            if (isset($filters['event_type'])) {
                $sql .= " AND event_type = ?";
                $params[] = $filters['event_type'];
            }
            
            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (isset($filters['severity'])) {
                $sql .= " AND severity = ?";
                $params[] = $filters['severity'];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("AnalyticsAuditLogger: Failed to get audit log count - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get audit statistics
     * 
     * @param string $period Period (day, week, month)
     * @return array Statistics
     */
    public function getAuditStats(string $period = 'day'): array {
        try {
            // Determine date filter based on period
            switch ($period) {
                case 'day':
                    $dateFilter = "DATE(created_at) = CURDATE()";
                    break;
                case 'week':
                    $dateFilter = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $dateFilter = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                default:
                    $dateFilter = "1=1";
                    break;
            }
            
            $stats = [];
            
            // Total events
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM analytics_audit_log WHERE $dateFilter
            ");
            $stats['total_events'] = (int)$stmt->fetchColumn();
            
            // By event type
            $stmt = $this->db->query("
                SELECT event_type, COUNT(*) as count
                FROM analytics_audit_log
                WHERE $dateFilter
                GROUP BY event_type
            ");
            $stats['by_event_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // By severity
            $stmt = $this->db->query("
                SELECT severity, COUNT(*) as count
                FROM analytics_audit_log
                WHERE $dateFilter
                GROUP BY severity
            ");
            $stats['by_severity'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Top users
            $stmt = $this->db->query("
                SELECT u.email, COUNT(*) as count
                FROM analytics_audit_log al
                JOIN users u ON al.user_id = u.id
                WHERE $dateFilter
                GROUP BY al.user_id
                ORDER BY count DESC
                LIMIT 10
            ");
            $stats['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
        } catch (Exception $e) {
            error_log("AnalyticsAuditLogger: Failed to get audit stats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Clean old audit logs
     * 
     * @param int $days Number of days to keep
     * @return int Number of deleted records
     */
    public function cleanOldLogs(int $days = 365): int {
        try {
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $stmt = $this->db->prepare("
                DELETE FROM analytics_audit_log
                WHERE created_at < ?
            ");
            
            $stmt->execute([$cutoff_date]);
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("AnalyticsAuditLogger: Failed to clean old logs - " . $e->getMessage());
            return 0;
        }
    }
}
