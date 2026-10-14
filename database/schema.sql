-- Database Schema
-- Version 2.0 — security & integrity hardening

CREATE DATABASE IF NOT EXISTS dms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dms_db;

-- ─────────────────────────────────────────────
-- DEPARTMENTS
-- ─────────────────────────────────────────────
CREATE TABLE departments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) UNIQUE NOT NULL,
    description  TEXT,
    -- Fix #21: department head FK added after users table is created (see ALTER below)
    head_user_id INT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP  -- Fix #18: DATETIME avoids 2038
);

-- ─────────────────────────────────────────────
-- ACTION TYPES  (Fix #12: enum-like lookup table for audit/notif actions)
-- ─────────────────────────────────────────────
CREATE TABLE action_types (
    id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) UNIQUE NOT NULL
);

INSERT IGNORE INTO action_types (name) VALUES
('upload'), ('update'), ('delete'), ('restore'), ('archive'),
('submit_review'), ('approve'), ('reject'), ('comment'),
('login'), ('logout'), ('password_change'), ('view'), ('download');

-- ─────────────────────────────────────────────
-- USERS
-- ─────────────────────────────────────────────
CREATE TABLE users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    department_id       INT,
    first_name          VARCHAR(100) NOT NULL,
    last_name           VARCHAR(100) NOT NULL,
    email               VARCHAR(150) UNIQUE NOT NULL,
    password            VARCHAR(255) NOT NULL,
    role                ENUM('admin','department_head','staff') DEFAULT 'staff',
    avatar              VARCHAR(255),
    is_active           TINYINT(1) NOT NULL DEFAULT 1,   -- Fix #2: never DELETE users
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,  -- Fix #1: force PW rotation
    locked_until        DATETIME NULL DEFAULT NULL,       -- Fix #16: brute-force lockout
    deleted_at          DATETIME NULL DEFAULT NULL,       -- Fix #2: soft-delete users
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,             -- Fix #18
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Fix #21: now that users exists, add the FK for department head
ALTER TABLE departments
    ADD CONSTRAINT fk_dept_head
    FOREIGN KEY (head_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- ─────────────────────────────────────────────
-- DOCUMENT CATEGORIES
-- ─────────────────────────────────────────────
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    -- Fix #21: enforce valid hex colour
    color       VARCHAR(7) DEFAULT '#3b82f6'
                    CHECK (color REGEXP '^#[0-9a-fA-F]{6}$'),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP  -- Fix #18
);

-- ─────────────────────────────────────────────
-- TAGS  (Fix #8: normalise tags out of VARCHAR(500))
-- ─────────────────────────────────────────────
CREATE TABLE tags (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────────
-- DOCUMENTS
-- ─────────────────────────────────────────────
CREATE TABLE documents (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    title              VARCHAR(255) NOT NULL,
    description        TEXT,
    category_id        INT,
    department_id      INT,
    uploaded_by        INT NOT NULL,
    file_name          VARCHAR(255) NOT NULL,
    file_path          VARCHAR(500) NOT NULL,
    file_size          BIGINT UNSIGNED NOT NULL,  -- Fix #17: was INT
    file_hash          CHAR(64) NULL,              -- Fix #17: SHA-256 dedup / integrity
    file_type          VARCHAR(100) NOT NULL,
    -- Fix #8: tags column kept for backward compat; use document_tags for new writes
    -- shortcut: tags column still read by old code; migrate reads to document_tags, then drop
    tags               VARCHAR(500),
    status             ENUM('draft','pending_review','under_review','approved',
                            'rejected','archived') DEFAULT 'draft',
    priority           ENUM('low','normal','high','urgent') DEFAULT 'normal',
    -- Fix #9/Fix #7: version mirrors current_version_id.version; keep in sync in one txn
    version            INT DEFAULT 1,
    -- Fix #7: parent_document_id = "copied from" only, NOT a version chain
    parent_document_id INT NULL,
    -- Fix #7: canonical pointer to the current revision row
    current_version_id INT NULL,
    -- Fix #14: per-document access level
    confidentiality    ENUM('public','internal','confidential','restricted')
                            NOT NULL DEFAULT 'internal',
    expires_at         DATE NULL,
    -- Fix #20: reference number generated from counter table, made non-null after approval
    reference_number   VARCHAR(50) UNIQUE NULL,
    deleted_at         DATETIME NULL,   -- Fix #18
    deleted_by         INT NULL,
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Fix #18
    updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id)        REFERENCES categories(id)  ON DELETE SET NULL,
    FOREIGN KEY (department_id)      REFERENCES departments(id)  ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by)        REFERENCES users(id)        ON DELETE RESTRICT,  -- Fix #2
    FOREIGN KEY (parent_document_id) REFERENCES documents(id)    ON DELETE SET NULL,
    FOREIGN KEY (deleted_by)         REFERENCES users(id)        ON DELETE SET NULL
    -- current_version_id FK added after document_versions is created (see ALTER below)
);

CREATE INDEX idx_documents_deleted_at ON documents(deleted_at);
CREATE INDEX idx_documents_expires    ON documents(expires_at);
-- Fix #15: additional composite indexes for common query patterns
CREATE INDEX idx_documents_status     ON documents(status);
CREATE INDEX idx_documents_cat        ON documents(category_id);
CREATE INDEX idx_documents_uploader   ON documents(uploaded_by);
CREATE INDEX idx_documents_dept_stat  ON documents(department_id, status);
CREATE FULLTEXT INDEX idx_documents_ft ON documents(title, description);

-- ─────────────────────────────────────────────
-- DOCUMENT → TAGS join  (Fix #8: 1NF)
-- ─────────────────────────────────────────────
CREATE TABLE document_tags (
    document_id INT NOT NULL,
    tag_id      INT NOT NULL,
    PRIMARY KEY (document_id, tag_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)      REFERENCES tags(id)      ON DELETE CASCADE
);

-- ─────────────────────────────────────────────
-- DOCUMENT VERSIONS
-- ─────────────────────────────────────────────
CREATE TABLE document_versions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    version     INT NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    file_size   BIGINT UNSIGNED NOT NULL,  -- Fix #17
    file_hash   CHAR(64) NULL,              -- Fix #17
    notes       TEXT,
    uploaded_by INT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Fix #18
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)    ON DELETE RESTRICT,  -- Fix #4: consistent RESTRICT
    -- Fix #5: no duplicate version number per document
    UNIQUE KEY uq_doc_version (document_id, version)
);

-- Fix #7: back-fill the FK now that document_versions exists
ALTER TABLE documents
    ADD CONSTRAINT fk_doc_current_version
    FOREIGN KEY (current_version_id) REFERENCES document_versions(id) ON DELETE SET NULL;

-- ─────────────────────────────────────────────
-- DOCUMENT PERMISSIONS  (Fix #14: per-document ACL)
-- ─────────────────────────────────────────────
CREATE TABLE document_permissions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    document_id   INT NOT NULL,
    -- grant to a user OR a department (at least one must be non-NULL)
    user_id       INT NULL,
    department_id INT NULL,
    level         ENUM('read','comment','edit') NOT NULL DEFAULT 'read',
    granted_by    INT NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id)   REFERENCES documents(id)   ON DELETE CASCADE,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by)    REFERENCES users(id)       ON DELETE CASCADE
);

-- ─────────────────────────────────────────────
-- APPROVAL WORKFLOWS
-- ─────────────────────────────────────────────
CREATE TABLE approval_workflows (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    document_id  INT NOT NULL,
    approver_id  INT NULL,   -- Fix #3: SET NULL so log survives user deletion
    -- Fix #6: track resubmission rounds to avoid stale rows mixing with new ones
    round        INT NOT NULL DEFAULT 1,
    step_order   INT NOT NULL DEFAULT 1,
    status       ENUM('pending','approved','rejected','skipped') DEFAULT 'pending',
    remarks      TEXT,
    acted_at     DATETIME NULL,    -- Fix #18
    deadline     DATE NULL,
    -- Fix #11: acted_at must be set iff status is not pending
    CONSTRAINT chk_approval_acted
        CHECK ((status = 'pending' AND acted_at IS NULL)
            OR (status <> 'pending' AND acted_at IS NOT NULL)),
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Fix #18
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id)    ON DELETE SET NULL,  -- Fix #3
    -- Fix #6: one step per (document, round, step_order)
    UNIQUE KEY uq_approval_round (document_id, round, step_order)
);

-- Fix #15: index for pending approvals per approver
CREATE INDEX idx_aw_approver_status ON approval_workflows(approver_id, status);

-- ─────────────────────────────────────────────
-- ACTIVITY LOG  (Fix #3, #12: SET NULL + snapshot + typed action)
-- ─────────────────────────────────────────────
CREATE TABLE activity_logs (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NULL,                  -- Fix #3: SET NULL on user delete
    user_name_snapshot VARCHAR(100) NULL,        -- Fix #3: preserve actor name
    document_id      INT NULL,
    action_type_id   TINYINT UNSIGNED NOT NULL,  -- Fix #12: FK to action_types
    description      TEXT,
    ip_address       VARCHAR(45),
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Fix #18; append-only
    FOREIGN KEY (user_id)        REFERENCES users(id)       ON DELETE SET NULL,  -- Fix #3
    FOREIGN KEY (document_id)    REFERENCES documents(id)   ON DELETE SET NULL,
    FOREIGN KEY (action_type_id) REFERENCES action_types(id)
);

-- Fix #15: index for per-document audit trail queries
CREATE INDEX idx_al_doc_created ON activity_logs(document_id, created_at);

-- ─────────────────────────────────────────────
-- LOGIN ATTEMPTS  (Fix #16: purge via EVENT)
-- ─────────────────────────────────────────────
CREATE TABLE login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(150) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Fix #18
    INDEX idx_email        (email),
    INDEX idx_ip           (ip_address),
    INDEX idx_attempted_at (attempted_at)
);

-- Fix #16: auto-purge login_attempts older than 24 h.
-- Run the statement below manually once event_scheduler=ON is set in my.cnf:
--   CREATE EVENT IF NOT EXISTS evt_purge_login_attempts
--       ON SCHEDULE EVERY 1 HOUR
--       DO DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 24 HOUR;
-- shortcut: event omitted from DDL; enable in my.cnf and create event when deploying to prod

-- ─────────────────────────────────────────────
-- NOTIFICATIONS  (Fix #12: typed; Fix #15: index)
-- ─────────────────────────────────────────────
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    document_id INT NULL,
    -- Fix #12: restrict to known types
    type        ENUM('approval_request','approval_decision','comment','system',
                     'deadline','archive','mention') NOT NULL,
    title       VARCHAR(255) NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Fix #18
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
);

-- Fix #15: unread-count query index
CREATE INDEX idx_notif_user_read ON notifications(user_id, is_read);

-- ─────────────────────────────────────────────
-- COMMENTS  (Fix #3: SET NULL)
-- ─────────────────────────────────────────────
CREATE TABLE comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id     INT NULL,                    -- Fix #3: SET NULL on user delete
    comment     TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Fix #18
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE SET NULL  -- Fix #3
);

-- ─────────────────────────────────────────────
-- CALENDAR TASKS  (Fix #10: visibility constraint)
-- ─────────────────────────────────────────────
CREATE TABLE calendar_tasks (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    description   TEXT NULL,
    due_date      DATE NOT NULL,
    priority      ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    is_done       TINYINT(1) NOT NULL DEFAULT 0,
    visibility    ENUM('private','department') NOT NULL DEFAULT 'private',
    department_id INT NULL,
    created_by    INT NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Fix #18
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Fix #10: department_id must be set when visibility='department'
    CONSTRAINT chk_ct_dept_visibility
        CHECK (visibility = 'private' OR department_id IS NOT NULL),
    INDEX idx_ct_due  (due_date),
    INDEX idx_ct_user (created_by),
    INDEX idx_ct_dept (department_id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)    REFERENCES users(id)       ON DELETE CASCADE
);

-- ─────────────────────────────────────────────
-- REFERENCE NUMBER COUNTER  (Fix #20: race-condition-safe seq)
-- ─────────────────────────────────────────────
CREATE TABLE document_reference_seq (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    prefix   VARCHAR(20) NOT NULL DEFAULT 'REF',
    year     SMALLINT    NOT NULL,
    next_val INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_ref_seq (prefix, year)
);

-- ─────────────────────────────────────────────
-- VIEWS  (Fix #13: enforce soft-delete at DB layer)
-- ─────────────────────────────────────────────
CREATE OR REPLACE VIEW active_documents AS
    SELECT * FROM documents
    WHERE deleted_at IS NULL AND status != 'archived';

-- ─────────────────────────────────────────────
-- SEED: Default Departments
-- ─────────────────────────────────────────────
INSERT IGNORE INTO departments (name, description) VALUES
('Administration',  'General office administration'),
('Human Resources', 'HR and personnel management'),
('Finance',         'Accounting and finance'),
('IT Department',   'Information technology'),
('Operations',      'Day-to-day operations');

-- ─────────────────────────────────────────────
-- SEED: Default Categories
-- ─────────────────────────────────────────────
INSERT IGNORE INTO categories (name, description, color) VALUES
('Memo',     'Internal memos and circulars',    '#3b82f6'),
('Letter',   'Formal letters',                  '#10b981'),
('Report',   'Reports and summaries',            '#f59e0b'),
('Contract', 'Legal contracts and agreements',  '#ef4444'),
('Form',     'Official forms and requests',     '#8b5cf6'),
('Policy',   'Company policies and guidelines', '#06b6d4'),
('Invoice',  'Billing and invoices',            '#f97316'),
('Others',   'Miscellaneous documents',         '#6b7280');

-- ─────────────────────────────────────────────
-- SEED: Default Action Types
-- ─────────────────────────────────────────────
INSERT IGNORE INTO action_types (name) VALUES
('upload'),('update'),('delete'),('restore'),('archive'),
('submit_review'),('approve'),('reject'),('comment'),
('login'),('logout'),('password_change'),('view'),('download');

-- ─────────────────────────────────────────────
-- SEED: User Accounts
-- Fix #1: plaintext credential comments removed.
--         Passwords are bcrypt cost-12 hashes (never stored in plain text).
--         Set must_change_password=1 to force rotation on first login.
-- Fix #19: department_id resolved by name subselect (not hard-coded integers)
-- ─────────────────────────────────────────────
INSERT IGNORE INTO users
    (department_id, first_name, last_name, email, password, role, must_change_password)
VALUES
-- Admins
((SELECT id FROM departments WHERE name='Administration'), 'System', 'Admin',
    'admin@dms.local',
    '$2y$12$qLV../3kD7s4KImzknSSuOpKV0XrBO/JYgDf/nSi1qZlHXgzj9tpm', 'admin', 1),
((SELECT id FROM departments WHERE name='Administration'), 'Anna', 'Santos',
    'admin2@dms.local',
    '$2y$12$60lrl26YCj4Q84C64VmLkuqPJ1UCfBiGquIxGQ.UaDUj3/GPwMKJO', 'admin', 1),
-- Department Heads
((SELECT id FROM departments WHERE name='Human Resources'), 'Mark', 'Reyes',
    'hr.head@dms.local',
    '$2y$12$Fkdnk62MFTR.anwPESICUuWsTMyn7.VKJQsbqIhAPufV0R1Fiy2MC', 'department_head', 1),
((SELECT id FROM departments WHERE name='Finance'), 'Liza', 'Cruz',
    'finance.head@dms.local',
    '$2y$12$mCG9X39sHvOebSs4z9EJHemGK86VeiL9cD49OgB1SHPMnmpzs6Gam', 'department_head', 1),
((SELECT id FROM departments WHERE name='IT Department'), 'Carlo', 'Mendoza',
    'it.head@dms.local',
    '$2y$12$L38l/N3pcUSMDs.ysAkr/eLL0C214ivGB59QTHdgWM.GFSXwReaom', 'department_head', 1),
((SELECT id FROM departments WHERE name='Operations'), 'Roberto', 'Morales',
    'operations.head@dms.local',
    '$2y$12$JpIiZJUOKHFciP6L.Fe0Le6N0UsUPlR/R1/d6sFyV2GMVWfrLSn.u', 'department_head', 1),
-- Staff
((SELECT id FROM departments WHERE name='Human Resources'), 'Maria', 'Bautista',
    'staff1@dms.local',
    '$2y$12$178mxKqEUEBsVNOp5O0PIOarfyy5lijOaOOFGW./5kjZq6Ne9AXAa', 'staff', 1),
((SELECT id FROM departments WHERE name='Finance'), 'Jose', 'Garcia',
    'staff2@dms.local',
    '$2y$12$DMDZKNX9OoT6S9/H1ghbt.1N9BNFPkV2Nk5JVsOatI.a3eEl4.aIq', 'staff', 1),
((SELECT id FROM departments WHERE name='IT Department'), 'Rosa', 'Dela Cruz',
    'staff3@dms.local',
    '$2y$12$Vdhcznh3MVLhYNpNhksO3edLQJywE9l4ctfzNjkt5Hf5/jeSy3L2i', 'staff', 1),
((SELECT id FROM departments WHERE name='Operations'), 'Pedro', 'Ramos',
    'staff4@dms.local',
    '$2y$12$I6I9r0BNTwlHMKHDUqD/HOnWZfVfTF6cFu7zQNFVBV4qMmXT32AYW', 'staff', 1);
