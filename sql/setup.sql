-- ============================================================
-- WDB Membership System — Complete Database Setup
-- Run once against MySQL on port 3306:
--   mysql -h localhost -P 3306 -u root -p < sql/setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS wdb_membership
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE wdb_membership;

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. centers ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS centers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    code        VARCHAR(50)  UNIQUE,
    description TEXT,
    location    VARCHAR(255),
    address     TEXT,
    city        VARCHAR(100),
    region      VARCHAR(100),
    country     VARCHAR(100) DEFAULT 'Ethiopia',
    phone       VARCHAR(30),
    email       VARCHAR(255),
    status      ENUM('active','inactive','suspended') DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. users (authentication) ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(100) NOT NULL UNIQUE,
    email               VARCHAR(255) UNIQUE,          -- NULL allowed (phone-only accounts)
    password_hash       VARCHAR(255) NOT NULL,
    full_name           VARCHAR(200),
    phone               VARCHAR(30),
    role                ENUM('user','admin','superadmin','system_admin') DEFAULT 'user',
    status              ENUM('active','inactive','suspended','pending') DEFAULT 'active',
    member_id           VARCHAR(50),                  -- links to members.member_id
    last_login          TIMESTAMP NULL,
    login_attempts      INT DEFAULT 0,
    locked_until        TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username  (username),
    INDEX idx_email     (email),
    INDEX idx_role      (role),
    INDEX idx_member_id (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. members (full membership data) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS members (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    member_id   VARCHAR(50) UNIQUE NOT NULL,

    -- Personal
    full_name           VARCHAR(255),
    first_name          VARCHAR(100),
    last_name           VARCHAR(100),
    baptism_name        VARCHAR(100),
    facebook_name       VARCHAR(100),
    date_of_birth       DATE,
    gender              ENUM('male','female','other') NULL,
    marital_status      ENUM('single','married','divorced','widowed') NULL,
    spouse_name         VARCHAR(100),
    spouse_phone        VARCHAR(30),
    confession_father   VARCHAR(100),

    -- Contact
    mobile_phone        VARCHAR(30),
    phone               VARCHAR(30),                  -- alias kept for legacy code
    email               VARCHAR(255),
    address             TEXT,

    -- Education & Occupation
    education_level     ENUM('primary','secondary','tvet','bachelor','master','phd','other') NULL,
    field_of_study      VARCHAR(200),
    university          VARCHAR(200),
    graduation_year     VARCHAR(10),
    occupation          VARCHAR(200),
    profession          VARCHAR(200),                 -- alias kept for legacy code
    organization        VARCHAR(255),

    -- Clergy
    diaconate_year      VARCHAR(10),
    diaconate_church    VARCHAR(255),
    diaconate_father    VARCHAR(255),
    priesthood_year     VARCHAR(10),
    priesthood_church   VARCHAR(255),
    priesthood_father   VARCHAR(255),
    monastic_year       VARCHAR(10),
    monastic_monastery  VARCHAR(255),
    monastic_father     VARCHAR(255),

    -- Regional & Church
    region              VARCHAR(100),
    country             VARCHAR(100) DEFAULT 'Ethiopia',
    state_region        VARCHAR(100),
    sub_city            VARCHAR(100),
    city                VARCHAR(100),
    postal_code         VARCHAR(20),
    current_church      VARCHAR(255),
    church_woreda       VARCHAR(100),
    current_service     VARCHAR(255),

    -- Service areas (comma-separated codes, max 3)
    service_areas       TEXT,

    -- Membership
    center_id           INT,
    center              VARCHAR(100),
    wdb_center          VARCHAR(100),
    membership_plan     ENUM('basic','premium','lifetime') DEFAULT 'basic',
    center_role         VARCHAR(50),
    membership_date     DATE,
    membership_start_date DATE,
    membership_expiry_date DATE,

    -- Payment
    payment_status      ENUM('paid','unpaid','partial','exempt') DEFAULT 'unpaid',
    payment_method      VARCHAR(50),
    payment_reference   VARCHAR(100),
    payment_date        DATE,
    membership_fee      DECIMAL(10,2),

    -- Legacy fields
    baptized            ENUM('yes','no') NULL,
    service_interest    TEXT,
    how_heard           VARCHAR(255),

    -- Photo / docs
    photo_path          VARCHAR(500),
    photo               VARCHAR(255),

    -- Consent
    terms_accepted      TINYINT(1) DEFAULT 0,
    privacy_consent     TINYINT(1) DEFAULT 0,

    -- Workflow
    membership_form_complete TINYINT(1) DEFAULT 0,
    notes               TEXT,
    status              ENUM('active','inactive','suspended','pending','deleted') DEFAULT 'pending',
    registration_date   DATETIME,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_by         INT,
    approved_at         TIMESTAMP NULL,

    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE SET NULL,
    FOREIGN KEY (center_id) REFERENCES centers(id)  ON DELETE SET NULL,

    INDEX idx_member_id  (member_id),
    INDEX idx_user_id    (user_id),
    INDEX idx_email      (email),
    INDEX idx_phone      (mobile_phone),
    INDEX idx_status     (status),
    INDEX idx_center_id  (center_id),
    INDEX idx_form_complete (membership_form_complete)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. administrators ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS administrators (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNIQUE NOT NULL,
    full_name   VARCHAR(255) NOT NULL,
    email       VARCHAR(255),
    phone       VARCHAR(30),
    position    VARCHAR(100),
    department  VARCHAR(100),
    status      ENUM('active','inactive','suspended') DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. admin_centers (many-to-many) ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_centers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT NOT NULL,
    center_id   INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id)  REFERENCES administrators(id) ON DELETE CASCADE,
    FOREIGN KEY (center_id) REFERENCES centers(id)        ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (admin_id, center_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. contributions ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contributions (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    member_id           INT,
    center_id           INT,
    title               VARCHAR(255),
    type                ENUM('financial','material','service','tithe','offering','other') DEFAULT 'financial',
    amount              DECIMAL(12,2) DEFAULT 0.00,
    currency            VARCHAR(3) DEFAULT 'ETB',
    contribution_date   DATE,
    payment_method      VARCHAR(50),
    payment_status      ENUM('pending','confirmed','failed','cancelled') DEFAULT 'pending',
    reference_number    VARCHAR(100),
    receipt_number      VARCHAR(100),
    notes               TEXT,
    status              ENUM('active','deleted') DEFAULT 'active',
    recorded_by         INT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id)   REFERENCES members(id)  ON DELETE SET NULL,
    FOREIGN KEY (center_id)   REFERENCES centers(id)  ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_member_id (member_id),
    INDEX idx_center_id (center_id),
    INDEX idx_date      (contribution_date),
    INDEX idx_status    (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. audit_logs ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    user_type   ENUM('admin','member','system') DEFAULT 'system',
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id   INT,
    details     TEXT,
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action     (action),
    INDEX idx_user_id    (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. registration_log ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS registration_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    action      VARCHAR(50) NOT NULL,
    member_id   VARCHAR(50),
    email       VARCHAR(255),
    user_id     INT,
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    timestamp   DATETIME NOT NULL,
    INDEX idx_member_id (member_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. system_settings ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    description   TEXT,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. user_sessions ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_sessions (
    id            VARCHAR(128) PRIMARY KEY,
    user_id       INT NOT NULL,
    ip_address    VARCHAR(45),
    user_agent    TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id  (user_id),
    INDEX idx_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seed data ─────────────────────────────────────────────────────────────────

-- Default center
INSERT IGNORE INTO centers (name, code, location, city, country, status)
VALUES ('Wiirtuu Muummee', 'WDB-MAIN', 'Finfinne / Addis Ababa', 'Addis Ababa', 'Ethiopia', 'active');

-- Default superadmin  (password: Admin@1234 — CHANGE THIS IMMEDIATELY)
INSERT IGNORE INTO users (username, email, password_hash, full_name, role, status)
VALUES (
    'superadmin',
    'admin@wdb.org',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System Administrator',
    'superadmin',
    'active'
);

-- Default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('site_name',               'Waldaa Duuka Bu\'ootaa',  'Organisation name'),
('site_email',              'info@wdb.org',             'Main contact email'),
('registration_enabled',    '1',                        'Allow new member registration'),
('member_approval_required','1',                        'Require admin approval for new members'),
('default_language',        'or',                       'Default UI language');

SELECT 'WDB database setup complete!' AS Status;
