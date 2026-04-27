-- =====================================================
-- WDB Membership System - Table Creation Script
-- =====================================================
-- This script creates all the required tables for the WDB system
-- Run this after creating the database

USE wdb_membership;

-- =====================================================
-- 1. CENTERS TABLE (Wiirtuu Centers)
-- =====================================================
CREATE TABLE IF NOT EXISTS centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,
    description TEXT,
    location VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    region VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Ethiopia',
    phone VARCHAR(20),
    email VARCHAR(255),
    established_date DATE,
    capacity INT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    
    INDEX idx_name (name),
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_region (region),
    INDEX idx_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. USERS TABLE (Unified Authentication)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    gender ENUM('male', 'female', 'other'),
    role ENUM('user', 'admin', 'superadmin') DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'active',
    language VARCHAR(5) DEFAULT 'en',
    last_login TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_last_login (last_login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. ADMINISTRATORS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS administrators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    position VARCHAR(100),
    department VARCHAR(100),
    hire_date DATE,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. ADMIN-CENTER ASSIGNMENTS (Many-to-Many)
-- =====================================================
CREATE TABLE IF NOT EXISTS admin_centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    center_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (admin_id) REFERENCES administrators(id) ON DELETE CASCADE,
    FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (admin_id, center_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_center_id (center_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. SERVICE AREAS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS service_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    name_om VARCHAR(255),
    description_en TEXT,
    description_om TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. MEMBERS TABLE (Comprehensive Membership Information)
-- =====================================================
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    member_id VARCHAR(50) UNIQUE,
    
    -- Personal Information
    full_name VARCHAR(255) NOT NULL,
    baptism_name VARCHAR(255),
    facebook_name VARCHAR(255),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other') NOT NULL,
    marital_status ENUM('single', 'married', 'divorced', 'widowed'),
    spouse_name VARCHAR(255),
    spouse_phone VARCHAR(50),
    confession_father VARCHAR(255),
    
    -- Contact Information
    address TEXT,
    mobile_phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    
    -- Education & Occupation
    education_level ENUM('primary', 'secondary', 'tvet', 'bachelor', 'master', 'phd', 'other'),
    field_of_study VARCHAR(200),
    university VARCHAR(200),
    graduation_year INT,
    occupation VARCHAR(200),
    organization VARCHAR(255),
    
    -- Clergy Status
    diaconate_year INT,
    diaconate_church VARCHAR(255),
    diaconate_father VARCHAR(255),
    priesthood_year INT,
    priesthood_church VARCHAR(255),
    priesthood_father VARCHAR(255),
    monastic_year INT,
    monastic_kawaala VARCHAR(255),
    monastic_father VARCHAR(255),
    
    -- Regional & Church Information
    region VARCHAR(100),
    country VARCHAR(100) DEFAULT 'Ethiopia',
    sub_city VARCHAR(100),
    current_church VARCHAR(255),
    church_woreda VARCHAR(100),
    current_service VARCHAR(255),
    
    -- WDB Service Areas (Legacy - will be moved to separate table)
    service_area1 VARCHAR(50),
    service_area2 VARCHAR(50),
    service_area3 VARCHAR(50),
    
    -- Membership Information
    center_id INT,
    wdb_center VARCHAR(100),
    membership_plan ENUM('basic', 'premium', 'lifetime') DEFAULT 'basic',
    membership_date DATE,
    registered_by VARCHAR(255),
    
    -- Membership Status and Payments
    payment_status ENUM('paid', 'unpaid', 'partial', 'exempt') DEFAULT 'unpaid',
    membership_fee DECIMAL(10,2),
    payment_method ENUM('cash', 'bank_transfer', 'mobile_money', 'check', 'other'),
    payment_reference VARCHAR(100),
    payment_date DATE,
    membership_start_date DATE,
    membership_expiry_date DATE,
    
    -- Additional Information
    how_did_you_hear_about_us TEXT,
    motivation_to_join TEXT,
    expectations TEXT,
    previous_membership BOOLEAN DEFAULT FALSE,
    previous_membership_details TEXT,
    
    -- Photo and Documents
    photo_path VARCHAR(500),
    id_document_path VARCHAR(500),
    certificate_path VARCHAR(500),
    
    -- Agreement and Consent
    terms_accepted BOOLEAN DEFAULT FALSE,
    privacy_consent BOOLEAN DEFAULT FALSE,
    newsletter_subscription BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT TRUE,
    
    -- System Fields
    status ENUM('active', 'inactive', 'suspended', 'pending', 'deleted') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_member_id (member_id),
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_phone (mobile_phone),
    INDEX idx_center_id (center_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at),
    INDEX idx_full_name (full_name),
    INDEX idx_region (region),
    INDEX idx_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. MEMBER SERVICE PREFERENCES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS member_service_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    service_area_id INT NOT NULL,
    priority_order INT NOT NULL, -- 1, 2, or 3
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (service_area_id) REFERENCES service_areas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_priority (member_id, priority_order),
    UNIQUE KEY unique_member_service (member_id, service_area_id),
    INDEX idx_member_id (member_id),
    INDEX idx_service_area_id (service_area_id),
    INDEX idx_priority (priority_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. CONTRIBUTIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS contributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT,
    center_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('financial', 'material', 'service', 'other') DEFAULT 'financial',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'ETB',
    contribution_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'mobile_money', 'check', 'other'),
    payment_status ENUM('pending', 'paid', 'partial', 'cancelled') DEFAULT 'pending',
    reference_number VARCHAR(100),
    receipt_number VARCHAR(100),
    notes TEXT,
    status ENUM('active', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    recorded_by INT,
    
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE RESTRICT,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_member_id (member_id),
    INDEX idx_center_id (center_id),
    INDEX idx_contribution_date (contribution_date),
    INDEX idx_payment_status (payment_status),
    INDEX idx_type (type),
    INDEX idx_amount (amount),
    INDEX idx_recorded_by (recorded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. LANGUAGES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(5) UNIQUE NOT NULL,
    name VARCHAR(50) NOT NULL,
    native_name VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    text_direction ENUM('ltr', 'rtl') DEFAULT 'ltr',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_active (is_active),
    INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. TRANSLATION KEYS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS translation_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_key_name (key_name),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. TRANSLATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_id INT NOT NULL,
    language_id INT NOT NULL,
    translation TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    
    FOREIGN KEY (key_id) REFERENCES translation_keys(id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_translation (key_id, language_id),
    INDEX idx_key_id (key_id),
    INDEX idx_language_id (language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. AUDIT LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    user_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. SYSTEM SETTINGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. MEMBER FAMILY TABLE (Optional)
-- =====================================================
CREATE TABLE IF NOT EXISTS member_family (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    family_member_name VARCHAR(255) NOT NULL,
    relationship ENUM('spouse', 'child', 'parent', 'sibling', 'other') NOT NULL,
    age INT,
    occupation VARCHAR(255),
    phone VARCHAR(20),
    is_emergency_contact BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_member_id (member_id),
    INDEX idx_relationship (relationship),
    INDEX idx_emergency_contact (is_emergency_contact)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. MEMBER DOCUMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS member_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    document_type ENUM('photo', 'id_document', 'certificate', 'reference_letter', 'other') NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_member_id (member_id),
    INDEX idx_document_type (document_type),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Display success message
SELECT 'All tables created successfully!' as Status;