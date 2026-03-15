USE movie_shop;

START TRANSACTION;

ALTER TABLE cinemas
    ADD COLUMN slug VARCHAR(150) NULL AFTER id,
    ADD COLUMN city VARCHAR(120) NULL AFTER name,
    ADD COLUMN manager_name VARCHAR(120) NULL AFTER address,
    ADD COLUMN support_phone VARCHAR(20) NULL AFTER manager_name,
    ADD COLUMN status ENUM('active','renovation','closed','archived') NULL DEFAULT 'active' AFTER support_phone,
    ADD COLUMN opening_time TIME NULL AFTER status,
    ADD COLUMN closing_time TIME NULL AFTER opening_time,
    ADD COLUMN latitude DECIMAL(10,7) NULL AFTER closing_time,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD COLUMN description TEXT NULL AFTER longitude,
    ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER description,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE cinemas
SET slug = LOWER(TRIM(BOTH '-' FROM REPLACE(REPLACE(REPLACE(COALESCE(name, CONCAT('cinema-', id)), ' ', '-'), '.', ''), '''', '')))
WHERE slug IS NULL OR slug = '';

UPDATE cinemas
SET city = COALESCE(NULLIF(city, ''), 'Unknown'),
    address = COALESCE(NULLIF(address, ''), 'Pending address'),
    status = COALESCE(status, 'active');

ALTER TABLE cinemas
    MODIFY COLUMN slug VARCHAR(150) NOT NULL,
    MODIFY COLUMN name VARCHAR(255) NOT NULL,
    MODIFY COLUMN city VARCHAR(120) NOT NULL,
    MODIFY COLUMN address VARCHAR(255) NOT NULL,
    MODIFY COLUMN status ENUM('active','renovation','closed','archived') NOT NULL DEFAULT 'active',
    ADD UNIQUE KEY uniq_cinemas_slug (slug),
    ADD INDEX idx_cinemas_city_status (city, status),
    ADD INDEX idx_cinemas_status (status);

ALTER TABLE rooms
    MODIFY COLUMN cinema_id INT NOT NULL,
    MODIFY COLUMN room_name VARCHAR(120) NOT NULL,
    MODIFY COLUMN total_seats INT NOT NULL DEFAULT 0,
    ADD COLUMN room_type VARCHAR(50) NULL AFTER room_name,
    ADD COLUMN screen_label VARCHAR(120) NULL AFTER room_type,
    ADD COLUMN projection_type VARCHAR(50) NULL AFTER screen_label,
    ADD COLUMN sound_profile VARCHAR(50) NULL AFTER projection_type,
    ADD COLUMN cleaning_buffer_minutes INT NOT NULL DEFAULT 15 AFTER sound_profile,
    ADD COLUMN status ENUM('active','maintenance','closed','archived') NOT NULL DEFAULT 'active' AFTER total_seats,
    ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER status,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD UNIQUE KEY uniq_rooms_cinema_room_name (cinema_id, room_name),
    ADD INDEX idx_rooms_cinema_status (cinema_id, status);

ALTER TABLE seats
    MODIFY COLUMN room_id INT NOT NULL,
    MODIFY COLUMN seat_row VARCHAR(5) NOT NULL,
    MODIFY COLUMN seat_number INT NOT NULL,
    ADD COLUMN status ENUM('available','maintenance','disabled','archived') NOT NULL DEFAULT 'available' AFTER seat_type,
    ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER status,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD UNIQUE KEY uniq_seats_room_position (room_id, seat_row, seat_number),
    ADD INDEX idx_seats_room_status_type (room_id, status, seat_type);

ALTER TABLE showtimes
    MODIFY COLUMN movie_id INT NOT NULL,
    MODIFY COLUMN room_id INT NOT NULL,
    MODIFY COLUMN show_date DATE NOT NULL,
    MODIFY COLUMN start_time TIME NOT NULL,
    MODIFY COLUMN price DECIMAL(10,2) NOT NULL,
    ADD COLUMN end_time TIME NULL AFTER start_time,
    ADD COLUMN status ENUM('draft','published','cancelled','completed','archived') NULL DEFAULT 'published' AFTER price,
    ADD COLUMN presentation_type VARCHAR(50) NULL AFTER status,
    ADD COLUMN language_version VARCHAR(50) NULL AFTER presentation_type,
    ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER language_version,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE showtimes
SET status = COALESCE(status, 'published'),
    end_time = COALESCE(end_time, ADDTIME(start_time, '02:00:00'));

ALTER TABLE showtimes
    MODIFY COLUMN end_time TIME NOT NULL,
    MODIFY COLUMN status ENUM('draft','published','cancelled','completed','archived') NOT NULL DEFAULT 'draft',
    ADD INDEX idx_showtimes_movie_date (movie_id, show_date),
    ADD INDEX idx_showtimes_room_date_status (room_id, show_date, status);

COMMIT;
