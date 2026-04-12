SET @db_name := DATABASE();

SET @drop_fk_users_role := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = @db_name
              AND TABLE_NAME = 'users'
              AND CONSTRAINT_NAME = 'fk_users_role'
        ),
        'ALTER TABLE users DROP FOREIGN KEY fk_users_role',
        'SELECT 1'
    )
);
PREPARE stmt FROM @drop_fk_users_role;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_role_id_column := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'role_id'
        ),
        'ALTER TABLE users DROP COLUMN role_id',
        'SELECT 1'
    )
);
PREPARE stmt FROM @drop_role_id_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
