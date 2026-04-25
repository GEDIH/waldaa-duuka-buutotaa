/**
 * WDB Language Switcher Component
 * Universal language switcher for all dashboards
 * Supports: English, Afaan Oromoo, Amharic, Tigrinya
 */

class LanguageSwitcher {
    constructor() {
        this.currentLang = localStorage.getItem('wdb_language') || 'en';
        this.init();
    }
    
    init() {
        // Add CSS styles
        this.addStyles();
        
        // Update active button on page load
        this.updateActiveButton();
    }
    
    addStyles() {
        if (document.getElementById('language-switcher-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'language-switcher-styles';
        style.textContent = `
            .wdb-language-switcher {
                display: flex;
                gap: 0.5rem;
                align-items: center;
            }
            
            .wdb-lang-btn {
                padding: 0.4rem 0.9rem;
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-radius: 20px;
                background: rgba(255, 255, 255, 0.1);
                color: white;
                cursor: pointer;
                font-size: 0.8rem;
                font-weight: 600;
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }
            
            .wdb-lang-btn:hover {
                border-color: rgba(255, 255, 255, 0.6);
                background: rgba(255, 255, 255, 0.2);
                transform: translateY(-1px);
            }
            
            .wdb-lang-btn.active {
                border-color: white;
                background: rgba(255, 255, 255, 0.3);
                box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
            }
            
            /* Dark theme variant */
            .wdb-language-switcher.dark .wdb-lang-btn {
                border-color: rgba(0, 0, 0, 0.2);
                background: rgba(0, 0, 0, 0.05);
                color: #333;
            }
            
            .wdb-language-switcher.dark .wdb-lang-btn:hover {
                border-color: rgba(0, 0, 0, 0.4);
                background: rgba(0, 0, 0, 0.1);
            }
            
            .wdb-language-switcher.dark .wdb-lang-btn.active {
                border-color: #6366f1;
                background: rgba(99, 102, 241, 0.1);
                color: #6366f1;
                box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
            }
        `;
        document.head.appendChild(style);
    }
    
    createSwitcher(theme = 'light') {
        const switcher = document.createElement('div');
        switcher.className = `wdb-language-switcher ${theme}`;
        switcher.innerHTML = `
            <button onclick="languageSwitcher.setLanguage('en')" class="wdb-lang-btn" id="wdb-lang-en">
                🇬🇧 English
            </button>
            <button onclick="languageSwitcher.setLanguage('om')" class="wdb-lang-btn" id="wdb-lang-om">
                🇪🇹 Afaan Oromoo
            </button>
            <button onclick="languageSwitcher.setLanguage('am')" class="wdb-lang-btn" id="wdb-lang-am">
                🇪🇹 አማርኛ
            </button>
            <button onclick="languageSwitcher.setLanguage('ti')" class="wdb-lang-btn" id="wdb-lang-ti">
                🇪🇹 ትግርኛ
            </button>
        `;
        return switcher;
    }
    
    setLanguage(lang) {
        if (typeof i18n !== 'undefined') {
            i18n.setLanguage(lang);
        } else {
            this.currentLang = lang;
            localStorage.setItem('wdb_language', lang);
        }
        this.updateActiveButton();
        
        // Dispatch language changed event for other components
        window.dispatchEvent(new CustomEvent('languageChanged', { detail: { language: lang } }));
        
        // Reload page to apply translations
        window.location.reload();
    }
    
    updateActiveButton() {
        const currentLang = localStorage.getItem('wdb_language') || 'en';
        
        // Remove active class from all buttons
        document.querySelectorAll('.wdb-lang-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to current language button
        const activeBtn = document.getElementById(`wdb-lang-${currentLang}`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
    }
    
    // Auto-inject into common locations
    autoInject() {
        // Try to find common header/nav locations
        const locations = [
            '.header',
            '.navbar',
            '.top-bar',
            '.nav-right',
            'header',
            'nav'
        ];
        
        for (const selector of locations) {
            const element = document.querySelector(selector);
            if (element) {
                const switcher = this.createSwitcher();
                element.appendChild(switcher);
                this.updateActiveButton();
                return true;
            }
        }
        
        return false;
    }
}

// Create global instance
const languageSwitcher = new LanguageSwitcher();

// Auto-inject on page load if requested
document.addEventListener('DOMContentLoaded', () => {
    // Check if auto-inject is enabled
    if (document.body.hasAttribute('data-auto-language-switcher')) {
        languageSwitcher.autoInject();
    }
});
