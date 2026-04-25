/**
 * Member Photo Handler
 * 
 * Handles member photo uploads, retrieval, and display
 */

class MemberPhotoHandler {
    constructor() {
        this.defaultAvatar = 'images/default-avatar.png';
        this.uploadEndpoint = 'api/upload-member-photo.php';
        this.registrationEndpoint = 'api/register-photo-upload.php';
        this.getEndpoint = 'api/get-member-photo.php';

        // Event listeners storage
        this.eventListeners = {
            photoUploaded: [],
            photoRemoved: [],
            photoUpdated: [],
            photoError: []
        };

        // Photo cache for performance
        this.photoCache = new Map();
        this.cacheTimeout = 3600000; // 1 hour

        // Configuration
        this.maxFileSize = 5 * 1024 * 1024; // 5MB
        this.allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        this.retryAttempts = 2;
    }

    /**
     * Add event listener
     * @param {string} eventType - Event type (photoUploaded, photoRemoved, photoUpdated, photoError)
     * @param {function} callback - Callback function
     */
    addEventListener(eventType, callback) {
        if (this.eventListeners[eventType]) {
            this.eventListeners[eventType].push(callback);
        } else {
            console.warn(`Unknown event type: ${eventType}`);
        }
    }

    /**
     * Remove event listener
     * @param {string} eventType - Event type
     * @param {function} callback - Callback function to remove
     */
    removeEventListener(eventType, callback) {
        if (this.eventListeners[eventType]) {
            this.eventListeners[eventType] = this.eventListeners[eventType].filter(
                cb => cb !== callback
            );
        }
    }

    /**
     * Dispatch custom event
     * @param {string} eventType - Event type
     * @param {object} detail - Event detail data
     */
    dispatchPhotoEvent(eventType, detail) {
        // Call registered callbacks
        if (this.eventListeners[eventType]) {
            this.eventListeners[eventType].forEach(callback => {
                try {
                    callback(detail);
                } catch (error) {
                    console.error(`Error in ${eventType} callback:`, error);
                }
            });
        }

        // Dispatch DOM event for broader compatibility
        const event = new CustomEvent(eventType, {
            detail: {
                ...detail,
                timestamp: Date.now()
            },
            bubbles: true,
            cancelable: true
        });
        document.dispatchEvent(event);
    }

    /**
     * Clear photo cache
     */
    clearPhotoCache() {
        this.photoCache.clear();
    }

    /**
     * Invalidate cache for specific member
     * @param {string} memberId - Member ID
     */
    invalidateCache(memberId) {
        this.photoCache.delete(memberId);
    }

    /**
     * Get cached photo URL
     * @param {string} memberId - Member ID
     * @returns {string|null} Cached photo URL or null
     */
    getCachedPhotoUrl(memberId) {
        const cached = this.photoCache.get(memberId);
        if (!cached) return null;

        // Check if cache expired
        if (Date.now() - cached.timestamp > this.cacheTimeout) {
            this.photoCache.delete(memberId);
            return null;
        }

        return cached.url;
    }

    /**
     * Set cached photo URL
     * @param {string} memberId - Member ID
     * @param {string} url - Photo URL
     */
    setCachedPhotoUrl(memberId, url) {
        this.photoCache.set(memberId, {
            url: url,
            timestamp: Date.now()
        });
    }

    /**
     * Compress image before upload
     * @param {File} file - Image file
     * @param {number} maxSizeMB - Maximum size in MB
     * @returns {Promise<File>} Compressed file
     */
    async compressImage(file, maxSizeMB = 2) {
        // If file is already small enough, return as-is
        if (file.size <= maxSizeMB * 1024 * 1024) {
            return file;
        }

        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onerror = () => reject(new Error('Failed to read file'));

            reader.onload = (e) => {
                const img = new Image();

                img.onerror = () => reject(new Error('Failed to load image'));

                img.onload = () => {
                    try {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');

                        // Calculate new dimensions (maintain aspect ratio)
                        let width = img.width;
                        let height = img.height;
                        const maxDimension = 2048;

                        if (width > maxDimension || height > maxDimension) {
                            if (width > height) {
                                height = (height / width) * maxDimension;
                                width = maxDimension;
                            } else {
                                width = (width / height) * maxDimension;
                                height = maxDimension;
                            }
                        }

                        canvas.width = width;
                        canvas.height = height;

                        // Draw image
                        ctx.drawImage(img, 0, 0, width, height);

                        // Convert to blob
                        canvas.toBlob((blob) => {
                            if (!blob) {
                                reject(new Error('Failed to compress image'));
                                return;
                            }

                            const compressedFile = new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });

                            resolve(compressedFile);
                        }, 'image/jpeg', 0.85);

                    } catch (error) {
                        reject(error);
                    }
                };

                img.src = e.target.result;
            };

            reader.readAsDataURL(file);
        });
    }

    /**
     * Retry function with exponential backoff
     * @param {function} fn - Function to retry
     * @param {number} maxAttempts - Maximum retry attempts
     * @returns {Promise} Result of function
     */
    async retryWithBackoff(fn, maxAttempts = 3) {
        let lastError;

        for (let attempt = 0; attempt < maxAttempts; attempt++) {
            try {
                return await fn();
            } catch (error) {
                lastError = error;

                // Don't retry on validation errors
                if (error.message.includes('validation') ||
                    error.message.includes('invalid') ||
                    error.message.includes('exceed')) {
                    throw error;
                }

                // If not last attempt, wait before retrying
                if (attempt < maxAttempts - 1) {
                    const delay = Math.pow(2, attempt) * 1000; // 1s, 2s, 4s
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            }
        }

        throw lastError;
    }

    /**
     * Load and display member photo
     */
    async loadMemberPhoto(userId, imgElementId) {
        try {
            const response = await fetch(`${this.getEndpoint}?user_id=${userId}`);
            const result = await response.json();

            if (result.success && result.data.photo_url) {
                const imgElement = document.getElementById(imgElementId);
                if (imgElement) {
                    imgElement.src = result.data.photo_url;
                    imgElement.onerror = () => {
                        imgElement.src = this.defaultAvatar;
                    };
                }
                return result.data;
            }
        } catch (error) {
            console.error('Error loading photo:', error);
            this.setDefaultAvatar(imgElementId);
            this.dispatchPhotoEvent('photoError', {
                error: error.message,
                operation: 'load',
                userId: userId
            });
        }
        return null;
    }

    /**
     * Load photo by member ID
     */
    async loadPhotoByMemberId(memberId, imgElementId) {
        try {
            const response = await fetch(`${this.getEndpoint}?member_id=${memberId}`);
            const result = await response.json();

            if (result.success && result.data.photo_url) {
                const imgElement = document.getElementById(imgElementId);
                if (imgElement) {
                    imgElement.src = result.data.photo_url;
                    imgElement.onerror = () => {
                        imgElement.src = this.defaultAvatar;
                    };
                }
                return result.data;
            }
        } catch (error) {
            console.error('Error loading photo:', error);
            this.setDefaultAvatar(imgElementId);
            this.dispatchPhotoEvent('photoError', {
                error: error.message,
                operation: 'load',
                memberId: memberId
            });
        }
        return null;
    }

    /**
     * Upload member photo
     * @param {File} file - Photo file to upload
     * @param {string} memberId - Member ID (for authenticated uploads)
     * @param {string} userId - User ID (for authenticated uploads)
     * @param {string} memberRecordId - Member record ID (for registration uploads)
     * @param {boolean} isRegistration - Whether this is a registration upload
     */
    async uploadPhoto(file, memberId = null, userId = null, memberRecordId = null, isRegistration = false) {
        try {
            console.log('[Photo Upload] Starting upload process');
            console.log('[Photo Upload] Parameters:', { memberId, userId, memberRecordId, isRegistration });
            
            // Validate file
            const validation = this.validatePhoto(file);
            if (!validation.valid) {
                const error = new Error(validation.error);
                this.dispatchPhotoEvent('photoError', {
                    error: validation.error,
                    operation: 'upload',
                    errorType: 'validation'
                });
                throw error;
            }

            console.log('[Photo Upload] File validation passed');

            // Compress image if needed
            let processedFile;
            try {
                processedFile = await this.compressImage(file);
                console.log('[Photo Upload] Image compressed successfully');
            } catch (compressionError) {
                console.warn('[Photo Upload] Compression failed, using original file:', compressionError);
                processedFile = file;
            }

            // Create form data
            const formData = new FormData();
            formData.append('photo', processedFile); // Use 'photo' for registration endpoint
            
            // Choose endpoint and parameters based on context
            let endpoint = this.uploadEndpoint;
            
            if (isRegistration && memberRecordId) {
                endpoint = this.registrationEndpoint;
                formData.append('member_record_id', memberRecordId);
                console.log('[Photo Upload] Using registration endpoint with member_record_id:', memberRecordId);
            } else {
                // Use memberPhoto for authenticated endpoint
                formData.delete('photo');
                formData.append('memberPhoto', processedFile);
                
                if (memberId) {
                    formData.append('member_id', memberId);
                    console.log('[Photo Upload] Added member_id:', memberId);
                } else if (userId) {
                    formData.append('user_id', userId);
                    console.log('[Photo Upload] Added user_id:', userId);
                } else {
                    throw new Error('Member ID or User ID is required for authenticated uploads');
                }
            }

            console.log('[Photo Upload] Sending request to:', endpoint);

            // Upload with retry
            const result = await this.retryWithBackoff(async () => {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                    
                    try {
                        const errorJson = JSON.parse(errorText);
                        if (errorJson.error) {
                            errorMessage = errorJson.error;
                        }
                    } catch (e) {
                        // Not JSON, use status text
                    }
                    
                    throw new Error(errorMessage);
                }

                return await response.json();
            }, this.retryAttempts);

            if (!result.success) {
                throw new Error(result.error || 'Upload failed');
            }

            console.log('[Photo Upload] Upload successful:', result);

            // Clear cache for this member
            if (memberId) {
                this.invalidateCache(memberId);
            }

            // Dispatch success event
            this.dispatchPhotoEvent('photoUploaded', {
                photoUrl: result.data.photo_url,
                memberId: memberId || memberRecordId || result.data.member_id,
                userId: userId,
                uploadedAt: result.data.uploaded_at || new Date().toISOString(),
                isRegistration: isRegistration
            });

            return result.data;

        } catch (error) {
            console.error('[Photo Upload] Upload failed:', error);
            this.dispatchPhotoEvent('photoError', {
                error: error.message,
                operation: 'upload',
                errorType: error.message.includes('network') ? 'network' : 'server'
            });
            throw error;
        }
    }

    /**
     * Validate photo file
     */
    validatePhoto(file) {
        // Check file size
        if (file.size > this.maxFileSize) {
            return {
                valid: false,
                error: 'File size must not exceed 5MB',
                code: 'FILE_TOO_LARGE'
            };
        }

        // Check file type
        if (!this.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                error: 'Please upload an image file (JPG, PNG, GIF)',
                code: 'INVALID_FILE_TYPE'
            };
        }

        return { valid: true };
    }

    /**
     * Set default avatar
     */
    setDefaultAvatar(imgElementId) {
        const imgElement = document.getElementById(imgElementId);
        if (imgElement) {
            imgElement.src = this.defaultAvatar;
        }
    }

    /**
     * Create photo upload widget
     */
    createPhotoWidget(containerId, userId, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const size = options.size || '150px';
        const editable = options.editable !== false;

        container.innerHTML = `
            <div class="photo-widget" style="text-align: center;">
                <div class="photo-container" style="position: relative; display: inline-block;">
                    <img id="photoWidget_${containerId}"
                         src="${this.defaultAvatar}"
                         alt="Member Photo"
                         style="width: ${size}; height: ${size}; border-radius: 50%; object-fit: cover; border: 4px solid #6366f1; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);">
                    ${editable ? `
                        <button onclick="photoHandler.triggerUpload('${containerId}')"
                                class="change-photo-btn"
                                style="margin-top: 1rem; padding: 0.5rem 1rem; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: block; margin-left: auto; margin-right: auto;">
                            📷 Change Photo
                        </button>
                    ` : ''}
                </div>
                <input type="file"
                       id="photoInput_${containerId}"
                       accept="image/*"
                       style="display: none;"
                       onchange="photoHandler.handleWidgetUpload('${containerId}', ${userId})">
            </div>
        `;

        // Load current photo
        this.loadMemberPhoto(userId, `photoWidget_${containerId}`);
    }

    /**
     * Trigger file upload
     */
    triggerUpload(containerId) {
        document.getElementById(`photoInput_${containerId}`).click();
    }

    /**
     * Handle widget upload
     */
    async handleWidgetUpload(containerId, userId) {
        const input = document.getElementById(`photoInput_${containerId}`);
        const file = input.files[0];

        if (!file) return;

        try {
            // Show loading
            const img = document.getElementById(`photoWidget_${containerId}`);
            const originalSrc = img.src;
            img.style.opacity = '0.5';

            // Upload
            const result = await this.uploadPhoto(file, null, userId);

            // Update display
            img.src = result.photo_url + '?t=' + Date.now();
            img.style.opacity = '1';

            // Show success message
            this.showMessage('Photo updated successfully!', 'success');

        } catch (error) {
            console.error('Upload error:', error);
            this.showMessage(error.message, 'error');
            img.style.opacity = '1';
        }

        // Reset input
        input.value = '';
    }

    /**
     * Show message
     */
    showMessage(message, type = 'info') {
        const colors = {
            error: '#ef4444',
            success: '#10b981',
            warning: '#f59e0b',
            info: '#3b82f6'
        };

        const icons = {
            error: '❌',
            success: '✅',
            warning: '⚠️',
            info: 'ℹ️'
        };

        // Create message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `photo-message photo-message-${type}`;
        messageDiv.innerHTML = `
            <span style="margin-right: 0.5rem;">${icons[type]}</span>
            <span>${message}</span>
        `;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${colors[type]};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
        `;

        document.body.appendChild(messageDiv);

        // Remove after 3 seconds
        setTimeout(() => {
            messageDiv.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    document.body.removeChild(messageDiv);
                }
            }, 300);
        }, 3000);
    }

    /**
     * Get photo URL for member (with caching)
     */
    async getPhotoUrl(memberId) {
        // Check cache first
        const cachedUrl = this.getCachedPhotoUrl(memberId);
        if (cachedUrl) {
            return cachedUrl;
        }

        // Fetch from API with retry
        try {
            const url = await this.retryWithBackoff(async () => {
                const response = await fetch(`${this.getEndpoint}?member_id=${memberId}`);
                const result = await response.json();

                if (result.success && result.data.photo_url) {
                    return result.data.photo_url;
                }

                return this.defaultAvatar;
            }, this.retryAttempts);

            // Cache the result
            this.setCachedPhotoUrl(memberId, url);

            return url;

        } catch (error) {
            console.error('Error getting photo URL:', error);
            this.dispatchPhotoEvent('photoError', {
                error: error.message,
                operation: 'getPhotoUrl',
                memberId: memberId
            });
            return this.defaultAvatar;
        }
    }
}


// Create global instance
const photoHandler = new MemberPhotoHandler();

// Add CSS animations
(function() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .change-photo-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
            transition: all 0.3s;
        }
    `;
    document.head.appendChild(style);
})();
