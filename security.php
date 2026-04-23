<?php
/**
 * Security Module for Waldaa Duuka Bu'ootaa Photo Upload System
 * Comprehensive security measures and validation
 */

require_once 'config.php';

class SecurityManager {
    
    private static $instance = null;
    private $loginAttempts = [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validate uploaded file for security threats
     */
    public function validateUploadedFile($file) {
        $errors = [];
        
        // Basic file validation
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'File upload validation failed';
            return $errors;
        }
        
        // File size validation
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds maximum allowed size';
        }
        
        if ($file['size'] <= 0) {
            $errors[] = 'File is empty';
        }
        
        // MIME type validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($detectedMimeType, ALLOWED_MIME_TYPES)) {
            $errors[] = 'Invalid file type detected: ' . $detectedMimeType;
        }
        
        // Extension validation
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            $errors[] = 'Invalid file extension: ' . $extension;
        }
        
        // File signature validation
        $signatureErrors = $this->validateFileSignature($file['tmp_name'], $extension);
        $errors = array_merge($errors, $signatureErrors);
        
        // Image validation
        if (!$this->isValidImage($file['tmp_name'])) {
            $errors[] = 'File is not a valid image';
        }
        
        // Malware scanning (basic)
        $malwareErrors = $this->scanForMalware($file['tmp_name']);
        $errors = array_merge($errors, $malwareErrors);
        
        // Filename validation
        $filenameErrors = $this->validateFilename($file['name']);
        $errors = array_merge($errors, $filenameErrors);
        
        return $errors;
    }
    
    /**
     * Validate file signature (magic bytes)
     */
    private function validateFileSignature($filePath, $extension) {
        $errors = [];
        
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            $errors[] = 'Cannot read file for signature validation';
            return $errors;
        }
        
        $signature = fread($handle, 12);
        fclose($handle);
        
        $validSignatures = [
            'jpg' => ["\xFF\xD8\xFF"],
            'jpeg' => ["\xFF\xD8\xFF"],
            'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
            'gif' => ["GIF87a", "GIF89a"],
            'webp' => ["RIFF"]
        ];
        
        if (!isset($validSignatures[$extension])) {
            $errors[] = 'Unknown file extension for signature validation';
            return $errors;
        }
        
        $isValidSignature = false;
        foreach ($validSignatures[$extension] as $validSig) {
            if (strpos($signature, $validSig) === 0) {
                $isValidSignature = true;
                break;
            }
        }
        
        if (!$isValidSignature) {
            $errors[] = 'File signature does not match extension';
            logSecurityEvent('invalid_file_signature', "Extension: {$extension}, Signature: " . bin2hex(substr($signature, 0, 8)));
        }
        
        return $errors;
    }
    
    /**
     * Basic malware scanning
     */
    private function scanForMalware($filePath) {
        $errors = [];
        
        // Read file content for suspicious patterns
        $content = file_get_contents($filePath, false, null, 0, 8192); // Read first 8KB
        
        // Suspicious patterns that shouldn't be in image files
        $suspiciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/shell_exec/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru/i',
            '/file_get_contents/i',
            '/file_put_contents/i',
            '/fopen/i',
            '/fwrite/i',
            '/curl_exec/i',
            '/wget/i',
            '/\$_GET/i',
            '/\$_POST/i',
            '/\$_REQUEST/i',
            '/\$_SERVER/i'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = 'Suspicious content detected in file';
                logSecurityEvent('malware_detected', "Pattern: {$pattern}");
                break;
            }
        }
        
        // Check for embedded executables
        $executableSignatures = [
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xFE\xED\xFA\xCE", // Mach-O executable
            "\xFE\xED\xFA\xCF", // Mach-O executable
        ];
        
        foreach ($executableSignatures as $sig) {
            if (strpos($content, $sig) !== false) {
                $errors[] = 'Executable code detected in file';
                logSecurityEvent('executable_detected', 'Executable signature found');
                break;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate filename for security
     */
    private function validateFilename($filename) {
        $errors = [];
        
        // Check for path traversal attempts
        if (strpos($filename, '..') !== false) {
            $errors[] = 'Path traversal attempt detected in filename';
            logSecurityEvent('path_traversal_attempt', $filename);
        }
        
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            $errors[] = 'Null byte detected in filename';
            logSecurityEvent('null_byte_attack', $filename);
        }
        
        // Check for suspicious characters
        if (preg_match('/[<>:"|?*]/', $filename)) {
            $errors[] = 'Invalid characters in filename';
        }
        
        // Check filename length
        if (strlen($filename) > 255) {
            $errors[] = 'Filename too long';
        }
        
        if (strlen($filename) < 1) {
            $errors[] = 'Filename is empty';
        }
        
        return $errors;
    }
    
    /**
     * Validate image using GD library
     */
    private function isValidImage($filePath) {
        $imageInfo = @getimagesize($filePath);
        
        if ($imageInfo === false) {
            return false;
        }
        
        // Check if dimensions are reasonable
        if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
            logSecurityEvent('suspicious_image_dimensions', "Width: {$imageInfo[0]}, Height: {$imageInfo[1]}");
            return false;
        }
        
        if ($imageInfo[0] < 1 || $imageInfo[1] < 1) {
            return false;
        }
        
        // Try to create image resource to verify it's a valid image
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = @imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $image = @imagecreatefrompng($filePath);
                break;
            case IMAGETYPE_GIF:
                $image = @imagecreatefromgif($filePath);
                break;
            case IMAGETYPE_WEBP:
                $image = @imagecreatefromwebp($filePath);
                break;
            default:
                return false;
        }
        
        if ($image === false) {
            return false;
        }
        
        imagedestroy($image);
        return true;
    }
    
    /**
     * Rate limiting for uploads
     */
    public function checkRateLimit($identifier = null) {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        $rateLimitFile = __DIR__ . '/data/rate_limits.json';
        
        if (!file_exists($rateLimitFile)) {
            file_put_contents($rateLimitFile, json_encode([]));
        }
        
        $rateLimits = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        $currentTime = time();
        $timeWindow = 3600; // 1 hour
        $maxUploads = 50; // Max uploads per hour
        
        // Clean old entries
        foreach ($rateLimits as $id => $data) {
            if ($currentTime - $data['first_request'] > $timeWindow) {
                unset($rateLimits[$id]);
            }
        }
        
        // Check current identifier
        if (!isset($rateLimits[$identifier])) {
            $rateLimits[$identifier] = [
                'count' => 1,
                'first_request' => $currentTime,
                'last_request' => $currentTime
            ];
        } else {
            $rateLimits[$identifier]['count']++;
            $rateLimits[$identifier]['last_request'] = $currentTime;
        }
        
        // Save updated limits
        file_put_contents($rateLimitFile, json_encode($rateLimits));
        
        if ($rateLimits[$identifier]['count'] > $maxUploads) {
            logSecurityEvent('rate_limit_exceeded', "Identifier: {$identifier}, Count: {$rateLimits[$identifier]['count']}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Get client identifier for rate limiting
     */
    private function getClientIdentifier() {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $ip . $userAgent);
    }
    
    /**
     * Get real client IP address
     */
    public function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Validate admin authentication
     */
    public function validateAdminAuth($password) {
        $ip = $this->getClientIP();
        
        // Check for brute force attempts
        if (!$this->checkLoginAttempts($ip)) {
            logSecurityEvent('login_blocked', "IP: {$ip}");
            return false;
        }
        
        // Verify password
        if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
            $this->recordFailedLogin($ip);
            logSecurityEvent('login_failed', "IP: {$ip}");
            return false;
        }
        
        // Reset failed attempts on successful login
        $this->resetFailedLogins($ip);
        logSecurityEvent('login_success', "IP: {$ip}");
        
        return true;
    }
    
    /**
     * Check login attempts for brute force protection
     */
    private function checkLoginAttempts($ip) {
        $attemptsFile = __DIR__ . '/data/login_attempts.json';
        
        if (!file_exists($attemptsFile)) {
            return true;
        }
        
        $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
        $currentTime = time();
        
        if (!isset($attempts[$ip])) {
            return true;
        }
        
        $ipAttempts = $attempts[$ip];
        
        // Check if IP is locked out
        if ($ipAttempts['count'] >= MAX_LOGIN_ATTEMPTS) {
            if ($currentTime - $ipAttempts['last_attempt'] < LOGIN_LOCKOUT_TIME) {
                return false;
            } else {
                // Lockout period expired, reset attempts
                unset($attempts[$ip]);
                file_put_contents($attemptsFile, json_encode($attempts));
            }
        }
        
        return true;
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedLogin($ip) {
        $attemptsFile = __DIR__ . '/data/login_attempts.json';
        $attempts = [];
        
        if (file_exists($attemptsFile)) {
            $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
        }
        
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $attempts[$ip]['count']++;
        $attempts[$ip]['last_attempt'] = time();
        
        file_put_contents($attemptsFile, json_encode($attempts));
    }
    
    /**
     * Reset failed login attempts
     */
    private function resetFailedLogins($ip) {
        $attemptsFile = __DIR__ . '/data/login_attempts.json';
        
        if (!file_exists($attemptsFile)) {
            return;
        }
        
        $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
        
        if (isset($attempts[$ip])) {
            unset($attempts[$ip]);
            file_put_contents($attemptsFile, json_encode($attempts));
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize user input
     */
    public function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            case 'filename':
                return preg_replace('/[^a-zA-Z0-9._-]/', '', basename($input));
            case 'int':
                return (int) $input;
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Check if request is from allowed origin
     */
    public function validateOrigin() {
        $allowedOrigins = [
            $_SERVER['HTTP_HOST'] ?? '',
            'localhost',
            '127.0.0.1'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        
        if (empty($origin)) {
            return true; // Allow requests without origin (direct access)
        }
        
        $originHost = parse_url($origin, PHP_URL_HOST);
        
        return in_array($originHost, $allowedOrigins);
    }
}

/**
 * Security middleware function
 */
function applySecurity() {
    $security = SecurityManager::getInstance();
    
    // Validate origin
    if (!$security->validateOrigin()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Invalid origin']));
    }
    
    // Check rate limiting
    if (!$security->checkRateLimit()) {
        http_response_code(429);
        die(json_encode(['success' => false, 'message' => 'Rate limit exceeded']));
    }
    
    // Log request
    logSecurityEvent('request', $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);
}

// Auto-apply security on include
applySecurity();

?>