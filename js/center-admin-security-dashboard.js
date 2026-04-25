/**
 * Center Admin Security Dashboard JavaScript
 * Real-time security monitoring and access control management
 */

class CenterAdminSecurityDashboard {
    constructor() {
        this.currentUser = null;
        this.securityData = {};
        this.charts = {};
        this.refreshInterval = null;
        this.alertTimeout = null;
        
        this.init();
    }
    
    async init() {
        try {
            await this.checkAuthentication();
            await this.loadSecurityData();
            this.setupCharts();
            this.setupEventListeners();
            this.startAutoRefresh();
        } catch (error) {
            console.error('Dashboard initialization error:', error);
            this.showError('Failed to initialize security dashboard');
        }
    }
    
    async checkAuthentication() {
        try {
            const response = await fetch('api/auth/session.php');
            const data = await response.json();
            
            if (!data.success) {
                window.location.href = 'admin-login.html';
                return;
            }
            
            this.currentUser = data.user;
            
            // Check if user has security monitoring privileges
            if (!['admin', 'superadmin'].includes(this.currentUser.role)) {
                this.showError('Insufficient privileges for security monitoring');
                return;
            }
            
            // Update UI with user info
            document.getElementById('adminName').textContent = this.currentUser.full_name || this.currentUser.username;
            
        } catch (error) {
            console.error('Authentication check failed:', error);
            window.location.href = 'admin-login.html';
        }
    }
    
    async loadSecurityData() {
        this.showLoading(true);
        
        try {
            await Promise.all([
                this.loadSecurityOverview(),
                this.loadRecentViolations(),
                this.loadAdminActivity(),
                this.loadCenterSecurityStatus(),
                this.loadSecurityRecommendations()
            ]);
        } catch (error) {
            console.error('Failed to load security data:', error);
            this.showError('Failed to load security data');
        } finally {
            this.showLoading(false);
        }
    }
    
    async loadSecurityOverview() {
        try {
            const response = await fetch('api/security/overview.php');
            const data = await response.json();
            
            if (data.success) {
                this.securityData.overview = data.overview;
                this.updateSecurityOverview(data.overview);
            }
        } catch (error) {
            console.error('Failed to load security overview:', error);
        }
    }
    
    updateSecurityOverview(overview) {
        // Update overall security status
        const statusCard = document.getElementById('overallSecurityCard');
        const statusText = document.getElementById('overallSecurityStatus');
        const statusDescription = document.getElementById('securityStatusDescription');
        
        // Remove existing status classes
        statusCard.classList.remove('security-status-critical', 'security-status-warning', 'security-status-secure', 'security-status-attention');
        
        switch (overview.overall_status) {
            case 'CRITICAL':
                statusCard.classList.add('security-status-critical');
                statusText.textContent = 'CRITICAL';
                statusDescription.textContent = 'Immediate attention required';
                this.showSecurityAlert('Critical security violations detected!');
                break;
            case 'WARNING':
                statusCard.classList.add('security-status-warning');
                statusText.textContent = 'WARNING';
                statusDescription.textContent = 'Security issues found';
                break;
            case 'ATTENTION_REQUIRED':
                statusCard.classList.add('security-status-attention');
                statusText.textContent = 'ATTENTION';
                statusDescription.textContent = 'Minor issues detected';
                break;
            default:
                statusCard.classList.add('security-status-secure');
                statusText.textContent = 'SECURE';
                statusDescription.textContent = 'All systems secure';
        }
        
        // Update metrics
        document.getElementById('totalViolations').textContent = overview.total_violations || 0;
        document.getElementById('activeAdmins').textContent = overview.active_admins || 0;
        document.getElementById('monitoredCenters').textContent = overview.monitored_centers || 0;
    }
    
    async loadRecentViolations() {
        try {
            const response = await fetch('api/security/violations.php?limit=10');
            const data = await response.json();
            
            if (data.success) {
                this.displayRecentViolations(data.violations);
            }
        } catch (error) {
            console.error('Failed to load recent violations:', error);
        }
    }
    
    displayRecentViolations(violations) {
        const container = document.getElementById('recentViolationsList');
        
        if (violations.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-400">No recent violations</p>';
            return;
        }
        
        container.innerHTML = violations.map(violation => `
            <div class="violation-${violation.severity.toLowerCase()} bg-white bg-opacity-5 p-4 rounded-lg">
                <div class="flex items-start justify-between">
                    <div class="flex-grow">
                        <div class="flex items-center space-x-2 mb-2">
                            <span class="px-2 py-1 bg-${this.getSeverityColor(violation.severity)} text-white text-xs rounded-full">
                                ${violation.severity}
                            </span>
                            <span class="text-sm text-gray-300">${violation.type}</span>
                        </div>
                        <p class="text-sm mb-2">${violation.description}</p>
                        <div class="flex items-center space-x-4 text-xs text-gray-400">
                            <span><i class="fas fa-user mr-1"></i>${violation.username || 'Unknown'}</span>
                            <span><i class="fas fa-clock mr-1"></i>${this.formatDateTime(violation.created_at)}</span>
                            ${violation.center_name ? `<span><i class="fas fa-building mr-1"></i>${violation.center_name}</span>` : ''}
                        </div>
                    </div>
                    <button onclick="dashboard.viewViolationDetails(${violation.id})" 
                            class="text-blue-300 hover:text-blue-100 transition">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    async loadAdminActivity() {
        try {
            const response = await fetch('api/security/admin-activity.php?limit=10');
            const data = await response.json();
            
            if (data.success) {
                this.displayAdminActivity(data.activities);
            }
        } catch (error) {
            console.error('Failed to load admin activity:', error);
        }
    }
    
    displayAdminActivity(activities) {
        const container = document.getElementById('adminActivityList');
        
        if (activities.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-400">No recent activity</p>';
            return;
        }
        
        container.innerHTML = activities.map(activity => `
            <div class="bg-white bg-opacity-5 p-4 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            ${activity.username.substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <p class="font-semibold">${activity.username}</p>
                            <p class="text-sm text-gray-300">${activity.action}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">${this.formatDateTime(activity.created_at)}</p>
                        <span class="px-2 py-1 bg-${activity.access_granted ? 'green' : 'red'}-500 text-white text-xs rounded-full">
                            ${activity.access_granted ? 'Allowed' : 'Denied'}
                        </span>
                    </div>
                </div>
                ${activity.resource_type ? `
                    <div class="mt-2 text-xs text-gray-400">
                        <i class="fas fa-cube mr-1"></i>Resource: ${activity.resource_type}
                        ${activity.center_name ? ` | <i class="fas fa-building mr-1"></i>${activity.center_name}` : ''}
                    </div>
                ` : ''}
            </div>
        `).join('');
    }
    
    async loadCenterSecurityStatus() {
        try {
            const response = await fetch('api/security/center-status.php');
            const data = await response.json();
            
            if (data.success) {
                this.displayCenterSecurityStatus(data.centers);
            }
        } catch (error) {
            console.error('Failed to load center security status:', error);
        }
    }
    
    displayCenterSecurityStatus(centers) {
        const tbody = document.getElementById('centerSecurityTableBody');
        
        if (centers.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                        No centers found
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = centers.map(center => `
            <tr class="border-b border-white border-opacity-10 hover:bg-white hover:bg-opacity-5">
                <td class="px-4 py-3">
                    <div class="font-semibold">${center.name}</div>
                    <div class="text-sm text-gray-400">${center.code}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center space-x-2">
                        <span class="font-semibold">${center.admin_count}</span>
                        <span class="text-sm text-gray-400">admins</span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center space-x-2">
                        <span class="font-semibold">${center.member_count}</span>
                        <span class="text-sm text-gray-400">members</span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-${center.violations_24h > 0 ? 'red' : 'green'}-500 text-white text-xs rounded-full">
                        ${center.violations_24h}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-${this.getSecurityLevelColor(center.security_level)} text-white text-xs rounded-full">
                        ${center.security_level}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex space-x-2">
                        <button onclick="dashboard.viewCenterDetails(${center.id})" 
                                class="text-blue-400 hover:text-blue-300" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="dashboard.auditCenter(${center.id})" 
                                class="text-green-400 hover:text-green-300" title="Run Audit">
                            <i class="fas fa-search"></i>
                        </button>
                        ${center.violations_24h > 0 ? `
                            <button onclick="dashboard.investigateCenter(${center.id})" 
                                    class="text-red-400 hover:text-red-300" title="Investigate">
                                <i class="fas fa-exclamation-triangle"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    }
    
    async loadSecurityRecommendations() {
        try {
            const response = await fetch('api/security/recommendations.php');
            const data = await response.json();
            
            if (data.success) {
                this.displaySecurityRecommendations(data.recommendations);
            }
        } catch (error) {
            console.error('Failed to load security recommendations:', error);
        }
    }
    
    displaySecurityRecommendations(recommendations) {
        const container = document.getElementById('securityRecommendations');
        
        if (recommendations.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-400">No recommendations at this time</p>';
            return;
        }
        
        container.innerHTML = recommendations.map((rec, index) => `
            <div class="bg-white bg-opacity-5 p-4 rounded-lg flex items-start space-x-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                    ${index + 1}
                </div>
                <div class="flex-grow">
                    <p class="text-sm">${rec.recommendation}</p>
                    ${rec.priority ? `
                        <span class="inline-block mt-2 px-2 py-1 bg-${this.getPriorityColor(rec.priority)} text-white text-xs rounded-full">
                            ${rec.priority} Priority
                        </span>
                    ` : ''}
                </div>
                ${rec.actionable ? `
                    <button onclick="dashboard.implementRecommendation(${index})" 
                            class="flex-shrink-0 text-green-400 hover:text-green-300">
                        <i class="fas fa-check-circle"></i>
                    </button>
                ` : ''}
            </div>
        `).join('');
    }
    
    setupCharts() {
        this.setupViolationsTrendChart();
        this.setupViolationTypesChart();
    }
    
    setupViolationsTrendChart() {
        const ctx = document.getElementById('violationsTrendChart').getContext('2d');
        
        this.charts.violationsTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Security Violations',
                    data: [],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
        
        this.loadViolationsTrendData();
    }
    
    setupViolationTypesChart() {
        const ctx = document.getElementById('violationTypesChart').getContext('2d');
        
        this.charts.violationTypes = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#ef4444',
                        '#f59e0b',
                        '#3b82f6',
                        '#10b981',
                        '#8b5cf6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#ffffff'
                        }
                    }
                }
            }
        });
        
        this.loadViolationTypesData();
    }
    
    async loadViolationsTrendData() {
        try {
            const response = await fetch('api/security/violations-trend.php?days=7');
            const data = await response.json();
            
            if (data.success && this.charts.violationsTrend) {
                this.charts.violationsTrend.data.labels = data.labels;
                this.charts.violationsTrend.data.datasets[0].data = data.values;
                this.charts.violationsTrend.update();
            }
        } catch (error) {
            console.error('Failed to load violations trend data:', error);
        }
    }
    
    async loadViolationTypesData() {
        try {
            const response = await fetch('api/security/violation-types.php');
            const data = await response.json();
            
            if (data.success && this.charts.violationTypes) {
                this.charts.violationTypes.data.labels = data.labels;
                this.charts.violationTypes.data.datasets[0].data = data.values;
                this.charts.violationTypes.update();
            }
        } catch (error) {
            console.error('Failed to load violation types data:', error);
        }
    }
    
    setupEventListeners() {
        // Auto-refresh controls
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopAutoRefresh();
            } else {
                this.startAutoRefresh();
            }
        });
    }
    
    startAutoRefresh() {
        this.stopAutoRefresh();
        this.refreshInterval = setInterval(() => {
            this.loadSecurityData();
        }, 30000); // Refresh every 30 seconds
    }
    
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    // Security Actions
    async runSecurityAudit() {
        this.showLoading(true);
        
        try {
            const response = await fetch('api/security/audit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    audit_type: 'comprehensive',
                    include_recommendations: true
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displayAuditResults(data.audit_results);
                document.getElementById('auditModal').style.display = 'flex';
            } else {
                this.showError(data.error || 'Failed to run security audit');
            }
        } catch (error) {
            console.error('Security audit failed:', error);
            this.showError('Failed to run security audit');
        } finally {
            this.showLoading(false);
        }
    }
    
    displayAuditResults(results) {
        const container = document.getElementById('auditResults');
        
        container.innerHTML = `
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Audit Summary</h3>
                    <span class="px-3 py-1 bg-${this.getStatusColor(results.overall_status)} text-white rounded-full text-sm">
                        ${results.overall_status}
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-400">${results.summary.high_severity}</div>
                        <div class="text-sm text-gray-400">High Severity</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-400">${results.summary.medium_severity}</div>
                        <div class="text-sm text-gray-400">Medium Severity</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-400">${results.summary.low_severity}</div>
                        <div class="text-sm text-gray-400">Low Severity</div>
                    </div>
                </div>
            </div>
            
            ${results.security_violations.length > 0 ? `
                <div class="mb-6">
                    <h4 class="font-semibold mb-3">Security Violations Found</h4>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        ${results.security_violations.map(violation => `
                            <div class="violation-${violation.severity.toLowerCase()} bg-white bg-opacity-5 p-3 rounded">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="font-semibold">${violation.type}</div>
                                        <div class="text-sm text-gray-300 mb-2">${violation.description}</div>
                                        <div class="text-xs text-gray-400">${violation.recommendation}</div>
                                    </div>
                                    <span class="px-2 py-1 bg-${this.getSeverityColor(violation.severity)} text-white text-xs rounded">
                                        ${violation.severity}
                                    </span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
            
            ${results.recommendations.length > 0 ? `
                <div>
                    <h4 class="font-semibold mb-3">Recommendations</h4>
                    <div class="space-y-2">
                        ${results.recommendations.map((rec, index) => `
                            <div class="flex items-start space-x-3 p-3 bg-white bg-opacity-5 rounded">
                                <div class="flex-shrink-0 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                    ${index + 1}
                                </div>
                                <div class="text-sm">${rec}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        `;
    }
    
    // Utility Methods
    getSeverityColor(severity) {
        switch (severity.toLowerCase()) {
            case 'high': return 'red-500';
            case 'medium': return 'yellow-500';
            case 'low': return 'blue-500';
            default: return 'gray-500';
        }
    }
    
    getSecurityLevelColor(level) {
        switch (level.toLowerCase()) {
            case 'critical': return 'red-500';
            case 'high': return 'green-500';
            case 'medium': return 'yellow-500';
            case 'low': return 'red-400';
            default: return 'gray-500';
        }
    }
    
    getPriorityColor(priority) {
        switch (priority.toLowerCase()) {
            case 'high': return 'red-500';
            case 'medium': return 'yellow-500';
            case 'low': return 'blue-500';
            default: return 'gray-500';
        }
    }
    
    getStatusColor(status) {
        switch (status) {
            case 'CRITICAL': return 'red-500';
            case 'WARNING': return 'yellow-500';
            case 'ATTENTION_REQUIRED': return 'blue-500';
            case 'SECURE': return 'green-500';
            default: return 'gray-500';
        }
    }
    
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }
    
    showSecurityAlert(message) {
        const banner = document.getElementById('securityAlertBanner');
        const messageEl = document.getElementById('alertMessage');
        
        messageEl.textContent = message;
        banner.style.display = 'block';
        
        // Auto-dismiss after 10 seconds
        if (this.alertTimeout) {
            clearTimeout(this.alertTimeout);
        }
        this.alertTimeout = setTimeout(() => {
            this.dismissAlert();
        }, 10000);
    }
    
    dismissAlert() {
        document.getElementById('securityAlertBanner').style.display = 'none';
        if (this.alertTimeout) {
            clearTimeout(this.alertTimeout);
            this.alertTimeout = null;
        }
    }
    
    showLoading(show) {
        document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
    }
    
    showError(message) {
        alert('Error: ' + message); // Replace with better notification system
    }
    
    showSuccess(message) {
        alert(message); // Replace with better notification system
    }
    
    // Modal Management
    closeAuditModal() {
        document.getElementById('auditModal').style.display = 'none';
    }
    
    // Refresh Methods
    async refreshViolations() {
        await this.loadRecentViolations();
    }
    
    async refreshAdminActivity() {
        await this.loadAdminActivity();
    }
    
    async refreshCenterStatus() {
        await this.loadCenterSecurityStatus();
    }
    
    // Export Methods
    async exportSecurityReport() {
        try {
            const response = await fetch('api/security/export-report.php');
            const blob = await response.blob();
            
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `security-report-${new Date().toISOString().split('T')[0]}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            
            this.showSuccess('Security report exported successfully');
        } catch (error) {
            console.error('Export failed:', error);
            this.showError('Failed to export security report');
        }
    }
    
    async exportAuditReport() {
        // Implementation for exporting audit report
        this.showSuccess('Audit report export functionality coming soon');
    }
}

// Global functions for onclick handlers
async function logout() {
    try {
        const response = await fetch('api/auth/logout.php', { method: 'POST' });
        const data = await response.json();
        
        if (data.success) {
            window.location.href = 'admin-login.html';
        } else {
            alert('Logout failed');
        }
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = 'admin-login.html';
    }
}

function runSecurityAudit() {
    dashboard.runSecurityAudit();
}

function dismissAlert() {
    dashboard.dismissAlert();
}

function closeAuditModal() {
    dashboard.closeAuditModal();
}

function refreshViolations() {
    dashboard.refreshViolations();
}

function refreshAdminActivity() {
    dashboard.refreshAdminActivity();
}

function refreshCenterStatus() {
    dashboard.refreshCenterStatus();
}

function exportSecurityReport() {
    dashboard.exportSecurityReport();
}

function exportAuditReport() {
    dashboard.exportAuditReport();
}

// Initialize dashboard when page loads
let dashboard;
document.addEventListener('DOMContentLoaded', () => {
    dashboard = new CenterAdminSecurityDashboard();
});