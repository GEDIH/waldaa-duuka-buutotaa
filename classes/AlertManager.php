<?php
/**
 * Alert Manager
 * Manages alerting system for production monitoring
 * Requirements: 5.2.1, 5.2.2, 5.2.3, 5.2.4, 5.2.5
 * 
 * This class provides:
 * - Email alerts (5.2.1, 5.2.2, 5.2.3, 5.2.4, 5.2.5)
 * - SMS alerts (optional)
 * - Alert throttling to prevent spam
 * - Integration with monitoring infrastructure
 * 
 * Alert Types:
 * - High error rate alerts (5.2.1)
 * - Database connection failure alerts (5.2.2)
 * - Disk space alerts (5.2.3)
 * - Backup failure alerts (5.2.4)
 * - Security incident alerts (5.2.5)
 */

require_once __DIR__ . '/EmailConfig.php';
require_once __DIR__ . '/CacheManager.php';
require_once __DIR__ . '/ApplicationMonitor.php';
require_once __DIR__ . '/DatabaseMonitor.php';
require_once __DIR__ . '/SystemMonitor.php';

class AlertManager
{
    private static $instance = null;
    private $emailConfig;
    private $cache;
    
    // Alert configuration
    private $config = [
        'enabled' => true,
        'email_enabled' => true,
        'sms_enabled' => false,
        'throttle_enabled' => true,
        'throttle_window' => 3600, // 1 hour - don't send same alert more than once per hour
        'batch_alerts' => true,
        'batch_window' => 300 // 5 minutes - batch alerts within this window
    ];
    
    // Alert recipients configuration
    private $recipients = [
        'critical' => [], // Critical alerts go to all admins
        'warning' => [],  // Warnings go to operations team
        'info' => []      // Info alerts go to monitoring team
    ];
    
    // Alert thresholds (integrated with monitors)
    private $thresholds = [
        'error_rate_percent' => 5.0,           // 5% error rate (5.2.1)
        'critical_error_rate_percent' => 10.0, // 10% critical error rate
        'disk_space_percent' => 10.0,          // < 10% free disk space (5.2.3)
        'critical_disk_space_percent' => 5.0,  // < 5% critical
        'memory_usage_percent' => 80.0,        // > 80% memory usage
        'connection_failure_rate' => 5.0       // 5% connection failure rate (5.2.2)
    ];
    
    private function __construct()
    {
        $this->emailConfig = new EmailConfig();
        $this->cache = CacheManager::getInstance();
        $this->loadRecipients();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load alert recipients from environment or configuration
     */
    private function loadRecipients()
    {
        // Load from environment variables
        $critical_emails = getenv('ALERT_CRITICAL_EMAILS') ?: '';
        $warning_emails = getenv('ALERT_WARNING_EMAILS') ?: '';
        $info_emails = getenv('ALERT_INFO_EMAILS') ?: '';
        
        // Parse comma-separated email lists
        $this->recipients['critical'] = $this->parseEmailList($critical_emails);
        $this->recipients['warning'] = $this->parseEmailList($warning_emails);
        $this->recipients['info'] = $this->parseEmailList($info_emails);
        
        // If no recipients configured, use default from email config
        if (empty($this->recipients['critical'])) {
            $default_email = getenv('ALERT_DEFAULT_EMAIL') ?: getenv('MAIL_FROM_ADDRESS');
            if ($default_email) {
                $this->recipients['critical'][] = $default_email;
                $this->recipients['warning'][] = $default_email;
            }
        }
    }
    
    /**
     * Parse comma-separated email list
     * 
     * @param string $emailList Comma-separated emails
     * @return array Array of valid email addresses
     */
    private function parseEmailList($emailList)
    {
        if (empty($emailList)) {
            return [];
        }
        
        $emails = array_map('trim', explode(',', $emailList));
        $validEmails = [];
        
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            }
        }
        
        return $validEmails;
    }
    
    /**
     * Send alert
     * Main method to send alerts with throttling
     * 
     * @param string $type Alert type (critical, warning, info)
     * @param string $category Alert category (error_rate, disk_space, etc.)
     * @param string $message Alert message
     * @param array $data Additional alert data
     * @return array Send result
     */
    public function sendAlert($type, $category, $message, $data = [])
    {
        if (!$this->config['enabled']) {
            return ['success' => false, 'message' => 'Alerting disabled'];
        }
        
        // Check throttling
        if ($this->config['throttle_enabled'] && $this->isThrottled($category, $type)) {
            return [
                'success' => false,
                'message' => 'Alert throttled',
                'throttled' => true
            ];
        }
        
        // Create alert record
        $alert = [
            'type' => $type,
            'category' => $category,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown'
        ];
        
        // Store alert
        $this->storeAlert($alert);
        
        // Send via configured channels
        $results = [];
        
        if ($this->config['email_enabled']) {
            $results['email'] = $this->sendEmailAlert($alert);
        }
        
        if ($this->config['sms_enabled']) {
            $results['sms'] = $this->sendSMSAlert($alert);
        }
        
        // Update throttle cache
        if ($this->config['throttle_enabled']) {
            $this->updateThrottle($category, $type);
        }
        
        return [
            'success' => true,
            'alert_id' => $alert['timestamp'],
            'results' => $results
        ];
    }
    
    /**
     * Check if alert is throttled
     * Requirement: Alert throttling to prevent spam
     * 
     * @param string $category Alert category
     * @param string $type Alert type
     * @return bool True if throttled
     */
    private function isThrottled($category, $type)
    {
        $throttleKey = "alert_throttle_{$category}_{$type}";
        $lastSent = $this->cache->get($throttleKey);
        
        if ($lastSent === null) {
            return false;
        }
        
        $timeSinceLastAlert = time() - $lastSent;
        return $timeSinceLastAlert < $this->config['throttle_window'];
    }
    
    /**
     * Update throttle cache
     * 
     * @param string $category Alert category
     * @param string $type Alert type
     */
    private function updateThrottle($category, $type)
    {
        $throttleKey = "alert_throttle_{$category}_{$type}";
        $this->cache->set($throttleKey, time(), $this->config['throttle_window']);
    }
    
    /**
     * Store alert in cache for history
     * 
     * @param array $alert Alert data
     */
    private function storeAlert($alert)
    {
        $alertKey = "alert_history_" . $alert['timestamp'];
        $this->cache->set($alertKey, $alert, 86400); // Store for 24 hours
        
        // Also log to error log
        $logMessage = "[{$alert['type']}] {$alert['category']}: {$alert['message']}";
        error_log("AlertManager: $logMessage");
    }
    
    /**
     * Send email alert
     * Requirements: 5.2.1, 5.2.2, 5.2.3, 5.2.4, 5.2.5
     * 
     * @param array $alert Alert data
     * @return array Send result
     */
    private function sendEmailAlert($alert)
    {
        $recipients = $this->getRecipientsForType($alert['type']);
        
        if (empty($recipients)) {
            return [
                'success' => false,
                'message' => 'No recipients configured for alert type: ' . $alert['type']
            ];
        }
        
        $subject = $this->buildEmailSubject($alert);
        $htmlBody = $this->buildEmailBody($alert);
        $textBody = $this->buildEmailTextBody($alert);
        
        $results = [];
        $successCount = 0;
        
        foreach ($recipients as $recipient) {
            try {
                $sent = $this->sendEmail($recipient, $subject, $htmlBody, $textBody);
                $results[$recipient] = $sent;
                if ($sent) {
                    $successCount++;
                }
            } catch (Exception $e) {
                $results[$recipient] = false;
                error_log("AlertManager: Failed to send email to $recipient - " . $e->getMessage());
            }
        }
        
        return [
            'success' => $successCount > 0,
            'sent_count' => $successCount,
            'total_recipients' => count($recipients),
            'results' => $results
        ];
    }
    
    /**
     * Get recipients for alert type
     * 
     * @param string $type Alert type
     * @return array Email addresses
     */
    private function getRecipientsForType($type)
    {
        return $this->recipients[$type] ?? $this->recipients['critical'] ?? [];
    }
    
    /**
     * Build email subject
     * 
     * @param array $alert Alert data
     * @return string Email subject
     */
    private function buildEmailSubject($alert)
    {
        $typeLabel = strtoupper($alert['type']);
        $server = $alert['server'];
        $category = ucwords(str_replace('_', ' ', $alert['category']));
        
        return "[$typeLabel] $category Alert - $server";
    }
    
    /**
     * Build email HTML body
     * 
     * @param array $alert Alert data
     * @return string HTML content
     */
    private function buildEmailBody($alert)
    {
        $typeColor = $this->getAlertTypeColor($alert['type']);
        $typeIcon = $this->getAlertTypeIcon($alert['type']);
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background-color: $typeColor; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .alert-message { font-size: 16px; font-weight: bold; margin: 15px 0; padding: 15px; background-color: white; border-left: 4px solid $typeColor; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                .label { font-weight: bold; width: 150px; }
                .footer { padding: 15px; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin: 0;'>$typeIcon " . strtoupper($alert['type']) . " ALERT</h2>
                </div>
                <div class='content'>
                    <div class='alert-message'>
                        {$alert['message']}
                    </div>
                    
                    <table>
                        <tr>
                            <td class='label'>Alert Type:</td>
                            <td>" . strtoupper($alert['type']) . "</td>
                        </tr>
                        <tr>
                            <td class='label'>Category:</td>
                            <td>" . ucwords(str_replace('_', ' ', $alert['category'])) . "</td>
                        </tr>
                        <tr>
                            <td class='label'>Time:</td>
                            <td>{$alert['datetime']}</td>
                        </tr>
                        <tr>
                            <td class='label'>Server:</td>
                            <td>{$alert['server']}</td>
                        </tr>
                        <tr>
                            <td class='label'>IP Address:</td>
                            <td>{$alert['ip']}</td>
                        </tr>
                    </table>";
        
        // Add additional data if present
        if (!empty($alert['data'])) {
            $html .= "<h3>Additional Details:</h3><table>";
            foreach ($alert['data'] as $key => $value) {
                $label = ucwords(str_replace('_', ' ', $key));
                $displayValue = is_array($value) ? json_encode($value) : htmlspecialchars($value);
                $html .= "<tr><td class='label'>$label:</td><td>$displayValue</td></tr>";
            }
            $html .= "</table>";
        }
        
        $html .= "
                    <p style='margin-top: 20px; padding: 10px; background-color: #fff3cd; border-left: 4px solid #ffc107;'>
                        <strong>Action Required:</strong> Please investigate this alert and take appropriate action.
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated alert from the WDB Management System.</p>
                    <p>Alert ID: {$alert['timestamp']}</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Build email plain text body
     * 
     * @param array $alert Alert data
     * @return string Plain text content
     */
    private function buildEmailTextBody($alert)
    {
        $text = strtoupper($alert['type']) . " ALERT\n";
        $text .= str_repeat("=", 50) . "\n\n";
        $text .= $alert['message'] . "\n\n";
        $text .= "Alert Details:\n";
        $text .= "- Type: " . strtoupper($alert['type']) . "\n";
        $text .= "- Category: " . ucwords(str_replace('_', ' ', $alert['category'])) . "\n";
        $text .= "- Time: {$alert['datetime']}\n";
        $text .= "- Server: {$alert['server']}\n";
        $text .= "- IP Address: {$alert['ip']}\n";
        
        if (!empty($alert['data'])) {
            $text .= "\nAdditional Details:\n";
            foreach ($alert['data'] as $key => $value) {
                $label = ucwords(str_replace('_', ' ', $key));
                $displayValue = is_array($value) ? json_encode($value) : $value;
                $text .= "- $label: $displayValue\n";
            }
        }
        
        $text .= "\n" . str_repeat("-", 50) . "\n";
        $text .= "This is an automated alert from the WDB Management System.\n";
        $text .= "Alert ID: {$alert['timestamp']}\n";
        
        return $text;
    }
    
    /**
     * Get color for alert type
     * 
     * @param string $type Alert type
     * @return string Hex color code
     */
    private function getAlertTypeColor($type)
    {
        $colors = [
            'critical' => '#dc3545',
            'warning' => '#ffc107',
            'info' => '#17a2b8'
        ];
        
        return $colors[$type] ?? '#6c757d';
    }
    
    /**
     * Get icon for alert type
     * 
     * @param string $type Alert type
     * @return string Icon character
     */
    private function getAlertTypeIcon($type)
    {
        $icons = [
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️'
        ];
        
        return $icons[$type] ?? '📢';
    }
    
    /**
     * Send email using EmailConfig
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML body
     * @param string $textBody Plain text body
     * @return bool Success status
     */
    private function sendEmail($to, $subject, $htmlBody, $textBody)
    {
        try {
            // Use PHPMailer if available
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $config = $this->emailConfig->getConfig();
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                
                // Server settings
                if ($config['smtp_enabled']) {
                    $mailer->isSMTP();
                    $mailer->Host = $config['host'];
                    $mailer->SMTPAuth = $config['smtp_auth'];
                    $mailer->Username = $config['username'];
                    $mailer->Password = $config['password'];
                    $mailer->Port = $config['port'];
                    
                    if (!empty($config['encryption']) && $config['encryption'] !== 'none') {
                        $mailer->SMTPSecure = $config['encryption'];
                    }
                }
                
                // Recipients
                $mailer->setFrom($config['from_address'], $config['from_name']);
                $mailer->addAddress($to);
                
                // Content
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                $mailer->Body = $htmlBody;
                $mailer->AltBody = $textBody;
                
                return $mailer->send();
            } else {
                // Fallback to PHP mail()
                $config = $this->emailConfig->getConfig();
                $headers = [];
                $headers[] = "MIME-Version: 1.0";
                $headers[] = "Content-Type: text/html; charset=UTF-8";
                $headers[] = "From: {$config['from_name']} <{$config['from_address']}>";
                $headers[] = "X-Mailer: PHP/" . phpversion();
                
                return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
            }
        } catch (Exception $e) {
            error_log("AlertManager: Failed to send email - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SMS alert (optional implementation)
     * Requirement: SMS alerts (optional)
     * 
     * @param array $alert Alert data
     * @return array Send result
     */
    private function sendSMSAlert($alert)
    {
        // SMS implementation would require an SMS gateway service
        // Examples: Twilio, AWS SNS, Nexmo, etc.
        
        // Placeholder implementation
        return [
            'success' => false,
            'message' => 'SMS alerts not configured',
            'note' => 'To enable SMS alerts, configure an SMS gateway service'
        ];
        
        /* Example Twilio implementation:
        $twilioSid = getenv('TWILIO_SID');
        $twilioToken = getenv('TWILIO_TOKEN');
        $twilioFrom = getenv('TWILIO_FROM_NUMBER');
        $twilioTo = getenv('ALERT_SMS_NUMBERS'); // Comma-separated
        
        if (empty($twilioSid) || empty($twilioToken)) {
            return ['success' => false, 'message' => 'Twilio not configured'];
        }
        
        $client = new Twilio\Rest\Client($twilioSid, $twilioToken);
        $message = "[{$alert['type']}] {$alert['category']}: {$alert['message']}";
        
        $recipients = explode(',', $twilioTo);
        foreach ($recipients as $recipient) {
            $client->messages->create(
                trim($recipient),
                ['from' => $twilioFrom, 'body' => $message]
            );
        }
        */
    }
    
    /**
     * Check monitoring systems and send alerts if thresholds exceeded
     * This method integrates with ApplicationMonitor, DatabaseMonitor, and SystemMonitor
     * Requirements: 5.2.1, 5.2.2, 5.2.3, 5.2.4, 5.2.5
     */
    public function checkAndAlert()
    {
        $alerts = [];
        
        // Check application metrics (5.2.1 - High error rate)
        try {
            $appMonitor = ApplicationMonitor::getInstance();
            $errorMetrics = $appMonitor->getErrorRateMetrics(1);
            
            if ($errorMetrics['critical_threshold_exceeded']) {
                $alerts[] = $this->sendAlert(
                    'critical',
                    'high_error_rate',
                    "Critical error rate detected: {$errorMetrics['current_error_rate']}% (threshold: {$this->thresholds['critical_error_rate_percent']}%)",
                    [
                        'error_rate' => $errorMetrics['current_error_rate'],
                        'threshold' => $this->thresholds['critical_error_rate_percent'],
                        'total_errors' => $errorMetrics['total_errors'],
                        'total_requests' => $errorMetrics['total_requests']
                    ]
                );
            } elseif ($errorMetrics['threshold_exceeded']) {
                $alerts[] = $this->sendAlert(
                    'warning',
                    'high_error_rate',
                    "High error rate detected: {$errorMetrics['current_error_rate']}% (threshold: {$this->thresholds['error_rate_percent']}%)",
                    [
                        'error_rate' => $errorMetrics['current_error_rate'],
                        'threshold' => $this->thresholds['error_rate_percent'],
                        'total_errors' => $errorMetrics['total_errors'],
                        'total_requests' => $errorMetrics['total_requests']
                    ]
                );
            }
        } catch (Exception $e) {
            error_log("AlertManager: Failed to check application metrics - " . $e->getMessage());
        }
        
        // Check database metrics (5.2.2 - Database connection failures)
        try {
            $dbMonitor = DatabaseMonitor::getInstance();
            $failureMetrics = $dbMonitor->getConnectionFailureMetrics(1);
            
            if ($failureMetrics['threshold_exceeded']) {
                $alerts[] = $this->sendAlert(
                    'critical',
                    'database_connection_failure',
                    "Database connection failures detected: {$failureMetrics['failure_rate']}% (threshold: {$this->thresholds['connection_failure_rate']}%)",
                    [
                        'failure_rate' => $failureMetrics['failure_rate'],
                        'threshold' => $this->thresholds['connection_failure_rate'],
                        'total_failures' => $failureMetrics['total_failures'],
                        'recent_failures' => $failureMetrics['recent_failures']
                    ]
                );
            }
        } catch (Exception $e) {
            error_log("AlertManager: Failed to check database metrics - " . $e->getMessage());
        }
        
        // Check system metrics (5.2.3 - Disk space)
        try {
            $sysMonitor = SystemMonitor::getInstance();
            $diskMetrics = $sysMonitor->getDiskSpaceMetrics(1);
            
            if ($diskMetrics['min_free_percent'] < $this->thresholds['critical_disk_space_percent']) {
                $alerts[] = $this->sendAlert(
                    'critical',
                    'low_disk_space',
                    "Critical low disk space: {$diskMetrics['min_free_percent']}% free (threshold: {$this->thresholds['critical_disk_space_percent']}%)",
                    [
                        'free_percent' => $diskMetrics['min_free_percent'],
                        'threshold' => $this->thresholds['critical_disk_space_percent']
                    ]
                );
            } elseif ($diskMetrics['threshold_exceeded']) {
                $alerts[] = $this->sendAlert(
                    'warning',
                    'low_disk_space',
                    "Low disk space: {$diskMetrics['min_free_percent']}% free (threshold: {$this->thresholds['disk_space_percent']}%)",
                    [
                        'free_percent' => $diskMetrics['min_free_percent'],
                        'threshold' => $this->thresholds['disk_space_percent']
                    ]
                );
            }
        } catch (Exception $e) {
            error_log("AlertManager: Failed to check system metrics - " . $e->getMessage());
        }
        
        return [
            'success' => true,
            'alerts_sent' => count(array_filter($alerts, function($a) { return $a['success'] ?? false; })),
            'alerts_throttled' => count(array_filter($alerts, function($a) { return $a['throttled'] ?? false; })),
            'total_checks' => 3,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Send backup failure alert
     * Requirement 5.2.4: Backup failure alerts
     * 
     * @param array $backupData Backup failure data
     * @return array Send result
     */
    public function sendBackupFailureAlert($backupData)
    {
        return $this->sendAlert(
            'critical',
            'backup_failure',
            "Backup failed: {$backupData['error_message']}",
            [
                'backup_type' => $backupData['backup_type'] ?? 'unknown',
                'error_message' => $backupData['error_message'],
                'timestamp' => $backupData['timestamp'] ?? date('Y-m-d H:i:s'),
                'backup_path' => $backupData['backup_path'] ?? 'N/A'
            ]
        );
    }
    
    /**
     * Send security incident alert
     * Requirement 5.2.5: Security incident alerts
     * 
     * @param array $incidentData Security incident data
     * @return array Send result
     */
    public function sendSecurityIncidentAlert($incidentData)
    {
        return $this->sendAlert(
            'critical',
            'security_incident',
            "Security incident detected: {$incidentData['incident_type']}",
            [
                'incident_type' => $incidentData['incident_type'],
                'description' => $incidentData['description'] ?? '',
                'ip_address' => $incidentData['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_id' => $incidentData['user_id'] ?? $_SESSION['user_id'] ?? null,
                'url' => $incidentData['url'] ?? $_SERVER['REQUEST_URI'] ?? 'unknown',
                'timestamp' => $incidentData['timestamp'] ?? date('Y-m-d H:i:s')
            ]
        );
    }
    
    /**
     * Get alert history
     * 
     * @param int $limit Number of alerts to retrieve
     * @param string $type Filter by alert type (optional)
     * @return array Alert history
     */
    public function getAlertHistory($limit = 50, $type = null)
    {
        // This would require scanning cache keys or database
        // For now, return empty array
        // In production, implement proper alert history storage
        
        return [
            'alerts' => [],
            'total' => 0,
            'note' => 'Alert history requires database implementation'
        ];
    }
    
    /**
     * Configure alert recipients
     * 
     * @param string $type Alert type (critical, warning, info)
     * @param array $emails Array of email addresses
     * @return bool Success status
     */
    public function configureRecipients($type, $emails)
    {
        if (!in_array($type, ['critical', 'warning', 'info'])) {
            return false;
        }
        
        $validEmails = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            }
        }
        
        $this->recipients[$type] = $validEmails;
        
        // Store in cache
        $this->cache->set("alert_recipients_$type", $validEmails, 86400);
        
        return true;
    }
    
    /**
     * Get current configuration
     * 
     * @return array Configuration
     */
    public function getConfiguration()
    {
        return [
            'config' => $this->config,
            'recipients' => $this->recipients,
            'thresholds' => $this->thresholds,
            'email_config' => $this->emailConfig->getConfigSummary(true)
        ];
    }
    
    /**
     * Update configuration
     * 
     * @param array $newConfig New configuration values
     * @return array Updated configuration
     */
    public function updateConfiguration($newConfig)
    {
        $this->config = array_merge($this->config, $newConfig);
        
        // Store in cache
        $this->cache->set('alert_manager_config', $this->config, 86400);
        
        return $this->config;
    }
    
    /**
     * Update thresholds
     * 
     * @param array $newThresholds New threshold values
     * @return array Updated thresholds
     */
    public function updateThresholds($newThresholds)
    {
        $this->thresholds = array_merge($this->thresholds, $newThresholds);
        
        // Store in cache
        $this->cache->set('alert_manager_thresholds', $this->thresholds, 86400);
        
        return $this->thresholds;
    }
    
    /**
     * Test alert system
     * 
     * @param string $recipient Test recipient email
     * @return array Test results
     */
    public function testAlertSystem($recipient = null)
    {
        if ($recipient === null) {
            $recipient = $this->recipients['critical'][0] ?? null;
        }
        
        if ($recipient === null) {
            return [
                'success' => false,
                'message' => 'No recipient configured for testing'
            ];
        }
        
        $testAlert = [
            'type' => 'info',
            'category' => 'system_test',
            'message' => 'This is a test alert from the AlertManager system',
            'data' => [
                'test_time' => date('Y-m-d H:i:s'),
                'test_purpose' => 'Verify alert system functionality'
            ],
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown'
        ];
        
        $subject = $this->buildEmailSubject($testAlert);
        $htmlBody = $this->buildEmailBody($testAlert);
        $textBody = $this->buildEmailTextBody($testAlert);
        
        $sent = $this->sendEmail($recipient, $subject, $htmlBody, $textBody);
        
        return [
            'success' => $sent,
            'recipient' => $recipient,
            'message' => $sent ? 'Test alert sent successfully' : 'Failed to send test alert'
        ];
    }
}

