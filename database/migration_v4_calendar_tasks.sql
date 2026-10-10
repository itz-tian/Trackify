-- v16-trial: custom calendar tasks.
-- calendar.php creates this table automatically on first visit; run this file only if you prefer to do it manually.
CREATE TABLE IF NOT EXISTS calendar_tasks (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
