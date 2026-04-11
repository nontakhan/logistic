-- Performance indexes for NR Logistics
-- This script is safe to run multiple times on the same database.

START TRANSACTION;

-- Remove duplicate indexes that overlap existing keys.
SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'cssale'
          AND index_name = 'idx_docno'
    ),
    'DROP INDEX idx_docno ON cssale',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'orders'
          AND index_name = 'idx_orders_updated_at'
    ),
    'DROP INDEX idx_orders_updated_at ON orders',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cssale: faster bill picker, unused CSSale cleanup, and salesman filters.
SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'cssale'
          AND index_name = 'idx_cssale_shipflag_docdate_docno'
    ),
    'SELECT 1',
    'ALTER TABLE cssale ADD INDEX idx_cssale_shipflag_docdate_docno (shipflag, docdate DESC, docno DESC)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'cssale'
          AND index_name = 'idx_cssale_docdate_docno'
    ),
    'SELECT 1',
    'ALTER TABLE cssale ADD INDEX idx_cssale_docdate_docno (docdate DESC, docno DESC)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'cssale'
          AND index_name = 'idx_cssale_code_lname'
    ),
    'SELECT 1',
    'ALTER TABLE cssale ADD INDEX idx_cssale_code_lname (code, lname)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders: faster queue pages, dashboard counters, exports, and date-range filters.
SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'orders'
          AND index_name = 'idx_orders_status_origin_orderdate_created'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD INDEX idx_orders_status_origin_orderdate_created (status, transport_origin_id, order_date, created_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'orders'
          AND index_name = 'idx_orders_status_updated_at'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD INDEX idx_orders_status_updated_at (status, updated_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'orders'
          AND index_name = 'idx_orders_origin_updated_at'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD INDEX idx_orders_origin_updated_at (transport_origin_id, updated_at)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'orders'
          AND index_name = 'idx_orders_status_docno'
    ),
    'SELECT 1',
    'ALTER TABLE orders ADD INDEX idx_orders_status_docno (status, cssale_docno)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- origin: faster analytics filters and amphoe lookup.
SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'origin'
          AND index_name = 'idx_origin_province_amphoe'
    ),
    'SELECT 1',
    'ALTER TABLE origin ADD INDEX idx_origin_province_amphoe (province, amphoe)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'origin'
          AND index_name = 'idx_origin_province_amphoe_tambon'
    ),
    'SELECT 1',
    'ALTER TABLE origin ADD INDEX idx_origin_province_amphoe_tambon (province, amphoe, tambon)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
