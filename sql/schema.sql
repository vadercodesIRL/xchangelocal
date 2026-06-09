-- XchangeLocal — Database Schema
-- Engine: MySQL / MariaDB, utf8mb4
-- Run: SOURCE /path/to/schema.sql  (after creating the database)
--
-- Reset sequence (dev only):
--   DROP DATABASE IF EXISTS xchangelocal;
--   CREATE DATABASE xchangelocal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE xchangelocal;
--   SOURCE schema.sql;
--   SOURCE seed.sql;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- users
CREATE TABLE users (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name                VARCHAR(100)    NOT NULL,
    surname             VARCHAR(100)    NOT NULL,
    email               VARCHAR(255)    NOT NULL,
    phone               VARCHAR(20)              DEFAULT NULL,
    date_of_birth       DATE                     DEFAULT NULL,
    password_hash       VARCHAR(255)    NOT NULL,
    role                ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
    can_sell            TINYINT(1)      NOT NULL DEFAULT 0,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    verification_token  VARCHAR(64)              DEFAULT NULL,
    email_verified_at   TIMESTAMP   NULL         DEFAULT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_email (email),
    KEY idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- listings (price stored in cents as BIGINT - never FLOAT)
CREATE TABLE listings (
    id              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    seller_id       INT UNSIGNED        NOT NULL,
    title           VARCHAR(200)        NOT NULL,
    description     TEXT                NOT NULL,
    price_cents     BIGINT UNSIGNED     NOT NULL COMMENT 'ZAR cents — never FLOAT',
    `condition`     ENUM('new','like_new','good','fair','for_parts') NOT NULL,
    location_city   VARCHAR(100)        NOT NULL,
    status          ENUM('active','sold','reserved','removed') NOT NULL DEFAULT 'active',
    is_featured     TINYINT(1)          NOT NULL DEFAULT 0,
    views           INT UNSIGNED        NOT NULL DEFAULT 0,
    image_path      VARCHAR(255)                 DEFAULT NULL,
    image_path_2    VARCHAR(255)                 DEFAULT NULL,
    image_path_3    VARCHAR(255)                 DEFAULT NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_seller_id  (seller_id),
    KEY idx_status     (status),
    KEY idx_created_at (created_at),

    CONSTRAINT fk_listings_seller
        FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- listing images
CREATE TABLE listing_images (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    listing_id  INT UNSIGNED    NOT NULL,
    path        VARCHAR(500)    NOT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    KEY idx_listing_id (listing_id),

    CONSTRAINT fk_images_listing
        FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- orders (price_cents is a snapshot at order time, not live listing price)
CREATE TABLE orders (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    buyer_id    INT UNSIGNED    NOT NULL,
    listing_id  INT UNSIGNED    NOT NULL,
    price_cents          BIGINT UNSIGNED     NOT NULL COMMENT 'Price snapshot at order time',
    commission_rate      DECIMAL(5,2)        NOT NULL DEFAULT 2.00,
    commission_cents     BIGINT UNSIGNED     NOT NULL DEFAULT 0,
    seller_payout_cents  BIGINT UNSIGNED     NOT NULL DEFAULT 0,
    status      ENUM('pending','paid','completed','disputed','cancelled')
                                NOT NULL DEFAULT 'pending',
    notes       TEXT                     DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_buyer_id   (buyer_id),
    KEY idx_listing_id (listing_id),
    KEY idx_status     (status),

    CONSTRAINT fk_orders_buyer
        FOREIGN KEY (buyer_id)   REFERENCES users (id),
    CONSTRAINT fk_orders_listing
        FOREIGN KEY (listing_id) REFERENCES listings (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- reviews (one per reviewer per order, enforced by unique key)
CREATE TABLE reviews (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED    NOT NULL,
    reviewer_id  INT UNSIGNED    NOT NULL,
    reviewee_id  INT UNSIGNED    NOT NULL,
    rating       TINYINT UNSIGNED NOT NULL,
    body         TEXT                     DEFAULT NULL,
    is_hidden    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_order_reviewer (order_id, reviewer_id),
    KEY idx_reviewee_id (reviewee_id),

    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),

    CONSTRAINT fk_reviews_order
        FOREIGN KEY (order_id)    REFERENCES orders (id),
    CONSTRAINT fk_reviews_reviewer
        FOREIGN KEY (reviewer_id) REFERENCES users (id),
    CONSTRAINT fk_reviews_reviewee
        FOREIGN KEY (reviewee_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
