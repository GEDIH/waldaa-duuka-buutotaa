<?php
/**
 * BackupNotifier Class
 * 
 * Sends email notifications for backup events including success, failure, and health alerts.
 * Uses PHPMailer if available, falls back to PHP mail() function.
 * 
 * Requirements: 5.1, 5.2, 5.5
 */

class BackupNotifier {
    private $smtp_config;
    private $admin_email;
    private $from_email;
    private $from_name;
    private $use_phpmailer;
    private $mailer;
    
    /**
     * Constructor
     * 
     * @param array $smtp_config SMTP configuration array
     * @param string $admin_email Admin email address to send notifications to
     */
    public function __construct(array $smtp_config, string $admin_email) {
        $this->smtp_config = $smtp_config;
        $this->admin_email = $admin_email;
        $this->from_email = $smtp_config['from_email'] ?? 'backup@wdb-membership.local';
        $this->from_name = $smtp_config['from_name'] ?? 'WDB Backup System';
        
        // Check if PHPMailer is available
        $this->use_phpmailer = class_exists('PHPMailer\PHPMailer\PHPMailer');
        
        if ($this->use_phpmailer) {
            $this->initializePHPMailer();
        }
    }
    
    /**
     * Initialize PHPMailer instance
     */
    private function initializePHPMailer(): void {
        try {
            $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            if (!empty($this->smtp_config['smtp_enabled']) && $this->smtp_config['smtp_enabled']) {
                $this->mailer->isSMTP();
                $this->mailer->Host = $this->smtp_config['smtp_host'] ?? 'localhost';
                $this->mailer->SMTPAuth = $this->smtp_config['smtp_auth'] ?? false;
                $this->mailer->Username = $this->smtp_config['smtp_user'] ?? '';
                $this->mailer->Password = $this->smtp_config['smtp_pass'] ?? '';
                $this->mailer->SMTPSecure = $this->smtp_config['smtp_secure'] ?? 'tls';
                $this->mailer->Port = $this->smtp_config['smtp_port'] ?? 587;
            } else {
                $this->mailer->isMail();
            }
            
            // Set from address
            $this->mailer->setFrom($this->from_email, $this->from_name);
            $this->mailer->isHTML(true);
            
        } catch (Exception $e) {
            // If PHPMailer initialization fails, fall back to mail()
            $this->use_phpmailer = false;
        }
    }
    
    /**
     * Send backup success notification
     * 
     * Requirements: 5.1
     * 
     * @param array $backup_info Backup information array
     * @return bool True if email sent successfully
     */
    public function notifyBackupSuccess(array $backup_info): bool {
        $subject = 'Backup Completed Successfully - WDB Membership System';
        
        // Load email template
        $html_body = $this->loadTemplate('backup-success', $backup_info);
        $text_body = $this->loadTemplate('backup-success-text', $backup_info);
        
        return $this->sendEmail($this->admin_email, $subject, $html_body, $text_body);
    }
    
    /**
     * Send backup failure notification
     * 
     * Requirements: 5.2
     * 
     * @param string $error_message Error message details
     * @return bool True if email sent successfully
     */
    public function notifyBackupFailure(string $error_message): bool {
        $subject = 'ALERT: Backup Failed - WDB Membership System';
        
        $data = [
            'error_message' => $error_message,
            'timestamp' => date('Y-m-d H:i:s'),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost'
        ];
        
        // Load email template
        $html_body = $this->loadTemplate('backup-failure', $data);
        $text_body = $this->loadTemplate('backup-failure-text', $data);
        
        return $this->sendEmail($this->admin_email, $subject, $html_body, $text_body);
    }
    
    /**
     * Send backup health alert notification
     * 
     * Requirements: 5.5
     * 
     * @param int $days_since_backup Days since last successful backup
     * @return bool True if email sent successfully
     */
    public function notifyHealthAlert(int $days_since_backup): bool {
        $subject = 'WARNING: Backup Health Alert - WDB Membership System';
        
        $data = [
            'days_since_backup' => $days_since_backup,
            'timestamp' => date('Y-m-d H:i:s'),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'health_status' => $this->getHealthStatus($days_since_backup)
        ];
        
        // Load email template
        $html_body = $this->loadTemplate('backup-health-alert', $data);
        $text_body = $this->loadTemplate('backup-health-alert-text', $data);
        
        return $this->sendEmail($this->admin_email, $subject, $html_body, $text_body);
    }
    
    /**
     * Get health status based on days since backup
     * 
     * @param int $days_since_backup Days since last backup
     * @return string Health status (green, yellow, red)
     */
    private function getHealthStatus(int $days_since_backup): string {
        if ($days_since_backup <= 1) {
            return 'green';
        } elseif ($days_since_backup <= 2) {
            return 'yellow';
        } else {
            return 'red';
        }
    }
    
    /**
     * Load email template
     * 
     * @param string $template_name Template name (without extension)
     * @param array $data Data to populate template
     * @return string Rendered template content
     */
    private function loadTemplate(string $template_name, array $data): string {
        $template_path = __DIR__ . '/../email-templates/backup-notifications/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            // Return basic template if file not found
            return $this->getDefaultTemplate($template_name, $data);
        }
        
        // Extract data variables for template
        extract($data);
        
        // Capture template output
        ob_start();
        include $template_path;
        $content = ob_get_clean();
        
        return $content;
    }
    
    /**
     * Get default template if template file not found
     * 
     * @param string $template_name Template name
     * @param array $data Template data
     * @return string Default template content
     */
    private function getDefaultTemplate(string $template_name, array $data): string {
        switch ($template_name) {
            case 'backup-success':
                return $this->getDefaultSuccessTemplate($data);
            
            case 'backup-success-text':
                return $this->getDefaultSuccessTextTemplate($data);
            
            case 'backup-failure':
                return $this->getDefaultFailureTemplate($data);
            
            case 'backup-failure-text':
                return $this->getDefaultFailureTextTemplate($data);
            
            case 'backup-health-alert':
                return $this->getDefaultHealthAlertTemplate($data);
            
            case 'backup-health-alert-text':
                return $this->getDefaultHealthAlertTextTemplate($data);
            
            default:
                return 'Email template not found.';
        }
    }
    
    /**
     * Get default success HTML template
     */
    private function getDefaultSuccessTemplate(array $data): string {
        $backup_date = $data['created_at'] ?? date('Y-m-d H:i:s');
        $backup_size = $data['size_human'] ?? 'Unknown';
        $next_backup = $data['next_backup'] ?? 'Not scheduled';
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                <h2 style='color: #28a745;'>✓ Backup Completed Successfully</h2>
                <p>The database backup has been completed successfully.</p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Backup Date:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'>$backup_date</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Backup Size:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'>$backup_size</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Next Scheduled Backup:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'>$next_backup</td>
                    </tr>
                </table>
                <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                    This is an automated notification from the WDB Membership Backup System.
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get default success text template
     */
    private function getDefaultSuccessTextTemplate(array $data): string {
        $backup_date = $data['created_at'] ?? date('Y-m-d H:i:s');
        $backup_size = $data['size_human'] ?? 'Unknown';
        $next_backup = $data['next_backup'] ?? 'Not scheduled';
        
        return "
BACKUP COMPLETED SUCCESSFULLY

The database backup has been completed successfully.

Backup Date: $backup_date
Backup Size: $backup_size
Next Scheduled Backup: $next_backup

---
This is an automated notification from the WDB Membership Backup System.
        ";
    }
    
    /**
     * Get default failure HTML template
     */
    private function getDefaultFailureTemplate(array $data): string {
        $error_message = $data['error_message'] ?? 'Unknown error';
        $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #dc3545; border-radius: 5px; background-color: #fff5f5;'>
                <h2 style='color: #dc3545;'>✗ Backup Failed</h2>
                <p><strong>The scheduled backup has failed.</strong></p>
                <div style='background-color: #fff; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>
                    <p><strong>Error Details:</strong></p>
                    <p style='font-family: monospace; color: #721c24;'>$error_message</p>
                </div>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Timestamp:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'>$timestamp</td>
                    </tr>
                </table>
                <div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #856404;'>Suggested Actions:</h3>
                    <ul style='margin: 10px 0;'>
                        <li>Check database connectivity</li>
                        <li>Verify backup storage has sufficient space</li>
                        <li>Review backup system logs</li>
                        <li>Attempt a manual backup from the admin panel</li>
                    </ul>
                </div>
                <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                    This is an automated alert from the WDB Membership Backup System.
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get default failure text template
     */
    private function getDefaultFailureTextTemplate(array $data): string {
        $error_message = $data['error_message'] ?? 'Unknown error';
        $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
        
        return "
BACKUP FAILED - ACTION REQUIRED

The scheduled backup has failed.

Error Details:
$error_message

Timestamp: $timestamp

Suggested Actions:
- Check database connectivity
- Verify backup storage has sufficient space
- Review backup system logs
- Attempt a manual backup from the admin panel

---
This is an automated alert from the WDB Membership Backup System.
        ";
    }
    
    /**
     * Get default health alert HTML template
     */
    private function getDefaultHealthAlertTemplate(array $data): string {
        $days_since_backup = $data['days_since_backup'] ?? 0;
        $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
        $health_status = $data['health_status'] ?? 'red';
        
        $status_color = [
            'green' => '#28a745',
            'yellow' => '#ffc107',
            'red' => '#dc3545'
        ][$health_status] ?? '#dc3545';
        
        $status_text = [
            'green' => 'Good',
            'yellow' => 'Warning',
            'red' => 'Critical'
        ][$health_status] ?? 'Critical';
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid $status_color; border-radius: 5px; background-color: #fff5f5;'>
                <h2 style='color: $status_color;'>⚠ Backup Health Alert</h2>
                <p><strong>The backup system requires attention.</strong></p>
                <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Health Status:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #eee; color: $status_color;'><strong>$status_text</strong></td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Days Since Last Backup:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'>$days_since_backup days</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Alert Time:</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #eee;'>$timestamp</td>
                    </tr>
                </table>
                <div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #856404;'>Action Required:</h3>
                    <ul style='margin: 10px 0;'>
                        <li>Review backup schedule configuration</li>
                        <li>Check if scheduled backups are running</li>
                        <li>Verify backup system is operational</li>
                        <li>Create a manual backup immediately</li>
                    </ul>
                </div>
                <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                    This is an automated alert from the WDB Membership Backup System.
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get default health alert text template
     */
    private function getDefaultHealthAlertTextTemplate(array $data): string {
        $days_since_backup = $data['days_since_backup'] ?? 0;
        $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
        $health_status = $data['health_status'] ?? 'red';
        
        $status_text = [
            'green' => 'Good',
            'yellow' => 'Warning',
            'red' => 'Critical'
        ][$health_status] ?? 'Critical';
        
        return "
BACKUP HEALTH ALERT - ACTION REQUIRED

The backup system requires attention.

Health Status: $status_text
Days Since Last Backup: $days_since_backup days
Alert Time: $timestamp

Action Required:
- Review backup schedule configuration
- Check if scheduled backups are running
- Verify backup system is operational
- Create a manual backup immediately

---
This is an automated alert from the WDB Membership Backup System.
        ";
    }
    
    /**
     * Send email using PHPMailer or mail() function
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $html_body HTML email body
     * @param string $text_body Plain text email body
     * @return bool True if email sent successfully
     */
    private function sendEmail(string $to, string $subject, string $html_body, string $text_body): bool {
        try {
            if ($this->use_phpmailer && $this->mailer) {
                return $this->sendWithPHPMailer($to, $subject, $html_body, $text_body);
            } else {
                return $this->sendWithMailFunction($to, $subject, $html_body, $text_body);
            }
        } catch (Exception $e) {
            error_log("BackupNotifier: Failed to send email - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHPMailer
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $html_body HTML email body
     * @param string $text_body Plain text email body
     * @return bool True if email sent successfully
     */
    private function sendWithPHPMailer(string $to, string $subject, string $html_body, string $text_body): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $html_body;
            $this->mailer->AltBody = $text_body;
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHP mail() function
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $html_body HTML email body
     * @param string $text_body Plain text email body
     * @return bool True if email sent successfully
     */
    private function sendWithMailFunction(string $to, string $subject, string $html_body, string $text_body): bool {
        // Create email headers
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "From: {$this->from_name} <{$this->from_email}>";
        $headers[] = "Reply-To: {$this->from_email}";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        
        $headers_string = implode("\r\n", $headers);
        
        // Send email
        return mail($to, $subject, $html_body, $headers_string);
    }
    
    /**
     * Test email configuration
     * 
     * @return array Test results
     */
    public function testEmailConfiguration(): array {
        $results = [
            'success' => false,
            'method' => $this->use_phpmailer ? 'PHPMailer' : 'PHP mail()',
            'from_email' => $this->from_email,
            'admin_email' => $this->admin_email,
            'errors' => []
        ];
        
        if ($this->use_phpmailer) {
            if (!$this->mailer) {
                $results['errors'][] = 'PHPMailer not initialized';
                return $results;
            }
            
            $results['smtp_enabled'] = !empty($this->smtp_config['smtp_enabled']);
            $results['smtp_host'] = $this->smtp_config['smtp_host'] ?? 'Not configured';
        }
        
        // Try to send a test email
        $test_subject = 'Test Email - WDB Backup System';
        $test_body = '<p>This is a test email from the WDB Backup System.</p>';
        $test_text = 'This is a test email from the WDB Backup System.';
        
        $sent = $this->sendEmail($this->admin_email, $test_subject, $test_body, $test_text);
        
        if ($sent) {
            $results['success'] = true;
            $results['message'] = 'Test email sent successfully';
        } else {
            $results['errors'][] = 'Failed to send test email';
        }
        
        return $results;
    }
}
