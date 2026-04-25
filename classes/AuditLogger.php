<?php
/**
 * AuditLogger Class
 * 
 * Records all backup and restore operations for audit trail.
 * Logs operations to backup_audit_log database table.
 * 
 * Requirements: 6.3
 */

class AuditLogger {
    private $db_connection;
    
    /**
     * Constructor
     * 
     * @param PDO $db_connection Database connection
     */
    public function __construct($db_connection = null) {
        if ($db_connection === null) {
            // Create default connection
            try {
                $this->db_connection = new PDO(
                    'mysql:host=localhost;port=3306;dbname=wdb_membership',
                    'root',
                    '',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                throw new Exception("Failed to connect to database: " . $e->getMessage());
            }
        } else {
            $this->db_connection = $db_connection;
        }
    }
    
    /**
     * Log backup operation
     * 
     * Requirements: 6.3
     * 
     * @param string $operation Operation type (create|delete|download)
     * @param string $backup_id Backup identifier
     * @param int $user_id User performing operation
     * @param string $result success|failure
     * @param string $details Additional details
     * @return bool True if logged successfully
     */
    public function logBackupOperation(
        string $operation,
        string $backup_id,
        int $user_id,
        string $result,
        string $details = ''
    ): bool {
        try {
            $stmt = $this->db_connection->prepare("
                INSERT INTO backup_audit_log 
                (operation_type, backup_id, user_id, result, details, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $ip_address = $this->getClientIp();
            
            return $stmt->execute([
                $operation,
                $backup_id,
                $user_id,
                $result,
                $details,
                $ip_address
            ]);
            
        } catch (PDOException $e) {
            // Log to error log as fallback
            error_log("AuditLogger: Failed to log backup operation - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log restore operation
     * 
     * Requirements: 6.3
     * 
     * @param string $backup_id Backup being restored
     * @param int $user_id User performing restore
     * @param string $result success|failure
     * @param string $details Additional details
     * @return bool True if logged successfully
     */
    public function logRestoreOperation(
        string $backup_id,
        int $user_id,
        string $result,
        string $details = ''
    ): bool {
        return $this->logBackupOperation('restore', $backup_id, $user_id, $result, $details);
    }
    
    /**
     * Get audit log entries
     * 
     * Requirements: 6.3
     * 
     * @param int $limit Number of entries to retrieve
     * @param int $offset Offset for pagination
     * @param string $operation_type Optional filter by operation type
     * @param int $user_id Optional filter by user ID
     * @return array Array of audit log entries
     */
    public function getAuditLog(
        int $limit = 50,
        int $offset = 0,
        string $operation_type = null,
        int $user_id = null
    ): array {
        try {
            $sql = "
                SELECT 
                    al.*,
                    u.email as user_email,
                    u.role as user_role
                FROM backup_audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($operation_type !== null) {
                $sql .= " AND al.operation_type = ?";
                $params[] = $operation_type;
            }
            
            if ($user_id !== null) {
                $sql .= " AND al.user_id = ?";
                $params[] = $user_id;
            }
            
            $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            
            $stmt = $this->db_connection->prepare($sql);
            
            // Bind integer parameters explicitly
            $paramIndex = 1;
            foreach ($params as $i => $param) {
                if ($i >= count($params) - 2) {
                    // Last two params are limit and offset - bind as integers
                    $stmt->bindValue($paramIndex++, $param, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($paramIndex++, $param);
                }
            }
            
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("AuditLogger: Failed to get audit log - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get audit log count
     * 
     * @param string $operation_type Optional filter by operation type
     * @param int $user_id Optional filter by user ID
     * @return int Total count
     */
    public function getAuditLogCount(
        string $operation_type = null,
        int $user_id = null
    ): int {
        try {
            $sql = "SELECT COUNT(*) FROM backup_audit_log WHERE 1=1";
            $params = [];
            
            if ($operation_type !== null) {
                $sql .= " AND operation_type = ?";
                $params[] = $operation_type;
            }
            
            if ($user_id !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $user_id;
            }
            
            $stmt = $this->db_connection->prepare($sql);
            $stmt->execute($params);
            
            return (int)$stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("AuditLogger: Failed to get audit log count - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get audit log statistics
     * 
     * @return array Statistics array
     */
    public function getAuditStats(): array {
        try {
            $stats = [
                'total_operations' => 0,
                'by_type' => [],
                'by_result' => [],
                'recent_operations' => []
            ];
            
            // Total operations
            $stmt = $this->db_connection->query("SELECT COUNT(*) FROM backup_audit_log");
            $stats['total_operations'] = (int)$stmt->fetchColumn();
            
            // By operation type
            $stmt = $this->db_connection->query("
                SELECT operation_type, COUNT(*) as count
                FROM backup_audit_log
                GROUP BY operation_type
            ");
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // By result
            $stmt = $this->db_connection->query("
                SELECT result, COUNT(*) as count
                FROM backup_audit_log
                GROUP BY result
            ");
            $stats['by_result'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Recent operations (last 10)
            $stats['recent_operations'] = $this->getAuditLog(10, 0);
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("AuditLogger: Failed to get audit stats - " . $e->getMessage());
            return [
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
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
     * Deletes logs older than specified days
     * 
     * @param int $days Number of days to keep
     * @return int Number of deleted records
     */
    public function cleanOldLogs(int $days = 90): int {
        try {
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $stmt = $this->db_connection->prepare("
                DELETE FROM backup_audit_log
                WHERE created_at < ?
            ");
            
            $stmt->execute([$cutoff_date]);
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("AuditLogger: Failed to clean old logs - " . $e->getMessage());
            return 0;
        }
    }
}
