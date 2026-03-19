CREATE DATABASE IF NOT EXISTS movie_shop
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE movie_shop;

-- ========================
-- USERS
-- ========================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(150) UNIQUE,
    password VARCHAR(255),
    phone VARCHAR(20),
    role ENUM('admin','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    address TEXT,
    city VARCHAR(100),
    district VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50)
);

-- ========================
    -- MOVIE SYSTEM
-- ========================

CREATE TABLE movie_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    primary_category_id INT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    summary TEXT,
    duration_minutes INT NOT NULL,
    release_date DATE,
    poster_url VARCHAR(255),
    trailer_url VARCHAR(255),
    age_rating VARCHAR(20),
    language VARCHAR(100),
    director VARCHAR(255),
    writer VARCHAR(255),
    cast_text TEXT,
    studio VARCHAR(255),
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    review_count INT DEFAULT 0,
    status ENUM('draft','coming_soon','now_showing','ended','archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_movies_status_release (status, release_date),
    INDEX idx_movies_primary_category (primary_category_id),
    FOREIGN KEY (primary_category_id) REFERENCES movie_categories(id)
);

CREATE TABLE movie_category_assignments (
    movie_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (movie_id, category_id),
    INDEX idx_movie_category_assignments_category (category_id),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES movie_categories(id) ON DELETE CASCADE
);

CREATE TABLE movie_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    asset_type ENUM('poster','banner','gallery') DEFAULT 'gallery',
    image_url VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_primary TINYINT(1) DEFAULT 0,
    status ENUM('draft','active','archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_movie_images_movie_status (movie_id, status, asset_type),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
);

CREATE TABLE cinemas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(150) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL,
    manager_name VARCHAR(120),
    support_phone VARCHAR(20),
    status ENUM('active','renovation','closed','archived') DEFAULT 'active',
    opening_time TIME NULL,
    closing_time TIME NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cinemas_city_status (city, status),
    INDEX idx_cinemas_status (status)
);

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cinema_id INT NOT NULL,
    room_name VARCHAR(120) NOT NULL,
    room_type VARCHAR(50),
    screen_label VARCHAR(120),
    projection_type VARCHAR(50),
    sound_profile VARCHAR(50),
    cleaning_buffer_minutes INT DEFAULT 15,
    total_seats INT DEFAULT 0,
    status ENUM('active','maintenance','closed','archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_rooms_cinema_room_name (cinema_id, room_name),
    INDEX idx_rooms_cinema_status (cinema_id, status),
    FOREIGN KEY (cinema_id) REFERENCES cinemas(id)
);

CREATE TABLE seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    seat_row VARCHAR(5) NOT NULL,
    seat_number INT NOT NULL,
    seat_type ENUM('normal','vip','couple') DEFAULT 'normal',
    status ENUM('available','maintenance','disabled','archived') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_seats_room_position (room_id, seat_row, seat_number),
    INDEX idx_seats_room_status_type (room_id, status, seat_type),
    FOREIGN KEY (room_id) REFERENCES rooms(id)
);

CREATE TABLE showtimes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    room_id INT NOT NULL,
    show_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('draft','published','cancelled','completed','archived') DEFAULT 'draft',
    presentation_type VARCHAR(50),
    language_version VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_showtimes_movie_date (movie_id, show_date),
    INDEX idx_showtimes_room_date_status (room_id, show_date, status),
    FOREIGN KEY (movie_id) REFERENCES movies(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id)
);

CREATE TABLE ticket_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(32) NOT NULL UNIQUE,
    user_id INT NULL,
    session_token VARCHAR(100) NULL,
    contact_name VARCHAR(120) NULL,
    contact_email VARCHAR(150) NULL,
    contact_phone VARCHAR(20) NULL,
    fulfillment_method ENUM('e_ticket','counter_pickup') DEFAULT 'e_ticket',
    seat_count INT NOT NULL DEFAULT 0,
    subtotal_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'VND',
    status ENUM('pending','paid','cancelled','expired','refunded') DEFAULT 'pending',
    hold_expires_at TIMESTAMP NULL DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ticket_orders_user_status (user_id, status),
    INDEX idx_ticket_orders_status_order_date (status, order_date),
    INDEX idx_ticket_orders_hold_expires (hold_expires_at),
    INDEX idx_ticket_orders_session_status_hold (session_token, status, hold_expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE ticket_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    showtime_id INT NOT NULL,
    seat_id INT NOT NULL,
    ticket_code VARCHAR(40) NOT NULL UNIQUE,
    status ENUM('pending','paid','cancelled','expired','refunded','used') DEFAULT 'pending',
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    surcharge_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    qr_payload VARCHAR(255),
    scanned_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ticket_details_order_status (order_id, status),
    INDEX idx_ticket_details_showtime_seat_status (showtime_id, seat_id, status),
    FOREIGN KEY (order_id) REFERENCES ticket_orders(id),
    FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
    FOREIGN KEY (seat_id) REFERENCES seats(id)
);

CREATE TABLE ticket_seat_holds (
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

CREATE TABLE movie_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    is_visible TINYINT(1) DEFAULT 0,
    moderation_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_movie_reviews_movie_status (movie_id, status, is_visible),
    INDEX idx_movie_reviews_user (user_id),
    FOREIGN KEY (movie_id) REFERENCES movies(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ========================
-- SHOP SYSTEM
-- ========================

CREATE TABLE product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT,
    display_order INT NOT NULL DEFAULT 0,
    visibility ENUM('featured','standard','hidden') NOT NULL DEFAULT 'standard',
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_categories_status_display (status, visibility, display_order)
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    sku VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    short_description VARCHAR(255) NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    compare_at_price DECIMAL(10,2) NULL,
    stock INT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'VND',
    track_inventory TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('draft','active','inactive','archived') NOT NULL DEFAULT 'draft',
    visibility ENUM('featured','standard','hidden') NOT NULL DEFAULT 'standard',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_category_status (category_id, status, visibility, sort_order),
    INDEX idx_products_status_updated (status, updated_at),
    FOREIGN KEY (category_id) REFERENCES product_categories(id)
);

CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    asset_type ENUM('thumbnail','gallery','banner','lifestyle') NOT NULL DEFAULT 'gallery',
    image_url VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_images_product_status (product_id, status, asset_type, sort_order),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE product_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    brand VARCHAR(100) NULL,
    weight VARCHAR(50) NULL,
    origin VARCHAR(100) NULL,
    description TEXT,
    attributes_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_details_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    session_token VARCHAR(64) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'VND',
    status ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active',
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_carts_user_status (user_id, status),
    INDEX idx_carts_session_status (session_token, status),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cart_items_cart_product (cart_id, product_id),
    INDEX idx_cart_items_product (product_id),
    FOREIGN KEY (cart_id) REFERENCES carts(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE shop_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(32) NOT NULL UNIQUE,
    user_id INT NULL,
    session_token VARCHAR(64) NULL,
    address_id INT NULL,
    contact_name VARCHAR(120) NULL,
    contact_email VARCHAR(150) NULL,
    contact_phone VARCHAR(20) NULL,
    fulfillment_method ENUM('pickup','delivery') NOT NULL DEFAULT 'delivery',
    shipping_address_text TEXT NULL,
    shipping_city VARCHAR(100) NULL,
    shipping_district VARCHAR(100) NULL,
    item_count INT NOT NULL DEFAULT 0,
    subtotal_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'VND',
    status ENUM('pending','confirmed','preparing','ready','shipping','completed','cancelled','expired','refunded') NOT NULL DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_due_at TIMESTAMP NULL DEFAULT NULL,
    confirmed_at TIMESTAMP NULL DEFAULT NULL,
    fulfilled_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shop_orders_user_status (user_id, status),
    INDEX idx_shop_orders_session_status (session_token, status),
    INDEX idx_shop_orders_status_order_date (status, order_date),
    INDEX idx_shop_orders_payment_due (payment_due_at),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (address_id) REFERENCES addresses(id)
);

CREATE TABLE order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name_snapshot VARCHAR(255) NOT NULL,
    product_sku_snapshot VARCHAR(64) NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_details_order (order_id),
    INDEX idx_order_details_product (product_id),
    FOREIGN KEY (order_id) REFERENCES shop_orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ========================
-- PAYMENT
-- ========================

CREATE TABLE payment_methods (
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

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_order_id INT NULL,
    shop_order_id INT NULL,
    payment_method VARCHAR(30) NOT NULL,
    payment_status ENUM('pending','processing','success','failed','cancelled','expired','refunded') NOT NULL DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'VND',
    transaction_code VARCHAR(255) NULL,
    provider_transaction_code VARCHAR(255) NULL,
    provider_order_ref VARCHAR(255) NULL,
    provider_response_code VARCHAR(50) NULL,
    provider_message VARCHAR(255) NULL,
    idempotency_key VARCHAR(80) NULL,
    checkout_url TEXT NULL,
    request_payload LONGTEXT NULL,
    callback_payload LONGTEXT NULL,
    initiated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    failed_at TIMESTAMP NULL DEFAULT NULL,
    refunded_at TIMESTAMP NULL DEFAULT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_payments_transaction_code (transaction_code),
    UNIQUE KEY uniq_payments_idempotency_key (idempotency_key),
    INDEX idx_payments_ticket_order (ticket_order_id),
    INDEX idx_payments_shop_order (shop_order_id),
    INDEX idx_payments_method_status_date (payment_method, payment_status, payment_date),
    INDEX idx_payments_status_initiated (payment_status, initiated_at),
    INDEX idx_payments_provider_order_ref (provider_order_ref),
    INDEX idx_payments_provider_transaction_code (provider_transaction_code),
    FOREIGN KEY (ticket_order_id) REFERENCES ticket_orders(id),
    FOREIGN KEY (shop_order_id) REFERENCES shop_orders(id)
);

-- ========================
-- EXTRA SYSTEM TABLES
-- ========================

CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    image VARCHAR(255),
    description TEXT
);

CREATE TABLE promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_discount_amount DECIMAL(10,2) NULL,
    min_order_amount DECIMAL(10,2) NULL,
    start_at TIMESTAMP NULL DEFAULT NULL,
    end_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('draft','scheduled','active','expired','archived') NOT NULL DEFAULT 'draft',
    usage_limit INT NULL,
    usage_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_promotions_status_window (status, start_at, end_at)
);

CREATE TABLE product_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    promotion_id INT NOT NULL,
    priority TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_promotions_pair (product_id, promotion_id),
    INDEX idx_product_promotions_promotion_status (promotion_id, status),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (promotion_id) REFERENCES promotions(id)
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ========================
-- DEFAULT ADMIN
-- ========================

INSERT INTO users (name, email, password, phone, role)
VALUES ('System Admin', 'admin', '$2y$10$3vfhhPHMopDOxtjV4PDAp.0j2Fu3waq.ylZugcb4p7t7w7bry9qOu', '0000000000', 'admin')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password = VALUES(password),
    phone = VALUES(phone),
    role = VALUES(role);

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
