SET @ticket_orders_has_session_token := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ticket_orders'
      AND COLUMN_NAME = 'session_token'
);
SET @ticket_orders_add_session_token_sql := IF(
    @ticket_orders_has_session_token = 0,
    'ALTER TABLE ticket_orders ADD COLUMN session_token VARCHAR(100) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE ticket_orders_add_session_token_stmt FROM @ticket_orders_add_session_token_sql;
EXECUTE ticket_orders_add_session_token_stmt;
DEALLOCATE PREPARE ticket_orders_add_session_token_stmt;

SET @ticket_orders_has_session_index := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ticket_orders'
      AND INDEX_NAME = 'idx_ticket_orders_session_status_hold'
);
SET @ticket_orders_add_session_index_sql := IF(
    @ticket_orders_has_session_index = 0,
    'ALTER TABLE ticket_orders ADD INDEX idx_ticket_orders_session_status_hold (session_token, status, hold_expires_at)',
    'SELECT 1'
);
PREPARE ticket_orders_add_session_index_stmt FROM @ticket_orders_add_session_index_sql;
EXECUTE ticket_orders_add_session_index_stmt;
DEALLOCATE PREPARE ticket_orders_add_session_index_stmt;
