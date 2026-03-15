SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    channel_type ENUM('e_wallet','gateway','international','counter') NOT NULL,
    status ENUM('active','maintenance','disabled') DEFAULT 'active',
    fee_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    fixed_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    settlement_cycle VARCHAR(20) NOT NULL DEFAULT 'instant',
    supports_refund TINYINT(1) NOT NULL DEFAULT 0,
    supports_webhook TINYINT(1) NOT NULL DEFAULT 0,
    supports_redirect TINYINT(1) NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_methods_status_display (status, display_order)
);

INSERT INTO payment_methods (
    code,
    name,
    provider,
    channel_type,
    status,
    fee_rate_percent,
    fixed_fee_amount,
    settlement_cycle,
    supports_refund,
    supports_webhook,
    supports_redirect,
    display_order,
    description
)
VALUES
    ('momo', 'MoMo Wallet', 'momo', 'e_wallet', 'active', 2.40, 0.00, 'T+1', 1, 1, 1, 1, 'Domestic e-wallet checkout for ticket and shop orders.'),
    ('vnpay', 'VNPay', 'vnpay', 'gateway', 'active', 2.10, 0.00, 'T+1', 1, 1, 1, 2, 'Online redirect gateway for VNPay card and banking flows.'),
    ('paypal', 'PayPal', 'paypal', 'international', 'maintenance', 3.90, 0.00, 'T+2', 1, 1, 1, 3, 'International checkout channel for selected cross-border payments.'),
    ('cash', 'Cash At Counter', 'internal', 'counter', 'active', 0.00, 0.00, 'instant', 1, 0, 0, 4, 'Offline counter settlement for pickup or walk-in orders.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    provider = VALUES(provider),
    channel_type = VALUES(channel_type),
    status = VALUES(status),
    fee_rate_percent = VALUES(fee_rate_percent),
    fixed_fee_amount = VALUES(fixed_fee_amount),
    settlement_cycle = VALUES(settlement_cycle),
    supports_refund = VALUES(supports_refund),
    supports_webhook = VALUES(supports_webhook),
    supports_redirect = VALUES(supports_redirect),
    display_order = VALUES(display_order),
    description = VALUES(description);

ALTER TABLE payments
    MODIFY COLUMN payment_method ENUM('momo','vnpay','paypal','cash') NOT NULL,
    MODIFY COLUMN payment_status ENUM('pending','processing','success','failed','cancelled','expired','refunded') NOT NULL DEFAULT 'pending';

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'amount') = 0,
    'ALTER TABLE payments ADD COLUMN amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER payment_status',
    'SELECT 1'
);
PREPARE s1 FROM @stmt; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'currency') = 0,
    'ALTER TABLE payments ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''VND'' AFTER amount',
    'SELECT 1'
);
PREPARE s2 FROM @stmt; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'provider_transaction_code') = 0,
    'ALTER TABLE payments ADD COLUMN provider_transaction_code VARCHAR(255) NULL AFTER transaction_code',
    'SELECT 1'
);
PREPARE s3 FROM @stmt; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'provider_order_ref') = 0,
    'ALTER TABLE payments ADD COLUMN provider_order_ref VARCHAR(255) NULL AFTER provider_transaction_code',
    'SELECT 1'
);
PREPARE s4 FROM @stmt; EXECUTE s4; DEALLOCATE PREPARE s4;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'provider_response_code') = 0,
    'ALTER TABLE payments ADD COLUMN provider_response_code VARCHAR(50) NULL AFTER provider_order_ref',
    'SELECT 1'
);
PREPARE s5 FROM @stmt; EXECUTE s5; DEALLOCATE PREPARE s5;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'provider_message') = 0,
    'ALTER TABLE payments ADD COLUMN provider_message VARCHAR(255) NULL AFTER provider_response_code',
    'SELECT 1'
);
PREPARE s6 FROM @stmt; EXECUTE s6; DEALLOCATE PREPARE s6;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'idempotency_key') = 0,
    'ALTER TABLE payments ADD COLUMN idempotency_key VARCHAR(80) NULL AFTER provider_message',
    'SELECT 1'
);
PREPARE s7 FROM @stmt; EXECUTE s7; DEALLOCATE PREPARE s7;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'checkout_url') = 0,
    'ALTER TABLE payments ADD COLUMN checkout_url VARCHAR(500) NULL AFTER idempotency_key',
    'SELECT 1'
);
PREPARE s8 FROM @stmt; EXECUTE s8; DEALLOCATE PREPARE s8;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'request_payload') = 0,
    'ALTER TABLE payments ADD COLUMN request_payload LONGTEXT NULL AFTER checkout_url',
    'SELECT 1'
);
PREPARE s9 FROM @stmt; EXECUTE s9; DEALLOCATE PREPARE s9;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'callback_payload') = 0,
    'ALTER TABLE payments ADD COLUMN callback_payload LONGTEXT NULL AFTER request_payload',
    'SELECT 1'
);
PREPARE s10 FROM @stmt; EXECUTE s10; DEALLOCATE PREPARE s10;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'initiated_at') = 0,
    'ALTER TABLE payments ADD COLUMN initiated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER callback_payload',
    'SELECT 1'
);
PREPARE s11 FROM @stmt; EXECUTE s11; DEALLOCATE PREPARE s11;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'completed_at') = 0,
    'ALTER TABLE payments ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER initiated_at',
    'SELECT 1'
);
PREPARE s12 FROM @stmt; EXECUTE s12; DEALLOCATE PREPARE s12;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'failed_at') = 0,
    'ALTER TABLE payments ADD COLUMN failed_at TIMESTAMP NULL DEFAULT NULL AFTER completed_at',
    'SELECT 1'
);
PREPARE s13 FROM @stmt; EXECUTE s13; DEALLOCATE PREPARE s13;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'refunded_at') = 0,
    'ALTER TABLE payments ADD COLUMN refunded_at TIMESTAMP NULL DEFAULT NULL AFTER failed_at',
    'SELECT 1'
);
PREPARE s14 FROM @stmt; EXECUTE s14; DEALLOCATE PREPARE s14;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE payments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER payment_date',
    'SELECT 1'
);
PREPARE s15 FROM @stmt; EXECUTE s15; DEALLOCATE PREPARE s15;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE payments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE s16 FROM @stmt; EXECUTE s16; DEALLOCATE PREPARE s16;

UPDATE payments
SET amount = 0.00
WHERE amount IS NULL;

UPDATE payments
SET currency = 'VND'
WHERE currency IS NULL OR currency = '';

UPDATE payments p
INNER JOIN ticket_orders o ON o.id = p.ticket_order_id
SET
    p.amount = CASE WHEN p.amount IS NULL OR p.amount = 0 THEN o.total_price ELSE p.amount END,
    p.currency = CASE WHEN p.currency IS NULL OR p.currency = '' THEN o.currency ELSE p.currency END,
    p.provider_order_ref = CASE
        WHEN p.provider_order_ref IS NULL OR p.provider_order_ref = '' THEN o.order_code
        ELSE p.provider_order_ref
    END,
    p.initiated_at = COALESCE(p.initiated_at, o.order_date),
    p.completed_at = CASE
        WHEN p.payment_status = 'success' AND p.completed_at IS NULL THEN COALESCE(o.paid_at, p.payment_date)
        ELSE p.completed_at
    END,
    p.failed_at = CASE
        WHEN p.payment_status = 'failed' AND p.failed_at IS NULL THEN p.payment_date
        ELSE p.failed_at
    END,
    p.refunded_at = CASE
        WHEN p.payment_status = 'refunded' AND p.refunded_at IS NULL THEN p.payment_date
        ELSE p.refunded_at
    END;

UPDATE payments p
INNER JOIN shop_orders o ON o.id = p.shop_order_id
SET
    p.amount = CASE WHEN p.amount IS NULL OR p.amount = 0 THEN o.total_price ELSE p.amount END,
    p.provider_order_ref = CASE
        WHEN p.provider_order_ref IS NULL OR p.provider_order_ref = '' THEN CONCAT('SHOP-', LPAD(o.id, 6, '0'))
        ELSE p.provider_order_ref
    END,
    p.initiated_at = COALESCE(p.initiated_at, o.order_date)
WHERE p.shop_order_id IS NOT NULL;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND INDEX_NAME = 'uniq_payments_transaction_code') = 0,
    'ALTER TABLE payments ADD UNIQUE KEY uniq_payments_transaction_code (transaction_code)',
    'SELECT 1'
);
PREPARE s17 FROM @stmt; EXECUTE s17; DEALLOCATE PREPARE s17;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND INDEX_NAME = 'uniq_payments_idempotency_key') = 0,
    'ALTER TABLE payments ADD UNIQUE KEY uniq_payments_idempotency_key (idempotency_key)',
    'SELECT 1'
);
PREPARE s18 FROM @stmt; EXECUTE s18; DEALLOCATE PREPARE s18;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_ticket_order') = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_ticket_order (ticket_order_id)',
    'SELECT 1'
);
PREPARE s19 FROM @stmt; EXECUTE s19; DEALLOCATE PREPARE s19;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_shop_order') = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_shop_order (shop_order_id)',
    'SELECT 1'
);
PREPARE s20 FROM @stmt; EXECUTE s20; DEALLOCATE PREPARE s20;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_method_status_date') = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_method_status_date (payment_method, payment_status, payment_date)',
    'SELECT 1'
);
PREPARE s21 FROM @stmt; EXECUTE s21; DEALLOCATE PREPARE s21;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_status_initiated') = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_status_initiated (payment_status, initiated_at)',
    'SELECT 1'
);
PREPARE s22 FROM @stmt; EXECUTE s22; DEALLOCATE PREPARE s22;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND CONSTRAINT_NAME = 'fk_payments_ticket_order') = 0,
    'ALTER TABLE payments ADD CONSTRAINT fk_payments_ticket_order FOREIGN KEY (ticket_order_id) REFERENCES ticket_orders(id)',
    'SELECT 1'
);
PREPARE s23 FROM @stmt; EXECUTE s23; DEALLOCATE PREPARE s23;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND CONSTRAINT_NAME = 'fk_payments_shop_order') = 0,
    'ALTER TABLE payments ADD CONSTRAINT fk_payments_shop_order FOREIGN KEY (shop_order_id) REFERENCES shop_orders(id)',
    'SELECT 1'
);
PREPARE s24 FROM @stmt; EXECUTE s24; DEALLOCATE PREPARE s24;
