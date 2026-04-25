/**
 * System Administrator Dashboard JavaScript
 * Comprehensive system management interface with exclusive administrative controls
 */

class SystemAdministratorDashboard {
    constructor() {
        this.currentUser = null;
        this.systemMetrics = {};
        this.refreshInterval = null;
        this.charts = {};
        this.currentSection = 'overview';
        
        this.init();
    }
    
    async init() {
        try {
            await this.checkAuthentication();
            await this.loadSystemMetrics();
            await this.initializeCharts();
            this.setupEventListeners();
            this.startAutoRefresh();
            this.showSection('overview');
        } catch (error) {
            console.error('Dashboard initialization error:', error);
            this.showError('Failed to initialize system dashboard');
        }
    }
    
    async checkAuthentication() {
        try {
            const response = await fetch('api/auth/system-admin-auth.php?action=session');
            const data = await response.json();
            
            if (!data.success) {
                window.location.href = 'system-admin-login.html';
                return;
            }
            
            this.currentUser = data.user;
            
            // Verify system administrator role
            if (this.currentUser.role !== 'system_admin') {
                this.showError('Access denied: System Administrator privileges required');
                setTimeout(() => {
                    window.location.href = 'admin-login.html';
                }, 3000);
                return;
            }
            
            // Update UI with user info
            document.getElementById('adminName').textContent = this.currentUser.full_name || this.currentUser.username;
            document.getElementById('adminCode').textContent = this.currentUser.admin_code || 'SYS001';
            
            // Check security clearance
            if (this.currentUser.security_clearance === 'critical') {
                this.enableCriticalOperations();
            }
            
        } catch (error) {
            console.error('Authentication check failed:', error);
            window.location.href = 'system-admin-login.html';
        }
    }
    
    async loadSystemMetrics() {
        try {
            const response = await fetch('api/system/system-overview.php?action=metrics');
            const data = await response.json();
            
            if (data.success) {
                this.systemMetrics = data.metrics;
                this.updateSystemStatus();
                this.updateMetricCards();
                this.loadRecentActivities();
            }
        } catch (error) {
            console.error('Failed to load system metrics:', error);
        }
    }
    
    updateSystemStatus() {
        const metrics = this.systemMetrics;
        
        // Update system status
        document.getElementById('systemStatus').textContent = metrics.system_status || 'Online';
        document.getElementById('systemUptime').textContent = metrics.uptime || '99.9%';
        
        // Update user counts
        document.getElementById('activeUsers').textContent = metrics.active_users || '0';
        document.getElementById('totalUsers').textContent = metrics.total_users || '0';
        
        // Update database info
        document.getElementById('dbSize').textContent = metrics.database_size || '0 MB';
        document.getElementById('dbGrowth').textContent = metrics.database_growth || '+0%';
        
        // Update alert count
        document.getElementById('alertCount').textContent = metrics.alert_count || '0';
        
        // Update system info
        document.getElementById('systemInfo').textContent = 
            `${metrics.system_name || 'WDB Management System'} v${metrics.system_version || '1.0.0'}`;
    }
    
    updateMetricCards() {
        // Update status indicators based on system health
        const statusIndicators = document.querySelectorAll('.status-indicator');
        statusIndicators.forEach(indicator => {
            const status = this.systemMetrics.system_status;
            indicator.className = 'status-indicator ' + 
                (status === 'Online' ? 'status-online' : 
                 status === 'Warning' ? 'status-warning' : 
                 status === 'Error' ? 'status-error' : 'status-offline');
        });
    }
    
    async loadRecentActivities() {
        try {
            const response = await fetch('api/system/system-overview.php?action=recent_activities');
            const data = await response.json();
            
            if (data.success) {
                this.displayRecentActivities(data.activities);
            }
        } catch (error) {
            console.error('Failed to load recent activities:', error);
        }
    }
    
    displayRecentActivities(activities) {
        const container = document.getElementById('recentActivities');
        
        if (!activities || activities.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-center py-4">No recent activities</p>';
            return;
        }
        
        container.innerHTML = activities.map(activity => `
            <div class="flex items-center justify-between p-3 bg-white bg-opacity-5 rounded-lg">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center">
                        <i class="fas ${this.getActivityIcon(activity.operation_type)} text-sm"></i>
                    </div>
                    <div>
                        <p class="font-medium">${activity.operation_type.replace('_', ' ').toUpperCase()}</p>
                        <p class="text-sm text-gray-300">${activity.operation_details || 'System operation'}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-300">${this.formatDate(activity.created_at)}</p>
                    <span class="text-xs px-2 py-1 rounded ${activity.success ? 'bg-green-500' : 'bg-red-500'}">
                        ${activity.success ? 'Success' : 'Failed'}
                    </span>
                </div>
            </div>
        `).join('');
    }
    
    getActivityIcon(operationType) {
        const icons = {
            'config_update': 'fa-cogs',
            'database_backup': 'fa-database',
            'user_management': 'fa-users',
            'security_event': 'fa-shield-alt',
            'system_maintenance': 'fa-wrench',
            'default': 'fa-info-circle'
        };
        return icons[operationType] || icons.default;
    }
    
    initializeCharts() {
        this.initPerformanceChart();
        this.initResourceChart();
        this.initHealthChart();
    }
    
    initPerformanceChart() {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) return;
        
        this.charts.performance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                datasets: [{
                    label: 'CPU Usage (%)',
                    data: [45, 52, 48, 61, 55, 49],
                    borderColor: '#60a5fa',
                    backgroundColor: 'rgba(96, 165, 250, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Memory Usage (%)',
                    data: [38, 42, 45, 48, 44, 41],
                    borderColor: '#34d399',
                    backgroundColor: 'rgba(52, 211, 153, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#ffffff' }
                    }
                },
                scales: {
                    x: { 
                        ticks: { color: '#ffffff' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: { 
                        ticks: { color: '#ffffff' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                }
            }
        });
    }
    
    initResourceChart() {
        const ctx = document.getElementById('resourceChart');
        if (!ctx) return;
        
        this.charts.resource = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Available'],
                datasets: [{
                    data: [65, 35],
                    backgroundColor: ['#f59e0b', '#374151'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#ffffff' }
                    }
                }
            }
        });
    }
    
    initHealthChart() {
        const ctx = document.getElementById('healthChart');
        if (!ctx) return;
        
        this.charts.health = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ['CPU', 'Memory', 'Disk', 'Network', 'Database', 'Security'],
                datasets: [{
                    label: 'System Health',
                    data: [85, 78, 92, 88, 95, 90],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    pointBackgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#ffffff' }
                    }
                },
                scales: {
                    r: {
                        ticks: { color: '#ffffff' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        pointLabels: { color: '#ffffff' }
                    }
                }
            }
        });
    }
    
    setupEventListeners() {
        // Navigation event listeners are handled by onclick attributes
        
        // Auto-refresh toggle
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopAutoRefresh();
            } else {
                this.startAutoRefresh();
            }
        });
    }
    
    showSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.section').forEach(section => {
            section.classList.add('hidden');
        });
        
        // Show selected section
        const targetSection = document.getElementById(`${sectionName}-section`);
        if (targetSection) {
            targetSection.classList.remove('hidden');
        }
        
        // Update navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const activeNav = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
        if (activeNav) {
            activeNav.classList.add('active');
        }
        
        this.currentSection = sectionName;
        
        // Load section-specific data
        this.loadSectionData(sectionName);
    }
    
    async loadSectionData(sectionName) {
        switch (sectionName) {
            case 'configuration':
                await this.loadConfiguration();
                break;
            case 'database':
                await this.loadDatabaseStatus();
                break;
            case 'security':
                await this.loadSecurityOverview();
                break;
            case 'monitoring':
                await this.loadMonitoringData();
                break;
            case 'backup':
                await this.loadBackupManagement();
                break;
            case 'users':
                await this.loadUserManagement();
                break;
            case 'audit':
                await this.loadAuditLogs();
                break;
            case 'payments':
                await this.loadPaymentMethods();
                break;
        }
    }
    
    async loadConfiguration() {
        try {
            const response = await fetch('api/system/system-configuration.php?action=get_all');
            const data = await response.json();
            
            if (data.success) {
                this.displayConfiguration(data.configuration);
            }
        } catch (error) {
            console.error('Failed to load configuration:', error);
        }
    }
    
    displayConfiguration(config) {
        const container = document.getElementById('configSections');
        
        // Group configuration by category
        const categories = {};
        config.forEach(item => {
            if (!categories[item.category]) {
                categories[item.category] = [];
            }
            categories[item.category].push(item);
        });
        
        container.innerHTML = Object.keys(categories).map(category => `
            <div class="config-section">
                <h4 class="text-lg font-semibold mb-4 capitalize">${category.replace('_', ' ')}</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    ${categories[category].map(item => `
                        <div class="bg-white bg-opacity-5 p-4 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <label class="font-medium">${item.config_key.replace(/[._]/g, ' ').toUpperCase()}</label>
                                ${item.is_sensitive ? '<i class="fas fa-lock text-yellow-400" title="Sensitive"></i>' : ''}
                            </div>
                            <input type="${this.getInputType(item.config_type)}" 
                                   value="${item.is_sensitive ? '••••••••' : item.config_value}" 
                                   class="w-full p-2 bg-white bg-opacity-10 rounded border border-white border-opacity-20 text-white"
                                   ${item.is_sensitive ? 'readonly' : ''}
                                   onchange="dashboard.updateConfig('${item.config_key}', this.value, '${item.config_type}')">
                            <p class="text-xs text-gray-400 mt-1">${item.description || ''}</p>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
    }
    
    getInputType(configType) {
        switch (configType) {
            case 'boolean': return 'checkbox';
            case 'integer': return 'number';
            case 'encrypted': return 'password';
            default: return 'text';
        }
    }
    
    async updateConfig(configKey, configValue, configType) {
        try {
            const response = await fetch('api/system/system-configuration.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    config_key: configKey,
                    config_value: configValue,
                    config_type: configType
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Configuration updated successfully');
            } else {
                this.showError(data.error || 'Failed to update configuration');
            }
        } catch (error) {
            console.error('Failed to update configuration:', error);
            this.showError('Failed to update configuration');
        }
    }
    
    async loadDatabaseStatus() {
        try {
            const response = await fetch('api/system/database-management.php?action=status');
            const data = await response.json();
            
            if (data.success) {
                this.displayDatabaseStatus(data.status);
            }
        } catch (error) {
            console.error('Failed to load database status:', error);
        }
    }
    
    displayDatabaseStatus(status) {
        const container = document.getElementById('databaseStatus');
        
        container.innerHTML = `
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span>Database Size:</span>
                    <span class="font-semibold">${status.size || 'Unknown'}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span>Total Tables:</span>
                    <span class="font-semibold">${status.table_count || 0}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span>Total Records:</span>
                    <span class="font-semibold">${status.record_count || 0}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span>Last Backup:</span>
                    <span class="font-semibold">${status.last_backup || 'Never'}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span>Status:</span>
                    <span class="font-semibold text-green-400">${status.status || 'Online'}</span>
                </div>
            </div>
        `;
    }
    
    async loadSecurityOverview() {
        try {
            const response = await fetch('api/system/security-overview.php?action=overview');
            const data = await response.json();
            
            if (data.success) {
                this.displaySecurityOverview(data.security);
            }
        } catch (error) {
            console.error('Failed to load security overview:', error);
        }
    }
    
    displaySecurityOverview(security) {
        const container = document.getElementById('securityOverview');
        
        container.innerHTML = `
            <div class="bg-green-500 bg-opacity-20 p-4 rounded-lg text-center">
                <i class="fas fa-shield-alt text-2xl text-green-400 mb-2"></i>
                <h4 class="font-semibold">Security Score</h4>
                <p class="text-2xl font-bold text-green-400">${security.score || 95}%</p>
            </div>
            <div class="bg-blue-500 bg-opacity-20 p-4 rounded-lg text-center">
                <i class="fas fa-users-shield text-2xl text-blue-400 mb-2"></i>
                <h4 class="font-semibold">Active Sessions</h4>
                <p class="text-2xl font-bold text-blue-400">${security.active_sessions || 0}</p>
            </div>
            <div class="bg-yellow-500 bg-opacity-20 p-4 rounded-lg text-center">
                <i class="fas fa-exclamation-triangle text-2xl text-yellow-400 mb-2"></i>
                <h4 class="font-semibold">Security Alerts</h4>
                <p class="text-2xl font-bold text-yellow-400">${security.alerts || 0}</p>
            </div>
        `;
        
        // Load security alerts
        const alertsContainer = document.getElementById('securityAlerts');
        if (security.recent_alerts && security.recent_alerts.length > 0) {
            alertsContainer.innerHTML = security.recent_alerts.map(alert => `
                <div class="flex items-center justify-between p-3 bg-red-500 bg-opacity-10 border-l-4 border-red-500 rounded">
                    <div>
                        <p class="font-medium text-red-400">${alert.type}</p>
                        <p class="text-sm text-gray-300">${alert.message}</p>
                    </div>
                    <span class="text-xs text-gray-400">${this.formatDate(alert.created_at)}</span>
                </div>
            `).join('');
        } else {
            alertsContainer.innerHTML = '<p class="text-gray-400 text-center py-4">No security alerts</p>';
        }
    }
    
    async loadMonitoringData() {
        // Monitoring data loading implementation
        console.log('Loading monitoring data...');
    }
    
    async loadBackupManagement() {
        // Backup management loading implementation
        console.log('Loading backup management...');
    }
    
    async loadUserManagement() {
        // User management loading implementation
        console.log('Loading user management...');
    }
    
    async loadAuditLogs() {
        try {
            const response = await fetch('api/system/audit-logs.php?action=recent');
            const data = await response.json();
            
            if (data.success) {
                this.displayAuditLogs(data.logs);
            }
        } catch (error) {
            console.error('Failed to load audit logs:', error);
        }
    }
    
    displayAuditLogs(logs) {
        const container = document.getElementById('auditLogs');
        
        if (!logs || logs.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-center py-4">No audit logs available</p>';
            return;
        }
        
        container.innerHTML = `
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-white bg-opacity-10">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Timestamp</th>
                            <th class="px-4 py-3 font-semibold">User</th>
                            <th class="px-4 py-3 font-semibold">Operation</th>
                            <th class="px-4 py-3 font-semibold">Resource</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Security Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${logs.map(log => `
                            <tr class="border-b border-white border-opacity-10 hover:bg-white hover:bg-opacity-5">
                                <td class="px-4 py-3 text-sm">${this.formatDate(log.created_at)}</td>
                                <td class="px-4 py-3 text-sm">${log.user_role}</td>
                                <td class="px-4 py-3 text-sm">${log.operation_type}</td>
                                <td class="px-4 py-3 text-sm">${log.resource_affected || '-'}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 rounded text-xs ${log.success ? 'bg-green-500' : 'bg-red-500'}">
                                        ${log.success ? 'Success' : 'Failed'}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 rounded text-xs ${this.getSecurityLevelClass(log.security_level)}">
                                        ${log.security_level}
                                    </span>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    getSecurityLevelClass(level) {
        switch (level) {
            case 'critical': return 'bg-red-600';
            case 'high': return 'bg-orange-600';
            case 'medium': return 'bg-yellow-600';
            case 'low': return 'bg-blue-600';
            default: return 'bg-gray-600';
        }
    }
    
    // System Operations
    async optimizeDatabase() {
        if (!confirm('Are you sure you want to optimize the database? This may take several minutes.')) {
            return;
        }
        
        this.showLoading(true);
        
        try {
            const response = await fetch('api/system/database-management.php?action=optimize', {
                method: 'POST'
            });
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Database optimization completed successfully');
            } else {
                this.showError(data.error || 'Database optimization failed');
            }
        } catch (error) {
            console.error('Database optimization error:', error);
            this.showError('Database optimization failed');
        } finally {
            this.showLoading(false);
        }
    }
    
    async backupDatabase() {
        this.showLoading(true);
        
        try {
            const response = await fetch('api/system/database-management.php?action=backup', {
                method: 'POST'
            });
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Database backup created successfully');
            } else {
                this.showError(data.error || 'Database backup failed');
            }
        } catch (error) {
            console.error('Database backup error:', error);
            this.showError('Database backup failed');
        } finally {
            this.showLoading(false);
        }
    }
    
    // Emergency Operations
    showEmergencyPanel() {
        document.getElementById('emergencyModal').classList.add('active');
    }
    
    closeEmergencyPanel() {
        document.getElementById('emergencyModal').classList.remove('active');
    }
    
    async emergencyShutdown() {
        const confirmation = prompt('Type "EMERGENCY SHUTDOWN" to confirm this critical operation:');
        if (confirmation !== 'EMERGENCY SHUTDOWN') {
            this.showError('Emergency shutdown cancelled - incorrect confirmation');
            return;
        }
        
        this.showLoading(true);
        
        try {
            const response = await fetch('api/system/emergency-operations.php?action=shutdown', {
                method: 'POST'
            });
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Emergency shutdown initiated');
                setTimeout(() => {
                    window.location.href = 'maintenance.html';
                }, 3000);
            } else {
                this.showError(data.error || 'Emergency shutdown failed');
            }
        } catch (error) {
            console.error('Emergency shutdown error:', error);
            this.showError('Emergency shutdown failed');
        } finally {
            this.showLoading(false);
        }
    }
    
    // Auto-refresh functionality
    startAutoRefresh() {
        this.refreshInterval = setInterval(() => {
            this.loadSystemMetrics();
        }, 30000); // Refresh every 30 seconds
    }
    
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    refreshActivities() {
        this.loadRecentActivities();
    }
    
    enableCriticalOperations() {
        // Enable critical system operations for users with critical security clearance
        console.log('Critical operations enabled for user with critical security clearance');
    }
    
    // Utility methods
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }
    
    showLoading(show) {
        document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
    }
    
    showSuccess(message) {
        // Implement success notification
        alert(message); // Replace with better notification system
    }
    
    showError(message) {
        // Implement error notification
        alert('Error: ' + message); // Replace with better notification system
    }
}

// Global functions for onclick handlers
function showSection(sectionName) {
    dashboard.showSection(sectionName);
}

function showEmergencyPanel() {
    dashboard.showEmergencyPanel();
}

function closeEmergencyPanel() {
    dashboard.closeEmergencyPanel();
}

function optimizeDatabase() {
    dashboard.optimizeDatabase();
}

function backupDatabase() {
    dashboard.backupDatabase();
}

function emergencyShutdown() {
    dashboard.emergencyShutdown();
}

function refreshActivities() {
    dashboard.refreshActivities();
}

async function logout() {
    try {
        const response = await fetch('api/auth/system-admin-auth.php?action=logout', { 
            method: 'POST' 
        });
        const data = await response.json();
        
        if (data.success) {
            window.location.href = 'system-admin-login.html';
        } else {
            alert('Logout failed');
        }
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = 'system-admin-login.html';
    }
}

// Initialize dashboard when page loads
let dashboard;
document.addEventListener('DOMContentLoaded', () => {
    dashboard = new SystemAdministratorDashboard();
});
    // Payment Methods Management
    async loadPaymentMethods() {
        try {
            // Load payment stats
            const statsResponse = await fetch('api/payments/payment-methods-admin.php?action=stats');
            const statsData = await statsResponse.json();
            
            if (statsData.success) {
                const stats = statsData.data;
                document.getElementById('totalPaymentMethods').textContent = stats.total_methods;
                document.getElementById('activePaymentMethods').textContent = stats.active_methods;
                document.getElementById('monthlyTransactions').textContent = stats.monthly_transactions;
                document.getElementById('monthlyAmount').textContent = new Intl.NumberFormat('en-ET', {
                    style: 'currency',
                    currency: 'ETB'
                }).format(stats.monthly_amount);
            }
            
            // Load payment methods list
            const methodsResponse = await fetch('api/payments/payment-methods-admin.php?action=list');
            const methodsData = await methodsResponse.json();
            
            if (methodsData.success) {
                this.displayPaymentMethods(methodsData.data);
            }
            
            // Load recent transactions
            const transactionsResponse = await fetch('api/payments/payment-methods-admin.php?action=transactions');
            const transactionsData = await transactionsResponse.json();
            
            if (transactionsData.success) {
                this.displayRecentTransactions(transactionsData.data);
            }
            
            // Load payment gateways
            const gatewaysResponse = await fetch('api/payments/payment-methods-admin.php?action=gateways');
            const gatewaysData = await gatewaysResponse.json();
            
            if (gatewaysData.success) {
                this.displayPaymentGateways(gatewaysData.data);
            }
            
        } catch (error) {
            console.error('Error loading payment methods:', error);
            this.showError('Failed to load payment methods data');
        }
    }
    
    displayPaymentMethods(methods) {
        const container = document.getElementById('paymentMethodsList');
        
        if (methods.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-center py-4">No payment methods configured</p>';
            return;
        }
        
        container.innerHTML = methods.map(method => `
            <div class="bg-gray-800 rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-${this.getPaymentMethodIcon(method.type)} text-white"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white">${method.name}</h4>
                        <p class="text-sm text-gray-400">${method.type.replace('_', ' ').toUpperCase()}</p>
                        <p class="text-xs text-gray-500">Used ${method.usage_count} times</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-1 rounded text-xs ${method.is_active ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}">
                        ${method.is_active ? 'Active' : 'Inactive'}
                    </span>
                    <button onclick="togglePaymentMethodStatus(${method.id})" class="text-blue-400 hover:text-blue-300">
                        <i class="fas fa-toggle-${method.is_active ? 'on' : 'off'}"></i>
                    </button>
                    <button onclick="editPaymentMethod(${method.id})" class="text-yellow-400 hover:text-yellow-300">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    displayRecentTransactions(transactions) {
        const container = document.getElementById('recentTransactions');
        
        if (transactions.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-center py-4">No recent transactions</p>';
            return;
        }
        
        container.innerHTML = transactions.map(transaction => `
            <div class="bg-gray-800 rounded-lg p-3">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <p class="font-semibold text-white">${transaction.member_name || 'Unknown Member'}</p>
                        <p class="text-sm text-gray-400">${transaction.center_name || 'No Center'}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-400">${new Intl.NumberFormat('en-ET', {
                            style: 'currency',
                            currency: 'ETB'
                        }).format(transaction.amount)}</p>
                        <p class="text-xs text-gray-500">${new Date(transaction.payment_date).toLocaleDateString()}</p>
                    </div>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-blue-400">${transaction.payment_method || 'Unknown Method'}</span>
                    <span class="px-2 py-1 rounded text-xs ${this.getStatusColor(transaction.payment_status)}">
                        ${transaction.payment_status.toUpperCase()}
                    </span>
                </div>
            </div>
        `).join('');
    }
    
    displayPaymentGateways(gateways) {
        const container = document.getElementById('paymentGatewaysList');
        
        if (gateways.length === 0) {
            container.innerHTML = '<p class="text-gray-400 text-center py-4 col-span-full">No payment gateways configured</p>';
            return;
        }
        
        container.innerHTML = gateways.map(gateway => `
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-white">${gateway.name}</h4>
                    <span class="w-3 h-3 rounded-full ${gateway.is_active ? 'bg-green-500' : 'bg-red-500'}"></span>
                </div>
                <p class="text-sm text-gray-400 mb-2">${gateway.provider.toUpperCase()}</p>
                <p class="text-xs text-gray-500 mb-3">${gateway.is_sandbox ? 'Sandbox Mode' : 'Production Mode'}</p>
                <div class="flex space-x-2">
                    <button onclick="testPaymentGateway(${gateway.id})" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-check mr-1"></i>Test
                    </button>
                    <button onclick="configureGateway(${gateway.id})" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-cog mr-1"></i>Config
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    getPaymentMethodIcon(type) {
        const icons = {
            'cash': 'money-bill-wave',
            'bank_transfer': 'university',
            'mobile_money': 'mobile-alt',
            'card': 'credit-card',
            'digital_wallet': 'wallet',
            'other': 'question-circle'
        };
        return icons[type] || 'question-circle';
    }
    
    getStatusColor(status) {
        const colors = {
            'confirmed': 'bg-green-600 text-white',
            'pending': 'bg-yellow-600 text-white',
            'failed': 'bg-red-600 text-white',
            'cancelled': 'bg-gray-600 text-white'
        };
        return colors[status] || 'bg-gray-600 text-white';
    }
}

// Global functions for payment methods
async function togglePaymentMethodStatus(methodId) {
    try {
        const response = await fetch('api/payments/payment-methods-admin.php?action=toggle-status', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: methodId })
        });
        
        const data = await response.json();
        if (data.success) {
            dashboard.showSuccess('Payment method status updated');
            dashboard.loadPaymentMethods();
        } else {
            dashboard.showError(data.error || 'Failed to update status');
        }
    } catch (error) {
        console.error('Error toggling payment method status:', error);
        dashboard.showError('Failed to update payment method status');
    }
}

async function testPaymentGateway(gatewayId) {
    try {
        dashboard.showLoading(true);
        
        const response = await fetch('api/payments/payment-methods-admin.php?action=test-gateway', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ gateway_id: gatewayId, test_amount: 1.00 })
        });
        
        const data = await response.json();
        if (data.success) {
            const result = data.data;
            dashboard.showSuccess(`Gateway test successful! Response time: ${result.response_time}`);
        } else {
            dashboard.showError(data.error || 'Gateway test failed');
        }
    } catch (error) {
        console.error('Error testing payment gateway:', error);
        dashboard.showError('Failed to test payment gateway');
    } finally {
        dashboard.showLoading(false);
    }
}

async function testAllGateways() {
    dashboard.showSuccess('Testing all payment gateways... (This is a demo implementation)');
}

function showAddPaymentMethodModal() {
    dashboard.showSuccess('Add Payment Method modal would open here (Feature in development)');
}

function editPaymentMethod(methodId) {
    dashboard.showSuccess(`Edit Payment Method ${methodId} modal would open here (Feature in development)`);
}

function configureGateway(gatewayId) {
    dashboard.showSuccess(`Configure Gateway ${gatewayId} modal would open here (Feature in development)`);
}

function refreshTransactions() {
    if (window.dashboard) {
        dashboard.loadPaymentMethods();
        dashboard.showSuccess('Transactions refreshed');
    }
}