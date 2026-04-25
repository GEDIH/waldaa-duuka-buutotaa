/**
 * Internationalization (i18n) JavaScript Helper
 * Handles client-side translation and language switching
 */

class I18n {
    constructor() {
        this.currentLanguage = 'en';
        this.translations = {};
        this.supportedLanguages = [];
        this.fallbackLanguage = 'en';
        
        this.init();
    }
    
    /**
     * Initialize i18n system
     */
    async init() {
        try {
            // Load supported languages
            await this.loadSupportedLanguages();
            
            // Get user's preferred language
            const userLang = await this.getUserLanguage();
            
            // Set current language
            await this.setLanguage(userLang);
            
        } catch (error) {
            console.error('Failed to initialize i18n:', error);
            // Fallback to default language
            await this.setLanguage(this.fallbackLanguage);
        }
    }
    
    /**
     * Load supported languages from API
     */
    async loadSupportedLanguages() {
        try {
            const response = await fetch('/api/i18n/languages.php');
            const result = await response.json();
            
            if (result.success) {
                this.supportedLanguages = result.data;
            }
        } catch (error) {
            console.error('Failed to load supported languages:', error);
            // Fallback to default languages
            this.supportedLanguages = [
                { code: 'en', name: 'English', native_name: 'English', is_default: 1, text_direction: 'ltr' },
                { code: 'om', name: 'Oromo', native_name: 'Afaan Oromoo', is_default: 0, text_direction: 'ltr' }
            ];
        }
    }
    
    /**
     * Get user's current language preference
     */
    async getUserLanguage() {
        try {
            const response = await fetch('/api/user/language.php');
            const result = await response.json();
            
            if (result.success) {
                return result.data.language;
            }
        } catch (error) {
            console.error('Failed to get user language:', error);
        }
        
        // Fallback to browser language or default
        const browserLang = navigator.language.split('-')[0];
        return this.supportedLanguages.find(lang => lang.code === browserLang)?.code || this.fallbackLanguage;
    }
    
    /**
     * Set current language and load translations
     */
    async setLanguage(language) {
        if (!this.supportedLanguages.find(lang => lang.code === language)) {
            console.warn(`Language ${language} not supported, falling back to ${this.fallbackLanguage}`);
            language = this.fallbackLanguage;
        }
        
        this.currentLanguage = language;
        
        // Load translations for this language
        await this.loadTranslations(language);
        
        // Update user preference
        await this.saveUserLanguage(language);
        
        // Update UI
        this.updateUI();
        
        // Trigger language change event
        this.triggerLanguageChangeEvent(language);
    }
    
    /**
     * Load translations for specific language
     */
    async loadTranslations(language) {
        try {
            const response = await fetch(`/api/i18n/translations.php?lang=${language}`);
            const result = await response.json();
            
            if (result.success) {
                this.translations = result.data.translations;
            } else {
                console.error('Failed to load translations:', result.error);
            }
        } catch (error) {
            console.error('Failed to load translations:', error);
            this.translations = {};
        }
    }
    
    /**
     * Save user language preference
     */
    async saveUserLanguage(language) {
        try {
            await fetch('/api/user/language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ language })
            });
        } catch (error) {
            console.error('Failed to save user language:', error);
        }
    }
    
    /**
     * Translate a key
     */
    t(key, params = {}) {
        let translation = this.translations[key] || key;
        
        // Replace parameters
        Object.keys(params).forEach(param => {
            translation = translation.replace(new RegExp(`{${param}}`, 'g'), params[param]);
        });
        
        return translation;
    }
    
    /**
     * Update UI elements with translations
     */
    updateUI() {
        // Update elements with data-i18n attribute
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            const translation = this.t(key);
            
            if (element.tagName === 'INPUT' && (element.type === 'text' || element.type === 'email' || element.type === 'password')) {
                element.placeholder = translation;
            } else {
                element.textContent = translation;
            }
        });
        
        // Update elements with data-i18n-title attribute
        document.querySelectorAll('[data-i18n-title]').forEach(element => {
            const key = element.getAttribute('data-i18n-title');
            element.title = this.t(key);
        });
        
        // Update document direction
        const currentLangData = this.supportedLanguages.find(lang => lang.code === this.currentLanguage);
        if (currentLangData) {
            document.documentElement.dir = currentLangData.text_direction || 'ltr';
            document.documentElement.lang = this.currentLanguage;
        }
    }
    
    /**
     * Get current language
     */
    getCurrentLanguage() {
        return this.currentLanguage;
    }
    
    /**
     * Get supported languages
     */
    getSupportedLanguages() {
        return this.supportedLanguages;
    }
    
    /**
     * Create language selector
     */
    createLanguageSelector(containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container ${containerId} not found`);
            return;
        }
        
        const select = document.createElement('select');
        select.className = 'language-selector';
        select.addEventListener('change', (e) => {
            this.setLanguage(e.target.value);
        });
        
        this.supportedLanguages.forEach(lang => {
            const option = document.createElement('option');
            option.value = lang.code;
            option.textContent = lang.native_name;
            option.selected = lang.code === this.currentLanguage;
            select.appendChild(option);
        });
        
        container.appendChild(select);
    }
    
    /**
     * Trigger language change event
     */
    triggerLanguageChangeEvent(language) {
        const event = new CusTesfayeEvent('languageChanged', {
            detail: { language, translations: this.translations }
        });
        document.dispatchEvent(event);
    }
    
    /**
     * Format number according to current locale
     */
    formatNumber(number, options = {}) {
        const locale = this.currentLanguage === 'om' ? 'om-ET' : 'en-US';
        return new Intl.NumberFormat(locale, options).format(number);
    }
    
    /**
     * Format date according to current locale
     */
    formatDate(date, options = {}) {
        const locale = this.currentLanguage === 'om' ? 'om-ET' : 'en-US';
        return new Intl.DateTimeFormat(locale, options).format(new Date(date));
    }
    
    /**
     * Format currency according to current locale
     */
    formatCurrency(amount, currency = 'ETB') {
        const locale = this.currentLanguage === 'om' ? 'om-ET' : 'en-US';
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: currency
        }).format(amount);
    }
}

// Initialize global i18n instance
const i18n = new I18n();

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = I18n;
}

// Make available globally
window.i18n = i18n;