-- ─────────────────────────────────────────────────────────────────────────────
-- Trackify — Dedicated MySQL Application User
-- Run this ONE TIME on your production MySQL server (as root / DBA).
-- After running, update config/config.php:
--   define('DB_USER', 'trackify_app');
--   define('DB_PASS', 'CHANGE_THIS_TO_YOUR_STRONG_PASSWORD');
-- ─────────────────────────────────────────────────────────────────────────────

-- STEP 1: Create the user (replace the password with something strong)
CREATE USER IF NOT EXISTS 'trackify_app'@'localhost'
    IDENTIFIED BY 'CHANGE_THIS_TO_YOUR_STRONG_PASSWORD';

-- STEP 2: Grant only what the application actually needs.
--         No CREATE/DROP/ALTER/GRANT — the app never changes the schema at runtime.
GRANT SELECT, INSERT, UPDATE, DELETE
    ON dms_db.*
    TO 'trackify_app'@'localhost';

-- STEP 3: The auto-init path in database.php needs CREATE DATABASE + FILE for first run.
--         If you pre-create the database manually (recommended for production),
--         you can skip this grant entirely.
-- GRANT CREATE ON *.* TO 'trackify_app'@'localhost';

-- STEP 4: Apply the changes
FLUSH PRIVILEGES;

-- STEP 5: Verify (optional)
-- SHOW GRANTS FOR 'trackify_app'@'localhost';
