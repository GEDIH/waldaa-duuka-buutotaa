-- WDB Membership System - Production Database Setup
-- This script creates the complete database schema for production deployment

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database (run separately with admin privileges)
-- CREATE DATABASE wdb_membership_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE wdb_membership_prod;

-- Users table with enhanced security
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('member','admin','superadmin','system_admin') NOT NULL DEFAULT 'member',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `two_factor_secret` varchar(32) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Centers table
CREATE TABLE `centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL UNIQUE,
  `address` text,
  `phone` varchar(20),
  `email` varchar(100),
  `manager_id` int(11),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_manager` (`manager_id`),
  KEY `idx_active` (`is_active`),
  FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Members table
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `center_id` int(11) NOT NULL,
  `member_number` varchar(20) NOT NULL UNIQUE,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20),
  `address` text,
  `date_of_birth` date,
  `gender` enum('male','female','other') DEFAULT NULL,
  `emergency_contact_name` varchar(100),
  `emergency_contact_phone` varchar(20),
  `membership_status` enum('active','inactive','suspended','terminated') NOT NULL DEFAULT 'active',
  `join_date` date NOT NULL,
  `photo_filename` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_number` (`member_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_center` (`center_id`),
  KEY `idx_status` (`membership_status`),
  KEY `idx_name` (`first_name`, `last_name`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payment Methods table
CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `configuration` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_active` (`is_active`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contributions table
CREATE TABLE `contributions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `center_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `contribution_type` varchar(50) NOT NULL DEFAULT 'monthly',
  `payment_date` date NOT NULL,
  `payment_status` enum('pending','confirmed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_center` (`center_id`),
  KEY `idx_payment_method` (`payment_method_id`),
  KEY `idx_date` (`payment_date`),
  KEY `idx_status` (`payment_status`),
  KEY `idx_reference` (`reference_number`),
  FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Configuration table
CREATE TABLE `system_configuration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL UNIQUE,
  `config_value` text NOT NULL,
  `config_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` text,
  `is_editable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Log table for security tracking
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table` (`table_name`),
  KEY `idx_created` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions table for secure session management
CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_activity` (`last_activity`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default payment methods (Ethiopian focus)
INSERT INTO `payment_methods` (`name`, `type`, `description`, `is_active`, `display_order`) VALUES
('Cash Payment', 'cash', 'Direct cash payment at center', 1, 1),
('Bank Transfer', 'bank_transfer', 'Direct bank transfer to center account', 1, 2),
('M-Birr', 'mobile_money', 'M-Birr mobile money payment', 1, 3),
('Telebirr', 'mobile_money', 'Telebirr mobile payment service', 1, 4),
('HelloCash', 'mobile_money', 'HelloCash mobile wallet payment', 1, 5),
('Amole', 'mobile_money', 'Amole digital wallet payment', 1, 6),
('CBE Mobile Banking', 'mobile_banking', 'Commercial Bank of Ethiopia mobile banking', 1, 7),
('Dashen Mobile Banking', 'mobile_banking', 'Dashen Bank mobile banking service', 1, 8);

-- Insert default system configuration
INSERT INTO `system_configuration` (`config_key`, `config_value`, `config_type`, `description`) VALUES
('system_version', '2.0.0', 'string', 'Current system version'),
('maintenance_mode', 'false', 'boolean', 'System maintenance mode status'),
('session_timeout', '3600', 'integer', 'Session timeout in seconds'),
('max_login_attempts', '5', 'integer', 'Maximum failed login attempts before lockout'),
('account_lockout_duration', '1800', 'integer', 'Account lockout duration in seconds'),
('require_2fa', 'false', 'boolean', 'Require two-factor authentication'),
('max_file_size', '5242880', 'integer', 'Maximum file upload size in bytes'),
('allowed_file_types', '["image/jpeg","image/png","image/gif"]', 'json', 'Allowed file types for uploads'),
('backup_retention_days', '30', 'integer', 'Number of days to retain backups'),
('log_level', 'ERROR', 'string', 'Application logging level');

-- Create default system administrator (password: Admin123!)
-- Note: In production, change this password immediately after first login
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `is_active`) VALUES
('sysadmin', 'admin@yourdomain.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'system_admin', 1);

-- Create indexes for performance
CREATE INDEX idx_contributions_date_status ON contributions(payment_date, payment_status);
CREATE INDEX idx_members_center_status ON members(center_id, membership_status);
CREATE INDEX idx_audit_log_date_user ON audit_log(created_at, user_id);

COMMIT;

-- Production-specific optimizations
SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB buffer pool
SET GLOBAL query_cache_size = 268435456; -- 256MB query cache
SET GLOBAL max_connections = 200;
SET GLOBAL wait_timeout = 28800;
SET GLOBAL interactive_timeout = 28800;

-- Enable binary logging for replication (if needed)
-- SET GLOBAL log_bin = ON;
-- SET GLOBAL binlog_format = 'ROW';

-- Security settings
SET GLOBAL local_infile = 0;
SET GLOBAL secure_file_priv = '/var/lib/mysql-files/';