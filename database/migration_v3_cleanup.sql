-- Cleanup for databases created from an older schema.sql.
-- Fresh imports of the current schema.sql do NOT need this file.
-- Safe to run more than once.
USE dms_db;

-- document_permissions was never used by the application.
DROP TABLE IF EXISTS document_permissions;

DELIMITER $$

DROP PROCEDURE IF EXISTS migration_v3_cleanup$$
CREATE PROCEDURE migration_v3_cleanup()
BEGIN
    -- reference_number is already UNIQUE, which creates its own index.
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_reference') THEN
        DROP INDEX idx_documents_reference ON documents;
    END IF;
END$$

DELIMITER ;

CALL migration_v3_cleanup();
DROP PROCEDURE migration_v3_cleanup;
