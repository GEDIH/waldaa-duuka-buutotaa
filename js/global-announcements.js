/**
 * Global Announcements Management System
 * For Super Administrator Dashboard
 */

class GlobalAnnouncementsManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.currentFilters = {};
        this.announcements = [];
        this.stats = {};
        
        this.init();
    }
    
    init() {
        this.loadStats();
        this.loadAnnouncements();
        this.setupEventListeners();
        this.setupFormValidation();
    }
    
    setupEventListeners() {
        // Create announcement button
        const createBtn = document.getElementById('createAnnouncementBtn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }
        
        // Filter buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filterType = e.target.dataset.filter;
                const filterValue = e.target.dataset.value;
                this.applyFilter(filterType, filterValue);
            });
        });
        
        // Search input
        const searchInput = document.getElementById('announcementSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchAnnouncements(e.target.value);
                }, 300);
            });
        }
        
        // Pagination
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('page-btn')) {
                const page = parseInt(e.target.dataset.page);
                this.loadAnnouncements(page);
            }
        });
        
        // Form submission
        const createForm = document.getElementById('createAnnouncementForm');
        if (createForm) {
            createForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createAnnouncement();
            });
        }
        
        const editForm = document.getElementById('editAnnouncementForm');
        if (editForm) {
            editForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.updateAnnouncement();
            });
        }
    }
    
    setupFormValidation() {
        // Real-time validation for create form
        const titleInput = document.getElementById('announcementTitle');
        const contentInput = document.getElementById('announcementContent');
        
        if (titleInput) {
            titleInput.addEventListener('input', () => {
                this.validateField(titleInput, 'Title must be between 5 and 255 characters', 
                    (value) => value.length >= 5 && value.length <= 255);
            });
        }
        
        if (contentInput) {
            contentInput.addEventListener('input', () => {
                this.validateField(contentInput, 'Content must be at least 10 characters', 
                    (value) => value.length >= 10);
                this.updateCharacterCount(contentInput);
            });
        }
        
        // Date validation
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        
        if (startDateInput && endDateInput) {
            startDateInput.addEventListener('change', () => this.validateDates());
            endDateInput.addEventListener('change', () => this.validateDates());
        }
    }
    
    async loadStats() {
        try {
            const response = await fetch('api/superadmin/announcements-bulletproof.php?action=stats');
            const result = await response.json();
            
            if (result.success) {
                this.stats = result.data;
                this.updateStatsDisplay();
            } else {
                console.error('Stats error:', result.error);
                this.showError('Failed to load stats: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading stats:', error);
            this.showError('Network error while loading stats');
        }
    }
    
    async loadAnnouncements(page = 1) {
        try {
            this.currentPage = page;
            const params = new URLSearchParams({
                page: page,
                limit: this.itemsPerPage,
                ...this.currentFilters
            });
            
            const response = await fetch(`api/superadmin/announcements-bulletproof.php?action=list&${params}`);
            const result = await response.json();
            
            if (result.success) {
                this.announcements = result.data.announcements;
                this.updateAnnouncementsDisplay();
                this.updatePagination(result.data.pagination);
            } else {
                this.showError('Failed to load announcements: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading announcements:', error);
            this.showError('Network error while loading announcements');
        }
    }
    
    updateStatsDisplay() {
        // Update stats cards
        const statsElements = {
            'total-announcements': Object.values(this.stats.by_status || {}).reduce((a, b) => a + b, 0),
            'active-announcements': this.stats.active_count || 0,
            'recent-announcements': this.stats.recent_count || 0,
            'total-views': this.stats.total_views || 0
        };
        
        Object.entries(statsElements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value.toLocaleString();
            }
        });
        
        // Update charts if available
        this.updateStatsCharts();
    }
    
    updateStatsCharts() {
        // Status distribution chart
        if (this.stats.by_status && document.getElementById('statusChart')) {
            this.createPieChart('statusChart', this.stats.by_status, 'Announcements by Status');
        }
        
        // Type distribution chart
        if (this.stats.by_type && document.getElementById('typeChart')) {
            this.createPieChart('typeChart', this.stats.by_type, 'Announcements by Type');
        }
    }
    
    createPieChart(elementId, data, title) {
        // Simple chart implementation (you can replace with Chart.js or similar)
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const total = Object.values(data).reduce((a, b) => a + b, 0);
        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
        
        let html = `<div class="chart-title">${title}</div><div class="chart-legend">`;
        
        Object.entries(data).forEach(([key, value], index) => {
            const percentage = ((value / total) * 100).toFixed(1);
            const color = colors[index % colors.length];
            
            html += `
                <div class="legend-item">
                    <span class="legend-color" style="background-color: ${color}"></span>
                    <span class="legend-label">${key}: ${value} (${percentage}%)</span>
                </div>
            `;
        });
        
        html += '</div>';
        element.innerHTML = html;
    }
    
    updateAnnouncementsDisplay() {
        const container = document.getElementById('announcementsContainer');
        if (!container) return;
        
        if (this.announcements.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-bullhorn text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No Announcements Found</h3>
                    <p class="text-gray-500 mb-4">Create your first announcement to get started.</p>
                    <button onclick="globalAnnouncements.showCreateModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Create Announcement
                    </button>
                </div>
            `;
            return;
        }
        
        const html = this.announcements.map(announcement => this.renderAnnouncementCard(announcement)).join('');
        container.innerHTML = html;
    }
    
    renderAnnouncementCard(announcement) {
        const statusColors = {
            'draft': 'bg-gray-100 text-gray-800',
            'active': 'bg-green-100 text-green-800',
            'scheduled': 'bg-blue-100 text-blue-800',
            'expired': 'bg-red-100 text-red-800',
            'archived': 'bg-yellow-100 text-yellow-800'
        };
        
        const typeIcons = {
            'info': 'fa-info-circle text-blue-600',
            'warning': 'fa-exclamation-triangle text-yellow-600',
            'success': 'fa-check-circle text-green-600',
            'error': 'fa-times-circle text-red-600',
            'urgent': 'fa-bolt text-red-600'
        };
        
        const priorityColors = {
            'low': 'text-gray-500',
            'medium': 'text-blue-500',
            'high': 'text-orange-500',
            'critical': 'text-red-500'
        };
        
        return `
            <div class="announcement-card bg-white rounded-lg shadow-md p-6 mb-4 border-l-4 border-${announcement.type === 'urgent' ? 'red' : 'blue'}-500">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center space-x-3">
                        <i class="fas ${typeIcons[announcement.type] || 'fa-info-circle text-blue-600'}"></i>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">${this.escapeHtml(announcement.title)}</h3>
                            <div class="flex items-center space-x-4 text-sm text-gray-500 mt-1">
                                <span>By ${announcement.creator_name || 'Unknown'}</span>
                                <span>${announcement.formatted_dates?.start || 'No start date'}</span>
                                <span class="${priorityColors[announcement.priority]} font-medium">
                                    ${announcement.priority.toUpperCase()} Priority
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        ${announcement.is_sticky ? '<i class="fas fa-thumbtack text-yellow-500" title="Sticky"></i>' : ''}
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${statusColors[announcement.status]}">
                            ${announcement.status.toUpperCase()}
                        </span>
                        <div class="dropdown relative">
                            <button class="text-gray-400 hover:text-gray-600" onclick="this.nextElementSibling.classList.toggle('hidden')">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                <a href="#" onclick="globalAnnouncements.editAnnouncement(${announcement.id})" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </a>
                                <a href="#" onclick="globalAnnouncements.viewAcknowledgments(${announcement.id})" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-users mr-2"></i>View Acknowledgments (${announcement.acknowledgment_count || 0})
                                </a>
                                <a href="#" onclick="globalAnnouncements.duplicateAnnouncement(${announcement.id})" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-copy mr-2"></i>Duplicate
                                </a>
                                <div class="border-t border-gray-100"></div>
                                <a href="#" onclick="globalAnnouncements.deleteAnnouncement(${announcement.id})" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-trash mr-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <p class="text-gray-700">${this.escapeHtml(announcement.content.substring(0, 200))}${announcement.content.length > 200 ? '...' : ''}</p>
                </div>
                
                <div class="flex justify-between items-center text-sm">
                    <div class="flex items-center space-x-4">
                        <span class="text-gray-500">
                            <i class="fas fa-users mr-1"></i>
                            Target: ${announcement.target_audience}
                        </span>
                        <span class="text-gray-500">
                            <i class="fas fa-eye mr-1"></i>
                            ${announcement.views_count || 0} views
                        </span>
                        ${announcement.requires_acknowledgment ? '<span class="text-blue-500"><i class="fas fa-check-square mr-1"></i>Requires Ack</span>' : ''}
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        ${announcement.show_on_login ? '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">Login</span>' : ''}
                        ${announcement.show_on_dashboard ? '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">Dashboard</span>' : ''}
                        ${announcement.days_remaining !== null ? 
                            `<span class="text-${announcement.days_remaining < 0 ? 'red' : announcement.days_remaining < 7 ? 'orange' : 'green'}-600">
                                ${announcement.days_remaining < 0 ? 'Expired' : `${announcement.days_remaining} days left`}
                            </span>` : ''
                        }
                    </div>
                </div>
            </div>
        `;
    }
    
    showCreateModal() {
        const modal = document.getElementById('createAnnouncementModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.getElementById('announcementTitle').focus();
        }
    }
    
    hideCreateModal() {
        const modal = document.getElementById('createAnnouncementModal');
        if (modal) {
            modal.classList.add('hidden');
            document.getElementById('createAnnouncementForm').reset();
        }
    }
    
    async createAnnouncement() {
        const form = document.getElementById('createAnnouncementForm');
        const formData = new FormData(form);
        
        const data = {
            title: formData.get('title'),
            content: formData.get('content'),
            type: formData.get('type'),
            priority: formData.get('priority'),
            target_audience: formData.get('target_audience'),
            status: formData.get('status'),
            start_date: formData.get('start_date') || null,
            end_date: formData.get('end_date') || null,
            is_sticky: formData.has('is_sticky'),
            show_on_login: formData.has('show_on_login'),
            show_on_dashboard: formData.has('show_on_dashboard'),
            requires_acknowledgment: formData.has('requires_acknowledgment')
        };
        
        try {
            const response = await fetch('api/superadmin/announcements-bulletproof.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Announcement created successfully!');
                this.hideCreateModal();
                this.loadAnnouncements();
                this.loadStats();
            } else {
                this.showError('Failed to create announcement: ' + result.error);
            }
        } catch (error) {
            console.error('Error creating announcement:', error);
            this.showError('Network error while creating announcement');
        }
    }
    
    async editAnnouncement(id) {
        // Find announcement data
        const announcement = this.announcements.find(a => a.id == id);
        if (!announcement) {
            this.showError('Announcement not found');
            return;
        }
        
        // Populate edit form
        this.populateEditForm(announcement);
        
        // Show edit modal
        const modal = document.getElementById('editAnnouncementModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }
    
    populateEditForm(announcement) {
        const form = document.getElementById('editAnnouncementForm');
        if (!form) return;
        
        form.querySelector('#editAnnouncementId').value = announcement.id;
        form.querySelector('#editTitle').value = announcement.title;
        form.querySelector('#editContent').value = announcement.content;
        form.querySelector('#editType').value = announcement.type;
        form.querySelector('#editPriority').value = announcement.priority;
        form.querySelector('#editTargetAudience').value = announcement.target_audience;
        form.querySelector('#editStatus').value = announcement.status;
        form.querySelector('#editStartDate').value = announcement.start_date ? announcement.start_date.slice(0, 16) : '';
        form.querySelector('#editEndDate').value = announcement.end_date ? announcement.end_date.slice(0, 16) : '';
        form.querySelector('#editIsSticky').checked = announcement.is_sticky;
        form.querySelector('#editShowOnLogin').checked = announcement.show_on_login;
        form.querySelector('#editShowOnDashboard').checked = announcement.show_on_dashboard;
        form.querySelector('#editRequiresAcknowledgment').checked = announcement.requires_acknowledgment;
    }
    
    async updateAnnouncement() {
        const form = document.getElementById('editAnnouncementForm');
        const formData = new FormData(form);
        const id = formData.get('id');
        
        const data = {
            title: formData.get('title'),
            content: formData.get('content'),
            type: formData.get('type'),
            priority: formData.get('priority'),
            target_audience: formData.get('target_audience'),
            status: formData.get('status'),
            start_date: formData.get('start_date') || null,
            end_date: formData.get('end_date') || null,
            is_sticky: formData.has('is_sticky'),
            show_on_login: formData.has('show_on_login'),
            show_on_dashboard: formData.has('show_on_dashboard'),
            requires_acknowledgment: formData.has('requires_acknowledgment')
        };
        
        try {
            const response = await fetch(`api/superadmin/announcements-bulletproof.php?id=${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Announcement updated successfully!');
                this.hideEditModal();
                this.loadAnnouncements();
                this.loadStats();
            } else {
                this.showError('Failed to update announcement: ' + result.error);
            }
        } catch (error) {
            console.error('Error updating announcement:', error);
            this.showError('Network error while updating announcement');
        }
    }
    
    hideEditModal() {
        const modal = document.getElementById('editAnnouncementModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    async deleteAnnouncement(id) {
        if (!confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch(`api/superadmin/announcements-bulletproof.php?id=${id}`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Announcement deleted successfully!');
                this.loadAnnouncements();
                this.loadStats();
            } else {
                this.showError('Failed to delete announcement: ' + result.error);
            }
        } catch (error) {
            console.error('Error deleting announcement:', error);
            this.showError('Network error while deleting announcement');
        }
    }
    
    async viewAcknowledgments(id) {
        try {
            const response = await fetch(`api/superadmin/announcements-bulletproof.php?action=acknowledgments&id=${id}`);
            const result = await response.json();
            
            if (result.success) {
                this.showAcknowledgmentsModal(result.data);
            } else {
                this.showError('Failed to load acknowledgments: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading acknowledgments:', error);
            this.showError('Network error while loading acknowledgments');
        }
    }
    
    showAcknowledgmentsModal(acknowledgments) {
        const modal = document.getElementById('acknowledgmentsModal');
        const container = document.getElementById('acknowledgmentsList');
        
        if (!modal || !container) return;
        
        if (acknowledgments.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-center py-4">No acknowledgments yet.</p>';
        } else {
            const html = acknowledgments.map(ack => `
                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                    <div>
                        <div class="font-medium text-gray-900">${this.escapeHtml(ack.full_name)}</div>
                        <div class="text-sm text-gray-500">${ack.email}</div>
                    </div>
                    <div class="text-sm text-gray-500">
                        ${new Date(ack.acknowledged_at).toLocaleDateString()} ${new Date(ack.acknowledged_at).toLocaleTimeString()}
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = html;
        }
        
        modal.classList.remove('hidden');
    }
    
    hideAcknowledgmentsModal() {
        const modal = document.getElementById('acknowledgmentsModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    applyFilter(filterType, filterValue) {
        if (filterValue === 'all') {
            delete this.currentFilters[filterType];
        } else {
            this.currentFilters[filterType] = filterValue;
        }
        
        this.currentPage = 1;
        this.loadAnnouncements();
        
        // Update filter button states
        document.querySelectorAll(`.filter-btn[data-filter="${filterType}"]`).forEach(btn => {
            btn.classList.toggle('active', btn.dataset.value === filterValue);
        });
    }
    
    searchAnnouncements(query) {
        if (query.trim()) {
            this.currentFilters.search = query.trim();
        } else {
            delete this.currentFilters.search;
        }
        
        this.currentPage = 1;
        this.loadAnnouncements();
    }
    
    updatePagination(pagination) {
        const container = document.getElementById('paginationContainer');
        if (!container) return;
        
        if (pagination.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<div class="flex items-center justify-center space-x-2">';
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `<button class="page-btn px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50" data-page="${pagination.current_page - 1}">Previous</button>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === pagination.current_page;
            html += `<button class="page-btn px-3 py-2 text-sm ${isActive ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'} border border-gray-300 rounded-md" data-page="${i}">${i}</button>`;
        }
        
        // Next button
        if (pagination.current_page < pagination.total_pages) {
            html += `<button class="page-btn px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50" data-page="${pagination.current_page + 1}">Next</button>`;
        }
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    validateField(input, message, validator) {
        const isValid = validator(input.value.trim());
        const errorElement = input.nextElementSibling;
        
        if (isValid) {
            input.classList.remove('border-red-500');
            input.classList.add('border-green-500');
            if (errorElement && errorElement.classList.contains('error-message')) {
                errorElement.remove();
            }
        } else {
            input.classList.remove('border-green-500');
            input.classList.add('border-red-500');
            if (!errorElement || !errorElement.classList.contains('error-message')) {
                const error = document.createElement('div');
                error.className = 'error-message text-red-500 text-sm mt-1';
                error.textContent = message;
                input.parentNode.insertBefore(error, input.nextSibling);
            }
        }
        
        return isValid;
    }
    
    validateDates() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (startDate && endDate && new Date(startDate) >= new Date(endDate)) {
            this.showError('End date must be after start date');
            return false;
        }
        
        return true;
    }
    
    updateCharacterCount(textarea) {
        const maxLength = 5000;
        const currentLength = textarea.value.length;
        const countElement = textarea.parentNode.querySelector('.character-count');
        
        if (countElement) {
            countElement.textContent = `${currentLength}/${maxLength}`;
            countElement.className = `character-count text-sm ${currentLength > maxLength ? 'text-red-500' : 'text-gray-500'}`;
        }
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('globalAnnouncementsSection')) {
        window.globalAnnouncements = new GlobalAnnouncementsManager();
    }
});