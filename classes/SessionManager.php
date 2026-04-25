<?php
/**
 * Session Manager
 * Handles user session management and authentication
 */

class SessionManager
{
    private $sessionStarted = false;
    
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->sessionStarted = true;
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get session value
     */
    public function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Set session value
     */
    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Check if session key exists
     */
    public function has($key)
    {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session key
     */
    public function remove($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Destroy session
     */
    public function destroy()
    {
        if ($this->sessionStarted) {
            session_destroy();
            $_SESSION = [];
        }
    }
    
    /**
     * Get user ID from session
     */
    public function getUserId()
    {
        return $this->get('user_id');
    }
    
    /**
     * Get user role from session
     */
    public function getRole()
    {
        return $this->get('role');
    }
    
    /**
     * Get center ID from session
     */
    public function getCenterId()
    {
        return $this->get('center_id');
    }
    
    /**
     * Check if user has specific role
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
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('superadmin');
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->hasRole(['superadmin', 'admin']);
    }
}

?>
