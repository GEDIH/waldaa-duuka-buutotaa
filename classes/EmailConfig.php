<?php
/**
 * EmailConfig Class
 * 
 * Manages email configuration for production deployment.
 * Loads SMTP settings from environment variables and provides connection testing.
 * 
 * Requirements: 2.3.1 - SMTP settings configured from environment
 * 
 * @package WDB\Config
 * @version 1.0.0
 */

class EmailConfig {
    private $config;
    private $mailer;
    private $use_phpmailer;
    
    /**
     * Constructor
     * Loads email configuration from environment variables
     */
    public function __construct() {
        $this->loadConfiguration();
        $this->use_phpmailer = class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    /**
     * Load email configuration from environment variables
     * 
     * Requirements: 2.3.1
     * 
     * @return void
     */
    private function loadConfiguration(): void {
        // Load environment variables if not already loaded
        if (!getenv('MAIL_HOST')) {
            $this->loadEnvironmentFile();
        }
        
        $this->config = [
            'driver' => getenv('MAIL_DRIVER') ?: 'smtp',
            'host' => getenv('MAIL_HOST') ?: 'localhost',
            'port' => (int)(getenv('MAIL_PORT') ?: 587),
            'username' => getenv('MAIL_USERNAME') ?: '',
            'password' => getenv('MAIL_PASSWORD') ?: '',
            'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
            'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@localhost',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'WDB System',
            'smtp_auth' => !empty(getenv('MAIL_USERNAME')),
            'smtp_enabled' => (getenv('MAIL_DRIVER') === 'smtp')
        ];
        
        // Validate required configuration
        $this->validateConfiguration();
    }
    
    /**
     * Load environment file if it exists
     * 
     * @return void
     */
    private function loadEnvironmentFile(): void {
        $env_file = __DIR__ . '/../.env';
        
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse key=value pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                    
                    // Set environment variable
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
    
    /**
     * Validate email configuration
     * 
     * @throws Exception If required configuration is missing
     * @return void
     */
    private function validateConfiguration(): void {
        $required_fields = ['host', 'port', 'from_address'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($this->config[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception(
                'Email configuration incomplete. Missing fields: ' . 
                implode(', ', $missing_fields)
            );
        }
        
        // Validate email format
        if (!filter_var($this->config['from_address'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid from_address email format: ' . $this->config['from_address']);
        }
        
        // Validate port number
        if ($this->config['port'] < 1 || $this->config['port'] > 65535) {
            throw new Exception('Invalid port number: ' . $this->config['port']);
        }
        
        // Validate encryption type
        $valid_encryption = ['tls', 'ssl', 'none', ''];
        if (!in_array(strtolower($this->config['encryption']), $valid_encryption)) {
            throw new Exception('Invalid encryption type: ' . $this->config['encryption']);
        }
    }
    
    /**
     * Get email configuration
     * 
     * @return array Email configuration array
     */
    public function getConfig(): array {
        return $this->config;
    }
    
    /**
     * Get specific configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Test SMTP connection
     * 
     * Requirements: 2.3.1 - Implement connection testing
     * 
     * @return array Test results with success status and details
     */
    public function testConnection(): array {
        $results = [
            'success' => false,
            'method' => $this->use_phpmailer ? 'PHPMailer' : 'PHP mail()',
            'config' => [
                'driver' => $this->config['driver'],
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'encryption' => $this->config['encryption'],
                'from_address' => $this->config['from_address'],
                'smtp_auth' => $this->config['smtp_auth']
            ],
            'errors' => [],
            'warnings' => []
        ];
        
        // Check if PHPMailer is available
        if (!$this->use_phpmailer) {
            $results['warnings'][] = 'PHPMailer not available, using PHP mail() function';
            $results['warnings'][] = 'SMTP configuration will not be used with mail() function';
        }
        
        // Test connection based on driver
        if ($this->config['smtp_enabled'] && $this->use_phpmailer) {
            return $this->testSMTPConnection($results);
        } else {
            return $this->testMailFunction($results);
        }
    }
    
    /**
     * Test SMTP connection using PHPMailer
     * 
     * @param array $results Initial results array
     * @return array Test results
     */
    private function testSMTPConnection(array $results): array {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mailer->isSMTP();
            $mailer->Host = $this->config['host'];
            $mailer->SMTPAuth = $this->config['smtp_auth'];
            $mailer->Username = $this->config['username'];
            $mailer->Password = $this->config['password'];
            $mailer->Port = $this->config['port'];
            
            // Set encryption
            if (!empty($this->config['encryption']) && $this->config['encryption'] !== 'none') {
                $mailer->SMTPSecure = $this->config['encryption'];
            }
            
            // Enable debug output for testing
            $mailer->SMTPDebug = 0; // Set to 2 for verbose debugging
            $mailer->Debugoutput = function($str, $level) use (&$results) {
                $results['debug'][] = trim($str);
            };
            
            // Test connection by attempting to connect
            if (!$mailer->smtpConnect()) {
                $results['success'] = false;
                $results['errors'][] = 'Failed to connect to SMTP server';
                return $results;
            }
            
            // Close connection
            $mailer->smtpClose();
            
            $results['success'] = true;
            $results['message'] = 'SMTP connection successful';
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = 'SMTP connection failed: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Test PHP mail() function
     * 
     * @param array $results Initial results array
     * @return array Test results
     */
    private function testMailFunction(array $results): array {
        // Check if mail() function is available
        if (!function_exists('mail')) {
            $results['success'] = false;
            $results['errors'][] = 'PHP mail() function is not available';
            return $results;
        }
        
        // Check if mail can be sent (basic check)
        $results['success'] = true;
        $results['message'] = 'PHP mail() function is available';
        $results['warnings'][] = 'Cannot test actual mail delivery without sending an email';
        
        return $results;
    }
    
    /**
     * Send a test email
     * 
     * @param string $to Recipient email address
     * @param string $subject Optional custom subject
     * @return array Send results with success status
     */
    public function sendTestEmail(string $to, string $subject = null): array {
        $results = [
            'success' => false,
            'to' => $to,
            'errors' => []
        ];
        
        // Validate recipient email
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $results['errors'][] = 'Invalid recipient email address';
            return $results;
        }
        
        $subject = $subject ?: 'Test Email - WDB System';
        $html_body = $this->getTestEmailBody();
        $text_body = $this->getTestEmailTextBody();
        
        try {
            if ($this->config['smtp_enabled'] && $this->use_phpmailer) {
                $sent = $this->sendWithPHPMailer($to, $subject, $html_body, $text_body);
            } else {
                $sent = $this->sendWithMailFunction($to, $subject, $html_body, $text_body);
            }
            
            if ($sent) {
                $results['success'] = true;
                $results['message'] = 'Test email sent successfully';
            } else {
                $results['errors'][] = 'Failed to send test email';
            }
            
        } catch (Exception $e) {
            $results['errors'][] = 'Error sending test email: ' . $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Send email using PHPMailer
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $html_body HTML body
     * @param string $text_body Plain text body
     * @return bool Success status
     */
    private function sendWithPHPMailer(string $to, string $subject, string $html_body, string $text_body): bool {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mailer->isSMTP();
            $mailer->Host = $this->config['host'];
            $mailer->SMTPAuth = $this->config['smtp_auth'];
            $mailer->Username = $this->config['username'];
            $mailer->Password = $this->config['password'];
            $mailer->Port = $this->config['port'];
            
            if (!empty($this->config['encryption']) && $this->config['encryption'] !== 'none') {
                $mailer->SMTPSecure = $this->config['encryption'];
            }
            
            // Recipients
            $mailer->setFrom($this->config['from_address'], $this->config['from_name']);
            $mailer->addAddress($to);
            
            // Content
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $html_body;
            $mailer->AltBody = $text_body;
            
            return $mailer->send();
            
        } catch (Exception $e) {
            error_log('EmailConfig: PHPMailer error - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHP mail() function
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $html_body HTML body
     * @param string $text_body Plain text body (unused with mail())
     * @return bool Success status
     */
    private function sendWithMailFunction(string $to, string $subject, string $html_body, string $text_body): bool {
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "From: {$this->config['from_name']} <{$this->config['from_address']}>";
        $headers[] = "Reply-To: {$this->config['from_address']}";
        $headers[] = "X-Mailer: PHP/" . phpversion();
        
        $headers_string = implode("\r\n", $headers);
        
        return mail($to, $subject, $html_body, $headers_string);
    }
    
    /**
     * Get test email HTML body
     * 
     * @return string HTML content
     */
    private function getTestEmailBody(): string {
        $timestamp = date('Y-m-d H:i:s');
        $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .header { background-color: #007bff; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 15px; font-size: 12px; color: #666; text-align: center; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                .success { color: #28a745; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin: 0;'>✓ Email Configuration Test</h2>
                </div>
                <div class='content'>
                    <p class='success'>Email system is working correctly!</p>
                    <p>This is a test email from the WDB Management System to verify that email configuration is working properly.</p>
                    
                    <table>
                        <tr>
                            <td><strong>Test Time:</strong></td>
                            <td>$timestamp</td>
                        </tr>
                        <tr>
                            <td><strong>Server:</strong></td>
                            <td>$server_name</td>
                        </tr>
                        <tr>
                            <td><strong>From Address:</strong></td>
                            <td>{$this->config['from_address']}</td>
                        </tr>
                        <tr>
                            <td><strong>SMTP Host:</strong></td>
                            <td>{$this->config['host']}</td>
                        </tr>
                        <tr>
                            <td><strong>SMTP Port:</strong></td>
                            <td>{$this->config['port']}</td>
                        </tr>
                        <tr>
                            <td><strong>Encryption:</strong></td>
                            <td>{$this->config['encryption']}</td>
                        </tr>
                    </table>
                    
                    <p>If you received this email, your email configuration is working correctly and you can proceed with production deployment.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated test email from the WDB Management System.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get test email plain text body
     * 
     * @return string Plain text content
     */
    private function getTestEmailTextBody(): string {
        $timestamp = date('Y-m-d H:i:s');
        $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        return "
EMAIL CONFIGURATION TEST

Email system is working correctly!

This is a test email from the WDB Management System to verify that email configuration is working properly.

Test Details:
- Test Time: $timestamp
- Server: $server_name
- From Address: {$this->config['from_address']}
- SMTP Host: {$this->config['host']}
- SMTP Port: {$this->config['port']}
- Encryption: {$this->config['encryption']}

If you received this email, your email configuration is working correctly and you can proceed with production deployment.

---
This is an automated test email from the WDB Management System.
Please do not reply to this email.
        ";
    }
    
    /**
     * Get configuration summary for display
     * 
     * @param bool $hide_sensitive Hide sensitive information like passwords
     * @return array Configuration summary
     */
    public function getConfigSummary(bool $hide_sensitive = true): array {
        $summary = [
            'driver' => $this->config['driver'],
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'encryption' => $this->config['encryption'],
            'from_address' => $this->config['from_address'],
            'from_name' => $this->config['from_name'],
            'smtp_auth' => $this->config['smtp_auth'],
            'smtp_enabled' => $this->config['smtp_enabled'],
            'phpmailer_available' => $this->use_phpmailer
        ];
        
        if (!$hide_sensitive) {
            $summary['username'] = $this->config['username'];
            $summary['password'] = $this->config['password'];
        } else {
            $summary['username'] = !empty($this->config['username']) ? '***configured***' : 'not set';
            $summary['password'] = !empty($this->config['password']) ? '***configured***' : 'not set';
        }
        
        return $summary;
    }
    
    /**
     * Check if email system is properly configured
     * 
     * @return array Status with any warnings or errors
     */
    public function checkConfiguration(): array {
        $status = [
            'configured' => true,
            'warnings' => [],
            'errors' => []
        ];
        
        // Check if PHPMailer is available for SMTP
        if ($this->config['smtp_enabled'] && !$this->use_phpmailer) {
            $status['warnings'][] = 'SMTP enabled but PHPMailer not available';
            $status['warnings'][] = 'Install PHPMailer for SMTP support: composer require phpmailer/phpmailer';
        }
        
        // Check if authentication is configured for SMTP
        if ($this->config['smtp_enabled'] && $this->config['smtp_auth']) {
            if (empty($this->config['username']) || empty($this->config['password'])) {
                $status['errors'][] = 'SMTP authentication enabled but username or password not configured';
                $status['configured'] = false;
            }
        }
        
        // Check encryption settings
        if ($this->config['smtp_enabled'] && empty($this->config['encryption'])) {
            $status['warnings'][] = 'SMTP encryption not configured - emails will be sent unencrypted';
        }
        
        // Check from address
        if (strpos($this->config['from_address'], 'localhost') !== false) {
            $status['warnings'][] = 'From address contains "localhost" - update for production';
        }
        
        return $status;
    }
}
