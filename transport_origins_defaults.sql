START TRANSACTION;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'transport_origins'
          AND column_name = 'active'
    ),
    'SELECT 1',
    'ALTER TABLE transport_origins ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER origin_name'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'transport_origins'
          AND column_name = 'is_default'
    ),
    'SELECT 1',
    'ALTER TABLE transport_origins ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER active'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE transport_origins
SET active = 1
WHERE active IS NULL;

UPDATE transport_origins
SET is_default = 0
WHERE is_default IS NULL;

UPDATE transport_origins
SET is_default = 0;

UPDATE transport_origins
SET is_default = 1
WHERE transport_origin_id = 1;

COMMIT;
