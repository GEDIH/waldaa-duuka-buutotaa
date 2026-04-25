/**
 * Authentication and Session Management
 * Handles login, logout, session validation, and auTesfayeatic refresh
 */

class AuthManager {
    constructor() {
        this.sessionCheckInterval = null;
        this.sessionWarningShown = false;
        this.refreshInterval = 15 * 60 * 1000; // 15 minutes
        this.warningTime = 5 * 60 * 1000; // 5 minutes before expiry
        
        this.init();
    }
    
    init() {
        // Start session monitoring if user is logged in
        if (this.isLoggedIn()) {
            this.startSessionMonitoring();
        }
        
        // Listen for storage changes (multi-tab logout)
        window.addEventListener('storage', (e) => {
            if (e.key === 'wdb_user' && !e.newValue) {
                // User logged out in another tab
                this.handleLogout(false);
            }
        });
        
        // Listen for page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isLoggedIn()) {
                this.verifySession();
            }
        });
    }
    
    /**
     * Check if user is logged in
     */
    isLoggedIn() {
        const userData = localStorage.getItem('wdb_user');
        return userData !== null;
    }
    
    /**
     * Get current user data
     */
    getCurrentUser() {
        const userData = localStorage.getItem('wdb_user');
        if (userData) {
            try {
                return JSON.parse(userData);
            } catch (e) {
                console.error('Error parsing user data:', e);
                this.handleLogout();
                return null;
            }
        }
        return null;
    }
    
    /**
     * Get user permissions
     */
    getPermissions() {
        const permissionsData = localStorage.getItem('wdb_permissions');
        if (permissionsData) {
            try {
                return JSON.parse(permissionsData);
            } catch (e) {
                console.error('Error parsing permissions data:', e);
                return {};
            }
        }
        return {};
    }
    
    /**
     * Check if user has specific permission
     */
    hasPermission(permission) {
        const permissions = this.getPermissions();
        return permissions[permission] === true;
    }
    
    /**
     * Check if user has specific role
     */
    hasRole(role) {
        const user = this.getCurrentUser();
        if (!user) return false;
        
        // SuperAdmin has all roles
        if (user.role === 'superadmin') return true;
        
        // Admin has admin and user roles
        if (user.role === 'admin' && ['admin', 'user'].includes(role)) return true;
        
        // Exact role match
        return user.role === role;
    }
    
    /**
     * Start session monitoring
     */
    startSessionMonitoring() {
        // Clear existing interval
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
        }
        
        // Check session every 5 minutes
        this.sessionCheckInterval = setInterval(() => {
            this.verifySession();
        }, 5 * 60 * 1000);
        
        // DON'T verify immediately on page load - causes redirect loop
        // The user just logged in, session is valid
        // this.verifySession();
    }
    
    /**
     * Stop session monitoring
     */
    stopSessionMonitoring() {
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
            this.sessionCheckInterval = null;
        }
    }
    
    /**
     * Verify session with server
     */
    async verifySession() {
        if (!this.isLoggedIn()) {
            return false;
        }
        
        try {
            const response = await fetch('api/auth/verify-session.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    // Update user data and permissions
                    localStorage.setItem('wdb_user', JSON.stringify(result.data.user));
                    localStorage.setItem('wdb_permissions', JSON.stringify(result.data.permissions));
                    
                    // Log successful session verification
                    if (window.auditLogger) {
                        window.auditLogger.logSystemEvent('SESSION_VERIFIED', {
                            user_id: result.data.user.id,
                            username: result.data.user.username
                        });
                    }
                    
                    this.sessionWarningShown = false;
                    return true;
                } else {
                    console.warn('Session verification failed:', result.error);
                    
                    // Log session verification failure
                    if (window.auditLogger) {
                        window.auditLogger.logSecurityEvent('SESSION_VERIFICATION_FAILED', {
                            reason: result.error
                        });
                    }
                    
                    this.handleLogout();
                    return false;
                }
            } else {
                console.warn('Session verification request failed');
                
                // Log session verification error
                if (window.auditLogger) {
                    window.auditLogger.logSecurityEvent('SESSION_VERIFICATION_ERROR', {
                        status_code: response.status
                    });
                }
                
                this.handleLogout();
                return false;
            }
        } catch (error) {
            console.error('Session verification error:', error);
            
            // Log session verification error
            if (window.auditLogger) {
                window.auditLogger.logSecurityEvent('SESSION_VERIFICATION_ERROR', {
                    error: error.message
                });
            }
            
            // Don't logout on network errors, just log the error
            return false;
        }
    }
    
    /**
     * Refresh session
     */
    async refreshSession() {
        if (!this.isLoggedIn()) {
            return false;
        }
        
        try {
            const response = await fetch('api/auth/refresh-session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    // Update user data, permissions, and CSRF token
                    localStorage.setItem('wdb_user', JSON.stringify(result.data.user));
                    localStorage.setItem('wdb_permissions', JSON.stringify(result.data.permissions));
                    localStorage.setItem('wdb_csrf_token', result.data.csrf_token);
                    
                    console.log('Session refreshed successfully');
                    return true;
                } else {
                    console.warn('Session refresh failed:', result.error);
                    this.handleLogout();
                    return false;
                }
            } else {
                console.warn('Session refresh request failed');
                this.handleLogout();
                return false;
            }
        } catch (error) {
            console.error('Session refresh error:', error);
            return false;
        }
    }
    
    /**
     * Handle logout
     */
    async handleLogout(callServer = true) {
        // Log logout action
        if (window.auditLogger) {
            const user = this.getCurrentUser();
            if (user) {
                window.auditLogger.logAuthentication('AUTH_LOGOUT', true, {
                    user_id: user.id,
                    username: user.username,
                    logout_method: callServer ? 'server_call' : 'client_side'
                });
            }
        }
        
        // Stop session monitoring
        this.stopSessionMonitoring();
        
        // Call server logout if requested
        if (callServer) {
            try {
                await fetch('api/auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
            } catch (error) {
                console.error('Logout request error:', error);
            }
        }
        
        // Clear local storage
        localStorage.removeItem('wdb_user');
        localStorage.removeItem('wdb_csrf_token');
        localStorage.removeItem('wdb_permissions');
        
        // Redirect to login page
        window.location.href = 'login.html';
    }
    
    /**
     * Show session warning
     */
    showSessionWarning() {
        if (this.sessionWarningShown) {
            return;
        }
        
        this.sessionWarningShown = true;
        
        const warning = document.createElement('div');
        warning.id = 'sessionWarning';
        warning.className = 'fixed top-0 left-0 right-0 bg-yellow-500 text-white p-4 text-center z-50';
        warning.innerHTML = `
            <div class="flex items-center justify-center space-x-4">
                <span>Your session will expire soon. Do you want to extend it?</span>
                <button id="extendSession" class="bg-white text-yellow-500 px-4 py-2 rounded hover:bg-gray-100">
                    Extend Session
                </button>
                <button id="dismissWarning" class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">
                    Dismiss
                </button>
            </div>
        `;
        
        document.body.appendChild(warning);
        
        // Handle extend session
        document.getElementById('extendSession').addEventListener('click', async () => {
            const success = await this.refreshSession();
            if (success) {
                document.body.removeChild(warning);
                this.sessionWarningShown = false;
            }
        });
        
        // Handle dismiss
        document.getElementById('dismissWarning').addEventListener('click', () => {
            document.body.removeChild(warning);
            this.sessionWarningShown = false;
        });
        
        // Auto-dismiss after 30 seconds
        setTimeout(() => {
            if (document.getElementById('sessionWarning')) {
                document.body.removeChild(warning);
                this.sessionWarningShown = false;
            }
        }, 30000);
    }
    
    /**
     * Get CSRF token
     */
    getCSRFToken() {
        return localStorage.getItem('wdb_csrf_token');
    }
    
    /**
     * Make authenticated API request
     */
    async apiRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        // Add CSRF token for non-GET requests
        if (options.method && options.method !== 'GET') {
            const csrfToken = this.getCSRFToken();
            if (csrfToken && options.body) {
                const body = JSON.parse(options.body);
                body.csrf_token = csrfToken;
                options.body = JSON.stringify(body);
            }
        }
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        try {
            const response = await fetch(url, mergedOptions);
            
            // Handle authentication errors
            if (response.status === 401) {
                this.handleLogout();
                throw new Error('Authentication required');
            }
            
            return response;
        } catch (error) {
            console.error('API request error:', error);
            throw error;
        }
    }
    
    /**
     * Redirect based on user role
     */
    redirectToDashboard() {
        const user = this.getCurrentUser();
        if (!user) {
            window.location.href = 'login.html';
            return;
        }
        
        if (['admin', 'superadmin'].includes(user.role)) {
            window.location.href = 'dashboard.html';
        } else if (user.role === 'user') {
            window.location.href = 'member-dashboard.html';
        } else {
            window.location.href = 'login.html';
        }
    }
    
    /**
     * Check page access permissions
     */
    checkPageAccess(requiredRole = null, requiredPermission = null) {
        if (!this.isLoggedIn()) {
            window.location.href = 'login.html';
            return false;
        }
        
        if (requiredRole && !this.hasRole(requiredRole)) {
            window.location.href = 'unauthorized.html';
            return false;
        }
        
        if (requiredPermission && !this.hasPermission(requiredPermission)) {
            window.location.href = 'unauthorized.html';
            return false;
        }
        
        return true;
    }
}

// Initialize global auth manager
window.authManager = new AuthManager();

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuthManager;
}