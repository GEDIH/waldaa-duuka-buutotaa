/**
 * Analytics Notification System
 * Configurable alert thresholds and notification display for dashboard metrics
 * Requirements: 12.1, 12.2, 12.3, 12.4
 * Task: 10.1 Implement notification system integration
 */

class AnalyticsNotificationSystem {
    constructor(widgetManager) {
        this.widgetManager = widgetManager;
        this.notifications = new Map();
        this.thresholds = new Map();
        this.preferences = new Map();
        this.activeAlerts = new Map();
        
        // Configuration
        this.config = {
            maxNotifications: 50,
            defaultDuration: 5000,
            persistentTypes: ['critical', 'error'],
            soundEnabled: true,
            vibrationEnabled: true,
            browserNotificationsEnabled: false
        };
        
        // Notification types
        this.notificationTypes = {
            visual: 'visual',
            popup: 'popup',
            system: 'system',
            toast: 'toast',
            badge: 'badge'
        };
        
        // Alert levels
        this.alertLevels = {
            info: { priority: 1, color: '#17a2b8', icon: 'fas fa-info-circle' },
            warning: { priority: 2, color: '#ffc107', icon: 'fas fa-exclamation-triangle' },
            critical: { priority: 3, color: '#dc3545', icon: 'fas fa-exclamation-circle' },
            success: { priority: 1, color: '#28a745', icon: 'fas fa-check-circle' }
        };
        
        this.init();
    }
    
    /**
     * Initialize notification system
     */
    async init() {
        try {
            console.log('Initializing Analytics Notification System...');
            
            // Load notification preferences
            await this.loadNotificationPreferences();
            
            // Load alert thresholds
            await this.loadAlertThresholds();
            
            // Setup notification container
            this.setupNotificationContainer();
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Request browser notification permission if enabled
            if (this.config.browserNotificationsEnabled) {
                await this.requestNotificationPermission();
            }
            
            console.log('Analytics Notification System initialized');
            
        } catch (error) {
            console.error('Failed to initialize notification system:', error);
        }
    }
    /**
     * Load notification preferences from database
     */
    async loadNotificationPreferences() {
        try {
            const response = await fetch('api/analytics/notification-preferences.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_preferences',
                    dashboard_type: this.widgetManager.context.dashboardType,
                    user_id: this.widgetManager.context.userId
                })
            });
            
            const result = await response.json();
            
            if (result.success && result.data) {
                result.data.forEach(pref => {
                    this.preferences.set(pref.preference_key, pref.preference_value);
                });
                console.log(`Loaded ${result.data.length} notification preferences`);
            }
        } catch (error) {
            console.error('Error loading notification preferences:', error);
        }
    }
    
    /**
     * Load alert thresholds from database
     */
    async loadAlertThresholds() {
        try {
            const response = await fetch('api/analytics/alert-thresholds.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_thresholds',
                    dashboard_type: this.widgetManager.context.dashboardType,
                    user_id: this.widgetManager.context.userId
                })
            });
            
            const result = await response.json();
            
            if (result.success && result.data) {
                result.data.forEach(threshold => {
                    this.thresholds.set(threshold.metric_key, {
                        warning: threshold.warning_threshold,
                        critical: threshold.critical_threshold,
                        enabled: threshold.enabled,
                        notification_types: JSON.parse(threshold.notification_types || '["visual"]')
                    });
                });
                console.log(`Loaded ${result.data.length} alert thresholds`);
            }
        } catch (error) {
            console.error('Error loading alert thresholds:', error);
        }
    }
    
    /**
     * Setup notification container in DOM
     */
    setupNotificationContainer() {
        // Create main notification container
        let container = document.getElementById('analytics-notifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'analytics-notifications';
            container.className = 'analytics-notifications-container';
            document.body.appendChild(container);
        }
        
        // Create notification areas for different types
        this.createNotificationArea(container, 'toast', 'top-right');
        this.createNotificationArea(container, 'popup', 'center');
        this.createNotificationArea(container, 'visual', 'top-bar');
        
        // Create notification badge
        this.createNotificationBadge();
    }
    
    /**
     * Create notification area for specific type
     */
    createNotificationArea(container, type, position) {
        let area = container.querySelector(`.notification-area-${type}`);
        if (!area) {
            area = document.createElement('div');
            area.className = `notification-area notification-area-${type} position-${position}`;
            container.appendChild(area);
        }
    }
    
    /**
     * Create notification badge for dashboard
     */
    createNotificationBadge() {
        const badge = document.createElement('div');
        badge.id = 'notification-badge';
        badge.className = 'notification-badge';
        badge.innerHTML = `
            <button class="btn btn-outline-secondary position-relative" id="notification-toggle">
                <i class="fas fa-bell"></i>
                <span class="badge bg-danger notification-count" style="display: none;">0</span>
            </button>
        `;
        
        // Find appropriate location for badge
        const navbar = document.querySelector('.navbar, .header, .top-bar');
        if (navbar) {
            navbar.appendChild(badge);
        }
        
        // Setup click handler
        document.getElementById('notification-toggle').addEventListener('click', () => {
            this.showNotificationPanel();
        });
    }
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Listen for widget updates to check thresholds
        this.widgetManager.on('widgetUpdated', (data) => {
            this.checkThresholds(data.widgetId, data.data);
        });
        
        // Listen for real-time updates
        this.widgetManager.on('broadcastUpdate', (update) => {
            if (update.type === 'alert') {
                this.handleAlert(update);
            }
        });
        
        // Listen for widget errors
        this.widgetManager.on('widgetError', (data) => {
            this.showNotification({
                type: 'critical',
                title: 'Widget Error',
                message: `Widget ${data.widgetId} encountered an error: ${data.error.message}`,
                notificationTypes: ['toast', 'visual']
            });
        });
    }
    
    /**
     * Check thresholds for widget data
     */
    checkThresholds(widgetId, data) {
        const widget = this.widgetManager.getWidget(widgetId);
        if (!widget || !widget.kpiConfig) return;
        
        const metric = widget.kpiConfig.metric;
        const threshold = this.thresholds.get(metric);
        
        if (!threshold || !threshold.enabled) return;
        
        const value = data.current_value;
        if (value === null || value === undefined) return;
        
        // Check critical threshold
        if (threshold.critical !== null && value <= threshold.critical) {
            this.triggerAlert(widgetId, metric, 'critical', value, threshold.critical);
        }
        // Check warning threshold
        else if (threshold.warning !== null && value <= threshold.warning) {
            this.triggerAlert(widgetId, metric, 'warning', value, threshold.warning);
        }
        // Clear existing alerts if value is above thresholds
        else {
            this.clearAlert(widgetId, metric);
        }
    }
    
    /**
     * Trigger alert for threshold violation
     */
    triggerAlert(widgetId, metric, level, value, threshold) {
        const alertKey = `${widgetId}-${metric}`;
        
        // Check if alert already exists
        if (this.activeAlerts.has(alertKey)) {
            const existingAlert = this.activeAlerts.get(alertKey);
            if (existingAlert.level === level) {
                return; // Same alert already active
            }
        }
        
        const alert = {
            id: this.generateId(),
            widgetId,
            metric,
            level,
            value,
            threshold,
            timestamp: new Date(),
            acknowledged: false
        };
        
        this.activeAlerts.set(alertKey, alert);
        
        // Get threshold configuration
        const thresholdConfig = this.thresholds.get(metric);
        const notificationTypes = thresholdConfig.notification_types || ['visual'];
        
        // Show notification
        this.showNotification({
            id: alert.id,
            type: level,
            title: `${level.toUpperCase()} Alert`,
            message: `${metric} is ${value} (threshold: ${threshold})`,
            notificationTypes: notificationTypes,
            persistent: level === 'critical',
            actions: [
                {
                    label: 'View Details',
                    action: () => this.showAlertDetails(alert)
                },
                {
                    label: 'Acknowledge',
                    action: () => this.acknowledgeAlert(alert.id)
                }
            ]
        });
    }
    
    /**
     * Clear alert for metric
     */
    clearAlert(widgetId, metric) {
        const alertKey = `${widgetId}-${metric}`;
        
        if (this.activeAlerts.has(alertKey)) {
            const alert = this.activeAlerts.get(alertKey);
            this.activeAlerts.delete(alertKey);
            
            // Show recovery notification
            this.showNotification({
                type: 'success',
                title: 'Alert Resolved',
                message: `${metric} has returned to normal levels`,
                notificationTypes: ['toast'],
                duration: 3000
            });
        }
    }
    
    /**
     * Show notification with specified types
     */
    showNotification(options) {
        const notification = {
            id: options.id || this.generateId(),
            type: options.type || 'info',
            title: options.title || 'Notification',
            message: options.message || '',
            timestamp: new Date(),
            duration: options.duration || this.config.defaultDuration,
            persistent: options.persistent || this.config.persistentTypes.includes(options.type),
            actions: options.actions || [],
            acknowledged: false
        };
        
        // Store notification
        this.notifications.set(notification.id, notification);
        
        // Show in specified notification types
        const notificationTypes = options.notificationTypes || ['toast'];
        
        notificationTypes.forEach(type => {
            switch (type) {
                case 'visual':
                    this.showVisualNotification(notification);
                    break;
                case 'popup':
                    this.showPopupNotification(notification);
                    break;
                case 'toast':
                    this.showToastNotification(notification);
                    break;
                case 'system':
                    this.showSystemNotification(notification);
                    break;
                case 'badge':
                    this.updateNotificationBadge();
                    break;
            }
        });
        
        // Play sound if enabled
        if (this.config.soundEnabled && ['warning', 'critical'].includes(notification.type)) {
            this.playNotificationSound(notification.type);
        }
        
        // Vibrate if enabled and supported
        if (this.config.vibrationEnabled && navigator.vibrate) {
            const pattern = notification.type === 'critical' ? [200, 100, 200] : [100];
            navigator.vibrate(pattern);
        }
        
        // Auto-remove non-persistent notifications
        if (!notification.persistent) {
            setTimeout(() => {
                this.removeNotification(notification.id);
            }, notification.duration);
        }
        
        // Update badge count
        this.updateNotificationBadge();
        
        return notification.id;
    }
    /**
     * Show visual notification in top bar
     */
    showVisualNotification(notification) {
        const area = document.querySelector('.notification-area-visual');
        if (!area) return;
        
        const element = document.createElement('div');
        element.id = `notification-visual-${notification.id}`;
        element.className = `notification-visual alert alert-${this.getBootstrapClass(notification.type)} alert-dismissible`;
        
        const alertConfig = this.alertLevels[notification.type];
        
        element.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="${alertConfig.icon} me-2"></i>
                <div class="flex-grow-1">
                    <strong>${notification.title}</strong>
                    <div class="small">${notification.message}</div>
                </div>
                <div class="notification-actions ms-2">
                    ${this.renderNotificationActions(notification)}
                </div>
                <button type="button" class="btn-close" onclick="analyticsNotificationSystem.removeNotification('${notification.id}')"></button>
            </div>
        `;
        
        area.appendChild(element);
        
        // Add animation
        element.style.opacity = '0';
        element.style.transform = 'translateY(-20px)';
        
        requestAnimationFrame(() => {
            element.style.transition = 'all 0.3s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        });
    }
    
    /**
     * Show popup notification modal
     */
    showPopupNotification(notification) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = `notification-popup-${notification.id}`;
        
        const alertConfig = this.alertLevels[notification.type];
        
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-${this.getBootstrapClass(notification.type)}">
                    <div class="modal-header bg-${this.getBootstrapClass(notification.type)} text-white">
                        <h5 class="modal-title">
                            <i class="${alertConfig.icon} me-2"></i>
                            ${notification.title}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>${notification.message}</p>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            ${notification.timestamp.toLocaleString()}
                        </small>
                    </div>
                    <div class="modal-footer">
                        ${this.renderNotificationActions(notification, true)}
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        // Remove modal after hiding
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }
    
    /**
     * Show toast notification
     */
    showToastNotification(notification) {
        const area = document.querySelector('.notification-area-toast');
        if (!area) return;
        
        const toast = document.createElement('div');
        toast.id = `notification-toast-${notification.id}`;
        toast.className = 'toast';
        toast.setAttribute('role', 'alert');
        
        const alertConfig = this.alertLevels[notification.type];
        
        toast.innerHTML = `
            <div class="toast-header bg-${this.getBootstrapClass(notification.type)} text-white">
                <i class="${alertConfig.icon} me-2"></i>
                <strong class="me-auto">${notification.title}</strong>
                <small>${this.formatTime(notification.timestamp)}</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${notification.message}
                ${notification.actions.length > 0 ? `
                    <div class="mt-2 pt-2 border-top">
                        ${this.renderNotificationActions(notification, false, 'btn-sm')}
                    </div>
                ` : ''}
            </div>
        `;
        
        area.appendChild(toast);
        
        const toastInstance = new bootstrap.Toast(toast, {
            autohide: !notification.persistent,
            delay: notification.duration
        });
        
        toastInstance.show();
        
        // Remove toast after hiding
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
    
    /**
     * Show system notification (browser notification)
     */
    showSystemNotification(notification) {
        if (!this.config.browserNotificationsEnabled || Notification.permission !== 'granted') {
            return;
        }
        
        const systemNotification = new Notification(notification.title, {
            body: notification.message,
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            tag: notification.id,
            requireInteraction: notification.persistent
        });
        
        systemNotification.onclick = () => {
            window.focus();
            this.showNotificationPanel();
            systemNotification.close();
        };
        
        // Auto-close non-persistent notifications
        if (!notification.persistent) {
            setTimeout(() => {
                systemNotification.close();
            }, notification.duration);
        }
    }
    
    /**
     * Render notification actions
     */
    renderNotificationActions(notification, isModal = false, buttonClass = '') {
        if (!notification.actions || notification.actions.length === 0) {
            return '';
        }
        
        return notification.actions.map(action => `
            <button type="button" class="btn btn-outline-primary ${buttonClass} me-1" 
                    onclick="analyticsNotificationSystem.executeAction('${notification.id}', '${action.label}')">
                ${action.label}
            </button>
        `).join('');
    }
    
    /**
     * Execute notification action
     */
    executeAction(notificationId, actionLabel) {
        const notification = this.notifications.get(notificationId);
        if (!notification) return;
        
        const action = notification.actions.find(a => a.label === actionLabel);
        if (action && typeof action.action === 'function') {
            action.action();
        }
    }
    
    /**
     * Update notification badge count
     */
    updateNotificationBadge() {
        const badge = document.querySelector('.notification-count');
        if (!badge) return;
        
        const unacknowledgedCount = Array.from(this.notifications.values())
            .filter(n => !n.acknowledged).length;
        
        if (unacknowledgedCount > 0) {
            badge.textContent = unacknowledgedCount > 99 ? '99+' : unacknowledgedCount;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
    /**
     * Show notification panel
     */
    showNotificationPanel() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-bell me-2"></i>
                            Notifications
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${this.renderNotificationList()}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" onclick="analyticsNotificationSystem.clearAllNotifications()">
                            Clear All
                        </button>
                        <button type="button" class="btn btn-primary" onclick="analyticsNotificationSystem.showNotificationSettings()">
                            Settings
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }
    
    /**
     * Render notification list
     */
    renderNotificationList() {
        const notifications = Array.from(this.notifications.values())
            .sort((a, b) => b.timestamp - a.timestamp);
        
        if (notifications.length === 0) {
            return '<p class="text-muted text-center p-4">No notifications</p>';
        }
        
        return `
            <div class="notification-list">
                ${notifications.map(notification => this.renderNotificationItem(notification)).join('')}
            </div>
        `;
    }
    
    /**
     * Render individual notification item
     */
    renderNotificationItem(notification) {
        const alertConfig = this.alertLevels[notification.type];
        
        return `
            <div class="notification-item ${notification.acknowledged ? 'acknowledged' : ''} border-start border-${this.getBootstrapClass(notification.type)} border-3 p-3 mb-2">
                <div class="d-flex align-items-start">
                    <div class="notification-icon me-3">
                        <i class="${alertConfig.icon}" style="color: ${alertConfig.color}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${notification.title}</h6>
                        <p class="mb-1">${notification.message}</p>
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            ${notification.timestamp.toLocaleString()}
                        </small>
                    </div>
                    <div class="notification-item-actions">
                        ${!notification.acknowledged ? `
                            <button class="btn btn-sm btn-outline-primary me-1" 
                                    onclick="analyticsNotificationSystem.acknowledgeNotification('${notification.id}')">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : ''}
                        <button class="btn btn-sm btn-outline-danger" 
                                onclick="analyticsNotificationSystem.removeNotification('${notification.id}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Show notification settings modal
     */
    showNotificationSettings() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-cog me-2"></i>
                            Notification Settings
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${this.renderNotificationSettings()}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="analyticsNotificationSystem.saveNotificationSettings()">
                            Save Settings
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }
    
    /**
     * Render notification settings form
     */
    renderNotificationSettings() {
        return `
            <div class="notification-settings">
                <div class="row">
                    <div class="col-md-6">
                        <h6>General Settings</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="soundEnabled" ${this.config.soundEnabled ? 'checked' : ''}>
                            <label class="form-check-label" for="soundEnabled">Enable sound notifications</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="vibrationEnabled" ${this.config.vibrationEnabled ? 'checked' : ''}>
                            <label class="form-check-label" for="vibrationEnabled">Enable vibration (mobile)</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="browserNotificationsEnabled" ${this.config.browserNotificationsEnabled ? 'checked' : ''}>
                            <label class="form-check-label" for="browserNotificationsEnabled">Enable browser notifications</label>
                        </div>
                        
                        <div class="mb-3">
                            <label for="defaultDuration" class="form-label">Default notification duration (ms)</label>
                            <input type="number" class="form-control" id="defaultDuration" value="${this.config.defaultDuration}" min="1000" max="30000" step="1000">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Alert Thresholds</h6>
                        ${this.renderThresholdSettings()}
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Dashboard-Specific Preferences</h6>
                    ${this.renderDashboardPreferences()}
                </div>
            </div>
        `;
    }
    
    /**
     * Render threshold settings
     */
    renderThresholdSettings() {
        const thresholds = Array.from(this.thresholds.entries());
        
        if (thresholds.length === 0) {
            return '<p class="text-muted">No thresholds configured</p>';
        }
        
        return thresholds.map(([metric, config]) => `
            <div class="threshold-setting mb-3 p-2 border rounded">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="threshold-${metric}" ${config.enabled ? 'checked' : ''}>
                    <label class="form-check-label fw-bold" for="threshold-${metric}">${metric}</label>
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="form-label small">Warning</label>
                        <input type="number" class="form-control form-control-sm" value="${config.warning || ''}" data-metric="${metric}" data-type="warning">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Critical</label>
                        <input type="number" class="form-control form-control-sm" value="${config.critical || ''}" data-metric="${metric}" data-type="critical">
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small">Notification Types</label>
                    <div class="d-flex gap-2 flex-wrap">
                        ${Object.keys(this.notificationTypes).map(type => `
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="${metric}-${type}" 
                                       ${config.notification_types.includes(type) ? 'checked' : ''} 
                                       data-metric="${metric}" data-notification-type="${type}">
                                <label class="form-check-label small" for="${metric}-${type}">${type}</label>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    /**
     * Render dashboard-specific preferences
     */
    renderDashboardPreferences() {
        const dashboardTypes = ['admin_main', 'center_management', 'members_management', 'contributions_management'];
        
        return dashboardTypes.map(dashboardType => `
            <div class="dashboard-preference mb-3 p-2 border rounded">
                <h6 class="mb-2">${this.formatDashboardName(dashboardType)}</h6>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label small">Preferred notification types</label>
                        <div class="d-flex gap-2 flex-wrap">
                            ${Object.keys(this.notificationTypes).map(type => `
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="pref-${dashboardType}-${type}" 
                                           ${this.getPreference(`${dashboardType}.notification_types`, []).includes(type) ? 'checked' : ''}>
                                    <label class="form-check-label small" for="pref-${dashboardType}-${type}">${type}</label>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="pref-${dashboardType}-enabled" 
                                   ${this.getPreference(`${dashboardType}.enabled`, true) ? 'checked' : ''}>
                            <label class="form-check-label" for="pref-${dashboardType}-enabled">Enable notifications for this dashboard</label>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }
    /**
     * Save notification settings
     */
    async saveNotificationSettings() {
        try {
            // Collect general settings
            this.config.soundEnabled = document.getElementById('soundEnabled').checked;
            this.config.vibrationEnabled = document.getElementById('vibrationEnabled').checked;
            this.config.browserNotificationsEnabled = document.getElementById('browserNotificationsEnabled').checked;
            this.config.defaultDuration = parseInt(document.getElementById('defaultDuration').value);
            
            // Collect threshold settings
            const thresholdUpdates = [];
            this.thresholds.forEach((config, metric) => {
                const enabled = document.getElementById(`threshold-${metric}`).checked;
                const warning = document.querySelector(`input[data-metric="${metric}"][data-type="warning"]`).value;
                const critical = document.querySelector(`input[data-metric="${metric}"][data-type="critical"]`).value;
                
                const notificationTypes = [];
                Object.keys(this.notificationTypes).forEach(type => {
                    const checkbox = document.querySelector(`input[data-metric="${metric}"][data-notification-type="${type}"]`);
                    if (checkbox && checkbox.checked) {
                        notificationTypes.push(type);
                    }
                });
                
                thresholdUpdates.push({
                    metric,
                    enabled,
                    warning: warning ? parseFloat(warning) : null,
                    critical: critical ? parseFloat(critical) : null,
                    notification_types: notificationTypes
                });
            });
            
            // Collect dashboard preferences
            const preferenceUpdates = [];
            const dashboardTypes = ['admin_main', 'center_management', 'members_management', 'contributions_management'];
            
            dashboardTypes.forEach(dashboardType => {
                const enabled = document.getElementById(`pref-${dashboardType}-enabled`).checked;
                const notificationTypes = [];
                
                Object.keys(this.notificationTypes).forEach(type => {
                    const checkbox = document.getElementById(`pref-${dashboardType}-${type}`);
                    if (checkbox && checkbox.checked) {
                        notificationTypes.push(type);
                    }
                });
                
                preferenceUpdates.push({
                    dashboard_type: dashboardType,
                    enabled,
                    notification_types: notificationTypes
                });
            });
            
            // Save to database
            const response = await fetch('api/analytics/notification-preferences.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_settings',
                    user_id: this.widgetManager.context.userId,
                    config: this.config,
                    thresholds: thresholdUpdates,
                    preferences: preferenceUpdates
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update local data
                thresholdUpdates.forEach(update => {
                    this.thresholds.set(update.metric, {
                        warning: update.warning,
                        critical: update.critical,
                        enabled: update.enabled,
                        notification_types: update.notification_types
                    });
                });
                
                // Request browser notification permission if enabled
                if (this.config.browserNotificationsEnabled) {
                    await this.requestNotificationPermission();
                }
                
                // Show success notification
                this.showNotification({
                    type: 'success',
                    title: 'Settings Saved',
                    message: 'Notification settings have been updated successfully',
                    notificationTypes: ['toast']
                });
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
                if (modal) modal.hide();
                
            } else {
                throw new Error(result.error || 'Failed to save settings');
            }
            
        } catch (error) {
            console.error('Error saving notification settings:', error);
            this.showNotification({
                type: 'critical',
                title: 'Save Failed',
                message: 'Failed to save notification settings: ' + error.message,
                notificationTypes: ['toast']
            });
        }
    }
    
    /**
     * Request browser notification permission
     */
    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            console.warn('Browser notifications not supported');
            return false;
        }
        
        if (Notification.permission === 'granted') {
            return true;
        }
        
        if (Notification.permission === 'denied') {
            console.warn('Browser notifications denied');
            return false;
        }
        
        const permission = await Notification.requestPermission();
        return permission === 'granted';
    }
    
    /**
     * Acknowledge notification
     */
    acknowledgeNotification(notificationId) {
        const notification = this.notifications.get(notificationId);
        if (notification) {
            notification.acknowledged = true;
            this.updateNotificationBadge();
            
            // Update UI
            const element = document.getElementById(`notification-visual-${notificationId}`);
            if (element) {
                element.classList.add('acknowledged');
            }
        }
    }
    
    /**
     * Acknowledge alert
     */
    acknowledgeAlert(alertId) {
        const notification = this.notifications.get(alertId);
        if (notification) {
            this.acknowledgeNotification(alertId);
            
            // Show acknowledgment confirmation
            this.showNotification({
                type: 'info',
                title: 'Alert Acknowledged',
                message: 'Alert has been acknowledged and will not repeat',
                notificationTypes: ['toast'],
                duration: 2000
            });
        }
    }
    
    /**
     * Remove notification
     */
    removeNotification(notificationId) {
        // Remove from storage
        this.notifications.delete(notificationId);
        
        // Remove from DOM
        const elements = document.querySelectorAll(`[id*="notification-"][id*="${notificationId}"]`);
        elements.forEach(element => {
            element.style.transition = 'all 0.3s ease';
            element.style.opacity = '0';
            element.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                element.remove();
            }, 300);
        });
        
        // Update badge
        this.updateNotificationBadge();
    }
    
    /**
     * Clear all notifications
     */
    clearAllNotifications() {
        // Clear storage
        this.notifications.clear();
        this.activeAlerts.clear();
        
        // Clear DOM
        document.querySelectorAll('[id*="notification-"]').forEach(element => {
            element.remove();
        });
        
        // Update badge
        this.updateNotificationBadge();
        
        // Show confirmation
        this.showNotification({
            type: 'info',
            title: 'Notifications Cleared',
            message: 'All notifications have been cleared',
            notificationTypes: ['toast'],
            duration: 2000
        });
    }
    
    /**
     * Show alert details
     */
    showAlertDetails(alert) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-${this.getBootstrapClass(alert.level)} text-white">
                        <h5 class="modal-title">
                            <i class="${this.alertLevels[alert.level].icon} me-2"></i>
                            Alert Details
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-sm">
                            <tr><td><strong>Widget:</strong></td><td>${alert.widgetId}</td></tr>
                            <tr><td><strong>Metric:</strong></td><td>${alert.metric}</td></tr>
                            <tr><td><strong>Level:</strong></td><td><span class="badge bg-${this.getBootstrapClass(alert.level)}">${alert.level.toUpperCase()}</span></td></tr>
                            <tr><td><strong>Current Value:</strong></td><td>${alert.value}</td></tr>
                            <tr><td><strong>Threshold:</strong></td><td>${alert.threshold}</td></tr>
                            <tr><td><strong>Triggered:</strong></td><td>${alert.timestamp.toLocaleString()}</td></tr>
                            <tr><td><strong>Status:</strong></td><td>${alert.acknowledged ? 'Acknowledged' : 'Active'}</td></tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        ${!alert.acknowledged ? `
                            <button type="button" class="btn btn-primary" onclick="analyticsNotificationSystem.acknowledgeAlert('${alert.id}'); bootstrap.Modal.getInstance(this.closest('.modal')).hide();">
                                Acknowledge Alert
                            </button>
                        ` : ''}
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }
    
    /**
     * Handle alert from real-time update
     */
    handleAlert(update) {
        this.showNotification({
            type: update.level || 'warning',
            title: update.title || 'System Alert',
            message: update.message || 'An alert condition has been detected',
            notificationTypes: update.notification_types || ['toast', 'visual'],
            persistent: update.level === 'critical'
        });
    }
    
    /**
     * Play notification sound
     */
    playNotificationSound(type) {
        try {
            const audio = new Audio();
            
            switch (type) {
                case 'critical':
                    // High-pitched urgent sound
                    audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTuR2O/Eeyw=';
                    break;
                case 'warning':
                    // Medium-pitched alert sound
                    audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTuR2O/Eeyw=';
                    break;
                default:
                    // Gentle notification sound
                    audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTuR2O/Eeyw=';
            }
            
            audio.volume = 0.3;
            audio.play().catch(e => console.log('Could not play notification sound:', e));
        } catch (error) {
            console.log('Error playing notification sound:', error);
        }
    }
    
    /**
     * Utility methods
     */
    generateId() {
        return 'notification_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    getBootstrapClass(type) {
        const mapping = {
            info: 'info',
            warning: 'warning',
            critical: 'danger',
            success: 'success'
        };
        return mapping[type] || 'info';
    }
    
    formatTime(timestamp) {
        return timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    formatDashboardName(dashboardType) {
        return dashboardType.split('_').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }
    
    getPreference(key, defaultValue = null) {
        return this.preferences.get(key) || defaultValue;
    }
    
    /**
     * Get notification statistics
     */
    getStatistics() {
        const notifications = Array.from(this.notifications.values());
        
        return {
            total: notifications.length,
            unacknowledged: notifications.filter(n => !n.acknowledged).length,
            byType: {
                info: notifications.filter(n => n.type === 'info').length,
                warning: notifications.filter(n => n.type === 'warning').length,
                critical: notifications.filter(n => n.type === 'critical').length,
                success: notifications.filter(n => n.type === 'success').length
            },
            activeAlerts: this.activeAlerts.size
        };
    }
}

// Export for global use
window.AnalyticsNotificationSystem = AnalyticsNotificationSystem;