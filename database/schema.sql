-- Database Schema

CREATE DATABASE IF NOT EXISTS dms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dms_db;

-- Departments
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'department_head', 'staff', 'viewer') DEFAULT 'staff',
    avatar VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Document Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#3b82f6',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Documents
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    department_id INT,
    uploaded_by INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    tags VARCHAR(500),
    status ENUM('draft', 'pending_review', 'under_review', 'approved', 'rejected', 'archived') DEFAULT 'draft',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    version INT DEFAULT 1,
    parent_document_id INT NULL,
    expires_at DATE NULL,
    reference_number VARCHAR(50) UNIQUE NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_documents_deleted_at ON documents(deleted_at);
CREATE INDEX idx_documents_expires ON documents(expires_at);

-- Document Versions (Revision History)
CREATE TABLE document_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    notes TEXT,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Approval Workflow
CREATE TABLE approval_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    approver_id INT NOT NULL,
    step_order INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'skipped') DEFAULT 'pending',
    remarks TEXT,
    acted_at TIMESTAMP NULL,
    deadline DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity Log (Audit Trail)
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
);

-- Login Attempts (Brute Force Rate Limiting)
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_attempted_at (attempted_at)
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_id INT,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
);

-- Comments
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Calendar Tasks
CREATE TABLE calendar_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    due_date DATE NOT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    visibility ENUM('private','department') NOT NULL DEFAULT 'private',
    department_id INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ct_due (due_date),
    INDEX idx_ct_user (created_by),
    INDEX idx_ct_dept (department_id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed: Default Departments
INSERT IGNORE INTO departments (name, description) VALUES
('Administration', 'General office administration'),
('Human Resources', 'HR and personnel management'),
('Finance', 'Accounting and finance'),
('IT Department', 'Information technology'),
('Operations', 'Day-to-day operations');

-- Seed: Default Categories
INSERT IGNORE INTO categories (name, description, color) VALUES
('Memo', 'Internal memos and circulars', '#3b82f6'),
('Letter', 'Formal letters', '#10b981'),
('Report', 'Reports and summaries', '#f59e0b'),
('Contract', 'Legal contracts and agreements', '#ef4444'),
('Form', 'Official forms and requests', '#8b5cf6'),
('Policy', 'Company policies and guidelines', '#06b6d4'),
('Invoice', 'Billing and invoices', '#f97316'),
('Others', 'Miscellaneous documents', '#6b7280');

-- Seed: User Accounts
-- Passwords are bcrypt cost-12 hashes. Change all passwords immediately after first login.
-- admin / admin2       → Trackify@Admin2026!
-- hr.head             → HrHead@Secure99!
-- finance.head        → Finance@Secure99!
-- it.head             → ItHead@Secure99!
-- operations.head     → Ops@Secure99!
-- staff1              → Staff1@Pass2026!
-- staff2              → Staff2@Pass2026!
-- staff3              → Staff3@Pass2026!
-- staff4              → Staff4@Pass2026!

INSERT IGNORE INTO users (department_id, first_name, last_name, email, password, role) VALUES
-- Admins
(1, 'System', 'Admin',    'admin@dms.local',        '$2y$12$qLV../3kD7s4KImzknSSuOpKV0XrBO/JYgDf/nSi1qZlHXgzj9tpm', 'admin'),
(1, 'Anna',   'Santos',   'admin2@dms.local',        '$2y$12$60lrl26YCj4Q84C64VmLkuqPJ1UCfBiGquIxGQ.UaDUj3/GPwMKJO', 'admin'),
-- Department Heads
(2, 'Mark',    'Reyes',    'hr.head@dms.local',         '$2y$12$Fkdnk62MFTR.anwPESICUuWsTMyn7.VKJQsbqIhAPufV0R1Fiy2MC', 'department_head'),
(3, 'Liza',    'Cruz',     'finance.head@dms.local',    '$2y$12$mCG9X39sHvOebSs4z9EJHemGK86VeiL9cD49OgB1SHPMnmpzs6Gam', 'department_head'),
(4, 'Carlo',   'Mendoza',  'it.head@dms.local',         '$2y$12$L38l/N3pcUSMDs.ysAkr/eLL0C214ivGB59QTHdgWM.GFSXwReaom', 'department_head'),
(5, 'Roberto', 'Morales',  'operations.head@dms.local', '$2y$12$JpIiZJUOKHFciP6L.Fe0Le6N0UsUPlR/R1/d6sFyV2GMVWfrLSn.u', 'department_head'),
-- Staff
(2, 'Maria',   'Bautista', 'staff1@dms.local',          '$2y$12$178mxKqEUEBsVNOp5O0PIOarfyy5lijOaOOFGW./5kjZq6Ne9AXAa', 'staff'),
(3, 'Jose',    'Garcia',   'staff2@dms.local',          '$2y$12$DMDZKNX9OoT6S9/H1ghbt.1N9BNFPkV2Nk5JVsOatI.a3eEl4.aIq', 'staff'),
(4, 'Rosa',    'Dela Cruz','staff3@dms.local',          '$2y$12$Vdhcznh3MVLhYNpNhksO3edLQJywE9l4ctfzNjkt5Hf5/jeSy3L2i', 'staff'),
(5, 'Pedro',   'Ramos',    'staff4@dms.local',          '$2y$12$I6I9r0BNTwlHMKHDUqD/HOnWZfVfTF6cFu7zQNFVBV4qMmXT32AYW', 'staff');
