-- Perfis e permissoes modulares para gestor financeiro

ALTER TABLE users
    MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user';

SET @manager_user_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'manager_user_id'
);

SET @manager_user_column_sql := IF(
    @manager_user_column_exists = 0,
    'ALTER TABLE users ADD COLUMN manager_user_id INT NULL AFTER role',
    'SELECT ''manager_user_id already exists'''
);

PREPARE manager_user_column_stmt FROM @manager_user_column_sql;
EXECUTE manager_user_column_stmt;
DEALLOCATE PREPARE manager_user_column_stmt;

SET @manager_user_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_manager_user_id'
);

SET @manager_user_index_sql := IF(
    @manager_user_index_exists = 0,
    'ALTER TABLE users ADD INDEX idx_users_manager_user_id (manager_user_id)',
    'SELECT ''idx_users_manager_user_id already exists'''
);

PREPARE manager_user_index_stmt FROM @manager_user_index_sql;
EXECUTE manager_user_index_stmt;
DEALLOCATE PREPARE manager_user_index_stmt;

CREATE TABLE IF NOT EXISTS user_module_access (
    user_id INT NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, module_key),
    KEY idx_user_module_access_module (module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
