USE movie_shop;

START TRANSACTION;

SET @schema_name := DATABASE();

-- ticket_orders: add the Ticket System v1 columns to legacy installs.
SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'order_code') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN order_code VARCHAR(32) NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'contact_name') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN contact_name VARCHAR(120) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'contact_email') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN contact_email VARCHAR(150) NULL AFTER contact_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'contact_phone') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN contact_phone VARCHAR(20) NULL AFTER contact_email',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'fulfillment_method') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN fulfillment_method ENUM(''e_ticket'',''counter_pickup'') DEFAULT ''e_ticket'' AFTER contact_phone',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'seat_count') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN seat_count INT NOT NULL DEFAULT 0 AFTER fulfillment_method',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'subtotal_price') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN subtotal_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER seat_count',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'discount_amount') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal_price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'fee_amount') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'currency') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''VND'' AFTER total_price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'hold_expires_at') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN hold_expires_at TIMESTAMP NULL DEFAULT NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'paid_at') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL AFTER hold_expires_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'cancelled_at') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER paid_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE ticket_orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER order_date',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE ticket_orders
SET total_price = COALESCE(total_price, 0.00);

ALTER TABLE ticket_orders
    MODIFY COLUMN total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN status ENUM('pending','paid','cancelled','expired','refunded') DEFAULT 'pending';

UPDATE ticket_orders o
SET
    order_code = COALESCE(NULLIF(order_code, ''), CONCAT('TKT-', LPAD(o.id, 6, '0'))),
    seat_count = (
        SELECT COUNT(*)
        FROM ticket_details td
        WHERE td.order_id = o.id
    ),
    subtotal_price = COALESCE((
        SELECT SUM(COALESCE(td.price, 0.00))
        FROM ticket_details td
        WHERE td.order_id = o.id
    ), 0.00),
    discount_amount = COALESCE(discount_amount, 0.00),
    fee_amount = COALESCE(fee_amount, 0.00),
    total_price = COALESCE(total_price, 0.00),
    currency = COALESCE(NULLIF(currency, ''), 'VND'),
    fulfillment_method = COALESCE(fulfillment_method, 'e_ticket'),
    hold_expires_at = CASE
        WHEN status = 'pending' THEN COALESCE(hold_expires_at, DATE_ADD(order_date, INTERVAL 15 MINUTE))
        ELSE hold_expires_at
    END,
    paid_at = CASE
        WHEN status = 'paid' THEN COALESCE(paid_at, order_date)
        ELSE paid_at
    END,
    cancelled_at = CASE
        WHEN status IN ('cancelled', 'expired') THEN COALESCE(cancelled_at, order_date)
        ELSE cancelled_at
    END;

ALTER TABLE ticket_orders
    MODIFY COLUMN order_code VARCHAR(32) NOT NULL,
    MODIFY COLUMN seat_count INT NOT NULL DEFAULT 0,
    MODIFY COLUMN subtotal_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN currency CHAR(3) NOT NULL DEFAULT 'VND';

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND INDEX_NAME = 'uniq_ticket_orders_order_code') = 0,
    'ALTER TABLE ticket_orders ADD UNIQUE KEY uniq_ticket_orders_order_code (order_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND INDEX_NAME = 'idx_ticket_orders_user_status') = 0,
    'ALTER TABLE ticket_orders ADD INDEX idx_ticket_orders_user_status (user_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND INDEX_NAME = 'idx_ticket_orders_status_order_date') = 0,
    'ALTER TABLE ticket_orders ADD INDEX idx_ticket_orders_status_order_date (status, order_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_orders' AND INDEX_NAME = 'idx_ticket_orders_hold_expires') = 0,
    'ALTER TABLE ticket_orders ADD INDEX idx_ticket_orders_hold_expires (hold_expires_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ticket_details: add the line-level Ticket System v1 columns.
SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'ticket_code') = 0,
    'ALTER TABLE ticket_details ADD COLUMN ticket_code VARCHAR(40) NULL AFTER seat_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE ticket_details ADD COLUMN status ENUM(''pending'',''paid'',''cancelled'',''expired'',''refunded'',''used'') DEFAULT ''pending'' AFTER ticket_code',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'base_price') = 0,
    'ALTER TABLE ticket_details ADD COLUMN base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'surcharge_amount') = 0,
    'ALTER TABLE ticket_details ADD COLUMN surcharge_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER base_price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'discount_amount') = 0,
    'ALTER TABLE ticket_details ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER surcharge_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'qr_payload') = 0,
    'ALTER TABLE ticket_details ADD COLUMN qr_payload VARCHAR(255) NULL AFTER price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'scanned_at') = 0,
    'ALTER TABLE ticket_details ADD COLUMN scanned_at TIMESTAMP NULL DEFAULT NULL AFTER qr_payload',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE ticket_details ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER scanned_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE ticket_details ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE ticket_details td
INNER JOIN ticket_orders o ON o.id = td.order_id
SET
    td.ticket_code = COALESCE(NULLIF(td.ticket_code, ''), CONCAT('TIC-', LPAD(td.id, 8, '0'))),
    td.status = COALESCE(td.status, CASE
        WHEN o.status = 'paid' THEN 'paid'
        WHEN o.status = 'cancelled' THEN 'cancelled'
        WHEN o.status = 'expired' THEN 'expired'
        WHEN o.status = 'refunded' THEN 'refunded'
        ELSE 'pending'
    END),
    td.base_price = COALESCE(td.base_price, td.price, 0.00),
    td.surcharge_amount = COALESCE(td.surcharge_amount, 0.00),
    td.discount_amount = COALESCE(td.discount_amount, 0.00),
    td.price = COALESCE(td.price, 0.00),
    td.qr_payload = COALESCE(NULLIF(td.qr_payload, ''), CONCAT('ticket:', COALESCE(NULLIF(td.ticket_code, ''), CONCAT('TIC-', LPAD(td.id, 8, '0')))));

ALTER TABLE ticket_details
    MODIFY COLUMN order_id INT NOT NULL,
    MODIFY COLUMN showtime_id INT NOT NULL,
    MODIFY COLUMN seat_id INT NOT NULL,
    MODIFY COLUMN ticket_code VARCHAR(40) NOT NULL,
    MODIFY COLUMN status ENUM('pending','paid','cancelled','expired','refunded','used') DEFAULT 'pending',
    MODIFY COLUMN base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN surcharge_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND INDEX_NAME = 'uniq_ticket_details_ticket_code') = 0,
    'ALTER TABLE ticket_details ADD UNIQUE KEY uniq_ticket_details_ticket_code (ticket_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND INDEX_NAME = 'idx_ticket_details_order_status') = 0,
    'ALTER TABLE ticket_details ADD INDEX idx_ticket_details_order_status (order_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'ticket_details' AND INDEX_NAME = 'idx_ticket_details_showtime_seat_status') = 0,
    'ALTER TABLE ticket_details ADD INDEX idx_ticket_details_showtime_seat_status (showtime_id, seat_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ticket_seat_holds: temporary holds before order confirmation.
CREATE TABLE IF NOT EXISTS ticket_seat_holds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    showtime_id INT NOT NULL,
    seat_id INT NOT NULL,
    user_id INT NULL,
    session_token VARCHAR(100) NOT NULL,
    hold_expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ticket_seat_holds_showtime_seat (showtime_id, seat_id),
    INDEX idx_ticket_seat_holds_session_expires (session_token, hold_expires_at),
    INDEX idx_ticket_seat_holds_expires (hold_expires_at),
    FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
    FOREIGN KEY (seat_id) REFERENCES seats(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

ALTER TABLE ticket_seat_holds
    MODIFY COLUMN hold_expires_at DATETIME NOT NULL;

COMMIT;
