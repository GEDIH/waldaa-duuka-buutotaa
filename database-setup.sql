-- WDB Membership System Database Setup
-- Run this SQL to create the necessary database structure

CREATE DATABASE IF NOT EXISTS wdb_membership CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wdb_membership;

-- Members table
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    date_of_birth DATE,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT NOT NULL,
    current_church VARCHAR(200),
    baptized ENUM('yes', 'no') NOT NULL,
    service_interest VARCHAR(100),
    how_heard VARCHAR(100),
    notes TEXT,
    photo VARCHAR(255),
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    center_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_member_id (member_id),
    INDEX idx_status (status),
    INDEX idx_center (center_id)
);

-- Administrators table
CREATE TABLE IF NOT EXISTS administrators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(20) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('superadmin', 'admin', 'center_admin') DEFAULT 'admin',
    center_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
);

-- Centers table
CREATE TABLE IF NOT EXISTS centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    center_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(200) NOT NULL,
    location VARCHAR(200) NOT NULL,
    address TEXT,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);

-- Contributions table
CREATE TABLE IF NOT EXISTS contributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribution_id VARCHAR(20) UNIQUE NOT NULL,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('tithe', 'offering', 'special', 'other') DEFAULT 'offering',
    description TEXT,
    payment_method ENUM('cash', 'bank_transfer', 'mobile_money') DEFAULT 'cash',
    reference_number VARCHAR(100),
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    contributed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    confirmed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES administrators(id),
    INDEX idx_member (member_id),
    INDEX idx_status (status),
    INDEX idx_type (type)
);

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('general', 'urgent', 'event', 'notice') DEFAULT 'general',
    target_audience ENUM('all', 'members', 'admins') DEFAULT 'all',
    center_id INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES administrators(id),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_target (target_audience)
);

-- Audit logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_type ENUM('admin', 'member') DEFAULT 'admin',
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- User sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('admin', 'member') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type),
    INDEX idx_activity (last_activity)
);

-- Email queue table
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(100) NOT NULL,
    to_name VARCHAR(200),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    template VARCHAR(100),
    priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at)
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);

-- Insert default administrator
INSERT INTO administrators (admin_id, username, password, full_name, email, role, status) 
VALUES (
    'WDB-ADMIN-001', 
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'System Administrator', 
    'admin@wdb.org', 
    'superadmin', 
    'active'
) ON DUPLICATE KEY UPDATE id=id;

-- Insert default center
INSERT INTO centers (center_id, name, location, address, contact_person, phone, status) 
VALUES (
    'WDB-CENTER-001', 
    'Main Center - Finfinne', 
    'Addis Ababa', 
    'Kilo 5 Gamo Almi, Second Floor', 
    'Administrator', 
    '+251911234567', 
    'active'
) ON DUPLICATE KEY UPDATE id=id;

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'Waldaa Duuka Bu\'ootaa', 'Organization name'),
('site_email', 'info@wdb.org', 'Main contact email'),
('site_phone', '+251911234567', 'Main contact phone'),
('registration_enabled', '1', 'Enable/disable member registration'),
('email_notifications', '1', 'Enable/disable email notifications'),
('default_language', 'or', 'Default system language'),
('member_approval_required', '1', 'Require admin approval for new members')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Add foreign key constraints
ALTER TABLE members ADD CONSTRAINT fk_member_center FOREIGN KEY (center_id) REFERENCES centers(id);
ALTER TABLE administrators ADD CONSTRAINT fk_admin_center FOREIGN KEY (center_id) REFERENCES centers(id);
ALTER TABLE announcements ADD CONSTRAINT fk_announcement_center FOREIGN KEY (center_id) REFERENCES centers(id);

-- Create indexes for better performance
CREATE INDEX idx_members_created ON members(created_at);
CREATE INDEX idx_contributions_date ON contributions(contributed_at);
CREATE INDEX idx_announcements_published ON announcements(published_at);

-- Create views for common queries
CREATE OR REPLACE VIEW member_summary AS
SELECT 
    m.id,
    m.member_id,
    CONCAT(m.first_name, ' ', m.last_name) as full_name,
    m.phone,
    m.email,
    m.status,
    c.name as center_name,
    m.created_at,
    COUNT(co.id) as total_contributions,
    COALESCE(SUM(co.amount), 0) as total_amount
FROM members m
LEFT JOIN centers c ON m.center_id = c.id
LEFT JOIN contributions co ON m.id = co.member_id AND co.status = 'confirmed'
GROUP BY m.id;

CREATE OR REPLACE VIEW admin_summary AS
SELECT 
    a.id,
    a.admin_id,
    a.username,
    a.full_name,
    a.email,
    a.role,
    a.status,
    c.name as center_name,
    a.last_login,
    a.created_at
FROM administrators a
LEFT JOIN centers c ON a.center_id = c.id;

-- Success message
SELECT 'Database setup completed successfully!' as message;