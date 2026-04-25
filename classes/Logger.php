<?php
/**
 * Logger Class
 * 
 * PSR-3 compliant centralized logging system for the WDB Management System.
 * Provides comprehensive logging with multiple log levels, structured logging,
 * context data, log rotation, and integration with the monitoring system.
 * 
 * Requirements: 5.3.1, 5.3.2, 5.3.3, 5.3.4, 5.3.5
 * 
 * Features:
 * - PSR-3 LoggerInterface compliance
 * - Multiple log levels (emergency, alert, critical, error, warning, notice, info, debug)
 * - Structured logging with context data
 * - Automatic log rotation (daily, configurable retention)
 * - Log search and filtering capabilities
 * - Integration with error handler and monitoring system
 * - Security event logging
 * - Database query logging (development mode)
 * - Performance tracking
 */

class Logger {
    /**
     * Log levels (PSR-3 compliant)
     */
    const EMERGENCY = 'emergency'; // System is unusable
    const ALERT     = 'alert';     // Action must be taken immediately
    const CRITICAL  = 'critical';  // Critical conditions
    const ERROR     = 'error';     // Error conditions
    const WARNING   = 'warning';   // Warning conditions
    const NOTICE    = 'notice';    // Normal but significant condition
    const INFO      = 'info';      // Informational messages
    const DEBUG     = 'debug';     // Debug-level messages
    
    /**
     * Log level priorities (for filtering)
     */
    private static $levelPriorities = [
        self::EMERGENCY => 800,
        self::ALERT     => 700,
        self::CRITICAL  => 600,
        self::ERROR     => 500,
        self::WARNING   => 400,
        self::NOTICE    => 300,
        self::INFO      => 200,
        self::DEBUG     => 100
    ];
    
    /**
     * Configuration
     */
    private $logDir;
    private $logRetentionDays;
    private $maxLogSize;
    private $minLogLevel;
    private $environment;
    private $enableDatabaseLogging;
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration options
     */
    public function __construct(array $config = []) {
        $this->logDir = $config['log_dir'] ?? __DIR__ . '/../logs';
        $this->logRetentionDays = $config['retention_days'] ?? 30;
        $this->maxLogSize = $config['max_log_size'] ?? 10485760; // 10MB
        $this->minLogLevel = $config['min_log_level'] ?? self::INFO;
        $this->environment = $config['environment'] ?? 'production';
        $this->enableDatabaseLogging = $config['enable_db_logging'] ?? ($this->environment === 'development');
        
        $this->ensureLogDirectory();
    }
    
    /**
     * Get singleton instance
     * 
     * @param array $config Configuration options (only used on first call)
     * @return Logger
     */
    public static function getInstance(array $config = []): Logger {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    /**
     * System is unusable
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function emergency(string $message, array $context = []): void {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Action must be taken immediately
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function alert(string $message, array $context = []): void {
        $this->log(self::ALERT, $message, $context);
    }
    
    /**
     * Critical conditions
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function critical(string $message, array $context = []): void {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Error conditions
     * 
     * Requirements: 5.3.1
     * 
     * @param string $message Log message
     * @param array $context Additional context data (include 'exception' for stack traces)
     */
    public function error(string $message, array $context = []): void {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Warning conditions
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function warning(string $message, array $context = []): void {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Normal but significant condition
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function notice(string $message, array $context = []): void {
        $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Informational messages
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function info(string $message, array $context = []): void {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Debug-level messages
     * 
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function debug(string $message, array $context = []): void {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log security event
     * 
     * Requirements: 5.3.2
     * 
     * @param string $event Event type (login_attempt, permission_change, etc.)
     * @param string $message Event description
     * @param array $context Additional context data
     */
    public function security(string $event, string $message, array $context = []): void {
        $context['event_type'] = $event;
        $context['category'] = 'security';
        $this->log(self::WARNING, $message, $context);
        
        // Also write to dedicated security log
        $this->writeToFile('security.log', self::WARNING, $message, $context);
    }
    
    /**
     * Log database query (development mode only)
     * 
     * Requirements: 5.3.3
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @param float $executionTime Execution time in seconds
     */
    public function query(string $query, array $params = [], float $executionTime = 0): void {
        if (!$this->enableDatabaseLogging) {
            return;
        }
        
        $context = [
            'query' => $query,
            'params' => $params,
            'execution_time' => $executionTime,
            'category' => 'database'
        ];
        
        // Log slow queries as warnings
        if ($executionTime > 1.0) {
            $this->warning("Slow query detected ({$executionTime}s)", $context);
        } else {
            $this->debug("Database query executed", $context);
        }
        
        // Write to dedicated query log
        $this->writeToFile('query.log', self::DEBUG, "Query: {$query}", $context);
    }
    
    /**
     * Main logging method
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public function log(string $level, string $message, array $context = []): void {
        // Check if this level should be logged
        if (!$this->shouldLog($level)) {
            return;
        }
        
        // Add automatic context
        $context = $this->enrichContext($context);
        
        // Write to main application log
        $this->writeToFile('application.log', $level, $message, $context);
        
        // For errors and above, also write to error log
        if ($this->isErrorLevel($level)) {
            $this->writeToFile('error.log', $level, $message, $context);
        }
        
        // Send alerts for critical issues
        if ($this->isCriticalLevel($level)) {
            $this->sendAlert($level, $message, $context);
        }
    }
    
    /**
     * Write log entry to file
     * 
     * @param string $filename Log filename
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     */
    private function writeToFile(string $filename, string $level, string $message, array $context): void {
        $logFile = $this->logDir . '/' . $filename;
        
        // Check if log rotation is needed
        $this->rotateLogIfNeeded($logFile);
        
        // Format log entry (structured JSON format)
        $logEntry = $this->formatLogEntry($level, $message, $context);
        
        // Write to file
        error_log($logEntry . "\n", 3, $logFile);
    }
    
    /**
     * Format log entry as structured JSON
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return string Formatted log entry
     */
    private function formatLogEntry(string $level, string $message, array $context): string {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context
        ];
        
        return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Enrich context with automatic data
     * 
     * @param array $context Original context
     * @return array Enriched context
     */
    private function enrichContext(array $context): array {
        // Add request information
        if (php_sapi_name() !== 'cli') {
            $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $context['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
            $context['request_uri'] = $_SERVER['REQUEST_URI'] ?? 'unknown';
        } else {
            $context['cli'] = true;
        }
        
        // Add user information if available
        if (isset($_SESSION['user_id'])) {
            $context['user_id'] = $_SESSION['user_id'];
            $context['user_role'] = $_SESSION['role'] ?? 'unknown';
        }
        
        // Add exception stack trace if present
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        // Add memory usage
        $context['memory_usage'] = memory_get_usage(true);
        
        return $context;
    }
    
    /**
     * Check if log level should be logged
     * 
     * @param string $level Log level
     * @return bool
     */
    private function shouldLog(string $level): bool {
        $levelPriority = self::$levelPriorities[$level] ?? 0;
        $minPriority = self::$levelPriorities[$this->minLogLevel] ?? 0;
        
        return $levelPriority >= $minPriority;
    }
    
    /**
     * Check if level is an error level
     * 
     * @param string $level Log level
     * @return bool
     */
    private function isErrorLevel(string $level): bool {
        return in_array($level, [self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY]);
    }
    
    /**
     * Check if level is critical
     * 
     * @param string $level Log level
     * @return bool
     */
    private function isCriticalLevel(string $level): bool {
        return in_array($level, [self::CRITICAL, self::ALERT, self::EMERGENCY]);
    }
    
    /**
     * Send alert for critical issues
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     */
    private function sendAlert(string $level, string $message, array $context): void {
        // Only send alerts in production
        if ($this->environment !== 'production') {
            return;
        }
        
        // Check if AlertManager is available
        if (class_exists('AlertManager')) {
            try {
                $alertManager = new AlertManager();
                $alertManager->sendAlert(
                    'application_error',
                    "[$level] $message",
                    array_merge($context, ['level' => $level])
                );
            } catch (Exception $e) {
                // Fallback: log the alert failure
                error_log("Failed to send alert: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Rotate log file if it exceeds maximum size
     * 
     * Requirements: 5.3.4
     * 
     * @param string $logFile Path to log file
     */
    private function rotateLogIfNeeded(string $logFile): void {
        if (!file_exists($logFile)) {
            return;
        }
        
        $fileSize = filesize($logFile);
        
        if ($fileSize >= $this->maxLogSize) {
            // Create rotated filename with timestamp
            $timestamp = date('Y-m-d_His');
            $rotatedFile = $logFile . '.' . $timestamp;
            
            // Rename current log file
            rename($logFile, $rotatedFile);
            
            // Create new empty log file
            touch($logFile);
            chmod($logFile, 0644);
            
            // Clean old rotated logs
            $this->cleanOldRotatedLogs($logFile);
        }
    }
    
    /**
     * Clean old rotated log files
     * 
     * Requirements: 5.3.4
     * 
     * @param string $baseLogFile Base log file path
     */
    private function cleanOldRotatedLogs(string $baseLogFile): void {
        $pattern = $baseLogFile . '.*';
        $files = glob($pattern);
        
        if (!$files) {
            return;
        }
        
        $cutoffTime = time() - ($this->logRetentionDays * 86400);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
    
    /**
     * Search logs
     * 
     * Requirements: 5.3.5
     * 
     * @param array $filters Search filters
     * @return array Matching log entries
     */
    public function search(array $filters = []): array {
        $logFile = $filters['log_file'] ?? 'application.log';
        $level = $filters['level'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $searchTerm = $filters['search_term'] ?? null;
        $limit = $filters['limit'] ?? 100;
        
        $logPath = $this->logDir . '/' . $logFile;
        
        if (!file_exists($logPath)) {
            return [];
        }
        
        $results = [];
        $file = new SplFileObject($logPath, 'r');
        
        while (!$file->eof() && count($results) < $limit) {
            $line = trim($file->current());
            $file->next();
            
            if (empty($line)) {
                continue;
            }
            
            // Parse JSON log entry
            $entry = json_decode($line, true);
            
            if (!$entry) {
                continue;
            }
            
            // Apply filters
            if ($level && strtolower($entry['level']) !== strtolower($level)) {
                continue;
            }
            
            if ($startDate && $entry['timestamp'] < $startDate) {
                continue;
            }
            
            if ($endDate && $entry['timestamp'] > $endDate) {
                continue;
            }
            
            if ($searchTerm && stripos(json_encode($entry), $searchTerm) === false) {
                continue;
            }
            
            $results[] = $entry;
        }
        
        return array_reverse($results); // Most recent first
    }
    
    /**
     * Get log statistics
     * 
     * Requirements: 5.3.5
     * 
     * @param string $logFile Log file to analyze
     * @return array Statistics
     */
    public function getStatistics(string $logFile = 'application.log'): array {
        $logPath = $this->logDir . '/' . $logFile;
        
        if (!file_exists($logPath)) {
            return [
                'total_entries' => 0,
                'by_level' => [],
                'by_hour' => [],
                'file_size' => 0
            ];
        }
        
        $stats = [
            'total_entries' => 0,
            'by_level' => [],
            'by_hour' => [],
            'file_size' => filesize($logPath)
        ];
        
        $file = new SplFileObject($logPath, 'r');
        
        while (!$file->eof()) {
            $line = trim($file->current());
            $file->next();
            
            if (empty($line)) {
                continue;
            }
            
            $entry = json_decode($line, true);
            
            if (!$entry) {
                continue;
            }
            
            $stats['total_entries']++;
            
            // Count by level
            $level = $entry['level'] ?? 'UNKNOWN';
            $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
            
            // Count by hour
            if (isset($entry['timestamp'])) {
                $hour = substr($entry['timestamp'], 0, 13); // YYYY-MM-DD HH
                $stats['by_hour'][$hour] = ($stats['by_hour'][$hour] ?? 0) + 1;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get available log files
     * 
     * Requirements: 5.3.5
     * 
     * @return array List of log files with metadata
     */
    public function getLogFiles(): array {
        $files = glob($this->logDir . '/*.log*');
        $logFiles = [];
        
        foreach ($files as $file) {
            $logFiles[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'modified_formatted' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by modified time (newest first)
        usort($logFiles, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $logFiles;
    }
    
    /**
     * Ensure log directory exists with proper permissions
     */
    private function ensureLogDirectory(): void {
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        // Ensure log directory is writable
        if (!is_writable($this->logDir)) {
            chmod($this->logDir, 0755);
        }
        
        // Create .htaccess to prevent direct access to logs
        $htaccessFile = $this->logDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all\n");
        }
        
        // Create index.php to prevent directory listing
        $indexFile = $this->logDir . '/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\nhttp_response_code(403);\nexit('Access denied');\n");
        }
    }
    
    /**
     * Clear all logs (use with caution)
     * 
     * @return bool Success status
     */
    public function clearAllLogs(): bool {
        $files = glob($this->logDir . '/*.log*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }
}
