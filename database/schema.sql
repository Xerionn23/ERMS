CREATE DATABASE IF NOT EXISTS erms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE erms;

CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NULL,
    starting_date DATE NULL,
    role ENUM('admin', 'security_operation', 'employee') NOT NULL DEFAULT 'employee',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deactivated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employees_employee_id (employee_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id VARCHAR(50) NOT NULL,
    role ENUM('admin', 'security_operation', 'employee') NOT NULL DEFAULT 'employee',
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deactivated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_employee_id (employee_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_invites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id VARCHAR(50) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_account_invites_employee_id (employee_id),
    UNIQUE KEY uq_account_invites_token_hash (token_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_employee_id VARCHAR(50) NULL,
    actor_user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NULL,
    target_id VARCHAR(80) NULL,
    detail TEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_actor_employee_id (actor_employee_id),
    KEY idx_audit_logs_action (action)
) ENGINE=InnoDB;

ALTER TABLE users
    MODIFY role ENUM('admin', 'security_operation', 'employee') NOT NULL DEFAULT 'employee';

CREATE TABLE IF NOT EXISTS guards (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    guard_no VARCHAR(50) NULL,
    last_name VARCHAR(60) NOT NULL,
    first_name VARCHAR(60) NOT NULL,
    middle_name VARCHAR(60) NULL,
    suffix VARCHAR(20) NULL,
    birthdate DATE NULL,
    age SMALLINT UNSIGNED NULL,
    agency VARCHAR(120) NULL,
    full_name VARCHAR(180) NOT NULL,
    contact_no VARCHAR(50) NULL,
    deployed DATE NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guards_guard_no (guard_no)
) ENGINE=InnoDB;

-- Add deployed column if not exists (run this if table already exists)
-- ALTER TABLE guards ADD COLUMN deployed DATE NULL AFTER contact_no;

CREATE TABLE IF NOT EXISTS requirement_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    expires TINYINT(1) NOT NULL DEFAULT 0,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_requirement_types_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS guard_requirements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    guard_id INT UNSIGNED NOT NULL,
    requirement_type_id INT UNSIGNED NOT NULL,
    document_no VARCHAR(120) NULL,
    issued_date DATE NULL,
    expiry_date DATE NULL,
    document_path VARCHAR(255) NULL,
    document_original_name VARCHAR(255) NULL,
    document_mime VARCHAR(120) NULL,
    document_size INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guard_requirements_guard_type (guard_id, requirement_type_id),
    KEY idx_guard_requirements_requirement_type_id (requirement_type_id),
    CONSTRAINT fk_guard_requirements_guard_id FOREIGN KEY (guard_id) REFERENCES guards(id) ON DELETE CASCADE,
    CONSTRAINT fk_guard_requirements_requirement_type_id FOREIGN KEY (requirement_type_id) REFERENCES requirement_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO requirement_types (code, name, expires, is_required) VALUES
('SSS', 'SSS', 0, 1),
('PAGIBIG', 'PAG-IBIG', 0, 1),
('PHILHEALTH', 'PhilHealth', 0, 1),
('SECURITY_LICENSE', 'Security License', 1, 1);
