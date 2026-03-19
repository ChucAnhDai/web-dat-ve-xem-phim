SET @schema_name = DATABASE();

ALTER TABLE payments
    MODIFY COLUMN payment_method VARCHAR(30) NOT NULL;

UPDATE payments
SET payment_method = LOWER(TRIM(payment_method))
WHERE payment_method IS NOT NULL
  AND payment_method <> LOWER(TRIM(payment_method));

UPDATE payments p
INNER JOIN ticket_orders o ON o.id = p.ticket_order_id
SET p.provider_order_ref = o.order_code
WHERE p.ticket_order_id IS NOT NULL
  AND (p.provider_order_ref IS NULL OR p.provider_order_ref = '');

UPDATE payments p
INNER JOIN shop_orders o ON o.id = p.shop_order_id
SET p.provider_order_ref = o.order_code
WHERE p.shop_order_id IS NOT NULL
  AND (
      p.provider_order_ref IS NULL
      OR p.provider_order_ref = ''
      OR p.provider_order_ref = CONCAT('SHOP-', LPAD(o.id, 6, '0'))
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
SELECT DISTINCT
    LOWER(TRIM(p.payment_method)) AS code,
    CASE LOWER(TRIM(p.payment_method))
        WHEN 'momo' THEN 'MoMo Wallet'
        WHEN 'vnpay' THEN 'VNPay'
        WHEN 'paypal' THEN 'PayPal'
        WHEN 'cash' THEN 'Cash At Counter'
        ELSE CONCAT(UPPER(LEFT(LOWER(TRIM(p.payment_method)), 1)), SUBSTRING(LOWER(TRIM(p.payment_method)), 2), ' Gateway')
    END AS name,
    LOWER(TRIM(p.payment_method)) AS provider,
    CASE LOWER(TRIM(p.payment_method))
        WHEN 'momo' THEN 'e_wallet'
        WHEN 'cash' THEN 'counter'
        WHEN 'paypal' THEN 'international'
        ELSE 'gateway'
    END AS channel_type,
    CASE LOWER(TRIM(p.payment_method))
        WHEN 'momo' THEN 'active'
        WHEN 'vnpay' THEN 'active'
        WHEN 'paypal' THEN 'maintenance'
        WHEN 'cash' THEN 'active'
        ELSE 'maintenance'
    END AS status,
    0.00 AS fee_rate_percent,
    0.00 AS fixed_fee_amount,
    CASE LOWER(TRIM(p.payment_method))
        WHEN 'cash' THEN 'instant'
        ELSE 'T+1'
    END AS settlement_cycle,
    CASE LOWER(TRIM(p.payment_method))
        WHEN 'momo' THEN 1
        WHEN 'vnpay' THEN 1
        WHEN 'paypal' THEN 1
        WHEN 'cash' THEN 1
        ELSE 0
    END AS supports_refund,
    CASE LOWER(TRIM(p.payment_method))
        WHEN 'cash' THEN 0
        ELSE 1
    END AS supports_webhook,
    CASE LOWER(TRIM(p.payment_method))
        WHEN 'cash' THEN 0
        ELSE 1
    END AS supports_redirect,
    999 AS display_order,
    'Auto-synced from legacy payments ledger.' AS description
FROM payments p
LEFT JOIN payment_methods pm ON pm.code = LOWER(TRIM(p.payment_method))
WHERE p.payment_method IS NOT NULL
  AND TRIM(p.payment_method) <> ''
  AND pm.id IS NULL
ON DUPLICATE KEY UPDATE
    updated_at = CURRENT_TIMESTAMP;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_provider_order_ref') = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_provider_order_ref (provider_order_ref)',
    'SELECT 1'
);
PREPARE s1 FROM @stmt; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_provider_transaction_code') = 0,
    'ALTER TABLE payments ADD INDEX idx_payments_provider_transaction_code (provider_transaction_code)',
    'SELECT 1'
);
PREPARE s2 FROM @stmt; EXECUTE s2; DEALLOCATE PREPARE s2;
