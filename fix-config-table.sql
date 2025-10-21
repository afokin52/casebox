-- Fix missing 'order' column in config table
-- This column is required for proper config ordering
-- Apply to all databases: casebox, cb_casebox, cb_demosrc

-- Check and add column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'config';
SET @columnname = 'order';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column order already exists' AS msg",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `order` smallint(6) NULL AFTER value")
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
