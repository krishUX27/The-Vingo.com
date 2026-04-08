-- ============================================================
--  Menu Manager — Production Database Schema (v3)
--  Optimized for Hostinger Deployment
-- ============================================================

CREATE DATABASE IF NOT EXISTS menu_project
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE menu_project;

-- ── 1. Users ──
CREATE TABLE IF NOT EXISTS users (
  id          INT          NOT NULL AUTO_INCREMENT,
  username    VARCHAR(50)  NOT NULL,
  email       VARCHAR(100) DEFAULT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin', 'superadmin') NOT NULL DEFAULT 'admin',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. Settings (Multi-tenant) ──
CREATE TABLE IF NOT EXISTS settings (
  id            INT          NOT NULL AUTO_INCREMENT,
  user_id       INT          NOT NULL DEFAULT 0,
  setting_key   VARCHAR(50)  NOT NULL,
  setting_value TEXT,
  PRIMARY KEY (id),
  INDEX idx_user_setting (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. Categories (Multi-tenant) ──
CREATE TABLE IF NOT EXISTS categories (
  id      INT          NOT NULL AUTO_INCREMENT,
  user_id INT          NOT NULL DEFAULT 0,
  name    VARCHAR(100) NOT NULL,
  is_deleted TINYINT(1) DEFAULT 0,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  INDEX idx_user_cat (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. Seasonal Offers ──
CREATE TABLE IF NOT EXISTS seasonal_offers (
  id          INT          NOT NULL AUTO_INCREMENT,
  user_id     INT          NOT NULL DEFAULT 0,
  title       VARCHAR(100) NOT NULL,
  description TEXT         DEFAULT NULL,
  discount    VARCHAR(50)  DEFAULT NULL,
  expires_at  DATE         DEFAULT NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_user_offer (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 5. Dishes (Multi-tenant) ──
CREATE TABLE IF NOT EXISTS dishes (
  id           INT            NOT NULL AUTO_INCREMENT,
  user_id      INT            NOT NULL DEFAULT 0,
  name         VARCHAR(150)   NOT NULL,
  price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  category_id  INT            NOT NULL,
  image        VARCHAR(255)   DEFAULT NULL,
  availability ENUM('Available','Not Available') NOT NULL DEFAULT 'Available',
  currency     VARCHAR(10)    DEFAULT 'INR',
  offer_id     INT            DEFAULT NULL,
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_user_dish (user_id),
  CONSTRAINT fk_dish_cat
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 6. System Logs ──
CREATE TABLE IF NOT EXISTS system_logs (
  id          INT          NOT NULL AUTO_INCREMENT,
  event       VARCHAR(100) NOT NULL,
  source      VARCHAR(50)  DEFAULT 'System',
  status      VARCHAR(20)  DEFAULT 'OK',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seed Initial Data ──
INSERT IGNORE INTO users (id, username, password, role) VALUES
  (1, 'superadmin', '$2y$10$7R8WXYuR0FvI/fHh3mK9Xe/S3dY3h8gGg8k4B9vX.iH3G5sZ.y7yS', 'superadmin');

INSERT IGNORE INTO categories (user_id, name) VALUES
  (1, 'Starters'),(1, 'Main Course'),(1, 'Desserts'),(1, 'Beverages');

INSERT IGNORE INTO settings (user_id, setting_key, setting_value) VALUES
  (0, 'restaurant_name', 'Vingo Menu'),
  (0, 'restaurant_sub', 'Premium Digital Menu Solution');
