<?php
/**
 * EmailQueue Class
 * 
 * Manages email queue for reliable and performant email delivery.
 * Provides queue management, processing, and retry logic for failed emails.
 * 
 * Requirements: 2.3.5 - Email queue system for high volume
 * 
 * @package WDB\Email
 * @version 1.0.0
 */

require_once __DIR__ . '/EmailConfig.php';
require_once __DIR__ . '/Database.php';

class EmailQueue {
    private $db;
    private $emailConfig;
    private $table = 'email_queue';
    
    // Queue status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    
    // Retry configuration
    const MAX_ATTEMPTS = 3;
    const RETRY_DELAY = 300; // 5 minutes in seconds
    
    /**
     * Constructor
     * 
     * @param Database|null $database Optional database instance for testing
     * @param EmailConfig|null $emailConfig Optional email config for testing
     */
    public function __construct($database = null, $emailConfig = null) {
        $this->db = $database ?: Database::getInstance();
        $this->emailConfig = $emailConfig ?: new EmailConfig();
    }
    
    /**
     * Create email queue table if it doesn't exist
     * 
     * Requirements: 2.3.5 - Create email queue table
     * 
     * @return bool Success status
     */
    public function createTable(): bool {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_address VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                body TEXT NOT NULL,
                body_text TEXT,
                status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
                attempts INT DEFAULT 0,
                max_attempts INT DEFAULT 3,
                last_error TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                sent_at TIMESTAMP NULL,
                next_retry_at TIMESTAMP NULL,
                INDEX idx_status (status),
                INDEX idx_next_retry (next_retry_at),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $conn->exec($sql);
            return true;
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to create table - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add email to queue
     * 
     * Requirements: 2.3.5 - Implement queue management
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body HTML email body
     * @param string|null $bodyText Plain text email body
     * @param int $maxAttempts Maximum retry attempts
     * @return int|false Queue ID on success, false on failure
     */
    public function enqueue(
        string $to, 
        string $subject, 
        string $body, 
        ?string $bodyText = null,
        int $maxAttempts = self::MAX_ATTEMPTS
    ) {
        // Validate email address
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("EmailQueue: Invalid email address - $to");
            return false;
        }
        
        try {
            $conn = $this->db->getConnection();
            
            $sql = "INSERT INTO {$this->table} 
                    (to_address, subject, body, body_text, status, max_attempts) 
                    VALUES (:to, :subject, :body, :body_text, :status, :max_attempts)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':to' => $to,
                ':subject' => $subject,
                ':body' => $body,
                ':body_text' => $bodyText,
                ':status' => self::STATUS_PENDING,
                ':max_attempts' => $maxAttempts
            ]);
            
            return (int)$conn->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to enqueue email - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process pending emails in the queue
     * 
     * Requirements: 2.3.5 - Implement queue processor
     * 
     * @param int $limit Maximum number of emails to process
     * @return array Processing results with counts
     */
    public function processPending(int $limit = 10): array {
        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        try {
            // Get pending emails ready to be sent
            $emails = $this->getPendingEmails($limit);
            
            foreach ($emails as $email) {
                $results['processed']++;
                
                // Mark as processing
                $this->updateStatus($email['id'], self::STATUS_PROCESSING);
                
                // Attempt to send
                $sent = $this->sendEmail($email);
                
                if ($sent) {
                    // Mark as sent
                    $this->markAsSent($email['id']);
                    $results['sent']++;
                } else {
                    // Handle failure with retry logic
                    $this->handleFailure($email);
                    $results['failed']++;
                }
            }
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            error_log("EmailQueue: Processing error - " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Get pending emails ready to be sent
     * 
     * @param int $limit Maximum number of emails to retrieve
     * @return array Array of pending email records
     */
    private function getPendingEmails(int $limit): array {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "SELECT * FROM {$this->table} 
                    WHERE status = :status 
                    AND (next_retry_at IS NULL OR next_retry_at <= NOW())
                    ORDER BY created_at ASC 
                    LIMIT :limit";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':status', self::STATUS_PENDING, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to get pending emails - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send an email from the queue
     * 
     * @param array $email Email record from queue
     * @return bool Success status
     */
    private function sendEmail(array $email): bool {
        try {
            $config = $this->emailConfig->getConfig();
            
            // Use PHPMailer if available and SMTP is enabled
            if ($config['smtp_enabled'] && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendWithPHPMailer($email, $config);
            } else {
                return $this->sendWithMailFunction($email, $config);
            }
            
        } catch (Exception $e) {
            error_log("EmailQueue: Failed to send email ID {$email['id']} - " . $e->getMessage());
            $this->updateLastError($email['id'], $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHPMailer
     * 
     * @param array $email Email record
     * @param array $config Email configuration
     * @return bool Success status
     */
    private function sendWithPHPMailer(array $email, array $config): bool {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mailer->isSMTP();
            $mailer->Host = $config['host'];
            $mailer->SMTPAuth = $config['smtp_auth'];
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'];
            $mailer->Port = $config['port'];
            
            if (!empty($config['encryption']) && $config['encryption'] !== 'none') {
                $mailer->SMTPSecure = $config['encryption'];
            }
            
            // Recipients
            $mailer->setFrom($config['from_address'], $config['from_name']);
            $mailer->addAddress($email['to_address']);
            
            // Content
            $mailer->isHTML(true);
            $mailer->Subject = $email['subject'];
            $mailer->Body = $email['body'];
            
            if (!empty($email['body_text'])) {
                $mailer->AltBody = $email['body_text'];
            }
            
            return $mailer->send();
            
        } catch (Exception $e) {
            $this->updateLastError($email['id'], $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHP mail() function
     * 
     * @param array $email Email record
     * @param array $config Email configuration
     * @return bool Success status
     */
    private function sendWithMailFunction(array $email, array $config): bool {
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "From: {$config['from_name']} <{$config['from_address']}>";
        $headers[] = "Reply-To: {$config['from_address']}";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        
        $headers_string = implode("\r\n", $headers);
        
        $result = mail($email['to_address'], $email['subject'], $email['body'], $headers_string);
        
        if (!$result) {
            $this->updateLastError($email['id'], 'mail() function returned false');
        }
        
        return $result;
    }
    
    /**
     * Handle failed email with retry logic
     * 
     * Requirements: 2.3.5 - Add retry logic for failed emails
     * 
     * @param array $email Email record
     * @return void
     */
    private function handleFailure(array $email): void {
        $attempts = (int)$email['attempts'] + 1;
        $maxAttempts = (int)$email['max_attempts'];
        
        if ($attempts >= $maxAttempts) {
            // Max attempts reached, mark as permanently failed
            $this->markAsFailed($email['id'], $attempts);
        } else {
            // Schedule retry with exponential backoff
            $this->scheduleRetry($email['id'], $attempts);
        }
    }
    
    /**
     * Schedule email for retry with exponential backoff
     * 
     * @param int $id Email queue ID
     * @param int $attempts Current attempt count
     * @return bool Success status
     */
    private function scheduleRetry(int $id, int $attempts): bool {
        try {
            $conn = $this->db->getConnection();
            
            // Calculate next retry time with exponential backoff
            // Attempt 1: 5 minutes, Attempt 2: 15 minutes
            $delay = self::RETRY_DELAY * pow(3, $attempts - 1);
            $nextRetry = date('Y-m-d H:i:s', time() + $delay);
            
            $sql = "UPDATE {$this->table} 
                    SET status = :status, 
                        attempts = :attempts,
                        next_retry_at = :next_retry
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':status' => self::STATUS_PENDING,
                ':attempts' => $attempts,
                ':next_retry' => $nextRetry,
                ':id' => $id
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to schedule retry - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update email status
     * 
     * @param int $id Email queue ID
     * @param string $status New status
     * @return bool Success status
     */
    private function updateStatus(int $id, string $status): bool {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "UPDATE {$this->table} SET status = :status WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':status' => $status, ':id' => $id]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to update status - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark email as sent
     * 
     * @param int $id Email queue ID
     * @return bool Success status
     */
    private function markAsSent(int $id): bool {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "UPDATE {$this->table} 
                    SET status = :status, 
                        sent_at = NOW(),
                        last_error = NULL
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([':status' => self::STATUS_SENT, ':id' => $id]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to mark as sent - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark email as permanently failed
     * 
     * @param int $id Email queue ID
     * @param int $attempts Final attempt count
     * @return bool Success status
     */
    private function markAsFailed(int $id, int $attempts): bool {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "UPDATE {$this->table} 
                    SET status = :status, 
                        attempts = :attempts
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':status' => self::STATUS_FAILED,
                ':attempts' => $attempts,
                ':id' => $id
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to mark as failed - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update last error message for an email
     * 
     * @param int $id Email queue ID
     * @param string $error Error message
     * @return bool Success status
     */
    private function updateLastError(int $id, string $error): bool {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "UPDATE {$this->table} SET last_error = :error WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':error' => $error, ':id' => $id]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to update error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get queue statistics
     * 
     * @return array Queue statistics
     */
    public function getStats(): array {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "SELECT 
                        status,
                        COUNT(*) as count
                    FROM {$this->table}
                    GROUP BY status";
            
            $stmt = $conn->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [
                'pending' => 0,
                'processing' => 0,
                'sent' => 0,
                'failed' => 0,
                'total' => 0
            ];
            
            foreach ($results as $row) {
                $stats[$row['status']] = (int)$row['count'];
                $stats['total'] += (int)$row['count'];
            }
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to get stats - " . $e->getMessage());
            return [
                'pending' => 0,
                'processing' => 0,
                'sent' => 0,
                'failed' => 0,
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get failed emails
     * 
     * @param int $limit Maximum number of records to retrieve
     * @return array Array of failed email records
     */
    public function getFailedEmails(int $limit = 50): array {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "SELECT * FROM {$this->table} 
                    WHERE status = :status 
                    ORDER BY created_at DESC 
                    LIMIT :limit";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':status', self::STATUS_FAILED, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to get failed emails - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retry a failed email
     * 
     * @param int $id Email queue ID
     * @return bool Success status
     */
    public function retryEmail(int $id): bool {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "UPDATE {$this->table} 
                    SET status = :status, 
                        attempts = 0,
                        next_retry_at = NULL,
                        last_error = NULL
                    WHERE id = :id AND status = :failed_status";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':status' => self::STATUS_PENDING,
                ':failed_status' => self::STATUS_FAILED,
                ':id' => $id
            ]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to retry email - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old sent emails
     * 
     * @param int $daysOld Number of days to keep sent emails
     * @return int Number of records deleted
     */
    public function cleanupOldEmails(int $daysOld = 30): int {
        try {
            $conn = $this->db->getConnection();
            
            $sql = "DELETE FROM {$this->table} 
                    WHERE status = :status 
                    AND sent_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':status' => self::STATUS_SENT,
                ':days' => $daysOld
            ]);
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("EmailQueue: Failed to cleanup old emails - " . $e->getMessage());
            return 0;
        }
    }
}
