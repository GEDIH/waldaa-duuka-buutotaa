-- =====================================================
-- WDB Membership System - Database Creation Script
-- =====================================================
-- This script creates the main database and sets up basic configuration
-- Run this first before running other scripts

-- Create database with proper character set and collation
CREATE DATABASE IF NOT EXISTS wdb_membership 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Use the database
USE wdb_membership;

-- Set SQL mode for better compatibility
SET SQL_MODE = 'NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- Set timezone (adjust as needed)
SET time_zone = '+03:00';

-- Display success message
SELECT 'Database wdb_membership created successfully!' as Status;