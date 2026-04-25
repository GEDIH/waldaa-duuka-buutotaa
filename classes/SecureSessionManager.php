<?php
/**
 * Secure Session Manager
 * Implements production-grade session security with timeout, validation, and regeneration
 * 
 * Security Features:
 * - HttpOnly and Secure cookie flags
 * - Session timeout (30 minutes inactivity)
 * - IP address validation
 * - User agent validation
 * - Periodic session ID regeneration
 * - Secure session destruction
 * 
 * Requirements: 1.4.2, 1.4.3, 1.4.4
 */

class SecureSessionManager
{
    // Session lifetime: 30 minutes (1800 seconds)
    private const SESSION_LIFETIME = 1800;
    
    // Session regeneration interval: 5 minutes (300 seconds)
    private const REGENERATION_INTERVAL = 300;
    
    // Session name
    private const SESSION_NAME = 'WDB_SESSION';
    
    private static $instance = null;
    private $sessionStarted = false;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        // Constructor is private to enforce singleton
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
     * Initialize secure session with production-grade settings
     * 
     * Sets secure cookie parameters and starts session with validation
     * 
     * @return bool True if session initialized successfully
     */
    public function initialize()
    {
        // Only initialize once
        if ($this->sessionStarted) {
            return true;
        }
        
        // Configure secure session settings
        ini_set('session.cookie_httponly', '1');
        // Only use secure cookies on HTTPS (not on localhost HTTP)
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict to Lax for better compatibility
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string)self::SESSION_LIFETIME);
        ini_set('session.cookie_lifetime', '0'); // Session cookie (expires on browser close)
        
        // Set session name
        session_name(self::SESSION_NAME);
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->sessionStarted = true;
        }
        
        // Validate existing session
        if (!$this->validateSession()) {
            return false;
        }
        
        // Initialize session metadata on first access
        if (!isset($_SESSION['initialized'])) {
            $this->initializeSessionMetadata();
        }
        
        return true;
    }
    
    /**
     * Initialize session metadata for security tracking
     */
    private function initializeSessionMetadata()
    {
        $_SESSION['initialized'] = true;
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $this->getClientIP();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Validate session security constraints
     * 
     * Checks:
     * - Session timeout (30 minutes inactivity)
     * - IP address consistency
     * - User agent consistency
     * - Periodic session ID regeneration
     * 
     * @return bool True if session is valid
     */
    private function validateSession()
    {
        // Check if session has required metadata
        if (!isset($_SESSION['last_activity'])) {
            // New session, will be initialized
            return true;
        }
        
        // Check session timeout (Requirement 1.4.3)
        if (time() - $_SESSION['last_activity'] > self::SESSION_LIFETIME) {
            $this->destroy();
            return false;
        }
        
        // Update last activity timestamp
        $_SESSION['last_activity'] = time();
        
        // Validate IP address consistency
        if (isset($_SESSION['ip_address'])) {
            $currentIP = $this->getClientIP();
            if ($_SESSION['ip_address'] !== $currentIP) {
                // IP address changed - potential session hijacking
                $this->destroy();
                return false;
            }
        }
        
        // Validate user agent consistency
        if (isset($_SESSION['user_agent'])) {
            $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($_SESSION['user_agent'] !== $currentUserAgent) {
                // User agent changed - potential session hijacking
                $this->destroy();
                return false;
            }
        }
        
        // Regenerate session ID periodically (Requirement 1.4.4)
        if (isset($_SESSION['created'])) {
            if (time() - $_SESSION['created'] > self::REGENERATION_INTERVAL) {
                $this->regenerateId();
            }
        }
        
        return true;
    }
    
    /**
     * Regenerate session ID to prevent session fixation
     * 
     * Should be called:
     * - After successful login (Requirement 1.4.4)
     * - Periodically during session lifetime
     * 
     * @return bool True if regeneration successful
     */
    public function regenerateId()
    {
        if (!$this->sessionStarted) {
            return false;
        }
        
        // Regenerate session ID and delete old session
        if (session_regenerate_id(true)) {
            $_SESSION['created'] = time();
            return true;
        }
        
        return false;
    }
    
    /**
     * Destroy session securely
     * 
     * Clears all session data, removes session cookie, and destroys session
     */
    public function destroy()
    {
        if (!$this->sessionStarted) {
            return;
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Remove session cookie
        if (isset($_COOKIE[self::SESSION_NAME])) {
            $params = session_get_cookie_params();
            setcookie(
                self::SESSION_NAME,
                '',
                time() - 3600,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        session_destroy();
        $this->sessionStarted = false;
    }
    
    /**
     * Get client IP address
     * 
     * Handles proxies and load balancers
     * 
     * @return string Client IP address
     */
    private function getClientIP()
    {
        // Check for proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool True if user is authenticated
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get session value
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key not found
     * @return mixed Session value or default
     */
    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Set session value
     * 
     * @param string $key Session key
     * @param mixed $value Session value
     */
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Check if session key exists
     * 
     * @param string $key Session key
     * @return bool True if key exists
     */
    public function has($key)
    {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session key
     * 
     * @param string $key Session key
     */
    public function remove($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Get user ID from session
     * 
     * @return int|null User ID or null
     */
    public function getUserId()
    {
        return $this->get('user_id');
    }
    
    /**
     * Get user role from session
     * 
     * @return string|null User role or null
     */
    public function getRole()
    {
        return $this->get('role');
    }
    
    /**
     * Get center ID from session
     * 
     * @return int|null Center ID or null
     */
    public function getCenterId()
    {
        return $this->get('center_id');
    }
    
    /**
     * Check if user has specific role
     * 
     * @param string|array $role Role or array of roles
     * @return bool True if user has role
     */
    public function hasRole($role)
    {
        $userRole = $this->getRole();
        
        if (is_array($role)) {
            return in_array($userRole, $role);
        }
        
        return $userRole === $role;
    }
    
    /**
     * Check if user is superadmin
     * 
     * @return bool True if user is superadmin
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('superadmin');
    }
    
    /**
     * Check if user is admin
     * 
     * @return bool True if user is admin or superadmin
     */
    public function isAdmin()
    {
        return $this->hasRole(['superadmin', 'admin']);
    }
    
    /**
     * Get session lifetime in seconds
     * 
     * @return int Session lifetime
     */
    public function getSessionLifetime()
    {
        return self::SESSION_LIFETIME;
    }
    
    /**
     * Get time remaining in current session
     * 
     * @return int Seconds remaining or 0 if session expired
     */
    public function getTimeRemaining()
    {
        if (!isset($_SESSION['last_activity'])) {
            return 0;
        }
        
        $elapsed = time() - $_SESSION['last_activity'];
        $remaining = self::SESSION_LIFETIME - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Check if session is about to expire
     * 
     * @param int $warningThreshold Seconds before expiry to warn (default: 300 = 5 minutes)
     * @return bool True if session will expire soon
     */
    public function isExpiringSoon($warningThreshold = 300)
    {
        return $this->getTimeRemaining() <= $warningThreshold;
    }
}
