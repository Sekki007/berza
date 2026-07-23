-- TelefonBerza — MySQL schema (utf8mb4)
-- Import: mysql -u root -p < database/schema.sql
-- Zatim: php tools/import_json_to_mysql.php

CREATE DATABASE IF NOT EXISTS berza CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE berza;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS ad_stats_daily;
DROP TABLE IF EXISTS ad_stats;
DROP TABLE IF EXISTS saved_searches;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS credit_transactions;
DROP TABLE IF EXISTS credit_deposits;
DROP TABLE IF EXISTS top_orders;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS ads;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    email_verified_at DATETIME NULL,
    email_verify_token VARCHAR(64) NULL,
    email_verify_sent_at DATETIME NULL,
    notify_email TINYINT(1) NOT NULL DEFAULT 1,
    shop_name VARCHAR(120) NULL,
    shop_bio TEXT NULL,
    location VARCHAR(120) NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    blocked_reason VARCHAR(255) NULL,
    verified_seller TINYINT(1) NOT NULL DEFAULT 0,
    verified_seller_at DATETIME NULL,
    google_id VARCHAR(64) NULL UNIQUE,
    credits INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE ads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    ad_type ENUM('telefon', 'delovi', 'servis') NOT NULL DEFAULT 'telefon',
    category VARCHAR(80) NOT NULL DEFAULT 'Telefoni',
    category_group VARCHAR(60) NULL,
    brand VARCHAR(60) NULL,
    model VARCHAR(120) NULL,
    storage VARCHAR(40) NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    condition_state VARCHAR(50) NOT NULL DEFAULT 'Polovno',
    location VARCHAR(120) NOT NULL,
    country VARCHAR(60) NOT NULL DEFAULT 'Srbija',
    contact_phone VARCHAR(40) NULL,
    shop_name VARCHAR(120) NULL,
    badge VARCHAR(60) NULL,
    images_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_sold TINYINT(1) NOT NULL DEFAULT 0,
    is_promoted TINYINT(1) NOT NULL DEFAULT 0,
    promoted_until DATETIME NULL,
    is_highlighted TINYINT(1) NOT NULL DEFAULT 0,
    highlighted_until DATETIME NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NULL,
    expiry_warned_at DATETIME NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ads_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_ads_active (is_active, is_sold),
    INDEX idx_ads_type (ad_type),
    INDEX idx_ads_location (location),
    INDEX idx_ads_brand (brand),
    INDEX idx_ads_promoted (is_promoted, promoted_until),
    FULLTEXT INDEX ft_ads_search (title, description, brand, model)
) ENGINE=InnoDB;

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ad_id INT UNSIGNED NOT NULL,
    from_user_id INT UNSIGNED NULL,
    from_name VARCHAR(120) NULL,
    from_phone VARCHAR(40) NULL,
    to_user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_ad FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_messages_to FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_thread (ad_id, from_user_id, to_user_id),
    INDEX idx_messages_to_unread (to_user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE favorites (
    user_id INT UNSIGNED NOT NULL,
    ad_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, ad_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_ad FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ratings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_id INT UNSIGNED NOT NULL,
    from_user_id INT UNSIGNED NOT NULL,
    vote ENUM('positive', 'negative') NOT NULL,
    score TINYINT NULL,
    comment TEXT NULL,
    ad_id INT UNSIGNED NULL,
    conversation_key VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ratings_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ratings_from FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ratings_seller (seller_id)
) ENGINE=InnoDB;

CREATE TABLE reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('ad', 'user') NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    from_user_id INT UNSIGNED NULL,
    from_name VARCHAR(120) NULL,
    reason VARCHAR(120) NOT NULL,
    details TEXT NULL,
    status ENUM('open', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
    admin_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_reports_status (status)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_unread (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE top_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    ad_id INT UNSIGNED NOT NULL,
    package_id VARCHAR(40) NOT NULL,
    days INT UNSIGNED NOT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    paid_with VARCHAR(30) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    CONSTRAINT fk_top_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_top_ad FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    INDEX idx_top_status (status)
) ENGINE=InnoDB;

CREATE TABLE credit_deposits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    CONSTRAINT fk_dep_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_dep_status (status)
) ENGINE=InnoDB;

CREATE TABLE credit_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount INT NOT NULL,
    type VARCHAR(40) NOT NULL,
    note VARCHAR(255) NULL,
    meta_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ctx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ctx_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE saved_searches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NULL,
    filters_json JSON NOT NULL,
    alert_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_match_ids_json JSON NULL,
    last_checked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ss_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ad_stats (
    ad_id INT UNSIGNED PRIMARY KEY,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    phone_reveals INT UNSIGNED NOT NULL DEFAULT 0,
    messages_started INT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_stats_ad FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ad_stats_daily (
    ad_id INT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    phone_reveals INT UNSIGNED NOT NULL DEFAULT 0,
    messages_started INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (ad_id, day),
    CONSTRAINT fk_stats_daily_ad FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value JSON NOT NULL
) ENGINE=InnoDB;

-- Demo admin (lozinka: admin123)
INSERT INTO users (id, username, password_hash, full_name, phone, is_admin, credits)
VALUES (
    1,
    'admin',
    '$2y$10$IePWFxxngm51mSE78bxi8.44l4n7pWf.8kmDDHmmcf9WSODhPPZfK',
    'Administrator',
    '0601234567',
    1,
    0
) ON DUPLICATE KEY UPDATE username = username;
