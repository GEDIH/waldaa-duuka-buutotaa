<?php
/**
 * Database Class - Handles all database operations
 */

if (!defined('WDB_SYSTEM')) {
    die('Direct access not allowed');
}

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Execute a prepared statement
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Database query failed");
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert record and return last insert ID
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->execute($sql, $data);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update record
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $key) {
            $setParts[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Delete record
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->execute($sql, $params);
    }
    
    /**
     * Check if record exists
     */
    public function exists($table, $where, $params = []) {
        $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
        $result = $this->fetchOne($sql, $params);
        return $result !== false;
    }
    
    /**
     * Count records
     */
    public function count($table, $where = '1=1', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$where}";
        $result = $this->fetchOne($sql, $params);
        return (int)$result['count'];
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Get table structure
     */
    public function getTableStructure($table) {
        $sql = "DESCRIBE {$table}";
        return $this->fetchAll($sql);
    }
    
    /**
     * Backup table data
     */
    public function backupTable($table) {
        $sql = "SELECT * FROM {$table}";
        return $this->fetchAll($sql);
    }
    
    /**
     * Get database statistics
     */
    public function getStats() {
        $stats = [];
        
        // Get table sizes
        $sql = "
            SELECT 
                table_name,
                table_rows,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = :database
            ORDER BY size_mb DESC
        ";
        
        $stats['tables'] = $this->fetchAll($sql, ['database' => DB_NAME]);
        
        // Get total database size
        $sql = "
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_size_mb
            FROM information_schema.tables 
            WHERE table_schema = :database
        ";
        
        $result = $this->fetchOne($sql, ['database' => DB_NAME]);
        $stats['total_size'] = $result['total_size_mb'];
        
        return $stats;
    }
    
    /**
     * Optimize database tables
     */
    public function optimizeTables() {
        $tables = ['members', 'administrators', 'centers', 'contributions', 'announcements', 'audit_logs'];
        $results = [];
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("OPTIMIZE TABLE {$table}");
                $results[$table] = 'success';
            } catch (Exception $e) {
                $results[$table] = 'failed: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Clean old audit logs (older than 90 days)
     */
    public function cleanOldLogs($days = 90) {
        $sql = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->execute($sql, [$days]);
        return $stmt->rowCount();
    }
    
    /**
     * Get connection status
     */
    public function getConnectionStatus() {
        try {
            $this->pdo->query('SELECT 1');
            return [
                'status' => 'connected',
                'server_info' => $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO),
                'client_version' => $this->pdo->getAttribute(PDO::ATTR_CLIENT_VERSION)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>