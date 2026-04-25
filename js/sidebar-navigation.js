/**
 * WDB Modern Sidebar Navigation System
 * Implements collapsible sidebar with smooth animations, hierarchical navigation,
 * and responsive behavior for the advanced dashboard system.
 */

class SidebarNavigation {
    constructor(options = {}) {
        this.options = {
            sidebarSelector: '.sidebar',
            toggleSelector: '.sidebar-toggle',
            overlaySelector: '.sidebar-overlay',
            collapsedClass: 'collapsed',
            animationDuration: 300,
            breakpoint: 768,
            persistState: true,
            storageKey: 'wdb_sidebar_state',
            ...options
        };

        this.sidebar = null;
        this.toggleButton = null;
        this.overlay = null;
        this.isCollapsed = false;
        this.isMobile = false;
        this.activeItem = null;
        this.tooltips = new Map();

        this.init();
    }

    /**
     * Initialize the sidebar navigation system
     */
    init() {
        this.sidebar = document.querySelector(this.options.sidebarSelector);
        if (!this.sidebar) {
            console.warn('Sidebar element not found');
            return;
        }

        this.setupElements();
        this.setupEventListeners();
        this.setupResponsiveHandling();
        this.restoreState();
        this.setupTooltips();
        this.setupKeyboardNavigation();
        this.setupUserProfile();

        console.log('Sidebar navigation initialized');
    }

    /**
     * Setup sidebar elements and structure
     */
    setupElements() {
        // Create toggle button if it Kebedesn't exist
        this.toggleButton = document.querySelector(this.options.toggleSelector);
        if (!this.toggleButton) {
            this.createToggleButton();
        }

        // Create overlay for mobile
        this.overlay = document.querySelector(this.options.overlaySelector);
        if (!this.overlay) {
            this.createOverlay();
        }

        // Setup navigation items
        this.setupNavigationItems();
    }

    /**
     * Create sidebar toggle button
     */
    createToggleButton() {
        this.toggleButton = document.createElement('button');
        this.toggleButton.className = 'sidebar-toggle';
        this.toggleButton.innerHTML = `
            <i class="fas fa-bars"></i>
            <span class="sr-only">Toggle sidebar</span>
        `;
        this.toggleButton.setAttribute('aria-label', 'Toggle sidebar navigation');
        this.toggleButton.setAttribute('aria-expanded', 'true');

        // Insert toggle button in header or create header
        let header = this.sidebar.querySelector('.sidebar-header');
        if (!header) {
            header = document.createElement('div');
            header.className = 'sidebar-header';
            this.sidebar.insertBefore(header, this.sidebar.firstChild);
        }

        header.appendChild(this.toggleButton);
    }

    /**
     * Create overlay for mobile sidebar
     */
    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.className = 'sidebar-overlay';
        this.overlay.setAttribute('aria-hidden', 'true');
        document.body.appendChild(this.overlay);
    }

    /**
     * Setup navigation items with proper structure
     */
    setupNavigationItems() {
        const navItems = this.sidebar.querySelectorAll('.nav-item');
        
        navItems.forEach((item, index) => {
            // Add unique ID if not present
            if (!item.id) {
                item.id = `nav-item-${index}`;
            }

            // Setup ARIA attributes
            item.setAttribute('role', 'menuitem');
            item.setAttribute('tabindex', '0');

            // Setup hierarchical navigation
            this.setupHierarchicalItem(item);

            // Setup role-based visibility
            this.setupRoleBasedVisibility(item);

            // Setup click handlers
            item.addEventListener('click', (e) => this.handleNavItemClick(e, item));
            item.addEventListener('keydown', (e) => this.handleNavItemKeydown(e, item));
        });

        // Setup section management
        this.setupSectionManagement();
    }

    /**
     * Setup hierarchical navigation item
     */
    setupHierarchicalItem(item) {
        const hasSubmenu = item.querySelector('.nav-submenu');
        
        if (hasSubmenu) {
            item.classList.add('has-submenu');
            item.setAttribute('aria-haspopup', 'true');
            item.setAttribute('aria-expanded', 'false');

            // Create expand/collapse indicator if not present
            let indicator = item.querySelector('.nav-expand-indicator');
            if (!indicator) {
                indicator = document.createElement('i');
                indicator.className = 'nav-expand-indicator fas fa-chevron-right';
                item.appendChild(indicator);
            }

            // Setup submenu
            hasSubmenu.setAttribute('role', 'menu');
            hasSubmenu.setAttribute('aria-hidden', 'true');
            hasSubmenu.style.maxHeight = '0';
            hasSubmenu.style.overflow = 'hidden';
            hasSubmenu.style.transition = 'max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1)';

            // Setup submenu items
            const submenuItems = hasSubmenu.querySelectorAll('.nav-item');
            submenuItems.forEach(submenuItem => {
                submenuItem.setAttribute('role', 'menuitem');
                submenuItem.setAttribute('tabindex', '-1');
                submenuItem.classList.add('nav-submenu-item');
            });
        }
    }

    /**
     * Setup role-based visibility
     */
    setupRoleBasedVisibility(item) {
        const requiredRole = item.dataset.role;
        const requiredPermission = item.dataset.permission;
        const currentUserRole = this.getCurrentUserRole();
        const currentUserPermissions = this.getCurrentUserPermissions();

        // Hide items based on role requirements
        if (requiredRole && !this.hasRole(currentUserRole, requiredRole)) {
            item.style.display = 'none';
            item.setAttribute('aria-hidden', 'true');
        }

        // Hide items based on permission requirements
        if (requiredPermission && !this.hasPermission(currentUserPermissions, requiredPermission)) {
            item.style.display = 'none';
            item.setAttribute('aria-hidden', 'true');
        }

        // Add role-specific styling
        if (currentUserRole) {
            item.classList.add(`role-${currentUserRole}`);
        }
    }

    /**
     * Setup section management
     */
    setupSectionManagement() {
        const sections = this.sidebar.querySelectorAll('.nav-section');
        
        sections.forEach(section => {
            const visibleItems = section.querySelectorAll('.nav-item:not([aria-hidden="true"])');
            
            // Hide empty sections
            if (visibleItems.length === 0) {
                section.style.display = 'none';
            }

            // Add section collapse functionality
            const sectionTitle = section.querySelector('.nav-section-title');
            if (sectionTitle) {
                sectionTitle.addEventListener('click', () => {
                    this.toggleSection(section);
                });
                sectionTitle.style.cursor = 'pointer';
                sectionTitle.setAttribute('role', 'button');
                sectionTitle.setAttribute('aria-expanded', 'true');
            }
        });
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Toggle button click
        if (this.toggleButton) {
            this.toggleButton.addEventListener('click', () => this.toggle());
        }

        // Overlay click (mobile)
        if (this.overlay) {
            this.overlay.addEventListener('click', () => this.close());
        }

        // Window resize
        window.addEventListener('resize', () => this.handleResize());

        // Escape key to close on mobile
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isMobile && !this.isCollapsed) {
                this.close();
            }
        });
    }

    /**
     * Setup responsive handling
     */
    setupResponsiveHandling() {
        this.handleResize();
    }

    /**
     * Handle window resize
     */
    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth < this.options.breakpoint;

        if (this.isMobile !== wasMobile) {
            if (this.isMobile) {
                this.sidebar.classList.add('sidebar-overlay');
                this.isCollapsed = true;
                this.updateSidebarState();
            } else {
                this.sidebar.classList.remove('sidebar-overlay');
                this.restoreState();
            }
        }
        
        // Update tooltips on resize
        this.updateTooltips();
    }

    /**
     * Toggle sidebar collapsed state
     */
    toggle() {
        if (this.isCollapsed) {
            this.expand();
        } else {
            this.collapse();
        }
    }

    /**
     * Expand sidebar
     */
    expand() {
        this.isCollapsed = false;
        this.updateSidebarState();
        this.saveState();

        // Trigger cusTesfaye event
        this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:expanded', {
            detail: { sidebar: this.sidebar }
        }));
    }

    /**
     * Collapse sidebar
     */
    collapse() {
        this.isCollapsed = true;
        this.updateSidebarState();
        this.saveState();

        // Trigger cusTesfaye event
        this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:collapsed', {
            detail: { sidebar: this.sidebar }
        }));
    }

    /**
     * Close sidebar (mobile)
     */
    close() {
        if (this.isMobile) {
            this.collapse();
        }
    }

    /**
     * Update sidebar visual state
     */
    updateSidebarState() {
        if (this.isCollapsed) {
            this.sidebar.classList.add(this.options.collapsedClass);
            if (this.overlay) {
                this.overlay.classList.remove('active');
            }
        } else {
            this.sidebar.classList.remove(this.options.collapsedClass);
            if (this.overlay && this.isMobile) {
                this.overlay.classList.add('active');
            }
        }

        // Update toggle button aria-expanded
        if (this.toggleButton) {
            this.toggleButton.setAttribute('aria-expanded', !this.isCollapsed);
        }

        // Update body class for layout adjustments
        document.body.classList.toggle('sidebar-collapsed', this.isCollapsed);
        
        // Update tooltips based on new state
        this.updateTooltips();
    }

    /**
     * Handle navigation item click
     */
    handleNavItemClick(event, item) {
        event.preventDefault();

        // Handle submenu toggle
        if (item.classList.contains('has-submenu')) {
            this.toggleSubmenu(item);
            return;
        }

        // Handle regular navigation
        this.setActiveItem(item);

        // Close sidebar on mobile after navigation
        if (this.isMobile) {
            setTimeout(() => this.close(), 150);
        }

        // Trigger navigation event
        const href = item.getAttribute('href') || item.dataset.href;
        if (href) {
            this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:navigate', {
                detail: { 
                    item: item, 
                    href: href,
                    label: item.querySelector('.nav-item-label')?.textContent
                }
            }));
        }
    }

    /**
     * Handle navigation item keyboard interaction
     */
    handleNavItemKeydown(event, item) {
        switch (event.key) {
            case 'Enter':
            case ' ':
                event.preventDefault();
                this.handleNavItemClick(event, item);
                break;
            case 'ArrowDown':
                event.preventDefault();
                this.focusNextItem(item);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.focusPreviousItem(item);
                break;
            case 'ArrowRight':
                if (item.classList.contains('has-submenu')) {
                    event.preventDefault();
                    this.expandSubmenu(item);
                }
                break;
            case 'ArrowLeft':
                if (item.classList.contains('has-submenu')) {
                    event.preventDefault();
                    this.collapseSubmenu(item);
                }
                break;
        }
    }

    /**
     * Toggle submenu
     */
    toggleSubmenu(item) {
        const isExpanded = item.getAttribute('aria-expanded') === 'true';
        
        if (isExpanded) {
            this.collapseSubmenu(item);
        } else {
            this.expandSubmenu(item);
        }
    }

    /**
     * Expand submenu
     */
    expandSubmenu(item) {
        const submenu = item.querySelector('.nav-submenu');
        if (!submenu) return;

        item.setAttribute('aria-expanded', 'true');
        item.classList.add('expanded');
        submenu.setAttribute('aria-hidden', 'false');

        // Animate submenu
        submenu.style.maxHeight = submenu.scrollHeight + 'px';
    }

    /**
     * Collapse submenu
     */
    collapseSubmenu(item) {
        const submenu = item.querySelector('.nav-submenu');
        if (!submenu) return;

        item.setAttribute('aria-expanded', 'false');
        item.classList.remove('expanded');
        submenu.setAttribute('aria-hidden', 'true');

        // Animate submenu
        submenu.style.maxHeight = '0';
    }

    /**
     * Set active navigation item
     */
    setActiveItem(item) {
        // Remove active class from all items
        this.sidebar.querySelectorAll('.nav-item').forEach(navItem => {
            navItem.classList.remove('active');
        });

        // Add active class to current item
        item.classList.add('active');
        this.activeItem = item;

        // Update parent items if in submenu
        let parent = item.closest('.nav-submenu')?.parentElement;
        while (parent && parent.classList.contains('nav-item')) {
            parent.classList.add('active-parent');
            parent = parent.closest('.nav-submenu')?.parentElement;
        }
    }

    /**
     * Setup tooltips for collapsed sidebar
     */
    setupTooltips() {
        const navItems = this.sidebar.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            const label = item.querySelector('.nav-item-label');
            if (label) {
                // Create tooltip if it Kebedesn't exist
                let tooltip = item.querySelector('.nav-tooltip');
                if (!tooltip) {
                    tooltip = document.createElement('div');
                    tooltip.className = 'nav-tooltip';
                    tooltip.setAttribute('role', 'tooltip');
                    tooltip.setAttribute('aria-hidden', 'true');
                    item.appendChild(tooltip);
                }
                
                // Set tooltip content
                tooltip.textContent = label.textContent.trim();
                
                // Store tooltip reference
                this.tooltips.set(item, tooltip);
                
                // Setup hover events for tooltip display
                this.setupTooltipEvents(item, tooltip);
            }
        });
    }

    /**
     * Setup tooltip hover events and positioning
     */
    setupTooltipEvents(item, tooltip) {
        let showTimeout;
        let hideTimeout;
        
        // Mouse enter event
        item.addEventListener('mouseenter', () => {
            // Only show tooltip when sidebar is collapsed and not on mobile
            if (this.isCollapsed && !this.isMobile) {
                clearTimeout(hideTimeout);
                showTimeout = setTimeout(() => {
                    this.showTooltip(item, tooltip);
                }, 300); // Delay to prevent flickering
            }
        });
        
        // Mouse leave event
        item.addEventListener('mouseleave', () => {
            clearTimeout(showTimeout);
            hideTimeout = setTimeout(() => {
                this.hideTooltip(tooltip);
            }, 100); // Small delay to allow moving to tooltip
        });
        
        // Focus events for keyboard navigation
        item.addEventListener('focus', () => {
            if (this.isCollapsed && !this.isMobile) {
                this.showTooltip(item, tooltip);
            }
        });
        
        item.addEventListener('blur', () => {
            this.hideTooltip(tooltip);
        });
        
        // Tooltip hover events to keep it visible
        tooltip.addEventListener('mouseenter', () => {
            clearTimeout(hideTimeout);
        });
        
        tooltip.addEventListener('mouseleave', () => {
            this.hideTooltip(tooltip);
        });
    }

    /**
     * Show tooltip with proper positioning
     */
    showTooltip(item, tooltip) {
        if (!tooltip || this.isMobile || !this.isCollapsed) {
            return;
        }
        
        // Position tooltip
        this.positionTooltip(item, tooltip);
        
        // Show tooltip
        tooltip.setAttribute('aria-hidden', 'false');
        tooltip.style.opacity = '1';
        tooltip.style.visibility = 'visible';
        tooltip.style.transform = 'translateY(-50%) translateX(0)';
        
        // Add active class for additional styling
        tooltip.classList.add('tooltip-active');
    }

    /**
     * Hide tooltip
     */
    hideTooltip(tooltip) {
        if (!tooltip) {
            return;
        }
        
        tooltip.setAttribute('aria-hidden', 'true');
        tooltip.style.opacity = '0';
        tooltip.style.visibility = 'hidden';
        tooltip.style.transform = 'translateY(-50%) translateX(-8px)';
        
        // Remove active class
        tooltip.classList.remove('tooltip-active');
    }

    /**
     * Position tooltip relative to navigation item
     */
    positionTooltip(item, tooltip) {
        const itemRect = item.getBoundingClientRect();
        const sidebarRect = this.sidebar.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        
        // Calculate horizontal position (always to the right of sidebar)
        const leftPosition = sidebarRect.width + 8; // 8px gap
        tooltip.style.left = `${leftPosition}px`;
        
        // Calculate vertical position (centered on item)
        let topPosition = itemRect.top - sidebarRect.top + (itemRect.height / 2);
        
        // Adjust if tooltip would go off screen
        const tooltipHeight = tooltip.offsetHeight || 32; // Fallback height
        const minTop = 8; // Minimum distance from top
        const maxTop = viewportHeight - tooltipHeight - 8; // Maximum distance from botTesfaye
        
        if (topPosition < minTop) {
            topPosition = minTop;
        } else if (topPosition > maxTop) {
            topPosition = maxTop;
        }
        
        tooltip.style.top = `${topPosition}px`;
        tooltip.style.transform = 'translateY(-50%)';
    }

    /**
     * Update tooltips when sidebar state changes
     */
    updateTooltips() {
        this.tooltips.forEach((tooltip, item) => {
            if (this.isCollapsed && !this.isMobile) {
                // Ensure tooltip is properly positioned
                this.positionTooltip(item, tooltip);
            } else {
                // Hide tooltip when sidebar is expanded or on mobile
                this.hideTooltip(tooltip);
            }
        });
    }

    /**
     * Refresh tooltip content for dynamic navigation items
     */
    refreshTooltipContent(item) {
        const tooltip = this.tooltips.get(item);
        const label = item.querySelector('.nav-item-label');
        
        if (tooltip && label) {
            tooltip.textContent = label.textContent.trim();
        }
    }

    /**
     * Setup keyboard navigation
     */
    setupKeyboardNavigation() {
        const navItems = this.sidebar.querySelectorAll('.nav-item');
        
        // Set first item as initially focusable
        if (navItems.length > 0) {
            navItems[0].setAttribute('tabindex', '0');
            for (let i = 1; i < navItems.length; i++) {
                navItems[i].setAttribute('tabindex', '-1');
            }
        }
    }

    /**
     * Setup user profile functionality
     */
    setupUserProfile() {
        const userInfo = this.sidebar.querySelector('.sidebar-user-info');
        const userDropdown = this.sidebar.querySelector('.sidebar-user-dropdown');
        
        if (!userInfo || !userDropdown) {
            return;
        }

        // Setup user profile dropdown toggle
        userInfo.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleUserDropdown();
        });

        userInfo.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.toggleUserDropdown();
            }
        });

        // Setup dropdown item interactions
        this.setupUserDropdownItems();

        // Setup preference controls
        this.setupUserPreferences();

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userInfo.contains(e.target) && !userDropdown.contains(e.target)) {
                this.closeUserDropdown();
            }
        });

        // Close dropdown on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && userDropdown.classList.contains('show')) {
                this.closeUserDropdown();
                userInfo.focus();
            }
        });
    }

    /**
     * Setup user dropdown items
     */
    setupUserDropdownItems() {
        const dropdownItems = this.sidebar.querySelectorAll('.user-dropdown-item');
        
        dropdownItems.forEach(item => {
            // Skip preference items as they have special handling
            if (item.classList.contains('preference-item')) {
                return;
            }

            item.addEventListener('click', (e) => {
                this.handleUserDropdownItemClick(e, item);
            });

            item.addEventListener('keydown', (e) => {
                this.handleUserDropdownKeydown(e, item);
            });
        });
    }

    /**
     * Setup user preferences controls
     */
    setupUserPreferences() {
        // Theme selector
        const themeSelector = this.sidebar.querySelector('.theme-selector');
        if (themeSelector) {
            // Load saved theme preference
            const savedTheme = safeGetItem('wdb_user_theme') || 'auto';
            themeSelector.value = savedTheme;
            this.applyTheme(savedTheme);

            themeSelector.addEventListener('change', (e) => {
                const theme = e.target.value;
                this.applyTheme(theme);
                safeSetItem('wdb_user_theme', theme);
                
                // Trigger theme change event
                this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:themeChanged', {
                    detail: { theme: theme }
                }));
            });
        }

        // Language selector
        const languageSelector = this.sidebar.querySelector('.language-selector');
        if (languageSelector) {
            // Load saved language preference
            const savedLanguage = safeGetItem('wdb_user_language') || 'en';
            languageSelector.value = savedLanguage;

            languageSelector.addEventListener('change', (e) => {
                const language = e.target.value;
                safeSetItem('wdb_user_language', language);
                
                // Trigger language change event
                this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:languageChanged', {
                    detail: { language: language }
                }));
            });
        }

        // Notifications toggle
        const notificationsToggle = this.sidebar.querySelector('.notifications-toggle');
        if (notificationsToggle) {
            // Load saved notifications preference
            const savedNotifications = safeGetItem('wdb_user_notifications');
            notificationsToggle.checked = savedNotifications !== 'false';

            notificationsToggle.addEventListener('change', (e) => {
                const enabled = e.target.checked;
                safeSetItem('wdb_user_notifications', enabled.toString());
                
                // Trigger notifications change event
                this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:notificationsChanged', {
                    detail: { enabled: enabled }
                }));
            });
        }
    }

    /**
     * Toggle user dropdown
     */
    toggleUserDropdown() {
        const userInfo = this.sidebar.querySelector('.sidebar-user-info');
        const userDropdown = this.sidebar.querySelector('.sidebar-user-dropdown');
        
        if (!userInfo || !userDropdown) {
            return;
        }

        const isOpen = userDropdown.classList.contains('show');
        
        if (isOpen) {
            this.closeUserDropdown();
        } else {
            this.openUserDropdown();
        }
    }

    /**
     * Open user dropdown
     */
    openUserDropdown() {
        const userInfo = this.sidebar.querySelector('.sidebar-user-info');
        const userDropdown = this.sidebar.querySelector('.sidebar-user-dropdown');
        
        if (!userInfo || !userDropdown) {
            return;
        }

        userDropdown.classList.add('show');
        userInfo.setAttribute('aria-expanded', 'true');
        userDropdown.setAttribute('aria-hidden', 'false');

        // Focus first dropdown item
        const firstItem = userDropdown.querySelector('.user-dropdown-item:not(.preference-item)');
        if (firstItem) {
            firstItem.focus();
        }

        // Trigger dropdown open event
        this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:userDropdownOpened', {
            detail: { dropdown: userDropdown }
        }));
    }

    /**
     * Close user dropdown
     */
    closeUserDropdown() {
        const userInfo = this.sidebar.querySelector('.sidebar-user-info');
        const userDropdown = this.sidebar.querySelector('.sidebar-user-dropdown');
        
        if (!userInfo || !userDropdown) {
            return;
        }

        userDropdown.classList.remove('show');
        userInfo.setAttribute('aria-expanded', 'false');
        userDropdown.setAttribute('aria-hidden', 'true');

        // Trigger dropdown close event
        this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:userDropdownClosed', {
            detail: { dropdown: userDropdown }
        }));
    }

    /**
     * Handle user dropdown item click
     */
    handleUserDropdownItemClick(event, item) {
        event.preventDefault();

        const href = item.getAttribute('href');
        
        // Handle logout specially
        if (href === '#logout') {
            this.handleLogout();
            return;
        }

        // Handle other navigation items
        if (href) {
            // Trigger navigation event
            this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:userNavigate', {
                detail: { 
                    item: item, 
                    href: href,
                    label: item.querySelector('span')?.textContent
                }
            }));
        }

        // Close dropdown after navigation
        this.closeUserDropdown();
    }

    /**
     * Handle user dropdown keyboard navigation
     */
    handleUserDropdownKeydown(event, item) {
        const dropdownItems = Array.from(this.sidebar.querySelectorAll('.user-dropdown-item:not(.preference-item)'));
        const currentIndex = dropdownItems.indexOf(item);

        switch (event.key) {
            case 'Enter':
            case ' ':
                event.preventDefault();
                this.handleUserDropdownItemClick(event, item);
                break;
            case 'ArrowDown':
                event.preventDefault();
                const nextIndex = (currentIndex + 1) % dropdownItems.length;
                dropdownItems[nextIndex].focus();
                break;
            case 'ArrowUp':
                event.preventDefault();
                const prevIndex = currentIndex === 0 ? dropdownItems.length - 1 : currentIndex - 1;
                dropdownItems[prevIndex].focus();
                break;
            case 'Escape':
                event.preventDefault();
                this.closeUserDropdown();
                this.sidebar.querySelector('.sidebar-user-info').focus();
                break;
        }
    }

    /**
     * Handle logout
     */
    handleLogout() {
        // Trigger logout event
        this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:logout', {
            detail: { timestamp: Date.now() }
        }));

        // Close dropdown
        this.closeUserDropdown();

        // In a real application, this would redirect to logout endpoint
        console.log('Logout requested');
    }

    /**
     * Apply theme
     */
    applyTheme(theme) {
        const body = document.body;
        
        // Remove existing theme classes
        body.classList.remove('theme-light', 'theme-dark', 'theme-auto');
        
        // Apply new theme
        if (theme === 'auto') {
            // Use system preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            body.classList.add(prefersDark ? 'theme-dark' : 'theme-light');
        } else {
            body.classList.add(`theme-${theme}`);
        }
    }

    /**
     * Update user profile information
     */
    updateUserProfile(userProfile) {
        // Update user name
        const userNameElements = this.sidebar.querySelectorAll('.sidebar-user-name, .user-dropdown-name');
        userNameElements.forEach(element => {
            element.textContent = userProfile.fullName || userProfile.username;
        });

        // Update user role
        const userRoleElements = this.sidebar.querySelectorAll('.sidebar-user-role');
        userRoleElements.forEach(element => {
            element.textContent = this.formatRole(userProfile.role);
        });

        // Update role badge
        const roleBadge = this.sidebar.querySelector('.user-dropdown-role-badge');
        if (roleBadge) {
            roleBadge.className = `user-dropdown-role-badge ${userProfile.role}`;
            roleBadge.textContent = this.formatRole(userProfile.role);
        }

        // Update email
        const emailElement = this.sidebar.querySelector('.user-dropdown-email');
        if (emailElement && userProfile.email) {
            emailElement.textContent = userProfile.email;
        }

        // Update avatar
        const avatarImages = this.sidebar.querySelectorAll('.user-avatar-image');
        if (userProfile.avatar) {
            avatarImages.forEach(img => {
                img.src = userProfile.avatar;
                img.style.display = 'block';
                img.nextElementSibling.style.display = 'none';
            });
        }

        // Update status
        const statusElement = this.sidebar.querySelector('.sidebar-user-status');
        if (statusElement && userProfile.status) {
            statusElement.className = `sidebar-user-status ${userProfile.status}`;
            statusElement.querySelector('span').textContent = this.formatStatus(userProfile.status);
        }

        // Trigger profile update event
        this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:profileUpdated', {
            detail: { profile: userProfile }
        }));
    }

    /**
     * Format role for display
     */
    formatRole(role) {
        const roleNames = {
            'user': 'User',
            'admin': 'Administrator',
            'superadmin': 'Super Administrator'
        };
        return roleNames[role] || role;
    }

    /**
     * Format status for display
     */
    formatStatus(status) {
        const statusNames = {
            'online': 'Online',
            'offline': 'Offline',
            'away': 'Away',
            'busy': 'Busy'
        };
        return statusNames[status] || status;
    }

    /**
     * Focus next navigation item
     */
    focusNextItem(currentItem) {
        const navItems = Array.from(this.sidebar.querySelectorAll('.nav-item'));
        const currentIndex = navItems.indexOf(currentItem);
        const nextIndex = (currentIndex + 1) % navItems.length;
        
        this.focusItem(navItems[nextIndex]);
    }

    /**
     * Focus previous navigation item
     */
    focusPreviousItem(currentItem) {
        const navItems = Array.from(this.sidebar.querySelectorAll('.nav-item'));
        const currentIndex = navItems.indexOf(currentItem);
        const prevIndex = currentIndex === 0 ? navItems.length - 1 : currentIndex - 1;
        
        this.focusItem(navItems[prevIndex]);
    }

    /**
     * Focus specific navigation item
     */
    focusItem(item) {
        // Update tabindex
        this.sidebar.querySelectorAll('.nav-item').forEach(navItem => {
            navItem.setAttribute('tabindex', '-1');
        });
        
        item.setAttribute('tabindex', '0');
        item.focus();
    }

    /**
     * Save sidebar state to localStorage
     */
    saveState() {
        if (this.options.persistState && !this.isMobile) {
            try {
                safeSetItem(this.options.storageKey, JSON.stringify({
                    collapsed: this.isCollapsed,
                    timestamp: Date.now()
                }));
            } catch (error) {
                console.warn('Failed to save sidebar state:', error);
            }
        }
    }

    /**
     * Restore sidebar state from localStorage
     */
    restoreState() {
        if (this.options.persistState && !this.isMobile) {
            try {
                const savedState = safeGetItem(this.options.storageKey);
                if (savedState) {
                    const state = JSON.parse(savedState);
                    this.isCollapsed = state.collapsed || false;
                    this.updateSidebarState();
                }
            } catch (error) {
                console.warn('Failed to restore sidebar state:', error);
            }
        }
    }

    /**
     * Get current sidebar state
     */
    getState() {
        return {
            collapsed: this.isCollapsed,
            mobile: this.isMobile,
            activeItem: this.activeItem?.id || null
        };
    }

    /**
     * Get current user role
     */
    getCurrentUserRole() {
        // Try to get from various sources
        const userRole = 
            this.sidebar.dataset.userRole ||
            document.body.dataset.userRole ||
            safeGetItem('wdb_user_role') ||
            'user'; // default role
        
        return userRole.toLowerCase();
    }

    /**
     * Get current user permissions
     */
    getCurrentUserPermissions() {
        try {
            const permissions = safeGetItem('wdb_user_permissions');
            return permissions ? JSON.parse(permissions) : [];
        } catch (error) {
            console.warn('Failed to parse user permissions:', error);
            return [];
        }
    }

    /**
     * Check if user has required role
     */
    hasRole(userRole, requiredRole) {
        const roleHierarchy = {
            'superadmin': ['superadmin', 'admin', 'user'],
            'admin': ['admin', 'user'],
            'user': ['user']
        };

        const allowedRoles = roleHierarchy[userRole] || [userRole];
        return allowedRoles.includes(requiredRole);
    }

    /**
     * Check if user has required permission
     */
    hasPermission(userPermissions, requiredPermission) {
        return userPermissions.includes(requiredPermission);
    }

    /**
     * Toggle section collapse/expand
     */
    toggleSection(section) {
        const sectionTitle = section.querySelector('.nav-section-title');
        const sectionContent = section.querySelector('.nav-section-content') || 
                              section.querySelector('.nav-items') ||
                              section;
        
        const isExpanded = sectionTitle.getAttribute('aria-expanded') === 'true';
        
        if (isExpanded) {
            this.collapseSection(section);
        } else {
            this.expandSection(section);
        }
    }

    /**
     * Collapse section
     */
    collapseSection(section) {
        const sectionTitle = section.querySelector('.nav-section-title');
        const navItems = section.querySelectorAll('.nav-item');
        
        sectionTitle.setAttribute('aria-expanded', 'false');
        section.classList.add('collapsed');
        
        navItems.forEach(item => {
            item.style.display = 'none';
        });
    }

    /**
     * Expand section
     */
    expandSection(section) {
        const sectionTitle = section.querySelector('.nav-section-title');
        const navItems = section.querySelectorAll('.nav-item');
        
        sectionTitle.setAttribute('aria-expanded', 'true');
        section.classList.remove('collapsed');
        
        navItems.forEach(item => {
            // Only show items that aren't hidden by role/permission
            if (item.getAttribute('aria-hidden') !== 'true') {
                item.style.display = 'flex';
            }
        });
    }

    /**
     * Update navigation based on user role change
     */
    updateNavigationForRole(newRole, newPermissions = []) {
        // Update stored role and permissions
        safeSetItem('wdb_user_role', newRole);
        safeSetItem('wdb_user_permissions', JSON.stringify(newPermissions));
        
        // Update sidebar data attribute
        this.sidebar.dataset.userRole = newRole;
        
        // Re-setup navigation items
        this.setupNavigationItems();
        
        // Trigger role change event
        this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:roleChanged', {
            detail: { 
                role: newRole, 
                permissions: newPermissions 
            }
        }));
    }

    /**
     * Get navigation hierarchy
     */
    getNavigationHierarchy() {
        const hierarchy = [];
        const sections = this.sidebar.querySelectorAll('.nav-section');
        
        sections.forEach(section => {
            const sectionTitle = section.querySelector('.nav-section-title');
            const sectionData = {
                title: sectionTitle ? sectionTitle.textContent : 'Untitled Section',
                items: [],
                collapsed: section.classList.contains('collapsed')
            };
            
            const navItems = section.querySelectorAll('.nav-item:not(.nav-submenu-item)');
            navItems.forEach(item => {
                const itemData = {
                    id: item.id,
                    label: item.querySelector('.nav-item-label')?.textContent || 'Untitled Item',
                    href: item.getAttribute('href') || item.dataset.href,
                    icon: item.querySelector('.nav-item-icon i')?.className,
                    active: item.classList.contains('active'),
                    visible: item.style.display !== 'none',
                    hasSubmenu: item.classList.contains('has-submenu'),
                    submenu: []
                };
                
                // Get submenu items
                if (itemData.hasSubmenu) {
                    const submenu = item.querySelector('.nav-submenu');
                    if (submenu) {
                        const submenuItems = submenu.querySelectorAll('.nav-item');
                        submenuItems.forEach(subItem => {
                            itemData.submenu.push({
                                id: subItem.id,
                                label: subItem.querySelector('.nav-item-label')?.textContent || 'Untitled Subitem',
                                href: subItem.getAttribute('href') || subItem.dataset.href,
                                icon: subItem.querySelector('.nav-item-icon i')?.className,
                                active: subItem.classList.contains('active'),
                                visible: subItem.style.display !== 'none'
                            });
                        });
                    }
                }
                
                sectionData.items.push(itemData);
            });
            
            hierarchy.push(sectionData);
        });
        
        return hierarchy;
    }

    /**
     * Find navigation item by path
     */
    findNavigationItem(path) {
        const hierarchy = this.getNavigationHierarchy();
        
        for (const section of hierarchy) {
            for (const item of section.items) {
                if (item.href === path) {
                    return { section: section.title, item: item };
                }
                
                // Check submenu items
                for (const subItem of item.submenu) {
                    if (subItem.href === path) {
                        return { 
                            section: section.title, 
                            item: item, 
                            subItem: subItem 
                        };
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Navigate to specific path and update active states
     */
    navigateToPath(path) {
        const found = this.findNavigationItem(path);
        
        if (found) {
            // Clear all active states
            this.sidebar.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active', 'active-parent');
            });
            
            // Set active states
            if (found.subItem) {
                // Submenu item is active
                const subItemElement = document.getElementById(found.subItem.id);
                const parentItemElement = document.getElementById(found.item.id);
                
                if (subItemElement) {
                    subItemElement.classList.add('active');
                }
                if (parentItemElement) {
                    parentItemElement.classList.add('active-parent');
                    this.expandSubmenu(parentItemElement);
                }
            } else {
                // Main item is active
                const itemElement = document.getElementById(found.item.id);
                if (itemElement) {
                    itemElement.classList.add('active');
                }
            }
            
            // Trigger navigation event
            this.sidebar.dispatchEvent(new CusTesfayeEvent('sidebar:pathChanged', {
                detail: { 
                    path: path,
                    navigationItem: found
                }
            }));
            
            return true;
        }
        
        return false;
    }

    /**
     * Destroy sidebar navigation
     */
    destroy() {
        // Remove event listeners
        if (this.toggleButton) {
            this.toggleButton.removeEventListener('click', this.toggle);
        }
        
        if (this.overlay) {
            this.overlay.removeEventListener('click', this.close);
        }

        window.removeEventListener('resize', this.handleResize);

        // Clean up tooltips
        this.tooltips.clear();

        console.log('Sidebar navigation destroyed');
    }
}

// Export for use in other modules
window.SidebarNavigation = SidebarNavigation;

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (document.querySelector('.sidebar')) {
            window.sidebarNavigation = new SidebarNavigation();
        }
    });
} else {
    if (document.querySelector('.sidebar')) {
        window.sidebarNavigation = new SidebarNavigation();
    }
}