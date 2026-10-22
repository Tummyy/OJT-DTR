-- OJT DTR System Database Schema
-- Run this file to create all required tables

CREATE DATABASE IF NOT EXISTS ojt_dtr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ojt_dtr;

-- Students / Users table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NULL,
    theme_preference VARCHAR(20) NOT NULL DEFAULT 'light',
    required_hours INT DEFAULT 600,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- OJT Schedule configuration per student
CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNIQUE NOT NULL,
    start_date DATE NOT NULL,
    default_time_in TIME NOT NULL DEFAULT '08:00:00',
    default_time_out TIME NOT NULL DEFAULT '17:00:00',
    break_duration DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    max_hours_per_day DECIMAL(4,2) NOT NULL DEFAULT 8.00,
    allow_overtime TINYINT(1) NOT NULL DEFAULT 0,
    max_overtime_per_day DECIMAL(4,2) NOT NULL DEFAULT 2.00,
    rest_days VARCHAR(20) DEFAULT '0,6',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Holidays table
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    name VARCHAR(150) NOT NULL,
    type ENUM('regular','special_non_working','special_working') NOT NULL DEFAULT 'regular',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_holiday (student_id, holiday_date)
);

-- Daily Time Records
CREATE TABLE IF NOT EXISTS dtr_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    record_date DATE NOT NULL,
    time_in TIME DEFAULT NULL,
    time_out TIME DEFAULT NULL,
    break_duration DECIMAL(4,2) DEFAULT 0.00,
    raw_hours DECIMAL(6,2) DEFAULT 0.00,
    regular_hours DECIMAL(6,2) DEFAULT 0.00,
    overtime_hours DECIMAL(6,2) DEFAULT 0.00,
    total_hours DECIMAL(6,2) DEFAULT 0.00,
    status ENUM('valid','incomplete','invalid','absent') DEFAULT 'incomplete',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_record (student_id, record_date)
);

-- Activity / audit logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NULL,
    action VARCHAR(60) NOT NULL,
    details VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
);

-- Password reset tokens (single-use, expiring)
CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    request_ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_reset_token_hash (token_hash)
);

-- Per-student export profile (phase 1)
CREATE TABLE IF NOT EXISTS export_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    company_name VARCHAR(150) NOT NULL DEFAULT '',
    company_address VARCHAR(255) NOT NULL DEFAULT '',
    company_logo VARCHAR(255) NOT NULL DEFAULT 'logo/Logo.png',
    supervisor_name VARCHAR(120) NOT NULL DEFAULT '',
    supervisor_title VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_export_profile_student (student_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Email verification OTPs (single-use, expiring)
CREATE TABLE IF NOT EXISTS email_verifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    code_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    resend_count INT NOT NULL DEFAULT 0,
    attempt_count INT NOT NULL DEFAULT 0,
    request_ip VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_dtr_student_date ON dtr_records(student_id, record_date);
CREATE INDEX IF NOT EXISTS idx_holidays_student_date ON holidays(student_id, holiday_date);
CREATE INDEX IF NOT EXISTS idx_activity_student_time ON activity_logs(student_id, created_at);
CREATE INDEX IF NOT EXISTS idx_activity_action_time ON activity_logs(action, created_at);
CREATE INDEX IF NOT EXISTS idx_reset_student_time ON password_resets(student_id, created_at);
CREATE INDEX IF NOT EXISTS idx_reset_expiry ON password_resets(expires_at);
CREATE INDEX IF NOT EXISTS idx_export_profile_student ON export_profiles(student_id);
CREATE INDEX IF NOT EXISTS idx_email_verify_student_time ON email_verifications(student_id, created_at);
CREATE INDEX IF NOT EXISTS idx_email_verify_expiry ON email_verifications(expires_at);

-- Sample holidays (Philippine 2025 holidays) - optional seed data
-- These will be available to all students who add them
INSERT IGNORE INTO students (id, name, email, password, required_hours) VALUES
(0, 'System', 'system@ojt-dtr.local', '', 0);

