/**
 * Suppress Tailwind CDN Production Warning
 * This script suppresses the console warning about using Tailwind CDN in production
 */

(function() {
    // Store original console.warn
    const originalWarn = console.warn;
    
    // Override console.warn to filter out Tailwind CDN warning
    console.warn = function(...args) {
        const message = args.join(' ');
        
        // Check if this is the Tailwind CDN warning
        if (message.includes('cdn.tailwindcss.com') && 
            message.includes('should not be used in production')) {
            // Suppress this specific warning
            return;
        }
        
        // Allow all other warnings through
        originalWarn.apply(console, args);
    };
})();
