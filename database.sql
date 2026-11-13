CREATE DATABASE IF NOT EXISTS transitgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transitgo;
SET time_zone = '+07:00';

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('customer', 'provider', 'admin') NOT NULL DEFAULT 'customer',
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(24) NOT NULL DEFAULT '',
    photo VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    company_logo VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    legal_document VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    verification_status ENUM('incomplete', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'incomplete',
    verification_note VARCHAR(250) DEFAULT NULL,
    verification_round INT UNSIGNED NOT NULL DEFAULT 0,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (role, verification_status)
) ENGINE=InnoDB;

CREATE TABLE routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id INT UNSIGNED NOT NULL,
    origin VARCHAR(80) NOT NULL,
    destination VARCHAR(80) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL CHECK (duration_minutes BETWEEN 1 AND 2880),
    UNIQUE (provider_id, id),
    INDEX (origin, destination),
    FOREIGN KEY (provider_id) REFERENCES users(id),
    CHECK (origin <> destination)
) ENGINE=InnoDB;

CREATE TABLE vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id INT UNSIGNED NOT NULL,
    vehicle_name VARCHAR(80) NOT NULL,
    vehicle_type ENUM('Bus', 'Minibus', 'Shuttle') NOT NULL,
    plate_number VARCHAR(16) NOT NULL UNIQUE,
    seat_capacity TINYINT UNSIGNED NOT NULL CHECK (seat_capacity BETWEEN 1 AND 60),
    vehicle_photo VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    stnk_document VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    kir_document VARCHAR(36) CHARACTER SET ascii DEFAULT NULL,
    UNIQUE (provider_id, id),
    FOREIGN KEY (provider_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id INT UNSIGNED NOT NULL,
    route_id INT UNSIGNED NOT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    departure_at DATETIME NOT NULL,
    arrival_at DATETIME NOT NULL,
    price INT UNSIGNED NOT NULL CHECK (price BETWEEN 1000 AND 10000000),
    is_published TINYINT UNSIGNED NOT NULL DEFAULT 0 CHECK (is_published IN (0, 1)),
    INDEX (route_id, departure_at),
    INDEX (vehicle_id, departure_at),
    INDEX (departure_at),
    FOREIGN KEY (provider_id, route_id) REFERENCES routes(provider_id, id),
    FOREIGN KEY (provider_id, vehicle_id) REFERENCES vehicles(provider_id, id),
    CHECK (arrival_at > departure_at)
) ENGINE=InnoDB;

CREATE TABLE bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'paid', 'expired') NOT NULL DEFAULT 'pending',
    total INT UNSIGNED NOT NULL CHECK (total BETWEEN 1000 AND 60000000),
    seat_count TINYINT UNSIGNED NOT NULL CHECK (seat_count BETWEEN 1 AND 6),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    paid_at DATETIME DEFAULT NULL,
    INDEX (customer_id, created_at),
    INDEX (schedule_id, status, expires_at),
    UNIQUE (id, schedule_id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id),
    FOREIGN KEY (customer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE booking_seats (
    schedule_id INT UNSIGNED NOT NULL,
    seat_number TINYINT UNSIGNED NOT NULL CHECK (seat_number BETWEEN 1 AND 60),
    booking_id INT UNSIGNED NOT NULL,
    passenger_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (schedule_id, seat_number),
    FOREIGN KEY (booking_id, schedule_id) REFERENCES bookings(id, schedule_id)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    user_id INT UNSIGNED PRIMARY KEY,
    token_hash BINARY(32) UNIQUE,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE auth_limits (
    action VARCHAR(16) CHARACTER SET ascii NOT NULL,
    client_key BINARY(32) NOT NULL,
    window_start INT UNSIGNED NOT NULL,
    attempts INT UNSIGNED NOT NULL,
    PRIMARY KEY (action, client_key, window_start),
    INDEX (window_start)
) ENGINE=InnoDB;

-- Fictional accounts for a local demonstration. Both providers start unverified and their schedules are drafts.
-- See README for the demo password and verification walkthrough.
SET @demo_hash = '$2y$12$6P10IVfBu0QiLMWbI4tFLeh6m1Cv7DUFwH2xOH2RD6J/Wu3TvcWP6';
INSERT INTO users (id, role, full_name, email, password_hash) VALUES
    (1, 'customer', 'Rani Contoh', 'customer@example.com', @demo_hash),
    (2, 'provider', 'Lintas Pagi Travel', 'provider@example.com', @demo_hash),
    (3, 'admin', 'Admin Demo', 'admin@example.com', @demo_hash),
    (4, 'provider', 'Cakrawala Shuttle', 'provider2@example.com', @demo_hash),
    (5, 'customer', 'Dimas Contoh', 'customer2@example.com', @demo_hash);

INSERT INTO routes (id, provider_id, origin, destination, duration_minutes) VALUES
    (1, 2, 'Jakarta', 'Bandung', 180), (2, 2, 'Bandung', 'Jakarta', 180),
    (3, 4, 'Jakarta', 'Bandung', 195), (4, 4, 'Semarang', 'Yogyakarta', 210),
    (5, 2, 'Yogyakarta', 'Surabaya', 360);

INSERT INTO vehicles (id, provider_id, vehicle_name, vehicle_type, plate_number, seat_capacity) VALUES
    (1, 2, 'Lintas Pagi Eksekutif', 'Bus', 'B 4101 TG', 32),
    (2, 2, 'Lintas Pagi Reguler', 'Minibus', 'B 4102 TG', 16),
    (3, 4, 'Cakrawala 01', 'Shuttle', 'D 4103 TG', 12),
    (4, 4, 'Cakrawala 02', 'Minibus', 'H 4104 TG', 14);

INSERT INTO schedules (provider_id, route_id, vehicle_id, departure_at, arrival_at, price) VALUES
    (2, 1, 1, TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '08:00:00'), TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '11:00:00'), 120000),
    (2, 1, 2, TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '14:30:00'), TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '17:30:00'), 95000),
    (4, 3, 3, TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '10:00:00'), TIMESTAMP(CURRENT_DATE + INTERVAL 1 DAY, '13:15:00'), 110000),
    (2, 2, 1, TIMESTAMP(CURRENT_DATE + INTERVAL 2 DAY, '09:00:00'), TIMESTAMP(CURRENT_DATE + INTERVAL 2 DAY, '12:00:00'), 120000),
    (4, 4, 4, TIMESTAMP(CURRENT_DATE + INTERVAL 2 DAY, '07:30:00'), TIMESTAMP(CURRENT_DATE + INTERVAL 2 DAY, '11:00:00'), 85000),
    (2, 5, 1, TIMESTAMP(CURRENT_DATE + INTERVAL 3 DAY, '06:30:00'), TIMESTAMP(CURRENT_DATE + INTERVAL 3 DAY, '12:30:00'), 165000);
