/**
 * WDB Advanced Dashboard System - Core Infrastructure
 * Modern dashboard shell with responsive layout, theme system, and widget architecture
 */

class DashboardCore {
    constructor() {
        this.currentUser = null;
        this.currentTheme = 'light';
        this.currentLanguage = 'en';
        this.sidebarCollapsed = false;
        this.sidebarMode = 'fixed';
        this.widgets = new Map();
        this.webSocketService = null;
        this.themeSystem = null;
        this.widgetSystem = null;
        this.responsiveLayout = null;
        this.isInitialized = false;
        
        // Event handlers
        this.eventHandlers = {
            initialized: [],
            themeChanged: [],
            navigationChanged: [],
            sidebarStateChanged: [],
            breakpointChanged: [],
            realTimeUpdate: [],
            userSessionChanged: []
        };
        
        this.init();
    }

    async init() {
        console.log('Initializing Advanced Dashboard System...');
        
        try {
            // Initialize core systems in order
            await this.initializeThemeSystem();
            await this.initializeResponsiveLayout();
            await this.initializeWebSocketService();
            await this.initializeWidgetSystem();
            
            // Load user session and preferences
            await this.loadUserSession();
            await this.loadUserPreferences();
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Initialize dashboard based on user role
            await this.initializeDashboard();
            
            // Mark as initialized
            this.isInitialized = true;
            this.emit('initialized', { timestamp: new Date().toISOString() });
            
            console.log('Advanced Dashboard System initialized successfully');
            
        } catch (error) {
            console.error('Failed to initialize dashboard system:', error);
            this.showNotification('Failed to initialize dashboard system', 'error');
        }
    }

    /**
     * Theme System Implementation
     */
    async initializeThemeSystem() {
        if (window.ThemeSystem) {
            this.themeSystem = new ThemeSystem();
            
            // Load saved theme preference
            const savedTheme = safeGetItem('wdb_theme') || 'light';
            await this.setTheme(savedTheme);
            
            // Setup theme change listener
            this.themeSystem.on('themeChanged', (event) => {
                this.emit('themeChanged', event.detail);
            });
            
            console.log('Theme system initialized');
        } else {
            console.warn('ThemeSystem not available, using fallback');
        }
    }

    async setTheme(theme) {
        this.currentTheme = theme;
        
        if (this.themeSystem) {
            this.themeSystem.applyTheme(theme);
        } else {
            // Fallback theme application
            document.documentElement.setAttribute('data-theme', theme);
            safeSetItem('wdb_theme', theme);
        }
        
        // Emit theme change event
        this.emit('themeChanged', { theme });
    }

    toggleTheme() {
        const themes = ['light', 'dark'];
        const currentIndex = themes.indexOf(this.currentTheme);
        const nextTheme = themes[(currentIndex + 1) % themes.length];
        this.setTheme(nextTheme);
    }

    /**
     * WebSocket Service Implementation
     */
    async initializeWebSocketService() {
        if (window.WebSocketService) {
            this.webSocketService = new WebSocketService();
            
            // Setup connection event handlers
            this.webSocketService.on('connected', () => {
                console.log('WebSocket connected - real-time updates enabled');
                this.updateConnectionStatus(true);
                this.showNotification('Real-time updates enabled', 'success');
            });
            
            this.webSocketService.on('disconnected', () => {
                console.log('WebSocket disconnected - switching to offline mode');
                this.updateConnectionStatus(false);
                this.showNotification('Connection lost. Working in offline mode.', 'warning');
            });
            
            this.webSocketService.on('reconnecting', (data) => {
                console.log('WebSocket reconnecting...', data);
                this.showNotification(`Reconnecting... (attempt ${data.attempt})`, 'info');
            });
            
            this.webSocketService.on('error', (error) => {
                console.error('WebSocket error:', error);
                this.showNotification('Connection error occurred', 'error');
            });
            
            console.log('WebSocket service initialized');
        } else {
            console.warn('WebSocketService not available');
        }
    }

    /**
     * Widget System Implementation
     */
    async initializeWidgetSystem() {
        if (window.WidgetSystem) {
            this.widgetSystem = new WidgetSystem();
            
            // Register default widget types
            if (window.MetricWidget) this.widgetSystem.registerWidget('metric', MetricWidget);
            if (window.ChartWidget) this.widgetSystem.registerWidget('chart', ChartWidget);
            if (window.TableWidget) this.widgetSystem.registerWidget('table', TableWidget);
            if (window.ActionWidget) this.widgetSystem.registerWidget('action', ActionWidget);
            if (window.CusTesfayeWidget) this.widgetSystem.registerWidget('cusTesfaye', CusTesfayeWidget);
            
            // Setup widget event handlers
            this.widgetSystem.on('widgetCreated', (data) => {
                console.log(`Widget created: ${data.widget.id}`);
            });
            
            this.widgetSystem.on('widgetError', (data) => {
                console.error(`Widget error: ${data.id}`, data.error);
                this.showNotification(`Widget error: ${data.id}`, 'error');
            });
            
            console.log('Widget system initialized');
        } else {
            console.warn('WidgetSystem not available');
        }
    }

    /**
     * Responsive Layout System
     */
    async initializeResponsiveLayout() {
        if (window.ResponsiveLayout) {
            this.responsiveLayout = new ResponsiveLayout();
            
            // Setup breakpoint listeners
            this.responsiveLayout.on('breakpointChanged', (data) => {
                this.handleBreakpointChange(data);
            });
            
            this.responsiveLayout.on('orientationChanged', (data) => {
                console.log('Orientation changed:', data.orientation);
                this.handleOrientationChange(data);
            });
            
            console.log('Responsive layout system initialized');
        } else {
            console.warn('ResponsiveLayout not available, using fallback');
            this.setupFallbackResponsive();
        }
    }

    setupFallbackResponsive() {
        // Basic responsive fallback
        window.addEventListener('resize', () => {
            const width = window.innerWidth;
            let breakpoint = 'desktop';
            
            if (width <= 768) breakpoint = 'mobile';
            else if (width <= 1024) breakpoint = 'tablet';
            
            this.handleBreakpointChange({ current: breakpoint, width });
        });
    }

    handleBreakpointChange(data) {
        const breakpoint = data.current || data;
        console.log(`Breakpoint changed to: ${breakpoint}`);
        
        // Adjust sidebar behavior based on breakpoint
        if (breakpoint === 'mobile' || breakpoint === 'tablet') {
            this.setSidebarMode('overlay');
            // Auto-collapse sidebar on mobile
            if (breakpoint === 'mobile') {
                this.setSidebarCollapsed(true);
            }
        } else {
            this.setSidebarMode('fixed');
        }
        
        // Adjust widget layouts
        if (this.widgetSystem) {
            this.widgetSystem.adjustForBreakpoint(breakpoint);
        }
        
        // Update body classes for responsive styling
        this.updateResponsiveClasses(breakpoint);
        
        // Emit breakpoint change event
        this.emit('breakpointChanged', data);
    }

    handleOrientationChange(data) {
        // Handle orientation-specific adjustments
        const { orientation } = data;
        
        // Update CSS cusTesfaye property
        document.documentElement.style.setProperty('--orientation', orientation);
        
        // Adjust layouts for orientation
        if (orientation === 'landscape' && this.responsiveLayout?.isMobile()) {
            // Special handling for mobile landscape
            this.adjustMobileLandscape();
        }
    }

    updateResponsiveClasses(breakpoint) {
        const body = document.body;
        
        // Remove existing responsive classes
        body.classList.remove('is-mobile', 'is-tablet', 'is-desktop', 'is-large');
        
        // Add current breakpoint class
        body.classList.add(`is-${breakpoint}`);
    }

    adjustMobileLandscape() {
        // Specific adjustments for mobile landscape mode
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.add('mobile-landscape');
        }
    }

    /**
     * User Session Management
     */
    async loadUserSession() {
        try {
            const session = safeGetItem('wdb_session');
            if (session) {
                this.currentUser = JSON.parse(session);
                console.log(`User session loaded: ${this.currentUser.username} (${this.currentUser.role})`);
            } else {
                // Redirect to login if no session
                window.location.href = 'login.html';
                return;
            }
        } catch (error) {
            console.error('Failed to load user session:', error);
            window.location.href = 'login.html';
        }
    }

    async loadUserPreferences() {
        try {
            const preferences = safeGetItem('wdb_preferences');
            if (preferences) {
                const prefs = JSON.parse(preferences);
                
                // Apply theme preference
                if (prefs.theme) {
                    this.setTheme(prefs.theme);
                }
                
                // Apply sidebar preference
                if (prefs.sidebarCollapsed !== undefined) {
                    this.setSidebarCollapsed(prefs.sidebarCollapsed);
                }
                
                // Apply language preference
                if (prefs.language) {
                    this.setLanguage(prefs.language);
                }
            }
        } catch (error) {
            console.error('Failed to load user preferences:', error);
        }
    }

    saveUserPreferences() {
        const preferences = {
            theme: this.currentTheme,
            sidebarCollapsed: this.sidebarCollapsed,
            language: this.currentLanguage || 'en',
            lastSaved: new Date().toISOString()
        };
        
        safeSetItem('wdb_preferences', JSON.stringify(preferences));
    }

    /**
     * Dashboard Initialization Based on Role
     */
    async initializeDashboard() {
        if (!this.currentUser) return;
        
        const role = this.currentUser.role;
        console.log(`Initializing dashboard for role: ${role}`);
        
        // Load role-specific configuration
        const dashboardConfig = await this.loadDashboardConfig(role);
        
        // Initialize sidebar navigation
        this.initializeSidebar(dashboardConfig.navigation);
        
        // Initialize widgets
        await this.initializeWidgets(dashboardConfig.widgets);
        
        // Setup role-specific features
        this.setupRoleFeatures(role);
        
        // Connect to real-time updates
        await this.connectRealTimeUpdates(role);
    }

    async loadDashboardConfig(role) {
        // Default configurations for each role
        const configs = {
            superadmin: {
                navigation: [
                    { id: 'dashboard', label: 'System Overview', icon: 'fas fa-tachometer-alt', path: '#dashboard' },
                    { id: 'system-control', label: 'System Control', icon: 'fas fa-cogs', path: '#system-control', badge: 'CRITICAL' },
                    { id: 'user-management', label: 'User Management', icon: 'fas fa-users-cog', path: '#user-management' },
                    { id: 'security-center', label: 'Security Center', icon: 'fas fa-shield-alt', path: '#security-center' },
                    { id: 'analytics', label: 'Advanced Analytics', icon: 'fas fa-chart-line', path: '#analytics' }
                ],
                widgets: [
                    { type: 'metric', id: 'system-health', title: 'System Health', size: 'small' },
                    { type: 'metric', id: 'total-users', title: 'Total Users', size: 'small' },
                    { type: 'metric', id: 'security-score', title: 'Security Score', size: 'small' },
                    { type: 'metric', id: 'database-size', title: 'Database Size', size: 'small' },
                    { type: 'chart', id: 'system-performance', title: 'System Performance', size: 'large' },
                    { type: 'table', id: 'recent-activities', title: 'Recent Activities', size: 'medium' }
                ]
            },
            admin: {
                navigation: [
                    { id: 'dashboard', label: 'Dashboard Overview', icon: 'fas fa-tachometer-alt', path: '#dashboard' },
                    { id: 'members', label: 'Member Management', icon: 'fas fa-users', path: '#members' },
                    { id: 'contributions', label: 'Contributions', icon: 'fas fa-donate', path: '#contributions' },
                    { id: 'centers', label: 'My Centers', icon: 'fas fa-building', path: '#centers' },
                    { id: 'reports', label: 'Reports & Analytics', icon: 'fas fa-chart-bar', path: '#reports' }
                ],
                widgets: [
                    { type: 'metric', id: 'total-members', title: 'Total Members', size: 'small' },
                    { type: 'metric', id: 'paid-members', title: 'Paid Members', size: 'small' },
                    { type: 'metric', id: 'new-today', title: 'New Today', size: 'small' },
                    { type: 'metric', id: 'total-contributions', title: 'Total Contributions', size: 'small' },
                    { type: 'chart', id: 'member-trends', title: 'Member Registration Trends', size: 'large' },
                    { type: 'chart', id: 'contribution-analytics', title: 'Contribution Analytics', size: 'large' }
                ]
            },
            user: {
                navigation: [
                    { id: 'dashboard', label: 'My Dashboard', icon: 'fas fa-tachometer-alt', path: '#dashboard' },
                    { id: 'profile', label: 'My Profile', icon: 'fas fa-user', path: '#profile' },
                    { id: 'contributions', label: 'My Contributions', icon: 'fas fa-donate', path: '#contributions' },
                    { id: 'documents', label: 'My Documents', icon: 'fas fa-file-alt', path: '#documents' },
                    { id: 'announcements', label: 'Announcements', icon: 'fas fa-bullhorn', path: '#announcements' }
                ],
                widgets: [
                    { type: 'metric', id: 'membership-status', title: 'Membership Status', size: 'medium' },
                    { type: 'metric', id: 'contribution-summary', title: 'Contribution Summary', size: 'medium' },
                    { type: 'chart', id: 'contribution-history', title: 'My Contribution History', size: 'large' },
                    { type: 'table', id: 'recent-announcements', title: 'Recent Announcements', size: 'medium' }
                ]
            }
        };
        
        return configs[role] || configs.user;
    }

    /**
     * Sidebar Management
     */
    initializeSidebar(navigationItems) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        
        // Generate navigation HTML
        const navHTML = this.generateNavigationHTML(navigationItems);
        
        // Update sidebar content
        const navContainer = sidebar.querySelector('.sidebar-navigation') || sidebar;
        navContainer.innerHTML = navHTML;
        
        // Setup navigation event listeners
        this.setupNavigationListeners();
    }

    generateNavigationHTML(items) {
        return items.map(item => `
            <a href="${item.path}" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-text-primary transition-all duration-200" data-section="${item.id}">
                <i class="${item.icon} text-lg"></i>
                <span>${item.label}</span>
                ${item.badge ? `<span class="ml-auto bg-red-600 text-white text-xs px-2 py-1 rounded-full">${item.badge}</span>` : ''}
            </a>
        `).join('');
    }

    setupNavigationListeners() {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const section = item.dataset.section;
                this.navigateToSection(section);
            });
        });
    }

    navigateToSection(section) {
        // Update active navigation item
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const activeItem = document.querySelector(`[data-section="${section}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }
        
        // Show corresponding section
        this.showSection(section);
        
        // Emit navigation event
        this.emit('navigationChanged', { section });
    }

    setSidebarCollapsed(collapsed) {
        this.sidebarCollapsed = collapsed;
        const sidebar = document.getElementById('sidebar');
        
        if (sidebar) {
            sidebar.classList.toggle('collapsed', collapsed);
        }
        
        // Save preference
        this.saveUserPreferences();
        
        // Emit sidebar state change event
        this.emit('sidebarStateChanged', { collapsed });
    }

    toggleSidebar() {
        this.setSidebarCollapsed(!this.sidebarCollapsed);
    }

    setSidebarMode(mode) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.setAttribute('data-mode', mode);
        }
    }

    /**
     * Widget Management
     */
    async initializeWidgets(widgetConfigs) {
        for (const config of widgetConfigs) {
            await this.createWidget(config);
        }
    }

    async createWidget(config) {
        try {
            const widget = await this.widgetSystem.createWidget(config);
            this.widgets.set(config.id, widget);
            
            // Render widget to dashboard
            this.renderWidget(widget);
            
            console.log(`Widget created: ${config.id} (${config.type})`);
        } catch (error) {
            console.error(`Failed to create widget ${config.id}:`, error);
        }
    }

    renderWidget(widget) {
        const container = document.getElementById('dashboard-widgets') || document.getElementById('main-content');
        if (container) {
            const widgetElement = widget.render();
            container.appendChild(widgetElement);
        }
    }

    /**
     * Real-time Updates
     */
    async connectRealTimeUpdates(role) {
        if (!this.webSocketService) return;
        
        try {
            await this.webSocketService.connect();
            
            // Subscribe to role-specific channels
            const channels = this.getRoleChannels(role);
            channels.forEach(channel => {
                this.webSocketService.subscribe(channel, (data) => {
                    this.handleRealTimeUpdate(channel, data);
                });
            });
            
        } catch (error) {
            console.error('Failed to connect real-time updates:', error);
        }
    }

    getRoleChannels(role) {
        const channels = {
            superadmin: [
                'system.health',
                'security.alerts',
                'user.activity',
                'system.performance'
            ],
            admin: [
                'member.updates',
                'contribution.notifications',
                'center.activities'
            ],
            user: [
                'user.notifications',
                'center.announcements'
            ]
        };
        
        return channels[role] || [];
    }

    handleRealTimeUpdate(channel, data) {
        console.log(`Real-time update received on ${channel}:`, data);
        
        // Update relevant widgets
        this.widgets.forEach(widget => {
            if (widget.subscribesToChannel && widget.subscribesToChannel(channel)) {
                widget.updateData(data);
            }
        });
        
        // Emit real-time update event
        this.emit('realTimeUpdate', { channel, data });
    }

    /**
     * Connection Status Management
     */
    updateConnectionStatus(connected) {
        const indicator = document.querySelector('.connection-indicator');
        if (indicator) {
            indicator.classList.toggle('connected', connected);
            indicator.classList.toggle('disconnected', !connected);
            indicator.textContent = connected ? 'Online' : 'Offline';
        }
        
        // Show notification for connection changes
        if (!connected) {
            this.showNotification('Connection lost. Working in offline mode.', 'warning');
        } else {
            this.showNotification('Connection restored. Real-time updates enabled.', 'success');
        }
    }

    /**
     * Section Management
     */
    showSection(sectionId) {
        // Hide all sections
        document.querySelectorAll('.dashboard-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Show target section
        const targetSection = document.getElementById(`${sectionId}-section`);
        if (targetSection) {
            targetSection.classList.add('active');
        }
    }

    /**
     * Role-specific Features
     */
    setupRoleFeatures(role) {
        // Hide/show elements based on role
        document.querySelectorAll('[data-role]').forEach(element => {
            const requiredRole = element.dataset.role;
            const hasAccess = this.checkRoleAccess(role, requiredRole);
            element.style.display = hasAccess ? '' : 'none';
        });
        
        // Setup role-specific event handlers
        this.setupRoleEventHandlers(role);
    }

    checkRoleAccess(userRole, requiredRole) {
        const roleHierarchy = {
            superadmin: ['superadmin', 'admin', 'user'],
            admin: ['admin', 'user'],
            user: ['user']
        };
        
        return roleHierarchy[userRole]?.includes(requiredRole) || false;
    }

    setupRoleEventHandlers(role) {
        // Role-specific event handlers will be implemented here
        switch (role) {
            case 'superadmin':
                this.setupSuperAdminHandlers();
                break;
            case 'admin':
                this.setupAdminHandlers();
                break;
            case 'user':
                this.setupUserHandlers();
                break;
        }
    }

    setupSuperAdminHandlers() {
        // Super admin specific event handlers
        console.log('Setting up super admin event handlers');
    }

    setupAdminHandlers() {
        // Admin specific event handlers
        console.log('Setting up admin event handlers');
    }

    setupUserHandlers() {
        // User specific event handlers
        console.log('Setting up user event handlers');
    }

    /**
     * Event System
     */
    setupEventListeners() {
        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }
        
        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }
        
        // Window resize handler
        window.addEventListener('resize', () => {
            this.responsiveLayout.handleResize();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    }

    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + B: Toggle sidebar
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            this.toggleSidebar();
        }
        
        // Ctrl/Cmd + Shift + T: Toggle theme
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'T') {
            e.preventDefault();
            this.toggleTheme();
        }
    }

    /**
     * Utility Methods
     */
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} fixed top-4 right-4 p-4 rounded-lg text-white z-50 transition-all duration-300`;
        notification.textContent = message;
        
        // Add to DOM
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    emit(eventName, data) {
        const event = new CusTesfayeEvent(eventName, { detail: data });
        document.dispatchEvent(event);
    }

    on(eventName, handler) {
        document.addEventListener(eventName, handler);
    }

    off(eventName, handler) {
        document.removeEventListener(eventName, handler);
    }

    /**
     * Cleanup
     */
    destroy() {
        // Disconnect WebSocket
        if (this.webSocketService) {
            this.webSocketService.disconnect();
        }
        
        // Cleanup widgets
        this.widgets.forEach(widget => {
            if (widget.destroy) {
                widget.destroy();
            }
        });
        this.widgets.clear();
        
        // Remove event listeners
        // (Event listeners will be cleaned up auTesfayeatically when elements are removed)
        
        console.log('Dashboard core destroyed');
    }
}

// Export for global access
window.DashboardCore = DashboardCore;