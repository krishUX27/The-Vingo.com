-- ============================================================
--  Menu Manager — database.sql
--  Import: phpMyAdmin → Import this file
-- ============================================================

CREATE DATABASE IF NOT EXISTS menu_project
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE menu_project;
 
-- ── Settings ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
  id            INT          NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(50)  NOT NULL,
  setting_value TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY uq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Admin Accounts (Operator Level) ────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
  id         INT          NOT NULL AUTO_INCREMENT,
  username   VARCHAR(50)  NOT NULL,
  password   VARCHAR(255) NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Superadmin Accounts (Root Level) ───────────────────────────────
CREATE TABLE IF NOT EXISTS superadmins (
  id         INT          NOT NULL AUTO_INCREMENT,
  username   VARCHAR(50)  NOT NULL,
  password   VARCHAR(255) NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_superadmin_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Categories ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
  id   INT          NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cat_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Dishes ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS dishes (
  id           INT            NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150)   NOT NULL,
  price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  category_id  INT            NOT NULL,
  image        VARCHAR(255)   DEFAULT NULL,
  availability ENUM('Available','Not Available') NOT NULL DEFAULT 'Available',
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dish_cat
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seasonal Offers ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS seasonal_offers (
  id       INT          NOT NULL AUTO_INCREMENT,
  title    VARCHAR(100) NOT NULL,
  discount VARCHAR(50)  DEFAULT NULL,
  active   TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seed categories ───────────────────────────────────────────
INSERT IGNORE INTO categories (name) VALUES
  ('Starters'),('Main Course'),('Desserts'),('Beverages');

-- ── Seed dishes ───────────────────────────────────────────────
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Garlic Bread',          2.99, id, NULL, 'Available'     FROM categories WHERE name='Starters'    LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Paneer Tikka',          8.99, id, NULL, 'Available'     FROM categories WHERE name='Starters'    LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Grilled Chicken',      12.99, id, NULL, 'Available'     FROM categories WHERE name='Main Course' LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Dal Makhani',           9.50, id, NULL, 'Available'     FROM categories WHERE name='Main Course' LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Chocolate Lava Cake',   5.99, id, NULL, 'Available'     FROM categories WHERE name='Desserts'    LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Gulab Jamun',           3.99, id, NULL, 'Not Available' FROM categories WHERE name='Desserts'    LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Fresh Lemonade',        2.49, id, NULL, 'Available'     FROM categories WHERE name='Beverages'   LIMIT 1;
INSERT INTO dishes (name, price, category_id, image, availability)
SELECT 'Mango Lassi',           2.99, id, NULL, 'Available'     FROM categories WHERE name='Beverages'   LIMIT 1;

-- ── Seed System Data ───────────────────────────────────────────
-- Default Passwords: admin123, super123 (hashed using php PASSWORD_DEFAULT)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('restaurant_name', 'Vingo Menu'),
  ('restaurant_sub', 'The Future of Digital Menus'),
  ('contact_email', 'hello@thevingo.com');

INSERT IGNORE INTO admins (username, password) VALUES
  ('admin', '$2y$10$7R8WXYuR0FvI/fHh3mK9Xe/S3dY3h8gGg8k4B9vX.iH3G5sZ.y7yS');

INSERT IGNORE INTO superadmins (username, password) VALUES
  ('superadmin', '$2y$10$7R8WXYuR0FvI/fHh3mK9Xe/S3dY3h8gGg8k4B9vX.iH3G5sZ.y7yS');
