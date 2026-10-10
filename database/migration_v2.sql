-- DocuTrack Data Management Improvements
-- Safe to run on an existing dms_db database: every step checks first,
-- so running it twice (or on a fresh schema.sql import) does nothing harmful.
USE dms_db;

DELIMITER $$

DROP PROCEDURE IF EXISTS migration_v2$$
CREATE PROCEDURE migration_v2()
BEGIN
    -- Columns
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'reference_number') THEN
        ALTER TABLE documents ADD COLUMN reference_number VARCHAR(50) UNIQUE NULL AFTER expires_at;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'deleted_at') THEN
        ALTER TABLE documents ADD COLUMN deleted_at TIMESTAMP NULL AFTER reference_number;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'deleted_by') THEN
        ALTER TABLE documents ADD COLUMN deleted_by INT NULL AFTER deleted_at;
    END IF;

    -- Foreign key (skip if deleted_by already references users, whatever the constraint is named)
    IF NOT EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents'
                     AND COLUMN_NAME = 'deleted_by' AND REFERENCED_TABLE_NAME = 'users') THEN
        ALTER TABLE documents
            ADD CONSTRAINT fk_documents_deleted_by
            FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;
    END IF;

    -- Indexes
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_deleted_at') THEN
        CREATE INDEX idx_documents_deleted_at ON documents(deleted_at);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_expires') THEN
        CREATE INDEX idx_documents_expires ON documents(expires_at);
    END IF;

    -- Backfill reference numbers for old rows
    UPDATE documents
    SET reference_number = CONCAT('DOC-', YEAR(created_at), '-', LPAD(id, 6, '0'))
    WHERE reference_number IS NULL;
END$$

DELIMITER ;

CALL migration_v2();
DROP PROCEDURE migration_v2;
