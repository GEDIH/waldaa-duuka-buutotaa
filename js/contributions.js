/**
 * Contribution Management JavaScript
 * Handles all contribution-related functionality
 */

// Global variables for contribution management
let contributionData = [];
let contributionFilters = {
    search: '',
    center_id: '',
    type: '',
    payment_status: '',
    page: 1,
    limit: 20,
    order_by: 'contribution_date',
    order_dir: 'DESC'
};
let contributionPagination = {
    page: 1,
    limit: 20,
    total: 0,
    pages: 0
};
let contributionSortState = {
    column: 'contribution_date',
    direction: 'DESC'
};
let currentContributionId = null;

// Initialize contribution management
function initializeContributions() {
    setupContributionEventListeners();
    loadContributionFilters();
    loadContributions();
    loadContributionStats();
}

// Setup event listeners for contribution management
function setupContributionEventListeners() {
    // Search input
    const searchInput = document.getElementById('contributionSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            contributionFilters.search = this.value;
            contributionFilters.page = 1;
            loadContributions();
        }, 300));
    }

    // Filter dropdowns
    const centerFilter = document.getElementById('contributionCenterFilter');
    if (centerFilter) {
        centerFilter.addEventListener('change', function() {
            contributionFilters.center_id = this.value;
            contributionFilters.page = 1;
            loadContributions();
        });
    }

    const typeFilter = document.getElementById('contributionTypeFilter');
    if (typeFilter) {
        typeFilter.addEventListener('change', function() {
            contributionFilters.type = this.value;
            contributionFilters.page = 1;
            loadContributions();
        });
    }

    const paymentFilter = document.getElementById('contributionPaymentFilter');
    if (paymentFilter) {
        paymentFilter.addEventListener('change', function() {
            contributionFilters.payment_status = this.value;
            contributionFilters.page = 1;
            loadContributions();
        });
    }

    // Form submission
    const contributionForm = document.getElementById('contributionForm');
    if (contributionForm) {
        contributionForm.addEventListener('submit', handleContributionSubmit);
    }
}

// Load contribution filters (centers, members)
async function loadContributionFilters() {
    try {
        // Load centers for filter dropdown
        const centersResponse = await fetch('/api/admin/centers.php');
        if (centersResponse.ok) {
            const centersData = await centersResponse.json();
            if (centersData.success) {
                populateCenterDropdowns(centersData.data);
            }
        }

        // Load members for contribution form
        const membersResponse = await fetch('/api/admin/members.php?limit=1000');
        if (membersResponse.ok) {
            const membersData = await membersResponse.json();
            if (membersData.success) {
                populateMemberDropdown(membersData.data.members);
            }
        }
    } catch (error) {
        console.error('Error loading contribution filters:', error);
    }
}

// Populate center dropdowns
function populateCenterDropdowns(centers) {
    const centerFilter = document.getElementById('contributionCenterFilter');
    const contributionCenter = document.getElementById('contributionCenter');
    
    if (centerFilter) {
        centerFilter.innerHTML = '<option value="">All Centers</option>';
        centers.forEach(center => {
            centerFilter.innerHTML += `<option value="${center.id}">${center.name}</option>`;
        });
    }
    
    if (contributionCenter) {
        contributionCenter.innerHTML = '<option value="">Select Center</option>';
        centers.forEach(center => {
            contributionCenter.innerHTML += `<option value="${center.id}">${center.name}</option>`;
        });
    }
}

// Populate member dropdown
function populateMemberDropdown(members) {
    const memberSelect = document.getElementById('contributionMember');
    if (memberSelect) {
        memberSelect.innerHTML = '<option value="">Select Member (Optional)</option>';
        members.forEach(member => {
            memberSelect.innerHTML += `<option value="${member.id}">${member.full_name} (${member.member_id})</option>`;
        });
    }
}

// Load contributions with current filters
async function loadContributions() {
    try {
        showContributionLoading();
        
        const params = new URLSearchParams(contributionFilters);
        const response = await fetch(`/api/admin/contributions.php?${params}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            contributionData = data.data.contributions;
            contributionPagination = data.data.pagination;
            displayContributions();
            updateContributionPagination();
        } else {
            throw new Error(data.error || 'Failed to load contributions');
        }
    } catch (error) {
        console.error('Error loading contributions:', error);
        showContributionError('Failed to load contributions. Please try again.');
    }
}

// Load contribution statistics
async function loadContributionStats() {
    try {
        const response = await fetch('/api/admin/contributions.php?stats=1');
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                updateContributionStats(data.data);
            }
        }
    } catch (error) {
        console.error('Error loading contribution stats:', error);
    }
}

// Update contribution statistics display
function updateContributionStats(stats) {
    const elements = {
        contributionStatsTotal: stats.total_contributions || 0,
        contributionStatsPaid: `ETB ${formatNumber(stats.paid_amount || 0)}`,
        contributionStatsPending: `ETB ${formatNumber(stats.pending_amount || 0)}`,
        contributionStatsToday: stats.today_count || 0
    };

    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    });
}

// Display contributions in table
function displayContributions() {
    const tbody = document.getElementById('contributionsTableBody');
    if (!tbody) return;

    if (contributionData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                    No contributions found matching your criteria.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = contributionData.map(contribution => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">${escapeHtml(contribution.title || 'N/A')}</div>
                <div class="text-sm text-gray-500">${escapeHtml(contribution.description || '')}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${escapeHtml(contribution.member_name || 'Anonymous')}</div>
                <div class="text-sm text-gray-500">${escapeHtml(contribution.member_code || '')}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${escapeHtml(contribution.center_name || 'N/A')}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getTypeColor(contribution.type)}">
                    ${capitalizeFirst(contribution.type || 'other')}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">ETB ${formatNumber(contribution.amount || 0)}</div>
                <div class="text-sm text-gray-500">${contribution.currency || 'ETB'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${formatDate(contribution.contribution_date)}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getPaymentStatusColor(contribution.payment_status)}">
                    ${capitalizeFirst(contribution.payment_status || 'pending')}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                    <button onclick="editContribution(${contribution.id})" 
                            class="text-blue-600 hover:text-blue-900" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="toggleContributionPaymentStatus(${contribution.id}, '${contribution.payment_status}')" 
                            class="text-green-600 hover:text-green-900" title="Toggle Payment Status">
                        <i class="fas fa-money-check-alt"></i>
                    </button>
                    <button onclick="deleteContribution(${contribution.id}, '${escapeHtml(contribution.title)}')" 
                            class="text-red-600 hover:text-red-900" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Show loading state for contributions table
function showContributionLoading() {
    const tbody = document.getElementById('contributionsTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading contributions...
                </td>
            </tr>
        `;
    }
}

// Show error message for contributions
function showContributionError(message) {
    const tbody = document.getElementById('contributionsTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-6 py-4 text-center text-red-500">
                    <i class="fas fa-exclamation-triangle mr-2"></i>${message}
                </td>
            </tr>
        `;
    }
}

// Update contribution pagination
function updateContributionPagination() {
    // Update showing text
    const showingStart = document.getElementById('contributionShowingStart');
    const showingEnd = document.getElementById('contributionShowingEnd');
    const total = document.getElementById('contributionTotal');
    
    if (showingStart && showingEnd && total) {
        const start = (contributionPagination.page - 1) * contributionPagination.limit + 1;
        const end = Math.min(contributionPagination.page * contributionPagination.limit, contributionPagination.total);
        
        showingStart.textContent = contributionPagination.total > 0 ? start : 0;
        showingEnd.textContent = end;
        total.textContent = contributionPagination.total;
    }

    // Update pagination buttons
    const paginationContainer = document.getElementById('contributionPagination');
    if (paginationContainer) {
        paginationContainer.innerHTML = generatePaginationButtons(
            contributionPagination.page,
            contributionPagination.pages,
            'goToContributionPage'
        );
    }
}

// Navigate to specific contribution page
function goToContributionPage(page) {
    contributionFilters.page = page;
    loadContributions();
}

// Previous contribution page
function previousContributionPage() {
    if (contributionPagination.page > 1) {
        goToContributionPage(contributionPagination.page - 1);
    }
}

// Next contribution page
function nextContributionPage() {
    if (contributionPagination.page < contributionPagination.pages) {
        goToContributionPage(contributionPagination.page + 1);
    }
}

// Sort contributions
function sortContributions(column) {
    if (contributionSortState.column === column) {
        contributionSortState.direction = contributionSortState.direction === 'ASC' ? 'DESC' : 'ASC';
    } else {
        contributionSortState.column = column;
        contributionSortState.direction = 'ASC';
    }
    
    contributionFilters.order_by = column;
    contributionFilters.order_dir = contributionSortState.direction;
    contributionFilters.page = 1;
    
    loadContributions();
}

// Open add contribution modal
function openAddContributionModal() {
    currentContributionId = null;
    document.getElementById('contributionModalTitle').textContent = 'Add Contribution';
    document.getElementById('contributionSubmitText').textContent = 'Save Contribution';
    
    // Reset form
    document.getElementById('contributionForm').reset();
    document.getElementById('contributionId').value = '';
    
    // Set default date to today
    document.getElementById('contributionDate').value = new Date().toISOString().split('T')[0];
    
    // Show modal
    document.getElementById('contributionModal').classList.remove('hidden');
}

// Edit contribution
async function editContribution(id) {
    try {
        const response = await fetch(`/api/admin/contributions.php?id=${id}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        if (data.success) {
            currentContributionId = id;
            populateContributionForm(data.data);
            
            document.getElementById('contributionModalTitle').textContent = 'Edit Contribution';
            document.getElementById('contributionSubmitText').textContent = 'Update Contribution';
            document.getElementById('contributionModal').classList.remove('hidden');
        } else {
            throw new Error(data.error || 'Failed to load contribution');
        }
    } catch (error) {
        console.error('Error loading contribution:', error);
        showNotification('Failed to load contribution details', 'error');
    }
}

// Populate contribution form with data
function populateContributionForm(contribution) {
    const fields = {
        contributionId: contribution.id,
        contributionTitle: contribution.title,
        contributionMember: contribution.member_id,
        contributionCenter: contribution.center_id,
        contributionType: contribution.type,
        contributionAmount: contribution.amount,
        contributionDate: contribution.contribution_date,
        contributionPaymentMethod: contribution.payment_method,
        contributionPaymentStatus: contribution.payment_status,
        contributionReference: contribution.reference_number,
        contributionDescription: contribution.description,
        contributionNotes: contribution.notes
    };

    Object.entries(fields).forEach(([fieldId, value]) => {
        const field = document.getElementById(fieldId);
        if (field && value !== null && value !== undefined) {
            field.value = value;
        }
    });
}

// Handle contribution form submission
async function handleContributionSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const contributionData = Object.fromEntries(formData.entries());
    
    // Add ID if editing
    if (currentContributionId) {
        contributionData.id = currentContributionId;
    }
    
    try {
        const method = currentContributionId ? 'PUT' : 'POST';
        const response = await fetch('/api/admin/contributions.php', {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(contributionData)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            closeContributionModal();
            loadContributions();
            loadContributionStats();
            showNotification(
                currentContributionId ? 'Contribution updated successfully' : 'Contribution added successfully',
                'success'
            );
        } else {
            throw new Error(data.errors ? data.errors.join(', ') : 'Failed to save contribution');
        }
    } catch (error) {
        console.error('Error saving contribution:', error);
        showNotification(error.message, 'error');
    }
}

// Close contribution modal
function closeContributionModal() {
    document.getElementById('contributionModal').classList.add('hidden');
    currentContributionId = null;
}

// Delete contribution
function deleteContribution(id, title) {
    currentContributionId = id;
    document.getElementById('deleteContributionTitle').textContent = title;
    document.getElementById('deleteContributionModal').classList.remove('hidden');
}

// Confirm delete contribution
async function confirmDeleteContribution() {
    if (!currentContributionId) return;
    
    try {
        const response = await fetch(`/api/admin/contributions.php?id=${currentContributionId}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            closeDeleteContributionModal();
            loadContributions();
            loadContributionStats();
            showNotification('Contribution deleted successfully', 'success');
        } else {
            throw new Error(data.error || 'Failed to delete contribution');
        }
    } catch (error) {
        console.error('Error deleting contribution:', error);
        showNotification(error.message, 'error');
    }
}

// Close delete contribution modal
function closeDeleteContributionModal() {
    document.getElementById('deleteContributionModal').classList.add('hidden');
    currentContributionId = null;
}

// Toggle contribution payment status
async function toggleContributionPaymentStatus(id, currentStatus) {
    const newStatus = currentStatus === 'paid' ? 'pending' : 'paid';
    
    try {
        const response = await fetch('/api/admin/contributions.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                payment_status: newStatus
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            loadContributions();
            loadContributionStats();
            showNotification(`Payment status updated to ${newStatus}`, 'success');
        } else {
            throw new Error(data.error || 'Failed to update payment status');
        }
    } catch (error) {
        console.error('Error updating payment status:', error);
        showNotification(error.message, 'error');
    }
}

// Export contributions to CSV
async function exportContributions() {
    try {
        const params = new URLSearchParams({
            ...contributionFilters,
            export: 'csv',
            limit: 10000 // Export all matching records
        });
        
        const response = await fetch(`/api/admin/contributions.php?${params}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `contributions_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showNotification('Contributions exported successfully', 'success');
    } catch (error) {
        console.error('Error exporting contributions:', error);
        showNotification('Failed to export contributions', 'error');
    }
}

// Open contribution analytics modal
function openContributionAnalyticsModal() {
    document.getElementById('contributionAnalyticsModal').classList.remove('hidden');
    loadContributionAnalytics();
}

// Close contribution analytics modal
function closeContributionAnalyticsModal() {
    document.getElementById('contributionAnalyticsModal').classList.add('hidden');
}

// Load contribution analytics
async function loadContributionAnalytics() {
    try {
        const response = await fetch('/api/admin/contributions.php?analytics=1');
        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                displayContributionAnalytics(data.data);
            }
        }
    } catch (error) {
        console.error('Error loading contribution analytics:', error);
    }
}

// Display contribution analytics
function displayContributionAnalytics(analytics) {
    // Update summary statistics
    document.getElementById('analyticsTotal').textContent = analytics.total_contributions || 0;
    document.getElementById('analyticsAmount').textContent = `ETB ${formatNumber(analytics.total_amount || 0)}`;
    document.getElementById('analyticsAverage').textContent = `ETB ${formatNumber(analytics.avg_amount || 0)}`;
    document.getElementById('analyticsToday').textContent = analytics.today_count || 0;

    // Create charts
    createContributionTypeChart(analytics.by_type || []);
    createContributionTrendsChart(analytics.monthly_trends || []);
    createPaymentStatusChart(analytics);
    createCenterContributionsChart(analytics.by_center || []);
}

// Create contribution type chart
function createContributionTypeChart(data) {
    const ctx = document.getElementById('contributionTypeChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => capitalizeFirst(item.type)),
            datasets: [{
                data: data.map(item => item.total_amount),
                backgroundColor: [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'botTesfaye'
                }
            }
        }
    });
}

// Create contribution trends chart
function createContributionTrendsChart(data) {
    const ctx = document.getElementById('contributionTrendsChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.month),
            datasets: [{
                label: 'Amount',
                data: data.map(item => item.total_amount),
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Create payment status chart
function createPaymentStatusChart(analytics) {
    const ctx = document.getElementById('paymentStatusChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Paid', 'Pending'],
            datasets: [{
                data: [analytics.paid_amount || 0, analytics.pending_amount || 0],
                backgroundColor: ['#10B981', '#F59E0B']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'botTesfaye'
                }
            }
        }
    });
}

// Create center contributions chart
function createCenterContributionsChart(data) {
    const ctx = document.getElementById('centerContributionsChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.center_name),
            datasets: [{
                label: 'Total Amount',
                data: data.map(item => item.total_amount),
                backgroundColor: '#3B82F6'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Export contribution analytics
function exportContributionAnalytics(format) {
    // Implementation for exporting analytics data
    showNotification('Analytics export feature coming soon', 'info');
}

// Print contribution analytics
function printContributionAnalytics() {
    window.print();
}

// Utility functions
function getTypeColor(type) {
    const colors = {
        financial: 'bg-blue-100 text-blue-800',
        material: 'bg-green-100 text-green-800',
        service: 'bg-purple-100 text-purple-800',
        other: 'bg-gray-100 text-gray-800'
    };
    return colors[type] || colors.other;
}

function getPaymentStatusColor(status) {
    const colors = {
        paid: 'bg-green-100 text-green-800',
        pending: 'bg-yellow-100 text-yellow-800',
        partial: 'bg-blue-100 text-blue-800',
        cancelled: 'bg-red-100 text-red-800'
    };
    return colors[status] || colors.pending;
}

function capitalizeFirst(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on a page with contribution elements
    if (document.getElementById('contributionsTableBody')) {
        initializeContributions();
    }
});