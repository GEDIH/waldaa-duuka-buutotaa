<?php
// WDB System Configuration
// This file contains all system-wide configuration settings

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'wdb_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// System Configuration
define('SYSTEM_NAME', 'WDB Membership System');
define('SYSTEM_VERSION', '2.0.0');
define('SYSTEM_DESCRIPTION', 'Waldaa Duuka Bu\'ootaa - Advanced Membership Management');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', 'uploads/');

// Export Configuration
define('EXPORT_MAX_RECORDS', 10000);
define('EXPORT_TIMEOUT', 300); // 5 minutes
define('EXPORT_PATH', 'exports/');

// Email Configuration (for future use)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@wdb.org');
define('FROM_NAME', 'WDB System');

// Announcement Configuration
define('ANNOUNCEMENT_CACHE_TIME', 300); // 5 minutes
define('MAX_ANNOUNCEMENT_LENGTH', 5000);

// API Configuration
define('API_RATE_LIMIT', 100); // requests per minute
define('API_VERSION', 'v1');

// Logging Configuration
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_PATH', 'logs/');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

// Feature Flags
define('ENABLE_REGISTRATION', true);
define('ENABLE_PASSWORD_RESET', true);
define('ENABLE_EMAIL_NOTIFICATIONS', false);
define('ENABLE_SMS_NOTIFICATIONS', false);
define('ENABLE_AUDIT_LOGGING', true);
define('ENABLE_RATE_LIMITING', true);

// UI Configuration
define('DEFAULT_LANGUAGE', 'en');
define('SUPPORTED_LANGUAGES', ['en', 'or', 'am']); // English, Oromo, Amharic
define('DEFAULT_THEME', 'light');
define('ENABLE_DARK_MODE', true);

// Demo Configuration
define('DEMO_MODE', true);
define('DEMO_ADMIN_USERNAME', 'admin');
define('DEMO_ADMIN_PASSWORD', 'admin123');
define('DEMO_MEMBER_USERNAME', 'member');
define('DEMO_MEMBER_PASSWORD', 'member123');

// System Paths
define('BASE_PATH', __DIR__);
define('CLASSES_PATH', BASE_PATH . '/classes/');
define('COMPONENTS_PATH', BASE_PATH . '/components/');
define('API_PATH', BASE_PATH . '/api/');
define('ADMIN_PATH', BASE_PATH . '/admin/');

// Error Reporting (set to false in production)
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Africa/Addis_Ababa');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Function to get configuration value
function getConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

// Function to check if feature is enabled
function isFeatureEnabled($feature) {
    $constant = 'ENABLE_' . strtoupper($feature);
    return defined($constant) ? constant($constant) : false;
}
?>