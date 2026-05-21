-- =============================================================================
-- Uto Car Rentals / Uto Mobility — MySQL 8 schema for Laravel Forge
-- =============================================================================
-- Charset: utf8mb4 (full Unicode, emoji-safe)
-- Engine: InnoDB
--
-- Preferred on Forge: run migrations (keeps doctrine_migration_versions in sync)
--   php bin/console doctrine:migrations:migrate --no-interaction --env=prod
--
-- Use this file when you need a reference, manual import, or empty DB bootstrap.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Optional: create database and user (adjust names/passwords for your server)
-- Forge usually creates the database for you — skip this block if DB exists.
-- -----------------------------------------------------------------------------
-- CREATE DATABASE IF NOT EXISTS uto_carrentals
--   CHARACTER SET utf8mb4
--   COLLATE utf8mb4_unicode_ci;
-- USE uto_carrentals;
--
-- CREATE USER IF NOT EXISTS 'uto_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
-- GRANT ALL PRIVILEGES ON uto_carrentals.* TO 'uto_app'@'localhost';
-- FLUSH PRIVILEGES;

-- -----------------------------------------------------------------------------
-- Doctrine migrations tracking (created automatically by migrate command)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctrine_migration_versions (
    version VARCHAR(191) NOT NULL,
    executed_at DATETIME DEFAULT NULL,
    execution_time INT DEFAULT NULL,
    PRIMARY KEY (version)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Core tables
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS login (
    id INT AUTO_INCREMENT NOT NULL,
    username VARCHAR(180) NOT NULL,
    roles JSON NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1 NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0 NOT NULL,
    verification_token VARCHAR(255) DEFAULT NULL,
    display_name VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(32) DEFAULT NULL,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_expires_at DATETIME DEFAULT NULL,
    UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username),
    UNIQUE INDEX UNIQ_LOGIN_EMAIL (email),
    UNIQUE INDEX UNIQ_LOGIN_RESET_TOKEN (reset_token),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT NOT NULL,
    username VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone_number INT NOT NULL,
    address VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    roles JSON NOT NULL,
    UNIQUE INDEX UNIQ_8D93D649F85E0677 (username),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS car_inventory (
    id INT AUTO_INCREMENT NOT NULL,
    created_by_id INT DEFAULT NULL,
    brand VARCHAR(255) NOT NULL,
    model VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    price_per_day INT NOT NULL,
    status VARCHAR(255) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    transmission VARCHAR(20) DEFAULT NULL,
    passenger_seats INT DEFAULT NULL,
    INDEX IDX_B6CFB81AB03A8386 (created_by_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS booking (
    id INT AUTO_INCREMENT NOT NULL,
    car_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_by_id INT DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(32) DEFAULT NULL,
    pickup_location VARCHAR(255) NOT NULL,
    dropoff_location VARCHAR(255) NOT NULL,
    pickup_date DATETIME NOT NULL,
    return_date DATETIME NOT NULL,
    pickup_time TIME NOT NULL,
    return_time TIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    INDEX IDX_E00CEDDEC3C6F69F (car_id),
    INDEX IDX_E00CEDDEA76ED395 (user_id),
    INDEX IDX_E00CEDDEB03A8386 (created_by_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS payment (
    id INT AUTO_INCREMENT NOT NULL,
    car_id INT DEFAULT NULL,
    created_by_id INT DEFAULT NULL,
    booking_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(255) NOT NULL,
    amount_due INT NOT NULL DEFAULT 0,
    amount_paid INT DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    INDEX IDX_6D28840DC3C6F69F (car_id),
    INDEX IDX_6D28840DB03A8386 (created_by_id),
    INDEX IDX_6D28840D3301C60 (booking_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT NOT NULL,
    user VARCHAR(255) DEFAULT NULL,
    role VARCHAR(255) DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    date_time DATETIME NOT NULL,
    entity_type VARCHAR(255) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS car_favorite (
    id INT AUTO_INCREMENT NOT NULL,
    login_id INT NOT NULL,
    car_id INT NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_CAR_FAVORITE_LOGIN (login_id),
    INDEX IDX_CAR_FAVORITE_CAR (car_id),
    UNIQUE INDEX UNIQ_FAVORITE_USER_CAR (login_id, car_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS car_review (
    id INT AUTO_INCREMENT NOT NULL,
    login_id INT NOT NULL,
    car_id INT NOT NULL,
    rating SMALLINT NOT NULL,
    comment LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_CAR_REVIEW_LOGIN (login_id),
    INDEX IDX_CAR_REVIEW_CAR (car_id),
    UNIQUE INDEX UNIQ_REVIEW_USER_CAR (login_id, car_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS app_notification (
    id INT AUTO_INCREMENT NOT NULL,
    recipient_id INT NOT NULL,
    booking_id INT DEFAULT NULL,
    type VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    link_route VARCHAR(128) DEFAULT NULL,
    link_params JSON DEFAULT NULL,
    read_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_NOTIF_RECIPIENT_READ (recipient_id, read_at),
    INDEX IDX_NOTIF_BOOKING_TYPE (booking_id, type),
    INDEX IDX_NOTIF_CREATED (created_at),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Symfony Messenger (async queue) — optional if MESSENGER_TRANSPORT_DSN=doctrine://...
CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGINT AUTO_INCREMENT NOT NULL,
    body LONGTEXT NOT NULL,
    headers LONGTEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_75EA56E0FB7336F0 (queue_name),
    INDEX IDX_75EA56E0E3BD61CE (available_at),
    INDEX IDX_75EA56E016BA31DB (delivered_at),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- -----------------------------------------------------------------------------
-- Foreign keys
-- -----------------------------------------------------------------------------

ALTER TABLE car_inventory
    ADD CONSTRAINT FK_B6CFB81AB03A8386 FOREIGN KEY (created_by_id) REFERENCES login (id);

ALTER TABLE booking
    ADD CONSTRAINT FK_E00CEDDEC3C6F69F FOREIGN KEY (car_id) REFERENCES car_inventory (id),
    ADD CONSTRAINT FK_E00CEDDEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id),
    ADD CONSTRAINT FK_E00CEDDEB03A8386 FOREIGN KEY (created_by_id) REFERENCES login (id);

ALTER TABLE payment
    ADD CONSTRAINT FK_6D28840DC3C6F69F FOREIGN KEY (car_id) REFERENCES car_inventory (id) ON DELETE SET NULL,
    ADD CONSTRAINT FK_6D28840DB03A8386 FOREIGN KEY (created_by_id) REFERENCES login (id),
    ADD CONSTRAINT FK_6D28840D3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL;

ALTER TABLE car_favorite
    ADD CONSTRAINT FK_CAR_FAVORITE_LOGIN FOREIGN KEY (login_id) REFERENCES login (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_CAR_FAVORITE_CAR FOREIGN KEY (car_id) REFERENCES car_inventory (id) ON DELETE CASCADE;

ALTER TABLE car_review
    ADD CONSTRAINT FK_CAR_REVIEW_LOGIN FOREIGN KEY (login_id) REFERENCES login (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_CAR_REVIEW_CAR FOREIGN KEY (car_id) REFERENCES car_inventory (id) ON DELETE CASCADE;

ALTER TABLE app_notification
    ADD CONSTRAINT FK_NOTIF_RECIPIENT FOREIGN KEY (recipient_id) REFERENCES login (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_NOTIF_BOOKING FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
