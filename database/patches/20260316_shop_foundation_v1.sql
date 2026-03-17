USE movie_shop;

START TRANSACTION;

SET @schema_name := DATABASE();

-- ========================
-- product_categories foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'slug') = 0,
    'ALTER TABLE product_categories ADD COLUMN slug VARCHAR(140) NULL AFTER name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'display_order') = 0,
    'ALTER TABLE product_categories ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER description',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'visibility') = 0,
    'ALTER TABLE product_categories ADD COLUMN visibility ENUM(''featured'',''standard'',''hidden'') NOT NULL DEFAULT ''standard'' AFTER display_order',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE product_categories ADD COLUMN status ENUM(''active'',''inactive'',''archived'') NOT NULL DEFAULT ''active'' AFTER visibility',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE product_categories ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_categories' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE product_categories ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE product_categories
SET
    name = COALESCE(NULLIF(name, ''), CONCAT('Category ', id)),
    slug = COALESCE(NULLIF(slug, ''), CONCAT('category-', id)),
    display_order = COALESCE(display_order, id),
    visibility = COALESCE(NULLIF(visibility, ''), 'standard'),
    status = COALESCE(NULLIF(status, ''), 'active');

ALTER TABLE product_categories
    MODIFY COLUMN name VARCHAR(100) NOT NULL,
    MODIFY COLUMN slug VARCHAR(140) NOT NULL,
    MODIFY COLUMN display_order INT NOT NULL DEFAULT 0,
    MODIFY COLUMN visibility ENUM('featured','standard','hidden') NOT NULL DEFAULT 'standard',
    MODIFY COLUMN status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active';

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_categories' AND INDEX_NAME = 'uniq_product_categories_slug') = 0,
    'ALTER TABLE product_categories ADD UNIQUE KEY uniq_product_categories_slug (slug)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_categories' AND INDEX_NAME = 'idx_product_categories_status_display') = 0,
    'ALTER TABLE product_categories ADD INDEX idx_product_categories_status_display (status, visibility, display_order)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- products foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'slug') = 0,
    'ALTER TABLE products ADD COLUMN slug VARCHAR(180) NULL AFTER category_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'sku') = 0,
    'ALTER TABLE products ADD COLUMN sku VARCHAR(64) NULL AFTER slug',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'short_description') = 0,
    'ALTER TABLE products ADD COLUMN short_description VARCHAR(255) NULL AFTER name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'compare_at_price') = 0,
    'ALTER TABLE products ADD COLUMN compare_at_price DECIMAL(10,2) NULL AFTER price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'currency') = 0,
    'ALTER TABLE products ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''VND'' AFTER stock',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'track_inventory') = 0,
    'ALTER TABLE products ADD COLUMN track_inventory TINYINT(1) NOT NULL DEFAULT 1 AFTER currency',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE products ADD COLUMN status ENUM(''draft'',''active'',''inactive'',''archived'') NOT NULL DEFAULT ''draft'' AFTER track_inventory',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'visibility') = 0,
    'ALTER TABLE products ADD COLUMN visibility ENUM(''featured'',''standard'',''hidden'') NOT NULL DEFAULT ''standard'' AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'sort_order') = 0,
    'ALTER TABLE products ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER visibility',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE products
SET
    name = COALESCE(NULLIF(name, ''), CONCAT('Product ', id)),
    slug = COALESCE(NULLIF(slug, ''), CONCAT('product-', id)),
    sku = COALESCE(NULLIF(sku, ''), CONCAT('SKU-', LPAD(id, 6, '0'))),
    price = COALESCE(price, 0.00),
    stock = COALESCE(stock, 0),
    currency = COALESCE(NULLIF(currency, ''), 'VND'),
    track_inventory = COALESCE(track_inventory, 1),
    status = COALESCE(NULLIF(status, ''), 'active'),
    visibility = COALESCE(NULLIF(visibility, ''), 'standard'),
    sort_order = COALESCE(sort_order, id);

ALTER TABLE products
    MODIFY COLUMN name VARCHAR(255) NOT NULL,
    MODIFY COLUMN slug VARCHAR(180) NOT NULL,
    MODIFY COLUMN sku VARCHAR(64) NOT NULL,
    MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN stock INT NOT NULL DEFAULT 0,
    MODIFY COLUMN currency CHAR(3) NOT NULL DEFAULT 'VND',
    MODIFY COLUMN track_inventory TINYINT(1) NOT NULL DEFAULT 1,
    MODIFY COLUMN status ENUM('draft','active','inactive','archived') NOT NULL DEFAULT 'draft',
    MODIFY COLUMN visibility ENUM('featured','standard','hidden') NOT NULL DEFAULT 'standard',
    MODIFY COLUMN sort_order INT NOT NULL DEFAULT 0;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND INDEX_NAME = 'uniq_products_slug') = 0,
    'ALTER TABLE products ADD UNIQUE KEY uniq_products_slug (slug)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND INDEX_NAME = 'uniq_products_sku') = 0,
    'ALTER TABLE products ADD UNIQUE KEY uniq_products_sku (sku)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_category_status') = 0,
    'ALTER TABLE products ADD INDEX idx_products_category_status (category_id, status, visibility, sort_order)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_status_updated') = 0,
    'ALTER TABLE products ADD INDEX idx_products_status_updated (status, updated_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- product_images foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'asset_type') = 0,
    'ALTER TABLE product_images ADD COLUMN asset_type ENUM(''thumbnail'',''gallery'',''banner'',''lifestyle'') NOT NULL DEFAULT ''gallery'' AFTER product_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'image_url') = 0,
    'ALTER TABLE product_images ADD COLUMN image_url VARCHAR(255) NULL AFTER asset_type',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'alt_text') = 0,
    'ALTER TABLE product_images ADD COLUMN alt_text VARCHAR(255) NULL AFTER image_url',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'sort_order') = 0,
    'ALTER TABLE product_images ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER alt_text',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'is_primary') = 0,
    'ALTER TABLE product_images ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE product_images ADD COLUMN status ENUM(''draft'',''active'',''archived'') NOT NULL DEFAULT ''active'' AFTER is_primary',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE product_images ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE product_images ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @legacy_product_image_expr := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND COLUMN_NAME = 'image') = 0,
    'NULL',
    'NULLIF(image, '''')'
);

SET @sql := CONCAT(
    'UPDATE product_images SET ',
    'image_url = COALESCE(NULLIF(image_url, ''''), ', @legacy_product_image_expr, ', CONCAT(''product-image-'', id)), ',
    'asset_type = COALESCE(NULLIF(asset_type, ''''), ''gallery''), ',
    'sort_order = COALESCE(sort_order, id), ',
    'is_primary = COALESCE(is_primary, 0), ',
    'status = COALESCE(NULLIF(status, ''''), ''active'')'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE product_images
    MODIFY COLUMN image_url VARCHAR(255) NOT NULL,
    MODIFY COLUMN asset_type ENUM('thumbnail','gallery','banner','lifestyle') NOT NULL DEFAULT 'gallery',
    MODIFY COLUMN sort_order INT NOT NULL DEFAULT 0,
    MODIFY COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0,
    MODIFY COLUMN status ENUM('draft','active','archived') NOT NULL DEFAULT 'active';

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_images' AND INDEX_NAME = 'idx_product_images_product_status') = 0,
    'ALTER TABLE product_images ADD INDEX idx_product_images_product_status (product_id, status, asset_type, sort_order)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- product_details foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_details' AND COLUMN_NAME = 'attributes_json') = 0,
    'ALTER TABLE product_details ADD COLUMN attributes_json LONGTEXT NULL AFTER description',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_details' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE product_details ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER attributes_json',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_details' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE product_details ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_details' AND INDEX_NAME = 'uniq_product_details_product') = 0
    AND (
        SELECT COUNT(*) FROM (
            SELECT product_id
            FROM product_details
            GROUP BY product_id
            HAVING COUNT(*) > 1
        ) duplicated_details
    ) = 0,
    'ALTER TABLE product_details ADD UNIQUE KEY uniq_product_details_product (product_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- carts foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'carts' AND COLUMN_NAME = 'session_token') = 0,
    'ALTER TABLE carts ADD COLUMN session_token VARCHAR(64) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'carts' AND COLUMN_NAME = 'currency') = 0,
    'ALTER TABLE carts ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''VND'' AFTER session_token',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'carts' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE carts ADD COLUMN status ENUM(''active'',''converted'',''abandoned'') NOT NULL DEFAULT ''active'' AFTER currency',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'carts' AND COLUMN_NAME = 'expires_at') = 0,
    'ALTER TABLE carts ADD COLUMN expires_at TIMESTAMP NULL DEFAULT NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'carts' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE carts ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE carts
SET
    currency = COALESCE(NULLIF(currency, ''), 'VND'),
    status = COALESCE(NULLIF(status, ''), 'active'),
    expires_at = CASE
        WHEN status = 'active' THEN COALESCE(expires_at, DATE_ADD(created_at, INTERVAL 7 DAY))
        ELSE expires_at
    END;

ALTER TABLE carts
    MODIFY COLUMN currency CHAR(3) NOT NULL DEFAULT 'VND',
    MODIFY COLUMN status ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active';

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'carts' AND INDEX_NAME = 'idx_carts_user_status') = 0,
    'ALTER TABLE carts ADD INDEX idx_carts_user_status (user_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'carts' AND INDEX_NAME = 'idx_carts_session_status') = 0,
    'ALTER TABLE carts ADD INDEX idx_carts_session_status (session_token, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- cart_items foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'cart_items' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE cart_items ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'cart_items' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE cart_items ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE cart_items
SET
    quantity = CASE WHEN quantity IS NULL OR quantity < 1 THEN 1 ELSE quantity END,
    price = COALESCE(price, 0.00);

ALTER TABLE cart_items
    MODIFY COLUMN quantity INT NOT NULL DEFAULT 1,
    MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'cart_items' AND INDEX_NAME = 'uniq_cart_items_cart_product') = 0
    AND (
        SELECT COUNT(*) FROM (
            SELECT cart_id, product_id
            FROM cart_items
            GROUP BY cart_id, product_id
            HAVING COUNT(*) > 1
        ) duplicated_cart_items
    ) = 0,
    'ALTER TABLE cart_items ADD UNIQUE KEY uniq_cart_items_cart_product (cart_id, product_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'cart_items' AND INDEX_NAME = 'idx_cart_items_product') = 0,
    'ALTER TABLE cart_items ADD INDEX idx_cart_items_product (product_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- shop_orders foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'order_code') = 0,
    'ALTER TABLE shop_orders ADD COLUMN order_code VARCHAR(32) NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'session_token') = 0,
    'ALTER TABLE shop_orders ADD COLUMN session_token VARCHAR(64) NULL AFTER user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'contact_name') = 0,
    'ALTER TABLE shop_orders ADD COLUMN contact_name VARCHAR(120) NULL AFTER address_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'contact_email') = 0,
    'ALTER TABLE shop_orders ADD COLUMN contact_email VARCHAR(150) NULL AFTER contact_name',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'contact_phone') = 0,
    'ALTER TABLE shop_orders ADD COLUMN contact_phone VARCHAR(20) NULL AFTER contact_email',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'fulfillment_method') = 0,
    'ALTER TABLE shop_orders ADD COLUMN fulfillment_method ENUM(''pickup'',''delivery'') NOT NULL DEFAULT ''delivery'' AFTER contact_phone',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'shipping_address_text') = 0,
    'ALTER TABLE shop_orders ADD COLUMN shipping_address_text TEXT NULL AFTER fulfillment_method',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'shipping_city') = 0,
    'ALTER TABLE shop_orders ADD COLUMN shipping_city VARCHAR(100) NULL AFTER shipping_address_text',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'shipping_district') = 0,
    'ALTER TABLE shop_orders ADD COLUMN shipping_district VARCHAR(100) NULL AFTER shipping_city',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'item_count') = 0,
    'ALTER TABLE shop_orders ADD COLUMN item_count INT NOT NULL DEFAULT 0 AFTER shipping_district',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'subtotal_price') = 0,
    'ALTER TABLE shop_orders ADD COLUMN subtotal_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER item_count',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'discount_amount') = 0,
    'ALTER TABLE shop_orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal_price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'fee_amount') = 0,
    'ALTER TABLE shop_orders ADD COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'shipping_amount') = 0,
    'ALTER TABLE shop_orders ADD COLUMN shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER fee_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'currency') = 0,
    'ALTER TABLE shop_orders ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''VND'' AFTER total_price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE shop_orders
    MODIFY COLUMN total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN status ENUM('pending','confirmed','preparing','ready','shipping','completed','cancelled','expired','refunded') NOT NULL DEFAULT 'pending';

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'payment_due_at') = 0,
    'ALTER TABLE shop_orders ADD COLUMN payment_due_at TIMESTAMP NULL DEFAULT NULL AFTER order_date',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'confirmed_at') = 0,
    'ALTER TABLE shop_orders ADD COLUMN confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER payment_due_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'fulfilled_at') = 0,
    'ALTER TABLE shop_orders ADD COLUMN fulfilled_at TIMESTAMP NULL DEFAULT NULL AFTER confirmed_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'cancelled_at') = 0,
    'ALTER TABLE shop_orders ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER fulfilled_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE shop_orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER cancelled_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE shop_orders o
LEFT JOIN users u ON u.id = o.user_id
LEFT JOIN addresses a ON a.id = o.address_id
SET
    o.order_code = COALESCE(NULLIF(o.order_code, ''), CONCAT('SHOP-', LPAD(o.id, 6, '0'))),
    o.contact_name = COALESCE(NULLIF(o.contact_name, ''), NULLIF(u.name, ''), CONCAT('Guest ', o.id)),
    o.contact_email = COALESCE(NULLIF(o.contact_email, ''), NULLIF(u.email, '')),
    o.contact_phone = COALESCE(NULLIF(o.contact_phone, ''), NULLIF(u.phone, '')),
    o.fulfillment_method = COALESCE(NULLIF(o.fulfillment_method, ''), 'delivery'),
    o.shipping_address_text = COALESCE(NULLIF(o.shipping_address_text, ''), NULLIF(a.address, '')),
    o.shipping_city = COALESCE(NULLIF(o.shipping_city, ''), NULLIF(a.city, '')),
    o.shipping_district = COALESCE(NULLIF(o.shipping_district, ''), NULLIF(a.district, '')),
    o.item_count = COALESCE((
        SELECT SUM(COALESCE(od.quantity, 0))
        FROM order_details od
        WHERE od.order_id = o.id
    ), 0),
    o.subtotal_price = COALESCE((
        SELECT SUM(COALESCE(od.quantity, 0) * COALESCE(od.price, 0.00))
        FROM order_details od
        WHERE od.order_id = o.id
    ), COALESCE(o.total_price, 0.00)),
    o.discount_amount = COALESCE(o.discount_amount, 0.00),
    o.fee_amount = COALESCE(o.fee_amount, 0.00),
    o.shipping_amount = COALESCE(o.shipping_amount, 0.00),
    o.currency = COALESCE(NULLIF(o.currency, ''), 'VND'),
    o.payment_due_at = CASE
        WHEN o.status = 'pending' THEN COALESCE(o.payment_due_at, DATE_ADD(o.order_date, INTERVAL 15 MINUTE))
        ELSE o.payment_due_at
    END,
    o.confirmed_at = CASE
        WHEN o.status IN ('confirmed', 'preparing', 'ready', 'shipping', 'completed', 'refunded') THEN COALESCE(o.confirmed_at, o.order_date)
        ELSE o.confirmed_at
    END,
    o.fulfilled_at = CASE
        WHEN o.status = 'completed' THEN COALESCE(o.fulfilled_at, o.order_date)
        ELSE o.fulfilled_at
    END,
    o.cancelled_at = CASE
        WHEN o.status IN ('cancelled', 'expired') THEN COALESCE(o.cancelled_at, o.order_date)
        ELSE o.cancelled_at
    END;

ALTER TABLE shop_orders
    MODIFY COLUMN order_code VARCHAR(32) NOT NULL,
    MODIFY COLUMN item_count INT NOT NULL DEFAULT 0,
    MODIFY COLUMN subtotal_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN currency CHAR(3) NOT NULL DEFAULT 'VND',
    MODIFY COLUMN fulfillment_method ENUM('pickup','delivery') NOT NULL DEFAULT 'delivery';

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND INDEX_NAME = 'uniq_shop_orders_order_code') = 0,
    'ALTER TABLE shop_orders ADD UNIQUE KEY uniq_shop_orders_order_code (order_code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND INDEX_NAME = 'idx_shop_orders_user_status') = 0,
    'ALTER TABLE shop_orders ADD INDEX idx_shop_orders_user_status (user_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND INDEX_NAME = 'idx_shop_orders_session_status') = 0,
    'ALTER TABLE shop_orders ADD INDEX idx_shop_orders_session_status (session_token, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND INDEX_NAME = 'idx_shop_orders_status_order_date') = 0,
    'ALTER TABLE shop_orders ADD INDEX idx_shop_orders_status_order_date (status, order_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'shop_orders' AND INDEX_NAME = 'idx_shop_orders_payment_due') = 0,
    'ALTER TABLE shop_orders ADD INDEX idx_shop_orders_payment_due (payment_due_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- order_details foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'order_details' AND COLUMN_NAME = 'product_name_snapshot') = 0,
    'ALTER TABLE order_details ADD COLUMN product_name_snapshot VARCHAR(255) NULL AFTER product_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'order_details' AND COLUMN_NAME = 'product_sku_snapshot') = 0,
    'ALTER TABLE order_details ADD COLUMN product_sku_snapshot VARCHAR(64) NULL AFTER product_name_snapshot',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'order_details' AND COLUMN_NAME = 'discount_amount') = 0,
    'ALTER TABLE order_details ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'order_details' AND COLUMN_NAME = 'line_total') = 0,
    'ALTER TABLE order_details ADD COLUMN line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'order_details' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE order_details ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER line_total',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'order_details' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE order_details ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE order_details od
LEFT JOIN products p ON p.id = od.product_id
SET
    od.product_name_snapshot = COALESCE(NULLIF(od.product_name_snapshot, ''), NULLIF(p.name, ''), CONCAT('Product #', COALESCE(od.product_id, od.id))),
    od.product_sku_snapshot = COALESCE(NULLIF(od.product_sku_snapshot, ''), NULLIF(p.sku, ''), CASE
        WHEN od.product_id IS NULL THEN NULL
        ELSE CONCAT('SKU-', LPAD(od.product_id, 6, '0'))
    END),
    od.quantity = CASE WHEN od.quantity IS NULL OR od.quantity < 1 THEN 1 ELSE od.quantity END,
    od.price = COALESCE(od.price, 0.00),
    od.discount_amount = COALESCE(od.discount_amount, 0.00),
    od.line_total = GREATEST((CASE WHEN od.quantity IS NULL OR od.quantity < 1 THEN 1 ELSE od.quantity END) * COALESCE(od.price, 0.00) - COALESCE(od.discount_amount, 0.00), 0.00);

ALTER TABLE order_details
    MODIFY COLUMN product_name_snapshot VARCHAR(255) NOT NULL,
    MODIFY COLUMN quantity INT NOT NULL DEFAULT 1,
    MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'order_details' AND INDEX_NAME = 'idx_order_details_order') = 0,
    'ALTER TABLE order_details ADD INDEX idx_order_details_order (order_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'order_details' AND INDEX_NAME = 'idx_order_details_product') = 0,
    'ALTER TABLE order_details ADD INDEX idx_order_details_product (product_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- promotions foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'code') = 0,
    'ALTER TABLE promotions ADD COLUMN code VARCHAR(50) NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'description') = 0,
    'ALTER TABLE promotions ADD COLUMN description TEXT NULL AFTER title',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'discount_type') = 0,
    'ALTER TABLE promotions ADD COLUMN discount_type ENUM(''percent'',''fixed'') NOT NULL DEFAULT ''percent'' AFTER description',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'discount_value') = 0,
    'ALTER TABLE promotions ADD COLUMN discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_type',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'max_discount_amount') = 0,
    'ALTER TABLE promotions ADD COLUMN max_discount_amount DECIMAL(10,2) NULL AFTER discount_value',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'min_order_amount') = 0,
    'ALTER TABLE promotions ADD COLUMN min_order_amount DECIMAL(10,2) NULL AFTER max_discount_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'start_at') = 0,
    'ALTER TABLE promotions ADD COLUMN start_at TIMESTAMP NULL DEFAULT NULL AFTER min_order_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'end_at') = 0,
    'ALTER TABLE promotions ADD COLUMN end_at TIMESTAMP NULL DEFAULT NULL AFTER start_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE promotions ADD COLUMN status ENUM(''draft'',''scheduled'',''active'',''expired'',''archived'') NOT NULL DEFAULT ''draft'' AFTER end_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'usage_limit') = 0,
    'ALTER TABLE promotions ADD COLUMN usage_limit INT NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'usage_count') = 0,
    'ALTER TABLE promotions ADD COLUMN usage_count INT NOT NULL DEFAULT 0 AFTER usage_limit',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE promotions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER usage_count',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE promotions ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @legacy_discount_percent_expr := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'discount_percent') = 0,
    'NULL',
    'discount_percent'
);

SET @legacy_start_at_expr := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'start_date') = 0,
    'NULL',
    'CASE WHEN start_date IS NULL THEN NULL ELSE TIMESTAMP(start_date, ''00:00:00'') END'
);

SET @legacy_end_at_expr := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND COLUMN_NAME = 'end_date') = 0,
    'NULL',
    'CASE WHEN end_date IS NULL THEN NULL ELSE TIMESTAMP(end_date, ''23:59:59'') END'
);

SET @sql := CONCAT(
    'UPDATE promotions SET ',
    'title = COALESCE(NULLIF(title, ''''), CONCAT(''Promotion '', id)), ',
    'code = COALESCE(NULLIF(code, ''''), CONCAT(''PROMO-'', LPAD(id, 6, ''0''))), ',
    'discount_type = COALESCE(NULLIF(discount_type, ''''), ''percent''), ',
    'discount_value = COALESCE(discount_value, COALESCE(', @legacy_discount_percent_expr, ', 0)), ',
    'start_at = COALESCE(start_at, ', @legacy_start_at_expr, '), ',
    'end_at = COALESCE(end_at, ', @legacy_end_at_expr, '), ',
    'status = CASE ',
        'WHEN COALESCE(end_at, ', @legacy_end_at_expr, ') IS NOT NULL AND COALESCE(end_at, ', @legacy_end_at_expr, ') < CURRENT_TIMESTAMP THEN ''expired'' ',
        'WHEN COALESCE(start_at, ', @legacy_start_at_expr, ') IS NOT NULL AND COALESCE(start_at, ', @legacy_start_at_expr, ') > CURRENT_TIMESTAMP THEN ''scheduled'' ',
        'ELSE COALESCE(NULLIF(status, ''''), ''active'') ',
    'END, ',
    'usage_count = COALESCE(usage_count, 0)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE promotions
    MODIFY COLUMN title VARCHAR(255) NOT NULL,
    MODIFY COLUMN code VARCHAR(50) NOT NULL,
    MODIFY COLUMN discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    MODIFY COLUMN discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    MODIFY COLUMN status ENUM('draft','scheduled','active','expired','archived') NOT NULL DEFAULT 'draft',
    MODIFY COLUMN usage_count INT NOT NULL DEFAULT 0;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND INDEX_NAME = 'uniq_promotions_code') = 0,
    'ALTER TABLE promotions ADD UNIQUE KEY uniq_promotions_code (code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'promotions' AND INDEX_NAME = 'idx_promotions_status_window') = 0,
    'ALTER TABLE promotions ADD INDEX idx_promotions_status_window (status, start_at, end_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- product_promotions foundation
-- ========================

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_promotions' AND COLUMN_NAME = 'priority') = 0,
    'ALTER TABLE product_promotions ADD COLUMN priority TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER promotion_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_promotions' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE product_promotions ADD COLUMN status ENUM(''active'',''archived'') NOT NULL DEFAULT ''active'' AFTER priority',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_promotions' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE product_promotions ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_promotions' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE product_promotions ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE product_promotions
SET
    priority = COALESCE(priority, 1),
    status = COALESCE(NULLIF(status, ''), 'active');

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_promotions' AND INDEX_NAME = 'uniq_product_promotions_pair') = 0
    AND (
        SELECT COUNT(*) FROM (
            SELECT product_id, promotion_id
            FROM product_promotions
            GROUP BY product_id, promotion_id
            HAVING COUNT(*) > 1
        ) duplicated_assignments
    ) = 0,
    'ALTER TABLE product_promotions ADD UNIQUE KEY uniq_product_promotions_pair (product_id, promotion_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'product_promotions' AND INDEX_NAME = 'idx_product_promotions_promotion_status') = 0,
    'ALTER TABLE product_promotions ADD INDEX idx_product_promotions_promotion_status (promotion_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
