START TRANSACTION;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'staff'
          AND column_name = 'default_vehicle_id'
    ),
    'SELECT 1',
    'ALTER TABLE staff ADD COLUMN default_vehicle_id INT(11) NULL DEFAULT NULL AFTER staff_role'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'staff'
          AND column_name = 'active'
    ),
    'SELECT 1',
    'ALTER TABLE staff ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER default_vehicle_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'vehicles'
          AND column_name = 'active'
    ),
    'SELECT 1',
    'ALTER TABLE vehicles ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER vehicle_plate'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE staff
SET active = 1
WHERE active IS NULL;

UPDATE vehicles
SET active = 1
WHERE active IS NULL;

UPDATE staff s
LEFT JOIN vehicles v ON s.default_vehicle_id = v.vehicle_id
SET s.default_vehicle_id = NULL
WHERE s.default_vehicle_id IS NOT NULL
  AND v.vehicle_id IS NULL;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'staff'
          AND index_name = 'idx_staff_active'
    ),
    'SELECT 1',
    'ALTER TABLE staff ADD INDEX idx_staff_active (active)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'staff'
          AND index_name = 'idx_staff_default_vehicle'
    ),
    'SELECT 1',
    'ALTER TABLE staff ADD INDEX idx_staff_default_vehicle (default_vehicle_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'vehicles'
          AND index_name = 'idx_vehicles_active'
    ),
    'SELECT 1',
    'ALTER TABLE vehicles ADD INDEX idx_vehicles_active (active)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.table_constraints
        WHERE table_schema = DATABASE()
          AND table_name = 'staff'
          AND constraint_type = 'FOREIGN KEY'
          AND constraint_name = 'fk_staff_default_vehicle'
    ),
    'SELECT 1',
    'ALTER TABLE staff ADD CONSTRAINT fk_staff_default_vehicle FOREIGN KEY (default_vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
