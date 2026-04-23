<?php
/**
 * Configuration file for Waldaa Duuka Bu'ootaa Photo Upload System
 */

// Security Configuration
define('ADMIN_PASSWORD_HASH', password_hash('WDB2024Admin!', PASSWORD_DEFAULT));
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File Upload Configuration
define('UPLOAD_BASE_DIR', __DIR__ . '/images/');
define('GALLERY_DIR', UPLOAD_BASE_DIR . 'gallery/');
define('THUMBNAIL_DIR', GALLERY_DIR . 'thumbnails/');
define('TEMP_DIR', UPLOAD_BASE_DIR . 'temp/');

// File Size Limits (in bytes)
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_TOTAL_SIZE', 100 * 1024 * 1024); // 100MB total
define('MAX_FILES_PER_UPLOAD', 20);

// Allowed File Types
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png', 
    'image/gif',
    'image/webp'
]);

define('ALLOWED_EXTENSIONS', [
    'jpg', 'jpeg', 'png', 'gif', 'webp'
]);

// Image Processing
define('THUMBNAIL_WIDTH', 300);
define('THUMBNAIL_HEIGHT', 300);
define('THUMBNAIL_QUALITY', 85);

// Database Configuration
define('DB_TYPE', 'sqlite'); // sqlite or mysql
define('DB_FILE', __DIR__ . '/data/gallery.db');

// MySQL Configuration (if using MySQL)
define('DB_HOST', 'localhost');
define('DB_NAME', 'waldaa_gallery');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// Security Headers
$security_headers = [
    'X-Content-Type-Options: nosniff',
    'X-Frame-Options: DENY',
    'X-XSS-Protection: 1; mode=block',
    'Referrer-Policy: strict-origin-when-cross-origin',
    'Content-Security-Policy: default-src \'self\'; img-src \'self\' data:; style-src \'self\' \'unsafe-inline\'; script-src \'self\' \'unsafe-inline\''
];

// Apply security headers
foreach ($security_headers as $header) {
    header($header);
}

// Error Reporting (disable in production)
if (defined('DEVELOPMENT') && DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// Create necessary directories
$directories = [
    GALLERY_DIR,
    THUMBNAIL_DIR,
    TEMP_DIR,
    __DIR__ . '/data',
    __DIR__ . '/logs'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Create .htaccess for security
$htaccess_content = "
# Waldaa Duuka Bu'ootaa Security Rules
Options -Indexes
Options -ExecCGI

# Prevent access to sensitive files
<Files ~ \"^\.ht\">
    Order allow,deny
    Deny from all
</Files>

<Files ~ \"\.db$\">
    Order allow,deny
    Deny from all
</Files>

<Files ~ \"\.log$\">
    Order allow,deny
    Deny from all
</Files>

# Only allow image files in gallery directory
<FilesMatch \"\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">
    Order allow,deny
    Deny from all
</FilesMatch>

# Set proper MIME types for images
<IfModule mod_mime.c>
    AddType image/jpeg .jpg .jpeg
    AddType image/png .png
    AddType image/gif .gif
    AddType image/webp .webp
</IfModule>

# Enable compression for images
<IfModule mod_deflate.c>
    <FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">
        SetOutputFilter DEFLATE
    </FilesMatch>
</IfModule>

# Set cache headers for images
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg \"access plus 1 month\"
    ExpiresByType image/png \"access plus 1 month\"
    ExpiresByType image/gif \"access plus 1 month\"
    ExpiresByType image/webp \"access plus 1 month\"
</IfModule>
";

file_put_contents(GALLERY_DIR . '.htaccess', $htaccess_content);

/**
 * Utility Functions
 */

/**
 * Sanitize filename
 */
function sanitizeFilename($filename) {
    // Remove any path information
    $filename = basename($filename);
    
    // Remove special characters
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Limit length
    if (strlen($filename) > 100) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 90);
        $filename = $name . '.' . $extension;
    }
    
    return $filename;
}

/**
 * Generate secure random filename
 */
function generateSecureFilename($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $timestamp = time();
    $random = bin2hex(random_bytes(16));
    return "wdb_{$timestamp}_{$random}.{$extension}";
}

/**
 * Validate file type by content
 */
function validateFileContent($filePath) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    return in_array($mimeType, ALLOWED_MIME_TYPES);
}

/**
 * Check if file is actually an image
 */
function isValidImage($filePath) {
    $imageInfo = @getimagesize($filePath);
    return $imageInfo !== false;
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '') {
    $logFile = __DIR__ . '/logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $logEntry = "[{$timestamp}] IP: {$ip} | Event: {$event} | Details: {$details} | User-Agent: {$userAgent}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Rate limiting for uploads
 */
function checkRateLimit($ip, $maxUploads = 10, $timeWindow = 3600) {
    $rateLimitFile = __DIR__ . '/data/rate_limit.json';
    
    if (!file_exists($rateLimitFile)) {
        file_put_contents($rateLimitFile, json_encode([]));
    }
    
    $rateLimits = json_decode(file_get_contents($rateLimitFile), true);
    $currentTime = time();
    
    // Clean old entries
    foreach ($rateLimits as $checkIp => $data) {
        if ($currentTime - $data['first_request'] > $timeWindow) {
            unset($rateLimits[$checkIp]);
        }
    }
    
    // Check current IP
    if (!isset($rateLimits[$ip])) {
        $rateLimits[$ip] = [
            'count' => 1,
            'first_request' => $currentTime
        ];
    } else {
        $rateLimits[$ip]['count']++;
    }
    
    // Save updated limits
    file_put_contents($rateLimitFile, json_encode($rateLimits));
    
    return $rateLimits[$ip]['count'] <= $maxUploads;
}

/**
 * Clean up old temporary files
 */
function cleanupTempFiles($maxAge = 3600) {
    $tempDir = TEMP_DIR;
    $files = glob($tempDir . '*');
    $currentTime = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($currentTime - filemtime($file)) > $maxAge) {
            unlink($file);
        }
    }
}

// Auto-cleanup on script execution
register_shutdown_function('cleanupTempFiles');

/**
 * Database connection
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            if (DB_TYPE === 'sqlite') {
                $pdo = new PDO('sqlite:' . DB_FILE);
            } else {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            }
            
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create tables if they don't exist
            initializeTables($pdo);
            
        } catch (PDOException $e) {
            logSecurityEvent('database_connection_failed', $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    return $pdo;
}

/**
 * Initialize database tables
 */
function initializeTables($pdo) {
    $tables = [
        'gallery' => "
            CREATE TABLE IF NOT EXISTS gallery (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                title VARCHAR(255),
                description TEXT,
                file_size INTEGER,
                mime_type VARCHAR(100),
                width INTEGER,
                height INTEGER,
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT 1,
                sort_order INTEGER DEFAULT 0
            )
        ",
        'upload_log' => "
            CREATE TABLE IF NOT EXISTS upload_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address VARCHAR(45),
                filename VARCHAR(255),
                file_size INTEGER,
                status VARCHAR(50),
                error_message TEXT,
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        "
    ];
    
    foreach ($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            logSecurityEvent('table_creation_failed', "Table: {$name}, Error: " . $e->getMessage());
        }
    }
}

?>