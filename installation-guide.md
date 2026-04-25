# WDB Membership System - Installation Guide

## Overview
This guide explains how to set up the integrated Waldaa Duuka Bu'ootaa membership registration system.

## System Components

### 1. Database Layer
- **File**: `database-setup.sql`
- **Purpose**: Creates all necessary database tables
- **Tables**: members, administrators, centers, contributions, announcements, audit_logs, email_queue

### 2. Configuration
- **File**: `config.php`
- **Purpose**: Database connection and system settings
- **Features**: Database config, security settings, utility functions

### 3. Core Classes
- **File**: `classes/Database.php` - Database singleton with CRUD operations
- **File**: `classes/Member.php` - Member management and registration logic

### 4. API Endpoints
- **File**: `api/register.php` - Member registration API with validation

### 5. Frontend Pages
- **File**: `member-registration.html` - Advanced multi-step registration form
- **File**: `admin-dashboard.html` - Admin management interface
- **File**: `admin-login.html` - Admin authentication
- **File**: `member-dashboard.html` - Member portal
- **File**: `js/registration.js` - Modern JavaScript with multi-language support

## Installation Steps

### Step 1: Database Setup
1. Create a MySQL database named `wdb_membership`
2. Import the database schema:
   ```sql
   mysql -u root -p wdb_membership < database-setup.sql
   ```

### Step 2: Configuration
1. Edit `config.php` and update database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'wdb_membership');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

2. Update site URL:
   ```php
   define('SITE_URL', 'http://your-domain.com');
   ```

### Step 3: File Permissions
Ensure proper permissions for upload directories:
```bash
chmod 755 uploads/
chmod 755 uploads/members/
```

### Step 4: Test the System
1. Visit `member-registration.html` to test registration
2. Visit `admin-login.html` to access admin panel (any username/password works in demo mode)
3. Check database for registered members

## Features

### Member Registration
- Multi-step form with validation
- Multi-language support (Oromo, English, Amharic, Tigrinya)
- Real-time form validation
- API integration with backend
- Responsive design

### Admin Dashboard
- Member management
- Registration approval
- Statistics and reporting
- Modern glass-morphism UI

### Member Dashboard
- Personal profile management
- Membership status
- Announcements
- Events calendar

## Security Features
- Input sanitization
- SQL injection prevention
- Session management
- Activity logging
- File upload validation

## Multi-Language Support
The system supports 4 languages:
- Oromo (or) - Default
- English (en)
- Amharic (am)
- Tigrinya (ti)

## API Endpoints

### POST /api/register.php
Register a new member
```json
{
  "fname": "First Name",
  "lname": "Last Name",
  "gender": "Dhiira|Dubartii",
  "phone": "+251911234567",
  "email": "email@example.com",
  "address": "Address",
  "baptized": "eeyyee|lakki"
}
```

## Troubleshooting

### Common Issues
1. **Database Connection Error**: Check credentials in `config.php`
2. **Registration Not Working**: Verify API endpoint path
3. **Images Not Loading**: Check file permissions and paths
4. **JavaScript Errors**: Ensure `js/registration.js` is loaded

### Debug Mode
Enable debug mode in `config.php`:
```php
define('DEBUG_MODE', true);
```

## Production Deployment
1. Set `DEBUG_MODE` to `false`
2. Configure proper email settings for notifications
3. Set up SSL certificate
4. Configure backup procedures
5. Set up monitoring

## Support
For technical support, contact the development team or refer to the integration documentation files:
- `integration-plan.md`
- `quick-integration.md`
- `selective-integration.md`