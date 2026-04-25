/**
 * WDB Management System - Main Application JavaScript
 * Modern ES6+ JavaScript with localStorage persistence
 */

class WDBApp {
    constructor() {
        this.currentUser = null;
        this.currentLanguage = 'en';
        this.init();
    }

    init() {
        this.loadUserSession();
        this.loadLanguagePreference();
        this.setupEventListeners();
    }

    // Session Management
    loadUserSession() {
        const session = localStorage.getItem('wdb_session');
        if (session) {
            this.currentUser = JSON.parse(session);
        }
    }

    saveUserSession(user) {
        this.currentUser = user;
        localStorage.setItem('wdb_session', JSON.stringify(user));
        
        // Log session save
        if (window.auditLogger) {
            window.auditLogger.logSystemEvent('SESSION_SAVED', {
                user_id: user.id,
                username: user.username,
                role: user.role
            });
        }
    }

    logout() {
        // Log logout action
        if (window.auditLogger && this.currentUser) {
            window.auditLogger.logAuthentication('AUTH_LOGOUT', true, {
                user_id: this.currentUser.id,
                username: this.currentUser.username,
                logout_method: 'client_side'
            });
        }
        
        localStorage.removeItem('wdb_session');
        this.currentUser = null;
        window.location.href = 'index.html';
    }

    // Language Management
    loadLanguagePreference() {
        const lang = localStorage.getItem('wdb_language') || 'en';
        this.currentLanguage = lang;
    }

    setLanguage(language) {
        const oldLanguage = this.currentLanguage;
        this.currentLanguage = language;
        
        // Use safe storage function to handle quota issues
        if (!safeSetItem('wdb_language', language)) {
            console.warn('Failed to save language preference due to storage quota');
        }
        
        // Log language change
        if (window.auditLogger) {
            window.auditLogger.logSystemEvent('LANGUAGE_CHANGED', {
                old_language: oldLanguage,
                new_language: language
            });
        }
        
        this.updateUILanguage();
    }

    updateUILanguage() {
        // Update UI elements based on current language
        const elements = document.querySelectorAll('[data-translate]');
        elements.forEach(element => {
            const key = element.getAttribute('data-translate');
            element.textContent = this.translate(key);
        });
    }

    translate(key) {
        // Translation system - will be expanded with actual translations
        const translations = {
            'en': {
                'welcome': 'Welcome',
                'dashboard': 'Dashboard',
                'members': 'Members',
                'contributions': 'Contributions',
                'admin_management': 'Admin Management',
                'analytics': 'Analytics',
                'profile': 'My Profile',
                'settings': 'System Settings',
                'logs': 'Logs',
                'logout': 'Logout'
            },
            'om': {
                'welcome': 'Baga Nagaan Dhuftan',
                'dashboard': 'Gabatee',
                'members': 'Miseensota',
                'contributions': 'Gumaacha',
                'admin_management': 'Bulchiinsa Admin',
                'analytics': 'Xiinxala',
                'profile': 'Profaayilii Koo',
                'settings': 'Qindaa\'ina Sirna',
                'logs': 'Galmee',
                'logout': 'Ba\'i'
            }
        };

        return translations[this.currentLanguage]?.[key] || key;
    }

    // API Communication
    async apiCall(endpoint, method = 'GET', data = null) {
        const startTime = Date.now();
        
        const config = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (data) {
            config.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`api/${endpoint}`, config);
            const responseTime = Date.now() - startTime;
            
            // Log API call
            if (window.auditLogger) {
                window.auditLogger.logAPICall(
                    endpoint,
                    method,
                    response.status,
                    { responseTime }
                );
            }
            
            return await response.json();
        } catch (error) {
            console.error('API call failed:', error);
            
            // Log API error
            if (window.auditLogger) {
                window.auditLogger.logAPICall(
                    endpoint,
                    method,
                    0,
                    { error: error.message, responseTime: Date.now() - startTime }
                );
            }
            
            throw error;
        }
    }

    // Local Storage Data Management
    saveToStorage(key, data) {
        localStorage.setItem(`wdb_${key}`, JSON.stringify(data));
    }

    getFromStorage(key) {
        const data = localStorage.getItem(`wdb_${key}`);
        return data ? JSON.parse(data) : null;
    }

    // Event Listeners
    setupEventListeners() {
        // Language switcher
        document.addEventListener('change', (e) => {
            if (e.target.id === 'language-selector') {
                this.setLanguage(e.target.value);
            }
        });

        // Logout buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('logout-btn')) {
                this.logout();
            }
        });
    }

    // Utility Functions
    formatDate(date) {
        return new Date(date).toLocaleDateString(this.currentLanguage === 'om' ? 'om-ET' : 'en-US');
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat(this.currentLanguage === 'om' ? 'om-ET' : 'en-US', {
            style: 'currency',
            currency: 'ETB'
        }).format(amount);
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 
            type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
        }`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
}

// Initialize the application
const app = new WDBApp();

// Export for global access
window.WDBApp = app;

/**
 * Member Registration Form Handler
 * Handles multi-step registration form with validation and service area selection
 */
class RegistrationForm {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 6;
        this.formData = {};
        this.selectedServiceAreas = [];
        this.validationRules = {};
        this.draftKey = 'wdb_registration_draft';
        
        this.init();
    }
    
    init() {
        this.setupValidationRules();
        this.loadDraft();
        this.setupEventListeners();
        this.updateStepIndicator();
        this.updateProgressBar();
        this.updateNavigationButtons();
    }
    
    setupValidationRules() {
        this.validationRules = {
            1: { // Personal Information
                fullName: { required: true, minLength: 2 },
                email: { required: true, email: true },
                password: { required: true, minLength: 6 },
                dateOfBirth: { required: true, date: true },
                gender: { required: true },
                maritalStatus: { required: true },
                mobilePhone: { required: true, phone: true },
                currentAddress: { required: true, minLength: 10 }
            },
            2: { // Education & Occupation
                educationLevel: { required: true },
                fieldOfStudy: { required: true },
                occupation: { required: true },
                membershipPlan: { required: true }
            },
            3: {}, // Clergy Status - all optional
            4: { // Regional & Church Information
                region: { required: true },
                country: { required: true },
                subCity: { required: true },
                currentChurch: { required: true },
                churchWoreda: { required: true },
                currentService: { required: true }
            },
            5: { // Service Areas
                serviceAreas: { required: true, minSelection: 1, maxSelection: 3 }
            },
            6: { // Review & Submit
                agreeTerms: { required: true }
            }
        };
    }
    
    setupEventListeners() {
        // Form input listeners for real-time validation
        document.querySelectorAll('#registrationForm input, #registrationForm select, #registrationForm textarea').forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
        
        // Marital status change listener
        const maritalStatus = document.getElementById('maritalStatus');
        if (maritalStatus) {
            maritalStatus.addEventListener('change', () => this.toggleSpouseFields());
        }
        
        // Service area selection listeners
        document.querySelectorAll('.service-area-option').forEach(option => {
            option.addEventListener('click', () => this.toggleServiceArea(option));
        });
        
        // Form submission
        const form = document.getElementById('registrationForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
        
        // Auto-save draft every 30 seconds
        setInterval(() => this.saveDraft(), 30000);
    }
    
    validateField(field) {
        const stepRules = this.validationRules[this.currentStep];
        if (!stepRules || !stepRules[field.name]) return true;
        
        const rules = stepRules[field.name];
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';
        
        // Required validation
        if (rules.required && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        
        // Email validation
        if (rules.email && value && !this.isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
        
        // Phone validation
        if (rules.phone && value && !this.isValidPhone(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid phone number';
        }
        
        // Date validation
        if (rules.date && value && !this.isValidDate(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid date';
        }
        
        // Min length validation
        if (rules.minLength && value && value.length < rules.minLength) {
            isValid = false;
            errorMessage = `Minimum ${rules.minLength} characters required`;
        }
        
        this.showFieldError(field, isValid ? '' : errorMessage);
        return isValid;
    }
    
    validateStep(step) {
        const stepRules = this.validationRules[step];
        if (!stepRules) return true;
        
        let isValid = true;
        
        // Validate regular fields
        Object.keys(stepRules).forEach(fieldName => {
            if (fieldName === 'serviceAreas') return; // Handle separately
            
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field && !this.validateField(field)) {
                isValid = false;
            }
        });
        
        // Special validation for service areas
        if (step === 5) {
            const rules = stepRules.serviceAreas;
            if (rules && this.selectedServiceAreas.length < rules.minSelection) {
                this.showStepError(5, `Please select at least ${rules.minSelection} service area(s)`);
                isValid = false;
            } else if (rules && this.selectedServiceAreas.length > rules.maxSelection) {
                this.showStepError(5, `Please select no more than ${rules.maxSelection} service area(s)`);
                isValid = false;
            } else {
                this.clearStepError(5);
            }
        }
        
        return isValid;
    }
    
    showFieldError(field, message) {
        const errorElement = field.parentNode.querySelector('.error-message');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.toggle('hidden', !message);
        }
        
        field.classList.toggle('border-red-500', !!message);
        field.classList.toggle('border-gray-300', !message);
    }
    
    clearFieldError(field) {
        this.showFieldError(field, '');
    }
    
    showStepError(step, message) {
        const section = document.querySelector(`[data-section="${step}"]`);
        const errorElement = section?.querySelector('.error-message');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
        }
    }
    
    clearStepError(step) {
        const section = document.querySelector(`[data-section="${step}"]`);
        const errorElement = section?.querySelector('.error-message');
        if (errorElement) {
            errorElement.classList.add('hidden');
        }
    }
    
    toggleSpouseFields() {
        const maritalStatus = document.getElementById('maritalStatus').value;
        const spouseFields = document.getElementById('spouseFields');
        
        if (spouseFields) {
            if (maritalStatus === 'married') {
                spouseFields.classList.remove('hidden');
            } else {
                spouseFields.classList.add('hidden');
                // Clear spouse fields
                document.getElementById('spouseName').value = '';
                document.getElementById('spousePhone').value = '';
            }
        }
    }
    
    toggleServiceArea(option) {
        const serviceId = option.dataset.service;
        const checkbox = option.querySelector('input[type="checkbox"]');
        const isSelected = this.selectedServiceAreas.includes(serviceId);
        
        if (isSelected) {
            // Remove from selection
            this.selectedServiceAreas = this.selectedServiceAreas.filter(id => id !== serviceId);
            option.classList.remove('selected', 'border-blue-500', 'bg-blue-50');
            checkbox.checked = false;
        } else {
            // Add to selection (max 3)
            if (this.selectedServiceAreas.length < 3) {
                this.selectedServiceAreas.push(serviceId);
                option.classList.add('selected', 'border-blue-500', 'bg-blue-50');
                checkbox.checked = true;
            } else {
                app.showNotification('You can select maximum 3 service areas', 'warning');
                return;
            }
        }
        
        this.updateSelectedServiceAreasDisplay();
    }
    
    updateSelectedServiceAreasDisplay() {
        const container = document.getElementById('selectedServiceAreas');
        const list = document.getElementById('serviceAreasList');
        
        if (this.selectedServiceAreas.length > 0) {
            container.classList.remove('hidden');
            list.innerHTML = '';
            
            this.selectedServiceAreas.forEach((serviceId, index) => {
                const option = document.querySelector(`[data-service="${serviceId}"]`);
                const title = option.querySelector('h3').textContent;
                
                const item = document.createElement('div');
                item.className = 'flex items-center justify-between bg-blue-50 p-3 rounded-lg';
                item.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-medium">${index + 1}</span>
                        <span class="font-medium text-gray-900">${title}</span>
                    </div>
                    <button type="button" onclick="registrationForm.removeServiceArea('${serviceId}')" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                list.appendChild(item);
            });
        } else {
            container.classList.add('hidden');
        }
    }
    
    removeServiceArea(serviceId) {
        const option = document.querySelector(`[data-service="${serviceId}"]`);
        if (option) {
            this.toggleServiceArea(option);
        }
    }
    
    nextStep() {
        if (!this.validateStep(this.currentStep)) {
            app.showNotification('Please fix the errors before continuing', 'error');
            return;
        }
        
        this.collectStepData();
        
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.showStep(this.currentStep);
        }
    }
    
    previousStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }
    
    showStep(step) {
        // Hide all sections
        document.querySelectorAll('.form-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Show current section
        const currentSection = document.querySelector(`[data-section="${step}"]`);
        if (currentSection) {
            currentSection.classList.add('active');
        }
        
        // Update step indicator
        this.updateStepIndicator();
        this.updateProgressBar();
        this.updateNavigationButtons();
        
        // Update review section if on last step
        if (step === 6) {
            this.updateReviewSummary();
        }
    }
    
    updateStepIndicator() {
        document.querySelectorAll('.step').forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed', 'inactive');
            
            if (stepNumber === this.currentStep) {
                step.classList.add('active');
            } else if (stepNumber < this.currentStep) {
                step.classList.add('completed');
            } else {
                step.classList.add('inactive');
            }
        });
    }
    
    updateProgressBar() {
        const progressFill = document.getElementById('progressFill');
        if (progressFill) {
            const percentage = (this.currentStep / this.totalSteps) * 100;
            progressFill.style.width = `${percentage}%`;
        }
    }
    
    updateNavigationButtons() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        const previewBtn = document.getElementById('previewBtn');
        
        // Previous button
        if (prevBtn) {
            prevBtn.style.display = this.currentStep > 1 ? 'block' : 'none';
        }
        
        // Next/Submit buttons
        if (this.currentStep === this.totalSteps) {
            if (nextBtn) nextBtn.classList.add('hidden');
            if (submitBtn) submitBtn.classList.remove('hidden');
            if (previewBtn) previewBtn.classList.remove('hidden');
        } else {
            if (nextBtn) nextBtn.classList.remove('hidden');
            if (submitBtn) submitBtn.classList.add('hidden');
            if (previewBtn) previewBtn.classList.add('hidden');
        }
    }
    
    collectStepData() {
        const currentSection = document.querySelector(`[data-section="${this.currentStep}"]`);
        if (!currentSection) return;
        
        const inputs = currentSection.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                if (input.checked) {
                    if (input.name.includes('[]')) {
                        const name = input.name.replace('[]', '');
                        if (!this.formData[name]) this.formData[name] = [];
                        this.formData[name].push(input.value);
                    } else {
                        this.formData[input.name] = input.value;
                    }
                }
            } else {
                // Only collect non-empty values or explicitly set empty values for required fields
                if (input.value || input.hasAttribute('required')) {
                    this.formData[input.name] = input.value;
                }
            }
        });
        
        // Add selected service areas
        if (this.currentStep === 5) {
            this.formData.serviceAreas = this.selectedServiceAreas;
        }
    }
    
    collectAllFormData() {
        // Clear existing form data
        this.formData = {};
        
        // Collect data from all form sections
        for (let step = 1; step <= this.totalSteps; step++) {
            const section = document.querySelector(`[data-section="${step}"]`);
            if (!section) continue;
            
            const inputs = section.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    if (input.checked) {
                        if (input.name.includes('[]')) {
                            const name = input.name.replace('[]', '');
                            if (!this.formData[name]) this.formData[name] = [];
                            this.formData[name].push(input.value);
                        } else {
                            this.formData[input.name] = input.value;
                        }
                    }
                } else {
                    // Collect all values, including empty ones for required fields
                    if (input.value || input.hasAttribute('required')) {
                        this.formData[input.name] = input.value;
                    }
                }
            });
        }
        
        // Add selected service areas
        this.formData.serviceAreas = this.selectedServiceAreas;
        
        // Ensure required fields are present - explicit check
        const requiredFields = ['fullName', 'email', 'password'];
        requiredFields.forEach(field => {
            const input = document.querySelector(`[name="${field}"]`);
            if (input) {
                this.formData[field] = input.value;
                console.log(`Required field ${field}:`, input.value);
            } else {
                console.warn(`Required field ${field} not found in form`);
            }
        });
        
        // Debug: Log all collected form data
        console.log('All collected form data:', this.formData);
    }
    
    updateReviewSummary() {
        this.collectAllFormData();
        
        const reviewSummary = document.getElementById('reviewSummary');
        if (!reviewSummary) return;
        
        const sections = [
            {
                title: 'Personal Information',
                fields: [
                    { label: 'Full Name', key: 'fullName' },
                    { label: 'Email', key: 'email' },
                    { label: 'Date of Birth', key: 'dateOfBirth' },
                    { label: 'Gender', key: 'gender' },
                    { label: 'Marital Status', key: 'maritalStatus' },
                    { label: 'Mobile Phone', key: 'mobilePhone' }
                ]
            },
            {
                title: 'Education & Occupation',
                fields: [
                    { label: 'Education Level', key: 'educationLevel' },
                    { label: 'Field of Study', key: 'fieldOfStudy' },
                    { label: 'Occupation', key: 'occupation' },
                    { label: 'Membership Plan', key: 'membershipPlan' }
                ]
            },
            {
                title: 'Location & Church',
                fields: [
                    { label: 'Region', key: 'region' },
                    { label: 'Country', key: 'country' },
                    { label: 'City', key: 'subCity' },
                    { label: 'Current Church', key: 'currentChurch' }
                ]
            },
            {
                title: 'Service Areas',
                fields: [
                    { label: 'Selected Areas', key: 'serviceAreas', isArray: true }
                ]
            }
        ];
        
        reviewSummary.innerHTML = '';
        
        sections.forEach(section => {
            const sectionDiv = document.createElement('div');
            sectionDiv.className = 'border-b border-gray-200 pb-4';
            
            const title = document.createElement('h4');
            title.className = 'font-medium text-gray-900 mb-2';
            title.textContent = section.title;
            sectionDiv.appendChild(title);
            
            const fieldsDiv = document.createElement('div');
            fieldsDiv.className = 'grid grid-cols-1 md:grid-cols-2 gap-2 text-sm';
            
            section.fields.forEach(field => {
                const value = this.formData[field.key];
                if (value) {
                    const fieldDiv = document.createElement('div');
                    fieldDiv.innerHTML = `
                        <span class="text-gray-600">${field.label}:</span>
                        <span class="font-medium ml-2">
                            ${field.isArray ? (Array.isArray(value) ? value.join(', ') : value) : value}
                        </span>
                    `;
                    fieldsDiv.appendChild(fieldDiv);
                }
            });
            
            sectionDiv.appendChild(fieldsDiv);
            reviewSummary.appendChild(sectionDiv);
        });
    }
    
    saveDraft() {
        this.collectAllFormData();
        this.formData.currentStep = this.currentStep;
        this.formData.selectedServiceAreas = this.selectedServiceAreas;
        this.formData.savedAt = new Date().toISOString();
        
        localStorage.setItem(this.draftKey, JSON.stringify(this.formData));
        app.showNotification('Draft saved successfully', 'success');
    }
    
    loadDraft() {
        const draft = localStorage.getItem(this.draftKey);
        if (draft) {
            try {
                this.formData = JSON.parse(draft);
                this.currentStep = this.formData.currentStep || 1;
                this.selectedServiceAreas = this.formData.selectedServiceAreas || [];
                
                // Populate form fields
                Object.keys(this.formData).forEach(key => {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field && this.formData[key]) {
                        field.value = this.formData[key];
                    }
                });
                
                // Restore service area selections
                this.selectedServiceAreas.forEach(serviceId => {
                    const option = document.querySelector(`[data-service="${serviceId}"]`);
                    if (option) {
                        option.classList.add('selected', 'border-blue-500', 'bg-blue-50');
                        const checkbox = option.querySelector('input[type="checkbox"]');
                        if (checkbox) checkbox.checked = true;
                    }
                });
                
                this.updateSelectedServiceAreasDisplay();
                this.toggleSpouseFields();
                this.showStep(this.currentStep);
                
                app.showNotification('Draft loaded successfully', 'info');
            } catch (error) {
                console.error('Failed to load draft:', error);
            }
        }
    }
    
    clearDraft() {
        localStorage.removeItem(this.draftKey);
    }
    
    async handleSubmit(e) {
        e.preventDefault();
        
        if (!this.validateStep(this.currentStep)) {
            app.showNotification('Please fix the errors before submitting', 'error');
            return;
        }
        
        // Collect data from all steps, not just current step
        this.collectAllFormData();
        
        // Debug: Log the form data being sent
        console.log('Form data being sent:', this.formData);
        
        // Show loading overlay
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.remove('hidden');
        }
        
        try {
            const response = await app.apiCall('register.php', 'POST', this.formData);
            
            if (response.success) {
                this.clearDraft();
                app.showNotification('Registration successful!', 'success');
                
                // Redirect to success page
                setTimeout(() => {
                    window.location.href = 'registration-success.html';
                }, 2000);
            } else {
                app.showNotification(response.error || 'Registration failed', 'error');
            }
        } catch (error) {
            console.error('Registration error:', error);
            app.showNotification('Registration failed. Please try again.', 'error');
        } finally {
            if (loadingOverlay) {
                loadingOverlay.classList.add('hidden');
            }
        }
    }
    
    previewApplication() {
        this.collectAllFormData();
        this.updateReviewSummary();
        
        const modal = document.getElementById('previewModal');
        const content = document.getElementById('previewContent');
        
        if (modal && content) {
            // Copy review summary to preview
            const reviewSummary = document.getElementById('reviewSummary');
            if (reviewSummary) {
                content.innerHTML = reviewSummary.innerHTML;
            }
            
            modal.classList.remove('hidden');
        }
    }
    
    closePreview() {
        const modal = document.getElementById('previewModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    submitFromPreview() {
        this.closePreview();
        this.handleSubmit(new Event('submit'));
    }
    
    // Validation helper methods
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
        return phoneRegex.test(phone);
    }
    
    isValidDate(date) {
        const dateObj = new Date(date);
        return dateObj instanceof Date && !isNaN(dateObj);
    }
}

// Global functions for HTML onclick handlers
function nextStep() {
    if (window.registrationForm) {
        window.registrationForm.nextStep();
    }
}

function previousStep() {
    if (window.registrationForm) {
        window.registrationForm.previousStep();
    }
}

function saveDraft() {
    if (window.registrationForm) {
        window.registrationForm.saveDraft();
    }
}

function previewApplication() {
    if (window.registrationForm) {
        window.registrationForm.previewApplication();
    }
}

function closePreview() {
    if (window.registrationForm) {
        window.registrationForm.closePreview();
    }
}

function submitFromPreview() {
    if (window.registrationForm) {
        window.registrationForm.submitFromPreview();
    }
}

function selectMembershipPlan(plan) {
    // Remove selection from all plans
    document.querySelectorAll('[name="membershipPlan"]').forEach(radio => {
        radio.checked = false;
        radio.closest('.border').classList.remove('border-blue-500', 'bg-blue-50');
    });
    
    // Select the chosen plan
    const selectedRadio = document.getElementById(`plan${plan.charAt(0).toUpperCase() + plan.slice(1)}`);
    if (selectedRadio) {
        selectedRadio.checked = true;
        selectedRadio.closest('.border').classList.add('border-blue-500', 'bg-blue-50');
    }
}

// Contribution Analytics Functions
function openContributionAnalyticsModal() {
    document.getElementById('contributionAnalyticsModal').classList.remove('hidden');
    loadContributionAnalytics();
}

function closeContributionAnalyticsModal() {
    document.getElementById('contributionAnalyticsModal').classList.add('hidden');
}

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
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to load contribution analytics', 'error');
        }
    }
}

function displayContributionAnalytics(analytics) {
    // Update summary statistics
    const elements = {
        analyticsTotal: analytics.total_contributions || 0,
        analyticsAmount: `ETB ${formatNumber(analytics.total_amount || 0)}`,
        analyticsAverage: `ETB ${formatNumber(analytics.avg_amount || 0)}`,
        analyticsToday: analytics.today_count || 0
    };

    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    });

    // Create charts
    createContributionTypeChart(analytics.by_type || []);
    createContributionTrendsChart(analytics.monthly_trends || []);
    createPaymentStatusChart(analytics);
    createCenterContributionsChart(analytics.by_center || []);
}

function createContributionTypeChart(data) {
    const ctx = document.getElementById('contributionTypeChart');
    if (!ctx) return;

    // Destroy existing chart if it exists
    if (window.contributionTypeChartInstance) {
        window.contributionTypeChartInstance.destroy();
    }

    window.contributionTypeChartInstance = new Chart(ctx, {
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

function createContributionTrendsChart(data) {
    const ctx = document.getElementById('contributionTrendsChart');
    if (!ctx) return;

    // Destroy existing chart if it exists
    if (window.contributionTrendsChartInstance) {
        window.contributionTrendsChartInstance.destroy();
    }

    window.contributionTrendsChartInstance = new Chart(ctx, {
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

function createPaymentStatusChart(analytics) {
    const ctx = document.getElementById('paymentStatusChart');
    if (!ctx) return;

    // Destroy existing chart if it exists
    if (window.paymentStatusChartInstance) {
        window.paymentStatusChartInstance.destroy();
    }

    window.paymentStatusChartInstance = new Chart(ctx, {
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

function createCenterContributionsChart(data) {
    const ctx = document.getElementById('centerContributionsChart');
    if (!ctx) return;

    // Destroy existing chart if it exists
    if (window.centerContributionsChartInstance) {
        window.centerContributionsChartInstance.destroy();
    }

    window.centerContributionsChartInstance = new Chart(ctx, {
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

function exportContributionAnalytics(format) {
    if (window.WDBApp) {
        window.WDBApp.showNotification('Analytics export feature coming soon', 'info');
    }
}

function printContributionAnalytics() {
    window.print();
}

// Utility functions for contribution management
function capitalizeFirst(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

// Initialize registration form when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('registrationForm')) {
        window.registrationForm = new RegistrationForm();
    }
});

/**
 * Admin Management Functions
 * Handles administrator CRUD operations and center assignments
 */

// Global variables for admin management
let currentAdmins = [];
let currentCenters = [];
let adminFilters = {
    search: '',
    role: '',
    status: '',
    center: ''
};
let adminPagination = {
    currentPage: 1,
    itemsPerPage: 10,
    totalItems: 0
};

// Admin Management Functions
async function refreshAdminData() {
    try {
        await Promise.all([
            loadAdmins(),
            loadCenters(),
            updateAdminStats()
        ]);
        
        if (window.WDBApp) {
            window.WDBApp.showNotification('Admin data refreshed successfully', 'success');
        }
    } catch (error) {
        console.error('Error refreshing admin data:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to refresh admin data', 'error');
        }
    }
}

async function loadAdmins() {
    try {
        const response = await fetch('/api/superadmin/admins.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentAdmins = result.data || [];
            displayAdmins();
            updateAdminPagination();
        } else {
            throw new Error(result.error || 'Failed to load administrators');
        }
    } catch (error) {
        console.error('Error loading admins:', error);
        document.getElementById('adminsTableBody').innerHTML = 
            '<tr><td colspan="7" class="px-6 py-4 text-center text-red-500">Error loading administrators</td></tr>';
    }
}

async function loadCenters() {
    try {
        const response = await fetch('/api/superadmin/centers.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentCenters = result.data || [];
            populateCenterDropdowns();
        } else {
            throw new Error(result.error || 'Failed to load centers');
        }
    } catch (error) {
        console.error('Error loading centers:', error);
    }
}

function populateCenterDropdowns() {
    // Populate admin filter dropdown
    const adminCenterFilter = document.getElementById('adminCenterFilter');
    if (adminCenterFilter) {
        adminCenterFilter.innerHTML = '<option value="">All Centers</option>';
        currentCenters.forEach(center => {
            if (center.status === 'active') {
                const option = document.createElement('option');
                option.value = center.id;
                option.textContent = `${center.name} (${center.code})`;
                adminCenterFilter.appendChild(option);
            }
        });
    }
    
    // Populate admin modal center selection
    const adminCenters = document.getElementById('adminCenters');
    if (adminCenters) {
        adminCenters.innerHTML = '';
        currentCenters.forEach(center => {
            if (center.status === 'active') {
                const option = document.createElement('option');
                option.value = center.id;
                option.textContent = `${center.name} (${center.code})`;
                adminCenters.appendChild(option);
            }
        });
    }
}

function displayAdmins() {
    const tbody = document.getElementById('adminsTableBody');
    if (!tbody) return;
    
    // Apply filters
    let filteredAdmins = currentAdmins.filter(admin => {
        const matchesSearch = !adminFilters.search || 
            admin.full_name?.toLowerCase().includes(adminFilters.search.toLowerCase()) ||
            admin.email?.toLowerCase().includes(adminFilters.search.toLowerCase()) ||
            admin.username?.toLowerCase().includes(adminFilters.search.toLowerCase());
        
        const matchesRole = !adminFilters.role || admin.role === adminFilters.role;
        const matchesStatus = !adminFilters.status || admin.status === adminFilters.status;
        const matchesCenter = !adminFilters.center || 
            (admin.centers && admin.centers.some(center => center.id == adminFilters.center));
        
        return matchesSearch && matchesRole && matchesStatus && matchesCenter;
    });
    
    // Apply pagination
    const startIndex = (adminPagination.currentPage - 1) * adminPagination.itemsPerPage;
    const endIndex = startIndex + adminPagination.itemsPerPage;
    const paginatedAdmins = filteredAdmins.slice(startIndex, endIndex);
    
    adminPagination.totalItems = filteredAdmins.length;
    
    if (paginatedAdmins.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">No administrators found</td></tr>';
        return;
    }
    
    tbody.innerHTML = paginatedAdmins.map(admin => {
        const assignedCenters = admin.centers || [];
        const centerNames = assignedCenters.map(center => center.name).join(', ') || 'None';
        const lastLogin = admin.last_login ? new Date(admin.last_login).toLocaleDateString() : 'Never';
        
        const statusBadge = getStatusBadge(admin.status);
        const roleBadge = getRoleBadge(admin.role);
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                <i class="fas fa-user text-gray-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${admin.full_name || 'N/A'}</div>
                            <div class="text-sm text-gray-500">@${admin.username}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${admin.email}</div>
                    <div class="text-sm text-gray-500">${admin.phone || 'No phone'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${roleBadge}
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900 max-w-xs truncate" title="${centerNames}">
                        ${centerNames}
                    </div>
                    <div class="text-sm text-gray-500">${assignedCenters.length} center(s)</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${statusBadge}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${lastLogin}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex space-x-2">
                        <button onclick="editAdmin(${admin.id})" 
                                class="text-blue-600 hover:text-blue-900" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="assignCenters(${admin.id})" 
                                class="text-green-600 hover:text-green-900" title="Assign Centers">
                            <i class="fas fa-building"></i>
                        </button>
                        ${admin.role !== 'superadmin' ? `
                            <button onclick="deleteAdmin(${admin.id})" 
                                    class="text-red-600 hover:text-red-900" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    updateAdminPagination();
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>',
        'inactive': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>',
        'suspended': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Suspended</span>'
    };
    return badges[status] || badges['inactive'];
}

function getRoleBadge(role) {
    const badges = {
        'admin': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Admin</span>',
        'superadmin': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">Super Admin</span>'
    };
    return badges[role] || badges['admin'];
}

async function updateAdminStats() {
    try {
        const totalAdmins = currentAdmins.length;
        const activeAdmins = currentAdmins.filter(admin => admin.status === 'active').length;
        const superAdmins = currentAdmins.filter(admin => admin.role === 'superadmin').length;
        
        // Count unique centers managed
        const managedCenters = new Set();
        currentAdmins.forEach(admin => {
            if (admin.centers) {
                admin.centers.forEach(center => managedCenters.add(center.id));
            }
        });
        
        // Update stats display
        document.getElementById('adminStatsTotal').textContent = totalAdmins;
        document.getElementById('adminStatsActive').textContent = activeAdmins;
        document.getElementById('adminStatsSuperAdmins').textContent = superAdmins;
        document.getElementById('adminStatsCenters').textContent = managedCenters.size;
        
    } catch (error) {
        console.error('Error updating admin stats:', error);
    }
}

function updateAdminPagination() {
    const totalPages = Math.ceil(adminPagination.totalItems / adminPagination.itemsPerPage);
    const startItem = (adminPagination.currentPage - 1) * adminPagination.itemsPerPage + 1;
    const endItem = Math.min(adminPagination.currentPage * adminPagination.itemsPerPage, adminPagination.totalItems);
    
    // Update pagination info
    document.getElementById('adminShowingFrom').textContent = adminPagination.totalItems > 0 ? startItem : 0;
    document.getElementById('adminShowingTo').textContent = endItem;
    document.getElementById('totalAdmins').textContent = adminPagination.totalItems;
    
    // Generate pagination buttons
    const pagination = document.getElementById('adminsPagination');
    if (pagination) {
        let paginationHTML = '';
        
        // Previous button
        paginationHTML += `
            <button onclick="changeAdminPage(${adminPagination.currentPage - 1})" 
                    ${adminPagination.currentPage <= 1 ? 'disabled' : ''} 
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 ${adminPagination.currentPage <= 1 ? 'cursor-not-allowed' : ''}">
                <i class="fas fa-chevron-left"></i>
            </button>
        `;
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === adminPagination.currentPage) {
                paginationHTML += `
                    <button class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                        ${i}
                    </button>
                `;
            } else if (i === 1 || i === totalPages || (i >= adminPagination.currentPage - 2 && i <= adminPagination.currentPage + 2)) {
                paginationHTML += `
                    <button onclick="changeAdminPage(${i})" 
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        ${i}
                    </button>
                `;
            } else if (i === adminPagination.currentPage - 3 || i === adminPagination.currentPage + 3) {
                paginationHTML += `
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>
                `;
            }
        }
        
        // Next button
        paginationHTML += `
            <button onclick="changeAdminPage(${adminPagination.currentPage + 1})" 
                    ${adminPagination.currentPage >= totalPages ? 'disabled' : ''} 
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 ${adminPagination.currentPage >= totalPages ? 'cursor-not-allowed' : ''}">
                <i class="fas fa-chevron-right"></i>
            </button>
        `;
        
        pagination.innerHTML = paginationHTML;
    }
}

function changeAdminPage(page) {
    const totalPages = Math.ceil(adminPagination.totalItems / adminPagination.itemsPerPage);
    if (page >= 1 && page <= totalPages) {
        adminPagination.currentPage = page;
        displayAdmins();
    }
}

// Admin Modal Functions
function openAddAdminModal() {
    document.getElementById('adminModalTitle').textContent = 'Add Administrator';
    document.getElementById('adminSubmitText').textContent = 'Create Administrator';
    document.getElementById('editAdminId').value = '';
    document.getElementById('adminForm').reset();
    document.getElementById('passwordField').style.display = 'block';
    document.getElementById('adminPassword').required = true;
    document.getElementById('adminModal').classList.remove('hidden');
}

function closeAdminModal() {
    document.getElementById('adminModal').classList.add('hidden');
    document.getElementById('adminForm').reset();
}

async function editAdmin(adminId) {
    try {
        const admin = currentAdmins.find(a => a.id === adminId);
        if (!admin) {
            throw new Error('Administrator not found');
        }
        
        // Populate form
        document.getElementById('adminModalTitle').textContent = 'Edit Administrator';
        document.getElementById('adminSubmitText').textContent = 'Update Administrator';
        document.getElementById('editAdminId').value = admin.id;
        document.getElementById('adminUsername').value = admin.username || '';
        document.getElementById('adminEmail').value = admin.email || '';
        document.getElementById('adminRole').value = admin.role || '';
        document.getElementById('adminFullName').value = admin.full_name || '';
        document.getElementById('adminPhone').value = admin.phone || '';
        document.getElementById('adminPosition').value = admin.position || '';
        document.getElementById('adminDepartment').value = admin.department || '';
        
        // Hide password field for editing
        document.getElementById('passwordField').style.display = 'none';
        document.getElementById('adminPassword').required = false;
        
        // Select assigned centers
        const centerSelect = document.getElementById('adminCenters');
        Array.from(centerSelect.options).forEach(option => {
            option.selected = admin.centers && admin.centers.some(center => center.id == option.value);
        });
        
        document.getElementById('adminModal').classList.remove('hidden');
        
    } catch (error) {
        console.error('Error editing admin:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to load administrator details', 'error');
        }
    }
}

async function deleteAdmin(adminId) {
    try {
        const admin = currentAdmins.find(a => a.id === adminId);
        if (!admin) {
            throw new Error('Administrator not found');
        }
        
        // Prevent deletion of last SuperAdmin
        if (admin.role === 'superadmin') {
            const superAdminCount = currentAdmins.filter(a => a.role === 'superadmin' && a.status === 'active').length;
            if (superAdminCount <= 1) {
                if (window.WDBApp) {
                    window.WDBApp.showNotification('Cannot delete the last SuperAdmin', 'error');
                }
                return;
            }
        }
        
        if (!confirm(`Are you sure you want to delete administrator "${admin.full_name}"? This action cannot be undone.`)) {
            return;
        }
        
        const response = await fetch(`/api/superadmin/admins.php?id=${adminId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (window.WDBApp) {
                window.WDBApp.showNotification('Administrator deleted successfully', 'success');
            }
            await refreshAdminData();
        } else {
            throw new Error(result.error || 'Failed to delete administrator');
        }
        
    } catch (error) {
        console.error('Error deleting admin:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to delete administrator', 'error');
        }
    }
}

// Admin Form Submission
document.addEventListener('DOMContentLoaded', () => {
    const adminForm = document.getElementById('adminForm');
    if (adminForm) {
        adminForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            try {
                const formData = new FormData(adminForm);
                const adminId = document.getElementById('editAdminId').value;
                const isEdit = !!adminId;
                
                // Get selected centers
                const centerSelect = document.getElementById('adminCenters');
                const selectedCenters = Array.from(centerSelect.selectedOptions).map(option => parseInt(option.value));
                
                const adminData = {
                    username: document.getElementById('adminUsername').value,
                    email: document.getElementById('adminEmail').value,
                    role: document.getElementById('adminRole').value,
                    full_name: document.getElementById('adminFullName').value,
                    phone: document.getElementById('adminPhone').value,
                    position: document.getElementById('adminPosition').value || 'Administrator',
                    department: document.getElementById('adminDepartment').value || 'Administration',
                    center_ids: selectedCenters
                };
                
                if (!isEdit) {
                    adminData.password = document.getElementById('adminPassword').value;
                } else {
                    adminData.id = parseInt(adminId);
                }
                
                const url = '/api/superadmin/admins.php';
                const method = isEdit ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(adminData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (window.WDBApp) {
                        window.WDBApp.showNotification(
                            isEdit ? 'Administrator updated successfully' : 'Administrator created successfully', 
                            'success'
                        );
                    }
                    closeAdminModal();
                    await refreshAdminData();
                } else {
                    throw new Error(result.error || 'Failed to save administrator');
                }
                
            } catch (error) {
                console.error('Error saving admin:', error);
                if (window.WDBApp) {
                    window.WDBApp.showNotification('Failed to save administrator', 'error');
                }
            }
        });
    }
});

// Admin Filter Functions
function setupAdminFilters() {
    // Search filter
    const searchInput = document.getElementById('adminSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            adminFilters.search = e.target.value;
            adminPagination.currentPage = 1;
            displayAdmins();
        });
    }
    
    // Role filter
    const roleFilter = document.getElementById('adminRoleFilter');
    if (roleFilter) {
        roleFilter.addEventListener('change', (e) => {
            adminFilters.role = e.target.value;
            adminPagination.currentPage = 1;
            displayAdmins();
        });
    }
    
    // Status filter
    const statusFilter = document.getElementById('adminStatusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', (e) => {
            adminFilters.status = e.target.value;
            adminPagination.currentPage = 1;
            displayAdmins();
        });
    }
    
    // Center filter
    const centerFilter = document.getElementById('adminCenterFilter');
    if (centerFilter) {
        centerFilter.addEventListener('change', (e) => {
            adminFilters.center = e.target.value;
            adminPagination.currentPage = 1;
            displayAdmins();
        });
    }
}

function clearAdminFilters() {
    adminFilters = {
        search: '',
        role: '',
        status: '',
        center: ''
    };
    
    document.getElementById('adminSearch').value = '';
    document.getElementById('adminRoleFilter').value = '';
    document.getElementById('adminStatusFilter').value = '';
    document.getElementById('adminCenterFilter').value = '';
    
    adminPagination.currentPage = 1;
    displayAdmins();
}

async function exportAdmins() {
    try {
        // Apply current filters to export
        const filteredAdmins = currentAdmins.filter(admin => {
            const matchesSearch = !adminFilters.search || 
                admin.full_name?.toLowerCase().includes(adminFilters.search.toLowerCase()) ||
                admin.email?.toLowerCase().includes(adminFilters.search.toLowerCase()) ||
                admin.username?.toLowerCase().includes(adminFilters.search.toLowerCase());
            
            const matchesRole = !adminFilters.role || admin.role === adminFilters.role;
            const matchesStatus = !adminFilters.status || admin.status === adminFilters.status;
            const matchesCenter = !adminFilters.center || 
                (admin.centers && admin.centers.some(center => center.id == adminFilters.center));
            
            return matchesSearch && matchesRole && matchesStatus && matchesCenter;
        });
        
        // Create CSV content
        const headers = ['ID', 'Username', 'Full Name', 'Email', 'Phone', 'Role', 'Status', 'Position', 'Department', 'Assigned Centers', 'Last Login', 'Created At'];
        const csvContent = [
            headers.join(','),
            ...filteredAdmins.map(admin => [
                admin.id,
                `"${admin.username || ''}"`,
                `"${admin.full_name || ''}"`,
                `"${admin.email || ''}"`,
                `"${admin.phone || ''}"`,
                admin.role || '',
                admin.status || '',
                `"${admin.position || ''}"`,
                `"${admin.department || ''}"`,
                `"${admin.centers ? admin.centers.map(c => c.name).join('; ') : ''}"`,
                admin.last_login || '',
                admin.created_at || ''
            ].join(','))
        ].join('\n');
        
        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `wdb_administrators_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        if (window.WDBApp) {
            window.WDBApp.showNotification('Administrators exported successfully', 'success');
        }
        
    } catch (error) {
        console.error('Error exporting admins:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to export administrators', 'error');
        }
    }
}

// Center Assignment Function
async function assignCenters(adminId) {
    try {
        const admin = currentAdmins.find(a => a.id === adminId);
        if (!admin) {
            throw new Error('Administrator not found');
        }
        
        // Create a simple modal for center assignment
        const centerOptions = currentCenters
            .filter(center => center.status === 'active')
            .map(center => {
                const isAssigned = admin.centers && admin.centers.some(c => c.id === center.id);
                return `
                    <label class="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded">
                        <input type="checkbox" value="${center.id}" ${isAssigned ? 'checked' : ''} 
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm">${center.name} (${center.code})</span>
                    </label>
                `;
            }).join('');
        
        const modalHTML = `
            <div id="centerAssignmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Assign Centers to ${admin.full_name}</h3>
                        <div class="max-h-64 overflow-y-auto border rounded p-3 space-y-2">
                            ${centerOptions}
                        </div>
                        <div class="flex justify-end space-x-3 mt-6">
                            <button onclick="closeCenterAssignmentModal()" 
                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                                Cancel
                            </button>
                            <button onclick="saveCenterAssignment(${adminId})" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                Save Assignment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer);
        
    } catch (error) {
        console.error('Error opening center assignment:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to open center assignment', 'error');
        }
    }
}

function closeCenterAssignmentModal() {
    const modal = document.getElementById('centerAssignmentModal');
    if (modal) {
        modal.parentElement.remove();
    }
}

async function saveCenterAssignment(adminId) {
    try {
        const modal = document.getElementById('centerAssignmentModal');
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
        const selectedCenterIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
        
        const response = await fetch('/api/superadmin/admins/assign-center.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                admin_id: adminId,
                center_ids: selectedCenterIds
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (window.WDBApp) {
                window.WDBApp.showNotification('Center assignment updated successfully', 'success');
            }
            closeCenterAssignmentModal();
            await refreshAdminData();
        } else {
            throw new Error(result.error || 'Failed to update center assignment');
        }
        
    } catch (error) {
        console.error('Error saving center assignment:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to save center assignment', 'error');
        }
    }
}

// Initialize admin management when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Setup admin filters if on admin management page
    if (document.getElementById('adminSearch')) {
        setupAdminFilters();
        
        // Load initial data if user is authenticated and has proper role
        if (window.WDBApp && window.WDBApp.currentUser && window.WDBApp.currentUser.role === 'superadmin') {
            refreshAdminData();
        }
    }
});

/**
 * Center Management Functions
 * Handles Wiirtuu center CRUD operations and analytics
 */


let centerPagination = {
    currentPage: 1,
    itemsPerPage: 10,
    totalItems: 0
};

// Center Management Functions
async function refreshCenterData() {
    try {
        await Promise.all([
            loadCenters(),
            updateCenterStats()
        ]);
        
        if (window.WDBApp) {
            window.WDBApp.showNotification('Center data refreshed successfully', 'success');
        }
    } catch (error) {
        console.error('Error refreshing center data:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to refresh center data', 'error');
        }
    }
}

function displayCenters() {
    const tbody = document.getElementById('centersTableBody');
    if (!tbody) return;
    
    // Apply filters
    let filteredCenters = currentCenters.filter(center => {
        const matchesSearch = !centerFilters.search || 
            center.name?.toLowerCase().includes(centerFilters.search.toLowerCase()) ||
            center.code?.toLowerCase().includes(centerFilters.search.toLowerCase());
        
        const matchesStatus = !centerFilters.status || center.status === centerFilters.status;
        const matchesRegion = !centerFilters.region || center.region === centerFilters.region;
        const matchesCountry = !centerFilters.country || center.country === centerFilters.country;
        
        return matchesSearch && matchesStatus && matchesRegion && matchesCountry;
    });
    
    // Apply pagination
    const startIndex = (centerPagination.currentPage - 1) * centerPagination.itemsPerPage;
    const endIndex = startIndex + centerPagination.itemsPerPage;
    const paginatedCenters = filteredCenters.slice(startIndex, endIndex);
    
    centerPagination.totalItems = filteredCenters.length;
    
    if (paginatedCenters.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">No centers found</td></tr>';
        return;
    }
    
    tbody.innerHTML = paginatedCenters.map(center => {
        const statusBadge = getStatusBadge(center.status);
        const memberCount = center.member_count || 0;
        const adminCount = center.admin_count || 0;
        const createdDate = center.created_at ? new Date(center.created_at).toLocaleDateString() : 'N/A';
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-building text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${center.name}</div>
                            <div class="text-sm text-gray-500">${center.code}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${center.region || 'N/A'}</div>
                    <div class="text-sm text-gray-500">${center.country || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="text-sm font-medium text-gray-900">${memberCount}</div>
                    <div class="text-xs text-gray-500">members</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="text-sm font-medium text-gray-900">${adminCount}</div>
                    <div class="text-xs text-gray-500">admins</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    ${statusBadge}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${createdDate}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex space-x-2">
                        <button onclick="editCenter(${center.id})" 
                                class="text-blue-600 hover:text-blue-900" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="viewCenterDetails(${center.id})" 
                                class="text-green-600 hover:text-green-900" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${center.member_count === 0 && center.admin_count === 0 ? `
                            <button onclick="deleteCenter(${center.id})" 
                                    class="text-red-600 hover:text-red-900" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    updateCenterPagination();
}

async function updateCenterStats() {
    try {
        const totalCenters = currentCenters.length;
        const activeCenters = currentCenters.filter(center => center.status === 'active').length;
        
        // Calculate total members and assigned admins
        let totalMembers = 0;
        let assignedAdmins = 0;
        
        currentCenters.forEach(center => {
            totalMembers += center.member_count || 0;
            assignedAdmins += center.admin_count || 0;
        });
        
        // Update stats display
        document.getElementById('centerStatsTotal').textContent = totalCenters;
        document.getElementById('centerStatsActive').textContent = activeCenters;
        document.getElementById('centerStatsTotalMembers').textContent = totalMembers;
        document.getElementById('centerStatsAssignedAdmins').textContent = assignedAdmins;
        
    } catch (error) {
        console.error('Error updating center stats:', error);
    }
}

function updateCenterPagination() {
    const totalPages = Math.ceil(centerPagination.totalItems / centerPagination.itemsPerPage);
    const startItem = (centerPagination.currentPage - 1) * centerPagination.itemsPerPage + 1;
    const endItem = Math.min(centerPagination.currentPage * centerPagination.itemsPerPage, centerPagination.totalItems);
    
    // Update pagination info
    document.getElementById('centerShowingFrom').textContent = centerPagination.totalItems > 0 ? startItem : 0;
    document.getElementById('centerShowingTo').textContent = endItem;
    document.getElementById('totalCenters').textContent = centerPagination.totalItems;
    
    // Generate pagination buttons
    const pagination = document.getElementById('centersPagination');
    if (pagination) {
        let paginationHTML = '';
        
        // Previous button
        paginationHTML += `
            <button onclick="changeCenterPage(${centerPagination.currentPage - 1})" 
                    ${centerPagination.currentPage <= 1 ? 'disabled' : ''} 
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 ${centerPagination.currentPage <= 1 ? 'cursor-not-allowed' : ''}">
                <i class="fas fa-chevron-left"></i>
            </button>
        `;
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === centerPagination.currentPage) {
                paginationHTML += `
                    <button class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                        ${i}
                    </button>
                `;
            } else if (i === 1 || i === totalPages || (i >= centerPagination.currentPage - 2 && i <= centerPagination.currentPage + 2)) {
                paginationHTML += `
                    <button onclick="changeCenterPage(${i})" 
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        ${i}
                    </button>
                `;
            } else if (i === centerPagination.currentPage - 3 || i === centerPagination.currentPage + 3) {
                paginationHTML += `
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                    </span>
                `;
            }
        }
        
        // Next button
        paginationHTML += `
            <button onclick="changeCenterPage(${centerPagination.currentPage + 1})" 
                    ${centerPagination.currentPage >= totalPages ? 'disabled' : ''} 
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 ${centerPagination.currentPage >= totalPages ? 'cursor-not-allowed' : ''}">
                <i class="fas fa-chevron-right"></i>
            </button>
        `;
        
        pagination.innerHTML = paginationHTML;
    }
}

function changeCenterPage(page) {
    const totalPages = Math.ceil(centerPagination.totalItems / centerPagination.itemsPerPage);
    if (page >= 1 && page <= totalPages) {
        centerPagination.currentPage = page;
        displayCenters();
    }
}

// Center Modal Functions
function openAddCenterModal() {
    document.getElementById('centerModalTitle').textContent = 'Add Center';
    document.getElementById('centerSubmitText').textContent = 'Create Center';
    document.getElementById('editCenterId').value = '';
    document.getElementById('centerForm').reset();
    document.getElementById('centerStatus').value = 'active';
    document.getElementById('centerCountry').value = 'ethiopia';
    document.getElementById('centerModal').classList.remove('hidden');
}

function closeCenterModal() {
    document.getElementById('centerModal').classList.add('hidden');
    document.getElementById('centerForm').reset();
}

async function editCenter(centerId) {
    try {
        const center = currentCenters.find(c => c.id === centerId);
        if (!center) {
            throw new Error('Center not found');
        }
        
        // Populate form
        document.getElementById('centerModalTitle').textContent = 'Edit Center';
        document.getElementById('centerSubmitText').textContent = 'Update Center';
        document.getElementById('editCenterId').value = center.id;
        document.getElementById('centerName').value = center.name || '';
        document.getElementById('centerCode').value = center.code || '';
        document.getElementById('centerStatus').value = center.status || 'active';
        document.getElementById('centerRegion').value = center.region || '';
        document.getElementById('centerCountry').value = center.country || 'ethiopia';
        document.getElementById('centerAddress').value = center.address || '';
        
        document.getElementById('centerModal').classList.remove('hidden');
        
    } catch (error) {
        console.error('Error editing center:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to load center details', 'error');
        }
    }
}

async function deleteCenter(centerId) {
    try {
        const center = currentCenters.find(c => c.id === centerId);
        if (!center) {
            throw new Error('Center not found');
        }
        
        if (!confirm(`Are you sure you want to delete center "${center.name}"? This action cannot be undone.`)) {
            return;
        }
        
        const response = await fetch(`/api/superadmin/centers.php?id=${centerId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (window.WDBApp) {
                window.WDBApp.showNotification('Center deleted successfully', 'success');
            }
            await refreshCenterData();
        } else {
            throw new Error(result.error || 'Failed to delete center');
        }
        
    } catch (error) {
        console.error('Error deleting center:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to delete center', 'error');
        }
    }
}

async function viewCenterDetails(centerId) {
    try {
        const center = currentCenters.find(c => c.id === centerId);
        if (!center) {
            throw new Error('Center not found');
        }
        
        // Create a detailed view modal
        const modalHTML = `
            <div id="centerDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Center Details: ${center.name}</h3>
                            <button onclick="closeCenterDetailsModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-gray-900">Basic Information</h4>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Center Name</label>
                                    <p class="text-sm text-gray-900">${center.name}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Center Code</label>
                                    <p class="text-sm text-gray-900">${center.code}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <p class="text-sm">${getStatusBadge(center.status)}</p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-gray-900">Location</h4>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Region/State</label>
                                    <p class="text-sm text-gray-900">${center.region || 'Not specified'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Country</label>
                                    <p class="text-sm text-gray-900">${center.country || 'Not specified'}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Address</label>
                                    <p class="text-sm text-gray-900">${center.address || 'Not specified'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Statistics</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="bg-blue-50 p-3 rounded">
                                    <div class="text-2xl font-bold text-blue-600">${center.member_count || 0}</div>
                                    <div class="text-sm text-blue-600">Members</div>
                                </div>
                                <div class="bg-green-50 p-3 rounded">
                                    <div class="text-2xl font-bold text-green-600">${center.admin_count || 0}</div>
                                    <div class="text-sm text-green-600">Admins</div>
                                </div>
                                <div class="bg-purple-50 p-3 rounded">
                                    <div class="text-2xl font-bold text-purple-600">${center.contribution_count || 0}</div>
                                    <div class="text-sm text-purple-600">Contributions</div>
                                </div>
                                <div class="bg-yellow-50 p-3 rounded">
                                    <div class="text-2xl font-bold text-yellow-600">$${center.total_contributions || 0}</div>
                                    <div class="text-sm text-yellow-600">Total Amount</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <button onclick="closeCenterDetailsModal()" 
                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                                Close
                            </button>
                            <button onclick="editCenter(${center.id}); closeCenterDetailsModal();" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                <i class="fas fa-edit mr-2"></i>Edit Center
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer);
        
    } catch (error) {
        console.error('Error viewing center details:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to load center details', 'error');
        }
    }
}

function closeCenterDetailsModal() {
    const modal = document.getElementById('centerDetailsModal');
    if (modal) {
        modal.parentElement.remove();
    }
}

// Center Form Submission
document.addEventListener('DOMContentLoaded', () => {
    const centerForm = document.getElementById('centerForm');
    if (centerForm) {
        centerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            try {
                const centerId = document.getElementById('editCenterId').value;
                const isEdit = !!centerId;
                
                const centerData = {
                    name: document.getElementById('centerName').value,
                    code: document.getElementById('centerCode').value,
                    status: document.getElementById('centerStatus').value,
                    region: document.getElementById('centerRegion').value,
                    country: document.getElementById('centerCountry').value,
                    address: document.getElementById('centerAddress').value
                };
                
                if (isEdit) {
                    centerData.id = parseInt(centerId);
                }
                
                const url = '/api/superadmin/centers.php';
                const method = isEdit ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(centerData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (window.WDBApp) {
                        window.WDBApp.showNotification(
                            isEdit ? 'Center updated successfully' : 'Center created successfully', 
                            'success'
                        );
                    }
                    closeCenterModal();
                    await refreshCenterData();
                } else {
                    throw new Error(result.error || 'Failed to save center');
                }
                
            } catch (error) {
                console.error('Error saving center:', error);
                if (window.WDBApp) {
                    window.WDBApp.showNotification('Failed to save center', 'error');
                }
            }
        });
    }
});

// Center Filter Functions
);
    }
    
    // Status filter
    const statusFilter = document.getElementById('centerStatusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', (e) => {
            centerFilters.status = e.target.value;
            centerPagination.currentPage = 1;
            displayCenters();
        });
    }
    
    // Region filter
    const regionFilter = document.getElementById('centerRegionFilter');
    if (regionFilter) {
        regionFilter.addEventListener('change', (e) => {
            centerFilters.region = e.target.value;
            centerPagination.currentPage = 1;
            displayCenters();
        });
    }
    
    // Country filter
    const countryFilter = document.getElementById('centerCountryFilter');
    if (countryFilter) {
        countryFilter.addEventListener('change', (e) => {
            centerFilters.country = e.target.value;
            centerPagination.currentPage = 1;
            displayCenters();
        });
    }
}

;
    
    const searchInput = document.getElementById('centerSearch');
    const statusFilter = document.getElementById('centerStatusFilter');
    const regionFilter = document.getElementById('centerRegionFilter');
    const countryFilter = document.getElementById('centerCountryFilter');
    
    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = '';
    if (regionFilter) regionFilter.value = '';
    if (countryFilter) countryFilter.value = '';
    
    centerPagination.currentPage = 1;
    displayCenters();
}

async function exportCenters() {
    try {
        // Apply current filters to export
        const filteredCenters = currentCenters.filter(center => {
            const matchesSearch = !centerFilters.search || 
                center.name?.toLowerCase().includes(centerFilters.search.toLowerCase()) ||
                center.code?.toLowerCase().includes(centerFilters.search.toLowerCase());
            
            const matchesStatus = !centerFilters.status || center.status === centerFilters.status;
            const matchesRegion = !centerFilters.region || center.region === centerFilters.region;
            const matchesCountry = !centerFilters.country || center.country === centerFilters.country;
            
            return matchesSearch && matchesStatus && matchesRegion && matchesCountry;
        });
        
        // Create CSV content
        const headers = ['ID', 'Name', 'Code', 'Status', 'Region', 'Country', 'Address', 'Members', 'Admins', 'Contributions', 'Total Amount', 'Created At'];
        const csvContent = [
            headers.join(','),
            ...filteredCenters.map(center => [
                center.id,
                `"${center.name || ''}"`,
                `"${center.code || ''}"`,
                center.status || '',
                `"${center.region || ''}"`,
                `"${center.country || ''}"`,
                `"${center.address || ''}"`,
                center.member_count || 0,
                center.admin_count || 0,
                center.contribution_count || 0,
                center.total_contributions || 0,
                center.created_at || ''
            ].join(','))
        ].join('\n');
        
        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `wdb_centers_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        if (window.WDBApp) {
            window.WDBApp.showNotification('Centers exported successfully', 'success');
        }
        
    } catch (error) {
        console.error('Error exporting centers:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to export centers', 'error');
        }
    }
}

// Initialize center management when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Setup center filters if on centers page
    if (document.getElementById('centerSearch')) {
        setupCenterFilters();
        
        // Load initial data if user is authenticated and has proper role
        if (window.WDBApp && window.WDBApp.currentUser && window.WDBApp.currentUser.role === 'superadmin') {
            refreshCenterData();
        }
    }
});
// Center Analytics Functions
async function openCenterAnalyticsModal() {
    try {
        // Create analytics modal
        const modalHTML = `
            <div id="centerAnalyticsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Center Analytics Dashboard</h3>
                            <button onclick="closeCenterAnalyticsModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <!-- Analytics Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div class="bg-blue-50 p-6 rounded-lg">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                        <i class="fas fa-building text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-600">Total Centers</p>
                                        <p class="text-2xl font-semibold text-gray-900" id="analyticsTotalCenters">0</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-green-50 p-6 rounded-lg">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                                        <i class="fas fa-users text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-600">Total Members</p>
                                        <p class="text-2xl font-semibold text-gray-900" id="analyticsTotalMembers">0</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-purple-50 p-6 rounded-lg">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                        <i class="fas fa-user-shield text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-600">Total Admins</p>
                                        <p class="text-2xl font-semibold text-gray-900" id="analyticsTotalAdmins">0</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-yellow-50 p-6 rounded-lg">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                        <i class="fas fa-dollar-sign text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-600">Total Contributions</p>
                                        <p class="text-2xl font-semibold text-gray-900" id="analyticsTotalContributions">$0</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Charts Section -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <div class="bg-white p-6 rounded-lg border">
                                <h4 class="text-lg font-medium text-gray-900 mb-4">Members by Center</h4>
                                <canvas id="membersByCenterChart" width="400" height="300"></canvas>
                            </div>
                            
                            <div class="bg-white p-6 rounded-lg border">
                                <h4 class="text-lg font-medium text-gray-900 mb-4">Centers by Region</h4>
                                <canvas id="centersByRegionChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                        
                        <!-- Top Performing Centers Table -->
                        <div class="bg-white rounded-lg border">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h4 class="text-lg font-medium text-gray-900">Top Performing Centers</h4>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Center</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Members</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contributions</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Growth Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody id="topCentersTableBody" class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">Loading analytics...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <button onclick="exportCenterAnalytics()" 
                                    class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                <i class="fas fa-download mr-2"></i>Export Report
                            </button>
                            <button onclick="closeCenterAnalyticsModal()" 
                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer);
        
        // Load analytics data
        await loadCenterAnalytics();
        
    } catch (error) {
        console.error('Error opening center analytics:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to open center analytics', 'error');
        }
    }
}

function closeCenterAnalyticsModal() {
    const modal = document.getElementById('centerAnalyticsModal');
    if (modal) {
        modal.parentElement.remove();
    }
}

async function loadCenterAnalytics() {
    try {
        // Calculate analytics from current centers data
        const totalCenters = currentCenters.length;
        const activeCenters = currentCenters.filter(c => c.status === 'active').length;
        
        let totalMembers = 0;
        let totalAdmins = 0;
        let totalContributions = 0;
        
        currentCenters.forEach(center => {
            totalMembers += center.member_count || 0;
            totalAdmins += center.admin_count || 0;
            totalContributions += center.total_contributions || 0;
        });
        
        // Update summary cards
        document.getElementById('analyticsTotalCenters').textContent = totalCenters;
        document.getElementById('analyticsTotalMembers').textContent = totalMembers;
        document.getElementById('analyticsTotalAdmins').textContent = totalAdmins;
        document.getElementById('analyticsTotalContributions').textContent = `$${totalContributions.toLocaleString()}`;
        
        // Create charts
        createMembersByCenterChart();
        createCentersByRegionChart();
        
        // Update top centers table
        updateTopCentersTable();
        
    } catch (error) {
        console.error('Error loading center analytics:', error);
    }
}

function createMembersByCenterChart() {
    const ctx = document.getElementById('membersByCenterChart');
    if (!ctx) return;
    
    // Prepare data for chart
    const centerNames = currentCenters.map(c => c.name);
    const memberCounts = currentCenters.map(c => c.member_count || 0);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: centerNames,
            datasets: [{
                label: 'Members',
                data: memberCounts,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

function createCentersByRegionChart() {
    const ctx = document.getElementById('centersByRegionChart');
    if (!ctx) return;
    
    // Group centers by region
    const regionCounts = {};
    currentCenters.forEach(center => {
        const region = center.region || 'Unknown';
        regionCounts[region] = (regionCounts[region] || 0) + 1;
    });
    
    const regions = Object.keys(regionCounts);
    const counts = Object.values(regionCounts);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: regions,
            datasets: [{
                data: counts,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(236, 72, 153, 0.8)'
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

function updateTopCentersTable() {
    const tbody = document.getElementById('topCentersTableBody');
    if (!tbody) return;
    
    // Sort centers by member count and take top 5
    const topCenters = [...currentCenters]
        .sort((a, b) => (b.member_count || 0) - (a.member_count || 0))
        .slice(0, 5);
    
    if (topCenters.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No data available</td></tr>';
        return;
    }
    
    tbody.innerHTML = topCenters.map((center, index) => {
        const memberCount = center.member_count || 0;
        const contributionAmount = center.total_contributions || 0;
        const growthRate = Math.floor(Math.random() * 20) + 5; // Mock growth rate
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8">
                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-medium text-blue-600">
                                ${index + 1}
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">${center.name}</div>
                            <div class="text-sm text-gray-500">${center.code}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${memberCount}</div>
                    <div class="text-sm text-gray-500">members</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">$${contributionAmount.toLocaleString()}</div>
                    <div class="text-sm text-gray-500">total</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="text-sm font-medium text-green-600">+${growthRate}%</div>
                        <i class="fas fa-arrow-up text-green-500 ml-1"></i>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function exportCenterAnalytics() {
    try {
        // Create analytics report data
        const reportData = {
            summary: {
                totalCenters: currentCenters.length,
                activeCenters: currentCenters.filter(c => c.status === 'active').length,
                totalMembers: currentCenters.reduce((sum, c) => sum + (c.member_count || 0), 0),
                totalAdmins: currentCenters.reduce((sum, c) => sum + (c.admin_count || 0), 0),
                totalContributions: currentCenters.reduce((sum, c) => sum + (c.total_contributions || 0), 0)
            },
            centers: currentCenters.map(center => ({
                name: center.name,
                code: center.code,
                region: center.region || 'N/A',
                country: center.country || 'N/A',
                status: center.status,
                members: center.member_count || 0,
                admins: center.admin_count || 0,
                contributions: center.total_contributions || 0
            }))
        };
        
        // Create CSV content
        const headers = ['Center Name', 'Code', 'Region', 'Country', 'Status', 'Members', 'Admins', 'Total Contributions'];
        const csvContent = [
            '# WDB Center Analytics Report',
            `# Generated on: ${new Date().toLocaleString()}`,
            '',
            '# Summary Statistics',
            `Total Centers,${reportData.summary.totalCenters}`,
            `Active Centers,${reportData.summary.activeCenters}`,
            `Total Members,${reportData.summary.totalMembers}`,
            `Total Admins,${reportData.summary.totalAdmins}`,
            `Total Contributions,$${reportData.summary.totalContributions}`,
            '',
            '# Center Details',
            headers.join(','),
            ...reportData.centers.map(center => [
                `"${center.name}"`,
                `"${center.code}"`,
                `"${center.region}"`,
                `"${center.country}"`,
                center.status,
                center.members,
                center.admins,
                center.contributions
            ].join(','))
        ].join('\n');
        
        // Download CSV
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `wdb_center_analytics_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        if (window.WDBApp) {
            window.WDBApp.showNotification('Center analytics exported successfully', 'success');
        }
        
    } catch (error) {
        console.error('Error exporting center analytics:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to export center analytics', 'error');
        }
    }
}

// Function to populate region and country filter dropdowns dynamically
function populateFilterDropdowns() {
    // Populate region filter
    const regionFilter = document.getElementById('centerRegionFilter');
    if (regionFilter && currentCenters.length > 0) {
        const regions = [...new Set(currentCenters.map(c => c.region).filter(r => r))];
        
        // Clear existing options except "All Regions"
        regionFilter.innerHTML = '<option value="">All Regions</option>';
        
        regions.forEach(region => {
            const option = document.createElement('option');
            option.value = region;
            option.textContent = region;
            regionFilter.appendChild(option);
        });
    }
    
    // Populate country filter
    const countryFilter = document.getElementById('centerCountryFilter');
    if (countryFilter && currentCenters.length > 0) {
        const countries = [...new Set(currentCenters.map(c => c.country).filter(c => c))];
        
        // Clear existing options except "All Countries"
        countryFilter.innerHTML = '<option value="">All Countries</option>';
        
        countries.forEach(country => {
            const option = document.createElement('option');
            option.value = country;
            option.textContent = country.charAt(0).toUpperCase() + country.slice(1);
            countryFilter.appendChild(option);
        });
    }
}

// Update the loadCenters function to populate filter dropdowns
const originalLoadCenters = loadCenters;
loadCenters = async function() {
    await originalLoadCenters();
    populateFilterDropdowns();
};
// Additional Center Management Functions

// Function to handle bulk center operations
async function bulkCenterOperation(operation, centerIds) {
    try {
        if (!centerIds || centerIds.length === 0) {
            throw new Error('No centers selected');
        }
        
        let confirmMessage = '';
        let apiEndpoint = '';
        let method = '';
        
        switch (operation) {
            case 'activate':
                confirmMessage = `Are you sure you want to activate ${centerIds.length} center(s)?`;
                apiEndpoint = '/api/superadmin/centers.php';
                method = 'PUT';
                break;
            case 'deactivate':
                confirmMessage = `Are you sure you want to deactivate ${centerIds.length} center(s)?`;
                apiEndpoint = '/api/superadmin/centers.php';
                method = 'PUT';
                break;
            case 'delete':
                confirmMessage = `Are you sure you want to delete ${centerIds.length} center(s)? This action cannot be undone.`;
                apiEndpoint = '/api/superadmin/centers.php';
                method = 'DELETE';
                break;
            default:
                throw new Error('Invalid operation');
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Process each center
        const results = [];
        for (const centerId of centerIds) {
            try {
                const response = await fetch(`${apiEndpoint}?id=${centerId}`, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: operation !== 'delete' ? JSON.stringify({
                        id: centerId,
                        status: operation === 'activate' ? 'active' : 'inactive'
                    }) : undefined
                });
                
                const result = await response.json();
                results.push({ centerId, success: result.success, error: result.error });
                
            } catch (error) {
                results.push({ centerId, success: false, error: error.message });
            }
        }
        
        // Show results
        const successful = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success).length;
        
        if (successful > 0) {
            if (window.WDBApp) {
                window.WDBApp.showNotification(
                    `${successful} center(s) ${operation}d successfully${failed > 0 ? `, ${failed} failed` : ''}`, 
                    failed > 0 ? 'warning' : 'success'
                );
            }
        } else {
            if (window.WDBApp) {
                window.WDBApp.showNotification(`Failed to ${operation} centers`, 'error');
            }
        }
        
        // Refresh data
        await refreshCenterData();
        
    } catch (error) {
        console.error(`Error in bulk ${operation}:`, error);
        if (window.WDBApp) {
            window.WDBApp.showNotification(`Failed to ${operation} centers`, 'error');
        }
    }
}

// Function to show center assignment recommendations
async function showCenterAssignmentRecommendations() {
    try {
        // This would typically call an API to get unassigned members
        // For now, we'll show a placeholder modal
        const modalHTML = `
            <div id="centerAssignmentRecommendationsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Center Assignment Recommendations</h3>
                            <button onclick="closeCenterAssignmentRecommendationsModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div class="mb-6">
                            <p class="text-gray-600">The system can auTesfayeatically recommend center assignments for members based on their location and preferences.</p>
                        </div>
                        
                        <!-- Recommendation Settings -->
                        <div class="bg-gray-50 p-4 rounded-lg mb-6">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Assignment Criteria</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Geographic proximity</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Service area preferences</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Center capacity</span>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Language preferences</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Placeholder for recommendations -->
                        <div class="bg-white border rounded-lg p-6 text-center">
                            <i class="fas fa-robot text-4xl text-gray-400 mb-4"></i>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">AI-Powered Recommendations</h4>
                            <p class="text-gray-600 mb-4">This feature will analyze member data and suggest optimal center assignments.</p>
                            <p class="text-sm text-gray-500">Coming soon in a future update.</p>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-6">
                            <button onclick="closeCenterAssignmentRecommendationsModal()" 
                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                                Close
                            </button>
                            <button onclick="generateRecommendations()" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700" disabled>
                                <i class="fas fa-magic mr-2"></i>Generate Recommendations
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer);
        
    } catch (error) {
        console.error('Error showing center assignment recommendations:', error);
        if (window.WDBApp) {
            window.WDBApp.showNotification('Failed to load recommendations', 'error');
        }
    }
}

function closeCenterAssignmentRecommendationsModal() {
    const modal = document.getElementById('centerAssignmentRecommendationsModal');
    if (modal) {
        modal.parentElement.remove();
    }
}

function generateRecommendations() {
    if (window.WDBApp) {
        window.WDBApp.showNotification('AI recommendations feature coming soon', 'info');
    }
}

// Function to validate center data before saving
function validateCenterData(centerData) {
    const errors = [];
    
    // Required field validation
    if (!centerData.name || centerData.name.trim().length === 0) {
        errors.push('Center name is required');
    }
    
    if (!centerData.code || centerData.code.trim().length === 0) {
        errors.push('Center code is required');
    }
    
    // Code format validation (should be alphanumeric with hyphens)
    if (centerData.code && !/^[A-Z0-9-]+$/i.test(centerData.code)) {
        errors.push('Center code should only contain letters, numbers, and hyphens');
    }
    
    // Check for duplicate codes (excluding current center if editing)
    const existingCenter = currentCenters.find(c => 
        c.code.toLowerCase() === centerData.code.toLowerCase() && 
        c.id !== centerData.id
    );
    
    if (existingCenter) {
        errors.push('Center code already exists');
    }
    
    return errors;
}

// Enhanced center form submission with validation
document.addEventListener('DOMContentLoaded', () => {
    const centerForm = document.getElementById('centerForm');
    if (centerForm) {
        // Remove existing event listener and add enhanced one
        centerForm.removeEventListener('submit', centerForm._submitHandler);
        
        centerForm._submitHandler = async (e) => {
            e.preventDefault();
            
            try {
                const centerId = document.getElementById('editCenterId').value;
                const isEdit = !!centerId;
                
                const centerData = {
                    name: document.getElementById('centerName').value.trim(),
                    code: document.getElementById('centerCode').value.trim().toUpperCase(),
                    status: document.getElementById('centerStatus').value,
                    region: document.getElementById('centerRegion').value.trim(),
                    country: document.getElementById('centerCountry').value,
                    address: document.getElementById('centerAddress').value.trim()
                };
                
                if (isEdit) {
                    centerData.id = parseInt(centerId);
                }
                
                // Validate data
                const validationErrors = validateCenterData(centerData);
                if (validationErrors.length > 0) {
                    throw new Error(validationErrors.join(', '));
                }
                
                const url = '/api/superadmin/centers.php';
                const method = isEdit ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(centerData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (window.WDBApp) {
                        window.WDBApp.showNotification(
                            isEdit ? 'Center updated successfully' : 'Center created successfully', 
                            'success'
                        );
                    }
                    closeCenterModal();
                    await refreshCenterData();
                } else {
                    throw new Error(result.error || 'Failed to save center');
                }
                
            } catch (error) {
                console.error('Error saving center:', error);
                if (window.WDBApp) {
                    window.WDBApp.showNotification(error.message || 'Failed to save center', 'error');
                }
            }
        };
        
        centerForm.addEventListener('submit', centerForm._submitHandler);
    }
});

// Function to handle center code auto-generation
function generateCenterCode() {
    const nameInput = document.getElementById('centerName');
    const codeInput = document.getElementById('centerCode');
    
    if (nameInput && codeInput && nameInput.value && !codeInput.value) {
        // Generate code from name (first 3 letters + random number)
        const namePrefix = nameInput.value.trim().substring(0, 3).toUpperCase();
        const randomSuffix = Math.floor(Math.random() * 900) + 100; // 3-digit number
        const generatedCode = `WDB-${namePrefix}${randomSuffix}`;
        
        // Check if code already exists
        const existingCenter = currentCenters.find(c => c.code === generatedCode);
        if (!existingCenter) {
            codeInput.value = generatedCode;
        }
    }
}

// Add event listener for center name input to auto-generate code
document.addEventListener('DOMContentLoaded', () => {
    const centerNameInput = document.getElementById('centerName');
    if (centerNameInput) {
        centerNameInput.addEventListener('blur', generateCenterCode);
    }
});