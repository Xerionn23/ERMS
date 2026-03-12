CREATE DATABASE IF NOT EXISTS erms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE erms;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_employee_id (employee_id)
) ENGINE=InnoDB;

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
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_guards_guard_no (guard_no)
) ENGINE=InnoDB;

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
