/**
 * Center Member Photos Dashboard JavaScript
 * Comprehensive photo management with center-based access control
 */

class CenterMemberPhotosDashboard {
    constructor() {
        this.currentUser = null;
        this.userCenters = [];
        this.currentMembers = [];
        this.currentPage = 1;
        this.itemsPerPage = 20;
        this.selectedMember = null;
        this.bulkDropzone = null;
        
        this.init();
    }
    
    async init() {
        try {
            await this.checkAuthentication();
            await this.loadUserCenters();
            await this.loadPhotoStats();
            await this.loadMemberPhotos();
            this.setupEventListeners();
            this.initializeBulkUpload();
        } catch (error) {
            console.error('Dashboard initialization error:', error);
            this.showError('Failed to initialize dashboard');
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
            
            // Check if user has admin privileges
            if (!['admin', 'superadmin'].includes(this.currentUser.role)) {
                this.showError('Insufficient privileges');
                return;
            }
            
            // Update UI with user info
            document.getElementById('adminName').textContent = this.currentUser.full_name || this.currentUser.username;
            
        } catch (error) {
            console.error('Authentication check failed:', error);
            window.location.href = 'admin-login.html';
        }
    }
    
    async loadUserCenters() {
        try {
            const response = await fetch('api/admin/center-member-photos.php?action=centers');
            const data = await response.json();
            
            if (data.success) {
                this.userCenters = data.centers || [];
                this.updateCenterInfo();
                this.populateCenterSelectors();
            }
        } catch (error) {
            console.error('Failed to load centers:', error);
        }
    }
    
    updateCenterInfo() {
        const centerInfo = document.getElementById('centerInfo');
        
        if (this.currentUser.role === 'superadmin') {
            centerInfo.textContent = 'All Centers Access';
        } else if (this.userCenters.length === 1) {
            centerInfo.textContent = `Managing: ${this.userCenters[0].name}`;
        } else if (this.userCenters.length > 1) {
            centerInfo.textContent = `Managing ${this.userCenters.length} Centers`;
        } else {
            centerInfo.textContent = 'No center assignments';
        }
    }
    
    populateCenterSelectors() {
        const selectors = ['centerFilter'];
        
        selectors.forEach(selectorId => {
            const select = document.getElementById(selectorId);
            if (select) {
                // Clear existing options (except first one)
                select.innerHTML = '<option value="">All Centers</option>';
                
                // Add center options
                this.userCenters.forEach(center => {
                    const option = document.createElement('option');
                    option.value = center.id;
                    option.textContent = center.name;
                    select.appendChild(option);
                });
            }
        });
    }
    
    async loadPhotoStats() {
        try {
            const selectedCenter = document.getElementById('centerFilter')?.value || '';
            const url = `api/admin/center-member-photos.php?action=stats${selectedCenter ? '&center_id=' + selectedCenter : ''}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success && data.stats) {
                const stats = data.stats;
                document.getElementById('totalMembers').textContent = stats.total_members || 0;
                document.getElementById('membersWithPhotos').textContent = stats.members_with_photos || 0;
                document.getElementById('membersWithoutPhotos').textContent = stats.members_without_photos || 0;
                document.getElementById('photoCoverage').textContent = `${stats.photo_coverage_percentage || 0}% coverage`;
                document.getElementById('storageUsed').textContent = stats.total_storage_used_formatted || '0 B';
            }
        } catch (error) {
            console.error('Failed to load photo stats:', error);
        }
    }
    
    async loadMemberPhotos(page = 1, search = '', centerId = '', hasPhoto = '') {
        this.showLoading(true);
        
        try {
            const params = new URLSearchParams({
                action: 'list',
                page: page,
                limit: this.itemsPerPage
            });
            
            if (search) params.append('search', search);
            if (centerId) params.append('center_id', centerId);
            if (hasPhoto) params.append('has_photo', hasPhoto);
            
            const response = await fetch(`api/admin/center-member-photos.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.currentMembers = data.members;
                this.displayMembers(data.members);
                this.displayPagination(data.pagination);
                this.populateUploadMemberSelect(data.members);
            } else {
                this.showError(data.error || 'Failed to load member photos');
            }
        } catch (error) {
            console.error('Failed to load member photos:', error);
            this.showError('Failed to load member photos');
        } finally {
            this.showLoading(false);
        }
    }
    
    displayMembers(members) {
        const viewMode = document.getElementById('viewMode').value;
        
        if (viewMode === 'grid') {
            this.displayGridView(members);
            document.getElementById('gridView').classList.remove('hidden');
            document.getElementById('listView').classList.add('hidden');
        } else {
            this.displayListView(members);
            document.getElementById('gridView').classList.add('hidden');
            document.getElementById('listView').classList.remove('hidden');
        }
    }
    
    displayGridView(members) {
        const container = document.getElementById('gridView');
        
        if (members.length === 0) {
            container.innerHTML = '<div class="col-span-full text-center text-gray-400 py-8">No members found</div>';
            return;
        }
        
        container.innerHTML = members.map(member => `
            <div class="photo-item glass-card photo-card" onclick="dashboard.viewMemberPhoto(${member.id})">
                ${member.has_photo ? 
                    `<img src="${member.thumbnails?.medium || member.photo_url}" alt="${member.full_name}" class="w-full h-full object-cover">` :
                    `<div class="photo-placeholder w-full h-full">
                        <i class="fas fa-user text-4xl"></i>
                    </div>`
                }
                <div class="photo-overlay">
                    <div class="text-center">
                        <h4 class="font-semibold text-white mb-1">${member.full_name}</h4>
                        <p class="text-sm text-gray-300">${member.center_name || 'No Center'}</p>
                        <div class="mt-3 flex space-x-2">
                            ${!member.has_photo ? 
                                `<button onclick="event.stopPropagation(); dashboard.uploadPhotoForMember(${member.id})" 
                                         class="bg-green-500 hover:bg-green-600 px-3 py-1 rounded text-sm">
                                    <i class="fas fa-upload"></i>
                                </button>` :
                                `<button onclick="event.stopPropagation(); dashboard.replacePhotoForMember(${member.id})" 
                                         class="bg-blue-500 hover:bg-blue-600 px-3 py-1 rounded text-sm">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <button onclick="event.stopPropagation(); dashboard.deletePhotoForMember(${member.id})" 
                                        class="bg-red-500 hover:bg-red-600 px-3 py-1 rounded text-sm">
                                    <i class="fas fa-trash"></i>
                                </button>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    displayListView(members) {
        const tbody = document.getElementById('listViewBody');
        
        if (members.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                        No members found
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = members.map(member => `
            <tr class="border-b border-white border-opacity-10 hover:bg-white hover:bg-opacity-5">
                <td class="px-4 py-3">
                    ${member.has_photo ? 
                        `<img src="${member.thumbnails?.small || member.photo_url}" alt="${member.full_name}" 
                             class="w-12 h-12 rounded-full object-cover cursor-pointer" 
                             onclick="dashboard.viewMemberPhoto(${member.id})">` :
                        `<div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center">
                            <i class="fas fa-user text-gray-500"></i>
                        </div>`
                    }
                </td>
                <td class="px-4 py-3">
                    <div class="font-semibold">${member.full_name}</div>
                    <div class="text-sm text-gray-300">${member.email}</div>
                    <div class="text-xs text-gray-400">${member.member_id || 'No ID'}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-sm">${member.center_name || 'No Center'}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-sm">${member.photo_uploaded_at ? this.formatDate(member.photo_uploaded_at) : 'No photo'}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-sm">${member.photo_file_size ? this.formatFileSize(member.photo_file_size) : '-'}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex space-x-2">
                        ${member.has_photo ? `
                            <button onclick="dashboard.viewMemberPhoto(${member.id})" 
                                    class="text-blue-400 hover:text-blue-300" title="View Photo">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="dashboard.replacePhotoForMember(${member.id})" 
                                    class="text-green-400 hover:text-green-300" title="Replace Photo">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button onclick="dashboard.deletePhotoForMember(${member.id})" 
                                    class="text-red-400 hover:text-red-300" title="Delete Photo">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : `
                            <button onclick="dashboard.uploadPhotoForMember(${member.id})" 
                                    class="text-green-400 hover:text-green-300" title="Upload Photo">
                                <i class="fas fa-upload"></i>
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `).join('');
    }
    
    displayPagination(pagination) {
        const container = document.getElementById('pagination');
        
        if (!pagination || pagination.pages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        const { page, pages, total } = pagination;
        
        container.innerHTML = `
            <div class="text-sm text-gray-300">
                Showing ${((page - 1) * this.itemsPerPage) + 1} to ${Math.min(page * this.itemsPerPage, total)} of ${total} results
            </div>
            <div class="flex space-x-2">
                <button onclick="dashboard.loadMemberPhotos(${page - 1})" 
                        ${page <= 1 ? 'disabled' : ''} 
                        class="px-3 py-2 border border-white/30 rounded-lg hover:bg-white/10 disabled:opacity-50">
                    Previous
                </button>
                <span class="px-3 py-2 bg-blue-500 text-white rounded-lg">
                    ${page} of ${pages}
                </span>
                <button onclick="dashboard.loadMemberPhotos(${page + 1})" 
                        ${page >= pages ? 'disabled' : ''} 
                        class="px-3 py-2 border border-white/30 rounded-lg hover:bg-white/10 disabled:opacity-50">
                    Next
                </button>
            </div>
        `;
    }
    
    populateUploadMemberSelect(members) {
        const select = document.getElementById('uploadMemberSelect');
        select.innerHTML = '<option value="">Choose a member...</option>';
        
        // Only show members without photos for upload
        const membersWithoutPhotos = members.filter(member => !member.has_photo);
        
        membersWithoutPhotos.forEach(member => {
            const option = document.createElement('option');
            option.value = member.id;
            option.textContent = `${member.full_name} (${member.center_name || 'No Center'})`;
            select.appendChild(option);
        });
    }
    
    setupEventListeners() {
        // View mode change
        document.getElementById('viewMode').addEventListener('change', () => {
            this.displayMembers(this.currentMembers);
        });
        
        // Photo file input change
        document.getElementById('photoFileInput').addEventListener('change', (e) => {
            this.handlePhotoSelection(e.target.files[0]);
        });
        
        // Drag and drop for single photo upload
        const dropZone = document.getElementById('photoDropZone');
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handlePhotoSelection(files[0]);
            }
        });
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.searchMembers();
            }
        });
    }
    
    initializeBulkUpload() {
        // Initialize Dropzone for bulk upload
        Dropzone.autoDiscover = false;
        
        this.bulkDropzone = new Dropzone('#bulkDropzone', {
            url: 'api/admin/center-member-photos.php?action=bulk-upload',
            paramName: 'photos',
            maxFilesize: 5, // MB
            acceptedFiles: 'image/*',
            addRemoveLinks: true,
            parallelUploads: 3,
            uploadMultiple: true,
            autoProcessQueue: false,
            
            init: function() {
                const dropzone = this;
                
                // Add member selection for each file
                dropzone.on('addedfile', function(file) {
                    const memberSelect = document.createElement('select');
                    memberSelect.className = 'w-full mt-2 p-2 border rounded text-black';
                    memberSelect.innerHTML = '<option value="">Select member...</option>';
                    
                    // Populate with members without photos
                    dashboard.currentMembers.filter(m => !m.has_photo).forEach(member => {
                        const option = document.createElement('option');
                        option.value = member.id;
                        option.textContent = member.full_name;
                        memberSelect.appendChild(option);
                    });
                    
                    file.previewElement.appendChild(memberSelect);
                    file.memberSelect = memberSelect;
                });
                
                // Process queue when upload button is clicked
                document.getElementById('processBulkUpload')?.addEventListener('click', function() {
                    dropzone.processQueue();
                });
            },
            
            sending: function(file, xhr, formData) {
                const memberId = file.memberSelect?.value;
                if (memberId) {
                    formData.append('member_ids[]', memberId);
                }
            },
            
            success: function(file, response) {
                if (response.success) {
                    dashboard.showSuccess('Bulk upload completed successfully');
                    dashboard.loadMemberPhotos();
                    dashboard.loadPhotoStats();
                } else {
                    dashboard.showError(response.error || 'Upload failed');
                }
            }
        });
    }
    
    handlePhotoSelection(file) {
        if (!file) return;
        
        // Validate file
        if (!file.type.startsWith('image/')) {
            this.showError('Please select a valid image file');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            this.showError('File size must be less than 5MB');
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('previewImage').src = e.target.result;
            document.getElementById('photoPreview').classList.remove('hidden');
            document.getElementById('uploadPhotoBtn').disabled = false;
        };
        reader.readAsDataURL(file);
        
        this.selectedFile = file;
    }
    
    async uploadPhoto() {
        const memberId = document.getElementById('uploadMemberSelect').value;
        if (!memberId) {
            this.showError('Please select a member');
            return;
        }
        
        if (!this.selectedFile) {
            this.showError('Please select a photo');
            return;
        }
        
        const formData = new FormData();
        formData.append('member_id', memberId);
        formData.append('photo', this.selectedFile);
        
        try {
            this.showLoading(true);
            
            const response = await fetch('api/admin/center-member-photos.php?action=upload', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Photo uploaded successfully');
                this.closePhotoUploadModal();
                await this.loadMemberPhotos();
                await this.loadPhotoStats();
            } else {
                this.showError(data.error || 'Failed to upload photo');
            }
        } catch (error) {
            console.error('Upload failed:', error);
            this.showError('Failed to upload photo');
        } finally {
            this.showLoading(false);
        }
    }
    
    async deletePhotoForMember(memberId) {
        if (!confirm('Are you sure you want to delete this member\'s photo?')) {
            return;
        }
        
        try {
            this.showLoading(true);
            
            const response = await fetch(`api/admin/center-member-photos.php?action=photo&member_id=${memberId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Photo deleted successfully');
                await this.loadMemberPhotos();
                await this.loadPhotoStats();
            } else {
                this.showError(data.error || 'Failed to delete photo');
            }
        } catch (error) {
            console.error('Delete failed:', error);
            this.showError('Failed to delete photo');
        } finally {
            this.showLoading(false);
        }
    }
    
    async viewMemberPhoto(memberId) {
        const member = this.currentMembers.find(m => m.id == memberId);
        if (!member || !member.has_photo) {
            this.showError('No photo available for this member');
            return;
        }
        
        document.getElementById('photoViewTitle').innerHTML = 
            `<i class="fas fa-image mr-2"></i>${member.full_name}'s Photo`;
        document.getElementById('fullSizePhoto').src = member.photo_url;
        
        document.getElementById('photoDetails').innerHTML = `
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Member Name</label>
                    <p class="text-lg font-semibold">${member.full_name}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Center</label>
                    <p>${member.center_name || 'No Center'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Upload Date</label>
                    <p>${member.photo_uploaded_at ? this.formatDate(member.photo_uploaded_at) : 'Unknown'}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">File Size</label>
                    <p>${member.photo_file_size ? this.formatFileSize(member.photo_file_size) : 'Unknown'}</p>
                </div>
            </div>
        `;
        
        this.selectedMember = member;
        document.getElementById('photoViewModal').classList.add('active');
    }
    
    uploadPhotoForMember(memberId) {
        const member = this.currentMembers.find(m => m.id == memberId);
        if (member) {
            document.getElementById('uploadMemberSelect').value = memberId;
            this.showPhotoUploadModal();
        }
    }
    
    replacePhotoForMember(memberId) {
        // For replace, we can use the same upload modal
        this.uploadPhotoForMember(memberId);
    }
    
    async loadMissingPhotos() {
        try {
            const response = await fetch('api/admin/center-member-photos.php?action=missing');
            const data = await response.json();
            
            if (data.success) {
                this.displayMissingPhotos(data.members_without_photos);
            }
        } catch (error) {
            console.error('Failed to load missing photos:', error);
        }
    }
    
    displayMissingPhotos(members) {
        const container = document.getElementById('missingPhotosList');
        
        if (members.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-400">All members have photos!</p>';
            return;
        }
        
        container.innerHTML = members.map(member => `
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <h4 class="font-semibold text-gray-800">${member.full_name}</h4>
                    <p class="text-sm text-gray-600">${member.email}</p>
                    <p class="text-xs text-gray-500">${member.center_name || 'No Center'}</p>
                </div>
                <button onclick="dashboard.uploadPhotoForMember(${member.id})" 
                        class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                    <i class="fas fa-upload mr-2"></i>Upload Photo
                </button>
            </div>
        `).join('');
    }
    
    // Modal Management
    showPhotoUploadModal() {
        document.getElementById('photoUploadModal').classList.add('active');
        document.getElementById('photoPreview').classList.add('hidden');
        document.getElementById('uploadPhotoBtn').disabled = true;
        this.selectedFile = null;
    }
    
    closePhotoUploadModal() {
        document.getElementById('photoUploadModal').classList.remove('active');
        document.getElementById('uploadMemberSelect').value = '';
        document.getElementById('photoFileInput').value = '';
    }
    
    showBulkUploadModal() {
        document.getElementById('bulkUploadModal').classList.add('active');
    }
    
    closeBulkUploadModal() {
        document.getElementById('bulkUploadModal').classList.remove('active');
        if (this.bulkDropzone) {
            this.bulkDropzone.removeAllFiles();
        }
    }
    
    closePhotoViewModal() {
        document.getElementById('photoViewModal').classList.remove('active');
        this.selectedMember = null;
    }
    
    showMissingPhotosModal() {
        this.loadMissingPhotos();
        document.getElementById('missingPhotosModal').classList.add('active');
    }
    
    closeMissingPhotosModal() {
        document.getElementById('missingPhotosModal').classList.remove('active');
    }
    
    // Search and Filter
    searchMembers() {
        const search = document.getElementById('searchInput').value;
        const centerId = document.getElementById('centerFilter').value;
        const hasPhoto = document.getElementById('photoStatusFilter').value;
        
        this.loadMemberPhotos(1, search, centerId, hasPhoto);
    }
    
    refreshPhotos() {
        this.loadMemberPhotos();
        this.loadPhotoStats();
    }
    
    // Utility Methods
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    formatFileSize(bytes) {
        if (bytes == 0) return '0 B';
        
        const units = ['B', 'KB', 'MB', 'GB'];
        const factor = Math.floor(Math.log(bytes) / Math.log(1024));
        
        return (bytes / Math.pow(1024, factor)).toFixed(2) + ' ' + units[factor];
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

function showPhotoUploadModal() {
    dashboard.showPhotoUploadModal();
}

function closePhotoUploadModal() {
    dashboard.closePhotoUploadModal();
}

function showBulkUploadModal() {
    dashboard.showBulkUploadModal();
}

function closeBulkUploadModal() {
    dashboard.closeBulkUploadModal();
}

function closePhotoViewModal() {
    dashboard.closePhotoViewModal();
}

function showMissingPhotosModal() {
    dashboard.showMissingPhotosModal();
}

function closeMissingPhotosModal() {
    dashboard.closeMissingPhotosModal();
}

function searchMembers() {
    dashboard.searchMembers();
}

function refreshPhotos() {
    dashboard.refreshPhotos();
}

function uploadPhoto() {
    dashboard.uploadPhoto();
}

// Initialize dashboard when page loads
let dashboard;
document.addEventListener('DOMContentLoaded', () => {
    dashboard = new CenterMemberPhotosDashboard();
});