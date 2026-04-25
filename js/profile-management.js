/**
 * Profile Management JavaScript
 * Handles user profile management interface and functionality
 */

let currentProfile = {};
let originalProfile = {};

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkAuthentication();
    loadUserProfile();
});

/**
 * Check if user is authenticated
 */
function checkAuthentication() {
    const user = getCurrentUser();
    if (!user) {
        alert('Please log in to access your profile.');
        window.location.href = 'login.html';
        return;
    }
    
    document.getElementById('userInfo').textContent = `${user.full_name} (${user.role})`;
}

/**
 * Load user profile from API
 */
async function loadUserProfile() {
    showLoading(true);
    
    try {
        const user = getCurrentUser();
        const response = await fetch(`api/user/profile.php?user_id=${user.id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentProfile = result.data;
            originalProfile = JSON.parse(JSON.stringify(result.data)); // Deep copy
            populateProfileForm();
        } else {
            showNotification('Error loading profile: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        showNotification('Failed to load profile information', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Populate form fields with current profile data
 */
function populateProfileForm() {
    // Personal Information
    setFieldValue('full_name', currentProfile.full_name || '');
    setFieldValue('username', currentProfile.username || '');
    setFieldValue('gender', currentProfile.gender || '');
    setFieldValue('role', currentProfile.role || '');
    
    // Member information
    if (currentProfile.member) {
        setFieldValue('member_id', currentProfile.member.member_id || '');
        setFieldValue('membership_status', currentProfile.member.status || '');
    } else {
        setFieldValue('member_id', 'Not assigned');
        setFieldValue('membership_status', 'Not a member');
    }
    
    // Contact Details
    setFieldValue('email', currentProfile.email || '');
    setFieldValue('phone', currentProfile.phone || '');
    
    // Load additional profile data from member record if available
    loadMemberDetails();
    
    // Preferences (load from localStorage or defaults)
    const preferences = getUserPreferences();
    setFieldValue('language', preferences.language || 'en');
    setFieldValue('timezone', preferences.timezone || 'Africa/Addis_Ababa');
    setCheckboxValue('email_notifications', preferences.email_notifications !== false);
    setCheckboxValue('sms_notifications', preferences.sms_notifications !== false);
}

/**
 * Load additional member details
 */
async function loadMemberDetails() {
    if (!currentProfile.member) return;
    
    try {
        const response = await fetch(`api/admin/members.php?id=${currentProfile.member.id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            const memberData = result.data[0];
            setFieldValue('notes', memberData.notes || '');
        }
    } catch (error) {
        console.error('Error loading member details:', error);
    }
}

/**
 * Set field value helper
 */
function setFieldValue(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.value = value;
    }
}

/**
 * Set checkbox value helper
 */
function setCheckboxValue(fieldId, value) {
    const field = document.getElementById(fieldId);
    if (field) {
        field.checked = value === true || value === 'true';
    }
}

/**
 * Show/hide tabs
 */
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.add('hidden'));
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active', 'border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    });
    
    // Show selected tab content
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.classList.remove('hidden');
    }
    
    // Add active class to selected tab button
    const selectedButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (selectedButton) {
        selectedButton.classList.add('active', 'border-blue-500', 'text-blue-600');
        selectedButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    }
}

/**
 * Save profile changes
 */
async function saveProfile() {
    showLoading(true);
    
    try {
        const profileData = collectProfileData();
        
        const response = await fetch('api/user/profile.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(profileData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Profile updated successfully!', 'success');
            
            // Update current user in localStorage
            const currentUser = getCurrentUser();
            currentUser.full_name = profileData.full_name;
            currentUser.email = profileData.email;
            currentUser.phone = profileData.phone;
            currentUser.gender = profileData.gender;
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            
            // Save preferences
            saveUserPreferences({
                language: profileData.language,
                timezone: profileData.timezone,
                email_notifications: profileData.email_notifications,
                sms_notifications: profileData.sms_notifications
            });
            
            originalProfile = JSON.parse(JSON.stringify(currentProfile)); // Update original profile
        } else {
            showNotification('Error saving profile: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error saving profile:', error);
        showNotification('Failed to save profile changes', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Collect form data into profile object
 */
function collectProfileData() {
    return {
        full_name: document.getElementById('full_name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        gender: document.getElementById('gender').value,
        notes: document.getElementById('notes').value,
        language: document.getElementById('language').value,
        timezone: document.getElementById('timezone').value,
        email_notifications: document.getElementById('email_notifications').checked,
        sms_notifications: document.getElementById('sms_notifications').checked
    };
}

/**
 * Reset profile to original values
 */
function resetProfile() {
    if (confirm('Are you sure you want to reset all changes?')) {
        currentProfile = JSON.parse(JSON.stringify(originalProfile));
        populateProfileForm();
        showNotification('Profile reset to original values', 'info');
    }
}

/**
 * Change password
 */
async function changePassword() {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Validation
    if (!currentPassword || !newPassword || !confirmPassword) {
        showNotification('Please fill in all password fields', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showNotification('New passwords do not match', 'error');
        return;
    }
    
    if (newPassword.length < 8) {
        showNotification('New password must be at least 8 characters long', 'error');
        return;
    }
    
    showLoading(true);
    
    try {
        const response = await fetch('api/user/change-password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Password changed successfully!', 'success');
            
            // Clear password fields
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
        } else {
            showNotification('Error changing password: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error changing password:', error);
        showNotification('Failed to change password', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Get user preferences from localStorage
 */
function getUserPreferences() {
    const prefsStr = localStorage.getItem('userPreferences');
    return prefsStr ? JSON.parse(prefsStr) : {};
}

/**
 * Save user preferences to localStorage
 */
function saveUserPreferences(preferences) {
    localStorage.setItem('userPreferences', JSON.stringify(preferences));
}

/**
 * Go back to dashboard
 */
function goBack() {
    const user = getCurrentUser();
    if (user) {
        switch (user.role) {
            case 'superadmin':
            case 'admin':
                window.location.href = 'dashboard.html';
                break;
            case 'user':
                window.location.href = 'member-dashboard.html';
                break;
            default:
                window.location.href = 'index.html';
        }
    } else {
        window.location.href = 'index.html';
    }
}

/**
 * Show loading overlay
 */
function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (show) {
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
    } else {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${getNotificationClasses(type)}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${getNotificationIcon(type)} mr-2"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

/**
 * Get notification CSS classes based on type
 */
function getNotificationClasses(type) {
    switch (type) {
        case 'success':
            return 'bg-green-500 text-white';
        case 'error':
            return 'bg-red-500 text-white';
        case 'warning':
            return 'bg-yellow-500 text-white';
        default:
            return 'bg-blue-500 text-white';
    }
}

/**
 * Get notification icon based on type
 */
function getNotificationIcon(type) {
    switch (type) {
        case 'success':
            return 'fa-check-circle';
        case 'error':
            return 'fa-exclamation-circle';
        case 'warning':
            return 'fa-exclamation-triangle';
        default:
            return 'fa-info-circle';
    }
}

/**
 * Get current user from session/localStorage
 */
function getCurrentUser() {
    const userStr = localStorage.getItem('currentUser') || sessionStorage.getItem('currentUser');
    return userStr ? JSON.parse(userStr) : null;
}

/**
 * Logout function
 */
async function logout() {
    try {
        await fetch('api/auth/logout.php', { method: 'POST' });
    } catch (error) {
        console.error('Logout error:', error);
    }
    
    localStorage.removeItem('currentUser');
    sessionStorage.removeItem('currentUser');
    window.location.href = 'login.html';
}

// Initialize tab functionality
document.addEventListener('DOMContentLoaded', function() {
    // Show first tab by default
    showTab('personal');
});